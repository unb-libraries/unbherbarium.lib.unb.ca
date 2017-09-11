<?php

/**
 * @file
 * Drush commands related to herbarium_specimen_lts.
 */

use Drupal\Core\Site\Settings;
use Drupal\file\Entity\File;
use Drupal\herbarium_specimen_lts\HerbariumImageLtsArchiver;
use Drupal\user\Entity\User;

/**
 * Implements hook_drush_command().
 */
function herbarium_specimen_lts_drush_command() {
  $items = [];

  $items['push-lts-repo'] = [
    'description' => 'Push the LTS repo to the remote.',
    'drupal dependencies' => ['herbarium_specimen_lts'],
    'aliases' => ['herbarium:push-lts-repo'],
  ];

  $items['queue-lts-images'] = [
    'description' => 'Queue the images in a path for an LTS import.',
    'arguments' => [
      'path' => 'The path to import the LTS images from.',
    ],
    'drupal dependencies' => ['herbarium_specimen_lts'],
    'aliases' => ['herbarium:queue-lts-images'],
  ];

  $items['regenerate-surrogates'] = [
    'description' => 'Regenerate the surrogate images for a specimen.',
    'arguments' => [
      'nid' => 'The NID of the specimen to generate.',
    ],
    'drupal dependencies' => ['herbarium_specimen_lts'],
    'aliases' => ['herbarium:regenerate-surrogates'],
  ];

  $items['import-lts-images'] = [
    'description' => 'Import LTS images that have been pre-populated in the queue.',
    'options' => [
      'ignore-existing' => 'Should the import skip existing LTS images that exist in the git repo?',
      'limit' => 'Limit the number of TIF files to import. (Default is 10).',
    ],
    'drupal dependencies' => ['herbarium_specimen_lts'],
    'aliases' => ['herbarium:import-lts-images'],
  ];

  return $items;
}

/**
 * Push the LTS repo to the remote master.
 */
function drush_herbarium_specimen_lts_push_lts_repo() {
  // User root should almost never be running this, rather the web daemon user.
  $processUser = posix_getpwuid(posix_geteuid());
  if ($processUser['name'] == 'root') {
    drush_set_error(t('This command should almost never be run as root. Try su.'));
    die();
  }

  $batch = [
    'title' => t('Pushing LFS Repository'),
    'init_message' => t('Pushing LFS Repository'),
    'progressive' => FALSE,
    'operations' => [
      [
        [
          'Drupal\herbarium_specimen_lts\HerbariumImageLtsArchiver',
          'pushLfs',
        ],
        [],

      ],
    ],
  ];

  batch_set($batch);
  drush_backend_batch_process();
}

/**
 * Generate surrogates for a TIF, attach to node and send image to LTS server.
 *
 * @param string $path
 *   The path containing the archival master files.
 */
function drush_herbarium_specimen_lts_queue_lts_images($path = NULL) {
  ini_set('memory_limit', '4G');

  // User root should almost never be running this, rather the web daemon user.
  $processUser = posix_getpwuid(posix_geteuid());
  if ($processUser['name'] == 'root') {
    drush_set_error(t('This command should almost never be run as root. Try su.'));
    die();
  }

  // Make sure this isn't being run as anonymous.
  $user = User::load(\Drupal::currentUser()->id());
  if ($user->id() == 0) {
    drush_set_error(t('This command must be run in Drush with the -u (UID) argument.'));
    die();
  }

  // Find files in source directory.
  $directory = new RecursiveDirectoryIterator("$path/");
  $iterator = new RecursiveIteratorIterator($directory);
  $regex = new RegexIterator($iterator, '/^.+\/[0-9]+\.tif$/i', RecursiveRegexIterator::GET_MATCH);
  $limit = drush_get_option('limit', 10);

  foreach ($regex as $tif_file) {
    foreach ($tif_file as $file_path) {
      db_merge(HERBARIUM_SPECIMEN_LTS_QUEUE_TABLE)
        ->key(['file' => $file_path])
        ->fields(['status' => HERBARIUM_SPECIMEN_LTS_QUEUE_STATUS_UNPROCESSED])
        ->execute();
    }
  }
}

/**
 * Generate surrogates for a TIF, attach to node and send image to LTS server.
 *
 * @param string $path
 *   The path containing the archival master files.
 */
function drush_herbarium_specimen_lts_import_lts_images($path = NULL) {
  ini_set('memory_limit', '4G');

  // User root should almost never be running this, rather the web daemon user.
  $processUser = posix_getpwuid(posix_geteuid());
  if ($processUser['name'] == 'root') {
    drush_set_error(t('This command should almost never be run as root. Try su.'));
    die();
  }

  // Make sure this isn't being run as anonymous.
  $user = User::load(\Drupal::currentUser()->id());
  if ($user->id() == 0) {
    drush_set_error(t('This command must be run in Drush with the -u (UID) argument.'));
    die();
  }

  // Check the LFS storage configuration.
  if (trim(Settings::get('specimen_lts_archive') == '')) {
    drush_set_error(
      t('WARNING: Settings for a LFS storage server have not been detected. Any changes to the archival master for this specimen will not be stored in the permanent archive.'));
    sleep(3);
  }
  else {
    // LTS storage was configured, check the status.
    list($storage_status, $error_message) = HerbariumImageLtsArchiver::checkStorageStatus();
    if ($error_message) {
      drush_set_error($error_message);
      die();
    }
  }

  // Find files in source directory.
  $limit = drush_get_option('limit', 10);
  $import_counter = 0;

  while ($import_counter < $limit) {
    // Start a transaction.
    $transaction = db_transaction();

    // Lock the table when selecting to avoid race conditions.
    $sql = 'SELECT file FROM ' . HERBARIUM_SPECIMEN_LTS_QUEUE_TABLE . " WHERE status=" . HERBARIUM_SPECIMEN_LTS_QUEUE_STATUS_UNPROCESSED . " LIMIT 1 OFFSET $import_counter FOR UPDATE";

    foreach (db_query($sql) as $result) {
      $file_path = $result->file;
    }

    // Update the status as 'in process'.
    _herbarium_specimen_lts_set_file_status($file_path, HERBARIUM_SPECIMEN_LTS_QUEUE_STATUS_PROCESSING);

    // Release the select lock on the table.
    unset($transaction);

    $batch = [
      'title' => t('Updating Archive Images'),
      'init_message' => t('Updating Archive Images'),
      'progressive' => FALSE,
      'operations' => [],
    ];

    $path_parts = pathinfo($file_path);

    // Check if a node with the accession ID exists.
    $nid = _herbarium_specimen_lts_get_nid_from_accession($path_parts['filename']);

    // No NID found, check to see if leading zeroes were added.
    if (!$nid) {
      $trimmed_nid = ltrim($path_parts['filename'], '0');
      $nid = _herbarium_specimen_lts_get_nid_from_accession($trimmed_nid);
    }

    // We cannot proceed here - no node to attach surrogates to. Critical.
    if (!$nid) {
      drush_set_error(
        t(
          'No node found for accession ID @acc_id in file @file_path',
          [
            '@acc_id' => $path_parts['filename'],
            '@file_path' => $file_path,
          ]
        )
      );

      // Set status in DB.
      _herbarium_specimen_lts_set_file_status($file_path, HERBARIUM_SPECIMEN_LTS_QUEUE_STATUS_FAIL_NO_NODE_ACCID);
    }
    else {
      if (!_herbarium_specimen_lts_get_if_already_imported($nid)) {
        if (file_exists($file_path)) {
          $import_counter++;

          // Image surrogates.
          $surrogates_batch = _herbarium_specimen_generate_specimen_surrogates_batch($nid, $file_path);
          $batch['operations'] = array_merge($batch['operations'],
            $surrogates_batch['operations']);

          // Only process file for LTS if we have a server set.
          if (trim(Settings::get('specimen_lts_archive') != '')) {
            $lts_batch = _herbarium_specimen_lts_store_new_image($nid, $file_path, "[$nid] Batch import of archival file.");
            $batch['operations'] = array_merge($batch['operations'],
              $lts_batch['operations']);
          }
        }
      }
    }

    if (!empty($batch['operations'])) {
      batch_set($batch);
    }
  }

  drush_print("Limit of $limit files reached. Stopping batch loading.");

  // Process the batches we've created.
  drush_backend_batch_process();
}

/**
 * Get a specimen NID from an UNB Herbarium accession ID.
 *
 * @param string $accession_id
 *   The accession ID to query..
 *
 * @return mixed
 *   The NID of the corresponding specimen, or FALSE on no match.
 */
function _herbarium_specimen_lts_get_nid_from_accession($accession_id) {
  $query = \Drupal::entityQuery('node');
  $query->condition('field_dwc_record_number', $accession_id);
  $nids = $query->execute();
  if (!empty($nids)) {
    foreach ($nids as $nid) {
      return $nid;
    }
  }
  else {
    return FALSE;
  }
}

/**
 * Get a specimen NID from an UNB Herbarium accession ID.
 *
 * @param int $nid
 *   The node ID to query.
 *
 * @return bool
 *   TRUE if the archive has already been imported, FALSE otherwise.
 */
function _herbarium_specimen_lts_get_if_already_imported($nid) {
  $query = \Drupal::entityQuery('node');
  $query->condition('nid', $nid);
  $query->condition('field_herbarium_spec_master_impo', TRUE);
  $nids = $query->execute();
  if (!empty($nids)) {
    return TRUE;
  }
  else {
    return FALSE;
  }
}

/**
 * Add a temporary file to the Drupal filesystem and return the FID.
 *
 * @param string $source
 *   The path to the file to add.
 * @param string $destination
 *   The file namespace to add the file to.
 *
 * @return mixed
 *   The FID of the added file, or FALSE if the $source file did not exist.
 */
function _herbarium_specimen_lts_add_temp_file_filesystem($source, $destination = 'public') {
  $file_basename = basename($source);
  $file_destination = "$destination://$file_basename";

  if (file_exists($source)) {
    $file_uri = file_unmanaged_copy($source, $file_destination, FILE_EXISTS_REPLACE);

    $file = File::Create([
      'uri' => $file_uri,
    ]);
    $file->setPermanent();
    $file->save();

    return $file->id();
  }
  else {
    return FALSE;
  }
}

/**
 * Regenerate surrogates for an existing specimen with LTS attached.
 *
 * @param int $nid
 *   The node ID to regenerate.
 */
function drush_herbarium_specimen_lts_regenerate_surrogates($nid) {
  // User root should almost never be running this, rather the web daemon user.
  $processUser = posix_getpwuid(posix_geteuid());
  if ($processUser['name'] == 'root') {
    drush_set_error(t('This command should almost never be run as root. Try su.'));
    die();
  }

  $batch = _herbarium_specimen_lts_regenerate_specimen_derivatives_batch($nid);

  batch_set($batch);
  drush_backend_batch_process();
}
