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
 * @link      https://github.com/azure/azure-storage-php
 */

namespace MicrosoftAzure\Storage\Tests\Unit\Table\Models;

use MicrosoftAzure\Storage\Table\Models\EdmType;
use MicrosoftAzure\Storage\Table\Models\Property;

/**
 * Unit tests for class Property
 *
 * @link      https://github.com/azure/azure-storage-php
 */
class PropertyTest extends \PHPUnit\Framework\TestCase
{
    public function testSetEdmType()
    {
        // Setup
        $pro = new Property();
        $expected = EdmType::BINARY;

        // Test
        $pro->setEdmType($expected);

        // Assert
        self::assertEquals($expected, $pro->getEdmType());
    }

    public function testSetValue()
    {
        // Setup
        $pro = new Property();
        $expected = 'wal3a';

        // Test
        $pro->setValue($expected);

        // Assert
        self::assertEquals($expected, $pro->getValue());
    }
}
