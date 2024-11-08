<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Docker\DockerContainer;
use Dockworker\Docker\DockerContainerExecTrait;
use Dockworker\DockworkerDrupalCommands;
use Dockworker\FileSystem\RecursivePathFileOperatorTrait;
use Dockworker\IO\DockworkerIOTrait;
use Dockworker\Storage\ApplicationPersistentDataStorageTrait;

/**
 * Defines the commands used to bulk import archival masters into the CMH.
 */
class HerbariumBulkImportImagesCommands extends DockworkerDrupalCommands
{
    use ApplicationPersistentDataStorageTrait;
    use DockworkerIOTrait;
    use DockerContainerExecTrait;
    use RecursivePathFileOperatorTrait;

    /**
     * The PHP snippet used to set the batch for adding an archival master.
     */
    protected const DRUSH_EVAL_BATCH_SET = 'batch_set(_herbarium_specimen_lts_add_archival_master("%s", "%s", "%s"));';

    /**
     * The PHP snippet used to log in a user via a Drush eval command.
     */
    protected const DRUSH_EVAL_LOGIN_USER = 'use \Drupal\user\Entity\User; \Drupal::currentUser()->setAccount(User::load(%s))';

    /**
     * The PHP snippet used to trigger a Drush batch process.
     */
    protected const DRUSH_EVAL_PROCESS_BATCH = 'drush_backend_batch_process()';

    /**
     * The CMH accession ID of the current file being imported.
     *
     * @var string
     */
    protected string $curFileAccessionId;

    /**
     * The file name of the current file being imported.
     *
     * @var string
     */
    protected string $curFileName;

    /**
     * The corresponding remote node ID of the current file being imported.
     *
     * @var string
     */
    protected string $curFileNodeId;

    /**
     * The full file path to the current file being queued for import.
     *
     * @var string
     */
    protected string $curFilePath;

    /**
     * The ID of the current import.
     *
     * @var string
     */
    protected string $curImportID;

    /**
     * The current container.
     *
     * @var \Dockworker\Docker\DockerContainer
     */
    protected DockerContainer $curContainer;

    /**
     * The file extension of the images to import.
     *
     * @var string
     */
    protected string $imageExtension;

    /**
     * The base of the tree to parse for files to import.
     *
     * @var string
     */
    protected string $sourceTreePath;

    /**
     * The commit message to use when archiving the file.
     *
     * @var string
     */
    protected string $targetCommitMessage;

    /**
     * The k8s deployment environment to use when importing the file.
     *
     * @var string
     */
    protected string $targetDeployEnv;

    /**
     * The Drupal user ID to use when importing the archival master.
     *
     * @var string
     */
    protected string $targetDrupalUid;

    /**
     * Adds a tree of images as the archival masters in the CMH site.
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
     * @option string $image-extension
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
            'image-extension' => 'tif',
            'uid' => '1274',
        ]
    ) {
        $this->initContainerExecCommand($this->dockworkerIO, $env);
        $this->sourceTreePath = $path;
        $this->targetDrupalUid = $options['uid'];
        $this->targetCommitMessage = $options['commit-message'];
        $this->imageExtension = $options['image-extension'];
        $this->targetDeployEnv = $env;

        $this->dockworkerIO->title('Herbarium Archival Master Import');
        $this->dockworkerIO->section('Import Options');

      // Query the user for an ID string for this import. If none is given, generate one randomly.
        $this->curImportID = $this->dockworkerIO->ask(
            'ID for the import? If continuing a previous import, enter the previous ID.',
            'herbarium-import-' . date('Y-m-d-H-i-s')
        );

        $this->setUpArchivalMasterQueue();
        $this->importQueuedArchivalMasters();
    }

    /**
     * Queues any files in the tree that are to be imported as archival masters.
     */
    protected function setUpArchivalMasterQueue()
    {
        $this->addRecursivePathFilesFromPath(
            [$this->sourceTreePath],
            [$this->imageExtension]
        );
    }

    /**
     * Imports previously-queued archival masters into the CMH instance.
     *
     * @throws \Dockworker\DockworkerException
     * @throws \Exception
     */
    protected function importQueuedArchivalMasters()
    {
        $import_files = $this->getRecursivePathFiles();
        if (!empty($import_files)) {
            $imported_items = $this->getCurrentImportedItems();
            $this->curContainer = $this->getDeployedContainer(
                $this->dockworkerIO,
                $this->targetDeployEnv,
                false,
                true
            );
            foreach ($import_files as $import_file) {
                $this->curFilePath = $import_file;
                if (!empty($this->curFilePath) && file_exists($this->curFilePath)) {
                    $this->curFileName = basename($this->curFilePath);
                    if (!in_array($this->curFileName, $imported_items)) {
                        $this->curFileAccessionId = $this->getAccessionIdFromFilepath(
                            $this->curFilePath,
                            $this->imageExtension
                        );
                        $this->curFileNodeId = $this->getNidFromAccessionId();
                        if (!empty($this->curFileNodeId)) {
                            $this->dockworkerIO->title($this->curFileName);
                            $this->importArchivalMaster();
                            $imported_items[] = $this->curFileName;
                            $this->updateCurrentImportedItems($imported_items);
                        } else {
                            $this->say("[$this->curFileName] No NIDs found for accession ID [$this->curFileAccessionId], skipping...");
                        }
                    } else {
                        $this->say("[$this->curFileName] File is marked as previously imported, skipping...");
                    }
                }
            }
        }
    }

    /**
     * Determines the probable CMH accession ID from an import file path.
     *
     * @param string $file_path
     *   The file path to use when determining the CMH accession ID.
     * @param string $extension
     *   The extension of the file.
     *
     * @return string
     *   The probable CMH accession ID.
     */
    private static function getAccessionIdFromFilepath(string $file_path, string $extension): string
    {
        return basename($file_path, '.' . $extension);
    }

    /**
     * Determines the remote Drupal NID to target when importing the master.
     *
     * @return string
     *   The NID to target.
     *
     * @throws \Dockworker\DockworkerException
     */
    private function getNidFromAccessionId(): string
    {
        $cmd = [
            '/usr/bin/drush',
            'eval',
            'echo _unb_herbarium_get_nid_from_accession_id("' . $this->curFileAccessionId . '")',
        ];
        $result = $this->curContainer->run(
            $cmd,
            $this->dockworkerIO,
            false
        );
        $response = explode("\n", $result->getOutput());
        if (!empty($response[0])) {
            return $response[0];
        }
        return '';
    }

    /**
     * Imports the current archival master file into the remote instance.
     *
     * @throws \Dockworker\DockworkerException
     */
    protected function importArchivalMaster()
    {
        $this->copyArchivalMasterToPod();
        $this->executeArchivalMasterDeployedImport();
    }

    /**
     * Copies the local archival master to the remote target pod.
     *
     * @throws \Dockworker\DockworkerException
     */
    protected function copyArchivalMasterToPod()
    {
        $target_location = "/tmp/$this->curFileName";
        $this->say("Copying archival master $this->curFilePath -> $target_location...");
        $this->curContainer->copyTo(
            $this->dockworkerIO,
            $this->curFilePath,
            $target_location
        );
    }

    /**
     * Executes the archival master import process on the remote instance.
     *
     * @throws \Dockworker\DockworkerException
     */
    protected function executeArchivalMasterDeployedImport()
    {
        $this->say("Importing archival master [NID#$this->curFileNodeId]...");
        $login_user_cmd = sprintf(
            self::DRUSH_EVAL_LOGIN_USER,
            $this->targetDrupalUid
        );
        $batch_set_cmd = sprintf(
            self::DRUSH_EVAL_BATCH_SET,
            $this->curFileNodeId,
            "/tmp/$this->curFileName",
            $this->targetCommitMessage
        );
        $process_batch_cmd = self::DRUSH_EVAL_PROCESS_BATCH;
        $cmd = [
            '/usr/bin/drush',
            'eval',
            $login_user_cmd . ';' .
            $batch_set_cmd .
            $process_batch_cmd . ';',
        ];
        $this->curContainer->run(
            $cmd,
            $this->dockworkerIO,
            true
        );
    }

    /**
     * Retrieves the already-imported items for the current import.
     *
     * @return array
     *   The current imported items.
     */
    protected function getCurrentImportedItems()
    {
        $cur = $this->getApplicationPersistentDataConfigurationItem(
            'bulk_imports',
            $this->curImportID
        );
        if (empty($cur)) {
            $this->setApplicationPersistentDataConfigurationItem(
                'bulk_imports',
                $this->curImportID,
                []
            );
            return [];
        }
        return $cur;
    }

    /**
     * Updates the already-imported items for the current import.
     *
     * @param mixed $items
     * @return void
     */
    protected function updateCurrentImportedItems($items)
    {
        $this->setApplicationPersistentDataConfigurationItem(
            'bulk_imports',
            $this->curImportID,
            $items
        );
    }
}
