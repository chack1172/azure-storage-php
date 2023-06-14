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

namespace AzureOSS\Storage\Tests\Unit\Blob\Models;

use AzureOSS\Storage\Blob\Models\SetBlobPropertiesResult;
use AzureOSS\Storage\Common\Internal\Utilities;
use AzureOSS\Storage\Tests\Framework\TestResources;

/**
 * Unit tests for class SetBlobPropertiesResult
 *
 * @see      https://github.com/azure/azure-storage-php
 */
class SetBlobPropertiesResultTest extends \PHPUnit\Framework\TestCase
{
    public function testCreate()
    {
        // Setup
        $sample = TestResources::ListBlobsOneEntry()['Blobs']['Blob']['Properties'];
        $expectedDate = Utilities::rfc1123ToDateTime($sample['Last-Modified']);

        // Test
        $result = SetBlobPropertiesResult::create($sample);

        // Assert
        self::assertEquals($expectedDate, $result->getLastModified());
        self::assertEquals($sample['x-ms-blob-sequence-number'], $result->getSequenceNumber());
        self::assertEquals($sample['Etag'], $result->getETag());
    }
}
