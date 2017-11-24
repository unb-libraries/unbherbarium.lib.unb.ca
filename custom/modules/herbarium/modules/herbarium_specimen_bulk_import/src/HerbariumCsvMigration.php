<?php

namespace Drupal\herbarium_specimen_bulk_import;

use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Symfony\Component\Yaml\Yaml;
use \Datetime;
use Drupal\migrate_plus\Entity\MigrationGroup;
use Drupal\migrate_tools\MigrateExecutable;
use Drupal\migrate\MigrateMessage;

/**
 * HerbariumImageSurrogateFactory caption set object.
 */
class HerbariumCsvMigration {

  /**
   * The error message of the migration.
   *
   * @var string
   */
  public $errors = [];

  /**
   * The ID of the current import.
   *
   * @var string
   */
  public $importId;

  /**
   * The file path to the configuration file.
   *
   * @var string
   */
  public $migrateFilePath;

  /**
   * Constructor.
   *
   * @param string $import_id
   *   The import ID of the migration.
   * @param string $import_file
   *   The path to the csv import file.
   */
  public function __construct($import_id = NULL, $import_file = NULL, $limit = 1) {
    $time_obj = new DateTime();
    $date_time_string = $time_obj->format('Y-m-d H:i:s');
    $date_time_stamp = $time_obj->getTimestamp();
    $this->importId = "cmh_{$import_id}_{$date_time_stamp}";

    if (!file_exists($import_file)) {
      $this->addError(
        t('Import file not found.')
      );
      return;
    }

    $module_handler = \Drupal::service('module_handler');
    $module_relative_path = $module_handler->getModule('herbarium_specimen_bulk_import')->getPath();
    $this->migrateFilePath = DRUPAL_ROOT . "/$module_relative_path/config/imports/$import_id.migration.migrate_csv.yml";

    if (!file_exists($this->migrateFilePath)) {
      $this->addError(
        t('Migration configuration file not found.')
      );
      return;
    }

    $config_contents = file_get_contents($this->migrateFilePath);
    $config_array = Yaml::parse($config_contents);

    $config_array['id'] = $this->importId;
    $config_array['label'] = "Herbarium Sample Import from $date_time_string";
    $config_array['source']['path'] = $import_file;

    $config_storage = \Drupal::service('config.storage');
    $config_storage->write('migrate_plus.migration.' . $this->importId, $config_array);
  }

  private function addError($message) {
    $this->errors[] = $message;
  }

  public static function runCsvMigrationBatch($migration_id, $item_limit, &$context) {
    $migration = \Drupal::service('plugin.manager.migration')->createInstance($migration_id);
    $executable = new MigrateExecutable($migration, new MigrateMessage(), ['limit' => $item_limit]);
    $executable->import();

    $context['message'] = t(
      '[NID#@nid] Imported specimen.',
      [
        '@nid' => 777,
      ]
    );
  }

}

