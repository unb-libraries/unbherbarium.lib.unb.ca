<?php

namespace Drupal\herbarium_specimen_lts;

use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use TQ\Git\Repository\Repository;

/**
 * HerbariumImageSurrogateFactory caption set object.
 */
class HerbariumImageLtsArchiver {

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
   * The user requesting the update.
   *
   * @var object
   */
  protected $user;

  /**
   * An associative array : file path information as returned by pathinfo().
   *
   * @var array
   */
  protected $filePathParts;

  /**
   * An associative array : file path information as returned by pathinfo().
   *
   * @var array
   */
  protected $ltsRepoPath = '/lts-archive';

  /**
   * Constructor.
   *
   * @param int $fid
   *   The file ID of the archival TIFF File object.
   * @param int $nid
   *   The node id of the parent herbarium specimen.
   * @param int $uid
   *   The user requesting the update.
   */
  protected function __construct($fid = 0, $nid = 0, $uid = 0) {
    if ($fid) {
      $this->file = File::load($fid);
      $this->node = Node::load($nid);

      if ($uid) {
        $this->user = User::load($uid);
      }

      $file_path = drupal_realpath($this->file->getFileUri());
      $this->filePathParts = pathinfo($file_path);
    }
  }

  /**
   * Archive the TIF file to LTS.
   *
   * @param int $fid
   *   The file ID of the archival TIFF File object.
   * @param int $nid
   *   The node id of the parent herbarium specimen.
   * @param int $uid
   *   The user requesting the update.
   * @param array $context
   *   The Batch API context array.
   */
  public static function archiveFileToLts($fid, $nid, $uid, array &$context) {
    $obj = new static($fid, $nid, $uid);
    $obj->archiveTiff($context);
  }

  /**
   * Push the new TIF file up to the LTS archive.
   *
   * @param array $context
   *   The Batch API context array.
   */
  protected function archiveTiff(array &$context) {
    $email = $this->user->get('mail')->value;
    $name = $this->user->get('name')->value;

    // Copy file to LTS folder.
    exec(
      "cd {$this->filePathParts['dirname']} && cp {$this->filePathParts['basename']} {$this->ltsRepoPath}/{$this->node->id()}.tif",
      $output,
      $return
    );

    // Stage the file for commit.
    exec(
      "cd {$this->ltsRepoPath} && git lfs track \"*.tif\" && git add {$this->node->id()}.tif",
      $output,
      $return
    );

    // Commit and push.
    exec(
      "cd {$this->ltsRepoPath} && git config --global user.email \"$email\" && git config --global user.name \"$name\" && git commit -m 'Update archival file for NID#{$this->node->id()}' && GIT_SSH_COMMAND=\"ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -i /var/lib/nginx/.ssh/id_rsa\" git push origin master",
      $output,
      $return
    );

    $context['message'] = t('Updated long term storage file for specimen.');
  }

  /**
   * Check the storage status of the LTS archiver.
   */
  public static function checkStorageStatus() {
    $obj = new static();

    // Check if the LTS archive path exists.
    if (!file_exists($obj->ltsRepoPath . '/.git')) {
      return [FALSE, t('ERROR: The long-term storage repository path does not exist. Please contact an administrator.')];
    }

    $git = Repository::open($obj->ltsRepoPath, '/usr/bin/git');
    // Check if the archive is dirty. This means something went wrong before.
    if ($git->isDirty()) {
      return [FALSE, t('ERROR: The long-term storage repository appears desynced. Please contact an administrator.')];
    }

    // Can we contact the LFS server?
    $fp = @fsockopen("tcp://hilstorage.hil.unb.ca:6983");
    if (!$fp) {
      return [FALSE, t('ERROR: A connection cannot be made to the long-term storage server. Please contact an administrator.')];
    }

    return [TRUE, NULL];
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

}
