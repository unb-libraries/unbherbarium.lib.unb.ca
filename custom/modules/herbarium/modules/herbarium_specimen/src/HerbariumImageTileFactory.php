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
   * An associative array : file path information as returned by pathinfo().
   *
   * @var array
   */
  protected $file_path_parts;

  /**
   * Constructor.
   *
   * @param object $file
   *   The Drupal File object to generate the DZI and tiles for.
   */
  protected function __construct($file) {
    $this->file = $file;
    $file_path = drupal_realpath($this->file->getFileUri());
    $this->file_path_parts = pathinfo($file_path);
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
    $obj = new static($file);
    $obj->deleteExistingTiles();
    $obj->generateTiles($context);
  }

  /**
   * Delete the existing tiles and DZI for this file, if they exist.
   */
  protected function deleteExistingTiles() {
  }

  /**
   * Generate the tiles and DZI for this file.
   *
   * @param array $context
   *   The Batch API context array.
   */
  protected function generateTiles(&$context) {
    $context['message'] = t(
      'Generating DZI and tiled images for specimen image [@fid]',
      array(
        '@fid' => $this->file->id(),
      )
    );

    exec(
      "cd {$this->file_path_parts['dirname']} && /usr/local/bin/magick-slicer {$this->file_path_parts['basename']} --extension jpg",
      $output,
      $return
    );

    $context['results'][] = t(
      'Generated DZI and tiled images for specimen image [@fid]',
      array(
        '@fid' => $this->file->id(),
      )
    );
  }

}
