<?php

namespace Drupal\herbarium_specimen_csv_export\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * DownloadSpecimenCSVController object.
 */
class DownloadSpecimenCSVController extends ControllerBase {

  /**
   * Render a CSV formatted list specimen properties.
   *
   * @param string $node
   *   The node ID to render the CSV for.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The symfony response object.
   */
  public function getNodeCsv($node) {
    return $this->serveFile(
      $node,
      "nid_{$node}_" . (string) time()
    );
  }

  /**
   * Render a CSV formatted list for all specimen properties.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The symfony response object.
   */
  public function getAllNodesCsv() {
    $query = \Drupal::entityQuery('node')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('type', 'herbarium_specimen');
    $result = $query->execute();

    return $this->serveFile(
      implode('|', $result),
      'all_specimens_' . (string) time()
    );
  }

  /**
   * Render a CSV formatted list of node objects.
   *
   * @param string $node_ids
   *   The nodes to render the CSV for.
   * @param string $export_filename
   *   The label to use for the filename. Defaults to current time.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The symfony response object.
   */
  public function serveFile($node_ids, $export_filename = NULL) {
    $nids = explode('|', $node_ids);

    $nodes_to_process = $this->filterHerbariumSpecimenNodes($nids);
    if (empty($nodes_to_process)) {
      throw new NotFoundHttpException();
    }

    if (empty($export_filename)) {
      $export_filename = time();
    }
    // Instantiate and build header.
    $csv = Writer::createFromFileObject(new \SplTempFileObject());
    $csv->insertOne($this->buildExportHeader());

    foreach ($nodes_to_process as $node_to_process) {
      $csv->insertOne($this->buildNodeRowData($node_to_process));
    }

    $response = new Response($csv->__toString());
    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    $response->headers->set('Content-Disposition', "attachment; filename=\"cmh_export_{$export_filename}.csv\"");
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
      'CMH Accession ID',
      'Name',
      'Species ID',
      'Species Tree',
      'Collector(s)',
      'Country',
      'ProvinceState',
      'County',
      'Verbatim Locality',
      'Latitude,Longitude',
      'Geo Precision',
      'Collection Year',
      'Collection Month',
      'Collection Day',
      'Verbatim Event Date',
      'Abundance',
      'Habitat',
      'Occurrence Remarks',
      'Other Catalogue No.',
      'Previous Identifications',
      'Reproductive Condition',
      'Data Entry By',
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
    $species = $node->get('field_taxonomy_tid')->entity;
    $collection_date = $node->get('field_dwc_eventdate')->value;
    $collection_date_data = explode('-', $collection_date);

    if (count($collection_date_data) > 1) {
      $year_value = $collection_date_data[0];
      $month_value = $collection_date_data[1];
      $day_value = $collection_date_data[2];
    }
    else {
      $year_value = NULL;
      $month_value = NULL;
      $day_value = NULL;
    }

    if (
      !empty($node->get('field_dwc_decimallatitude')->getString()) &&
      !empty($node->get('field_dwc_decimallongitude')->getString())
    ) {
      $long_lat = $node->get('field_dwc_decimallatitude')->getString() . ',' . $node->get('field_dwc_decimallongitude')->getString();
    }
    else {
      $long_lat = NULL;
    }

    $data_columns = [
      $node->id(),
      $node->get('field_dwc_record_number')->getString(),
      $node->getTitle(),
      $species->id(),
      $this->getDelimitedSpeciesRepresentation($species),
      $this->buildDelimitedTermNames($node->get('field_collector_tid')->referencedEntities()),
      $this->buildDelimitedTermNames($node->get('field_dwc_country_tax')->referencedEntities()),
      $this->buildDelimitedTermNames($node->get('field_dwc_province_tax')->referencedEntities()),
      $this->buildDelimitedTermNames($node->get('field_dwc_county_tax')->referencedEntities()),
      $node->get('field_dwc_verbatimlocality')->getString(),
      $long_lat,
      $node->get('field_dwc_coordinateprecision')->getString(),
      $year_value,
      $month_value,
      $day_value,
      $node->get('field_dwc_verbatimeventdate')->getString(),
      $node->get('field_dwc_eventremarks')->getString(),
      $node->get('field_dwc_habitat')->getString(),
      $node->get('field_dwc_occurrenceremarks')->getString(),
      $node->get('field_dwc_othercatalognumbers')->getString(),
      $node->get('field_previous_identifications')->getString(),
      $node->get('field_dwc_reproductivecondition')->getString(),
      $node->get('field_dc_contributor_other')->getString(),
    ];

    return $data_columns;
  }

  /**
   * Build a delimited species name string for a species.
   *
   * @param \Drupal\taxonomy\Entity\Term $term
   *   The term to parse.
   * @param string $delimiter
   *   The string to use as a delimiter.
   *
   * @return string
   *   The delimited species name string.
   */
  private function getDelimitedSpeciesRepresentation(Term $term, $delimiter = '|') {
    $ancestors = _herbarium_core_term_get_ancestors($term);
    return $this->buildDelimitedTermNames($ancestors);
  }

  /**
   * Build a delimited species name string for an array of terms.
   *
   * @param \Drupal\taxonomy\Entity\Term[] $terms
   *   The terms to parse.
   * @param string $delimiter
   *   The string to use as a delimiter.
   *
   * @return string
   *   The delimited name string.
   */
  private function buildDelimitedTermNames(array $terms, $delimiter = '|') {
    $names = [];
    foreach ($terms as $term) {
      $names[] = $term->getName();
    }
    return implode($delimiter, $names);
  }

}
