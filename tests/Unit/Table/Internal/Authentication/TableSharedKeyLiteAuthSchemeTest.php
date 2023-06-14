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

namespace MicrosoftAzure\Storage\Tests\Unit\Table\Internal\Authentication;

use AzureOSS\Storage\Table\Internal\TableResources as Resources;
use MicrosoftAzure\Storage\Tests\Framework\TestResources;
use MicrosoftAzure\Storage\Tests\Mock\Table\Internal\Authentication\TableSharedKeyLiteAuthSchemeMock;

/**
 * Unit tests for TableSharedKeyLiteAuthScheme class.
 *
 * @see       https://github.com/azure/azure-storage-php
 */
class TableSharedKeyLiteAuthSchemeTest extends \PHPUnit\Framework\TestCase
{
    public function testConstruct()
    {
        $expected = [];
        $expected[] = Resources::DATE;

        $mock = new TableSharedKeyLiteAuthSchemeMock(TestResources::ACCOUNT_NAME, TestResources::KEY4);

        self::assertEquals($expected, $mock->getIncludedHeaders());
    }

    public function testComputeSignatureSimple()
    {
        $httpMethod = 'GET';
        $queryParams = [Resources::QP_COMP => 'list'];
        $url = TestResources::URI1;
        $date = TestResources::DATE1;
        $apiVersion = Resources::STORAGE_API_LATEST_VERSION;
        $accountName = TestResources::ACCOUNT_NAME;
        $headers = [Resources::X_MS_DATE => $date, Resources::X_MS_VERSION => $apiVersion];
        $expected = "\n/$accountName" . parse_url($url, PHP_URL_PATH) . '?comp=list';
        $mock = new TableSharedKeyLiteAuthSchemeMock($accountName, TestResources::KEY4);

        $actual = $mock->computeSignatureMock($headers, $url, $queryParams, $httpMethod);

        self::assertEquals($expected, $actual);
    }

    public function testGetAuthorizationHeaderSimple()
    {
        $accountName = TestResources::ACCOUNT_NAME;
        $apiVersion = Resources::STORAGE_API_LATEST_VERSION;
        $accountKey = TestResources::KEY4;
        $url = TestResources::URI2;
        $date1 = TestResources::DATE2;
        $headers = [Resources::X_MS_VERSION => $apiVersion, Resources::X_MS_DATE => $date1];
        $queryParams = [Resources::QP_COMP => 'list'];
        $httpMethod = 'GET';
        $expected = 'SharedKeyLite ' . $accountName . ':KB+TK3FPHLADYwd0/b3PcZgK/fYXUSlwsoOIf80l2co=';

        $mock = new TableSharedKeyLiteAuthSchemeMock($accountName, $accountKey);

        $actual = $mock->getAuthorizationHeader($headers, $url, $queryParams, $httpMethod);

        self::assertEquals($expected, $actual);
    }
}
