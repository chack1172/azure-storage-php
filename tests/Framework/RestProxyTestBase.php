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

namespace AzureOSS\Storage\Tests\Framework;

use AzureOSS\Storage\Common\Internal\Serialization\XmlSerializer;
use AzureOSS\Storage\Common\Logger;

/**
 * Testbase for all REST proxy tests.
 *
 * @see      https://github.com/azure/azure-storage-php
 */
class RestProxyTestBase extends \PHPUnit\Framework\TestCase
{
    protected $restProxy;
    protected $xmlSerializer;

    protected function getTestName()
    {
        return sprintf('onesdkphp%04x', mt_rand(0, 65535));
    }

    public static function assertHandler($file, $line, $code)
    {
        echo "Assertion Failed:\n
            File '$file'\n
            Line '$line'\n
            Code '$code'\n";
    }

    public function __construct()
    {
        if (method_exists(get_parent_class(), '__construct')) {
            parent::__construct(...func_get_args());
        }
        $this->xmlSerializer = new XmlSerializer();
        Logger::setLogFile('C:\log.txt');

        // Enable PHP asserts
        assert_options(ASSERT_ACTIVE, 1);
        assert_options(ASSERT_WARNING, 0);
        assert_options(ASSERT_CALLBACK, 'AzureOSS\Storage\Tests\Framework\RestProxyTestBase::assertHandler');
    }

    public function setProxy($serviceRestProxy)
    {
        $this->restProxy = $serviceRestProxy;
    }

    protected function onNotSuccessfulTest(\Throwable $e): void
    {
        parent::onNotSuccessfulTest($e);

        $this->tearDown();
        throw $e;
    }
}
