<?php

namespace Drupal\herbarium_specimen_bulk_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\herbarium_specimen_bulk_import\HerbariumCsvMigration;
use League\Csv\Reader;

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
    $user_input = $form_state->getUserInput();
    if (empty($user_input['_triggering_element_value']) || $user_input['_triggering_element_value'] != 'Remove') {
      ini_set("auto_detect_line_endings", '1');

      $file = File::Load($form_state->getValue('import_file')[0]);
      $file_path = drupal_realpath($file->getFileUri());
      $format_id = $form_state->getValue('import_format');

      if (
        $this->validateImportFormat($form_state, $format_id) &&
        $this->validateCSVStructure($form, $form_state, $file_path, $format_id) &&
        $this->validateRowData($form, $form_state, $file_path, $format_id)
      ) {
        // No errors found. Do nothing, but process all validations sequentially.
      };

    }
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

  /**
   * {@inheritdoc}
   */
  private function validateCSVStructure(array &$form, FormStateInterface $form_state, $file_path, $format_id) {
    // Validate CSV structure.
    try {
      $reader = Reader::createFromPath($file_path, 'r');
      $nbColumns = $reader->fetchOne();
      $allColumns = $reader->fetchAll();

      // Check header matches expected number of rows from format.
      $import_format = _herbarium_specimen_bulk_import_get_import_format($format_id);
      if (count($nbColumns) != count($import_format['columns'])) {
        $form_state->setErrorByName('import_file', $this->t(
          'The number of columns in the file (@file_columns) is different than expected by the import format (@format_columns).',
          [
            '@format_columns' => count($import_format['columns']),
            '@file_columns' => count($nbColumns),
          ]
        ));
        return FALSE;
      }

      // Check Row Consistency.
      foreach ($allColumns as $row_num => $column) {
        if (count($column) != count($nbColumns)) {
          $form_state->setErrorByName('import_file', $this->t(
            'The number of columns in row #@row_num (@row_columns) of the file is not the same as the header (@header_columns)',
            [
              '@row_num' => $row_num,
              '@row_columns' => count($column),
              '@header_columns' => count($nbColumns),
            ]
          ));
          return FALSE;
        }
      }
    }
    catch (Exception $e) {
      $form_state->setErrorByName('import_file', $this->t('Selected file is not in valid CSV format.'));
      return FALSE;
    }

    return TRUE;
  }

  private function validateRowData(array &$form, FormStateInterface $form_state, $file_path, $format_id) {
    $errors = FALSE;

    $reader = Reader::createFromPath($file_path, 'r');
    $dataRows = $reader->setOffset(1)->fetchAll();
    $import_format = _herbarium_specimen_bulk_import_get_import_format($format_id);

    foreach ($dataRows as $row_id => $row) {
      foreach ($row as $column_id => $column_data) {
        if (!empty($import_format['columns'][$column_id]['validate'])) {
          foreach ($import_format['columns'][$column_id]['validate'] as $validator) {
            // Pack data onto arguments.
            $function_args = ['data' => $column_data] + $validator['args'];
            if (!$validator['function'](...array_values($function_args))) {
              $data_row_id = $row_id + 2;
              // Validation failed.
              $errors = TRUE;
              drupal_set_message("{$import_format['columns'][$column_id]['name']} validation failed in row $data_row_id, column $column_id", 'error');
            }
          }
        }
      }
    }

    if ($errors) {
      $form_state->setErrorByName('import_file', 'Errors were found while validating the import file.');
    }
  }

  private function validateImportFormat(FormStateInterface $form_state, $format_id) {
    if (empty(_herbarium_specimen_bulk_import_get_import_format($format_id))) {
      $form_state->setErrorByName('import_file', $this->t('The import format is invalid.'));
      return FALSE;
    };
    return TRUE;
  }

}
