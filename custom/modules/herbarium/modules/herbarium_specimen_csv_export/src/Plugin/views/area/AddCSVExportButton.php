<?php

namespace Drupal\herbarium_specimen_csv_export\Plugin\views\area;

use Drupal\Core\Url;
use Drupal\views\Plugin\views\area\AreaPluginBase;

/**
 * Views area collection add new button handler.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("herbarium_specimen_csv_export_button")
 */
class AddCSVExportButton extends AreaPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    // Set the default to TRUE so it shows on empty pages by default.
    $options['empty']['default'] = FALSE;
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    if (!$empty || !empty($this->options['empty'])) {
      $view = $this->view;

      $csv_nodes = [];
      foreach ($view->result as $row) {
        $csv_nodes[] = $row->_entity->id();
      }

      $button = [
        '#type' => 'link',
        '#attributes' => [
          'class' => [
            'btn',
            'btn-primary',
          ],
        ],
        '#id' => 'csv-bulk-export-download',
        '#title' => $this->t('Download Page Results as CSV'),
        '#url' => Url::fromRoute(
          'herbarium_specimen_csv_export.bulk_download',
          [
            'node_ids' => implode('|', $csv_nodes),
            'export_filename' => 'search_results_' . (string) time(),
          ]
        ),
        '#prefix' => '<div class="pull-right">',
        '#suffix' => '</div><div class="clearfix"></div>',
        '#attached' => [
          'library' => [
            'herbarium_specimen_csv_export/csv_export_button',
          ],
        ],
      ];

      return $button;
    }
  }

}
