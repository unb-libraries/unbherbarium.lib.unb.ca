<?php

namespace Drupal\herbarium_specimen\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

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
      '#upload_location' => "private://dzi/$node/",
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
    $fid = $form_state->getValue('tiff_file')[0];
    $nid = $form_state->getValue('nid');

    $batch = array(
      'title' => t('Generating Specimen Surrogate Images'),
      'init_message' => t('Generating Specimen Surrogate Images'),
      'operations' => array(

        array(
          array(
            'Drupal\herbarium_specimen\HerbariumImageSurrogateFactory',
            'deleteExistingAssets',
          ),
          array($fid, $nid),
        ),

        array(
          array(
            'Drupal\herbarium_specimen\HerbariumImageSurrogateFactory',
            'buildJpgSurrogate',
          ),
          array($fid, $nid),
        ),

        array(
          array(
            'Drupal\herbarium_specimen\HerbariumImageSurrogateFactory',
            'attachSurrogatesToNode',
          ),
          array($fid, $nid),
        ),

        array(
          array(
            'Drupal\herbarium_specimen\HerbariumImageSurrogateFactory',
            'buildDziTiles',
          ),
          array($fid, $nid),
        ),

        array(
          array(
            'Drupal\herbarium_specimen\HerbariumImageSurrogateFactory',
            'cleanupFiles',
          ),
          array($fid, $nid),
        ),
      ),
    );

    batch_set($batch);
  }

}
