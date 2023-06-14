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

namespace MicrosoftAzure\Storage\Tests\Unit\Table\Models;

use AzureOSS\Storage\Table\Models\TableACL;
use MicrosoftAzure\Storage\Tests\Framework\TestResources;

/**
 * Unit tests for class TableACL
 *
 * @see      https://github.com/azure/azure-storage-php
 */
class TableACLTest extends \PHPUnit\Framework\TestCase
{
    public function testCreateEmpty()
    {
        // Setup
        $sample = [];

        // Test
        $acl = TableACL::create($sample);

        // Assert
        self::assertCount(0, $acl->getSignedIdentifiers());
    }

    public function testCreateOneEntry()
    {
        // Setup
        $sample = TestResources::getTableACLOneEntrySample();

        // Test
        $acl = TableACL::create($sample['SignedIdentifiers']);

        // Assert
        self::assertCount(1, $acl->getSignedIdentifiers());
    }

    public function testCreateMultipleEntries()
    {
        // Setup
        $sample = TestResources::getTableACLMultipleEntriesSample();

        // Test
        $acl = TableACL::create($sample['SignedIdentifiers']);

        // Assert
        self::assertCount(2, $acl->getSignedIdentifiers());

        return $acl;
    }
}
