<?php

namespace Drupal\herbarium_specimen;

use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;

/**
 * HerbariumImageSurrogateFactory caption set object.
 */
class HerbariumImageSurrogateFactory {

  /**
   * The path to the file.
   *
   * @var string
   */
  protected $file;

  /**
   * The parent node of the file object.
   *
   * @var object
   */
  protected $node;

  /**
   * The parent node of the file object.
   *
   * @var object
   */
  protected $nid;

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
   * @param object $nid
   *   The node id of the parent herbarium specimen.
   * @param string $file_path
   *   The file ID of the archival TIFF File object.
   * @param bool $load_entities
   *   True if the $node entity should  be loaded on construction.
   */
  protected function __construct($nid, $file_path = NULL, $load_entities = TRUE) {
    if ($file_path) {
      $this->file = $file_path;
      $this->filePathParts = pathinfo($file_path);
    }

    $this->nid = $nid;
    if ($load_entities) {
      if ($nid) {
        $this->node = Node::load($nid);
      }
    }

    $public_dzi_path = \Drupal::service('file_system')->realpath(file_default_scheme() . "://") . '/dzi';
    $this->nodeDziPath = "$public_dzi_path/$nid";
  }

  /**
   * Build the DZI and tile files for the archival image.
   *
   * @param object $nid
   *   The node id of the parent herbarium specimen.
   * @param string $file_path
   *   The file ID of the archival TIFF File object.
   * @param array $context
   *   The Batch API context array.
   */
  public static function buildDziTiles($nid, $file_path, &$context) {
    $obj = new static($nid, $file_path, FALSE);
    $obj->generateDziTiles($context);
  }

  /**
   * Create the master JPG surrogate from the archival image.
   *
   * @param object $nid
   *   The node id of the parent herbarium specimen.
   * @param string $file_path
   *   The file ID of the archival TIFF File object.
   * @param array $context
   *   The Batch API context array.
   */
  public static function buildJpgSurrogate($nid, $file_path, &$context) {
    $obj = new static($nid, $file_path);
    $obj->generateJpgSurrogate($context);
  }

  /**
   * Create the maked JPG surrogate from the archival image.
   *
   * @param object $nid
   *   The node id of the parent herbarium specimen.
   * @param string $file_path
   *   The file ID of the archival TIFF File object.
   * @param array $context
   *   The Batch API context array.
   */
  public static function buildMaskedJpgSurrogate($nid, $file_path, &$context) {
    $obj = new static($nid, $file_path);
    $obj->generateMaskedJpgSurrogate($context);
  }

  /**
   * Delete any existing assets from the DZI/Tile directory.
   *
   * @param object $nid
   *   The node id of the parent herbarium specimen.
   * @param array $context
   *   The Batch API context array.
   */
  public static function deleteExistingAssets($nid, &$context) {
    $obj = new static($nid, NULL, TRUE);
    $obj->deleteGeneratedAssets($context);
  }

  /**
   * Generate the tiles and DZI for this file.
   *
   * @param array $context
   *   The Batch API context array.
   */
  protected function generateDziTiles(&$context) {
    // First, generate the masked image.
    $nid = $this->nid;
    list($width, $height, $type, $attr) = getimagesize($this->file);
    $mask_start_x = $width * (1 - $this->maskedWidthFactor);
    $mask_start_y = $height * (1 - $this->maskedHeightFactor);
    $temp_image_file = tempnam(sys_get_temp_dir(), "$nid-masked-") . '.tif';
    exec(
      "convert \"{$this->file}\" -strokewidth 0 -fill \"rgba(255,255,255,1)\" -draw \"rectangle $mask_start_x,$mask_start_y $width,$height\" -unsharp 8x6+1+0 $temp_image_file",
      $output,
      $return
    );

    // Generate DZI tiles.
    exec(
      "/usr/local/bin/magick-slicer -e jpg -i \"$temp_image_file\" -o \"{$this->nodeDziPath}\"",
      $output,
      $return
    );

    $context['message'] = t(
      '[NID#@nid] Generated DZI and tiled images for specimen image',
      [
        '@nid' => $this->nid,
      ]
    );
  }

  /**
   * Generate the JPG surrogate to be used for this file.
   *
   * @param array $context
   *   The Batch API context array.
   */
  protected function generateJpgSurrogate(&$context) {
    $nid = $this->node->id();
    $temp_image_file = tempnam(sys_get_temp_dir(), "$nid-unmasked-") . '.jpg';

    exec(
      "convert \"{$this->file}\" -unsharp 0x1.0+0.5+0 $temp_image_file ",
      $output,
      $return
    );

    // Create unmasked file object.
    $uniqid = uniqid(rand(), TRUE);
    $target_path_u = 'private://specimen_images';
    file_prepare_directory($target_path_u, FILE_CREATE_DIRECTORY);
    $file_destination_u = "$target_path_u/$nid-$uniqid.jpg";
    $uri_u = file_unmanaged_copy($temp_image_file, $file_destination_u, FILE_EXISTS_REPLACE);
    $file_u = File::Create([
      'uri' => $uri_u,
    ]);
    $file_u->setPermanent();
    $file_u->save();

    // Assign file to node.
    $this->node->get('field_large_sample_surrogate')->setValue($file_u);
    $this->node->save();

    // Unlink temporary file.
    unlink($temp_image_file);

    $context['message'] = t(
      '[NID#@nid] Generated and Attached Unmasked JPG specimen surrogate image.',
      [
        '@nid' => $this->nid,
      ]
    );
  }

  /**
   * Generate the masked JPG surrogate to be used for this file.
   *
   * @param array $context
   *   The Batch API context array.
   */
  protected function generateMaskedJpgSurrogate(&$context) {
    $nid = $this->node->id();
    list($width, $height, $type, $attr) = getimagesize($this->file);
    $mask_start_x = $width * (1 - $this->maskedWidthFactor);
    $mask_start_y = $height * (1 - $this->maskedHeightFactor);

    $temp_image_file = tempnam(sys_get_temp_dir(), "$nid-masked-") . '.jpg';

    exec(
      "convert \"{$this->file}\" -strokewidth 0 -fill \"rgba(255,255,255,1)\" -draw \"rectangle $mask_start_x,$mask_start_y $width,$height\" $temp_image_file",
      $output,
      $return
    );

    // Create unmasked file object.
    $uniqid = uniqid(rand(), TRUE);
    // Create masked file object.
    $target_path_m = 'public://specimen_images';
    file_prepare_directory($target_path_m, FILE_CREATE_DIRECTORY);
    $file_destination_m = "$target_path_m/{$nid}-{$uniqid}_masked.jpg";
    $uri_m = file_unmanaged_copy($temp_image_file, $file_destination_m, FILE_EXISTS_REPLACE);
    $file_m = File::Create([
      'uri' => $uri_m,
    ]);
    $file_m->setPermanent();
    $file_m->save();

    // Attach new existing JPG surrogates.
    $this->node->get('field_large_sample_surrogate_msk')->setValue($file_m);
    $this->node->save();

    // Unlink temporary file.
    unlink($temp_image_file);

    $context['message'] = t(
      '[NID#@nid] Generated Masked JPG specimen surrogate image for archival master',
      [
        '@nid' => $this->nid,
      ]
    );
  }

  /**
   * Delete any previous generated DZI assets for this node.
   *
   * @param array $context
   *   The Batch API context array.
   */
  protected function deleteGeneratedAssets(&$context) {
    // Delete DZI assets, surrogates will be deleted by attachNodeSurrogates().
    exec(
      "rm -rf  {$this->nodeDziPath}",
      $output,
      $return
    );

    // Remove images attached to node.
    $surrogate_fields = [
      'field_large_sample_surrogate',
      'field_large_sample_surrogate_msk',
    ];

    // Clean up the entities currently referenced.
    foreach ($surrogate_fields as $surrogate_field) {
      if (!empty($this->node->get($surrogate_field)->entity)) {
        // Set field value as empty.
        $this->node->get($surrogate_field)->value = [];

        // Delete current entities referenced.
        if (!empty($this->node->get($surrogate_field)->entity) && trim($this->node->get($surrogate_field)->entity->getFileUri()) != '') {
          // File entity still exists. Remove it the normal way.
          $fid = $this->node->get($surrogate_field)->entity->id();
          $fids_to_remove[] = $fid;
          $file_obj = File::Load($fid);
          $file_obj->delete();
        }
        else {
          // Looks broken. Yank it out manually.
          _herbarium_specimen_manually_remove_broken_file_reference($this->node->id(), $surrogate_field);
        }
      }
    }
    $this->node->save();

    // Set message.
    $context['message'] = t(
      '[NID#@nid] Deleted previously generated assets for specimen',
      [
        '@nid' => $this->nid,
      ]
    );
  }

}
