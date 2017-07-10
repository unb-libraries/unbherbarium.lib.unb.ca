<?php

/**
 * @file
 * Drush commands related to herbarium_specimen_lts.
 */

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
      'overwrite_existing' => 'Should the import overwrite existing LTS images.',
    ],
    'drupal dependencies' => ['herbarium_specimen_lts'],
    'aliases' => ['herbarium:import-lts-images'],
  ];
  return $items;
}

/**
 * Import tif images into requisite records and generate all surrogates.
 */
function drush_herbarium_specimen_lts_import_lts_images($path = NULL, $overwrite_existing = FALSE) {

  // Make sure this isn't being run as anonymous.
  $user = User::load(\Drupal::currentUser()->id());
  if ($user->id() == 0) {
    drush_set_error(t('This command must be run in Drush with the -u (UID) argument.'));
    die();
  }

  // Check the LTS storage status.
  list($storage_status, $error_message) = HerbariumImageLtsArchiver::checkStorageStatus();
  if ($error_message) {
    drush_set_error($error_message);
    die();
  }

  // Find files in source directory.
  $directory = new RecursiveDirectoryIterator("$path/");
  $iterator = new RecursiveIteratorIterator($directory);
  $regex = new RegexIterator($iterator, '/^.+\.tif$/i', RecursiveRegexIterator::GET_MATCH);

  foreach ($regex as $tif_files) {
    foreach ($tif_files as $file_path) {
      $path_parts = pathinfo($file_path);
      $nid = _get_nid_from_accession($path_parts['filename']);

      if (!$nid) {
        drush_set_error('No node found for accession ID ' . $path_parts['filename'] . '!');
      }
      else {
        $temp_fid = _add_temp_file_filesystem($file_path);
        if ($temp_fid) {
          batch_set(
            _herbarium_specimen_generate_specimen_surrogates_batch($nid, $temp_fid)
          );

          batch_set(
            _herbarium_specimen_lts_store_new_image($nid, $temp_fid)
          );
        }
      }
    }
  }
  drush_backend_batch_process();
}

function _get_nid_from_accession($accession_id) {
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

function _add_temp_file_filesystem($source, $destination = 'public') {
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
