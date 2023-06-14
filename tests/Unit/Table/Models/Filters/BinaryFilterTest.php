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

namespace AzureOSS\Storage\Tests\Unit\Table\Models\Filters;

use AzureOSS\Storage\Table\Models\Filters\BinaryFilter;

/**
 * Unit tests for class BinaryFilter
 *
 * @see      https://github.com/azure/azure-storage-php
 */
class BinaryFilterTest extends \PHPUnit\Framework\TestCase
{
    public function testGetOperator()
    {
        // Setup
        $expected = 'x';
        $filter = new BinaryFilter(null, $expected, null);

        // Assert
        self::assertEquals($expected, $filter->getOperator());
    }

    public function testGetLeft()
    {
        // Setup
        $expected = null;
        $filter = new BinaryFilter($expected, null, null);

        // Assert
        self::assertEquals($expected, $filter->getLeft());
    }

    public function testGetRight()
    {
        // Setup
        $expected = null;
        $filter = new BinaryFilter(null, null, $expected);

        // Assert
        self::assertEquals($expected, $filter->getRight());
    }
}
