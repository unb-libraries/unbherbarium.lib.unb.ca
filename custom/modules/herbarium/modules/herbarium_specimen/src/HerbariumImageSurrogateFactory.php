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
   * The amount of the original image height to mask, to cover label.
   *
   * @var float
   */
  protected $maskedHeightFactor = 0.23;

  /**
   * The amount of the original image width to mask, to cover label.
   *
   * @var float
   */
  protected $maskedWidthFactor = 0.45;

  /**
   * The path to write the DZI tiles and index.
   *
   * @var string
   */
  protected $nodeDziPath = NULL;

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

    $public_dzi_path = \Drupal::service('file_system')->realpath(file_default_scheme() . "://") . '/dzi';
    $this->nodeDziPath = "$public_dzi_path/$nid";
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
   * Create the maked JPG surrogate from the archival image.
   *
   * @param object $fid
   *   The file ID of the archival TIFF File object.
   * @param object $nid
   *   The node id of the parent herbarium specimen.
   * @param array $context
   *   The Batch API context array.
   */
  public static function buildMaskedJpgSurrogate($fid, $nid, array &$context) {
    $obj = new static($fid, $nid);
    $obj->generateMaskedJpgSurrogate($context);
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
    // $obj->deleteTempFiles($context);
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

    // Generate tiles from masked image.
    $cmd = "
      cd {$this->filePathParts['dirname']} &&
      mv {$nid}_masked.jpg $nid.jpg &&
      /usr/local/bin/magick-slicer {$nid}.jpg &&
      mkdir -p {$this->nodeDziPath} &&
      mv $nid.dzi {$this->nodeDziPath}/ &&
      mv {$nid}_files {$this->nodeDziPath}/
    ";

    exec(
      $cmd,
      $output,
      $return
    );

    $context['message'] = t(
      'Generated DZI and tiled images for specimen image [@fid]',
      [
        '@fid' => $this->file->id(),
      ]
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
      "cd {$this->filePathParts['dirname']} && convert {$this->filePathParts['basename']} -unsharp 0x1.0+0.5+0 $nid.jpg",
      $output,
      $return
    );

    $context['message'] = t(
      'Generated JPG specimen surrogate image for archival master [@fid]',
      [
        '@fid' => $this->file->id(),
      ]
    );
  }

  /**
   * Generate the masked JPG surrogate to be used for this file.
   *
   * @param array $context
   *   The Batch API context array.
   */
  protected function generateMaskedJpgSurrogate(array &$context) {
    $nid = $this->node->id();
    list($width, $height, $type, $attr) = getimagesize("{$this->filePathParts['dirname']}/$nid.jpg");
    $mask_start_x = $width * (1 - $this->maskedWidthFactor);
    $mask_start_y = $height * (1 - $this->maskedHeightFactor);

    exec(
      "cd {$this->filePathParts['dirname']} && convert $nid.jpg -strokewidth 0 -fill \"rgba(255,255,255,1)\" -draw \"rectangle $mask_start_x,$mask_start_y $width,$height\" {$nid}_masked.jpg",
      $output,
      $return
    );

    $context['message'] = t(
      'Generated Masked JPG specimen surrogate image for archival master [@fid]',
      [
        '@fid' => $this->file->id(),
      ]
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
      "cd {$this->filePathParts['dirname']} && rm -rf *.jpg *.dzi *_files && cd {$this->nodeDziPath} && rm -rf *.jpg *.dzi *_files",
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
    $masked_filename = "{$this->filePathParts['dirname']}/{$nid}_masked.jpg";

    // Create unmasked file object.
    $target_path_u = 'private://specimen_images';
    file_prepare_directory($target_path_u, FILE_CREATE_DIRECTORY);
    $file_destination_u = "$target_path_u/$nid.jpg";
    $uri_u = file_unmanaged_copy($unmasked_filename, $file_destination_u, FILE_EXISTS_REPLACE);
    $file_u = File::Create([
      'uri' => $uri_u,
    ]);
    $file_u->setPermanent();
    $file_u->save();

    // Create unmasked file object.
    $target_path_m = 'public://specimen_images';
    file_prepare_directory($target_path_m, FILE_CREATE_DIRECTORY);
    $file_destination_m = "$target_path_m/{$nid}_masked.jpg";
    $uri_m = file_unmanaged_copy($masked_filename, $file_destination_m, FILE_EXISTS_REPLACE);
    $file_m = File::Create([
      'uri' => $uri_m,
    ]);
    $file_m->setPermanent();
    $file_m->save();

    // Remove existing JPG surrogates.
    if (!empty($this->node->get('field_large_sample_surrogate')->entity)) {
      $this->node->get('field_large_sample_surrogate')->entity->delete();
    }
    if (!empty($this->node->get('field_large_sample_surrogate_msk')->entity)) {
      $this->node->get('field_large_sample_surrogate_msk')->entity->delete();
    }

    // Attach new existing JPG surrogates.
    $this->node->get('field_large_sample_surrogate')->setValue($file_u);
    $this->node->get('field_large_sample_surrogate_msk')->setValue($file_m);
    $this->node->save();

    $context['message'] = t('Attached unmasked image to specimen.');
  }

}
