<?php

namespace Drupal\unb_herbarium_migrate_csv\Term;

use Drupal\taxonomy\Entity\Term;

define('IMPORT_SPEC_NAME_UNKNOWN_VALUE', 'Unknown');

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

  public $taxauth = NULL;

  /**
   * Constructor.
   */
  public function __construct($data) {
    $this->data = $data;
    $this->setPropertiesFromData();
  }

  /**
   * Check if a taxonony term exists.
   *
   * @param string $value
   *   The name of the term.
   * @param string $field
   *   The machine name of the field.
   * @param string $vocabulary
   *   The machine name of the vocabulary.
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
   * Generate Taxon Rank vocabulary terms.
   *
   * @param string $taxon_rank
   *   The name of the taxon rank term.
   *
   * @return mixed
   *   Returns the TID of the pre-existing/created taxon rank term.
   */
  public function getTaxonRankId($taxon_rank) {
    $rank_term_tid = $this->taxtermExists($taxon_rank, 'name', 'taxon_rank');
    if (!empty($rank_term_tid)) {
      $rank_term = Term::load($rank_term_tid);
    }
    else {
      $rank_term = Term::create([
        'vid' => 'taxon_rank',
        'name' => $taxon_rank,
      ]);
      $rank_term->save();
    }

    return $rank_term->id();
  }

  /**
   * Check if a stub term relating to this row exists.
   *
   * @param string $name
   *   The name of the term.
   * @param array $parent
   *   The TID of the parent term.
   *
   * @return mixed
   *   Returns the TID of the stub term, if it exists. False otherwise.
   */
  public function checkStubTermExists($name, $parent = NULL) {
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', 'herbarium_specimen_taxonomy');
    $query->condition('name', $name);

    $tids = $query->execute();
    if (!empty($tids)) {
      foreach ($tids as $tid) {
        $storage = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term');
        $test_parents = $storage->loadParents($tid);
        $test_parents_array = array_keys($test_parents);
        $parent_tid = array_pop($test_parents_array);
        if ($parent_tid == $parent) {
          return $tid;
        }
      }
    }
    return FALSE;
  }

  /**
   * Create a stub taxonomy term to be later fully populated.
   *
   * @param string $name
   *   The name of the term.
   * @param int $parent
   *   The TID of the parent term.
   *
   * @return int
   *   Returns the TID of the created stub term, or an existing one matching
   *   the given values.
   */
  public function createStubTerm($name, $taxon_rank = 'Family', $parent = 0) {
    $stub_tid = $this->checkStubTermExists($name, $parent);
    if ($stub_tid == FALSE) {
      $term = Term::create([
        'vid' => 'herbarium_specimen_taxonomy',
        'name' => $name,
        'parent' => array($parent),
      ]);

      // Populate DwC:taxonRank field.
      $term->set('field_dwc_taxonrank', $this->getTaxonRankId($taxon_rank));

      $term->save();
      return $term->id();
    }
    return $stub_tid;
  }

  /**
   * Create a taxonomy term from the row of data.
   */
  public function createTermFromRow() {

    // Level 5 Record.
    if (
      $this->xt != ''
    ) {

      $spec_label = $this->xt;
      $spec_name = $this->xn;
      $spec_auth = $this->xndauth;

      if ($spec_name == '') {
        $spec_name = IMPORT_SPEC_NAME_UNKNOWN_VALUE;
      }

      $family_tid = $this->createStubTerm($this->family);
      $genus_tid = $this->createStubTerm($this->gen, 'Genus', $family_tid);
      $species_tid = $this->createStubTerm($this->spec, 'Species', $genus_tid);
      $variant_tid = $this->createStubTerm($this->txn, $this->xt, $species_tid);

      $stub_tid = $this->checkStubTermExists($spec_name, $variant_tid);

      if (!empty($stub_tid)) {
        $term = Term::load($stub_tid);
        $term->set('name', $spec_name);
        $term->set('parent', array($variant_tid));
        $term->set('field_dwc_scientificnameauthor', $spec_auth);
      }
      else {
        $term = Term::create([
          'vid' => 'herbarium_specimen_taxonomy',
          'name' => $spec_name,
          'parent' => array($variant_tid),
          'field_dwc_scientificnameauthor' => $spec_auth,
          'field_dwc_taxonrank' => $this->getTaxonRankId($this->xt),
        ]);
      }

      $this->setFullProperties($term);
      $term->save();
    }

    // Level 4 Record.
    elseif (
      $this->txt != '' &&
      $this->xt == '' &&
      $this->xn == '' &&
      $this->spec != ''
    ) {
      $spec_label = $this->txt;
      $spec_name = $this->txn;
      $spec_auth = $this->taxauth;

      if ($spec_name == '') {
        $spec_name = IMPORT_SPEC_NAME_UNKNOWN_VALUE;
      }

      $family_tid = $this->createStubTerm($this->family);
      $genus_tid = $this->createStubTerm($this->gen, 'Genus', $family_tid);
      $species_tid = $this->createStubTerm($this->spec, 'Species', $genus_tid);

      $stub_tid = $this->checkStubTermExists($spec_name, $species_tid);

      if (!empty($stub_tid)) {
        $term = Term::load($stub_tid);
        $term->set('name', $spec_name);
        $term->set('parent', array($species_tid));
        $term->set('field_dwc_scientificnameauthor', $spec_auth);
      }
      else {
        $term = Term::create([
          'vid' => 'herbarium_specimen_taxonomy',
          'name' => $spec_name,
          'parent' => array($species_tid),
          'field_dwc_scientificnameauthor' => $spec_auth,
          'field_dwc_taxonrank' => $this->getTaxonRankId($this->txt),
        ]);
      }

      $this->setFullProperties($term);
      $term->save();
    }

    // Level 3.1 Record.
    elseif (
      $this->txt != '' &&
      $this->xt == '' &&
      $this->xn == '' &&
      $this->spec == ''
    ) {
      $spec_label = $this->txt;
      $spec_name = $this->txn;
      $spec_auth = $this->taxauth;

      if ($spec_name == '') {
        $spec_name = IMPORT_SPEC_NAME_UNKNOWN_VALUE;
      }

      $family_tid = $this->createStubTerm($this->family);
      $genus_tid = $this->createStubTerm($this->gen, 'Genus', $family_tid);
      $stub_tid = $this->checkStubTermExists($spec_name, 'sect. Species', $genus_tid);

      if (!empty($stub_tid)) {
        $term = Term::load($stub_tid);
        $term->set('name', $spec_name);
        $term->set('parent', array($genus_tid));
        $term->set('field_dwc_scientificnameauthor', $spec_auth);
      }
      else {
        $term = Term::create([
          'vid' => 'herbarium_specimen_taxonomy',
          'name' => $spec_name,
          'parent' => array($genus_tid),
          'field_dwc_scientificnameauthor' => $spec_auth,
          'field_dwc_taxonrank' => $this->getTaxonRankId($this->txt),
        ]);
      }

      $this->setFullProperties($term);
      $term->save();
    }

    // Level 3.2 Record.
    elseif (
      $this->spec != '' &&
      $this->txt == '' &&
      $this->txn == '' &&
      $this->xt == '' &&
      $this->xn == ''
    ) {
      $spec_label = 'sp.';
      $spec_name = $this->spec;
      $spec_auth = $this->auth;

      $family_tid = $this->createStubTerm($this->family);
      $genus_tid = $this->createStubTerm($this->gen, 'Genus', $family_tid);
      $stub_tid = $this->checkStubTermExists($spec_name, 'Species', $genus_tid);

      if (!empty($stub_tid)) {
        $term = Term::load($stub_tid);
        $term->set('name', $spec_name);
        $term->set('parent', array($genus_tid));
        $term->set('field_dwc_scientificnameauthor', $spec_auth);
      }
      else {
        $term = Term::create([
          'vid' => 'herbarium_specimen_taxonomy',
          'name' => $spec_name,
          'parent' => array($genus_tid),
          'field_dwc_scientificnameauthor' => $spec_auth,
          'field_dwc_taxonrank' => $this->getTaxonRankId('Species'),
        ]);
      }

      $this->setFullProperties($term);
      $term->save();
    }

    // Level 2 Record.
    elseif (
      $this->gen != '' &&
      $this->spec == '' &&
      $this->txt == '' &&
      $this->txn == '' &&
      $this->xt == '' &&
      $this->xn == ''
    ) {
      $spec_label = 'gen.';
      $spec_name = $this->gen;
      $spec_auth = $this->auth;

      $family_tid = $this->createStubTerm($this->family);
      $stub_tid = $this->checkStubTermExists($spec_name, $family_tid);

      if (!empty($stub_tid)) {
        $term = Term::load($stub_tid);
        $term->set('name', $spec_name);
        $term->set('parent', array($family_tid));
        $term->set('field_dwc_scientificnameauthor', $spec_auth);
      }
      else {
        $term = Term::create([
          'vid' => 'herbarium_specimen_taxonomy',
          'name' => $spec_name,
          'parent' => array($family_tid),
          'field_dwc_scientificnameauthor' => $spec_auth,
        ]);
      }

      $this->setFullProperties($term);
      $term->save();
    }
    else {
      print "Skipping Row [{$this->specid}]\n";
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
    $term->set('field_dwc_measurementvalue', $this->status);
    $term->set('field_dc_replaces', $this->name1st);
    $term->set('field_cmh_english_common_names', array_map('trim', explode(',', $this->commonname)));
    $term->set('field_cmh_french_common_names', array_map('trim', explode(',', $this->frenchname)));
    $term->set('field_synonyms', array_map('trim', preg_split('/[\v;]/', $this->synonyms)));
  }

}
