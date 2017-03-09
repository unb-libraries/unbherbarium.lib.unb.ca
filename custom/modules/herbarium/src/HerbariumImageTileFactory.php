<?php

namespace Drupal\herbarium_specimen;

use Drupal\node\Entity\Node;

/**
 * HerbariumImageTileFactory caption set object.
 */
class HerbariumImageTileFactory {

  /**
   * The Drupal File object to generate the DZI and tiles for.
   *
   * @var object
   */
  protected $file;

  /**
   * Constructor.
   *
   * @param object $file
   *   The Drupal File object to generate the DZI and tiles for.
   */
  protected function __construct($file) {
    $this->file = $file;
  }

  /**
   * Build DZI and custom map tiles for an image.
   *
   * @param object $file
   *   The Drupal File object to generate the DZI and tiles for.
   */
  public static function BuildImageTiles($file) {
    // Remove old image tile stuff
    $obj = new static($file);
  }

}
