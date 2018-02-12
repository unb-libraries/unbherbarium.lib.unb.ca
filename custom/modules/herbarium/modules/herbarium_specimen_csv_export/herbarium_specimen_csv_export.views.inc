<?php

/**
 * @file
 * Provide views data.
 */

/**
 * Implements hook_views_data().
 */
function herbarium_specimen_csv_export_views_data() {
  $data['views']['table']['group'] = t('Global');
  $data['views']['table']['join'] = [
    '#global' => [],
  ];

  $data['views']['herbarium_specimen_csv_export_button'] = [
    'title' => t('CSV Data Export Button'),
    'help' => t('Provides a button to provide a CSV data export.'),
    'area' => [
      'id' => 'herbarium_specimen_csv_export_button',
    ],
  ];

  return $data;
}
