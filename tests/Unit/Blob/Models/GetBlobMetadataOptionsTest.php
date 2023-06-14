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

use AzureOSS\Storage\Blob\Models\AccessCondition;
use AzureOSS\Storage\Blob\Models\GetBlobMetadataOptions;
use AzureOSS\Storage\Tests\Framework\TestResources;

/**
 * Unit tests for class GetBlobMetadataOptions
 *
 * @see      https://github.com/azure/azure-storage-php
 */
class GetBlobMetadataOptionsTest extends \PHPUnit\Framework\TestCase
{
    public function testSetLeaseId()
    {
        // Setup
        $expected = '0x8CAFB82EFF70C46';
        $options = new GetBlobMetadataOptions();
        $options->setLeaseId($expected);

        // Test
        $options->setLeaseId($expected);

        // Assert
        self::assertEquals($expected, $options->getLeaseId());
    }

    public function testGetAccessConditions()
    {
        // Setup
        $expected = AccessCondition::none();
        $result = new GetBlobMetadataOptions();
        $result->setAccessConditions($expected);

        // Test
        $actual = $result->getAccessConditions();

        // Assert
        self::assertEquals($expected, $actual[0]);
    }

    public function testSetAccessConditions()
    {
        // Setup
        $expected = AccessCondition::none();
        $result = new GetBlobMetadataOptions();

        // Test
        $result->setAccessConditions($expected);

        // Assert
        self::assertEquals($expected, $result->getAccessConditions()[0]);
    }

    public function testSetSnapshot()
    {
        // Setup
        $blob = new GetBlobMetadataOptions();
        $expected = TestResources::QUEUE1_NAME;

        // Test
        $blob->setSnapshot($expected);

        // Assert
        self::assertEquals($expected, $blob->getSnapshot());
    }

    public function testGetSnapshot()
    {
        // Setup
        $blob = new GetBlobMetadataOptions();
        $expected = TestResources::QUEUE_URI;
        $blob->setSnapshot($expected);

        // Test
        $actual = $blob->getSnapshot();

        // Assert
        self::assertEquals($expected, $actual);
    }
}
