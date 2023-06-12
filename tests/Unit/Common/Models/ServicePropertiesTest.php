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

namespace MicrosoftAzure\Storage\Tests\Unit\Common\Models;

use MicrosoftAzure\Storage\Common\Internal\Serialization\XmlSerializer;
use MicrosoftAzure\Storage\Common\Internal\Utilities;
use MicrosoftAzure\Storage\Common\Models\GetServicePropertiesResult;
use MicrosoftAzure\Storage\Common\Models\Logging;
use MicrosoftAzure\Storage\Common\Models\Metrics;
use MicrosoftAzure\Storage\Common\Models\ServiceProperties;
use MicrosoftAzure\Storage\Tests\Framework\TestResources;

/**
 * Unit tests for class ServiceProperties
 *
 * @see      https://github.com/azure/azure-storage-php
 */
class ServicePropertiesTest extends \PHPUnit\Framework\TestCase
{
    public function testCreate()
    {
        // Setup
        $sample = TestResources::getServicePropertiesSample();
        $logging = Logging::create($sample['Logging']);
        $metrics = Metrics::create($sample['HourMetrics']);

        // Test
        $result = ServiceProperties::create($sample);

        // Assert
        self::assertEquals($logging, $result->getLogging());
        self::assertEquals($metrics, $result->getHourMetrics());
    }

    public function testSetLogging()
    {
        // Setup
        $sample = TestResources::getServicePropertiesSample();
        $logging = Logging::create($sample['Logging']);
        $result = new ServiceProperties();

        // Test
        $result->setLogging($logging);

        // Assert
        self::assertEquals($logging, $result->getLogging());
    }

    public function testGetLogging()
    {
        // Setup
        $sample = TestResources::getServicePropertiesSample();
        $logging = Logging::create($sample['Logging']);
        $result = new ServiceProperties();
        $result->setLogging($logging);

        // Test
        $actual = $result->getLogging($logging);

        // Assert
        self::assertEquals($logging, $actual);
    }

    public function testSetHourMetrics()
    {
        // Setup
        $sample = TestResources::getServicePropertiesSample();
        $metrics = Metrics::create($sample['HourMetrics']);
        $result = new ServiceProperties();

        // Test
        $result->setHourMetrics($metrics);

        // Assert
        self::assertEquals($metrics, $result->getHourMetrics());
    }

    public function testGetHourMetrics()
    {
        // Setup
        $sample = TestResources::getServicePropertiesSample();
        $metrics = Metrics::create($sample['HourMetrics']);
        $result = new ServiceProperties();
        $result->setHourMetrics($metrics);

        // Test
        $actual = $result->getHourMetrics($metrics);

        // Assert
        self::assertEquals($metrics, $actual);
    }

    public function testToArray()
    {
        // Setup
        $properties = ServiceProperties::create(TestResources::getServicePropertiesSample());
        $corsesArray = [];
        if (count($properties->getCorses()) == 1) {
            $corsesArray = ['CorsRule' => $properties->getCorses()[0]->toArray()];
        } else {
            foreach ($properties->getCorses() as $cors) {
                $corsesArray[] = ['CorsRule' => $cors->toArray()];
            }
        }

        $expected = [
            'Logging' => $properties->getLogging()->toArray(),
            'HourMetrics' => $properties->getHourMetrics()->toArray(),
            'MinuteMetrics' => $properties->getMinuteMetrics()->toArray(),
            'Cors' => !empty($corsesArray) ? $corsesArray : null,
        ];

        // Test
        $actual = $properties->toArray();

        // Assert
        self::assertEquals($expected, $actual);
    }

    public function testToXml()
    {
        // Setup
        $properties = ServiceProperties::create(TestResources::getServicePropertiesSample());
        $xmlSerializer = new XmlSerializer();

        // Test
        $actual = $properties->toXml($xmlSerializer);

        // Assert
        $actualParsed = Utilities::unserialize($actual);
        $actualProperties = GetServicePropertiesResult::create($actualParsed);
        self::assertEquals($actualProperties->getValue(), $properties);
    }
}
