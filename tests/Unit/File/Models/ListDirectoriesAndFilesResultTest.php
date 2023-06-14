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

namespace AzureOSS\Storage\Tests\Unit\File\Models;

use AzureOSS\Storage\File\Internal\FileResources as Resources;
use AzureOSS\Storage\File\Models\Directory;
use AzureOSS\Storage\File\Models\File;
use AzureOSS\Storage\File\Models\ListDirectoriesAndFilesResult;
use AzureOSS\Storage\Tests\Framework\TestResources;

/**
 * Unit tests for class ListDirectoriesAndFilesResult
 *
 * @see      https://github.com/azure/azure-storage-php
 */
class ListDirectoriesAndFilesResultTest extends \PHPUnit\Framework\TestCase
{
    public function testCreate()
    {
        // Setup
        $samples = [];
        $samples[] =
            TestResources::getInterestingListDirectoriesAndFilesResultArray();
        $samples[] =
            TestResources::getInterestingListDirectoriesAndFilesResultArray(1, 0);
        $samples[] =
            TestResources::getInterestingListDirectoriesAndFilesResultArray(0, 1);
        $samples[] =
            TestResources::getInterestingListDirectoriesAndFilesResultArray(1, 1);
        $samples[] =
            TestResources::getInterestingListDirectoriesAndFilesResultArray(5, 5);

        // Test
        $actuals = [];
        $actuals[] = ListDirectoriesAndFilesResult::create($samples[0]);
        $actuals[] = ListDirectoriesAndFilesResult::create($samples[1]);
        $actuals[] = ListDirectoriesAndFilesResult::create($samples[2]);
        $actuals[] = ListDirectoriesAndFilesResult::create($samples[3]);
        $actuals[] = ListDirectoriesAndFilesResult::create($samples[4]);

        // Assert
        for ($i = 0; $i < count($samples); ++$i) {
            $sample = $samples[$i];
            $actual = $actuals[$i];
            $entries = $sample[Resources::QP_ENTRIES];
            if (empty($entries)) {
                self::assertEmpty($actual->getDirectories());
                self::assertEmpty($actual->getFiles());
            } else {
                if (array_key_exists(Resources::QP_DIRECTORY, $entries)) {
                    self::assertEquals(
                        count($entries[Resources::QP_DIRECTORY]),
                        count($actual->getDirectories())
                    );
                    foreach ($actual->getDirectories() as $dir) {
                        self::assertInstanceOf(Directory::class, $dir);
                        self::assertStringStartsWith('testdirectory', $dir->getName());
                    }
                } else {
                    self::assertEmpty($actual->getDirectories());
                }
                if (array_key_exists(Resources::QP_FILE, $entries)) {
                    self::assertEquals(
                        count($entries[Resources::QP_FILE]),
                        count($actual->getFiles())
                    );
                    foreach ($actual->getFiles() as $file) {
                        self::assertInstanceOf(File::class, $file);
                        self::assertStringStartsWith('testfile', $file->getName());
                        self::assertGreaterThanOrEqual(0, $file->getLength());
                    }
                } else {
                    self::assertEmpty($actual->getFiles());
                }
            }
            self::assertEquals('myaccount', $actual->getAccountName());
            self::assertEquals(5, $actual->getMaxResults());
            self::assertEquals(
                $sample[Resources::QP_NEXT_MARKER],
                $actual->getNextMarker()
            );
        }
    }
}
