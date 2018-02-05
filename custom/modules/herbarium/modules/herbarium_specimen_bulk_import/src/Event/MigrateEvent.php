<?php

namespace Drupal\unb_herbarium_migrate_csv\Event;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\file\Entity\File;
use Drupal\migrate_plus\Event\MigrateEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Drupal\taxonomy\Entity\Term;
use Drupal\unb_herbarium_migrate_csv\Gpoint\GpointConverter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\migrate\Row;

/**
 * Defines the migrate event CSV row.
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
    $term_tid = $this->taxTermExists($value, $target_property_name, $vid);

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

}
