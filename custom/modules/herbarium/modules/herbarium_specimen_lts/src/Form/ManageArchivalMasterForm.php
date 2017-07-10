<?php

namespace Drupal\herbarium_specimen_lts\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\herbarium_specimen_lts\HerbariumImageLtsArchiver;

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

    list($storage_status, $error_message) = HerbariumImageLtsArchiver::checkStorageStatus();
    if ($error_message) {
      drupal_set_message($error_message, 'error');
    }

    $form['description'] = [
      '#markup' => 'The archival master serves as the canonical version of the digital specimen scan.',
    ];

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

    batch_set(
      _herbarium_specimen_generate_specimen_surrogates_batch($nid, $fid)
    );

    batch_set(
      _herbarium_specimen_lts_store_new_image($nid, $fid)
    );
  }

}
