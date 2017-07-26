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
   * @param int $nid
   *   The node id of the parent herbarium specimen.
   * @param int $file_path
   *   The file path of the archival TIFF File object.
   * @param int $uid
   *   The user requesting the update.
   */
  protected function __construct($nid, $file_path = NULL, $uid = 0) {
    if ($nid) {
      $this->node = Node::load($nid);
    }

    if ($file_path) {
      $this->file = $file_path;
      $this->filePathParts = pathinfo($this->file);
    }

    if ($uid) {
      $this->user = User::load($uid);
    }
  }

  /**
   * Archive the TIF file to LTS.
   *
   * @param int $nid
   *   The node id of the parent herbarium specimen.
   * @param string $file_path
   *   The file path of the archival TIFF File object.
   * @param int $uid
   *   The user requesting the update.
   * @param array $context
   *   The Batch API context array.
   */
  public static function archiveFileToLts($nid, $file_path, $uid, &$context) {
    $obj = new static($nid, $file_path, $uid);
    $obj->archiveTiff($context);
  }

  /**
   * Push the new TIF file up to the LTS archive.
   *
   * @param array $context
   *   The Batch API context array.
   */
  protected function archiveTiff(&$context) {
    $email = $this->user->get('mail')->value;
    $name = $this->user->get('name')->value;
    $target_nid = $this->node->id();

    // Clone local repo to temp folder, avoiding problems with concurrent use.
    $temp_clone_directory = tempnam(sys_get_temp_dir(), 'LTSGitRepo');
    if (file_exists($temp_clone_directory)) {
      unlink($temp_clone_directory);
    }
    mkdir($temp_clone_directory);

    exec(
      "git clone {$this->ltsRepoPath} {$temp_clone_directory} && cp {$this->ltsRepoPath}/.lfsconfig {$temp_clone_directory}/.lfsconfig",
      $output,
      $return
    );

    // Copy file to LTS folder.
    exec(
      "cp {$this->file} {$temp_clone_directory}/{$target_nid}.tif",
      $output,
      $return
    );

    // Stage the file for commit.
    exec(
      "cd {$temp_clone_directory} && git lfs track \"*.tif\" && git add {$target_nid}.tif",
      $output,
      $return
    );

    // Commit.
    exec(
      "cd {$temp_clone_directory} && git config --global user.email \"$email\" && git config --global user.name \"$name\" && git commit -m 'Update archival file for NID#{$target_nid}'",
      $output,
      $return
    );

    // Push back to origin. Check for errors indicating concurrent use / retry.
    $return = 1;
    while ($return != 0) {
      exec(
        "cd {$temp_clone_directory} && git pull --rebase origin master && git push origin master",
        $output,
        $return
      );
      sleep(3);
    }

    // Update the node to ensure that we don't double batch import.
    if ($this->node->get('field_herbarium_spec_master_impo')->value == FALSE) {
      $this->node->get('field_herbarium_spec_master_impo')->setValue(TRUE);
      $this->node->save();
    }

    // Remove the temporary dir.
    unlink($temp_clone_directory);

    $context['message'] = t(
      '[NID#@nid] Updated long term storage file for specimen.',
      [
        '@nid' => $target_nid,
      ]
    );
  }

  /**
   * Check the storage status of the LTS archiver.
   */
  public static function checkStorageStatus() {
    $obj = new static(FALSE);

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
    $lts_server_ip = $_SERVER['LTS_LFS_SERVER_HOST'];
    $lts_server_port = $_SERVER['LTS_LFS_SERVER_PORT'];

    $fp = @fsockopen("tcp://$lts_server_ip:$lts_server_port");
    if (!$fp) {
      return [FALSE, t('ERROR: A connection cannot be made to the long-term storage server. Please contact an administrator.')];
    }

    return [TRUE, NULL];
  }

}
