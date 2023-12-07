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

namespace AzureOSS\Storage\Tests\Unit\Table;

use AzureOSS\Storage\Common\Models\ServiceProperties;
use AzureOSS\Storage\Table\Internal\ITable;
use AzureOSS\Storage\Table\Models\BatchOperations;
use AzureOSS\Storage\Table\Models\EdmType;
use AzureOSS\Storage\Table\Models\Filters\Filter;
use AzureOSS\Storage\Table\Models\Query;
use AzureOSS\Storage\Table\Models\QueryEntitiesOptions;
use AzureOSS\Storage\Table\Models\QueryTablesOptions;
use AzureOSS\Storage\Table\Models\TableACL;
use AzureOSS\Storage\Table\TableRestProxy;
use AzureOSS\Storage\Tests\Framework\TableServiceRestProxyTestBase;
use AzureOSS\Storage\Tests\Framework\TestResources;

/**
 * Unit tests for class TableRestProxy
 *
 * @see      https://github.com/azure/azure-storage-php
 */
class TableRestProxyTest extends TableServiceRestProxyTestBase
{
    public function testBuildForTable()
    {
        // Test
        $tableRestProxy = TableRestProxy::createTableService(TestResources::getWindowsAzureStorageServicesConnectionString());

        // Assert
        self::assertInstanceOf(ITable::class, $tableRestProxy);
    }

    public function testSetServiceProperties()
    {
        $this->skipIfEmulated();

        // Setup
        $expected = ServiceProperties::create(TestResources::setServicePropertiesSample());

        // Test
        $this->setServiceProperties($expected);
        //Add 30s interval to wait for setting to take effect.
        \sleep(30);
        $actual = $this->restProxy->getServiceProperties();

        // Assert
        self::assertEquals($expected->toXml($this->xmlSerializer), $actual->getValue()->toXml($this->xmlSerializer));
    }

    public function testSetServicePropertiesWithEmptyParts()
    {
        $this->skipIfEmulated();

        // Setup
        $xml = TestResources::setServicePropertiesSample();
        unset($xml['HourMetrics']);
        $expected = ServiceProperties::create($xml);

        // Test
        $this->setServiceProperties($expected);
        //Add 30s interval to wait for setting to take effect.
        \sleep(30);
        $actual = $this->restProxy->getServiceProperties();

        // Assert
        self::assertEquals($expected->toXml($this->xmlSerializer), $actual->getValue()->toXml($this->xmlSerializer));
    }

    public function testCreateTable()
    {
        // Setup
        $name = 'createtable';

        // Test
        $this->createTable($name);

        // Assert
        $result = $this->restProxy->queryTables();
        self::assertCount(1, $result->getTables());
    }

    public function testGetTable()
    {
        // Setup
        $name = 'gettable';
        $this->createTable($name);

        // Test
        $result = $this->restProxy->getTable($name);

        // Assert
        self::assertEquals($name, $result->getName());
    }

    public function testDeleteTable()
    {
        // Setup
        $name = 'deletetable';
        $this->restProxy->createTable($name);

        // Test
        $this->restProxy->deleteTable($name);

        // Assert
        $result = $this->restProxy->queryTables();
        self::assertCount(0, $result->getTables());
    }

    public function testQueryTablesSimple()
    {
        // Setup
        $name1 = 'querytablessimple1';
        $name2 = 'querytablessimple2';
        $this->createTable($name1);
        $this->createTable($name2);

        // Test
        $result = $this->restProxy->queryTables();

        // Assert
        $tables = $result->getTables();
        self::assertCount(2, $tables);
        self::assertEquals($name1, $tables[0]);
        self::assertEquals($name2, $tables[1]);
    }

    public function testQueryTablesOneTable()
    {
        // Setup
        $name1 = 'mytable1';
        $this->createTable($name1);

        // Test
        $result = $this->restProxy->queryTables();

        // Assert
        $tables = $result->getTables();
        self::assertCount(1, $tables);
        self::assertEquals($name1, $tables[0]);
    }

    public function testQueryTablesEmpty()
    {
        // Test
        $result = $this->restProxy->queryTables();

        // Assert
        $tables = $result->getTables();
        self::assertCount(0, $tables);
    }

    public function testQueryTablesWithPrefix()
    {
        $this->skipIfEmulated();

        // Setup
        $name1 = 'wquerytableswithprefix1';
        $name2 = 'querytableswithprefix2';
        $name3 = 'querytableswithprefix3';
        $options = new QueryTablesOptions();
        $options->setPrefix('q');
        $this->createTable($name1);
        $this->createTable($name2);
        $this->createTable($name3);

        // Test
        $result = $this->restProxy->queryTables($options);

        // Assert
        $tables = $result->getTables();
        self::assertCount(2, $tables);
        self::assertEquals($name2, $tables[0]);
        self::assertEquals($name3, $tables[1]);
    }

    public function testQueryTablesWithStringOption()
    {
        $this->skipIfEmulated();

        // Setup
        $name1 = 'wquerytableswithstringoption1';
        $name2 = 'querytableswithstringoption2';
        $name3 = 'querytableswithstringoption3';
        $prefix = 'q';
        $this->createTable($name1);
        $this->createTable($name2);
        $this->createTable($name3);

        // Test
        $result = $this->restProxy->queryTables($prefix);

        // Assert
        $tables = $result->getTables();
        self::assertCount(2, $tables);
        self::assertEquals($name2, $tables[0]);
        self::assertEquals($name3, $tables[1]);
    }

    public function testQueryTablesWithFilterOption()
    {
        $this->skipIfEmulated();

        // Setup
        $name1 = 'wquerytableswithfilteroption1';
        $name2 = 'querytableswithfilteroption2';
        $name3 = 'querytableswithfilteroption3';
        $prefix = 'q';
        $prefixFilter = Filter::applyAnd(
            Filter::applyGe(
                Filter::applyPropertyName('TableName'),
                Filter::applyConstant($prefix, EdmType::STRING)
            ),
            Filter::applyLe(
                Filter::applyPropertyName('TableName'),
                Filter::applyConstant($prefix . '{', EdmType::STRING)
            )
        );
        $this->createTable($name1);
        $this->createTable($name2);
        $this->createTable($name3);

        // Test
        $result = $this->restProxy->queryTables($prefixFilter);

        // Assert
        $tables = $result->getTables();
        self::assertCount(2, $tables);
        self::assertEquals($name2, $tables[0]);
        self::assertEquals($name3, $tables[1]);
    }

    public function testInsertEntity()
    {
        // Setup
        $name = 'insertentity';
        $this->createTable($name);
        $expected = TestResources::getTestEntity('123', '456');

        // Test
        $result = $this->restProxy->insertEntity($name, $expected);

        // Assert
        $actual = $result->getEntity();
        self::assertEquals($expected->getPartitionKey(), $actual->getPartitionKey());
        self::assertEquals($expected->getRowKey(), $actual->getRowKey());
        // Add extra count for the properties because the Timestamp property
        self::assertCount(count($expected->getProperties()) + 1, $actual->getProperties());
    }

    public function testQueryEntitiesWithEmpty()
    {
        // Setup
        $name = 'queryentitieswithempty';
        $this->createTable($name);

        // Test
        $result = $this->restProxy->queryEntities($name);

        // Assert
        $entities = $result->getEntities();
        self::assertCount(0, $entities);
    }

    public function testQueryEntitiesWithOneEntity()
    {
        // Setup
        $name = 'queryentitieswithoneentity';
        $pk1 = '123';
        $e1 = TestResources::getTestEntity($pk1, '1');
        $this->createTable($name);
        $this->restProxy->insertEntity($name, $e1);

        // Test
        $result = $this->restProxy->queryEntities($name);

        // Assert
        $entities = $result->getEntities();
        self::assertCount(1, $entities);

        $actualEntity = $entities[0];
        self::assertEquals($pk1, $actualEntity->getPartitionKey());
        self::assertEquals(EdmType::STRING, $entities[0]->getProperty('CustomerName')->getEdmType());
    }

    public function testQueryEntitiesQueryStringOption()
    {
        $this->skipIfEmulated();

        // Setup
        $name = 'queryentitieswithquerystringoption';
        $pk1 = '123';
        $pk2 = '124';
        $pk3 = '125';
        $e1 = TestResources::getTestEntity($pk1, '1');
        $e2 = TestResources::getTestEntity($pk2, '2');
        $e3 = TestResources::getTestEntity($pk3, '3');
        $this->createTable($name);
        $this->restProxy->insertEntity($name, $e1);
        $this->restProxy->insertEntity($name, $e2);
        $this->restProxy->insertEntity($name, $e3);
        $queryString = "PartitionKey eq '123'";

        // Test
        $result = $this->restProxy->queryEntities($name, $queryString);

        // Assert
        $entities = $result->getEntities();
        self::assertCount(1, $entities);
        self::assertEquals($pk1, $entities[0]->getPartitionKey());
    }

    public function testQueryEntitiesFilterOption()
    {
        $this->skipIfEmulated();

        // Setup
        $name = 'queryentitieswithfilteroption';
        $pk1 = '123';
        $pk2 = '124';
        $pk3 = '125';
        $e1 = TestResources::getTestEntity($pk1, '1');
        $e2 = TestResources::getTestEntity($pk2, '2');
        $e3 = TestResources::getTestEntity($pk3, '3');
        $this->createTable($name);
        $this->restProxy->insertEntity($name, $e1);
        $this->restProxy->insertEntity($name, $e2);
        $this->restProxy->insertEntity($name, $e3);
        $queryString = "PartitionKey eq '123'";
        $filter = Filter::applyQueryString($queryString);

        // Test
        $result = $this->restProxy->queryEntities($name, $filter);

        // Assert
        $entities = $result->getEntities();
        self::assertCount(1, $entities);
        self::assertEquals($pk1, $entities[0]->getPartitionKey());
    }

    public function testQueryEntitiesWithMultipleEntities()
    {
        $this->skipIfEmulated();

        // Setup
        $name = 'queryentitieswithmultipleentities';
        $pk1 = '123';
        $pk2 = '124';
        $pk3 = '125';
        // This value is hard coded in TestResources::getTestEntity
        $expected = 890;
        $field = 'CustomerId';
        $e1 = TestResources::getTestEntity($pk1, '1');
        $e2 = TestResources::getTestEntity($pk2, '2');
        $e3 = TestResources::getTestEntity($pk3, '3');
        $this->createTable($name);
        $this->restProxy->insertEntity($name, $e1);
        $this->restProxy->insertEntity($name, $e2);
        $this->restProxy->insertEntity($name, $e3);
        $query = new Query();
        $query->addSelectField('CustomerId');
        $options = new QueryEntitiesOptions();
        $options->setQuery($query);

        // Test
        $result = $this->restProxy->queryEntities($name, $options);

        // Assert
        $entities = $result->getEntities();
        self::assertCount(3, $entities);
        self::assertEquals($expected, $entities[0]->getProperty($field)->getValue());
        self::assertEquals($expected, $entities[1]->getProperty($field)->getValue());
        self::assertEquals($expected, $entities[2]->getProperty($field)->getValue());
    }

    public function testQueryEntitiesWithGetTop()
    {
        // Setup
        $name = 'queryentitieswithgettop';
        $pk1 = '123';
        $pk2 = '124';
        $pk3 = '125';
        $e1 = TestResources::getTestEntity($pk1, '1');
        $e2 = TestResources::getTestEntity($pk2, '2');
        $e3 = TestResources::getTestEntity($pk3, '3');
        $this->createTable($name);
        $this->restProxy->insertEntity($name, $e1);
        $this->restProxy->insertEntity($name, $e2);
        $this->restProxy->insertEntity($name, $e3);
        $query = new Query();
        $query->setTop(1);
        $options = new QueryEntitiesOptions();
        $options->setQuery($query);

        // Test
        $result = $this->restProxy->queryEntities($name, $options);

        // Assert
        $entities = $result->getEntities();
        self::assertCount(1, $entities);
        self::assertEquals($pk1, $entities[0]->getPartitionKey());
    }

    public function testUpdateEntity()
    {
        // Setup
        $name = 'updateentity';
        $this->createTable($name);
        $expected = TestResources::getTestEntity('123', '456');
        $this->restProxy->insertEntity($name, $expected);
        $result = $this->restProxy->queryEntities($name);
        $entities = $result->getEntities();
        $expected = $entities[0];
        $expected->addProperty('CustomerPlace', EdmType::STRING, 'Redmond');

        // Test
        $result = $this->restProxy->UpdateEntity($name, $expected);

        // Assert
        self::assertNotNull($result);
        $result = $this->restProxy->queryEntities($name);
        $entities = $result->getEntities();
        $actual = $entities[0];
        self::assertEquals($expected->getPartitionKey(), $actual->getPartitionKey());
        self::assertEquals($expected->getRowKey(), $actual->getRowKey());
        self::assertCount(count($expected->getProperties()), $actual->getProperties());
    }

    public function testUpdateEntityWithDeleteProperty()
    {
        $this->skipIfEmulated();

        // Setup
        $name = 'updateentitywithdeleteproperty';
        $this->createTable($name);
        $expected = TestResources::getTestEntity('123', '456');
        $this->restProxy->insertEntity($name, $expected);
        $expected->setPropertyValue('CustomerId', null);

        // Test
        $result = $this->restProxy->updateEntity($name, $expected);

        // Assert
        self::assertNotNull($result);
        $actual = $this->restProxy->getEntity($name, $expected->getPartitionKey(), $expected->getRowKey());
        self::assertEquals($expected->getPartitionKey(), $actual->getEntity()->getPartitionKey());
        self::assertEquals($expected->getRowKey(), $actual->getEntity()->getRowKey());
        // Add +1 to the count to include Timestamp property.
        self::assertCount(count($expected->getProperties()), $actual->getEntity()->getProperties());
    }

    public function testMergeEntity()
    {
        // Setup
        $name = 'mergeentity';
        $this->createTable($name);
        $expected = TestResources::getTestEntity('123', '456');
        $this->restProxy->insertEntity($name, $expected);
        $result = $this->restProxy->queryEntities($name);
        $entities = $result->getEntities();
        $expected = $entities[0];
        $expected->addProperty('CustomerPhone', EdmType::STRING, '99999999');

        // Test
        $result = $this->restProxy->mergeEntity($name, $expected);

        // Assert
        self::assertNotNull($result);
        $result = $this->restProxy->queryEntities($name);
        $entities = $result->getEntities();
        $actual = $entities[0];
        self::assertEquals($expected->getPartitionKey(), $actual->getPartitionKey());
        self::assertEquals($expected->getRowKey(), $actual->getRowKey());
        self::assertCount(count($expected->getProperties()), $actual->getProperties());
    }

    public function testInsertOrReplaceEntity()
    {
        $this->skipIfEmulated();

        // Setup
        $name = 'insertorreplaceentity';
        $this->createTable($name);
        $expected = TestResources::getTestEntity('123', '456');
        $this->restProxy->insertEntity($name, $expected);
        $result = $this->restProxy->queryEntities($name);
        $entities = $result->getEntities();
        $expected = $entities[0];
        $expected->addProperty('CustomerPlace', EdmType::STRING, 'Redmond');

        // Test
        $result = $this->restProxy->InsertOrReplaceEntity($name, $expected);

        // Assert
        self::assertNotNull($result);
        $result = $this->restProxy->queryEntities($name);
        $entities = $result->getEntities();
        $actual = $entities[0];
        self::assertEquals($expected->getPartitionKey(), $actual->getPartitionKey());
        self::assertEquals($expected->getRowKey(), $actual->getRowKey());
        self::assertCount(count($expected->getProperties()), $actual->getProperties());
    }

    public function testInsertOrMergeEntity()
    {
        $this->skipIfEmulated();

        // Setup
        $name = 'insertormergeentity';
        $this->createTable($name);
        $expected = TestResources::getTestEntity('123', '456');
        $this->restProxy->insertEntity($name, $expected);
        $result = $this->restProxy->queryEntities($name);
        $entities = $result->getEntities();
        $expected = $entities[0];
        $expected->addProperty('CustomerPhone', EdmType::STRING, '99999999');

        // Test
        $result = $this->restProxy->InsertOrMergeEntity($name, $expected);

        // Assert
        self::assertNotNull($result);
        $result = $this->restProxy->queryEntities($name);
        $entities = $result->getEntities();
        $actual = $entities[0];
        self::assertEquals($expected->getPartitionKey(), $actual->getPartitionKey());
        self::assertEquals($expected->getRowKey(), $actual->getRowKey());
        self::assertCount(count($expected->getProperties()), $actual->getProperties());
    }

    public function testDeleteEntity()
    {
        // Setup
        $name = 'deleteentity';
        $this->createTable($name);
        $partitionKey = '123';
        $rowKey = '456';
        $entity = TestResources::getTestEntity($partitionKey, $rowKey);
        $result = $this->restProxy->insertEntity($name, $entity);

        // Test
        $this->restProxy->deleteEntity($name, $partitionKey, $rowKey);

        // Assert
        $result = $this->restProxy->queryEntities($name);
        $entities = $result->getEntities();
        self::assertCount(0, $entities);
    }

    public function testDeleteEntityWithSpecialChars()
    {
        // Setup
        $name = 'deleteentitywithspecialchars';
        $this->createTable($name);
        $partitionKey = '123';
        $rowKey = 'key with spaces';
        $entity = TestResources::getTestEntity($partitionKey, $rowKey);
        $result = $this->restProxy->insertEntity($name, $entity);

        // Test
        $this->restProxy->deleteEntity($name, $partitionKey, $rowKey);

        // Assert
        $result = $this->restProxy->queryEntities($name);
        $entities = $result->getEntities();
        self::assertCount(0, $entities);
    }

    public function testGetEntity()
    {
        // Setup
        $name = 'getentity';
        $this->createTable($name);
        $partitionKey = '123';
        $rowKey = '456';
        $expected = TestResources::getTestEntity($partitionKey, $rowKey);
        $this->restProxy->insertEntity($name, $expected);

        // Test
        $result = $this->restProxy->getEntity($name, $partitionKey, $rowKey);

        // Assert
        $actual = $result->getEntity();
        self::assertEquals($expected->getPartitionKey(), $actual->getPartitionKey());
        self::assertEquals($expected->getRowKey(), $actual->getRowKey());
        // Increase thec properties count to incloude the Timestamp property.
        self::assertCount(count($expected->getProperties()) + 1, $actual->getProperties());
    }

    public function testGetEntityVariousType()
    {
        // Setup
        $name = 'getentityvarioustype';
        $this->createTable($name);
        $partitionKey = '123';
        $rowKey = '456';
        $expected = TestResources::getVariousTypesEntity();
        $expected->setPartitionKey($partitionKey);
        $expected->setRowKey($rowKey);
        $this->restProxy->insertEntity($name, $expected);

        // Test
        $result = $this->restProxy->getEntity($name, $partitionKey, $rowKey);

        // Assert
        $actual = $result->getEntity();
        self::assertEquals($expected->getPartitionKey(), $actual->getPartitionKey());
        self::assertEquals($expected->getRowKey(), $actual->getRowKey());
        $expectedProperties = $expected->getProperties();
        $actualProperties = $actual->getProperties();
        foreach ($expectedProperties as $key => $property) {
            self::assertEquals(
                $property->getEdmType(),
                $actualProperties[$key]->getEdmType()
            );
            self::assertEquals(
                $property->getValue(),
                $actualProperties[$key]->getValue()
            );
        }
    }

    public function testBatchWithInsert()
    {
        // Setup
        $name = 'batchwithinsert';
        $this->createTable($name);
        $partitionKey = '123';
        $rowKey = '456';
        $expected = TestResources::getTestEntity($partitionKey, $rowKey);
        $operations = new BatchOperations();
        $operations->addInsertEntity($name, $expected);

        // Test
        $result = $this->restProxy->batch($operations);

        // Assert
        $entries = $result->getEntries();
        $actual = $entries[0]->getEntity();
        self::assertEquals($expected->getPartitionKey(), $actual->getPartitionKey());
        self::assertEquals($expected->getRowKey(), $actual->getRowKey());
        // Increase the properties count to include Timestamp property.
        self::assertCount(count($expected->getProperties()) + 1, $actual->getProperties());
    }

    public function testBatchWithDelete()
    {
        // Setup
        $name = 'batchwithdelete';
        $this->createTable($name);
        $partitionKey = '123';
        $rowKey = '456';
        $expected = TestResources::getTestEntity($partitionKey, $rowKey);
        $this->restProxy->insertEntity($name, $expected);
        $operations = new BatchOperations();
        $operations->addDeleteEntity($name, $partitionKey, $rowKey);

        // Test
        $this->restProxy->batch($operations);

        // Assert
        $result = $this->restProxy->queryEntities($name);
        $entities = $result->getEntities();
        self::assertCount(0, $entities);
    }

    public function testBatchWithUpdate()
    {
        // Setup
        $name = 'batchwithupdate';
        $this->createTable($name);
        $partitionKey = '123';
        $rowKey = '456';
        $expected = TestResources::getTestEntity($partitionKey, $rowKey);
        $this->restProxy->insertEntity($name, $expected);
        $result = $this->restProxy->queryEntities($name);
        $entities = $result->getEntities();
        $expected = $entities[0];
        $expected->addProperty('CustomerPlace', EdmType::STRING, 'Redmond');
        $operations = new BatchOperations();
        $operations->addUpdateEntity($name, $expected);

        // Test
        $result = $this->restProxy->batch($operations);

        // Assert
        $entries = $result->getEntries();
        self::assertNotNull($entries[0]->getETag());
        $result = $this->restProxy->queryEntities($name);
        $entities = $result->getEntities();
        $actual = $entities[0];
        self::assertEquals($expected->getPartitionKey(), $actual->getPartitionKey());
        self::assertEquals($expected->getRowKey(), $actual->getRowKey());
        self::assertCount(count($expected->getProperties()), $actual->getProperties());
    }

    public function testBatchWithMerge()
    {
        // Setup
        $name = 'batchwithmerge';
        $this->createTable($name);
        $partitionKey = '123';
        $rowKey = '456';
        $expected = TestResources::getTestEntity($partitionKey, $rowKey);
        $this->restProxy->insertEntity($name, $expected);
        $result = $this->restProxy->queryEntities($name);
        $entities = $result->getEntities();
        $expected = $entities[0];
        $expected->addProperty('CustomerPlace', EdmType::STRING, 'Redmond');
        $operations = new BatchOperations();
        $operations->addMergeEntity($name, $expected);

        // Test
        $result = $this->restProxy->batch($operations);

        // Assert
        $entries = $result->getEntries();
        self::assertNotNull($entries[0]->getETag());
        $result = $this->restProxy->queryEntities($name);
        $entities = $result->getEntities();
        $actual = $entities[0];
        self::assertEquals($expected->getPartitionKey(), $actual->getPartitionKey());
        self::assertEquals($expected->getRowKey(), $actual->getRowKey());
        self::assertCount(count($expected->getProperties()), $actual->getProperties());
    }

    public function testBatchWithInsertOrReplace()
    {
        $this->skipIfEmulated();

        // Setup
        $name = 'batchwithinsertorreplace';
        $this->createTable($name);
        $partitionKey = '123';
        $rowKey = '456';
        $expected = TestResources::getTestEntity($partitionKey, $rowKey);
        $this->restProxy->insertEntity($name, $expected);
        $result = $this->restProxy->queryEntities($name);
        $entities = $result->getEntities();
        $expected = $entities[0];
        $expected->addProperty('CustomerPlace', EdmType::STRING, 'Redmond');
        $operations = new BatchOperations();
        $operations->addInsertOrReplaceEntity($name, $expected);

        // Test
        $result = $this->restProxy->batch($operations);

        // Assert
        $entries = $result->getEntries();
        self::assertNotNull($entries[0]->getETag());
        $result = $this->restProxy->queryEntities($name);
        $entities = $result->getEntities();
        $actual = $entities[0];
        self::assertEquals($expected->getPartitionKey(), $actual->getPartitionKey());
        self::assertEquals($expected->getRowKey(), $actual->getRowKey());
        self::assertCount(count($expected->getProperties()), $actual->getProperties());
    }

    public function testBatchWithInsertOrMerge()
    {
        $this->skipIfEmulated();

        // Setup
        $name = 'batchwithinsertormerge';
        $this->createTable($name);
        $partitionKey = '123';
        $rowKey = '456';
        $expected = TestResources::getTestEntity($partitionKey, $rowKey);
        $this->restProxy->insertEntity($name, $expected);
        $result = $this->restProxy->queryEntities($name);
        $entities = $result->getEntities();
        $expected = $entities[0];
        $expected->addProperty('CustomerPlace', EdmType::STRING, 'Redmond');
        $operations = new BatchOperations();
        $operations->addInsertOrMergeEntity($name, $expected);

        // Test
        $result = $this->restProxy->batch($operations);

        // Assert
        $entries = $result->getEntries();
        self::assertNotNull($entries[0]->getETag());
        $result = $this->restProxy->queryEntities($name);
        $entities = $result->getEntities();
        $actual = $entities[0];
        self::assertEquals($expected->getPartitionKey(), $actual->getPartitionKey());
        self::assertEquals($expected->getRowKey(), $actual->getRowKey());
        self::assertCount(count($expected->getProperties()), $actual->getProperties());
    }

    public function testBatchWithMultipleOperations()
    {
        // Setup
        $name = 'batchwithwithmultipleoperations';
        $this->createTable($name);
        $partitionKey = '123';
        $rk1 = '456';
        $rk2 = '457';
        $rk3 = '458';
        $delete = TestResources::getTestEntity($partitionKey, $rk1);
        $insert = TestResources::getTestEntity($partitionKey, $rk2);
        $update = TestResources::getTestEntity($partitionKey, $rk3);
        $this->restProxy->insertEntity($name, $delete);
        $this->restProxy->insertEntity($name, $update);
        $result = $this->restProxy->queryEntities($name);
        $entities = $result->getEntities();
        $delete = $entities[0];
        $update = $entities[1];
        $update->addProperty('CustomerPlace', EdmType::STRING, 'Redmond');
        $operations = new BatchOperations();
        $operations->addInsertEntity($name, $insert);
        $operations->addUpdateEntity($name, $update);
        $operations->addDeleteEntity($name, $delete->getPartitionKey(), $delete->getRowKey(), $delete->getETag());

        // Test
        $result = $this->restProxy->batch($operations);

        // Assert
        self::assertTrue(true);
    }

    public function testBatchWithDifferentPKFail()
    {
        $this->expectException(\AzureOSS\Storage\Common\Exceptions\ServiceException::class);
        $this->expectExceptionMessage('All commands in a batch must operate on same entity group.');

        // Setup
        $name = 'batchwithwithdifferentpkfail';
        $this->createTable($name);
        $partitionKey = '123';
        $rk1 = '456';
        $rk3 = '458';
        $delete = TestResources::getTestEntity($partitionKey, $rk1);
        $update = TestResources::getTestEntity($partitionKey, $rk3);
        $this->restProxy->insertEntity($name, $delete);
        $this->restProxy->insertEntity($name, $update);
        $result = $this->restProxy->queryEntities($name);
        $entities = $result->getEntities();
        $delete = $entities[0];
        $update = $entities[1];
        $update->addProperty('CustomerPlace', EdmType::STRING, 'Redmond');
        $operations = new BatchOperations();
        $operations->addUpdateEntity($name, $update);
        $operations->addDeleteEntity($name, '125', $delete->getRowKey(), $delete->getETag());

        // Test
        $result = $this->restProxy->batch($operations);
    }

    public function testGetSetTableAcl()
    {
        // Setup
        $name = self::getTableNameWithPrefix('testGetSetTableAcl');
        $this->createTable($name);
        $sample = TestResources::getTableACLMultipleEntriesSample();
        $acl = TableACL::create($sample['SignedIdentifiers']);
        //because the time is randomized, this should create a different instance
        $negativeSample = TestResources::getTableACLMultipleEntriesSample();
        $negative = TableACL::create($negativeSample['SignedIdentifiers']);

        // Test
        $this->restProxy->setTableAcl($name, $acl);
        $resultAcl = $this->restProxy->getTableAcl($name);

        self::assertEquals(
            $acl->getSignedIdentifiers(),
            $resultAcl->getSignedIdentifiers()
        );

        self::assertFalse(
            $resultAcl->getSignedIdentifiers() == $negative->getSignedIdentifiers(),
            'Should not equal to the negative test case'
        );
    }

    public function testGetServiceStats()
    {
        $result = $this->restProxy->getServiceStats();

        // Assert
        self::assertNotNull($result->getStatus());
        self::assertNotNull($result->getLastSyncTime());
        self::assertTrue($result->getLastSyncTime() instanceof \DateTime);
    }

    private static function getTableNameWithPrefix($prefix)
    {
        return $prefix . sprintf('%04x', mt_rand(0, 65535));
    }
}
