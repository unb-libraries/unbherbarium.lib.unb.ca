<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\PersistentLocalDockworkerDataTrait;
use Dockworker\RecursivePathFileOperatorTrait;
use Dockworker\Robo\Plugin\Commands\DrupalDeploymentDrushCommands;

/**
 * Defines the commands used to interact with a deployed Drupal application.
 */
class HerbariumBulkImportImagesCommand extends DrupalDeploymentDrushCommands {

  use PersistentLocalDockworkerDataTrait;
  use RecursivePathFileOperatorTrait;

  /**
   * The PHP snippet used to log-in a user in a Drush eval command.
   */
  const DRUSH_EVAL_LOGIN_USER = 'use \Drupal\user\Entity\User; \Drupal::currentUser()->setAccount(User::load(%s))';

  /**
   * The PHP snippet used to trigger a Drush batch process.
   */
  const DRUSH_EVAL_PROCESS_BATCH = 'drush_backend_batch_process();';

  /**
   * The CMH accession ID of the current file being imported.
   *
   * @var string
   */
  protected $curFileAccessionId;

  /**
   * The file name of the current file being imported.
   *
   * @var string
   */
  protected $curFileName;

  /**
   * The corresponding remote node ID of the current file being imported.
   *
   * @var string
   */
  protected $curFileNodeId;

  /**
   * The full file path to the current file being imported.
   *
   * @var string
   */
  protected $curFilePath;

  /**
   * The base of the tree to parse for files to import.
   *
   * @var string
   */
  protected $sourceTreePath;

  /**
   * The commit message to use when archiving the file.
   *
   * @var string
   */
  protected $targetCommitMessage;

  /**
   * The k8s deployment environment to use when importing the file.
   *
   * @var string
   */
  protected $targetDeployEnv;

  /**
   * The Drupal user ID to use when importing the archival master.
   *
   * @var string
   */
  protected $targetDrupalUid;

  /**
   * The k8s pod ID to target when importing the file.
   *
   * @var string
   */
  protected $targetPodId;

  /**
   * Adds a tree of images as the archival masters in the herbarium site.
   *
   * @param string $path
   *   The path to parse for the files.
   * @param string $env
   *   The environment to add the archival masters to.
   * @param array $options
   *   The Drupal user ID to use when importing the archival master.
   *
   * @option string $commit-message
   *   The commit message to use when archiving the file.
   * @option string $issue-page-extension
   *   The file extension to use when parsing the tree for files to import.
   * @option string $uid
   *   The file extension to use when parsing the tree for files to import.
   *
   * @command herbarium:add-master:from-tree
   * @usage herbarium:add-master:from-tree /tmp/New-Herbarium-Oct-2021 prod
   *
   * @throws \Dockworker\DockworkerException
   *
   * @kubectl
   */
  public function addHerbariumArchivalMastersFromTree(
    string $path,
    string $env = 'prod',
    array $options = [
      'commit-message' => 'Automated import of archival master',
      'issue-page-extension' => 'tif',
      'uid' => '1274',
    ]
  ) {
    $this->sourceTreePath = $path;
    $this->targetDrupalUid = $options['uid'];
    $this->targetCommitMessage = $options['commit-message'];
    $this->targetDeployEnv = $env;
    $this->options = $options;

    $this->setUpArchivalMasterQueue();
    $this->importQueuedArchivalMasters();
  }

  /**
   * Queues any files in the tree that are to be imported as archival masters.
   */
  protected function setUpArchivalMasterQueue() {
    $this->addRecursivePathFilesFromPath(
      [$this->sourceTreePath],
      [$this->options['issue-page-extension']]
    );
  }

  /**
   * Imports the previously-queued archival masters into the remote instance.
   *
   * @throws \Dockworker\DockworkerException
   */
  protected function importQueuedArchivalMasters() {
    $import_files = $this->getRecursivePathFiles();
    if (!empty($import_files)) {
      $this->initLocalDockworkerConfig('bulk_imports');
      $this->targetPodId = $this->k8sGetLatestPod($this->targetDeployEnv, 'deployment', 'Open Shell');
      $imported_items = $this->curLocalDockworkerConfiguration->get('dockworker.imported_items');
      if (empty($imported_items)) {
        $imported_items = [];
      }
      foreach ($import_files as $import_file) {
        $this->curFilePath = $import_file;
        if (!empty($this->curFilePath) && file_exists($this->curFilePath)) {
          $this->curFileName = basename($this->curFilePath);
          if (!in_array($this->curFileName, $imported_items)) {
            $this->curFileAccessionId = $this->getAccessionIdFromFilepath($this->curFilePath);
            $this->curFileNodeId = $this->getNidFromAccessionId();
            if (!empty($this->curFileNodeId)) {
              $this->io()->title($this->curFileName);
              $this->importArchivalMaster();
              $imported_items[] = $this->curFileName;
              $this->curLocalDockworkerConfiguration->set('dockworker.imported_items', $imported_items);
              $this->witeLocalDockworkerConfig();
            }
            else {
              $this->say("[$this->curFileName] No NIDs found for accession ID [$this->curFileAccessionId], skipping...");
            }
          }
          else {
            $this->say("[$this->curFileName] File is marked as previously imported, skipping...");
          }
        }
      }
    }
  }

  /**
   * Imports the current archival master file into the remote instance.
   *
   * @throws \Dockworker\DockworkerException
   */
  protected function importArchivalMaster() {
    $this->copyArchivalMasterToPod();
    $this->executeArchivalMasterDeployedImport();
  }

  /**
   * Determines the probable CMH accession ID from an import file path.
   *
   * @param string $file_path
   *   The file path to use when determining the CMH accession ID.
   *
   * @return string
   *   The probable CMH accession ID.
   */
  private function getAccessionIdFromFilepath(string $file_path) : string {
    return basename($file_path, '.' . $this->options['issue-page-extension']);
  }

  /**
   * Determines the remote Drupal NID to target when importing the master.
   *
   * @return string
   *   The NID to target.
   *
   * @throws \Dockworker\DockworkerException
   */
  private function getNidFromAccessionId() : string {
    $cmd = sprintf(
      '$DRUSH eval "echo _unb_herbarium_get_nid_from_accession_id(\"%s\")"',
      $this->curFileAccessionId
    );
    $response = $this->kubernetesPodExecCommand(
      $this->targetPodId,
      $this->targetDeployEnv,
      $cmd
    );
    if (!empty($response[0])) {
      return $response[0];
    }
    return '';
  }

  /**
   * Copies the local archival master to the remote target pod.
   *
   * @throws \Dockworker\DockworkerException
   */
  protected function copyArchivalMasterToPod() {
    $target_name = "$this->targetPodId:/tmp/$this->curFileName";
    $this->say("Copying archival master $this->curFilePath -> $target_name...");
    $this->kubernetesPodFileCopyCommand(
      $this->targetDeployEnv,
      $this->curFilePath,
      $target_name
    );
  }

  /**
   * Executes the archival master import process on the remote instance.
   *
   * @throws \Dockworker\DockworkerException
   */
  protected function executeArchivalMasterDeployedImport() {
    $this->say("Importing archival master [NID#$this->curFileNodeId]...");
    $cmd = sprintf('$DRUSH eval "' .
      self::DRUSH_EVAL_LOGIN_USER . ';' .
     'batch_set(_herbarium_specimen_lts_add_archival_master(\"%s\", \"%s\", \"%s\"));' .
      self::DRUSH_EVAL_PROCESS_BATCH . ';"',
      $this->targetDrupalUid,
      $this->curFileNodeId,
      "/tmp/$this->curFileName",
      $this->options['commit-message']
    );
    $this->kubernetesPodExecCommand(
      $this->targetPodId,
      $this->targetDeployEnv,
      $cmd
    );
  }

}
