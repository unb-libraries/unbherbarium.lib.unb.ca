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
  $items = array();
  $items['import-lts-images'] = [
    'description' => 'Import LTS images to samples.',
    'arguments' => [
      'path' => 'The path to import the LTS images from.',
      'overwrite_existing' => 'Should the import overwrite existing LTS images in the git repo?',
    ],
    'drupal dependencies' => ['herbarium_specimen_lts'],
    'aliases' => ['herbarium:import-lts-images'],
  ];
  return $items;
}

/**
 * Generate surrogates for a TIF, attach to node and send image to LTS server.
 *
 * @param string $path
 *   The path containing the archival master files.
 * @param bool $overwrite_existing
 *   If archival masters exist for this file, should we replace them?
 */
function drush_herbarium_specimen_lts_import_lts_images($path = NULL, $overwrite_existing = FALSE) {

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
  $directory = new RecursiveDirectoryIterator("$path/");
  $iterator = new RecursiveIteratorIterator($directory);
  $regex = new RegexIterator($iterator, '/^.+\.tif$/i', RecursiveRegexIterator::GET_MATCH);

  foreach ($regex as $tif_files) {
    // Loop over files here.
    foreach ($tif_files as $file_path) {
      $path_parts = pathinfo($file_path);

      // Check if a node with the accession ID exists.
      $nid = _herbarium_specimen_lts_get_nid_from_accession($path_parts['filename']);
      if (!$nid) {
        // We cannot proceed here - no node to attach surrogates to. Critical.
        drush_set_error(
          t(
            'No node found for accession ID @acc_id in file @file_path',
            [
              '@acc_id' => $path_parts['filename'],
              '@file_path' => $file_path,
            ]
          )
        );
        die();
      }
      else {
        // Upload the file into a temporary FS.
        $temp_fid = _herbarium_specimen_lts_add_temp_file_filesystem($file_path);

        // If upload was successful, start with surrogates.
        if ($temp_fid) {
          batch_set(
            _herbarium_specimen_generate_specimen_surrogates_batch($nid, $temp_fid)
          );

          // Only process file for LTS if we have a server set.
          if (trim(Settings::get('specimen_lts_archive') != '')) {
            batch_set(
              _herbarium_specimen_lts_store_new_image($nid, $temp_fid)
            );
          }
        }
      }
    }
  }

  // Start the batch.
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
    $file->save();

    return $file->id();
  }
  else {
    return FALSE;
  }
}