<?php

namespace Drupal\herbarium_specimen_lts\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\herbarium_specimen_lts\HerbariumImageLtsArchiver;
use Drupal\Core\Site\Settings;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;

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
    $node_obj = Node::load($node);

    if (trim(Settings::get('specimen_lts_archive') == '')) {
      $this->messenger()->addWarning(t('WARNING: Settings for a LFS storage server have not been detected. Any changes to the master image for this specimen will not be stored in the permanent archive.'));

      // Allow the form to be submitted, but LFS storage will be skipped.
      $storage_status = TRUE;
    }
    else {
      list($storage_status, $error_message) = HerbariumImageLtsArchiver::checkStorageStatus();
      if ($error_message) {
        $this->messenger()->addError($error_message);
      }
    }

    $form['description'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Information'),
    ];

    $form['description']['description'] = [
      '#markup' => '<p>' . t('The specimen master image serves as the digital preservation copy for the herbarium specimen. The images are stored in a separate repository and version controlled to ensure integrity. Additionally, all local images used in the site are derived from this master image.') . '</p>',
    ];

    $form['master_history'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Master Image Audit History'),
    ];

    $history_rows = HerbariumImageLtsArchiver::getFileHistory($node);
    $has_master = HerbariumImageLtsArchiver::specimenHasMaster($node);

    if (!empty($history_rows)) {
      // Construct header.
      $header = [
        t('Revision'),
        t('Date'),
        t('User'),
        t('Change'),
        t('Commit'),
      ];

      // Build the rows.
      $rows = [];
      foreach ($history_rows as $index => $row) {
        array_unshift($row, $index + 1);
        $rows[] = ['data' => $row];
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
        '#markup' => t('No master images have been attached to this specimen yet.'),
      ];
    }

    $form['upload_new'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Upload New Master Image'),
    ];

    $form['upload_new']['details'] = [
      '#markup' => '<p>' . t('If you wish to add a new or replace an existing master image, upload one below. Adding a new master will trigger several tasks and can take upwards of several minutes.') . '</p>',
    ];

    $form['upload_new']['requirements'] = [
      '#markup' => '<p>' . t('The master image file should be the original TIF file from the scanner, unmodified.') . '</p>',
    ];

    $form['upload_new']['tiff_file'] = [
      '#disabled' => !$storage_status,
      '#title' => t('TIF File'),
      '#type' => 'managed_file',
      '#description' => t('Upload an master image, allowed extensions: TIF TIFF'),
      '#upload_location' => "temporary://arc-tif/$node/",
      '#required' => TRUE,
      '#upload_validators' => [
        'file_validate_extensions' => ['tif', 'tiff'],
      ],
    ];

    if ($has_master) {
      $form['reassign'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Update Master Image'),
      ];

      $form['reassign']['info'] = [
        '#markup' => '<p>' . t('If the master image (and corresponding local images) are not correct for this sample, this may be corrected below.') . '</p>',
      ];

      $form['reassign']['reassign_action'] = [
        '#type' => 'select',
        '#title' => $this->t('Actions'),
        '#options' => [
          'delete' => $this->t('Delete the master image associated with this specimen'),
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
        '#disabled' => empty($history_rows) || !$storage_status,
        '#value' => t('Update Master Image'),
        '#submit' => [
          [$this, 'reassignArchivalMaster'],
        ],
        '#limit_validation_errors' => [
          ['nid'],
          ['action_target'],
          ['reassign_action'],
        ],
      ];
    }

    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => isset($node) && is_numeric($node) ? $node : -1,
    ];

    $form['upload_new']['submit'] = [
      '#type' => 'submit',
      '#disabled' => !$storage_status,
      '#value' => t('Upload New Master Image'),
      '#submit' => [
        [$this, 'uploadArchivalMasterSubmitForm'],
      ],
    ];

    if ($has_master) {
      $form['local_images'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Local Images'),
      ];

      $form['local_images']['regenerate_info'] = [
        '#markup' => '<p>' . t('The specimen master image serves as the source for the local images - those presented to users for this specimen. To regenerate the local images from the master image, click below') . '</p>',
      ];

      $form['local_images']['submit_regenerate'] = [
        '#type' => 'submit',
        '#disabled' => empty($history_rows) || !$storage_status,
        '#value' => t('Regenerate Local Images'),
        '#submit' => [
          [$this, 'regenerateSurrogatesSubmitForm'],
        ],
        '#limit_validation_errors' => [
          ['nid'],
        ],
      ];

      if (_herbarium_specimen_has_local_images($node_obj)) {
        $form['local_images']['delete_info'] = [
          '#markup' => '<p>' . t('To delete the local images attached to the specimen, click below. The master image will not be affected.') . '</p>',
        ];

        $form['local_images']['submit_delete'] = [
          '#type' => 'submit',
          '#value' => t('Delete Local Images'),
          '#submit' => [
            [$this, 'deleteSurrogatesSubmitForm'],
          ],
          '#limit_validation_errors' => [
            ['nid'],
          ],
        ];
      }

    }

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
    $file_path = \Drupal::service('file_system')->realpath($file->getFileUri());
    $nid = $form_state->getValue('nid');

    $batch = _herbarium_specimen_lts_add_archival_master($nid, $file_path);

    // Start the batch.
    batch_set($batch);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteSurrogatesSubmitForm(array &$form, FormStateInterface $form_state) {
    $nid = $form_state->getValue('nid');

    $batch = [
      'title' => t('Deleting Local Images'),
      'init_message' => t('Deleting Local Images'),
      'operations' => [],
    ];

    $batch['operations'][] = [
      [
        'Drupal\herbarium_specimen\HerbariumImageSurrogateFactory',
        'deleteExistingAssets',
      ],
      [
        $nid,
      ],
    ];

    // Start the batch.
    batch_set($batch);
  }

  /**
   * {@inheritdoc}
   */
  public function reassignArchivalMaster(array &$form, FormStateInterface $form_state) {
    $nid = $form_state->getValue('nid');
    $reassign_action = $form_state->getValue('reassign_action');
    $action_target = $form_state->getValue('action_target');
    $batch = [];

    switch ($reassign_action) {
      case 'delete':
        $batch = _herbarium_specimen_lts_remove_item_batch($nid);
        break;

      case 'swap':
        $batch = _herbarium_specimen_lts_swap_item_batch($nid, $action_target);
        break;
    }

    batch_set($batch);
  }

}
