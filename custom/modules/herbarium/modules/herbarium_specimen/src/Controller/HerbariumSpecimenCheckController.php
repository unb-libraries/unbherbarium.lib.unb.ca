<?php

namespace Drupal\herbarium_specimen\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;

/**
 * VideoNodeCheckController object.
 */
class HerbariumSpecimenCheckController extends ControllerBase {

  /**
   * Check to see if a node is a herbarium specimen.
   */
  public function checkAccess($node) {
    $actualNode = Node::load($node);
    return AccessResult::allowedIf($actualNode->bundle() === 'herbarium_specimen');
  }

}
