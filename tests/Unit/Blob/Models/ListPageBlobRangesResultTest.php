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

namespace MicrosoftAzure\Storage\Tests\Unit\Blob\Models;

use MicrosoftAzure\Storage\Blob\Models\ListPageBlobRangesResult;
use MicrosoftAzure\Storage\Common\Internal\Utilities;
use MicrosoftAzure\Storage\Common\Models\Range;
use MicrosoftAzure\Storage\Tests\Framework\TestResources;

/**
 * Unit tests for class ListPageBlobRangesResultTest
 *
 * @link      https://github.com/azure/azure-storage-php
 */
class ListPageBlobRangesResultTest extends \PHPUnit\Framework\TestCase
{
    public function testCreate()
    {
        // Setup
        $headers = TestResources::listPageRangeHeaders();
        $bodyArray = TestResources::listPageRangeBodyInArray();
        // Prepare expected page range
        $rawPageRanges = [];
        if (!empty($bodyArray['PageRange'])) {
            $rawPageRanges = Utilities::getArray($bodyArray['PageRange']);
        }

        $pageRanges = [];
        foreach ($rawPageRanges as $value) {
            $pageRanges[] = new Range(
                (int) ($value['Start']),
                (int) ($value['End'])
            );
        }
        // Prepare expected last modified date
        $expectedLastModified = Utilities::rfc1123ToDateTime($headers['Last-Modified']);

        // Test
        $result = ListPageBlobRangesResult::create($headers, $bodyArray);

        //Assert
        self::assertEquals($pageRanges, $result->getRanges());
        self::assertEquals($expectedLastModified, $result->getLastModified());
        self::assertEquals($headers['Etag'], $result->getETag());
        self::assertEquals($headers['x-ms-blob-content-length'], $result->getContentLength());
    }
}
