<?php

namespace Drupal\herbarium_specimen_csv_export\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * DownloadSpecimenCSVController object.
 */
class DownloadSpecimenCSVController extends ControllerBase {

  /**
   * Render a CSV formatted list of node objects.
   *
   * @param string $node_ids
   *   The nodes to render the CSV for.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The symfony response object.
   */
  public function serveFile($node_ids) {
    $nids = explode('|', $node_ids);

    $nodes_to_process = $this->filterHerbariumSpecimenNodes($nids);
    if (empty($nodes_to_process)) {
      throw new NotFoundHttpException();
    }

    // Instantiate and build header.
    $csv = Writer::createFromFileObject(new \SplTempFileObject());
    $csv->insertOne($this->buildExportHeader());

    foreach ($nodes_to_process as $node_to_process) {
      $csv->insertOne($this->buildNodeRowData($node_to_process));
    }

    $datestamp = time();
    $response = new Response($csv->__toString());
    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    $response->headers->set('Content-Disposition', "attachment; filename=\"cmh_export_template_{$datestamp}.csv\"");
    return $response;
  }

  /**
   * Filter a list of nids for herbarium specimens.
   *
   * @param array $node_ids
   *   The nodes IDs to render.
   *
   * @return \Drupal\node\Entity\Node[]
   *   An array of herbarium_specimen node objects.
   */
  private function filterHerbariumSpecimenNodes(array $node_ids = []) {
    $specimen_nodes = [];
    foreach ($node_ids as $id_key => $nid) {
      $node = Node::load($nid);
      if ($node->getType() == 'herbarium_specimen') {
        $specimen_nodes[] = $node;
      }
    }
    return $specimen_nodes;
  }

  /**
   * Build a header for a CSV export.
   *
   * @return array
   *   An array of node export header labels.
   */
  private function buildExportHeader() {
    $header_columns = [];

    $header_columns = [
      'nid',
      'title',
    ];

    return $header_columns;
  }

  /**
   * Build a data row for a CSV export of a Herbarium Specimen node.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The nodes to parse.
   *
   * @return array
   *   An array of values to output in the CSV.
   */
  private function buildNodeRowData(Node $node) {
    $data_columns = [];

    $data_columns = [
      $node->id(),
      $node->getTitle(),
    ];

    return $data_columns;
  }

}
