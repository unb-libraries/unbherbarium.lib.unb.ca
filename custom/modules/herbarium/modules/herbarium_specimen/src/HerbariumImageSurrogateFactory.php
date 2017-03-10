<?php

namespace Drupal\herbarium_specimen;

use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;

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
   * Remove local files after processing.
   *
   * @param object $file
   *   The Drupal TIFF File object to generate the DZI and tiles for.
   * @param array $context
   *   The Batch API context array.
   */
  public static function cleanupFiles($file, array &$context) {
    // Remove old image tile stuff.
    $obj = new static($file);
    $obj->deleteTempFiles($context);
  }

  public static function deleteExistingAssets($file, array &$context) {
    // Remove old image tile stuff.
    $obj = new static($file);
    $obj->deleteGeneratedAssets($context);
  }

  public static function attachSurrogatesToNode($file, $nid, array &$context) {
    // Remove old image tile stuff.
    die($nid);
    $obj = new static($file);
    $obj->attachNodeSurrogates($nid, $context);
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
  protected function deleteTempFiles(&$context) {
    $this->file->delete();
    exec(
      "cd {$this->file_path_parts['dirname']} && rm -rf *.jpg *.tif *.tiff",
      $output,
      $return
    );

    $context['message'] = t(
      'Deleted temporary processing files'
    );
  }

  /**
   * Delete any previous generated assets for this node.
   *
   * @param array $context
   *   The Batch API context array.
   */
  protected function deleteGeneratedAssets(&$context) {
    exec(
      "cd {$this->file_path_parts['dirname']} && rm -rf *.jpg *.dzi *_files",
      $output,
      $return
    );

    $context['message'] = t('Deleted previously generated assets for specimen.');
  }

  /**
   * Attach the generated JPG images to the specimen node.
   *
   * @param array $context
   *   The Batch API context array.
   */
  protected function attachNodeSurrogates($nid, &$context) {
    $node = Node::load($nid);

    $unmasked_filename =  "{$this->file_path_parts['dirname']}/{$this->file_path_parts['filename']}.jpg";

    // Create file.
    $target_path = 'private://specimen_images';
    file_prepare_directory($target_path, FILE_CREATE_DIRECTORY);
    $file_destination = "$target_path/$nid.jpg";
    $uri  = file_unmanaged_copy($unmasked_filename, $file_destination, FILE_EXISTS_REPLACE);
    $file = File::Create([
      'uri' => $uri,
    ]);

    if (!empty($node->get('field_large_sample_surrogate')->entity)) {
      $node->get('field_large_sample_surrogate')->entity->delete();
    }

    $node->get('field_large_sample_surrogate')->setValue($file);
    $node->save();

    $context['message'] = t('Attached unmasked image to specimen.');
  }

}
