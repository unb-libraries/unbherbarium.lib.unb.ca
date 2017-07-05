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

  /**
   * Check to see if a node is a herbarium specimen, and has DZI tiles.
   */
  public function checkInspectAccess($node) {
    $actualNode = Node::load($node);

    $dzi_path = \Drupal::service('file_system')->realpath(file_default_scheme() . "://") .
      "/dzi/$node/$node.dzi";

    return AccessResult::allowedIf(
      $actualNode->bundle() === 'herbarium_specimen' &&
      file_exists($dzi_path)
    );
  }

}
