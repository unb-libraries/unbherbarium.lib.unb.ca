<?php

namespace Drupal\herbarium_specimen\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\pmportal_video\Captions\VideoCaptionLine;
use Drupal\pmportal_video\Captions\VideoCaptionSet;
use Drupal\views\Views;

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
   *
   * @TODO: Update legacy code to use theme_form().
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {
    $form = array();

    $form['tiff_file'] = array(
      '#title' => t('TIF File'),
      '#type' => 'managed_file',
      '#description' => t('Upload a archival master file, allowed extensions: TIF TIFF'),
      '#upload_location' => 'private://srt_upload/',
      '#required' => TRUE,
      '#upload_validators' => array(
        'file_validate_extensions' => array('tif', 'tiff'),
      ),
    );

    $form['nid'] = array(
      '#type' => 'hidden',
      '#value' => isset($node) && is_numeric($node) ? $node : -1,
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Upload Archival Master'),
    );

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
  }

}
