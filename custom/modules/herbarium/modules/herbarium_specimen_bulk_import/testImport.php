<?php

use Drupal\herbarium_specimen_bulk_import\HerbariumCsvMigration;

$importObject = new HerbariumCsvMigration(
  'cmr_herb_import',
  '/app/html/modules/custom/herbarium/modules/herbarium_specimen_bulk_import/test.csv'
);
