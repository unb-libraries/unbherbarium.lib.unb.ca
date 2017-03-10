<?php

namespace Drupal\herbarium_specimen;

/**
 * HerbariumImageSurrogateFactory caption set object.
 */
class HerbariumImageSurrogateFactory {

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
  public static function buildDZITiles($file, array &$context) {
    // Remove old image tile stuff.
    $obj = new static($file);
    $obj->deleteExistingTiles();
    $obj->generateDZITiles($context);
  }

  /**
   * Build surrogate JPG from TIFF to be used for derivatives.
   *
   * @param object $file
   *   The Drupal TIFF File object to generate the DZI and tiles for.
   * @param array $context
   *   The Batch API context array.
   */
  public static function buildJPGSurrogate($file, array &$context) {
    // Remove old image tile stuff.
    $obj = new static($file);
    $obj->generateJpgSurrogate($context);
  }

  /**
   * Remove local TIFF after archiving to remote store.
   *
   * @param object $file
   *   The Drupal TIFF File object to generate the DZI and tiles for.
   * @param array $context
   *   The Batch API context array.
   */
  public static function deleteLocalTiff($file, array &$context) {
    // Remove old image tile stuff.
    $obj = new static($file);
    $obj->deleteTempArchivalFile($context);
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
  protected function generateDZITiles(&$context) {
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
   * Delete the uploaded archival tiff from local.
   *
   * @param array $context
   *   The Batch API context array.
   */
  protected function deleteTempArchivalFile(&$context) {
    $this->file->delete();

    $context['message'] = t(
      'Deleted locally uploaded archival TIFF'
    );
  }
}
