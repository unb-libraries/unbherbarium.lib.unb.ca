<?php

namespace Drupal\herbarium_specimen_bulk_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\node\Entity\Node;

/**
 * HerbariumSpecimenBulkMigrationView object.
 */
class HerbariumSpecimenBulkMigrationView extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'herbarium_specimen_bulk_migration_view';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $migration_id = NULL) {
    $form = [];

    $form['import_details'] = [
      '#type' => 'fieldset',
    ];
    $form['import_details']['header'] = array(
      '#markup' => t(
        '<h2>Details for @import_id:</h2>',
        [
          '@import_id' => $migration_id,
        ]
      ),
    );
    $form['import_details']['table'] = _herbarium_specimen_bulk_import_get_cmh_migration_table($migration_id);


    $migrate_targets = _herbarium_specimen_bulk_import_get_migration_destinations($migration_id);
    if (!empty($migrate_targets)) {
      // Construct header.
      $header = [
        t('ID'),
      ];
      // Build the rows.
      $rows = [];

      foreach ($migrate_targets as $target) {
        if (!empty($target->destid1)) {
          $node = Node::load($target->destid1);
          $rows[] = [
            'data' => [
              Link::createFromRoute($node->getTitle(), 'entity.node.canonical', ['node' => $target->destid1]),
            ],
          ];
        }
      }

      $form['import_details']['specimen_list'] = [
        '#type' => 'fieldset',
      ];

      $form['import_details']['specimen_list']['header'] = array(
        '#markup' => t(
          '<h2><em>Specimens Imported:</em></h2>'
        ),
      );

      $form['import_details']['specimen_list']['message_table'] = [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ];
      $form['import_details']['specimen_list']['pager'] = [
        '#type' => 'pager',
      ];

    }
    else {
      $form['import_details']['none_found'] = [
        '#markup' => t('Invalid Migration ID.'),
      ];
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

  }

}
