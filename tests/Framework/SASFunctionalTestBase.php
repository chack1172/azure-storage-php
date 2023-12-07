<?php

/**
 * LICENSE: The MIT License (the "License")
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * https://github.com/azure/azure-storage-php/LICENSE
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * PHP version 5
 *
 * @see      https://github.com/azure/azure-storage-php
 */

namespace AzureOSS\Storage\Tests\Framework;

use AzureOSS\Storage\Blob\BlobRestProxy;
use AzureOSS\Storage\Common\Exceptions\ServiceException;
use AzureOSS\Storage\Common\Internal\Resources;
use AzureOSS\Storage\Common\Internal\Serialization\XmlSerializer;
use AzureOSS\Storage\Common\Internal\StorageServiceSettings;
use AzureOSS\Storage\File\FileRestProxy;
use AzureOSS\Storage\Queue\QueueRestProxy;
use AzureOSS\Storage\Table\TableRestProxy;

/**
 * Test base for SAS functional tests.
 *
 * @see      https://github.com/azure/azure-storage-php
 */
class SASFunctionalTestBase extends \PHPUnit\Framework\TestCase
{
    protected $connectionString;
    protected $xmlSerializer;
    protected $serviceSettings;
    protected $createdContainer;
    protected $createdTable;
    protected $createdQueue;
    protected $createdShare;
    protected $blobRestProxy;
    protected $tableRestProxy;
    protected $queueRestProxy;
    protected $fileRestProxy;

    public function __construct()
    {
        if (method_exists(get_parent_class(), '__construct')) {
            parent::__construct(...func_get_args());
        }
        $this->xmlSerializer = new XmlSerializer();
        $this->connectionString = TestResources::getWindowsAzureStorageServicesConnectionString();
        $this->serviceSettings =
            StorageServiceSettings::createFromConnectionString(
                $this->connectionString
            );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->createdContainer = [];
        $this->createdTable = [];
        $this->createdQueue = [];
        $this->createdShare = [];
        $this->blobRestProxy = null;
        $this->tableRestProxy = null;
        $this->queueRestProxy = null;
        $this->fileRestProxy = null;
    }

    protected function setUpWithConnectionString($connectionString)
    {
        $this->blobRestProxy =
            BlobRestProxy::createBlobService($connectionString);
        $this->queueRestProxy =
            QueueRestProxy::createQueueService($connectionString);
        $this->tableRestProxy =
            TableRestProxy::createTableService($connectionString);
        $this->fileRestProxy =
            FileRestProxy::createFileService($connectionString);
    }

    protected function tearDown(): void
    {
        $this->blobRestProxy =
            BlobRestProxy::createBlobService($this->connectionString);
        $this->queueRestProxy =
            QueueRestProxy::createQueueService($this->connectionString);
        $this->tableRestProxy =
            TableRestProxy::createTableService($this->connectionString);
        $this->fileRestProxy =
            FileRestProxy::createFileService($this->connectionString);

        foreach ($this->createdContainer as $container) {
            $this->safeDeleteContainer($container);
        }
        foreach ($this->createdTable as $table) {
            $this->safeDeleteTable($table);
        }
        foreach ($this->createdQueue as $queue) {
            $this->safeDeleteQueue($queue);
        }
        foreach ($this->createdShare as $share) {
            $this->safeDeleteShare($share);
        }

        $this->blobRestProxy = null;
        $this->tableRestProxy = null;
        $this->queueRestProxy = null;
        $this->fileRestProxy = null;
    }

    protected function initializeProxiesWithSASandAccountName($sas, $accountName)
    {
        $connectionString = Resources::BLOB_ENDPOINT_NAME .
                             '=' .
                             'https://' .
                             $accountName .
                             '.' .
                             Resources::BLOB_BASE_DNS_NAME .
                             ';';
        $connectionString .= Resources::QUEUE_ENDPOINT_NAME .
                             '=' .
                             'https://' .
                             $accountName .
                             '.' .
                             Resources::QUEUE_BASE_DNS_NAME .
                             ';';
        $connectionString .= Resources::TABLE_ENDPOINT_NAME .
                             '=' .
                             'https://' .
                             $accountName .
                             '.' .
                             Resources::TABLE_BASE_DNS_NAME .
                             ';';
        $connectionString .= Resources::FILE_ENDPOINT_NAME .
                             '=' .
                             'https://' .
                             $accountName .
                             '.' .
                             Resources::FILE_BASE_DNS_NAME .
                             ';';
        $connectionString .= Resources::SAS_TOKEN_NAME .
                             '=' .
                             $sas;

        $this->setUpWithConnectionString($connectionString);
    }

    protected function createProxyWithSAS($sas, $accountName, $signedResource)
    {
        $connectionString = Resources::SAS_TOKEN_NAME .
                             '=' .
                             $sas;
        switch ($signedResource) {
            case Resources::RESOURCE_TYPE_BLOB:
            case Resources::RESOURCE_TYPE_CONTAINER:
                $connectionString = Resources::BLOB_ENDPOINT_NAME .
                                    '=' .
                                    'https://' .
                                    $accountName .
                                    '.' .
                                    Resources::BLOB_BASE_DNS_NAME .
                                    ';' .
                                    $connectionString;
                return BlobRestProxy::createBlobService($connectionString);
                break;
            case Resources::RESOURCE_TYPE_QUEUE:
                $connectionString = Resources::QUEUE_ENDPOINT_NAME .
                                    '=' .
                                    'https://' .
                                    $accountName .
                                    '.' .
                                    Resources::QUEUE_BASE_DNS_NAME .
                                    ';' .
                                    $connectionString;
                return QueueRestProxy::createQueueService($connectionString);
                break;
            case Resources::RESOURCE_TYPE_TABLE:
                $connectionString = Resources::TABLE_ENDPOINT_NAME .
                                    '=' .
                                    'https://' .
                                    $accountName .
                                    '.' .
                                    Resources::TABLE_BASE_DNS_NAME .
                                    ';' .
                                    $connectionString;
                return TableRestProxy::createTableService($connectionString);
                break;
            case Resources::RESOURCE_TYPE_FILE:
            case Resources::RESOURCE_TYPE_SHARE:
                $connectionString = Resources::FILE_ENDPOINT_NAME .
                                    '=' .
                                    'https://' .
                                    $accountName .
                                    '.' .
                                    Resources::FILE_BASE_DNS_NAME .
                                    ';' .
                                    $connectionString;
                return FileRestProxy::createFileService($connectionString);
                break;
            default:
                self::assertTrue(false);// Given signed resource not valid.
                break;
        }
    }

    protected function safeDeleteContainer($name)
    {
        try {
            $this->blobRestProxy->deleteContainer($name);
            $this->createdContainer = array_diff($this->createdContainer, [$name]);
        } catch (ServiceException $e) {
            error_log($e->getMessage());
        }
    }

    protected function safeCreateContainer($name)
    {
        try {
            $this->blobRestProxy->createContainer($name);
            $this->createdContainer[] = $name;
        } catch (ServiceException $e) {
            error_log($e->getMessage());
        }
    }

    protected function safeDeleteQueue($name)
    {
        try {
            $this->queueRestProxy->deleteQueue($name);
            $this->createdQueue = array_diff($this->createdQueue, [$name]);
        } catch (ServiceException $e) {
            error_log($e->getMessage());
        }
    }

    protected function safeCreateQueue($name)
    {
        try {
            $this->queueRestProxy->createQueue($name);
            $this->createdQueue[] = $name;
        } catch (ServiceException $e) {
            error_log($e->getMessage());
        }
    }

    protected function safeDeleteTable($name)
    {
        try {
            $this->tableRestProxy->deleteTable($name);
            $this->createdTable = array_diff($this->createdTable, [$name]);
        } catch (ServiceException $e) {
            error_log($e->getMessage());
        }
    }

    protected function safeCreateTable($name)
    {
        try {
            $this->tableRestProxy->createTable($name);
            $this->createdTable[] = $name;
        } catch (ServiceException $e) {
            error_log($e->getMessage());
        }
    }

    protected function safeDeleteShare($name)
    {
        try {
            $this->fileRestProxy->deleteShare($name);
            $this->createdShare = array_diff($this->createdShare, [$name]);
        } catch (ServiceException $e) {
            error_log($e->getMessage());
        }
    }

    protected function safeCreateShare($name)
    {
        try {
            $this->fileRestProxy->createShare($name);
            $this->createdShare[] = $name;
        } catch (ServiceException $e) {
            error_log($e->getMessage());
        }
    }

    protected function validateServiceExceptionErrorMessage(
        $errorMsg,
        callable $callable,
        $failureMessage = ''
    ) {
        $message = '';
        try {
            call_user_func($callable);
        } catch (ServiceException $e) {
            $message = $e->getMessage();
        }
        self::assertStringContainsString($errorMsg, $message, $failureMessage);
    }
}
