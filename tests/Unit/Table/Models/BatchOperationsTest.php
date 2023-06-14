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

use AzureOSS\Storage\Table\Models\BatchOperation;
use AzureOSS\Storage\Table\Models\BatchOperations;
use AzureOSS\Storage\Table\Models\Entity;

/**
 * Unit tests for class BatchOperations
 *
 * @see      https://github.com/azure/azure-storage-php
 */
class BatchOperationsTest extends \PHPUnit\Framework\TestCase
{
    public function testConstruct()
    {
        // Test
        $operations = new BatchOperations();

        // Assert
        self::assertCount(0, $operations->getOperations());

        return $operations;
    }

    /**
     * @depends testConstruct
     */
    public function testSetOperations($operations)
    {
        // Setup
        $operation = new BatchOperation();
        $expected = [$operation];
        $operations->addOperation($operation);

        // Test
        $operations->setOperations($expected);

        // Assert
        self::assertEquals($expected, $operations->getOperations());
    }

    public function testAddInsertEntity()
    {
        // Setup
        $table = 'mytable';
        $entity = new Entity();
        $operations = new BatchOperations();

        // Test
        $operations->addInsertEntity($table, $entity);

        // Assert
        self::assertCount(1, $operations->getOperations());
    }

    public function testAddUpdateEntity()
    {
        // Setup
        $table = 'mytable';
        $entity = new Entity();
        $operations = new BatchOperations();

        // Test
        $operations->addUpdateEntity($table, $entity);

        // Assert
        self::assertCount(1, $operations->getOperations());
    }

    public function testAddMergeEntity()
    {
        // Setup
        $table = 'mytable';
        $entity = new Entity();
        $operations = new BatchOperations();

        // Test
        $operations->addMergeEntity($table, $entity);

        // Assert
        self::assertCount(1, $operations->getOperations());
    }

    public function testAddInsertOrReplaceEntity()
    {
        // Setup
        $table = 'mytable';
        $entity = new Entity();
        $operations = new BatchOperations();

        // Test
        $operations->addInsertOrReplaceEntity($table, $entity);

        // Assert
        self::assertCount(1, $operations->getOperations());
    }

    public function testAddInsertOrMergeEntity()
    {
        // Setup
        $table = 'mytable';
        $entity = new Entity();
        $operations = new BatchOperations();

        // Test
        $operations->addInsertOrMergeEntity($table, $entity);

        // Assert
        self::assertCount(1, $operations->getOperations());
    }

    public function testAddDeleteEntity()
    {
        // Setup
        $table = 'mytable';
        $partitionKey = '123';
        $rowKey = '456';
        $etag = 'W/datetime:2009';
        $operations = new BatchOperations();

        // Test
        $operations->addDeleteEntity($table, $partitionKey, $rowKey, $etag);

        // Assert
        self::assertCount(1, $operations->getOperations());
    }
}
