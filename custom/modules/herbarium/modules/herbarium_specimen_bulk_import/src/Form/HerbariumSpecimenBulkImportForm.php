<?php

namespace Drupal\herbarium_specimen_bulk_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\herbarium_specimen_bulk_import\HerbariumCsvMigration;

/**
 * HerbariumSpecimenBulkImportForm object.
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

    $import_formats = _herbarium_specimen_bulk_import_get_import_formats();
    $select_options = [];
    foreach ($import_formats as $import_format) {
      $select_options[$import_format['id']] = $import_format['description'];
    }

    $form['upload_import']['import_format'] = array(
      '#type' => 'select',
      '#title' => t('Import Format:'),
      '#required' => TRUE,
      '#options' => $select_options,
      '#default_value' => array_shift($import_formats)['id'],
    );

    $form['upload_import']['import_file'] = array(
      '#title' => t('Import File'),
      '#type' => 'managed_file',
      '#required' => TRUE,
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

    $form['import_history'] = [
      '#type' => 'fieldset',
    ];

    $form['import_history']['header'] = array(
      '#markup' => t(
        '<h2><em>Previous Bulk Imports</em></h2>'
      ),
    );

    $form['import_history']['table'] = _herbarium_specimen_bulk_import_get_cmh_migration_table();

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
    $fid = $form_state->getValue('import_file')[0];
    $file = File::Load($fid);
    $file_path = drupal_realpath($file->getFileUri());

    $import_id = $form_state->getValue('import_format');

    $migrateObject = new HerbariumCsvMigration(
      $import_id,
      $file_path
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
