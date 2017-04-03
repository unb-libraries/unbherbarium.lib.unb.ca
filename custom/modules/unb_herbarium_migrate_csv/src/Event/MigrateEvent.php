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

    // Surrogate Image Handling For Caching.
    $file_unmasked = $images_path . 'u-' . $accNum . '.jpg';
    $file_masked = $images_path . $accNum . '.jpg';
    $this->addFieldFile($row, 'image_file_unmasked', $file_unmasked);
    $this->addFieldFile($row, 'image_file_masked', $file_masked);

    $year = (trim($row->getSourceProperty('year')) != '') ? $row->getSourceProperty('year') : NULL;
    $month = (trim($row->getSourceProperty('month')) != '') ? $row->getSourceProperty('month') : NULL;
    $day = (trim($row->getSourceProperty('day')) != '') ? $row->getSourceProperty('day') : NULL;

    // Collection Date.
    $iso_date = '';
    if ($year != NULL) {
      $date_array = array(
        'year' => $year,
        'month' => $month,
        'day' => $day,
      );
      $iso_date = DrupalDateTime::arrayToISO($date_array);
    }
    $row->setSourceProperty('date_iso', $iso_date);

    // Verbatim Event Date.
    $date_str = 'Y: ' . $year . ' M: ' . $month . ' D: ' . $day;
    $row->setSourceProperty('dwc_verbatimeventdate', $date_str);

    // Record Creation Date.
    $iso_date = $date_str = '';
    $date_array = [];
    $date_str = str_replace("/", "-", trim($row->getSourceProperty('dc_created')));
    if (!empty($date_str)) {
      list($date_array['year'], $date_array['month'], $date_array['day']) = array_filter(explode("-", $date_str));
      if ($this->isValidYearRange($date_array['year']) &&
        $this->isValidMonthRange($date_array['month']) &&
        $this->isValidDayRange($date_array['day'])) {
        $iso_date = DrupalDateTime::arrayToISO($date_array);
      }
    }
    $row->setSourceProperty('date_created_iso', $iso_date);

    // DwC Modified from Filemaker (Date + time).
    $filemaker_date = $date_str = '';
    $date_array = [];
    $date_str = str_replace("/", "-", trim($row->getSourceProperty('dc_modified')));
    if (!empty($date_str)) {
      list($date_array['year'], $date_array['month'], $date_array['day']) = array_filter(explode("-", $date_str));
      list($date_array['hour'], $date_array['minute'], $date_array['second']) = array(
        '00',
        '00',
        '00'
      );

      if ($this->isValidYearRange($date_array['year']) &&
        $this->isValidMonthRange($date_array['month']) &&
        $this->isValidDayRange($date_array['day'])
      ) {
        $filemaker_date = DrupalDateTime::arrayToISO($date_array);
      }
      $row->setSourceProperty('date_modified_iso', $filemaker_date);
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

    // Temporary title => 255 chars of DetAnnList, aka Previous Identifications
    $dwc_previousidents_raw = trim($row->getSourceProperty('previous_identifications'));
    // DetAnnList field values are delimited by vertical tab character.
    $dwc_previousidents = explode("\v", $dwc_previousidents_raw);
    $row->setSourceProperty('previous_identifications', $dwc_previousidents);
    $tmp_title = ($dwc_previousidents_raw != '') ? substr($dwc_previousidents_raw, 0, 255) : 'Temporary title';
    $row->setSourceProperty('title_string', $tmp_title);

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

    // Sample Taxonomy
    $fieldname = 'field_dwc_taxonid';
    $vocabulary = 'herbarium_specimen_taxonomy';
    $tax_id = trim($row->getSourceProperty('assigned_taxon'));
    if (is_numeric($tax_id)) {
      $term_tid = $this->taxtermExists($tax_id, $fieldname, $vocabulary);
      if (!empty($term_tid)) {
        $term = Term::load($term_tid);
        $assign_taxon_id = $term->id();
        $row->setSourceProperty('assigned_taxon', $assign_taxon_id);
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
          1 => '0.00001',
          2 => '0.0001',
          3 => '0.001',
          4 => '0.01',
          5 => '0.1',
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
      is_numeric($geoUtmn)) {
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
function convertDMStoDecimal($deg,$min,$sec) {
  return number_format($deg+((($min*60)+($sec))/3600),6);
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

    if (preg_match($latPatternValidator, $longitudeValue) &&
      preg_match($longPatternValidator, $latitudeValue)) {
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
  function isValidMonthRange($monthValue) {
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
  function isValidYearRange($yearValue) {
    if ($yearValue >= 1800 && $yearValue <= date("Y")) {
      return TRUE;
    }
    return FALSE;
  }

  /**
  * Check if a herbarium taxonony term exists.
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
  public function addFieldFile(&$row, $field_map, $source) {
    $file_basename = basename($source);
    $file_destination = "public://$file_basename";
    if (file_exists($source)) {
      $file_uri = file_unmanaged_copy($source, $file_destination,
        FILE_EXISTS_REPLACE);
      $public_file = File::Create([
        'uri' => $file_uri,
      ]);
      $row->setSourceProperty(
        $field_map,
        $public_file
      );
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

}
