<?php
// phpcs:ignoreFile -- this contains batches - array contexts.

namespace Drupal\herbarium_specimen_lts;

use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * HerbariumImageSurrogateFactory caption set object.
 */
class HerbariumImageLtsArchiver {

  const PUSH_FAILURE_RETRIES = 30;
  const GIT_SSH_COMMAND_SETUP = 'GIT_SSH_COMMAND="ssh -o UserKnownHostsFile=/dev/NULL -o StrictHostKeyChecking=no -i ~/.ssh/id_rsa"';

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
  protected function __construct($nid = NULL, $file_path = NULL, $uid = 0) {
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
   * @param string $commit_msg
   *   The commit message to use when archiving the file.
   * @param array $context
   *   The Batch API context array.
   */
  public static function archiveFileToLts($nid, $file_path, $uid, $commit_msg, &$context) {
    $obj = new static($nid, $file_path, $uid);
    $obj->archiveTiff($commit_msg, $context);
  }

  /**
   * Remove the TIF file to LTS.
   *
   * @param int $nid
   *   The node id of the parent herbarium specimen.
   * @param int $uid
   *   The user requesting the update.
   * @param array $context
   *   The Batch API context array.
   */
  public static function removeFileFromLts($nid, $uid, &$context) {
    $obj = new static($nid, NULL, $uid);
    $obj->deleteTiff($context);
  }

  /**
   * Push the new TIF file up to the LTS archive.
   *
   * @param string $commit_msg
   *   The commit message to use when archiving the file.
   * @param array $context
   *   The Batch API context array.
   */
  protected function archiveTiff($commit_msg, &$context) {
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
      "cp \"{$this->file}\" {$temp_clone_directory}/{$target_nid}.tif",
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
      "cd {$temp_clone_directory} && git config --global user.email \"libsystems@unb.ca\" && git config --global user.name \"Mr. Robot.\" && git commit --author \"$name <$email>\" -m '$commit_msg'",
      $output,
      $return
    );

    // Push back to origin. Check for errors indicating concurrent use / retry.
    $return = 1;
    $push_failures = 0;
    $git_ssh_command_setup = self::GIT_SSH_COMMAND_SETUP;

    while ($return != 0) {
      exec(
        "cd {$temp_clone_directory} && {$git_ssh_command_setup} git pull --rebase origin master && {$git_ssh_command_setup} git push origin master",
        $output,
        $return
      );
      if ($return != 0) {

        // Some files were getting caught in an 'Unstaged Changes' issue.
        $push_failures++;
        if ($push_failures >= self::PUSH_FAILURE_RETRIES) {
          $context['message'] = t(
            '[NID#@nid] Could not push commits upstream. Skipping.',
            [
              '@nid' => $target_nid,
            ]
          );
          return;
        }

        $sleep_seconds = 3;
        $output_string = implode("\n", $output);
        echo("Busy repo : pausing for $sleep_seconds before trying push again. ($output_string)\n");
        sleep($sleep_seconds);
      }
    }

    // Update the node to ensure that we don't double batch import.
    if ($this->node->get('field_herbarium_spec_master_impo')->value == FALSE) {
      $this->node->get('field_herbarium_spec_master_impo')->setValue(TRUE);
      $this->node->setNewRevision(FALSE);
      $this->node->save();
    }

    // Remove the temporary dir.
    $this->delTree($temp_clone_directory);

    $context['message'] = t(
      '[NID#@nid] Updated long term storage file for specimen.',
      [
        '@nid' => $target_nid,
      ]
    );
  }

  /**
   * Remove the TIF from the LTS archive.
   *
   * @param array $context
   *   The Batch API context array.
   */
  protected function deleteTiff(&$context) {
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

    // Stage the file for commit.
    exec(
      "cd {$temp_clone_directory} && git lfs track \"*.tif\" && git rm {$target_nid}.tif",
      $output,
      $return
    );

    // Commit.
    exec(
      "cd {$temp_clone_directory} && git config --global user.email \"libsystems@unb.ca\" && git config --global user.name \"Mr. Robot.\" && git commit --author \"$name <$email>\" -m '[$target_nid] Deletion of archival file.'",
      $output,
      $return
    );

    $return = 1;
    $push_failures = 0;
    $git_ssh_command_setup = self::GIT_SSH_COMMAND_SETUP;

    while ($return != 0) {
      exec(
        "cd {$temp_clone_directory} && {$git_ssh_command_setup} git pull --rebase origin master && {$git_ssh_command_setup} git push origin master",
        $output,
        $return
      );
      if ($return != 0) {

        // Some files were getting caught in an 'Unstaged Changes' issue.
        $push_failures++;
        if ($push_failures >= self::PUSH_FAILURE_RETRIES) {
          $context['message'] = t(
            '[NID#@nid] Could not push commits upstream. Skipping.',
            [
              '@nid' => $target_nid,
            ]
          );
          return;
        }

        $sleep_seconds = 3;
        $output_string = implode("\n", $output);
        echo("Busy repo : pausing for $sleep_seconds before trying push again. ($output_string)\n");
        sleep($sleep_seconds);
      }
    }

    // Update the node to ensure that we don't double batch import.
    if ($this->node->get('field_herbarium_spec_master_impo')->value == FALSE) {
      $this->node->get('field_herbarium_spec_master_impo')->setValue(TRUE);
      $this->node->setNewRevision(FALSE);
      $this->node->save();
    }

    // Remove the temporary dir.
    $this->delTree($temp_clone_directory);

    $context['message'] = t(
      '[NID#@nid] Deleted long term storage file for specimen.',
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
    if (!file_exists($obj->ltsRepoPath . '/HEAD')) {
      return [
        FALSE,
        t('ERROR: The long-term storage repository path does not exist. Please contact an administrator.')
      ];
    }

    // Can we contact the LFS server?
    $lts_server_ip = $_SERVER['LTS_LFS_SERVER_HOST'];
    $lts_server_port = $_SERVER['LTS_LFS_SERVER_PORT'];

    $fp = @fsockopen("tcp://$lts_server_ip:$lts_server_port");
    if (!$fp) {
      return [
        FALSE,
        t('ERROR: A connection cannot be made to the long-term storage server. Please contact an administrator.')
      ];
    }

    return [TRUE, NULL];
  }

  /**
   * Delete a filesystem tree recursively.
   *
   * @see http://php.net/manual/fr/function.rmdir.php#98622
   */
  private function delTree($dir) {
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
      (is_dir("$dir/$file") && !is_link($dir)) ? $this->delTree("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
  }

  /**
   * Archive the TIF file to LTS.
   *
   * @param int $nid
   *   The node id of the parent herbarium specimen.
   * @param string $file_path
   *   The file path of the archival TIFF File object.
   * @param array $context
   *   The Batch API context array.
   */
  public static function auditDrushBatch($nid, $file_path, &$context) {
    $obj = new static($nid, $file_path);
    $obj->auditFile($context);
  }

  /**
   * Push the new TIF file up to the LTS archive.
   *
   * @param array $context
   *   The Batch API context array.
   */
  protected function auditFile(&$context) {
    if (empty($this->getNodeHistory())) {
      _herbarium_specimen_lts_set_file_status($this->file, HERBARIUM_SPECIMEN_LTS_QUEUE_STATUS_FAIL_NOT_ARCHIVED);

      $context['message'] = t(
        '[NID#@nid] Import Audit Failed!.',
        [
          '@nid' => $this->node->id(),
        ]
      );

      return;
    }

    _herbarium_specimen_lts_set_file_status($this->file, HERBARIUM_SPECIMEN_LTS_QUEUE_STATUS_COMPLETE);

    $context['message'] = t(
      '[NID#@nid] Import Audit Pass.',
      [
        '@nid' => $this->node->id(),
      ]
    );

  }

  /**
   * Archive the TIF file to LTS.
   *
   * @param int $nid
   *   The node id of the parent herbarium specimen.
   */
  public static function getFileHistory($nid) {
    $obj = new static($nid);
    return $obj->getNodeHistory();
  }

  /**
   * Determine if a NID has a specimen master in the archive.
   *
   * @param int $nid
   *   The node id of the parent herbarium specimen.
   */
  public static function specimenHasMaster($nid) {
    $obj = new static($nid);
    return $obj->getIfSpecimenHasMaster();
  }

  /**
   * Get if the specimen has a master image in HEAD.
   */
  protected function getIfSpecimenHasMaster() {
    $history = [];

    // Get the LTS history.
    exec(
      "cd {$this->ltsRepoPath} && git cat-file -e HEAD:{$this->node->id()}.tif",
      $output,
      $return
    );

    if ($return == 0) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Get the history of an archival file in the LTS repo.
   */
  protected function getNodeHistory() {
    $history = [];

    // Get the LTS history.
    exec(
      "cd {$this->ltsRepoPath} && git log --pretty=format:\"%ai||%aE||%s||%h\" | grep '\[{$this->node->id()}\]'",
      $output,
      $return
    );

    if ($return == 0 && !empty($output)) {
      foreach (array_reverse($output) as $line) {
        $history[] = explode('||', $line);
      }
    }

    return $history;
  }

  /**
   * Push the repository to the remote LFS server.
   *
   * @param array $context
   *   The Batch API context array.
   */
  public static function pushLfs(&$context) {
    $obj = new static();
    return $obj->pushLfsRepo($context);
  }

  /**
   * Push the repository to the remote LFS server.
   *
   * @param array $context
   *   The Batch API context array.
   */
  protected function pushLfsRepo(&$context) {
    // Push back to origin. Check for errors indicating concurrent use / retry.
    $return = 1;
    $push_failures = 0;
    $push_failure_retries = 10;
    $git_ssh_command_setup = self::GIT_SSH_COMMAND_SETUP;

    while ($return != 0) {
      exec(
        "cd {$this->ltsRepoPath} && {$git_ssh_command_setup} git push origin master",
        $output,
        $return
      );
      if ($return != 0) {

        // Some files were getting caught in an 'Unstaged Changes' issue.
        $push_failures++;
        if ($push_failures >= $push_failure_retries) {
          $context['message'] = t(
            'Remote LFS push failed!'
          );
          return;
        }

        $sleep_seconds = 15;
        $output_string = implode("\n", $output);
        echo("Busy repo : pausing for $sleep_seconds before trying push again. ($output_string)\n");
        sleep($sleep_seconds);
      }
    }

    $context['message'] = t(
      '[NID#@nid] Pushed successfully to remote LFS.'
    );
  }

  /**
   * Pull a file from LTS to repo.
   *
   * @param int $nid
   *   The node id of the parent herbarium specimen.
   * @param array $context
   *   The Batch API context array.
   */
  public static function pullMasterFromLts($nid, &$context) {
    $obj = new static($nid);
    return $obj->smudgeFromLts($context);
  }

  /**
   * Push the repository to the remote LFS server.
   *
   * @param array $context
   *   The Batch API context array.
   */
  protected function smudgeFromLts(&$context) {
    $nid = $this->node->id();
    $git_ssh_command_setup = self::GIT_SSH_COMMAND_SETUP;

    // Smudge out file.
    exec(
      "cd '/lts-archive' && {$git_ssh_command_setup} git lfs pull --include \"$nid.tif\"",
      $output,
      $return
    );

    if ($return == 0) {
      $context['message'] = t(
        '[NID#@nid] Pulled from remote LFS.',
        [
          '@nid' => $this->node->id(),
        ]
      );
    }
    else {
      $context['message'] = t(
        '[NID#@nid] Remote LFS pull failed!',
        [
          '@nid' => $this->node->id(),
        ]
      );
    }
  }

  /**
   * Remove a smudged file from local LTS.
   *
   * @param int $nid
   *   The node id of the parent herbarium specimen.
   * @param array $context
   *   The Batch API context array.
   */
  public static function removeMasterFromLocalLts($nid, &$context) {
    $obj = new static($nid);
    return $obj->removeMasterFromLts($context);
  }

  /**
   * Remove a locally smudged file.
   *
   * @param array $context
   *   The Batch API context array.
   */
  protected function removeMasterFromLts(&$context) {
    $nid = $this->node->id();

    exec(
      "rm '/lts-archive/$nid.tif'",
      $output,
      $return
    );

    if ($return == 0) {
      $context['message'] = t(
        '[NID#@nid] Removed local copy of LTS master.',
        [
          '@nid' => $this->node->id(),
        ]
      );
    }
    else {
      $context['message'] = t(
        '[NID#@nid] Removal of local copy of LTS master failed!',
        [
          '@nid' => $this->node->id(),
        ]
      );
    }
  }

}
