<?php

namespace Drupal\herbarium_specimen_lts\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\herbarium_specimen_lts\HerbariumImageLtsArchiver;
use Drupal\Core\Site\Settings;
use Drupal\file\Entity\File;

/**
 * ManageArchivalMasterForm object.
 */
class ManageArchivalMasterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'herbarium_specimen_manage_archival_master_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {
    $form = [];

    if (trim(Settings::get('specimen_lts_archive') == '')) {
      drupal_set_message(
        t('WARNING: Settings for a LFS storage server have not been detected. Any changes to the archival master for this specimen will not be stored in the permanent archive.'),
        'warning'
      );

      // Allow the form to be submitted, but LFS storage will be skipped.
      $storage_status = TRUE;
    }
    else {
      list($storage_status, $error_message) = HerbariumImageLtsArchiver::checkStorageStatus();
      if ($error_message) {
        drupal_set_message($error_message, 'error');
      }
    }

    $form['description'] = [
      '#markup' => 'The archival master serves as the canonical version of the digital specimen scan.',
    ];

    $history_rows = HerbariumImageLtsArchiver::getFileHistory($node);
    if (!empty($history_rows)) {
      $header = [
        t('Date'),
        t('Author'),
        t('Email'),
      ];

      $rows = [];
      foreach ($history_rows as $row) {
        $rows[] = ['data' => (array) $row];
      }

      $form = [
        '#markup' => t('List of All locations'),
      ];

      $form['history_table'] = [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ];

      $form['pager'] = [
        '#type' => 'pager',
      ];
    }

    $form['tiff_file'] = [
      '#disabled' => !$storage_status,
      '#title' => t('TIF File'),
      '#type' => 'managed_file',
      '#description' => t('Upload a archival master file, allowed extensions: TIF TIFF'),
      '#upload_location' => "private://dzi/$node/",
      '#required' => TRUE,
      '#upload_validators' => [
        'file_validate_extensions' => ['tif', 'tiff'],
      ],
    ];

    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => isset($node) && is_numeric($node) ? $node : -1,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#disabled' => !$storage_status,
      '#value' => t('Upload Archival Master'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $fid = $form_state->getValue('tiff_file')[0];
    $nid = $form_state->getValue('nid');

    $file = File::Load($fid);
    $file_path = drupal_realpath($file->getFileUri());

    $batch = [
      'title' => t('Updating Archive Images'),
      'init_message' => t('Updating Archive Images'),
      'operations' => [],
    ];

    // Image surrogates.
    $surrogates_batch = _herbarium_specimen_generate_specimen_surrogates_batch($nid, $file_path);
    $batch['operations'] = array_merge($batch['operations'], $surrogates_batch['operations']);

    // Only process file for LTS if we have a server set.
    if (trim(Settings::get('specimen_lts_archive') != '')) {
      $lts_batch = _herbarium_specimen_lts_store_new_image($nid, $file_path);
      $batch['operations'] = array_merge($batch['operations'], $lts_batch['operations']);
    }

    // Start the batch.
    batch_set($batch);
  }

}
