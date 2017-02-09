<?php

namespace Drupal\unb_herbarium_migrate_csv\Term;

use Drupal\taxonomy\Entity\Term;

/**
 * Defines the object for creating terms from a taxonomy CSV row.
 */
class TermCreatorRow {

  /**
   * The term name.
   *
   * @var array
   */
  public $data = NULL;

  /**
   * The species ID for this specific species.
   *
   * @var string
   */
  public $specid = NULL;

  /**
   * The species level name for this species.
   *
   * @var string
   */
  public $spec = NULL;

  /**
   * The english common name(s).
   *
   * @var string
   */
  public $commonname = NULL;

  /**
   * The french common name(s).
   *
   * @var string
   */
  public $frenchname = NULL;

  /**
   * The synonyms used for this specific species.
   *
   * @var string
   */
  public $synonyms = NULL;

  /**
   * The authority for this specific species.
   *
   * @var string
   */
  public $auth = NULL;

  /**
   * The subspecies type for this specific species.
   *
   * @var string
   */
  public $txt = NULL;

  /**
   * The subspecies name.
   *
   * @var string
   */
  public $txn = NULL;

  /**
   * The family name.
   *
   * @var string
   */
  public $family = NULL;

  /**
   * The hybrid type.
   *
   * @var string
   */
  public $xt = NULL;

  /**
   * The hybrid name.
   *
   * @var string
   */
  public $xn = NULL;

  /**
   * The authority for the hybrid name.
   *
   * @var string
   */
  public $xndauth = NULL;

  /**
   * The genus ID.
   *
   * @var string
   */
  public $genid = NULL;

  /**
   * The rarity ranking of this species.
   *
   * @var string
   */
  public $freqcdhal = NULL;

  /**
   * The genus name.
   *
   * @var string
   */
  public $gen = NULL;

  /**
   * The family ID.
   *
   * @var string
   */
  public $famid = NULL;

  /**
   * The full name of the species.
   *
   * @var string
   */
  public $fullnam = NULL;

  /**
   * Is this species found in NB?
   *
   * @var string
   */
  public $foundinnb = NULL;

  /**
   * The first name assigned to this species.
   *
   * @var string
   */
  public $name1st = NULL;

  /**
   * The status of this species.
   *
   * @var string
   */
  public $status = NULL;

  /**
   * The species name, including the genus.
   *
   * @var string
   */
  public $gensp = NULL;

  /**
   * The variant type (Again?).
   *
   * @var string
   */
  public $spvar = NULL;

  /**
   * The variant authority.
   *
   * @var string
   */
  public $svauth = NULL;

  /**
   * The hybrid hybrid name.
   *
   * @var string
   */
  public $xxn = NULL;

  /**
   * Does the species have vars?
   *
   * @var string
   */
  public $specieswithvars = NULL;

  /**
   * Notes on this specific species.
   *
   * @var string
   */
  public $notes = NULL;

  /**
   * Is this on the NB species list.
   *
   * @var string
   */
  public $nbspecieslist = NULL;

  /**
   * Concatenated Genus-Species Name.
   *
   * @var string
   */
  public $genspcalc = NULL;

  /**
   * The sample category.
   *
   * @var string
   */
  public $category = NULL;

  /**
   * The cabinet this species is stored in.
   *
   * @var string
   */
  public $cabinet = NULL;

  /**
   * The alternate name for this species.
   *
   * @var string
   */
  public $alternatename = NULL;

  /**
   * The flag for if this species is invasive.
   *
   * @var string
   */
  public $invasivespecies = NULL;

  /**
   * The ITIS code for this species.
   *
   * @var string
   */
  public $itiscode = NULL;

  /**
   * Constructor.
   */
  public function __construct($data) {
    $this->data = $data;
    $this->setPropertiesFromData();
  }

  /**
   * Check if a stub term relating to this row exists.
   *
   * @param string $value
   *   The name of the term.
   * @param array $parents
   *   The parents of the term.
   *
   * @return mixed
   *   Returns the TID of the stub term, if it exists. False otherwise.
   */
  public function checkStubTermExists($value, $parents) {
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', 'herbarium_specimen_taxonomy');
    $query->condition('name', $value);

    $tids = $query->execute();
    if (!empty($tids)) {
      foreach ($tids as $tid) {
        $storage = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term');
        $test_parents = $storage->loadParents($tid);
        $test_parents_array = array_keys($test_parents);
        if ($test_parents_array == $parents) {
          return $tid;
        }
      }
    }
    return FALSE;
  }

  /**
   * Create a full hybrid level taxonomy term.
   *
   * @param array $parents
   *   The parents of the term.
   */
  public function createFullHybridLevelTerm($parents) {
    $stub_tid = $this->checkStubTermExists($this->xn, $parents);
    if (!empty($stub_tid)) {
      $term = Term::load($stub_tid);
      $term->set('name', $this->xn);
      $term->set('parent', $parents);
    }
    else {
      $term = Term::create([
        'vid' => 'herbarium_specimen_taxonomy',
        'name' => $this->xn,
        'parent' => array($parents),
      ]);
    }

    $term->set('field_dwc_taxonrank', $this->txt);
    $this->setFullProperties($term);
    $term->save();
  }

  /**
   * Create a full species level taxonomy term.
   *
   * @param array $parents
   *   The parents of the term.
   */
  public function createFullSpeciesLevelTerm($parents) {
    $stub_tid = $this->checkStubTermExists($this->spec, $parents);
    if (!empty($stub_tid)) {
      $term = Term::load($stub_tid);
      $term->set('name', $this->spec);
      $term->set('parent', $parents);
    }
    else {
      $term = Term::create([
        'vid' => 'herbarium_specimen_taxonomy',
        'name' => $this->spec,
        'parent' => array($parents),
      ]);
    }

    $term->set('field_dwc_taxonrank', 'Species');
    $this->setFullProperties($term);
    $term->save();
  }

  /**
   * Create a full variant level taxonomy term.
   *
   * @param array $parents
   *   The parents of the term.
   */
  public function createFullVariantLevelTerm($parents) {
    $stub_tid = $this->checkStubTermExists($this->txn, $parents);
    if (!empty($stub_tid)) {
      $term = Term::load($stub_tid);
      $term->set('name', $this->txn);
      $term->set('parent', $parents);
    }
    else {
      $term = Term::create([
        'vid' => 'herbarium_specimen_taxonomy',
        'name' => $this->txn,
        'parent' => array($parents),
      ]);
    }

    $term->set('field_dwc_taxonrank', $this->txt);
    $this->setFullProperties($term);
    $term->save();
  }

  /**
   * Create a stub taxonomy term to be later fully populated.
   *
   * @param string $value
   *   The name of the term.
   * @param string $unique_id
   *   The unique ID used to identify the term.
   * @param array $parents
   *   The parents of the term.
   *
   * @return int
   *   Returns the TID of the created stub term, or an existing one matching
   *   the given values.
   */
  public function createStubTerm($value, $unique_id = NULL, $parents = array()) {
    $family_tid = $this->checkStubTermExists($value, $parents);
    if (empty($family_tid)) {
      $term = Term::create([
        'vid' => 'herbarium_specimen_taxonomy',
        'name' => $value,
        'parent' => $parents,
        'field_dwc_taxonid' => $unique_id,
      ]);
      $term->save();
      return $term->id();
    }
    return $family_tid;
  }

  /**
   * Create a taxonomy term from the row of data.
   */
  public function createTermFromRow() {
    if (trim($this->spec) == '') {
      $this->spec = 'Unknown';
    }
    if (trim($this->txn) == '') {
      $this->txn = 'Unknown';
    }
    if (trim($this->xn) == '') {
      $this->xn = 'Unknown';
    }

    if (!empty($this->xt)) {
      // This is hybrid level species item.
      $family_tid = $this->createStubTerm($this->family, $this->famid);
      $genus_tid = $this->createStubTerm($this->gen, $this->genid, array($family_tid));
      $species_tid = $this->createStubTerm($this->spec, NULL, array($genus_tid));
      $variant_tid = $this->createStubTerm($this->txn, NULL, array($species_tid));
      $this->createFullHybridLevelTerm(array($variant_tid));
    }
    elseif (!empty($this->txt)) {
      // This is ssp/variant level species item.
      $family_tid = $this->createStubTerm($this->family, $this->famid);
      $genus_tid = $this->createStubTerm($this->gen, $this->genid, array($family_tid));
      $species_tid = $this->createStubTerm($this->spec, NULL, array($genus_tid));
      $this->createFullVariantLevelTerm(array($species_tid));
    }
    else {
      // This is species level species item.
      $family_tid = $this->createStubTerm($this->family, $this->famid);
      $genus_tid = $this->createStubTerm($this->gen, $this->genid, array($family_tid));
      $this->createFullSpeciesLevelTerm(array($genus_tid));
    }
  }

  /**
   * Get data from a row's column.
   *
   * @param string $row_id
   *   The column index.
   *
   * @return string
   *   Returns the data contained in the column.
   */
  public function getRowColumnData($row_id) {
    $data = trim($this->data[$row_id]);
    return $data;
  }

  /**
   * Set this object's properties from the data stored within $data.
   */
  public function setPropertiesFromData() {
    $this->specid = $this->getRowColumnData(0);
    $this->spec = $this->getRowColumnData(1);
    $this->commonname = $this->getRowColumnData(2);
    $this->frenchname = $this->getRowColumnData(3);
    $this->synonyms = $this->getRowColumnData(4);
    $this->auth = $this->getRowColumnData(5);
    $this->txt = $this->getRowColumnData(6);
    $this->txn = $this->getRowColumnData(7);
    $this->family = $this->getRowColumnData(8);
    $this->taxauth = $this->getRowColumnData(9);
    $this->xt = $this->getRowColumnData(10);
    $this->xn = $this->getRowColumnData(11);
    $this->xndauth = $this->getRowColumnData(12);
    $this->genid = $this->getRowColumnData(13);
    $this->freqcdhal = $this->getRowColumnData(14);
    $this->gen = $this->getRowColumnData(15);
    $this->famid = $this->getRowColumnData(16);
    $this->fullnam = $this->getRowColumnData(17);
    $this->foundinnb = $this->getRowColumnData(18);
    $this->name1st = $this->getRowColumnData(19);
    $this->status = $this->getRowColumnData(20);
    $this->gensp = $this->getRowColumnData(21);
    $this->spvar = $this->getRowColumnData(22);
    $this->svauth = $this->getRowColumnData(23);
    $this->xxn = $this->getRowColumnData(24);
    $this->specieswithvars = $this->getRowColumnData(28);
    $this->notes = $this->getRowColumnData(29);
    $this->nbspecieslist = $this->getRowColumnData(30);
    $this->genspcalc = $this->getRowColumnData(31);
    $this->category = $this->getRowColumnData(32);
    $this->cabinet = $this->getRowColumnData(33);
    $this->alternatename = $this->getRowColumnData(34);
    $this->invasivespecies = $this->getRowColumnData(36);
    $this->itiscode = $this->getRowColumnData(37);
  }

  /**
   * Set this the term field data.
   */
  public function setFullProperties(&$term) {
    $term->set('field_dwc_taxonid', $this->specid);
  }

}
