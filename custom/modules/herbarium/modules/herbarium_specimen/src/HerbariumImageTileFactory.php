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

  public static function buildJPGSurrogate($file, array &$context) {
    // Remove old image tile stuff.
    $obj = new static($file);
    $obj->generateJpgSurrogate($context);
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
    exec(
      "cd {$this->file_path_parts['dirname']} && /usr/local/bin/magick-slicer {$this->file_path_parts['filename']}.jpg",
      $output,
      $return
    );

    $context['message'] = t(
      'Generated DZI and tiled images for specimen image [@fid]',
      array(
        '@fid' => $this->file->id(),
      )
    );
  }

  /**
   * Generate the JPG surrogate to be used for this file.
   *
   * @param array $context
   *   The Batch API context array.
   */
  protected function generateJpgSurrogate(&$context) {
    exec(
      "cd {$this->file_path_parts['dirname']} && convert {$this->file_path_parts['basename']} {$this->file_path_parts['filename']}.jpg",
      $output,
      $return
    );

    $context['message'] = t(
      'Generated JPG specimen surrogate image for archival master [@fid]',
      array(
        '@fid' => $this->file->id(),
      )
    );
  }

  /**
   * Set a message in the batch status.
   *
   * @param string $message
   *   The message to display.
   * @param array $context
   *   The Batch API context array.
   */
  public static function setBatchMessage($message, &$context) {
    $context['message'] = $message;
  }

}
