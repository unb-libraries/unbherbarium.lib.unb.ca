<?php

namespace Drupal\herbarium_specimen;

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
   * @param array $context
   *   The Batch API context array.
   */
  public static function buildImageTiles($file, array &$context) {
    // Remove old image tile stuff.
    $context['message'] = t(
      'Generating DZI and tiled images for specimen image [@fid]',
      array(
        '@fid' => $file->id(),
      )
    );

    $obj = new static($file);
    $obj->deleteExistingTiles();
    $obj->generateTiles();

    $context['results'][] = t(
      'Generated DZI and tiled images for specimen image [@fid]',
      array(
        '@fid' => $file->id(),
      )
    );
  }

  /**
   * Delete the existing tiles and DZI for this file, if they exist.
   */
  protected function deleteExistingTiles() {
  }

  /**
   * Generate the tiles and DZI for this file.
   */
  protected function generateTiles() {
  }

}
