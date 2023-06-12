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

namespace MicrosoftAzure\Storage\Tests\Unit\File\Models;

use MicrosoftAzure\Storage\Common\Internal\Resources;
use MicrosoftAzure\Storage\File\Internal\FileResources;
use MicrosoftAzure\Storage\File\Models\File;
use MicrosoftAzure\Storage\Tests\Framework\TestResources;

/**
 * Unit tests for class File
 *
 * @link      https://github.com/azure/azure-storage-php
 */
class FileTest extends \PHPUnit\Framework\TestCase
{
    public function testCreate()
    {
        // Setup
        $listArray =
            TestResources::getInterestingListDirectoriesAndFilesResultArray(0, 5);
        $samples = $listArray[Resources::QP_ENTRIES][FileResources::QP_FILE];

        // Test
        $actuals = [];
        $actuals[] = File::create($samples[0]);
        $actuals[] = File::create($samples[1]);
        $actuals[] = File::create($samples[2]);
        $actuals[] = File::create($samples[3]);
        $actuals[] = File::create($samples[4]);

        // Assert
        for ($i = 0; $i < count($samples); ++$i) {
            $sample = $samples[$i];
            $actual = $actuals[$i];

            self::assertEquals($sample[Resources::QP_NAME], $actual->getName());
            self::assertEquals(
                $sample[Resources::QP_PROPERTIES][Resources::QP_CONTENT_LENGTH],
                $actual->getLength()
            );
        }
    }
}
