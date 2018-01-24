<?php

/**
 * @file
 * Contains \Drupal\migrate\Event\MigrateMapDeleteEvent.
 */

namespace Drupal\unb_herbarium_migrate_csv\Event;

use Drupal\file\Entity\File;
use Drupal\migrate_plus\Event\MigrateEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Drupal\Core\Datetime\DrupalDateTime;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\unb_herbarium_migrate_csv\Gpoint\GpointConverter;


class MigrateEvent implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events[MigrateEvents::PREPARE_ROW][] = array('onPrepareRow', 0);
    return $events;
  }

  /**
   * React to a new row.
   *
   * @param \Drupal\migrate_plus\Event\MigratePrepareRowEvent $event
   *   The prepare-row event.
   */
  public function onPrepareRow(MigratePrepareRowEvent $event) {
    $images_path = UNB_HERBARIUM_MIGRATE_CSV_SPECIES_IMPORT_DATA_DIR;
    $row = $event->getRow();

    // Accession Number, aka ID
    $accNum = trim($row->getSourceProperty('record_number'));

    $year = (trim($row->getSourceProperty('year')) != '') ? $row->getSourceProperty('year') : NULL;
    $month = (trim($row->getSourceProperty('month')) != '') ? $row->getSourceProperty('month') : NULL;
    $day = (trim($row->getSourceProperty('day')) != '') ? $row->getSourceProperty('day') : NULL;

    // Collection Date.
    $iso_date = NULL;
    if ($this->isValidYearRange($year) &&
        $this->isValidMonthRange($month) &&
        $this->isValidDayRange($day)) {
      $date_array = array(
        'year' => $year,
        'month' => $month,
        'day' => $day,
      );
      $iso_date = DrupalDateTime::arrayToISO($date_array);
      $row->setSourceProperty('date_iso', $iso_date);
    }

    // Verbatim Event Date (compressed whitespace).
    $date_str = 'Y: ' . $year . ' M: ' . $month . ' D: ' . $day;
    $row->setSourceProperty('dwc_verbatimeventdate', preg_replace(
      '/\s+/', ' ', $date_str)
    );

    // Record Creation Date.
    $date_array = [];
    $record_creation_date_valid = FALSE;
    $date_str = str_replace("/", "-", trim($row->getSourceProperty('dc_created')));
    if (!empty($date_str)) {
      list($date_array['year'], $date_array['month'], $date_array['day']) = array_filter(explode("-", $date_str));
      if ($this->isValidYearRange($date_array['year']) &&
        $this->isValidMonthRange($date_array['month']) &&
        $this->isValidDayRange($date_array['day'])
      ) {
        $record_creation_date_valid = TRUE;
        $iso_date = DrupalDateTime::arrayToISO($date_array);
        $row->setSourceProperty('date_created_iso', $iso_date);
        $timestamp = strtotime($date_str);
        $row->setSourceProperty('created_timestamp', (int) $timestamp);
        $row->setSourceProperty('changed_timestamp', (int) $timestamp);
      }
    }

    // Modification Date.
    $date_array = [];
    $date_str = str_replace("/", "-", trim($row->getSourceProperty('dc_modified')));
    if (!empty($date_str)) {
      list($date_array['year'], $date_array['month'], $date_array['day']) = array_filter(explode("-", $date_str));
      if ($this->isValidYearRange($date_array['year']) &&
        $this->isValidMonthRange($date_array['month']) &&
        $this->isValidDayRange($date_array['day'])
      ) {
        $iso_date = DrupalDateTime::arrayToISO($date_array);
        $row->setSourceProperty('date_modified_iso', $iso_date);
        $timestamp = strtotime($date_str);
        $row->setSourceProperty('changed_timestamp', (int) $timestamp);
        if (!$record_creation_date_valid) {
          $row->setSourceProperty('created_timestamp', (int) $timestamp);
        }
      }
    }

    // Province Value - trim whitespace+strip periods.
    $dwc_province = trim($row->getSourceProperty('stateprovince'));
    $row->setSourceProperty('dwc_stateprovince', str_replace('.', '', $dwc_province));

    // Geo Heritage (Longitude/Latitude).
    $precisionValue = trim($row->getSourceProperty('coordinateprecision'));
    $longDec = trim($row->getSourceProperty('longitudedecimal'));
    $latDec = trim($row->getSourceProperty('latitudedecimal'));
    $longDig = trim($row->getSourceProperty('longitudedigital'));
    $latDig = trim($row->getSourceProperty('latitudedigital'));
    $longDeg = trim($row->getSourceProperty('longitudedegree'));
    $longMin = trim($row->getSourceProperty('longitudeminute'));
    $longSec = trim($row->getSourceProperty('longitudesecond'));
    $latDeg = trim($row->getSourceProperty('latitudedegree'));
    $latMin = trim($row->getSourceProperty('latitudeminute'));
    $latSec = trim($row->getSourceProperty('latitudesecond'));
    $geoUTMZ = trim($row->getSourceProperty('geoheritage_utmz'));
    $geoUTME = trim($row->getSourceProperty('geoheritage_utme'));
    $geoUTMN = trim($row->getSourceProperty('geoheritage_utmn'));

    $longLatItems = array(
      $accNum,
      $precisionValue,
      $longDec,
      $latDec,
      $longDig,
      $latDig,
      $longDeg,
      $longMin,
      $longSec,
      $latDeg,
      $latMin,
      $latSec,
      $geoUTMZ,
      $geoUTME,
      $geoUTMN,
    );
    list($decLong, $decLat, $geoRefRem) = $this->determineLongitudeLatitude($longLatItems);
    $row->setSourceProperty('geo_heritage', $geoRefRem);

    // Coordinate Precision.
    $coordPrec = $this->precMap($precisionValue);
    $row->setSourceProperty('mapped_coord_prec', $coordPrec);

    if ($decLat != NULL && $decLong != NULL) {
      $country = trim($row->getSourceProperty('country'));
      $isCanada = (substr(strtolower($country), 0, 3) === "can") ? TRUE : FALSE;
      if ($decLong > 0 && $isCanada) {
        // Canadian Longitude should be negative.
        $decLong = $decLong * (-1);
      }
      $row->setSourceProperty('dwc_longitude', $decLong);
      $row->setSourceProperty('dwc_latitude', $decLat);
      $row->setSourceProperty('one_line_gmap_address', $decLat . ',' . $decLong);
    }

    // Record Number aka UNB Accession No.
    $row->setSourceProperty('record_number_string', $accNum);

    // Sample Collectors
    $specimen_collector_ids = array();
    $fieldname = 'name';
    $vocabulary = 'herbarium_specimen_collector';
    $collectors = explode(";", $row->getSourceProperty('collectors'));
    foreach ($collectors as $value) {
      $term_value = trim($value);
      if (!empty($term_value)) {
        $term_tid = $this->taxtermExists($term_value, $fieldname, $vocabulary);
        if (!empty($term_tid)) {
          $term = Term::load($term_tid);
        } else {
          $term = Term::create([
            'vid' => $vocabulary,
            $fieldname => $term_value,
          ]);
          $term->save();
        }
        $specimen_collector_ids[] = $term->id();
      }
    }
    $row->setSourceProperty('specimen_collector', $specimen_collector_ids);

    // Country.
    $country_val = $row->getSourceProperty('country');
    if (!empty($country_val)) {
      $tid = _unb_herbarium_create_tax_term_if_not_exists(
        $country_val,
        'specimen_location_country'
      );
      $row->setSourceProperty('country_tid', $tid);
    }
    else {
      $row->setSourceProperty('country_tid', NULL);
    }

    // Province.
    $province_val = $row->getSourceProperty('dwc_stateprovince');
    if (!empty($province_val)) {
      $tid = _unb_herbarium_create_tax_term_if_not_exists(
        $province_val,
        'specimen_location_province'
      );
      $row->setSourceProperty('dwc_stateprovince_tid', $tid);
    }
    else {
      $row->setSourceProperty('dwc_stateprovince_tid', NULL);
    }

    // County.
    $county_val = $row->getSourceProperty('county');
    if (!empty($county_val)) {
      $tid = _unb_herbarium_create_tax_term_if_not_exists(
        $county_val,
        'specimen_location_county'
      );
      $row->setSourceProperty('county_tid', $tid);
    }
    else {
      $row->setSourceProperty('county_tid', NULL);
    }

    $dwc_previousidents_raw = trim($row->getSourceProperty('previous_identifications'));
    // DetAnnList field values are delimited by vertical tab character.
    $dwc_previousidents = explode("\v", $dwc_previousidents_raw);
    $row->setSourceProperty('previous_identifications', $dwc_previousidents);

    // Sample Taxonomy
    $full_title = "Untitled";
    $fieldname = 'field_dwc_taxonid';
    $vocabulary = 'herbarium_specimen_taxonomy';
    $tax_id = trim($row->getSourceProperty('assigned_taxon'));
    if (is_numeric($tax_id)) {
      $term_tid = $this->taxtermExists($tax_id, $fieldname, $vocabulary);
      if (!empty($term_tid)) {
        $term = Term::load($term_tid);
        $assign_taxon_id = $term->id();
        $row->setSourceProperty('assigned_taxon', $assign_taxon_id);

        $full_title = _herbarium_core_term_build_full_name(
          $term,
          HERBARIUM_CORE_SPECIMEN_VOCABULARY_RANKS_TO_OMIT_PRINTING,
          FALSE
        );
        $title = (empty($full_title)) ? 'Unavailable' : $full_title;
        $row->setSourceProperty('title_string', $title);
      } else {
        print "SPEC ID doesn't exist in vocabulary: " . $tax_id . "\n";
      }
    } else {
      print "\nAcc #=" . $accNum . ": SPECID NOT NUMERIC!" . "\n";
    }
  }

  // Determine and return Coordinate Precision.
  public function precMap($prec) {
    $coordPrec = NULL;
    if (is_numeric($prec)) {
      $intPrec = floor ($prec);
      if ($intPrec >= 1 && $intPrec <= 5) {
        $precisionMap = array(
          1 => '0.0001',
          2 => '0.001',
          3 => '0.01',
          4 => '0.1',
          5 => '1.0',
        );
        $coordPrec = $precisionMap[$intPrec];
      }
    }
    return $coordPrec;
  }

  /*
   * _herbariumImportFormatLocalityData(&$itemArray)
   *
   * &$itemArray : ARR of TSV imported line data.
   *
   * Tests locality relevant columns for a decimal longitude/latitude and attempts to determine a value to use.
   * Logs hertiage of value in array.
   *
   * RETURNS : none.
   */
  public function determineLongitudeLatitude($longLatVals) {
    $longVal = $latVal = '';
    $srcMethod = "Unknown";
    list($id, $prec, $longDec, $latDec, $longDig, $latDig, $longDeg, $longMin, $longSec, $latDeg, $latMin, $latSec, $geoUtmz, $geoUtme, $geoUtmn) = $longLatVals;
    if ($this->testLongitudeLatitudeFormat($longDec, $latDec)) {
      $srcMethod = "Direct From Spreadsheet";
      $longVal = $longDec;
      $latVal = $latDec;
    } elseif ($this->testLongitudeLatitudeFormat($longDig, $latDig)) {
      $srcMethod = "Decimals Found in Degrees Portion of Spreadsheet";
      $longVal = $longDig;
      $latVal = $latDig;
    } elseif (is_numeric($longDeg) &&
      is_numeric($longMin) &&
      is_numeric($longSec) &&
      is_numeric($latDeg) &&
      is_numeric($latMin) &&
      is_numeric($latDeg)) {
        $srcMethod = "Translated from DMS To Decimal";
        $longVal = $this->convertDMStoDecimal($longDeg, $longMin, $longSec);
        $latVal = $this->convertDMStoDecimal($latDeg, $latMin, $latSec);
    } elseif (is_numeric($geoUtmz) &&
      is_numeric($geoUtme) &&
      is_numeric($geoUtmn) &&
      (strlen($geoUtmn) < 10) &&
      (strlen($geoUtme) < 10)
    ) {
      $thisPoint = new GpointConverter;
      //print "\n"."Easting=".$geoUtme.", Northing=".$geoUtmn.", Zone=".$geoUtmz;
      list($latVal, $longVal) = $thisPoint->convertUtmToLatLng($geoUtme, $geoUtmn, $geoUtmz.'N');
      if ($latVal && $longVal) {
        $srcMethod='Translated from UTM To Decimal';
        //print "\n" . $srcMethod . ": (" . $longVal . ", " . $latVal . "); ";
      } else {
        print "Failure of Translation from UTM to Decimal - acc id " . $id . "\n";
      }
    }

    $geoHeritage = $srcMethod . " - Raw Decimal : " . $longDec . '/' . $latDec . '|' .
      'DMS : ' . $latDeg . '.' . $latMin . '.' . $latSec . '/' .
      $longDeg . '.' . $longMin . '.' . $longSec . '|' .
      'UTM : ' . $geoUtmz . '/' . $geoUtme . '/' . $geoUtmn . '|' .
      'Precision : ' . $prec;

    return array ($longVal, $latVal, $geoHeritage);
  }

/*
 * convertDMStoDecimal($deg,$min,$sec)
 *
 * $deg : INT degrees value
 * $min : INT minutes value
 * $sec : INT seconds value
 *
 * Converts degrees minutes and seconds to Decimal Long/Lat.
 * TODO : Validation!
 *
 * RETURNS : STR of decimal degrees.
 */
function convertDMStoDecimal($deg, $min, $sec) {
  $num = (float)$deg + ((float)$min * 60 + (float)$sec) / 3600;
  return number_format($num, 6);
}

  /*
   * testLongitudeLatitudeFormat($longitudeValue,$latitudeValue)
   *
   * $longitudeValue: STR of longitude representation.
   * $latitudeValue: STR of latitude representation.
   *
   * Tests longitude/latitude pairs for format AND range on global scale.
   * Localized testing can be handled downstream with _herbariumImportCheckLocalGeoRange().
   *
   * RETURNS : TRUE on validation, FALSE on fail.
   */
  public function testLongitudeLatitudeFormat($longitudeValue, $latitudeValue) {
    $latPatternValidator = '/
      \A
      [+-]?
      (?:
        90(?:\.0{1,6})?
        |
        \d
        (?(?<=9)|\d?)
        \.
        \d{1,6}
      )
      \z
    /x';

    $longPatternValidator = '/
      \A
      [+-]?
      (?:
        180(?:\.0{1,6})?
        |
        (?:
          1[0-7]\d
          |
          \d{1,2}
        )
        \.
        \d{1,6}
      )
      \z
    /x';

    if (preg_match($longPatternValidator, $longitudeValue) &&
      preg_match($latPatternValidator, $latitudeValue)) {
      return TRUE;
    }
    return FALSE;
  }

  /*
   * checkDayRange($dayValue)
   *
   * $dayValue : (hopefully) INT value of day-of-month.
   *
   * Validates day value intended for use in ISO date. TODO: Needs month value
   *
   * RETURNS : TRUE on validation, FALSE on fail.
   */
  public function isValidDayRange($dayValue) {
    if ($dayValue >= 1 && $dayValue <= 31) {
      return TRUE;
    }
    return FALSE;
  }

  /*
   * checkMonthRange($monthValue)
   *
   * $monthValue : (hopefully) INT value reprenenting month.
   *
   * Validates month value intended for use in ISO date.
   *
   * RETURNS : TRUE on validation, FALSE on fail.
   */
  public function isValidMonthRange($monthValue) {
    if ($monthValue >= 1 && $monthValue <= 12) {
      return TRUE;
    }
    return FALSE;
  }

  /*
   * checkYearRange($monthValue)
   *
   * $monthValue : (hopefully) INT value reprenenting month.
   *
   * Validates month value intended for use in ISO date.
   *
   * RETURNS : TRUE on validation, FALSE on fail.
   */
  public function isValidYearRange($yearValue) {
    if ($yearValue >= 1800 && $yearValue <= date("Y")) {
      return TRUE;
    }
    return FALSE;
  }

  /**
  * Check if a taxonony term exists.
  *
  * @param string $value
  *   The name of the term.
  * @param array $parents
  *   The parents of the term.
  *
  * @return mixed
  *   Returns the TID of the term, if it exists. False otherwise.
  */
  public function taxtermExists($value, $field, $vocabulary) {
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
   * Add a file to the public filesystem
   *
   * @param object $row
   *    The current row from CSV being migrated.
   * @param string $field_map
   *    The destination mapping to the file field.
   * @param string $source
   *    The full path & filename of the source file.
   *
   * @return  booleen
   *    Returns True if source file is found. False otherwise.
   */
  public function addFieldFile(&$row, $field_map, $source, $destination = 'public') {
    $file_basename = basename($source);
    $file_destination = "$destination://$file_basename";
    if (file_exists($source)) {
      $file_uri = file_unmanaged_copy($source, $file_destination,
        FILE_EXISTS_REPLACE);
      $file = File::Create([
        'uri' => $file_uri,
      ]);
      $row->setSourceProperty(
        $field_map,
        $file
      );
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

}
