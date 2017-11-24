<?php

namespace Drupal\herbarium_specimen_bulk_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\herbarium_specimen_bulk_import\HerbariumCsvMigration;

/**
 * ManageArchivalMasterForm object.
 */
class HerbariumSpecimenBulkImportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'herbarium_specimen_bulk_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $form['upload_import'] = array(
      '#type' => 'fieldset',
    );
    $form['upload_import']['header'] = array(
      '#markup' => t(
        '<h2>Bulk Upload Herbarium Specimens</h2>'
      ),
    );
    $form['upload_import']['message'] = array(
      '#markup' => t(
        '<p style="margin:10px;">This tab allows you to bulk import specimens from a CSV file.</p>'
      ),
    );
    $form['upload_import']['csv_file'] = array(
      '#title' => t('CSV File'),
      '#type' => 'managed_file',
      '#description' => t('Upload a file, allowed extensions: CSV'),
      '#upload_location' => 'public://specimen_csv_upload/',
      '#upload_validators' => array(
        'file_validate_extensions' => array('csv'),
      ),
    );
    $form['upload_import']['submit'] = array(
      '#type' => 'submit',
      '#prefix' => '<br>',
      '#value' => t('Import Specimens'),
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
    $import_id = 'cmh_herb_import';
    $migrateObject = new HerbariumCsvMigration(
      $import_id,
      '/app/html/modules/custom/herbarium/modules/herbarium_specimen_bulk_import/test.csv'
    );
    drupal_flush_all_caches();

    $migration = \Drupal::service('plugin.manager.migration')->createInstance($migrateObject->importId);
    $map = $migration->getIdMap();
    $source_plugin = $migration->getSourcePlugin();
    $source_rows = $source_plugin->count();
    $unprocessed = $source_rows - $map->processedCount();

    $batch = array(
      'title' => t('Importing Herbarium Specimen'),
      'init_message' => t('Importing Herbarium Specimens'),
      'operations' => [],
    );

    $batch_items_created = 0;
    while ($batch_items_created < $unprocessed) {
      $batch['operations'][] = array(
        array(
          'Drupal\herbarium_specimen_bulk_import\HerbariumCsvMigration',
          'runCsvMigrationBatch',
        ),
        array(
          $migrateObject->importId,
          1,
        ),
      );
      $batch_items_created++;
    }
    batch_set($batch);
  }

}
