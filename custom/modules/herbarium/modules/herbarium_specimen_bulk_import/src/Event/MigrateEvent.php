<?php

namespace Drupal\herbarium_specimen_bulk_import\Event;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\migrate_plus\Event\MigrateEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\migrate\Row;

/**
 * Defines the migrate event subscriber.
 */
class MigrateEvent implements EventSubscriberInterface {

  const MULTIPLE_VALUE_DELIMITER = ';';

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::PREPARE_ROW][] = ['onPrepareRow', 0];
    return $events;
  }

  /**
   * React to a new row.
   *
   * @param \Drupal\migrate_plus\Event\MigratePrepareRowEvent $event
   *   The prepare-row event.
   */
  public function onPrepareRow(MigratePrepareRowEvent $event) {
    $row = $event->getRow();

    // Collectors.
    $this->prepareCollectorData($row, 'specimen_collectors');

    // Country.
    $this->prepareTaxonomyData(
      $row,
      'cmh_country',
      'specimen_country',
      'specimen_location_country'
    );

    // Province.
    $this->prepareTaxonomyData(
      $row,
      'cmh_province',
      'specimen_province',
      'specimen_location_province'
    );

    // County.
    $this->prepareTaxonomyData(
      $row,
      'cmh_county',
      'specimen_county',
      'specimen_location_county'
    );

    // Collection Date.
    $year = $row->getSourceProperty('cmh_year');
    $month = $row->getSourceProperty('cmh_month');
    $day = $row->getSourceProperty('cmh_day');
    $iso_date = NULL;
    if (_herbarium_specimen_validate_year($year) &&
      _herbarium_specimen_validate_month($month) &&
      _herbarium_specimen_validate_day($day)) {
      $date_array = [
        'year' => $year,
        'month' => $month,
        'day' => $day,
      ];
      $iso_date = DrupalDateTime::arrayToISO($date_array);
      $row->setSourceProperty('cmh_date', $iso_date);
    }

    // Geo precision.
    $row->setSourceProperty(
      'geo_precision',
      $this->precMap($row->getSourceProperty('cmh_precision'))
    );

    $row->setSourceProperty('geo_heritage', NULL);
    if (
      !empty($row->getSourceProperty('cmh_geo_latitude')) &&
      !empty($row->getSourceProperty('cmh_geo_longitude'))
    ) {
      $heritage = t(
        'Direct from spreadsheet import : @long/@lat/@precision',
        [
          '@long' => $row->getSourceProperty('cmh_geo_longitude'),
          '@lat' => $row->getSourceProperty('cmh_geo_latitude'),
          '@precision' => $row->getSourceProperty('cmh_precision'),
        ]
      );
      $row->setSourceProperty('geo_heritage', $heritage);
    }
  }

  /**
   * Prepare the row to properly insert the collector data.
   *
   * @param \Drupal\migrate\Row $row
   *   The row to prepare.
   * @param string $target_property_name
   *   The target property name to store the data in.
   */
  private function prepareCollectorData(Row $row, $target_property_name) {
    $specimen_collector_ids = [];
    $vocabulary = 'herbarium_specimen_collector';
    $collectors = explode(
      self::MULTIPLE_VALUE_DELIMITER,
      $row->getSourceProperty('cmh_collectors')
    );

    foreach ($collectors as $value) {
      $term_value = trim($value);
      if (!empty($term_value)) {
        $term_tid = $this->taxTermExists($term_value, 'name', $vocabulary);
        if (!empty($term_tid)) {
          $term = Term::load($term_tid);
        }
        else {
          $term = Term::create([
            'vid' => $vocabulary,
            'name' => $term_value,
          ]);
          $term->save();
        }
        $specimen_collector_ids[] = $term->id();
      }
    }
    $row->setSourceProperty($target_property_name, $specimen_collector_ids);
  }

  /**
   * Prepare the row to insert a text value as a taxonomy term into a field.
   *
   * @param \Drupal\migrate\Row $row
   *   The row to prepare.
   * @param string $source_property_name
   *   The source property name to pull the data from.
   * @param string $target_property_name
   *   The target property name to store the data in.
   * @param string $vid
   *   The vid to use.
   * @param string $storage_field
   *   The field to store the value in, typically 'name'.
   */
  private function prepareTaxonomyData(Row $row, $source_property_name, $target_property_name, $vid, $storage_field = 'name') {
    $value = $row->getSourceProperty($source_property_name);
    $term_tid = $this->taxTermExists($value, $storage_field, $vid);

    if (!empty($term_tid)) {
      $term = Term::load($term_tid);
    }
    else {
      $term = Term::create([
        'vid' => $vid,
        $storage_field => $value,
      ]);
      $term->save();
    }

    $row->setSourceProperty($target_property_name, $term->id());
  }

  /**
   * Check if a taxonomy term exists.
   *
   * @param string $value
   *   The name of the term.
   * @param string $field
   *   The field to match when validating.
   * @param string $vocabulary
   *   The vid to match.
   *
   * @return mixed
   *   Contains an INT of the tid if exists, FALSE otherwise.
   */
  public function taxTermExists($value, $field, $vocabulary) {
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', $vocabulary);
    $query->condition($field, $value);
    $tids = $query->execute();
    if (!empty($tids)) {
      foreach ($tids as $tid) {
        return $tid;
      }
    }
    return FALSE;
  }

  /**
   * Map the coordinate precision from internal system to decimal amount.
   *
   * @param int $prec
   *   The precision value between 1 and 5.
   *
   * @return float
   *   The coordinate precision.
   */
  public function precMap($prec) {
    $coordPrec = NULL;
    if (is_numeric($prec)) {
      $intPrec = floor($prec);
      if ($intPrec >= 1 && $intPrec <= 5) {
        $precisionMap = [
          1 => '0.0001',
          2 => '0.001',
          3 => '0.01',
          4 => '0.1',
          5 => '1.0',
        ];
        $coordPrec = $precisionMap[$intPrec];
      }
    }
    return $coordPrec;
  }

}
