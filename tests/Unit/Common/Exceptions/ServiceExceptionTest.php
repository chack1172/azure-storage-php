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

namespace AzureOSS\Storage\Tests\Unit\Common\Exceptions;

use AzureOSS\Storage\Common\Exceptions\ServiceException;
use AzureOSS\Storage\Tests\Framework\TestResources;

/**
 * Unit tests for class ServiceException
 *
 * @see      https://github.com/azure/azure-storage-php
 */
class ServiceExceptionTest extends \PHPUnit\Framework\TestCase
{
    public function testConstruct()
    {
        // Setup
        $response = TestResources::getFailedResponse(400, 'test info');

        // Test
        $e = new ServiceException($response);

        // Assert
        self::assertEquals(400, $e->getCode());
        self::assertEquals('test info', $e->getErrorText());
        self::assertEquals($response, $e->getResponse());
    }

    public function testGetErrorText()
    {
        // Setup
        $response = TestResources::getFailedResponse(210, 'test info');
        $e = new ServiceException($response);

        // Test
        $actualError = $e->getErrorText();
        // Assert
        self::assertEquals('test info', $actualError);
    }

    public function testGetErrorMessage()
    {
        // Setup
        $response = TestResources::getFailedResponse(210, 'test info');
        $e = new ServiceException($response);

        // Test
        $actualErrorMessage = $e->getErrorMessage();

        // Assert
        self::assertEquals($actualErrorMessage, TestResources::ERROR_MESSAGE);
    }

    public function testGetRequestID()
    {
        // Setup
        $response = TestResources::getFailedResponse(210, 'test info');
        $e = new ServiceException($response);

        // Assert
        self::assertEquals($e->getRequestID(), TestResources::REQUEST_ID1);
    }

    public function testGetDate()
    {
        // Setup
        $response = TestResources::getFailedResponse(210, 'test info');
        $e = new ServiceException($response);

        // Assert
        self::assertEquals($e->getDate(), TestResources::DATE1);
    }

    public function testGetResponse()
    {
        // Setup
        $response = TestResources::getFailedResponse(210, 'test info');
        $e = new ServiceException($response);

        // Assert
        self::assertEquals($e->getResponse(), $response);
    }

    public function testNoWarningForNonXmlErrorMessage()
    {
        // Warnings are silenced in parseErrorMessage once they are converted to exceptions
        // \PHPUnit\Framework\Error\Warning::$enabled = false;

        // Setup
        $response = TestResources::getFailedResponseJson(210, 'test info');
        $e = new ServiceException($response);

        // Assert
        self::assertEquals($e->getErrorMessage(), TestResources::RESPONSE_BODY_JSON);
    }
}
