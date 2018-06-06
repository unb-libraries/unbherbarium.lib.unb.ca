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
    $this->removeDziTiles();

    // Generate DZI tiles.
    exec(
      "/usr/local/bin/magick-slicer -e jpg -i \"{$this->file}\" -o \"{$this->nodeDziPath}\"",
      $output,
      $return
    );

    $context['message'] = t(
      '[NID#@nid] Removing temporary files created for specimen image',
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
    $this->node->setNewRevision(FALSE);
    $this->node->save();

    // Unlink temporary file.
    unlink($temp_image_file);

    $context['message'] = t(
      '[NID#@nid] Generating JPG specimen surrogate image.',
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
    $this->removeDziTiles();

    // Remove images attached to node.
    $surrogate_fields = [
      'field_large_sample_surrogate',
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
    $this->node->setNewRevision(FALSE);
    $this->node->save();

    // Set message.
    $context['message'] = t(
      '[NID#@nid] Deleted generated Derivative assets',
      [
        '@nid' => $this->nid,
      ]
    );
  }

  /**
   * Remove the tiles and DZI for this file.
   */
  protected function removeDziTiles() {
    $public_dzi_path = \Drupal::service('file_system')->realpath(file_default_scheme() . "://") . '/dzi';

    // Delete DZI assets, surrogates will be deleted by attachNodeSurrogates().
    $nid = $this->node->id();
    exec(
      "rm -rf {$public_dzi_path}/{$nid}_files {$public_dzi_path}/{$nid}.dzi",
      $output,
      $return
    );
  }

}
