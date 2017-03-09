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
  public static function BuildImageTiles($file, &$context) {
    // Remove old image tile stuff
    $context['message'] = t(
      'Generating DZI and tiled images for specimen image [@fid]',
      array(
        '@fid' => $file->id()
      )
    );

    $obj = new static($file);
    $obj->DeleteExistingTiles();
    $obj->GenerateTiles();

    $context['results'][] = t(
      'Generated DZI and tiled images for specimen image [@fid]',
      array(
        '@fid' => $file->id()
      )
    );
  }

  /**
   * Delete the existing tiles and DZI for this file, if they exist.
   */
  protected function DeleteExistingTiles() {
  }

  /**
   * Generate the tiles and DZI for this file.
   */
  protected function GenerateTiles() {
  }

}
