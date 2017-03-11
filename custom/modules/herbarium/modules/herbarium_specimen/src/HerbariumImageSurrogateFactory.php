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
   * The parent node of the file object.
   *
   * @var object
   */
  protected $node;

  /**
   * An associative array : file path information as returned by pathinfo().
   *
   * @var array
   */
  protected $filePathParts;

  /**
   * Constructor.
   *
   * @param object $fid
   *   The file ID of the archival TIFF File object.
   * @param object $nid
   *   The node id of the parent herbarium specimen.
   */
  protected function __construct($fid, $nid) {
    $this->file = File::load($fid);
    $this->node = Node::load($nid);

    $file_path = drupal_realpath($this->file->getFileUri());
    $this->filePathParts = pathinfo($file_path);
  }

  /**
   * Attach the image surrogates to the node image fields.
   *
   * @param object $fid
   *   The file ID of the archival TIFF File object.
   * @param object $nid
   *   The node id of the parent herbarium specimen.
   * @param array $context
   *   The Batch API context array.
   */
  public static function attachSurrogatesToNode($fid, $nid, array &$context) {
    $obj = new static($fid, $nid);
    $obj->attachNodeSurrogates($context);
  }

  /**
   * Build the DZI and tile files for the archival image.
   *
   * @param object $fid
   *   The file ID of the archival TIFF File object.
   * @param object $nid
   *   The node id of the parent herbarium specimen.
   * @param array $context
   *   The Batch API context array.
   */
  public static function buildDziTiles($fid, $nid, array &$context) {
    $obj = new static($fid, $nid);
    $obj->generateDziTiles($context);
  }

  /**
   * Create the master JPG surrogate from the archival image.
   *
   * @param object $fid
   *   The file ID of the archival TIFF File object.
   * @param object $nid
   *   The node id of the parent herbarium specimen.
   * @param array $context
   *   The Batch API context array.
   */
  public static function buildJpgSurrogate($fid, $nid, array &$context) {
    $obj = new static($fid, $nid);
    $obj->generateJpgSurrogate($context);
  }

  /**
   * Remove local files after processing.
   *
   * @param object $fid
   *   The file ID of the archival TIFF File object.
   * @param object $nid
   *   The node id of the parent herbarium specimen.
   * @param array $context
   *   The Batch API context array.
   */
  public static function cleanupFiles($fid, $nid, array &$context) {
    $obj = new static($fid, $nid);
    $obj->deleteTempFiles($context);
  }

  /**
   * Delete any existing assets from the DZI/Tile directory.
   *
   * @param object $fid
   *   The file ID of the archival TIFF File object.
   * @param object $nid
   *   The node id of the parent herbarium specimen.
   * @param array $context
   *   The Batch API context array.
   */
  public static function deleteExistingAssets($fid, $nid, array &$context) {
    $obj = new static($fid, $nid);
    $obj->deleteGeneratedAssets($context);
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
  protected function generateDziTiles(array &$context) {
    $nid = $this->node->id();

    exec(
      "cd {$this->filePathParts['dirname']} && /usr/local/bin/magick-slicer $nid.jpg",
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
  protected function generateJpgSurrogate(array &$context) {
    $nid = $this->node->id();

    exec(
      "cd {$this->filePathParts['dirname']} && convert {$this->filePathParts['basename']} $nid.jpg",
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
  protected function deleteTempFiles(array &$context) {
    $this->file->delete();
    exec(
      "cd {$this->filePathParts['dirname']} && rm -rf *.jpg *.tif *.tiff",
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
  protected function deleteGeneratedAssets(array &$context) {
    exec(
      "cd {$this->filePathParts['dirname']} && rm -rf *.jpg *.dzi *_files",
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
  protected function attachNodeSurrogates(array &$context) {
    $nid = $this->node->id();
    $unmasked_filename = "{$this->filePathParts['dirname']}/$nid.jpg";

    // Create file.
    $target_path = 'private://specimen_images';
    file_prepare_directory($target_path, FILE_CREATE_DIRECTORY);
    $file_destination = "$target_path/$nid.jpg";
    $uri = file_unmanaged_copy($unmasked_filename, $file_destination, FILE_EXISTS_REPLACE);
    $file = File::Create([
      'uri' => $uri,
    ]);

    if (!empty($this->node->get('field_large_sample_surrogate')->entity)) {
      $this->node->get('field_large_sample_surrogate')->entity->delete();
    }

    $this->node->get('field_large_sample_surrogate')->setValue($file);
    $this->node->save();

    $context['message'] = t('Attached unmasked image to specimen.');
  }

}
