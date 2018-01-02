<?php

namespace Drupal\herbarium_specimen\Controller;

use Drupal\node\Entity\Node;

/**
 * HerbariumSpecimenTitleController object.
 */
class HerbariumSpecimenTitleController {

  /**
   * Get title of herbarium specimen from Scientific Name of Assigned Taxon.
   */
  public function getSpecimenTitle($node) {
    $actualNode = Node::load($node);

    return $actualNode
      ->get('field_taxonomy_tid')
      ->get(0)
      ->entity
      ->get('field_cmh_full_specimen_name')->first()->view();
  }

}
