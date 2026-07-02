<?php

/**
 * @file plugins/generic/crossref/CrossrefExportPlugin.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CrossrefExportPlugin
 *
 * @brief Crossref XML metadata export/deposit plugin for monographs and chapters.
 *
 * Self-contained export layer: rather than depending on OJS's
 * PubObjectsExportPlugin/DOIPubIdExportPlugin (which do not ship with OMP),
 * this class extends the pkp-lib ImportExportPlugin directly and implements
 * only the helpers needed for the DOI registration framework.
 */

namespace APP\plugins\generic\crossref;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\IDoiRegistrationAgency;
use APP\submission\Submission;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use PKP\config\Config;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\doi\Doi;
use PKP\file\FileManager;
use PKP\file\TemporaryFileManager;
use PKP\filter\FilterDAO;
use PKP\plugins\Hook;
use PKP\plugins\ImportExportPlugin;
use PKP\plugins\Plugin;

class CrossrefExportPlugin extends ImportExportPlugin
{
    // The status of the Crossref DOI.
    public const CROSSREF_STATUS_FAILED = 'failed';
    public const CROSSREF_API_DEPOSIT_OK = 200;
    public const CROSSREF_API_DEPOSIT_ERROR_FROM_CROSSREF = 403;
    public const CROSSREF_API_URL = 'https://api.crossref.org/v2/deposits';
    public const CROSSREF_API_URL_DEV = 'https://test.crossref.org/v2/deposits';
    public const CROSSREF_API_STATUS_URL = 'https://doi.crossref.org/servlet/submissionDownload';
    public const CROSSREF_API_STATUS_URL_DEV = 'https://test.crossref.org/servlet/submissionDownload';
    // The name of the setting used to save the registered DOI and the URL with the deposit status.
    public const CROSSREF_DEPOSIT_STATUS = 'depositStatus';

    public function __construct(protected IDoiRegistrationAgency|Plugin $agencyPlugin)
    {
        parent::__construct();
    }

    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success) {
            if (Application::isUnderMaintenance()) {
                return true;
            }
        }
        return $success;
    }

    /**
     * @copydoc Plugin::getName()
     */
    public function getName()
    {
        return 'CrossrefExportPlugin';
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return __('plugins.importexport.crossref.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return __('plugins.importexport.crossref.description');
    }

    /**
     * Filter used to serialize monographs (and their chapters) to Crossref XML.
     */
    public function getSubmissionFilter()
    {
        return 'monograph=>crossref-xml';
    }

    /** Proxy to main plugin class's `getSetting` method */
    public function getSetting($contextId, $name)
    {
        return $this->agencyPlugin->getSetting($contextId, $name);
    }

    /**
     * @copydoc ImportExportPlugin::getPluginSettingsPrefix()
     */
    public function getPluginSettingsPrefix()
    {
        return 'crossrefplugin';
    }

    /**
     * Whether the plugin is operating against the Crossref test system.
     */
    public function isTestMode($context)
    {
        return ($this->getSetting($context->getId(), 'testMode') == 1);
    }

    /**
     * @copydoc ImportExportPlugin::getExportDeploymentClassName()
     */
    public function getExportDeploymentClassName()
    {
        return (string) \APP\plugins\generic\crossref\CrossrefExportDeployment::class;
    }

    public function _instantiateExportDeployment($context)
    {
        $exportDeploymentClassName = $this->getExportDeploymentClassName();
        return new $exportDeploymentClassName($context, $this->agencyPlugin);
    }

    /**
     * Get the XML for a set of monographs by running the serialization filter.
     *
     * @param mixed $objects Array of or single Submission
     * @param string $filter
     * @param Context $context
     * @param bool $noValidation If set to true no XML validation will be done
     * @param null|array $outputErrors
     *
     * @return string XML document.
     */
    public function exportXML($objects, $filter, $context, $noValidation = null, &$outputErrors = null)
    {
        /** @var FilterDAO $filterDao */
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $exportFilters = $filterDao->getObjectsByGroup($filter);
        assert(count($exportFilters) == 1); // Assert only a single serialization filter
        $exportFilter = array_shift($exportFilters);
        $exportDeployment = $this->_instantiateExportDeployment($context);
        $exportFilter->setDeployment($exportDeployment);
        if ($noValidation) {
            $exportFilter->setNoValidation($noValidation);
        }
        libxml_use_internal_errors(true);
        $exportXml = $exportFilter->execute($objects, true);
        $xml = $exportXml->saveXml();
        $errors = array_filter(libxml_get_errors(), function ($a) {
            return $a->level == LIBXML_ERR_ERROR || $a->level == LIBXML_ERR_FATAL;
        });
        if (!empty($errors)) {
            if ($outputErrors === null) {
                $this->displayXMLValidationErrors($errors, $xml);
            } else {
                $outputErrors = $errors;
            }
        }
        return $xml;
    }

    /**
     * Exports and deposits the XML for the given monographs, one request per object.
     */
    public function exportAndDeposit($context, $objects, $filter, string &$responseMessage, $noValidation = null): bool
    {
        $fileManager = new FileManager();
        $resultErrors = [];

        assert($filter != null);

        $errorsOccurred = false;
        // The Crossref deposit API expects one request per object, so we loop.
        foreach ($objects as $object) {
            $exportErrors = [];
            $exportXml = $this->exportXML([$object], $filter, $context, $noValidation, $exportErrors);
            $objectFileNamePart = $this->_getObjectFileNamePart($object);
            $exportFileName = $this->getExportFileName($this->getExportPath(), $objectFileNamePart, $context, '.xml');
            $fileManager->writeFile($exportFileName, $exportXml);
            $result = $this->depositXML($object, $context, $exportFileName);
            if (!$result) {
                $errorsOccurred = true;
            }
            if (is_array($result)) {
                $resultErrors[] = $result;
            }
            $fileManager->deleteByPath($exportFileName);
        }

        if (empty($resultErrors)) {
            if ($errorsOccurred) {
                $responseMessage = 'plugins.importexport.crossref.register.error.mdsError';
                return false;
            } else {
                $responseMessage = $this->getDepositSuccessNotificationMessageKey();
                return true;
            }
        } else {
            $responseMessage = 'api.dois.400.depositFailed';
            return false;
        }
    }

    /**
     * Exports and stores XML as a TemporaryFile
     *
     * @throws Exception
     */
    public function exportAsDownload(Context $context, array $objects, string $filter, string $objectsFileNamePart, ?bool $noValidation = null, ?array &$exportErrors = null): ?int
    {
        $fileManager = new TemporaryFileManager();

        $exportErrors = [];
        $exportXml = $this->exportXML($objects, $filter, $context, $noValidation, $exportErrors);

        $exportFileName = $this->getExportFileName($this->getExportPath(), $objectsFileNamePart, $context, '.xml');

        $fileManager->writeFile($exportFileName, $exportXml);

        $user = Application::get()->getRequest()->getUser();

        return $fileManager->createTempFileFromExisting($exportFileName, $user->getId());
    }

    /**
     * @param Submission $objects
     * @param Context $context
     * @param string $filename Export XML filename
     *
     * @throws GuzzleException
     */
    public function depositXML($objects, $context, $filename)
    {
        // Application is set to sandbox mode and will not run the features of plugin
        if (Config::getVar('general', 'sandbox', false)) {
            error_log('Application is set to sandbox mode and will not have any interaction with crossref external service');
            return false;
        }

        $status = null;
        $msgSave = null;

        $httpClient = Application::get()->getHttpClient();
        assert(is_readable($filename));

        try {
            $response = $httpClient->request(
                'POST',
                $this->isTestMode($context) ? static::CROSSREF_API_URL_DEV : static::CROSSREF_API_URL,
                [
                    'multipart' => [
                        [
                            'name' => 'usr',
                            'contents' => $this->getSetting($context->getId(), 'username'),
                        ],
                        [
                            'name' => 'pwd',
                            'contents' => $this->getSetting($context->getId(), 'password'),
                        ],
                        [
                            'name' => 'operation',
                            'contents' => 'doMDUpload',
                        ],
                        [
                            'name' => 'mdFile',
                            'contents' => fopen($filename, 'r'),
                        ],
                    ]
                ]
            );
        } catch (RequestException $e) {
            $returnMessage = $e->getMessage();
            if ($e->hasResponse()) {
                $eResponseBody = $e->getResponse()->getBody();
                $eStatusCode = $e->getResponse()->getStatusCode();
                if ($eStatusCode == static::CROSSREF_API_DEPOSIT_ERROR_FROM_CROSSREF) {
                    $xmlDoc = new \DOMDocument('1.0', 'utf-8');
                    $xmlDoc->loadXML($eResponseBody);
                    $batchIdNode = $xmlDoc->getElementsByTagName('batch_id')->item(0);
                    $msg = $xmlDoc->getElementsByTagName('msg')->item(0)->nodeValue;
                    $msgSave = $msg . PHP_EOL . $eResponseBody;
                    $status = Doi::STATUS_ERROR;
                    $this->updateDepositStatus($context, $objects, $status, $batchIdNode->nodeValue, $msgSave);
                    $returnMessage = $msg . ' (' . $eStatusCode . ' ' . $e->getResponse()->getReasonPhrase() . ')';
                } else {
                    $returnMessage = $eResponseBody . ' (' . $eStatusCode . ' ' . $e->getResponse()->getReasonPhrase() . ')';
                    $this->updateDepositStatus($context, $objects, Doi::STATUS_ERROR, null, $returnMessage);
                }
            }
            return [['plugins.importexport.common.register.error.mdsError', $returnMessage]];
        }

        // Get DOMDocument from the response XML string
        $xmlDoc = new \DOMDocument('1.0', 'utf-8');
        $xmlDoc->loadXML($response->getBody());
        $batchIdNode = $xmlDoc->getElementsByTagName('batch_id')->item(0);
        $submissionIdNode = $xmlDoc->getElementsByTagName('submission_id')->item(0);
        $successMessage = __('plugins.generic.crossref.successMessage', ['submissionId' => $submissionIdNode->nodeValue]);

        // Get the DOI deposit status
        $failureCountNode = $xmlDoc->getElementsByTagName('failure_count')->item(0);
        $failureCount = (int) $failureCountNode->nodeValue;
        if ($failureCount > 0) {
            $status = Doi::STATUS_ERROR;
            $result = false;
        } else {
            // Deposit was received
            $status = Doi::STATUS_REGISTERED;
            $result = true;

            // If there were some warnings, display them
            $warningCountNode = $xmlDoc->getElementsByTagName('warning_count')->item(0);
            $warningCount = (int) $warningCountNode->nodeValue;
            if ($warningCount > 0) {
                $result = [['plugins.importexport.crossref.register.success.warning', htmlspecialchars($response->getBody())]];
            }
            // A possibility for other plugins (e.g. reference linking) to work with the response
            Hook::run('crossrefexportplugin::deposited', [[$this, $response->getBody(), $objects]]);
        }

        // Update the status
        if ($status) {
            $this->updateDepositStatus($context, $objects, $status, $batchIdNode->nodeValue, $msgSave, $successMessage);
        }

        return $result;
    }

    /**
     * Update the deposit status for all DOIs belonging to the given monograph
     * (monograph publication + chapters).
     *
     * @param Context $context
     * @param Submission $object The object getting deposited
     * @param int $status
     * @param string $batchId
     * @param string $failedMsg (optional)
     * @param null|mixed $successMsg
     */
    public function updateDepositStatus($context, $object, $status, $batchId = null, $failedMsg = null, $successMsg = null)
    {
        assert($object instanceof Submission);
        $doiIds = Repo::doi()->getDoisForSubmission($object->getId());

        foreach ($doiIds as $doiId) {
            $doi = Repo::doi()->get($doiId);

            $editParams = [
                'status' => $status,
                // Sets new failedMsg or resets to null for removal of previous message
                $this->getFailedMsgSettingName() => $failedMsg,
                $this->getDepositBatchIdSettingName() => $batchId,
                $this->getSuccessMsgSettingName() => $successMsg,
            ];

            if ($status === Doi::STATUS_REGISTERED) {
                $editParams['registrationAgency'] = $this->getName();
            }

            Repo::doi()->edit($doi, $editParams);
        }
    }

    /**
     * Mark selected monographs as registered.
     *
     * @param Context $context
     * @param Submission[] $objects
     */
    public function markRegistered($context, $objects)
    {
        foreach ($objects as $object) {
            $doiIds = Repo::doi()->getDoisForSubmission($object->getId());
            foreach ($doiIds as $doiId) {
                Repo::doi()->markRegistered($doiId);
            }
        }
    }

    /**
     * Get request failed message setting name.
     */
    public function getFailedMsgSettingName()
    {
        return $this->getPluginSettingsPrefix() . '_failedMsg';
    }

    /**
     * Get deposit batch ID setting name.
     */
    public function getDepositBatchIdSettingName()
    {
        return $this->getPluginSettingsPrefix() . '_batchId';
    }

    public function getSuccessMsgSettingName(): string
    {
        return $this->getPluginSettingsPrefix() . '_successMsg';
    }

    /**
     * Notification message shown on a successful deposit.
     */
    public function getDepositSuccessNotificationMessageKey()
    {
        return 'plugins.importexport.common.register.success';
    }

    /**
     * @param Submission $object
     */
    private function _getObjectFileNamePart($object): string
    {
        if ($object instanceof Submission) {
            return 'monographs-' . $object->getId();
        }
        return '';
    }

    /**
     * @copydoc ImportExportPlugin::executeCLI()
     */
    public function executeCLI($scriptName, &$args)
    {
        throw new Exception('Not implemented.');
    }

    /**
     * @copydoc ImportExportPlugin::usage()
     */
    public function usage($scriptName)
    {
    }

    /**
     * @copydoc ImportExportPlugin::supportsCLI()
     */
    public function supportsCLI(): bool
    {
        return false;
    }
}
