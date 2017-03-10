<?php

namespace Drupal\herbarium_specimen\Form;

use \Drupal\file\Entity\File;
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
      '#upload_location' => 'private://archival_master_upload/',
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
    // Validate structure of SRT file.
    $fid = $form_state->getValue('tiff_file')[0];
    $tiff_file = File::load($fid);

    $batch = array(
      'title' => t('Generating Specimen Surrogate Images'),
      'init_message' => t('Generating Specimen Surrogate Images'),
      'operations' => array(
        array(
          array(
            'Drupal\herbarium_specimen\HerbariumImageTileFactory',
            'buildJPGSurrogate',
          ),
          array($tiff_file),
        ),
        array(
          array(
            'Drupal\herbarium_specimen\HerbariumImageTileFactory',
            'buildImageTiles',
          ),
          array($tiff_file),
        ),
      ),
    );

    batch_set($batch);
  }

}
