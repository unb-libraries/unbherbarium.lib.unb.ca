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
      '#type' => 'fieldset',
      '#title' => $this->t('Information'),
    ];

    $form['description']['description'] = [
      '#markup' => '<p>' . t('The specimen archival image serves as the digital preservation copy for the herbarium specimen. The images are stored in a separate repository and version controlled to ensure integrity. Additionally, all images used in the site are derived from this master file.') . '</p>',
    ];

    $form['master_history'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Audit History'),
    ];

    $history_rows = HerbariumImageLtsArchiver::getFileHistory($node);
    if (!empty($history_rows)) {
      // Construct header.
      $header = [
        t('Date'),
        t('User'),
        t('Change'),
      ];

      // Build the rows.
      $rows = [];
      foreach ($history_rows as $row) {
        $rows[] = ['data' => (array) $row];
      }

      $form['master_history']['history_table'] = [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ];

      $form['master_history']['pager'] = [
        '#type' => 'pager',
      ];
    }
    else {
      $form['master_history']['none_found'] = [
        '#markup' => t('No archival images have been attached to this specimen yet.'),
      ];
    }

    $form['upload_new'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Upload New Archival Master'),
    ];

    $form['upload_new']['details'] = [
      '#markup' => '<p>' . t('If you wish to add a new or replace an existing archival image, upload one below. Adding a new archival master will trigger several tasks and can take upwards of several minutes.') . '</p>',
    ];

    $form['upload_new']['requirements'] = [
      '#markup' => '<p>' . t('The archival master file should be the original TIF file from the scanner, unmodified.') . '</p>',
    ];

    $form['upload_new']['tiff_file'] = [
      '#disabled' => !$storage_status,
      '#title' => t('TIF File'),
      '#type' => 'managed_file',
      '#description' => t('Upload an archival master file, allowed extensions: TIF TIFF'),
      '#upload_location' => "temporary://arc-tif/$node/",
      '#required' => TRUE,
      '#upload_validators' => [
        'file_validate_extensions' => ['tif', 'tiff'],
      ],
    ];

    $form['reassign'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Update Archival Master'),
    ];

    $form['reassign']['info'] = [
      '#markup' => '<p>' . t('If the archival master (and corresponding images) is not correct for this sample, this may be corrected below.') . '</p>',
    ];

    $form['reassign']['reassign_action'] = [
      '#type' => 'select',
      '#title' => $this->t('Actions'),
      '#options' => [
        'delete' => $this->t('Delete the archival master associated with this specimen'),
        'switch' => $this->t('Switch the archival master with another specimen'),
      ],
    ];

    $form['reassign']['action_target'] = [
      '#title' => t('Target Specimen Accession ID'),
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#selection_handler' => 'views',
      '#selection_settings' => [
        'view' => [
          'view_name' => 'autocomplete_node_by_accessionid',
          'display_name' => 'entity_reference_1',
          'arguments' => [0],
        ],
        'match_operator' => 'CONTAINS',
      ],
      '#states' => [
        'visible' => [
          'select[name="reassign_action"]' => [
            'value' => 'switch',
          ],
        ],
      ],
    ];

    $form['reassign']['submit'] = [
      '#type' => 'submit',
      '#disabled' => TRUE,
      '#value' => t('Update Archival Master'),
      '#submit' => [
        [$this, 'reassignArchivalMaster'],
      ],
      '#limit_validation_errors' => [
        ['nid'],
      ],
    ];

    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => isset($node) && is_numeric($node) ? $node : -1,
    ];

    $form['upload_new']['submit'] = [
      '#type' => 'submit',
      '#disabled' => !$storage_status,
      '#value' => t('Upload New Archival Master'),
      '#submit' => [
        [$this, 'uploadArchivalMasterSubmitForm'],
      ],
    ];

    $form['regenerate_assets'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Regenerate Sample Images'),
    ];

    $form['regenerate_assets']['info'] = [
      '#markup' => '<p>' . t('The specimen archival image serves as the source of all images presented to users for the specimen. To regenerate those images from the archival master, click below') . '</p>',
    ];

    $form['regenerate_assets']['submit'] = [
      '#type' => 'submit',
      '#disabled' => (bool) empty($history_rows) && $storage_status,
      '#value' => t('Regenerate Surrogate Images'),
      '#submit' => [
        [$this, 'regenerateSurrogatesSubmitForm'],
      ],
      '#limit_validation_errors' => [
        ['nid'],
      ],
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
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function regenerateSurrogatesSubmitForm(array &$form, FormStateInterface $form_state) {
    $nid = $form_state->getValue('nid');

    $batch = _herbarium_specimen_lts_regenerate_specimen_derivatives_batch($nid);

    batch_set($batch);
  }

  /**
   * {@inheritdoc}
   */
  public function uploadArchivalMasterSubmitForm(array &$form, FormStateInterface $form_state) {
    $fid = $form_state->getValue('tiff_file')[0];
    $file = File::Load($fid);
    $file_path = drupal_realpath($file->getFileUri());
    $nid = $form_state->getValue('nid');

    $batch = _herbarium_specimen_lts_add_archival_master($nid, $file_path);

    // Start the batch.
    batch_set($batch);
  }

}
