<?php

/**
 * @file
 * Contains \Drupal\migrate\Event\MigrateMapDeleteEvent.
 */

namespace Drupal\unb_herbarium_migrate_csv\Event;

use Drupal\migrate_plus\Event\MigrateEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Drupal\Core\Datetime\DrupalDateTime;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\unb_herbarium_migrate_csv\lib\gPoint;

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
    $row = $event->getRow();

    $year = (trim($row->getSourceProperty('year')) != '')  ? $row->getSourceProperty('year') : NULL;
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
    $date_str = '';
    $date_str = 'Y: ' . $year . ' M: ' . $month . ' D: ' . $day;
    $row->setSourceProperty('dwc_verbatimeventdate', $date_str);

    // Record Creation Date.
    $iso_date = $date_array = $date_str = '';
    $date_str = str_replace("/", "-", trim($row->getSourceProperty('dc_created')));
    list($date_array['year'], $date_array['month'], $date_array['day']) = array_filter(explode("-", $date_str));
    if (!empty($date_array)) {
      if ($this->isValidYearRange($date_array['year']) &&
        $this->isValidMonthRange($date_array['month']) &&
        $this->isValidDayRange($date_array['day'])
      ) {
        $iso_date = DrupalDateTime::arrayToISO($date_array);
      }
    }
    $row->setSourceProperty('date_created_iso', $iso_date);

    // DwC Modified from Filemaker (Date + time).
    $filemaker_date = $date_array = $date_str = '';
    $date_str = str_replace("/", "-", trim($row->getSourceProperty('dc_modified')));
    list($date_array['year'], $date_array['month'], $date_array['day']) = array_filter(explode("-", $date_str));
    list($date_array['hour'], $date_array['minute'], $date_array['second']) = array('00', '00', '00');
    if (!empty($date_array)) {
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

    // Coordinate Precision.
    $precisionValue = trim($row->getSourceProperty('coordinateprecision'));
    $row->setSourceProperty('verbatim_coordinateprec', $precisionValue);
    $coordPrec = $this->precMap($precisionValue);
    $row->setSourceProperty('mapped_coord_prec', $coordPrec);

    // Geo Heritage (Longitude/Latitude).
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
    $accNum = trim($row->getSourceProperty('record_number'));

    $longLatItems = array(
      $accNum,
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
    list($decLong, $decLat, $geoRefRem) =  $this->determineLongitudeLatitude($longLatItems);
    $row->setSourceProperty('geo_heritage', $geoRefRem);
    if ($decLong != NULL) {
      $country = trim($row->getSourceProperty('country'));
      $isCanada = (substr(strtolower($country), 0 , 3) === "can" ) ? TRUE : FALSE;
      if ($decLong > 0 && $isCanada) {
        // Canadian Longitude should be negative.
        $decLong = $decLong * (-1);
      }
      $row->setSourceProperty('dwc_longitude', $decLong);
      $row->setSourceProperty('dwc_latitude', $decLat);
    }

    // Temporary title.
    $tmp_title = (trim($row->getSourceProperty('tmp_title')) != '')  ? $row->getSourceProperty('tmp_title') : 'Unavailable';
    $row->setSourceProperty('title_string', $tmp_title);

    // Empty record number?
    if (empty($accNum)) {
      $accNum = "Unavailable";
    }
    $row->setSourceProperty('record_number_string', $accNum);

    // Sample Collectors
    $sample_collector_ids = array();
    $fieldname = 'name';
    $vocabulary = 'herbarium_sample_collectors';
    $collectors = explode(";", $row->getSourceProperty('collectors'));
    foreach ($collectors as $value) {
      $term_value = trim($value);
      if(!empty($term_value)) {
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
        $sample_collector_ids[] = $term->id();
      }
    }
    $row->setSourceProperty('sample_collectors', $sample_collector_ids);

    // Sample Taxonomy
    $fieldname = 'field_dwc_taxonid';
    $vocabulary = 'herbarium_sample_taxonomy';
    $tax_id = trim($row->getSourceProperty('assigned_taxon'));
    print "Taxon #:".$tax_id.", ";
    $term_tid = $this->taxtermExists($tax_id, $fieldname, $vocabulary);
    print "Term id:".$term_tid.";  ";
    if (is_numeric($term_tid)) {
      $term = Term::load($term_tid);
      $assign_taxon_id = $term->id();
      $row->setSourceProperty('assigned_taxon', $assign_taxon_id);
    }
  }

  // Determine and return Coordinate Precision.
  public function precMap($prec) {
    $coordPrec = '';
    if (is_numeric($prec)) {
      $intPrec = floor ($prec);
      if ($intPrec >= 1 && $intPrec <= 5) {
        $precisionMap = array(
          1 => '.00001',
          2 => '.0001',
          3 => '.001',
          4 => '.01',
          5 => '.1',
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
    list($id, $longDec, $latDec, $longDig, $latDig, $longDeg, $longMin, $longSec, $latDeg, $latMin, $latSec, $geoUtmz, $geoUtme, $geoUtmn) = $longLatVals;
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

      $thisPoint = new gPoint();
      $thisPoint->setUTM( $geoUtmz, $geoUtme, $geoUtmn.'T');
      $thisPoint->convertTMtoLL();
      if ($thisPoint->lat && $thisPoint->long) {
        $srcMethod='Translated from UTM To Decimal';
        list($longVal, $latVal)=array($thisPoint->long, $thisPoint->lat);
      } else {
        print "Failure of Translation from UTM to Decimal";
      }
    }

    $geoHeritage = $srcMethod . " - Raw Decimal : " . $longDec . '/' . $latDec . '|' .
      'DMS : ' . $latDeg . '.' . $latMin . '.' . $latSec . '/' .
      $longDeg . '.' . $longMin . '.' . $longSec . '|' .
      'UTM : ' . $geoUtmz . '/' . $geoUtme . '/' . $geoUtmn;

    return array ($longVal, $latVal, $geoHeritage);

    /*
    if ($tempLong && $tempLat) {


      // Now check if Decimals are in range.
      if (_herbariumImportCheckLocalGeoRange($tempLong,$tempLat)) {
        _herbariumImportLogReport(" Within acceptable range. Validated.)\n");

        // Format to standard '6 after decimal'
        $tempLat=number_format($tempLat,6);
        $tempLong=number_format($tempLong,6);
        $itemArray['verifiedlat']=$tempLat;
        $itemArray['verifiedlong']=$tempLong;
        $itemArray['sourcemethod']=$srcMethod;
        _herbariumImportLogReport("Coordinates : $tempLat,$tempLong\n");
        _herbariumImportIncrementSystemVariable('herbarium_sample_import_geo_success');

        // print "$tempLat\t$tempLong\n";
      } else {
        _herbariumImportLogReport(" Not within acceptable range. Not included.)\n");
      }
    }*/
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
   * Check if a herbarium sample collector term exists.
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
}
