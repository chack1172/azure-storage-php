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

namespace AzureOSS\Storage\Tests\Unit\Blob;

use AzureOSS\Storage\Blob\BlobRestProxy;
use AzureOSS\Storage\Blob\Internal\BlobResources;
use AzureOSS\Storage\Blob\Internal\IBlob;
use AzureOSS\Storage\Blob\Models\AccessCondition;
use AzureOSS\Storage\Blob\Models\AppendBlockOptions;
use AzureOSS\Storage\Blob\Models\BlobBlockType;
use AzureOSS\Storage\Blob\Models\BlobType;
use AzureOSS\Storage\Blob\Models\Block;
use AzureOSS\Storage\Blob\Models\BlockList;
use AzureOSS\Storage\Blob\Models\ContainerACL;
use AzureOSS\Storage\Blob\Models\CopyBlobOptions;
use AzureOSS\Storage\Blob\Models\CreateBlockBlobOptions;
use AzureOSS\Storage\Blob\Models\CreateContainerOptions;
use AzureOSS\Storage\Blob\Models\CreatePageBlobFromContentOptions;
use AzureOSS\Storage\Blob\Models\CreatePageBlobOptions;
use AzureOSS\Storage\Blob\Models\DeleteBlobOptions;
use AzureOSS\Storage\Blob\Models\GetBlobOptions;
use AzureOSS\Storage\Blob\Models\GetBlobPropertiesOptions;
use AzureOSS\Storage\Blob\Models\ListBlobBlocksOptions;
use AzureOSS\Storage\Blob\Models\ListBlobsOptions;
use AzureOSS\Storage\Blob\Models\ListContainersOptions;
use AzureOSS\Storage\Blob\Models\PublicAccessType;
use AzureOSS\Storage\Blob\Models\SetBlobPropertiesOptions;
use AzureOSS\Storage\Common\Exceptions\ServiceException;
use AzureOSS\Storage\Common\Internal\Resources;
use AzureOSS\Storage\Common\Models\Range;
use AzureOSS\Storage\Common\Models\RangeDiff;
use AzureOSS\Storage\Common\Models\ServiceProperties;
use AzureOSS\Storage\Tests\Framework\BlobServiceRestProxyTestBase;
use AzureOSS\Storage\Tests\Framework\TestResources;
use AzureOSS\Storage\Tests\Framework\VirtualFileSystem;

/**
 * Unit tests for class BlobRestProxy
 *
 * @see      https://github.com/azure/azure-storage-php
 */
class BlobRestProxyTest extends BlobServiceRestProxyTestBase
{
    private function createSuffix()
    {
        return sprintf('-%04x', mt_rand(0, 65535));
    }

    private function createPrefix()
    {
        return sprintf('blob-%d', time());
    }

    public function testBuildForBlob()
    {
        // Test
        $blobRestProxy = BlobRestProxy::createBlobService(TestResources::getWindowsAzureStorageServicesConnectionString());

        // Assert
        self::assertInstanceOf(IBlob::class, $blobRestProxy);
    }

    public function testBuildForAnonymousAccess()
    {
        $pEndpoint = sprintf(
            '%s://%s%s',
            Resources::HTTP_SCHEME,
            'myaccount.',
            Resources::BLOB_BASE_DNS_NAME
        );

        $blobRestProxy = BlobRestProxy::createContainerAnonymousAccess(
            $pEndpoint
        );

        self::assertInstanceOf(IBlob::class, $blobRestProxy);
        self::assertEquals('myaccount', $blobRestProxy->getAccountName());
    }

    public function testSetServiceProperties()
    {
        $this->skipIfEmulated();

        // Setup
        $expected = ServiceProperties::create(TestResources::setBlobServicePropertiesSample());

        // Test
        $this->setServiceProperties($expected);
        //Add 30s interval to wait for setting to take effect.
        \sleep(30);
        $actual = $this->restProxy->getServiceProperties();

        // Assert
        self::assertEquals($expected->toXml($this->xmlSerializer), $actual->getValue()->toXml($this->xmlSerializer));
    }

    public function testListContainersSimple()
    {
        // Setup
        $container1 = 'listcontainersimple1' . $this->createSuffix();
        $container2 = 'listcontainersimple2' . $this->createSuffix();
        $container3 = 'listcontainersimple3' . $this->createSuffix();

        parent::createContainer($container1);
        parent::createContainer($container2);
        parent::createContainer($container3);

        // Test
        $result = $this->restProxy->listContainers();

        // Assert
        $containers = $result->getContainers();
        self::assertNotNull($result->getAccountName());
        self::assertTrue($this->existInContainerArray($container1, $containers));
        self::assertTrue($this->existInContainerArray($container2, $containers));
        self::assertTrue($this->existInContainerArray($container3, $containers));
    }

    public function testListContainersWithOptions()
    {
        // Setup
        $prefix = $this->createPrefix();
        $container0 = $prefix . 'listcontainerwithoptions0' . $this->createSuffix();
        $container1 = $prefix . 'listcontainerwithoptions1' . $this->createSuffix();
        $container2 = $prefix . 'listcontainerwithoptions2' . $this->createSuffix();
        $container3 = 'm' . $prefix . 'mlistcontainerwithoptions3' . $this->createSuffix();
        $metadataName = 'Mymetadataname';
        $metadataValue = 'MetadataValue';
        $options = new CreateContainerOptions();
        $options->addMetadata($metadataName, $metadataValue);
        $options->setPublicAccess(PublicAccessType::BLOBS_ONLY);
        parent::createContainer($container0);
        parent::createContainer($container1, new CreateContainerOptions());
        parent::createContainer($container2, $options);
        parent::createContainer($container3);
        $options = new ListContainersOptions();
        $options->setPrefix($prefix);
        $options->setIncludeMetadata(true);

        // Test
        $result = $this->restProxy->listContainers($options);

        // Assert
        $containers = $result->getContainers();
        $metadata = $containers[2]->getMetadata();
        self::assertCount(3, $containers);
        self::assertTrue($this->existInContainerArray($container0, $containers));
        self::assertTrue($this->existInContainerArray($container1, $containers));
        self::assertTrue($this->existInContainerArray($container2, $containers));
        self::assertEquals($metadataValue, $metadata[$metadataName]);

        self::assertEquals(
            PublicAccessType::CONTAINER_AND_BLOBS,
            $containers[0]->getProperties()->getPublicAccess()
        );
        self::assertEquals(
            PublicAccessType::NONE,
            $containers[1]->getProperties()->getPublicAccess()
        );
        self::assertEquals(
            PublicAccessType::BLOBS_ONLY,
            $containers[2]->getProperties()->getPublicAccess()
        );
    }

    public function testListContainersWithNextMarker()
    {
        // Setup
        $prefix = $this->createPrefix();
        $container1 = $prefix . 'listcontainerswithnextmarker1' . $this->createSuffix();
        $container2 = $prefix . 'listcontainerswithnextmarker2' . $this->createSuffix();
        $container3 = $prefix . 'listcontainerswithnextmarker3' . $this->createSuffix();
        parent::createContainer($container1);
        parent::createContainer($container2);
        parent::createContainer($container3);
        $options = new ListContainersOptions();
        $options->setMaxResults('2');

        // Test
        $result = $this->restProxy->listContainers($options);

        // Assert
        $containers = $result->getContainers();
        self::assertCount(2, $containers);
        self::assertEquals($container1, $containers[0]->getName());
        self::assertEquals($container2, $containers[1]->getName());

        // Test
        $options->setMarker($result->getNextMarker());
        $result = $this->restProxy->listContainers($options);
        $containers = $result->getContainers();

        // Assert
        self::assertCount(1, $containers);
        self::assertEquals($container3, $containers[0]->getName());
    }

    public function testListContainersWithInvalidNextMarkerFail()
    {
        $this->expectException(\AzureOSS\Storage\Common\Exceptions\ServiceException::class);
        $this->expectExceptionMessage('400');

        $this->skipIfEmulated();

        // Setup
        $container1 = 'listcontainerswithinvalidnextmarker1' . $this->createSuffix();
        $container2 = 'listcontainerswithinvalidnextmarker2' . $this->createSuffix();
        $container3 = 'listcontainerswithinvalidnextmarker3' . $this->createSuffix();
        parent::createContainer($container1);
        parent::createContainer($container2);
        parent::createContainer($container3);
        $options = new ListContainersOptions();
        $options->setMaxResults('2');

        // Test
        $this->restProxy->listContainers($options);
        $options->setMarker('wrong marker');
        $this->restProxy->listContainers($options);
    }

    public function testListContainersWithNoContainers()
    {
        // Setup
        $this->deleteAllStorageContainers();

        // Test
        $result = $this->restProxy->listContainers();

        // Assert
        $containers = $result->getContainers();
        self::assertEmpty($containers);
    }

    public function testListContainersWithOneResult()
    {
        // Setup
        $containerName = 'listcontainerswithoneresult' . $this->createSuffix();
        parent::createContainer($containerName);

        // Test
        $result = $this->restProxy->listContainers();
        $containers = $result->getContainers();

        // Assert
        self::assertCount(1, $containers);
    }

    public function testCreateContainerSimple()
    {
        // Setup
        $containerName = 'createcontainersimple' . $this->createSuffix();

        // Test
        $this->createContainer($containerName);

        // Assert
        $result = $this->restProxy->listContainers();
        $containers = $result->getContainers();
        self::assertCount(1, $containers);
        self::assertEquals($containers[0]->getName(), $containerName);
    }

    public function testCreateContainerWithoutOptions()
    {
        // Setup
        $containerName = 'createcontainerwithoutoptions' . $this->createSuffix();

        // Test
        $this->createContainer($containerName);

        // Assert
        $result = $this->restProxy->listContainers();
        $containers = $result->getContainers();
        self::assertCount(1, $containers);
        self::assertEquals($containers[0]->getName(), $containerName);
    }

    public function testCreateContainerWithMetadata()
    {
        $containerName = 'createcontainerwithmetadata' . $this->createSuffix();
        $metadataName = 'Name';
        $metadataValue = 'MyName';
        $options = new CreateContainerOptions();
        $options->addMetadata($metadataName, $metadataValue);
        $options->setPublicAccess('blob');

        // Test
        $this->createContainer($containerName, $options);

        // Assert
        $options = new ListContainersOptions();
        $options->setIncludeMetadata(true);
        $result = $this->restProxy->listContainers($options);
        $containers = $result->getContainers();
        $metadata = $containers[0]->getMetadata();
        self::assertEquals($metadataValue, $metadata[$metadataName]);
    }

    public function testCreateContainerInvalidNameFail()
    {
        $this->expectException(\AzureOSS\Storage\Common\Exceptions\ServiceException::class);
        $this->expectExceptionMessage('400');

        // Setup
        $containerName = 'CreateContainerInvalidNameFail' . $this->createSuffix();

        // Test
        $this->createContainer($containerName);
    }

    public function testCreateContainerAlreadyExitsFail()
    {
        $this->expectException(\AzureOSS\Storage\Common\Exceptions\ServiceException::class);
        $this->expectExceptionMessage('409');

        // Setup
        $containerName = 'createcontaineralreadyexitsfail' . $this->createSuffix();
        $this->createContainer($containerName);

        // Test
        $this->createContainer($containerName);
    }

    public function testDeleteContainer()
    {
        // Setup
        $containerName = 'deletecontainer' . $this->createSuffix();
        $this->createContainer($containerName);

        // Test
        $this->restProxy->deleteContainer($containerName);

        // Assert
        $result = $this->restProxy->listContainers();
        $containers = $result->getContainers();
        self::assertEmpty($containers);
    }

    public function testDeleteContainerFail()
    {
        $this->expectException(\AzureOSS\Storage\Common\Exceptions\ServiceException::class);
        $this->expectExceptionMessage('404');

        // Setup
        $containerName = 'deletecontainerfail' . $this->createSuffix();

        // Test
        $this->restProxy->deleteContainer($containerName);
    }

    public function testGetContainerProperties()
    {
        // Setup
        $containerWithContainerLevelAccess = 'getcontainerproperties' . $this->createSuffix();
        $containerWithBlobLevelAccess = 'getcontainerproperties' . $this->createSuffix();
        $containerWithoutPublicAccess = 'getcontainerproperties' . $this->createSuffix();

        $options = new CreateContainerOptions();
        $options->setPublicAccess(PublicAccessType::BLOBS_ONLY);

        $this->createContainer($containerWithContainerLevelAccess);
        $this->createContainer($containerWithBlobLevelAccess, $options);
        $this->createContainer($containerWithoutPublicAccess, new CreateContainerOptions());

        // Test
        $resultWithContainerLevelAccess = $this->restProxy->getContainerProperties($containerWithContainerLevelAccess);
        $resultWithBlobLevelAccess = $this->restProxy->getContainerProperties($containerWithBlobLevelAccess);
        $resultWithoutPublicAccess = $this->restProxy->getContainerProperties($containerWithoutPublicAccess);

        // Assert
        self::assertEquals(PublicAccessType::CONTAINER_AND_BLOBS, $resultWithContainerLevelAccess->getPublicAccess());
        self::assertEquals(PublicAccessType::BLOBS_ONLY, $resultWithBlobLevelAccess->getPublicAccess());
        self::assertEquals(PublicAccessType::NONE, $resultWithoutPublicAccess->getPublicAccess());
        self::assertNotNull($resultWithContainerLevelAccess->getETag());
        self::assertNotNull($resultWithContainerLevelAccess->getLastModified());
        self::assertCount(0, $resultWithContainerLevelAccess->getMetadata());
    }

    public function testGetContainerMetadata()
    {
        // Setup
        $name = 'getcontainermetadata' . $this->createSuffix();
        $options = new CreateContainerOptions();
        $expected = ['name1' => 'MyName1', 'mymetaname' => '12345', 'values' => 'Microsoft_'];
        $options->setMetadata($expected);
        $this->createContainer($name, $options);
        $result = $this->restProxy->getContainerProperties($name);
        $expectedETag = $result->getETag();
        $expectedLastModified = $result->getLastModified();

        // Test
        $result = $this->restProxy->getContainerMetadata($name);

        // Assert
        self::assertEquals($expectedETag, $result->getETag());
        self::assertEquals($expectedLastModified, $result->getLastModified());
        self::assertEquals($expected, $result->getMetadata());
    }

    public function testGetContainerAcl()
    {
        // Setup
        $name = 'getcontaineracl' . $this->createSuffix();
        $expectedAccess = 'container';
        $this->createContainer($name);

        // Test
        $result = $this->restProxy->getContainerAcl($name);

        // Assert
        self::assertEquals($expectedAccess, $result->getContainerAcl()->getPublicAccess());
    }

    public function testSetContainerAcl()
    {
        // Setup
        $name = 'setcontaineracl' . $this->createSuffix();
        $this->createContainer($name);
        $sample = TestResources::getContainerAclMultipleEntriesSample();
        $expectedETag = '0x8CAFB82EFF70C46';
        $expectedLastModified = new \DateTime('Sun, 25 Sep 2011 19:42:18 GMT');
        $expectedPublicAccess = 'container';
        $acl = ContainerACL::create($expectedPublicAccess, $sample['SignedIdentifiers']);

        // Test
        $this->restProxy->setContainerAcl($name, $acl);

        // Assert
        $actual = $this->restProxy->getContainerAcl($name);
        self::assertEquals($acl->getPublicAccess(), $actual->getContainerAcl()->getPublicAccess());
        self::assertEquals($acl->getSignedIdentifiers(), $actual->getContainerAcl()->getSignedIdentifiers());
    }

    public function testSetContainerMetadata()
    {
        // Setup
        $name = 'setcontainermetadata' . $this->createSuffix();
        $expected = ['name1' => 'MyName1', 'mymetaname' => '12345', 'values' => 'Microsoft_'];
        $this->createContainer($name);

        // Test
        $this->restProxy->setContainerMetadata($name, $expected);

        // Assert
        $result = $this->restProxy->getContainerProperties($name);
        $expectedETag = $result->getETag();
        $expectedLastModified = $result->getLastModified();
        self::assertEquals($expectedETag, $result->getETag());
        self::assertEquals($expectedLastModified, $result->getLastModified());
        self::assertEquals($expected, $result->getMetadata());
    }

    public function testListBlobsNull()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('can\'t be NULL.');

        $this->restProxy->listBlobs(null);
    }

    public function testListBlobsSimple()
    {
        // Setup
        $name = 'listblobssimple' . $this->createSuffix();
        $blob1 = 'blob1';
        $blob2 = 'blob2';
        $blob3 = 'blob3';
        $length = 512;

        $this->createContainer($name);
        $this->restProxy->createPageBlob($name, $blob1, $length);
        $this->restProxy->createPageBlob($name, $blob2, $length);
        $this->restProxy->createPageBlob($name, $blob3, $length);

        // Test
        $result = $this->restProxy->listBlobs($name);

        // Assert
        $blobs = $result->getBlobs();
        self::assertNotNull($result->getContainerName());
        self::assertEquals($blob1, $blobs[0]->getName());
        self::assertEquals($blob2, $blobs[1]->getName());
        self::assertEquals($blob3, $blobs[2]->getName());
        self::assertNull($blobs[2]->getSnapshot());
        self::assertNotNull($blobs[2]->getUrl());
        self::assertCount(0, $blobs[2]->getMetadata());
        self::assertInstanceOf('AzureOSS\Storage\Blob\Models\BlobProperties', $blobs[2]->getProperties());
    }

    public function testListBlobsWithOptions()
    {
        // Setup
        $name = 'listblobswithoptions' . $this->createSuffix();
        $blob1 = 'blob1';
        $blob2 = 'blob2';
        $blob3 = 'blob3';
        $blob4 = 'lblob1';
        $blob5 = 'lblob2';
        $blob6 = 'lblob3';
        $length = 512;
        $options = new ListBlobsOptions();
        $options->setIncludeMetadata(true);
        $options->setIncludeSnapshots(true);
        $options->setIncludeUncommittedBlobs(true);
        $options->setMaxResults(10);
        $options->setPrefix('lb');

        $this->createContainer($name);
        $this->restProxy->createPageBlob($name, $blob1, $length);
        $this->restProxy->createPageBlob($name, $blob2, $length);
        $this->restProxy->createPageBlob($name, $blob3, $length);
        $this->restProxy->createPageBlob($name, $blob4, $length);
        $this->restProxy->createPageBlob($name, $blob5, $length);
        $this->restProxy->createPageBlob($name, $blob6, $length);

        // Test
        $result = $this->restProxy->listBlobs($name, $options);

        // Assert
        self::assertCount(3, $result->getBlobs());
        self::assertCount(0, $result->getBlobPrefixes());
    }

    public function testListBlobsIncludeDeleted()
    {
        // Setup
        $name = 'listblobswithdeleted' . $this->createSuffix();
        $blob1 = 'blob1';
        $blob2 = 'blob2';
        $blob3 = 'blob3';
        $blob4 = 'blob4';
        $length = 512;
        $options = new ListBlobsOptions();
        $options->setIncludeDeleted(true);
        $options->setMaxResults(10);

        $this->createContainer($name);
        $this->restProxy->createPageBlob($name, $blob1, $length);
        $this->restProxy->createPageBlob($name, $blob2, $length);
        $this->restProxy->createPageBlob($name, $blob3, $length);
        $this->restProxy->createPageBlob($name, $blob4, $length);

        // List blobs
        $result = $this->restProxy->listBlobs($name, $options);

        // Assert
        self::assertCount(4, $result->getBlobs());

        // Delete blob
        $this->restProxy->deleteBlob($name, $blob4);

        // List blobs
        $result = $this->restProxy->listBlobs($name, $options);

        // Assert
        self::assertCount(4, $result->getBlobs());
    }

    public function testListBlobsWithOptionsWithDelimiter()
    {
        $this->skipIfEmulated();

        // Setup
        $name = 'listblobswithoptionswithdelimiter' . $this->createSuffix();
        $blob1 = 'blob1';
        $blob2 = 'blob2';
        $blob3 = 'blob3';
        $blob4 = 'lblob1';
        $blob5 = 'lblob2';
        $blob6 = 'lblob3';
        $length = 512;
        $options = new ListBlobsOptions();
        $options->setDelimiter('b');
        $options->setIncludeMetadata(true);
        $options->setIncludeUncommittedBlobs(true);
        $options->setMaxResults(10);
        $this->createContainer($name);
        $this->restProxy->createPageBlob($name, $blob1, $length);
        $this->restProxy->createPageBlob($name, $blob2, $length);
        $this->restProxy->createPageBlob($name, $blob3, $length);
        $this->restProxy->createPageBlob($name, $blob4, $length);
        $this->restProxy->createPageBlob($name, $blob5, $length);
        $this->restProxy->createPageBlob($name, $blob6, $length);

        // Test
        $result = $this->restProxy->listBlobs($name, $options);

        // Assert
        self::assertCount(0, $result->getBlobs());
        self::assertCount(2, $result->getBlobPrefixes());
    }

    public function testListBlobsWithNextMarker()
    {
        // Setup
        $name = 'listblobswithnextmarker' . $this->createSuffix();
        $blob1 = 'blob1';
        $blob2 = 'blob2';
        $blob3 = 'blob3';
        $length = 512;
        $options = new ListBlobsOptions();
        $options->setMaxResults(2);

        $this->createContainer($name);
        $this->restProxy->createPageBlob($name, $blob1, $length);
        $this->restProxy->createPageBlob($name, $blob2, $length);
        $this->restProxy->createPageBlob($name, $blob3, $length);

        // Test
        $result = $this->restProxy->listBlobs($name, $options);

        // Assert
        self::assertCount(2, $result->getBlobs());

        // Setup
        $options->setMarker($result->getNextMarker());

        $result = $this->restProxy->listBlobs($name, $options);

        // Assert
        self::assertCount(1, $result->getBlobs());
    }

    public function testListBlobsWithNoBlobs()
    {
        // Test
        $name = 'listblobswithnoblobs' . $this->createSuffix();
        $this->createContainer($name);
        $result = $this->restProxy->listBlobs($name);

        // Assert
        self::assertCount(0, $result->getBlobs());
    }

    public function testListBlobsWithOneResult()
    {
        // Test
        $name = 'listblobswithoneresult' . $this->createSuffix();
        $this->createContainer($name);
        $this->restProxy->createPageBlob($name, 'myblob', 512);
        $result = $this->restProxy->listBlobs($name);

        // Assert
        self::assertCount(1, $result->getBlobs());
    }

    public function testCreatePageBlob()
    {
        // Setup
        $name = 'createpageblob' . $this->createSuffix();
        $this->createContainer($name);

        // Test
        $createResult = $this->restProxy->createPageBlob($name, 'myblob', 512);

        // Assert
        $result = $this->restProxy->listBlobs($name);
        self::assertNotNull($createResult->getETag());
        self::assertIsBool($createResult->getRequestServerEncrypted());
        self::assertInstanceOf('\DateTime', $createResult->getLastModified());
        self::assertCount(1, $result->getBlobs());
    }

    public function testCreateAppendBlob()
    {
        // Setup
        $name = 'createappendblob' . $this->createSuffix();
        $this->createContainer($name);

        // Test
        $createResult = $this->restProxy->createAppendBlob($name, 'myblob');

        // Assert
        self::assertNotNull($createResult->getETag());
        self::assertInstanceOf('\DateTime', $createResult->getLastModified());
        self::assertIsBool($createResult->getRequestServerEncrypted());

        $appendBlob = $this->restProxy->getBlobProperties($name, 'myblob');
        self::assertEquals('AppendBlob', $appendBlob->getProperties()->getBlobType());
        self::assertEquals(0, $appendBlob->getProperties()->getCommittedBlockCount());
        self::assertIsBool($appendBlob->getProperties()->getServerEncrypted());
    }

    public function testAppendBlock()
    {
        // Setup
        $name = 'createappendblob' . $this->createSuffix();
        $this->createContainer($name);
        $textToBeAppended = 'text to be appended';

        // Test
        $createResult = $this->restProxy->createAppendBlob($name, 'myblob');
        $appendResult = $this->restProxy->appendBlock($name, 'myblob', $textToBeAppended);

        // Assert
        self::assertNotNull($appendResult->getETag());
        self::assertInstanceOf('\DateTime', $appendResult->getLastModified());
        self::assertEquals(0, $appendResult->getAppendOffset());
        self::assertEquals(1, $appendResult->getCommittedBlockCount());
        self::assertIsBool($appendResult->getRequestServerEncrypted());
        self::assertIsBool($createResult->getRequestServerEncrypted());

        // List blobs
        $listBlobs = $this->restProxy->listBlobs($name, null)->getBlobs();
        self::assertCount(1, $listBlobs);
        self::assertEquals('AppendBlob', $listBlobs[0]->getProperties()->getBlobType());

        // Get append blob properties
        $appendBlob = $this->restProxy->getBlobProperties($name, 'myblob');
        self::assertEquals('AppendBlob', $appendBlob->getProperties()->getBlobType());
        self::assertEquals(1, $appendBlob->getProperties()->getCommittedBlockCount());
        self::assertEquals(strlen($textToBeAppended), $appendBlob->getProperties()->getContentLength());

        // Append again
        $appendResult = $this->restProxy->appendBlock($name, 'myblob', $textToBeAppended);
        self::assertNotNull($appendResult->getETag());
        self::assertInstanceOf('\DateTime', $appendResult->getLastModified());
        self::assertEquals(19, $appendResult->getAppendOffset());
        self::assertEquals(2, $appendResult->getCommittedBlockCount());

        $appendBlob = $this->restProxy->getBlobProperties($name, 'myblob');
        self::assertEquals('AppendBlob', $appendBlob->getProperties()->getBlobType());
        self::assertEquals(2, $appendBlob->getProperties()->getCommittedBlockCount());
        self::assertEquals(2 * strlen($textToBeAppended), $appendBlob->getProperties()->getContentLength());
    }

    public function testAppendBlockSuccessWithAppendPosition()
    {
        // Setup
        $name = 'appendblockappendpositionsuccess' . $this->createSuffix();
        $this->createContainer($name);
        $textToBeAppended = 'text to be appended';
        $appendBlockOption = new AppendBlockOptions();
        $appendBlockOption->setAppendPosition(0);

        // Test
        $this->restProxy->createAppendBlob($name, 'myblob');
        $this->restProxy->appendBlock($name, 'myblob', $textToBeAppended, $appendBlockOption);

        // Append again
        $appendBlockOption->setAppendPosition(strlen($textToBeAppended));
        $appendResult = $this->restProxy->appendBlock($name, 'myblob', $textToBeAppended, $appendBlockOption);
        self::assertNotNull($appendResult->getETag());
        self::assertIsBool($appendResult->getRequestServerEncrypted());
    }

    public function testAppendBlockConflictBecauseOfAppendPosition()
    {
        $this->expectException(\AzureOSS\Storage\Common\Exceptions\ServiceException::class);
        $this->expectExceptionMessage('412');

        // Setup
        $name = 'appendblockappendpositionconflict' . $this->createSuffix();
        $this->createContainer($name);
        $textToBeAppended = 'text to be appended';
        $appendBlockOption = new AppendBlockOptions();
        $appendBlockOption->setAppendPosition(1);

        // Test
        $this->restProxy->createAppendBlob($name, 'myblob');
        $this->restProxy->appendBlock($name, 'myblob', $textToBeAppended, $appendBlockOption);
    }

    public function testAppendBlockSuccessWithMaxBlobSize()
    {
        // Setup
        $name = 'appendblockmaxblobsizeconflict' . $this->createSuffix();
        $this->createContainer($name);
        $textToBeAppended = 'text to be appended';
        $appendBlockOption = new AppendBlockOptions();
        $appendBlockOption->setMaxBlobSize(1000);

        // Test
        $this->restProxy->createAppendBlob($name, 'myblob');
        $appendResult = $this->restProxy->appendBlock($name, 'myblob', $textToBeAppended, $appendBlockOption);
        self::assertNotNull($appendResult->getETag());
    }

    public function testAppendBlockConflictBecauseOfMaxBlobSize()
    {
        $this->expectException(\AzureOSS\Storage\Common\Exceptions\ServiceException::class);
        $this->expectExceptionMessage('412');

        // Setup
        $name = 'appendblockmaxblobsizeconflict' . $this->createSuffix();
        $this->createContainer($name);
        $textToBeAppended = 'text to be appended';
        $appendBlockOption = new AppendBlockOptions();
        $appendBlockOption->setMaxBlobSize(1);

        // Test
        $this->restProxy->createAppendBlob($name, 'myblob');
        $this->restProxy->appendBlock($name, 'myblob', $textToBeAppended, $appendBlockOption);
    }

    public function testCreatePageBlobWithExtraOptions()
    {
        // Setup
        $name = 'createpageblobwithextraoptions' . $this->createSuffix();
        $this->createContainer($name);
        $metadata = ['Name1' => 'Value1', 'Name2' => 'Value2'];
        $contentType = Resources::BINARY_FILE_TYPE;
        $options = new CreatePageBlobOptions();
        $options->setMetadata($metadata);
        $options->setContentType($contentType);

        // Test
        $createResult = $this->restProxy->createPageBlob($name, 'myblob', 512, $options);

        // Assert
        $result = $this->restProxy->listBlobs($name);
        self::assertCount(1, $result->getBlobs());
        self::assertIsBool($createResult->getRequestServerEncrypted());
    }

    public function testCreateBlockBlobWithBinary()
    {
        // Setup
        $name = 'createblockblobwithbinary' . $this->createSuffix();
        $this->createContainer($name);

        // Test
        $createResult = $this->restProxy->createBlockBlob($name, 'myblob', '123455');

        // Assert
        $result = $this->restProxy->listBlobs($name);
        $blobs = $result->getBlobs();
        $blob = $blobs[0];
        self::assertNotNull($createResult->getETag());
        self::assertInstanceOf('\DateTime', $createResult->getLastModified());
        self::assertCount(1, $result->getBlobs());
        self::assertEquals(Resources::BINARY_FILE_TYPE, $blob->getProperties()->getContentType());
        self::assertIsBool($createResult->getRequestServerEncrypted());
    }

    public function testCreateBlockBlobWithPlainText()
    {
        // Setup
        $name = 'createblockblobwithplaintext' . $this->createSuffix();
        $contentType = 'text/plain; charset=UTF-8';
        $this->createContainer($name);
        $options = new CreateBlockBlobOptions();
        $options->setContentType($contentType);

        // Test
        $createResult = $this->restProxy->createBlockBlob($name, 'myblob', 'Hello world', $options);

        // Assert
        $result = $this->restProxy->listBlobs($name);
        $blobs = $result->getBlobs();
        $blob = $blobs[0];
        self::assertCount(1, $result->getBlobs());
        self::assertEquals($contentType, $blob->getProperties()->getContentType());
        self::assertIsBool($createResult->getRequestServerEncrypted());
    }

    public function testCreateBlockBlobWithStream()
    {
        // Setup
        $name = 'createblockblobwithstream' . $this->createSuffix();
        $contentType = 'text/plain; charset=UTF-8';
        $this->createContainer($name);
        $options = new CreateBlockBlobOptions();
        $options->setContentType($contentType);
        $options->setUseTransactionalMD5(true);
        $fileContents = 'Hello world, I\'m a file';
        $stream = fopen(VirtualFileSystem::newFile($fileContents), 'r');

        // Test
        $createResult = $this->restProxy->createBlockBlob($name, 'myblob', $stream, $options);

        // Assert
        $result = $this->restProxy->listBlobs($name);
        $blobs = $result->getBlobs();
        $blob = $blobs[0];
        self::assertCount(1, $result->getBlobs());
        self::assertEquals($contentType, $blob->getProperties()->getContentType());
        self::assertIsBool($createResult->getRequestServerEncrypted());
    }

    public function testGetBlobProperties()
    {
        // Setup
        $name = 'getblobproperties' . $this->createSuffix();
        $contentLength = 512;
        $this->createContainer($name);
        $this->restProxy->createPageBlob($name, 'myblob', $contentLength);

        // Test
        $result = $this->restProxy->getBlobProperties($name, 'myblob');

        // Assert
        self::assertEquals($contentLength, $result->getProperties()->getContentLength());
        self::assertIsBool($result->getProperties()->getServerEncrypted());
    }

    public function testSetBlobProperties()
    {
        // Setup
        $name = 'setblobproperties' . $this->createSuffix();
        $contentLength = 1024;
        $blob = 'myblob';
        $this->createContainer($name);
        $this->restProxy->createPageBlob($name, 'myblob', 512);
        $options = new SetBlobPropertiesOptions();
        $options->setContentLength($contentLength);

        // Test
        $this->restProxy->setBlobProperties($name, $blob, $options);

        // Assert
        $result = $this->restProxy->getBlobProperties($name, $blob);
        self::assertEquals($contentLength, $result->getProperties()->getContentLength());
    }

    public function testSetBlobPropertiesWithNoOptions()
    {
        // Setup
        $name = 'setblobpropertieswithnooptions' . $this->createSuffix();
        $blob = 'myblob';
        $this->createContainer($name);
        $this->restProxy->createPageBlob($name, $blob, 512);

        // Test
        $result = $this->restProxy->setBlobProperties($name, $blob);

        // Assert
        self::assertInstanceOf('\DateTime', $result->getLastModified());
        self::assertTrue(null !== $result->getETag());
    }

    public function testGetBlobMetadata()
    {
        // Setup
        $name = 'getblobmetadata' . $this->createSuffix();
        $metadata = ['m1' => 'v1', 'm2' => 'v2'];
        $blob = 'myblob';
        $this->createContainer($name);
        $options = new CreatePageBlobOptions();
        $options->setMetadata($metadata);
        $this->restProxy->createPageBlob($name, $blob, 512, $options);

        // Test
        $result = $this->restProxy->getBlobMetadata($name, $blob);

        // Assert
        self::assertEquals($metadata, $result->getMetadata());
    }

    public function testSetBlobMetadata()
    {
        // Setup
        $name = 'setblobmetadata' . $this->createSuffix();
        $metadata = ['m1' => 'v1', 'm2' => 'v2'];
        $blob = 'myblob';
        $this->createContainer($name);
        $this->restProxy->createPageBlob($name, $blob, 512);

        // Test
        $setResult = $this->restProxy->setBlobMetadata($name, $blob, $metadata);

        // Assert
        $result = $this->restProxy->getBlobMetadata($name, $blob);
        self::assertEquals($metadata, $result->getMetadata());
        self::assertIsBool($setResult->getRequestServerEncrypted());
    }

    public function testGetBlob()
    {
        // Setup
        $name = 'getblob' . $this->createSuffix();
        $blob = 'myblob';
        $metadata = ['m1' => 'v1', 'm2' => 'v2'];
        $contentType = 'text/plain; charset=UTF-8';
        $contentStream = 'Hello world';
        $this->createContainer($name);
        $options = new CreateBlockBlobOptions();
        $options->setContentType($contentType);
        $options->setMetadata($metadata);
        $this->restProxy->createBlockBlob($name, $blob, $contentStream, $options);

        // Test
        $result = $this->restProxy->getBlob($name, $blob);

        // Assert
        self::assertEquals(BlobType::BLOCK_BLOB, $result->getProperties()->getBlobType());
        self::assertEquals($metadata, $result->getMetadata());
        self::assertIsBool($result->getProperties()->getServerEncrypted());
        self::assertEquals(
            $contentStream,
            stream_get_contents($result->getContentStream())
        );
    }

    public function testGetBlobNotExist()
    {
        $this->expectException(\AzureOSS\Storage\Common\Exceptions\ServiceException::class);
        $this->expectExceptionMessage('404');

        $name = 'notexistcontainer' . $this->createSuffix();
        $blob = 'notexistblob';

        $promise = $this->restProxy->getBlobAsync($name, $blob);

        $promise->wait();
    }

    public function testCreateContainerAsyncWithInvalidParameters()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('should be of type \'string\'');

        $this->restProxy->createContainerAsync([]);
    }

    public function testGetBlobWithRange()
    {
        // Setup
        $name = '$root';
        $blob = 'myblob';
        $this->createContainer($name);
        $this->_createdContainers[] = '$root';
        $length = 512;
        $range = new Range(0, 511);
        $contentStream = Resources::EMPTY_STRING;
        $this->restProxy->createPageBlob('', $blob, $length);
        for ($i = 0; $i < 512; ++$i) {
            $contentStream .= 'A';
        }
        $this->restProxy->createBlobPages('', $blob, $range, $contentStream);
        $options = new GetBlobOptions();
        $options->setRange(new Range(0, 511));

        // Test
        $result = $this->restProxy->getBlob('', $blob, $options);

        // Assert
        self::assertEquals(BlobType::PAGE_BLOB, $result->getProperties()->getBlobType());
        self::assertIsBool($result->getProperties()->getServerEncrypted());
        self::assertEquals(
            $contentStream,
            stream_get_contents($result->getContentStream())
        );
    }

    public function testGetBlobWithEndRange()
    {
        // Setup
        $name = 'getblobwithendrange' . $this->createSuffix();
        $blob = 'myblob';
        $this->createContainer($name);
        $length = 512;
        $range = new Range(0, 511);
        $contentStream = Resources::EMPTY_STRING;
        $this->restProxy->createPageBlob($name, $blob, $length);
        for ($i = 0; $i < 512; ++$i) {
            $contentStream .= 'A';
        }
        $this->restProxy->createBlobPages($name, $blob, $range, $contentStream);
        $options = new GetBlobOptions();
        $options->setRange(new Range(null, 511));

        // Test
        $result = $this->restProxy->getBlob($name, $blob, $options);

        // Assert
        self::assertEquals(BlobType::PAGE_BLOB, $result->getProperties()->getBlobType());
        self::assertIsBool($result->getProperties()->getServerEncrypted());
        self::assertEquals(
            $contentStream,
            stream_get_contents($result->getContentStream())
        );
    }

    public function testGetBlobGarbage()
    {
        // Setup
        $name = 'getblobwithgarbage' . $this->createSuffix();
        $blob = 'myblob';
        $metadata = ['m1' => 'v1', 'm2' => 'v2'];
        $contentType = 'text/plain; charset=UTF-8';
        $contentStream = chr(0);
        $this->createContainer($name);
        $options = new CreateBlockBlobOptions();
        $options->setContentType($contentType);
        $options->setMetadata($metadata);
        $this->restProxy->createBlockBlob($name, $blob, $contentStream, $options);

        // Test
        $result = $this->restProxy->getBlob($name, $blob);

        // Assert
        self::assertEquals(BlobType::BLOCK_BLOB, $result->getProperties()->getBlobType());
        self::assertEquals($metadata, $result->getMetadata());
        self::assertEquals(
            $contentStream,
            stream_get_contents($result->getContentStream())
        );
    }

    public function testUndeleteBlob()
    {
        // If this test case fails, please ensure that soft delete is enabled
        // for this storage account!

        // Setup
        $name = 'undeleteblob' . $this->createSuffix();
        $blob = 'myblob';
        $contentType = 'text/plain; charset=UTF-8';
        $this->createContainer($name);
        $options = new CreateBlockBlobOptions();
        $options->setContentType($contentType);
        $this->restProxy->createBlockBlob($name, $blob, 'Hello world', $options);

        // Delete blob
        $this->restProxy->deleteBlob($name, $blob);

        // Assert
        $result = $this->restProxy->listBlobs($name);
        self::assertCount(0, $result->getBlobs());

        // Undelete blob
        $this->restProxy->undeleteBlob($name, $blob);

        // Assert
        $result = $this->restProxy->listBlobs($name);
        self::assertCount(1, $result->getBlobs());
    }

    public function testDeleteBlob()
    {
        // Setup
        $name = 'deleteblob' . $this->createSuffix();
        $blob = 'myblob';
        $contentType = 'text/plain; charset=UTF-8';
        $this->createContainer($name);
        $options = new CreateBlockBlobOptions();
        $options->setContentType($contentType);
        $this->restProxy->createBlockBlob($name, $blob, 'Hello world', $options);

        // Test
        $this->restProxy->deleteBlob($name, $blob);

        // Assert
        $result = $this->restProxy->listBlobs($name);
        self::assertCount(0, $result->getBlobs());
    }

    public function testDeleteBlobSnapshot()
    {
        // Setup
        $name = 'deleteblobsnapshot' . $this->createSuffix();
        $blob = 'myblob';
        $contentType = 'text/plain; charset=UTF-8';
        $this->createContainer($name);
        $options = new CreateBlockBlobOptions();
        $options->setContentType($contentType);
        $this->restProxy->createBlockBlob($name, $blob, 'Hello world', $options);
        $snapshot = $this->restProxy->createBlobSnapshot($name, $blob);
        $options = new DeleteBlobOptions();
        $options->setSnapshot($snapshot->getSnapshot());

        // Test
        $this->restProxy->deleteBlob($name, $blob, $options);

        // Assert
        $listOptions = new ListBlobsOptions();
        $listOptions->setIncludeSnapshots(true);
        $blobsResult = $this->restProxy->listBlobs($name, $listOptions);
        $blobs = $blobsResult->getBlobs();
        $actualBlob = $blobs[0];
        self::assertNull($actualBlob->getSnapshot());
    }

    public function testDeleteBlobSnapshotsOnly()
    {
        // Setup
        $name = 'deleteblobsnapshotsonly' . $this->createSuffix();
        $blob = 'myblob';
        $contentType = 'text/plain; charset=UTF-8';
        $this->createContainer($name);
        $options = new CreateBlockBlobOptions();
        $options->setContentType($contentType);
        $this->restProxy->createBlockBlob($name, $blob, 'Hello world', $options);
        $this->restProxy->createBlobSnapshot($name, $blob);
        $options = new DeleteBlobOptions();
        $options->setDeleteSnaphotsOnly(true);

        // Test
        $this->restProxy->deleteBlob($name, $blob, $options);

        // Assert
        $listOptions = new ListBlobsOptions();
        $listOptions->setIncludeSnapshots(true);
        $blobsResult = $this->restProxy->listBlobs($name, $listOptions);
        $blobs = $blobsResult->getBlobs();
        $actualBlob = $blobs[0];
        self::assertNull($actualBlob->getSnapshot());
    }

    public function testAcquireLease()
    {
        // Setup
        $name = 'acquirelease' . $this->createSuffix();
        $blob = 'myblob';
        $contentType = 'text/plain; charset=UTF-8';
        $this->createContainer($name);
        $options = new CreateBlockBlobOptions();
        $options->setContentType($contentType);
        $this->restProxy->createBlockBlob($name, $blob, 'Hello world', $options);

        // Test
        $proposedLeaseId = '6c75960f-2837-4c35-9948-e35e87d00edf';
        $result = $this->restProxy->acquireLease($name, $blob, $proposedLeaseId, 20);

        // Assert
        self::assertEquals($proposedLeaseId, $result->getLeaseId());
    }

    public function testAcquireContainerLease()
    {
        // Setup
        $name = 'acquirelease' . $this->createSuffix();
        $blob = 'myblob';
        $contentType = 'text/plain; charset=UTF-8';
        $this->createContainer($name);
        $options = new CreateBlockBlobOptions();
        $options->setContentType($contentType);
        $this->restProxy->createBlockBlob($name, $blob, 'Hello world', $options);

        // Test
        $proposedLeaseId = '47809df9-8f4a-4243-828b-56243e702a04';
        $result = $this->restProxy->acquireLease($name, null, $proposedLeaseId);

        // Assert
        self::assertEquals($proposedLeaseId, $result->getLeaseId());

        // Break the lease so that the clean-up can delete the container
        $result = $this->restProxy->breakLease($name, null, null);
    }

    public function testChangeLease()
    {
        // Setup
        $name = 'changelease' . $this->createSuffix();
        $blob = 'myblob';
        $contentType = 'text/plain; charset=UTF-8';
        $this->createContainer($name);
        $options = new CreateBlockBlobOptions();
        $options->setContentType($contentType);
        $this->restProxy->createBlockBlob($name, $blob, 'Hello world', $options);

        // Test
        $result = $this->restProxy->acquireLease($name, $blob);

        $proposedLeaseId = '6c75960f-2837-4c35-9948-e35e87d00edf';
        $result = $this->restProxy->changeLease($name, $blob, $result->getLeaseId(), $proposedLeaseId);

        // Assert
        self::assertEquals($proposedLeaseId, $result->getLeaseId());
    }

    public function testRenewLease()
    {
        // Setup
        $name = 'renewlease' . $this->createSuffix();
        $blob = 'myblob';
        $contentType = 'text/plain; charset=UTF-8';
        $this->createContainer($name);
        $options = new CreateBlockBlobOptions();
        $options->setContentType($contentType);
        $this->restProxy->createBlockBlob($name, $blob, 'Hello world', $options);
        $result = $this->restProxy->acquireLease($name, $blob);

        // Test
        $result = $this->restProxy->renewLease($name, $blob, $result->getLeaseId());

        // Assert
        self::assertNotNull($result->getLeaseId());
    }

    public function testReleaseLease()
    {
        // Setup
        $name = 'releaselease' . $this->createSuffix();
        $blob = 'myblob';
        $contentType = 'text/plain; charset=UTF-8';
        $this->createContainer($name);
        $options = new CreateBlockBlobOptions();
        $options->setContentType($contentType);
        $this->restProxy->createBlockBlob($name, $blob, 'Hello world', $options);
        $result = $this->restProxy->acquireLease($name, $blob);

        // Test
        $this->restProxy->releaseLease($name, $blob, $result->getLeaseId());

        // Assert
        $result = $this->restProxy->acquireLease($name, $blob);
        self::assertNotNull($result->getLeaseId());
    }

    public function testBreakLease()
    {
        // Setup
        $name = 'breaklease' . $this->createSuffix();
        $blob = 'myblob';
        $contentType = 'text/plain; charset=UTF-8';
        $this->createContainer($name);
        $options = new CreateBlockBlobOptions();
        $options->setContentType($contentType);
        $this->restProxy->createBlockBlob($name, $blob, 'Hello world', $options);
        $this->restProxy->acquireLease($name, $blob);

        // Test
        $result = $this->restProxy->breakLease($name, $blob, 10);

        // Assert
        self::assertInstanceOf('AzureOSS\Storage\Blob\Models\BreakLeaseResult', $result);
        self::assertNotNull($result->getLeaseTime());
    }

    public function testCreateBlobPages()
    {
        // Setup
        $name = 'createblobpages' . $this->createSuffix();
        $blob = 'myblob';
        $length = 512;
        $range = new Range(0, 511);
        $content = Resources::EMPTY_STRING;
        $this->createContainer($name);
        $this->restProxy->createPageBlob($name, $blob, $length);
        for ($i = 0; $i < 512; ++$i) {
            $content .= 'A';
        }

        // Test
        $actual = $this->restProxy->createBlobPages($name, $blob, $range, $content);

        // Assert
        self::assertNotNull($actual->getETag());
        self::assertIsBool($actual->getRequestServerEncrypted());
    }

    public function testClearBlobPages()
    {
        // Setup
        $name = 'clearblobpages' . $this->createSuffix();
        $blob = 'myblob';
        $length = 512;
        $range = new Range(0, 511);
        $content = Resources::EMPTY_STRING;
        $this->createContainer($name);
        $this->restProxy->createPageBlob($name, $blob, $length);
        for ($i = 0; $i < 512; ++$i) {
            $content .= 'A';
        }
        $this->restProxy->createBlobPages($name, $blob, $range, $content);

        // Test
        $actual = $this->restProxy->clearBlobPages($name, $blob, $range);

        // Assert
        self::assertNotNull($actual->getETag());
        self::assertNull($actual->getRequestServerEncrypted());
    }

    public function testListPageBlobRanges()
    {
        // Setup
        $name = 'listpageblobranges' . $this->createSuffix();
        $blob = 'myblob';
        $length = 512;
        $range = new Range(0, 511);
        $content = Resources::EMPTY_STRING;
        $this->createContainer($name);
        $this->restProxy->createPageBlob($name, $blob, $length);
        for ($i = 0; $i < 512; ++$i) {
            $content .= 'A';
        }
        $this->restProxy->createBlobPages($name, $blob, $range, $content);

        // Test
        $result = $this->restProxy->listPageBlobRanges($name, $blob);

        // Assert
        self::assertNotNull($result->getETag());
        self::assertCount(1, $result->getRanges());
    }

    public function testListPageBlobRangesDiff()
    {
        // Setup
        $name = 'listpageblobranges' . $this->createSuffix();
        $blob = 'myblob';
        $length = 512 * 8;
        $range = new Range(0, $length - 1);
        $content = Resources::EMPTY_STRING;
        $this->createContainer($name);
        $this->restProxy->createPageBlob($name, $blob, $length);

        // Create snapshot for original page blob
        for ($i = 0; $i < $length; ++$i) {
            $content .= 'A';
        }
        $this->restProxy->createBlobPages($name, $blob, $range, $content);
        $snapshotResult = $this->restProxy->createBlobSnapshot($name, $blob);

        // Clear range 0->511
        $clearRange = new Range(0, 511);
        $this->restProxy->clearBlobPages($name, $blob, $clearRange);

        // Update range 512->1023
        $updateRange = new Range(512, 1023);
        $updateContent = Resources::EMPTY_STRING;
        for ($i = 0; $i < 512; ++$i) {
            $updateContent .= 'B';
        }
        $this->restProxy->createBlobPages($name, $blob, $updateRange, $updateContent);

        // Clear range 1024->1535
        $clearRange = new Range(1024, 1535);
        $this->restProxy->clearBlobPages($name, $blob, $clearRange);

        $exceptedRangesDiff = [
            new RangeDiff(512, 1023, false),
            new RangeDiff(0, 511, true),
            new RangeDiff(1024, 1535, true),
        ];

        // Test
        $result = $this->restProxy->listPageBlobRangesDiff($name, $blob, $snapshotResult->getSnapshot());

        // Assert
        self::assertNotNull($result->getETag());
        self::assertCount(3, $result->getRanges());
        self::assertEquals($exceptedRangesDiff, $result->getRanges());
    }

    public function testListPageBlobRangesEmpty()
    {
        // Setup
        $name = 'listpageblobrangesempty' . $this->createSuffix();
        $blob = 'myblob';
        $length = 512;
        $this->createContainer($name);
        $this->restProxy->createPageBlob($name, $blob, $length);

        // Test
        $result = $this->restProxy->listPageBlobRanges($name, $blob);

        // Assert
        self::assertNotNull($result->getETag());
        self::assertCount(0, $result->getRanges());
    }

    public function testCreateBlobBlock()
    {
        // Setup
        $name = 'createblobblock' . $this->createSuffix();
        $this->createContainer($name);
        $options = new ListBlobsOptions();
        $options->setIncludeUncommittedBlobs(true);

        // Test
        $createResult = $this->restProxy->createBlobBlock($name, 'myblob', 'AAAAAA==', 'Hello world');

        // Assert
        $result = $this->restProxy->listBlobs($name, $options);
        self::assertCount(1, $result->getBlobs());
        self::assertIsBool($createResult->getRequestServerEncrypted());
    }

    public function testCommitBlobBlocks()
    {
        // Setup
        $name = 'commitblobblocks' . $this->createSuffix();
        $blob = 'myblob';
        $id1 = 'AAAAAA==';
        $id2 = 'ANAAAA==';
        $this->createContainer($name);
        $this->restProxy->createBlobBlock($name, $blob, $id1, 'Hello world');
        $this->restProxy->createBlobBlock($name, $blob, $id2, 'Hello world');
        $blockList = new BlockList();

        $blockList->addEntry($id1, BlobBlockType::LATEST_TYPE);
        $blockList->addEntry($id2, BlobBlockType::LATEST_TYPE);

        // Test
        $commitResult = $this->restProxy->commitBlobBlocks($name, $blob, $blockList);

        // Assert
        $result = $this->restProxy->listBlobs($name);
        self::assertCount(1, $result->getBlobs());
        self::assertIsBool($commitResult->getRequestServerEncrypted());
    }

    public function testCommitBlobBlocksWithArray()
    {
        // Setup
        $name = 'commitblobblockswitharray' . $this->createSuffix();
        $blob = 'myblob';
        $id1 = 'AAAAAA==';
        $id2 = 'ANAAAA==';
        $block1 = new Block();
        $block1->setBlockId($id1);
        $block1->setType(BlobBlockType::LATEST_TYPE);
        $block2 = new Block();
        $block2->setBlockId($id2);
        $block2->setType(BlobBlockType::LATEST_TYPE);
        $blockList = [$block1, $block2];
        $this->createContainer($name);
        $this->restProxy->createBlobBlock($name, $blob, $id1, 'Hello world');
        $this->restProxy->createBlobBlock($name, $blob, $id2, 'Hello world');

        // Test
        $commitResult = $this->restProxy->commitBlobBlocks($name, $blob, $blockList);

        // Assert
        $result = $this->restProxy->listBlobs($name);
        self::assertCount(1, $result->getBlobs());
        self::assertIsBool($commitResult->getRequestServerEncrypted());
    }

    public function testListBlobBlocks()
    {
        // Setup
        $name = 'listblobblocks' . $this->createSuffix();
        $blob = 'myblob';
        $id1 = 'AAAAAA==';
        $id2 = 'ANAAAA==';
        $this->createContainer($name);
        $this->restProxy->createBlobBlock($name, $blob, $id1, 'Hello world');
        $this->restProxy->createBlobBlock($name, $blob, $id2, 'Hello world');

        // Test
        $result = $this->restProxy->listBlobBlocks($name, $blob);

        // Assert
        self::assertNull($result->getETag());
        self::assertEquals(0, $result->getContentLength());
        self::assertCount(2, $result->getUncommittedBlocks());
        self::assertCount(0, $result->getCommittedBlocks());
    }

    public function testListBlobBlocksEmpty()
    {
        // Setup
        $name = 'listblobblocksempty' . $this->createSuffix();
        $blob = 'myblob';
        $content = 'Hello world';
        $this->createContainer($name);
        $this->restProxy->createBlockBlob($name, $blob, $content);

        // Test
        $result = $this->restProxy->listBlobBlocks($name, $blob);

        // Assert
        self::assertNotNull($result->getETag());
        self::assertEquals(strlen($content), $result->getContentLength());
        self::assertCount(0, $result->getUncommittedBlocks());
        self::assertCount(0, $result->getCommittedBlocks());
    }

    public function testCopyBlobDifferentContainer()
    {
        // Setup
        $sourceContainerName = 'copyblobdiffcontainerssource' . $this->createSuffix();
        $sourceBlobName = 'sourceblob';
        $blobValue = 'testBlobValue';
        $destinationContainerName = 'copyblobdiffcontainersdestination' . $this->createSuffix();
        $destinationBlobName = 'destinationblob';
        $this->createContainer($sourceContainerName);
        $this->createContainer($destinationContainerName);
        $this->restProxy->createBlockBlob(
            $sourceContainerName,
            $sourceBlobName,
            $blobValue
        );

        // Test
        $result = $this->restProxy->copyBlob(
            $destinationContainerName,
            $destinationBlobName,
            $sourceContainerName,
            $sourceBlobName
        );
        $copyId = $result->getCopyId();
        $copyStatus = $result->getCopyStatus();

        // Assert
        self::assertNotNull($copyId);
        self::assertNotNull($copyStatus);

        $sourceBlob = $this->restProxy->getBlob($sourceContainerName, $sourceBlobName);
        $destinationBlob = $this->restProxy->getBlob($destinationContainerName, $destinationBlobName);
        $sourceBlobContent = stream_get_contents($sourceBlob->getContentStream());
        $destinationBlobContent =
            stream_get_contents($destinationBlob->getContentStream());

        self::assertEquals($sourceBlobContent, $destinationBlobContent);
        self::assertNotNull($result->getETag());
        self::assertInstanceOf('\DateTime', $result->getlastModified());

        $destinationBlobProperties =
            $this->restProxy->getBlobProperties($destinationContainerName, $destinationBlobName);
        $copyState = $destinationBlobProperties->getProperties()->getCopyState();

        self::assertNotNull($copyState);
        self::assertNotNull($copyState->getCopyId());
        self::assertNotNull($copyState->getCompletionTime());
        self::assertNotNull($copyState->getStatus());
        self::assertNotNull($copyState->getSource());
        self::assertNotNull($copyState->getBytesCopied());
        self::assertNotNull($copyState->getTotalBytes());

        $listBlobsOptions = new ListBlobsOptions();
        $listBlobsOptions->setIncludeCopy(true);
        $listedDestinationBlobs = $this->restProxy->listBlobs($destinationContainerName, $listBlobsOptions);

        $destBlob = $listedDestinationBlobs->getBlobs()[0];
        $copyState = $destBlob->getProperties()->getCopyState();

        self::assertNotNull($copyState);
        self::assertNotNull($copyState->getCopyId());
        self::assertNotNull($copyState->getCompletionTime());
        self::assertNotNull($copyState->getStatus());
        self::assertNotNull($copyState->getSource());
        self::assertNotNull($copyState->getBytesCopied());
        self::assertNotNull($copyState->getTotalBytes());

        try {
            $this->restProxy->abortCopy($destinationContainerName, $destinationBlobName, $copyId);
        } catch (ServiceException $e) {
            self::assertEquals(409, $e->getCode());
            self::assertStringContainsString('There is currently no pending copy operation.', $e->getErrorText());
        }
    }

    public function testCopyBlobSameContainer()
    {
        // Setup
        $containerName = 'copyblobsamecontainer' . $this->createSuffix();
        $sourceBlobName = 'sourceblob';
        $blobValue = 'testBlobValue';
        $destinationBlobName = 'destinationblob';
        $this->createContainer($containerName);
        $this->restProxy->createBlockBlob(
            $containerName,
            $sourceBlobName,
            $blobValue
        );

        // Test
        $this->restProxy->copyBlob(
            $containerName,
            $destinationBlobName,
            $containerName,
            $sourceBlobName
        );

        // Assert
        $sourceBlob = $this->restProxy->getBlob($containerName, $sourceBlobName);
        $destinationBlob = $this->restProxy->getBlob(
            $containerName,
            $destinationBlobName
        );

        $sourceBlobContent =
            stream_get_contents($sourceBlob->getContentStream());
        $destinationBlobContent =
            stream_get_contents($destinationBlob->getContentStream());
        self::assertEquals($sourceBlobContent, $destinationBlobContent);
    }

    public function testCopyBlobExistingBlob()
    {
        // Setup
        $containerName = 'copyblobexistingblob' . $this->createSuffix();
        $sourceBlobName = 'sourceblob';
        $blobValue = 'testBlobValue';
        $oldBlobValue = 'oldBlobValue';
        $destinationBlobName = 'destinationblob';
        $this->createContainer($containerName);
        $this->restProxy->createBlockBlob(
            $containerName,
            $sourceBlobName,
            $blobValue
        );
        $this->restProxy->createBlockBlob(
            $containerName,
            $destinationBlobName,
            $oldBlobValue
        );

        // Test
        $this->restProxy->copyBlob(
            $containerName,
            $destinationBlobName,
            $containerName,
            $sourceBlobName
        );

        // Assert
        $sourceBlob = $this->restProxy->getBlob($containerName, $sourceBlobName);
        $destinationBlob = $this->restProxy->getBlob($containerName, $destinationBlobName);
        $sourceBlobContent = stream_get_contents($sourceBlob->getContentStream());
        $destinationBlobContent =
            stream_get_contents($destinationBlob->getContentStream());

        self::assertEquals($sourceBlobContent, $destinationBlobContent);
        self::assertNotEquals($destinationBlobContent, $oldBlobValue);
    }

    public function testCopyBlobSnapshot()
    {
        // Setup
        $containerName = 'copyblobsnapshot' . $this->createSuffix();
        $sourceBlobName = 'sourceblob';
        $blobValue = 'testBlobValue';
        $destinationBlobName = 'destinationblob';
        $this->createContainer($containerName);
        $this->restProxy->createBlockBlob($containerName, $sourceBlobName, $blobValue);
        $snapshotResult = $this->restProxy->createBlobSnapshot($containerName, $sourceBlobName);
        $options = new CopyBlobOptions();
        $options->setSourceSnapshot($snapshotResult->getSnapshot());

        // Test
        $this->restProxy->copyBlob(
            $containerName,
            $destinationBlobName,
            $containerName,
            $sourceBlobName,
            $options
        );

        // Assert
        $sourceBlob = $this->restProxy->getBlob($containerName, $sourceBlobName);
        $destinationBlob = $this->restProxy->getBlob($containerName, $destinationBlobName);
        $sourceBlobContent = stream_get_contents($sourceBlob->getContentStream());
        $destinationBlobContent =
            stream_get_contents($destinationBlob->getContentStream());

        self::assertEquals($sourceBlobContent, $destinationBlobContent);
    }

    public function testCopyBlobIncremental()
    {
        // Setup
        $sourceContainerName = 'copyblobincrementalsource' . $this->createSuffix();
        $sourceBlobName = 'sourceblob';
        $sourceContentLength = 512 * 8;
        $sourceBlobContent = openssl_random_pseudo_bytes($sourceContentLength);

        $destinationContainerName = 'copyblobincrementaldest' . $this->createSuffix();
        $destinationBlobName = 'destinationblob';

        $this->createContainer($sourceContainerName);
        $this->createContainer($destinationContainerName);

        $options = new CreatePageBlobFromContentOptions();
        $options->setUseTransactionalMD5(true);

        $this->restProxy->createPageBlobFromContent(
            $sourceContainerName,
            $sourceBlobName,
            $sourceContentLength,
            $sourceBlobContent,
            $options
        );

        $sourceSnapshotResult = $this->restProxy->createBlobSnapshot(
            $sourceContainerName,
            $sourceBlobName
        );

        // Test
        $options = new CopyBlobOptions();
        $options->setSourceSnapshot($sourceSnapshotResult->getSnapshot());
        $options->setIsIncrementalCopy(true);

        $this->restProxy->copyBlob(
            $destinationContainerName,
            $destinationBlobName,
            $sourceContainerName,
            $sourceBlobName,
            $options
        );

        // Wait several seconds until copying ends
        sleep(20);

        // Assert
        $sourceBlob = $this->restProxy->getBlob($sourceContainerName, $sourceBlobName);

        $options = new ListBlobsOptions();
        $options->setIncludeSnapshots(true);
        $listDestContainerResult = $this->restProxy->listBlobs(
            $destinationContainerName,
            $options
        );

        // List destination blobs, including one incremental blob and one incremental blob snapshot
        self::assertCount(
            2,
            $listDestContainerResult->getBlobs()
        );
        foreach ($listDestContainerResult->getBlobs() as $blob) {
            self::assertTrue(
                $blob->getProperties()->getIncrementalCopy()
            );

            if ($blob->getSnapshot()) {
                $destBlobSnapshot = $blob;
            } else {
                $destBlob = $blob;
            }
        }

        // Validate properties of incremental blob and snapshots
        $destBlobProperties = $this->restProxy->getBlobProperties(
            $destinationContainerName,
            $destinationBlobName
        )->getProperties();

        $options = new GetBlobPropertiesOptions();
        $options->setSnapshot($destBlobSnapshot->getSnapshot());
        $destBlobSnapshotProperties = $this->restProxy->getBlobProperties(
            $destinationContainerName,
            $destinationBlobName,
            $options
        )->getProperties();

        self::assertTrue($destBlobProperties->getIncrementalCopy());
        self::assertEquals(
            $destBlobSnapshot->getSnapshot(),
            $destBlobProperties->getCopyDestinationSnapshot()
        );

        self::assertTrue($destBlobSnapshotProperties->getIncrementalCopy());
        self::assertEquals(
            $destBlobSnapshot->getSnapshot(),
            $destBlobSnapshotProperties->getCopyDestinationSnapshot()
        );

        // Validate incremental blob snapshot content
        $options = new GetBlobOptions();
        $options->setSnapshot($destBlobProperties->getCopyDestinationSnapshot());
        $destinationBlobSnapshot = $this->restProxy->getBlob(
            $destinationContainerName,
            $destinationBlobName,
            $options
        );

        $sourceBlobContent = stream_get_contents($sourceBlob->getContentStream());
        $destinationBlobContent =
            stream_get_contents($destinationBlobSnapshot->getContentStream());

        self::assertEquals($sourceBlobContent, $destinationBlobContent);
    }

    public function testCreateBlobSnapshot()
    {
        // Setup
        $containerName = 'createblobsnapshot' . $this->createSuffix();
        $blobName = 'testBlob';
        $blobValue = 'TestBlobValue';
        $this->createContainer($containerName);
        $this->restProxy->createBlockBlob($containerName, $blobName, $blobValue);

        // Test
        $snapshotResult = $this->restProxy->createBlobSnapshot($containerName, $blobName);

        // Assert
        $listOptions = new ListBlobsOptions();
        $listOptions->setIncludeSnapshots(true);
        $blobsResult = $this->restProxy->listBlobs($containerName, $listOptions);
        $blobs = $blobsResult->getBlobs();
        $actualBlob = $blobs[0];
        self::assertNotNull($snapshotResult->getETag());
        self::assertNotNull($snapshotResult->getLastModified());
        self::assertNotNull($snapshotResult->getSnapshot());
        self::assertEquals($snapshotResult->getSnapshot(), $actualBlob->getSnapshot());
    }

    public function testSingleBlobUploadZeroBytes()
    {
        // Bug reported for zero byte upload similar to unix touch command failing
        $name = 'createemptyblob' . $this->createSuffix();
        $blob = 'EmptyFile';
        $this->createContainer($name);
        $contentType = 'text/plain; charset=UTF-8';
        $content = '';
        $options = new CreateBlockBlobOptions();
        $options->setContentType($contentType);
        $this->restProxy->createBlockBlob($name, $blob, $content, $options);

        // Now see if we can pick the file back up.
        $result = $this->restProxy->getBlob($name, $blob);

        // Assert
        self::assertEquals($content, stream_get_contents($result->getContentStream()));
    }

    public function testSingleBlobUploadThresholdInBytes()
    {
        // Values based on http://msdn.microsoft.com/en-us/library/microsoft.windowsazure.storageclient.cloudblobclient.singleblobuploadthresholdinbytes.aspx
        // Read initial value
        self::assertEquals($this->restProxy->getSingleBlobUploadThresholdInBytes(), Resources::MB_IN_BYTES_32);

        // Change value
        $this->restProxy->setSingleBlobUploadThresholdInBytes(50);
        self::assertEquals($this->restProxy->getSingleBlobUploadThresholdInBytes(), 50);

        // Test over limit
        $this->restProxy->setSingleBlobUploadThresholdInBytes(257 * 1024 * 1024);
        // Should be truncated to 256MB
        self::assertEquals($this->restProxy->getSingleBlobUploadThresholdInBytes(), Resources::MB_IN_BYTES_256);

        // Under limit
        $this->restProxy->setSingleBlobUploadThresholdInBytes(-50);
        // value can not be less than 1, so reset to default value
        self::assertEquals($this->restProxy->getSingleBlobUploadThresholdInBytes(), Resources::MB_IN_BYTES_32);

        $this->restProxy->setSingleBlobUploadThresholdInBytes(0);
        // value can not be less than 1, so reset to default value
        self::assertEquals($this->restProxy->getSingleBlobUploadThresholdInBytes(), Resources::MB_IN_BYTES_32);
    }

    public function testCreateBlobLargerThanSingleBlock()
    {
        // First step, lets set the value for automatic splitting to something very small
        $max_size = 50;
        $this->restProxy->setSingleBlobUploadThresholdInBytes($max_size);
        self::assertEquals($this->restProxy->getSingleBlobUploadThresholdInBytes(), $max_size);
        $name = 'createbloblargerthansingleblock' . $this->createSuffix();
        $this->createContainer($name);
        $contentType = 'text/plain; charset=UTF-8';
        $content = 'This is a really long section of text needed for this test.';
        // Note this grows fast, each loop doubles the last run. Do not make too big
        // This results in a 1888 byte string, divided by 50 results in 38 blocks
        for ($i = 0; $i < 5; ++$i) {
            $content .= $content;
        }
        $options = new CreateBlockBlobOptions();
        $options->setContentType($contentType);
        $options->setUseTransactionalMD5(true);
        $this->restProxy->createBlockBlob($name, 'little_split', $content, $options);

        // Block specific
        $boptions = new ListBlobBlocksOptions();
        $boptions->setIncludeUncommittedBlobs(true);
        $boptions->setIncludeCommittedBlobs(true);
        $result = $this->restProxy->listBlobBlocks($name, 'little_split', $boptions);
        $blocks = $result->getUnCommittedBlocks();
        self::assertEquals(count($blocks), 0);
        $blocks = $result->getCommittedBlocks();
        self::assertEquals(count($blocks), \ceil(strlen($content) / $max_size));

        // Setting back to default value for one shot test
        $this->restProxy->setSingleBlobUploadThresholdInBytes(0);
        $this->restProxy->createBlockBlob($name, 'oneshot', $content, $options);
        $result = $this->restProxy->listBlobBlocks($name, 'oneshot', $boptions);
        $blocks = $result->getUnCommittedBlocks();
        self::assertEquals(count($blocks), 0);
        $blocks = $result->getCommittedBlocks();
        // this one doesn't make sense. On emulator, there is no block created,
        // so relying on content size to be final approval
        self::assertEquals(count($blocks), 0);
        self::assertEquals($result->getContentLength(), strlen($content));
    }

    public function testGetBlockBlobToFile()
    {
        // Setup
        $name = 'getblob' . $this->createSuffix();
        $blob = 'myblob';
        $metadata = ['m1' => 'v1', 'm2' => 'v2'];
        $contentType = 'text/plain; charset=UTF-8';
        $contentStream = 'Hello world';
        $this->createContainer($name);
        $options = new CreateBlockBlobOptions();
        $options->setContentType($contentType);
        $options->setMetadata($metadata);
        $this->restProxy->createBlockBlob(
            $name,
            $blob,
            $contentStream,
            $options
        );

        //get current working directory for the path to download
        $cwd = getcwd();
        $uuid = uniqid('test-file-', true);
        $path = $cwd . DIRECTORY_SEPARATOR . $uuid . '.txt';

        // Test
        $result = $this->restProxy->saveBlobToFile($path, $name, $blob);
        $contents = file_get_contents($path);

        // Assert
        self::assertEquals(BlobType::BLOCK_BLOB, $result->getProperties()->getBlobType());
        self::assertEquals($metadata, $result->getMetadata());
        self::assertEquals($contentStream, $contents);
        self::assertIsBool($result->getProperties()->getServerEncrypted());

        // Delete file after assertion.
        unlink($path);
    }

    public function testGetPageBlobToFile()
    {
        // Setup
        $name = 'createblobpages' . $this->createSuffix();
        $blob = 'myblob';
        $length = 512;
        $range = new Range(0, 511);
        $content = Resources::EMPTY_STRING;
        $this->createContainer($name);
        $this->restProxy->createPageBlob($name, $blob, $length);
        for ($i = 0; $i < 512; ++$i) {
            $content .= 'A';
        }

        $actual = $this->restProxy->createBlobPages($name, $blob, $range, $content);
        //get current working directory for the path to download
        $cwd = getcwd();
        $uuid = uniqid('test-file-', true);
        $path = $cwd . DIRECTORY_SEPARATOR . $uuid . '.txt';

        // Test
        $result = $this->restProxy->saveBlobToFile($path, $name, $blob);
        $contents = file_get_contents($path);

        // Assert
        self::assertEquals(
            BlobType::PAGE_BLOB,
            $result->getProperties()->getBlobType()
        );
        self::assertIsBool($result->getProperties()->getServerEncrypted());
        self::assertEquals($content, $contents);
        unlink($path);
    }

    public function testRangeCreationWithInvalidRange()
    {
        $errorMsg = '';
        //upload the blob
        $name = 'createblobpages' . $this->createSuffix();
        $blob = 'myblob';
        $length = 512;
        $this->createContainer($name);
        $this->restProxy->createPageBlob($name, $blob, $length);
        //upload the blob
        $range = new Range(0, 255);
        $body = openssl_random_pseudo_bytes(256);
        try {
            $actual = $this->restProxy->createBlobPages(
                $name,
                $blob,
                $range,
                $body
            );
        } catch (\RuntimeException $e) {
            $errorMsg = $e->getMessage();
        }
        self::assertEquals($errorMsg, BlobResources::ERROR_RANGE_NOT_ALIGN_TO_512);
    }

    public function testsaveBlobToFileWithInvalidPath()
    {
        $errorMsg = '';
        //Create a random string that is 8MB in size.
        $contentStr = openssl_random_pseudo_bytes(Resources::MB_IN_BYTES_4 * 2);
        //upload the blob
        $name = 'getblob' . $this->createSuffix();
        $blob = 'myblob';
        $metadata = ['m1' => 'v1', 'm2' => 'v2'];
        $contentType = 'text/plain; charset=UTF-8';
        $this->createContainer($name);
        $options = new CreateBlockBlobOptions();
        $options->setContentType($contentType);
        $options->setMetadata($metadata);
        $this->restProxy->createBlockBlob(
            $name,
            $blob,
            $contentStr,
            $options
        );
        // Test
        //get the path for the file to be downloaded into.
        $uuid = uniqid('test-file-', true);
        $downloadPath = '\0/0@$%@!Zasdf:\\\\\\\\Invalid.PATH' . $uuid . '.txt';
        error_reporting(E_ALL ^ E_WARNING);
        try {
            $result = $this->restProxy->saveBlobToFile($downloadPath, $name, $blob);
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
        } finally {
            error_reporting(E_ALL);
        }
        self::assertEquals($errorMsg, Resources::ERROR_FILE_COULD_NOT_BE_OPENED);
    }

    public function testsaveBlobToFileWithBlobNotExist()
    {
        $errorMsg = '';
        $name = 'getblob' . $this->createSuffix();
        $blob = 'non_existing_blob';
        $this->createContainer($name);
        //get the path for the file to be downloaded into.
        $uuid = uniqid('test-file-', true);
        $downloadPath = getcwd() . DIRECTORY_SEPARATOR . $uuid . '.txt';
        try {
            $result = $this->restProxy->saveBlobToFile($downloadPath, $name, $blob);
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
        }

        self::assertTrue(strpos($errorMsg, BlobResources::ERROR_BLOB_NOT_EXIST) != 0);

        if (file_exists($downloadPath)) {
            unlink($downloadPath);
        }
    }

    public function testsaveBlobToFileWithContainerNotExist()
    {
        $errorMsg = '';
        $name = 'nonexistingcontainer';
        $blob = 'non_existing_blob';
        //get the path for the file to be downloaded into.
        $uuid = uniqid('test-file-', true);
        $downloadPath = getcwd() . DIRECTORY_SEPARATOR . $uuid . '.txt';
        try {
            $result = $this->restProxy->saveBlobToFile($downloadPath, $name, $blob);
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
        }
        self::assertTrue(strpos($errorMsg, BlobResources::ERROR_CONTAINER_NOT_EXIST) != 0);

        if (file_exists($downloadPath)) {
            unlink($downloadPath);
        }
    }

    public function testAddOptionalAccessContitionHeader()
    {
        // Setup
        $expectedHeader = Resources::IF_MATCH;
        $expectedValue = '0x8CAFB82EFF70C46';
        $accessCondition = AccessCondition::ifMatch($expectedValue);
        $headers = ['Header1' => 'Value1', 'Header2' => 'Value2'];

        // Test
        $actual = $this->restProxy->addOptionalAccessConditionHeader($headers, [$accessCondition]);

        // Assert
        self::assertCount(3, $actual);
        self::assertEquals($expectedValue, $actual[$expectedHeader]);
    }

    public function testAddOptionalSourceAccessContitionHeader()
    {
        // Setup
        $expectedHeader = Resources::X_MS_SOURCE_IF_MATCH;
        $expectedValue = '0x8CAFB82EFF70C46';
        $accessCondition = AccessCondition::ifMatch($expectedValue);
        $headers = ['Header1' => 'Value1', 'Header2' => 'Value2'];

        // Test
        $actual = $this->restProxy->addOptionalSourceAccessConditionHeader($headers, [$accessCondition]);

        // Assert
        self::assertCount(3, $actual);
        self::assertEquals($expectedValue, $actual[$expectedHeader]);
    }

    public function testGetServiceStats()
    {
        $result = $this->restProxy->getServiceStats();

        // Assert
        self::assertNotNull($result->getStatus());
        self::assertNotNull($result->getLastSyncTime());
        self::assertTrue($result->getLastSyncTime() instanceof \DateTime);
    }
}
