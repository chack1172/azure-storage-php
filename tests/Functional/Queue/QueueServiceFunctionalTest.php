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

namespace AzureOSS\Storage\Tests\Functional\Queue;

use AzureOSS\Storage\Common\Exceptions\ServiceException;
use AzureOSS\Storage\Common\LocationMode;
use AzureOSS\Storage\Common\Middlewares\HistoryMiddleware;
use AzureOSS\Storage\Common\Middlewares\RetryMiddlewareFactory;
use AzureOSS\Storage\Queue\Models\CreateMessageOptions;
use AzureOSS\Storage\Queue\Models\CreateQueueOptions;
use AzureOSS\Storage\Queue\Models\ListMessagesOptions;
use AzureOSS\Storage\Queue\Models\ListQueuesOptions;
use AzureOSS\Storage\Queue\Models\PeekMessagesOptions;
use AzureOSS\Storage\Queue\Models\QueueServiceOptions;
use AzureOSS\Storage\Queue\QueueRestProxy;
use AzureOSS\Storage\Tests\Framework\TestResources;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

class QueueServiceFunctionalTest extends FunctionalTestBase
{
    public function testGetServicePropertiesNoOptions()
    {
        $serviceProperties = QueueServiceFunctionalTestData::getDefaultServiceProperties();

        $shouldReturn = false;
        try {
            $this->restProxy->setServiceProperties($serviceProperties);
            self::assertFalse($this->isEmulated(), 'Should succeed when not running in emulator');
        } catch (ServiceException $e) {
            // Expect failure in emulator, as v1.6 doesn't support this method
            if ($this->isEmulated()) {
                self::assertEquals(TestResources::STATUS_BAD_REQUEST, $e->getCode(), 'getCode');
                $shouldReturn = true;
            } else {
                throw $e;
            }
        }
        if ($shouldReturn) {
            return;
        }

        $this->getServicePropertiesWorker(null);
    }

    public function testGetServiceProperties()
    {
        $serviceProperties = QueueServiceFunctionalTestData::getDefaultServiceProperties();

        $shouldReturn = false;
        try {
            $this->restProxy->setServiceProperties($serviceProperties);
            self::assertFalse($this->isEmulated(), 'Should succeed when not running in emulator');
        } catch (ServiceException $e) {
            // Expect failure in emulator, as v1.6 doesn't support this method
            if ($this->isEmulated()) {
                self::assertEquals(TestResources::STATUS_BAD_REQUEST, $e->getCode(), 'getCode');
                $shouldReturn = true;
            } else {
                throw $e;
            }
        }
        if ($shouldReturn) {
            return;
        }

        // Now look at the combos.
        $interestingTimeouts = QueueServiceFunctionalTestData::getInterestingTimeoutValues();
        foreach ($interestingTimeouts as $timeout) {
            $options = new QueueServiceOptions();
            $options->setTimeout($timeout);
            $this->getServicePropertiesWorker($options);
        }
    }

    private function getServicePropertiesWorker($options)
    {
        self::println('Trying $options: ' . self::tmptostring($options));
        $effOptions = (null === $options ? new QueueServiceOptions() : $options);
        try {
            $ret = (null === $options ?
                $this->restProxy->getServiceProperties() :
                $this->restProxy->getServiceProperties($effOptions));

            if (null !== $effOptions->getTimeout() && $effOptions->getTimeout() < 1) {
                $this->true('Expect negative timeouts in $options to throw', false);
            } else {
                self::assertFalse($this->isEmulated(), 'Should succeed when not running in emulator');
            }
            $this->verifyServicePropertiesWorker($ret, null);
        } catch (ServiceException $e) {
            if ($this->isEmulated()) {
                if (null !== $options->getTimeout() && $options->getTimeout() < 0) {
                    self::assertEquals(TestResources::STATUS_INTERNAL_SERVER_ERROR, $e->getCode(), 'getCode');
                } else {
                    // Expect failure in emulator, as v1.6 doesn't support this method
                    self::assertEquals(TestResources::STATUS_BAD_REQUEST, $e->getCode(), 'getCode');
                }
            } elseif (null !== $effOptions->getTimeout() && $effOptions->getTimeout() < 1) {
                self::assertEquals(TestResources::STATUS_INTERNAL_SERVER_ERROR, $e->getCode(), 'getCode');
            } else {
                throw $e;
            }
        }
    }

    private function verifyServicePropertiesWorker($ret, $serviceProperties)
    {
        if (null === $serviceProperties) {
            $serviceProperties = QueueServiceFunctionalTestData::getDefaultServiceProperties();
        }

        $sp = $ret->getValue();
        self::assertNotNull($sp, 'getValue should be non-null');

        $l = $sp->getLogging();
        self::assertNotNull($l, 'getValue()->getLogging() should be non-null');
        self::assertEquals(
            $serviceProperties->getLogging()->getVersion(),
            $l->getVersion(),
            'getValue()->getLogging()->getVersion'
        );
        self::assertEquals(
            $serviceProperties->getLogging()->getDelete(),
            $l->getDelete(),
            'getValue()->getLogging()->getDelete'
        );
        self::assertEquals(
            $serviceProperties->getLogging()->getRead(),
            $l->getRead(),
            'getValue()->getLogging()->getRead'
        );
        self::assertEquals(
            $serviceProperties->getLogging()->getWrite(),
            $l->getWrite(),
            'getValue()->getLogging()->getWrite'
        );

        $r = $l->getRetentionPolicy();
        self::assertNotNull($r, 'getValue()->getLogging()->getRetentionPolicy should be non-null');
        self::assertEquals(
            $serviceProperties->getLogging()->getRetentionPolicy()->getDays(),
            $r->getDays(),
            'getValue()->getLogging()->getRetentionPolicy()->getDays'
        );

        $m = $sp->getHourMetrics();
        self::assertNotNull($m, 'getValue()->getHourMetrics() should be non-null');
        self::assertEquals(
            $serviceProperties->getHourMetrics()->getVersion(),
            $m->getVersion(),
            'getValue()->getHourMetrics()->getVersion'
        );
        self::assertEquals(
            $serviceProperties->getHourMetrics()->getEnabled(),
            $m->getEnabled(),
            'getValue()->getHourMetrics()->getEnabled'
        );
        self::assertEquals(
            $serviceProperties->getHourMetrics()->getIncludeAPIs(),
            $m->getIncludeAPIs(),
            'getValue()->getHourMetrics()->getIncludeAPIs'
        );

        $r = $m->getRetentionPolicy();
        self::assertNotNull($r, 'getValue()->getHourMetrics()->getRetentionPolicy should be non-null');
        self::assertEquals(
            $serviceProperties->getHourMetrics()->getRetentionPolicy()->getDays(),
            $r->getDays(),
            'getValue()->getHourMetrics()->getRetentionPolicy()->getDays'
        );
    }

    public function testSetServiceProperties()
    {
        $interestingServiceProperties = QueueServiceFunctionalTestData::getInterestingServiceProperties();
        foreach ($interestingServiceProperties as $serviceProperties) {
            $interestingTimeouts = QueueServiceFunctionalTestData::getInterestingTimeoutValues();
            foreach ($interestingTimeouts as $timeout) {
                $options = new QueueServiceOptions();
                $options->setTimeout($timeout);
                $this->setServicePropertiesWorker($serviceProperties, $options);
            }
        }

        if (!$this->isEmulated()) {
            $this->restProxy->setServiceProperties($interestingServiceProperties[0]);
        }
    }

    private function setServicePropertiesWorker($serviceProperties, $options)
    {
        try {
            if (null === $options) {
                $this->restProxy->setServiceProperties($serviceProperties);
            } else {
                $this->restProxy->setServiceProperties($serviceProperties, $options);
            }

            if (null === $options) {
                $options = new QueueServiceOptions();
            }

            if (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                self::assertTrue(false, 'Expect negative timeouts in $options to throw');
            } else {
                self::assertFalse($this->isEmulated(), 'Should succeed when not running in emulator');
            }

            \sleep(10);

            $ret = (
                null === $options ?
                $this->restProxy->getServiceProperties() :
                $this->restProxy->getServiceProperties($options)
            );
            $this->verifyServicePropertiesWorker($ret, $serviceProperties);
        } catch (ServiceException $e) {
            if (null === $options) {
                $options = new QueueServiceOptions();
            }

            if ($this->isEmulated()) {
                if (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                    self::assertEquals(TestResources::STATUS_INTERNAL_SERVER_ERROR, $e->getCode(), 'getCode');
                } else {
                    self::assertEquals(TestResources::STATUS_BAD_REQUEST, $e->getCode(), 'getCode');
                }
            } else {
                if (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                    self::assertEquals(TestResources::STATUS_INTERNAL_SERVER_ERROR, $e->getCode(), 'getCode');
                } else {
                    throw $e;
                }
            }
        }
    }

    public function testListQueuesNoOptions()
    {
        $this->listQueuesWorker(null);
    }

    public function testListQueues()
    {
        $interestingListQueuesOptions = QueueServiceFunctionalTestData::getInterestingListQueuesOptions();
        foreach ($interestingListQueuesOptions as $options) {
            $this->listQueuesWorker($options);
        }
    }

    private function listQueuesWorker($options)
    {
        $finished = false;
        while (!$finished) {
            try {
                $ret = (null === $options ? $this->restProxy->listQueues() : $this->restProxy->listQueues($options));

                if (null === $options) {
                    $options = new ListQueuesOptions();
                }

                if (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                    self::assertTrue(
                        false,
                        'Expect negative timeouts ' .
                        $options->getTimeout() .
                        ' in $options to throw'
                    );
                }
                $this->verifyListQueuesWorker($ret, $options);

                if (strlen($ret->getNextMarker()) == 0) {
                    self::println('Done with this loop');
                    $finished = true;
                } else {
                    self::println('Cycling to get the next marker: ' . $ret->getNextMarker());
                    $options->setMarker($ret->getNextMarker());
                }
            } catch (ServiceException $e) {
                $finished = true;
                if (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                    self::assertEquals(TestResources::STATUS_INTERNAL_SERVER_ERROR, $e->getCode(), 'getCode');
                } else {
                    throw $e;
                }
            }
        }
    }

    private function verifyListQueuesWorker($ret, $options)
    {
        // Uncomment when fixed
        // https://github.com/azure/azure-storage-php/issues/98
        //$this->assertEquals($accountName, $ret->getAccountName(), 'getAccountName');

        self::assertEquals($options->getNextMarker(), $ret->getMarker(), 'getNextMarker');
        self::assertEquals($options->getMaxResults(), $ret->getMaxResults(), 'getMaxResults');
        self::assertEquals($options->getPrefix(), $ret->getPrefix(), 'getPrefix');

        self::assertNotNull($ret->getQueues(), 'getQueues');

        if ($options->getMaxResults() == 0) {
            self::assertNull(
                $ret->getNextMarker(),
                'When MaxResults is 0, expect getNextMarker (' .
                $ret->getNextMarker() .
                ')to be null'
            );

            if (null !== $options->getPrefix()
                    && $options->getPrefix() ==
                        QueueServiceFunctionalTestData::$nonExistQueuePrefix) {
                self::assertCount(
                    0,
                    $ret->getQueues(),
                    'when MaxResults=0 and Prefix=(\'' .
                    $options->getPrefix() . '\'), then Queues->length'
                );
            } elseif (null !== $options->getPrefix()
                    && $options->getPrefix() ==
                        QueueServiceFunctionalTestData::$testUniqueId) {
                self::assertEquals(
                    count(QueueServiceFunctionalTestData::$testQueueNames),
                    count($ret->getQueues()),
                    'when MaxResults=0 and Prefix=(\'' .
                    $options->getPrefix() . '\'), then count Queues'
                );
            }
            // Don't know how many there should be

        } elseif (strlen($ret->getNextMarker()) == 0) {
            self::assertTrue(
                count($ret ->getQueues()) <= $options->getMaxResults(),
                'when NextMarker (\'' . $ret->getNextMarker() .
                '\')==\'\', Queues->length (' . count($ret->getQueues()) .
                ') should be <= MaxResults (' . $options->getMaxResults() .
                ')'
            );

            if (null !== $options->getPrefix() && $options->getPrefix() ==
                    QueueServiceFunctionalTestData::$nonExistQueuePrefix) {
                self::assertCount(
                    0,
                    $ret->getQueues(),
                    'when no next marker and Prefix=(\'' .
                    $options->getPrefix() . '\'), then Queues->length'
                );
            } elseif (null !== $options->getPrefix()
                    && $options->getPrefix() ==
                        QueueServiceFunctionalTestData::$testUniqueId) {
                // Need to futz with the mod because you are allowed to get MaxResults items returned.
                self::assertEquals(
                    count(QueueServiceFunctionalTestData::$testQueueNames) %
                        $options->getMaxResults(),
                    count($ret ->getQueues()) % $options->getMaxResults(),
                    'when no next marker and Prefix=(\'' . $options->getPrefix() .
                    '\'), then Queues->length'
                );
            }
            // Don't know how many there should be

        } else {
            self::assertEquals(
                count($ret ->getQueues()),
                $options->getMaxResults(),
                'when NextMarker (' . $ret->getNextMarker() .
                    ')!=\'\', Queues->length (' . count($ret->getQueues()) .
                    ') should be == MaxResults (' . $options->getMaxResults() . ')'
            );

            if (null !== $options->getPrefix()
                    && $options->getPrefix() ==
                        (QueueServiceFunctionalTestData::$nonExistQueuePrefix)) {
                self::assertTrue(
                    false,
                    'when a next marker and Prefix=(\'' .
                        $options->getPrefix() . '\'), impossible'
                );
            }
        }
    }

    public function testCreateQueueNoOptions()
    {
        $this->createQueueWorker(null);
    }

    public function testCreateQueue()
    {
        $interestingCreateQueueOptions = QueueServiceFunctionalTestData::getInterestingCreateQueueOptions();
        foreach ($interestingCreateQueueOptions as $options) {
            $this->createQueueWorker($options);
        }
    }

    private function createQueueWorker($options)
    {
        self::println('Trying $options: ' . self::tmptostring($options));
        $queue = QueueServiceFunctionalTestData::getInterestingQueueName();
        $created = false;

        try {
            if (null === $options) {
                $this->restProxy->createQueue($queue);
            } else {
                // TODO: https://github.com/azure/azure-storage-php/issues/105
                $this->restProxy->createQueue($queue, $options);
            }
            $created = true;

            if (null === $options) {
                $options = new CreateQueueOptions();
            }

            if (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                self::assertTrue(false, 'Expect negative timeouts in $options to throw');
            }

            // Now check that the queue was created correctly.

            // Make sure that the list of all applicable queues is correctly updated.
            $opts = new ListQueuesOptions();
            $opts->setPrefix(QueueServiceFunctionalTestData::$testUniqueId);
            $qs = $this->restProxy->listQueues($opts);
            self::assertEquals(
                count($qs->getQueues()),
                (count(QueueServiceFunctionalTestData::$testQueueNames) + 1),
                'After adding one, with Prefix=(\'' .
                    QueueServiceFunctionalTestData::$testUniqueId . '\'), then Queues->length'
            );

            // Check the metadata on the queue
            $ret = $this->restProxy->getQueueMetadata($queue);
            $this->verifyCreateQueueWorker($ret, $options);
            $this->restProxy->deleteQueue($queue);
            $created = false;
        } catch (ServiceException $e) {
            if (null === $options) {
                $options = new CreateQueueOptions();
            }
            if (null !== $options->getTimeout() && $options->getTimeout() <= 0) {
                self::assertEquals(TestResources::STATUS_INTERNAL_SERVER_ERROR, $e->getCode(), 'getCode');
            } else {
                throw $e;
            }
        }
        if ($created) {
            $this->restProxy->deleteQueue($queue);
        }
    }

    private function verifyCreateQueueWorker($ret, $options)
    {
        self::println('Trying $options: ' . self::tmptostring($options) .
                ' and ret ' . self::tmptostring($ret));
        if (null === $options) {
            $options = QueueServiceFunctionalTestData::getInterestingCreateQueueOptions();
            $options = $options[0];
        }

        if (null === $options->getMetadata()) {
            self::assertNotNull($ret->getMetadata(), 'queue Metadata');
            self::assertCount(0, $ret->getMetadata(), 'queue Metadata count');
        } else {
            self::assertNotNull($ret->getMetadata(), 'queue Metadata');
            self::assertEquals(count($options->getMetadata()), count($ret->getMetadata()), 'Metadata');
            $om = $options->getMetadata();
            $rm = $ret->getMetadata();
            foreach (array_keys($options->getMetadata()) as $key) {
                self::assertEquals($om[$key], $rm[$key], 'Metadata(' . $key . ')');
            }
        }
    }

    public function testDeleteQueueNoOptions()
    {
        $this->deleteQueueWorker(null);
    }

    public function testDeleteQueue()
    {
        $interestingTimeouts = QueueServiceFunctionalTestData::getInterestingTimeoutValues();
        foreach ($interestingTimeouts as $timeout) {
            $options = new QueueServiceOptions();
            $options->setTimeout($timeout);
            $this->deleteQueueWorker($options);
        }
    }

    private function deleteQueueWorker($options)
    {
        self::println('Trying $options: ' . self::tmptostring($options));
        $queue = QueueServiceFunctionalTestData::getInterestingQueueName();

        // Make sure there is something to delete.
        $this->restProxy->createQueue($queue);

        // Make sure that the list of all applicable queues is correctly updated.
        $opts = new ListQueuesOptions();
        $opts->setPrefix(QueueServiceFunctionalTestData::$testUniqueId);
        $qs = $this->restProxy->listQueues($opts);
        self::assertEquals(
            count($qs->getQueues()),
            (count(QueueServiceFunctionalTestData::$testQueueNames) + 1),
            'After adding one, with Prefix=(\'' .
                QueueServiceFunctionalTestData::$testUniqueId .
                '\'), then Queues->length'
        );

        $deleted = false;
        try {
            if (null === $options) {
                $this->restProxy->deleteQueue($queue);
            } else {
                $this->restProxy->deleteQueue($queue, $options);
            }

            $deleted = true;

            if (null === $options) {
                $options = new QueueServiceOptions();
            }

            if (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                self::assertTrue(false, 'Expect negative timeouts in $options to throw');
            }

            // Make sure that the list of all applicable queues is correctly updated.
            $opts = new ListQueuesOptions();
            $opts->setPrefix(QueueServiceFunctionalTestData::$testUniqueId);
            $qs = $this->restProxy->listQueues($opts);
            self::assertEquals(
                count($qs->getQueues()),
                count(QueueServiceFunctionalTestData::$testQueueNames),
                'After adding then deleting one, with Prefix=(\'' .
                    QueueServiceFunctionalTestData::$testUniqueId . '\'),
                then Queues->length'
            );

            // Nothing else interesting to check for the options.
        } catch (ServiceException $e) {
            if (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                self::assertEquals(TestResources::STATUS_INTERNAL_SERVER_ERROR, $e->getCode(), 'getCode');
            } else {
                throw $e;
            }
        }
        if (!$deleted) {
            // Try again. If it doesn't work, not much else to try.
            $this->restProxy->deleteQueue($queue);
        }
    }

    public function testGetQueueMetadataNoOptions()
    {
        $interestingMetadata = QueueServiceFunctionalTestData::getNiceMetadata();
        foreach ($interestingMetadata as $metadata) {
            $this->getQueueMetadataWorker(null, $metadata);
        }
    }

    public function testGetQueueMetadata()
    {
        $interestingTimeouts = QueueServiceFunctionalTestData::getInterestingTimeoutValues();
        $interestingMetadata = QueueServiceFunctionalTestData::getNiceMetadata();

        foreach ($interestingTimeouts as $timeout) {
            foreach ($interestingMetadata as $metadata) {
                $options = new QueueServiceOptions();
                $options->setTimeout($timeout);
                $this->getQueueMetadataWorker($options, $metadata);
            }
        }
    }

    private function getQueueMetadataWorker($options, $metadata)
    {
        self::println('Trying $options: ' . self::tmptostring($options) .
                ' and $metadata: ' . self::tmptostring($metadata));
        $queue = QueueServiceFunctionalTestData::getInterestingQueueName();

        // Make sure there is something to test
        $this->restProxy->createQueue($queue);

        // Put some messages to verify getApproximateMessageCount
        if (null !== $metadata) {
            for ($i = 0; $i < count($metadata); ++$i) {
                $this->restProxy->createMessage($queue, 'message ' . $i);
            }

            // And put in some metadata
            $this->restProxy->setQueueMetadata($queue, $metadata);
        }

        try {
            $res = (
                null === $options ?
                $this->restProxy->getQueueMetadata($queue) :
                $this->restProxy->getQueueMetadata($queue, $options)
            );

            if (null === $options) {
                $options = new QueueServiceOptions();
            }

            if (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                self::assertTrue(false, 'Expect negative timeouts in $options to throw');
            }

            $this->verifyGetSetQueueMetadataWorker($res, $metadata);
        } catch (ServiceException $e) {
            if (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                self::assertEquals(TestResources::STATUS_INTERNAL_SERVER_ERROR, $e->getCode(), 'getCode');
            } else {
                throw $e;
            }
        }
        // Clean up->
        $this->restProxy->deleteQueue($queue);
    }

    private function verifyGetSetQueueMetadataWorker($ret, $metadata)
    {
        self::assertNotNull($ret->getMetadata(), 'queue Metadata');
        if (null === $metadata) {
            self::assertCount(0, $ret->getMetadata(), 'Metadata');
            self::assertEquals(0, $ret->getApproximateMessageCount(), 'getApproximateMessageCount');
        } else {
            self::assertEquals(count($metadata), count($ret->getMetadata()), 'Metadata');
            $rm = $ret->getMetadata();
            foreach (array_keys($metadata) as $key) {
                self::assertEquals($metadata[$key], $rm[$key], 'Metadata(' . $key . ')');
            }

            // Hard to test "approximate", so just verify that it is in the expected range
            self::assertTrue(
                (0 <= $ret->getApproximateMessageCount())
                    && ($ret->getApproximateMessageCount() <= count($metadata)),
                '0 <= getApproximateMessageCount (' .
                $ret->getApproximateMessageCount() . ') <= $metadata count (' .
                count($metadata) . ')'
            );
        }
    }

    public function testSetQueueMetadataNoOptions()
    {
        $interestingMetadata = QueueServiceFunctionalTestData::getInterestingMetadata();
        foreach ($interestingMetadata as $metadata) {
            if (null === $metadata) {
                // This is tested above.
                continue;
            }
            $this->setQueueMetadataWorker(null, $metadata);
        }
    }

    public function testSetQueueMetadata()
    {
        $interestingTimeouts = QueueServiceFunctionalTestData::getInterestingTimeoutValues();
        $interestingMetadata = QueueServiceFunctionalTestData::getInterestingMetadata();

        foreach ($interestingTimeouts as $timeout) {
            foreach ($interestingMetadata as $metadata) {
                if (null === $metadata) {
                    // This is tested above.
                    continue;
                }
                $options = new QueueServiceOptions();
                $options->setTimeout($timeout);
                $this->setQueueMetadataWorker($options, $metadata);
            }
        }
    }

    private function setQueueMetadataWorker($options, $metadata)
    {
        self::println('Trying $options: ' . self::tmptostring($options) .
                ' and $metadata: ' . self::tmptostring($metadata));
        $queue = QueueServiceFunctionalTestData::getInterestingQueueName();

        // Make sure there is something to test
        $this->restProxy->createQueue($queue);

        try {
            // And put in some metadata
            if (null === $options) {
                $this->restProxy->setQueueMetadata($queue, $metadata);
            } else {
                $this->restProxy->setQueueMetadata($queue, $metadata, $options);
            }

            if (null === $options) {
                $options = new QueueServiceOptions();
            }

            if (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                self::assertTrue(false, 'Expect negative timeouts in $options to throw');
            }

            $res = $this->restProxy->getQueueMetadata($queue);
            $this->verifyGetSetQueueMetadataWorker($res, $metadata);
        } catch (ServiceException $e) {
            if (null !== $metadata && count($metadata) > 0) {
                $keypart = array_keys($metadata);
                $keypart = $keypart[0];
                if (substr($keypart, 0, 1) == '<') {
                    self::assertEquals(TestResources::STATUS_BAD_REQUEST, $e->getCode(), 'getCode');
                }
            } elseif (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                self::assertEquals(TestResources::STATUS_INTERNAL_SERVER_ERROR, $e->getCode(), 'getCode');
            } else {
                throw $e;
            }
        }
        // Clean up.
        $this->restProxy->deleteQueue($queue);
    }

    public function testCreateMessageEmpty()
    {
        $this->createMessageWorker('', QueueServiceFunctionalTestData::getSimpleCreateMessageOptions());
    }

    public function testCreateMessageUnicodeMessage()
    {
        $this->createMessageWorker(
            'Some unicode: ' .
                chr(0xEB) . chr(0x8B) . chr(0xA4) . // \uB2E4 in UTF-8
                chr(0xEB) . chr(0xA5) . chr(0xB4) . // \uB974 in UTF-8
                chr(0xEB) . chr(0x8B) . chr(0xA4) . // \uB2E4 in UTF-8
                chr(0xEB) . chr(0x8A) . chr(0x94) . // \uB294 in UTF-8
                chr(0xD8) . chr(0xA5) .             // \u0625 in UTF-8
                ' ' .
                chr(0xD9) . chr(0x8A) .             // \u064A in UTF-8
                chr(0xD8) . chr(0xAF) .             // \u062F in UTF-8
                chr(0xD9) . chr(0x8A) .             // \u064A in UTF-8
                chr(0xD9) . chr(0x88),              // \u0648 in UTF-8
            QueueServiceFunctionalTestData::getSimpleCreateMessageOptions()
        );
    }

    public function testCreateMessageXmlMessage()
    {
        $this->createMessageWorker(
            'Some HTML: <this><is></a>',
            QueueServiceFunctionalTestData::getSimpleCreateMessageOptions()
        );
    }

    public function testCreateMessageWithSmallTTL()
    {
        $queue = QueueServiceFunctionalTestData::$testQueueNames;
        $queue = $queue[0];
        $this->restProxy->clearMessages($queue);
        $messageText = QueueServiceFunctionalTestData::getSimpleMessageText();

        $options = new CreateMessageOptions();
        $options->setVisibilityTimeoutInSeconds(2);
        $options->setTimeToLiveInSeconds('4');

        $this->restProxy->createMessage($queue, $messageText, $options);

        $lmr = $this->restProxy->listMessages($queue);

        // No messages, because it is not visible for 2 seconds.
        self::assertCount(0, $lmr->getQueueMessages(), 'getQueueMessages() count');
        sleep(6);
        // Try again, passed the VisibilityTimeout has passed, but also the 4 second TTL has passed.
        $lmr = $this->restProxy->listMessages($queue);

        self::assertCount(0, $lmr->getQueueMessages(), 'getQueueMessages() count');

        $this->restProxy->clearMessages($queue);
    }

    public function testCreateMessage()
    {
        $interestingTimes = [null, -2, 0, QueueServiceFunctionalTestData::INTERESTING_TTL, 1000];
        foreach ($interestingTimes as $timeToLiveInSeconds) {
            foreach ($interestingTimes as $visibilityTimeoutInSeconds) {
                $timeout = null;
                $options = new CreateMessageOptions();
                $options->setTimeout($timeout);

                $options->setTimeToLiveInSeconds($timeToLiveInSeconds);
                $options->setVisibilityTimeoutInSeconds($visibilityTimeoutInSeconds . '');
                $this->createMessageWorker(QueueServiceFunctionalTestData::getSimpleMessageText(), $options);
            }
        }

        foreach ($interestingTimes as $timeout) {
            $timeToLiveInSeconds = 1000;
            $visibilityTimeoutInSeconds = QueueServiceFunctionalTestData::INTERESTING_TTL;
            $options = new CreateMessageOptions();
            $options->setTimeout($timeout);

            $options->setTimeToLiveInSeconds($timeToLiveInSeconds . '');
            $options->setVisibilityTimeoutInSeconds($visibilityTimeoutInSeconds);
            $this->createMessageWorker(QueueServiceFunctionalTestData::getSimpleMessageText(), $options);
        }
    }

    private function createMessageWorker($messageText, $options)
    {
        self::println('Trying $options: ' . self::tmptostring($options));
        $queue = QueueServiceFunctionalTestData::$testQueueNames;
        $queue = $queue[0];
        $this->restProxy->clearMessages($queue);

        try {
            if (null === $options) {
                $this->restProxy->createMessage($queue, $messageText);
            } else {
                $this->restProxy->createMessage($queue, $messageText, $options);
            }

            if (null === $options) {
                $options = new CreateMessageOptions();
            }

            if (null !== $options->getVisibilityTimeoutInSeconds() && $options->getVisibilityTimeoutInSeconds() < 0) {
                self::assertTrue(false, 'Expect negative getVisibilityTimeoutInSeconds in $options to throw');
            } elseif (null !== $options->getTimeToLiveInSeconds() && $options->getTimeToLiveInSeconds() <= 0) {
                self::assertTrue(false, 'Expect negative getVisibilityTimeoutInSeconds in $options to throw');
            } elseif (null !== $options->getVisibilityTimeoutInSeconds()
                    && null !== $options->getTimeToLiveInSeconds()
                    && $options->getVisibilityTimeoutInSeconds() > 0
                    && $options->getTimeToLiveInSeconds() <= $options->getVisibilityTimeoutInSeconds()) {
                self::assertTrue(
                    false,
                    'Expect getTimeToLiveInSeconds() <= getVisibilityTimeoutInSeconds in $options to throw'
                );
            } elseif (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                self::assertTrue(false, 'Expect negative timeouts in $options to throw');
            }

            // Check that the message matches
            $lmr = $this->restProxy->listMessages($queue);
            if (null !== $options->getVisibilityTimeoutInSeconds() && $options->getVisibilityTimeoutInSeconds() > 0) {
                self::assertCount(0, $lmr->getQueueMessages(), 'getQueueMessages() count');
                sleep(QueueServiceFunctionalTestData::INTERESTING_TTL);
                // Try again, not that the 4 second visibility has passed
                $lmr = $this->restProxy->listMessages($queue);
                if ($options->getVisibilityTimeoutInSeconds() > QueueServiceFunctionalTestData::INTERESTING_TTL) {
                    self::assertCount(0, $lmr->getQueueMessages(), 'getQueueMessages() count');
                } else {
                    self::assertCount(1, $lmr->getQueueMessages(), 'getQueueMessages() count');
                    $qm = $lmr->getQueueMessages();
                    $qm = $qm[0];
                    self::assertEquals($messageText, $qm->getMessageText(), '$qm->getMessageText');
                }
            } else {
                self::assertCount(1, $lmr->getQueueMessages(), 'getQueueMessages() count');
                $qm = $lmr->getQueueMessages();
                $qm = $qm[0];
                self::assertEquals($messageText, $qm->getMessageText(), '$qm->getMessageText');
            }
        } catch (ServiceException $e) {
            if (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                self::assertEquals(TestResources::STATUS_INTERNAL_SERVER_ERROR, $e->getCode(), 'getCode');
            } elseif (null !== $options->getVisibilityTimeoutInSeconds()
                    && $options->getVisibilityTimeoutInSeconds() < 0) {
                // Trying to pass bad metadata
                self::assertEquals(TestResources::STATUS_BAD_REQUEST, $e->getCode(), 'getCode');
            } elseif (null !== $options->getTimeToLiveInSeconds() && $options->getTimeToLiveInSeconds() <= 0) {
                self::assertEquals(TestResources::STATUS_BAD_REQUEST, $e->getCode(), 'getCode');
            } elseif (null !== $options->getVisibilityTimeoutInSeconds()
                    && null !== $options->getTimeToLiveInSeconds()
                    && $options->getVisibilityTimeoutInSeconds() > 0
                    && $options->getTimeToLiveInSeconds() <= $options->getVisibilityTimeoutInSeconds()) {
                self::assertEquals(TestResources::STATUS_BAD_REQUEST, $e->getCode(), 'getCode');
            } else {
                throw $e;
            }
        }
        $this->restProxy->clearMessages($queue);
    }

    public function testUpdateMessageNoOptions()
    {
        $interestingVisibilityTimes = [
            -1,
            0,
            QueueServiceFunctionalTestData::INTERESTING_TTL,
            QueueServiceFunctionalTestData::INTERESTING_TTL * 2,
        ];

        $startingMessage = new CreateMessageOptions();
        $startingMessage->setTimeout(QueueServiceFunctionalTestData::INTERESTING_TTL);
        $startingMessage->setTimeToLiveInSeconds(QueueServiceFunctionalTestData::INTERESTING_TTL * 1.5);

        foreach ($interestingVisibilityTimes as $visibilityTimeoutInSeconds) {
            $this->updateMessageWorker(
                QueueServiceFunctionalTestData::getSimpleMessageText(),
                $startingMessage,
                $visibilityTimeoutInSeconds,
                null
            );
        }
    }

    public function testUpdateMessage()
    {
        $interestingTimes = [null, -1, 0, QueueServiceFunctionalTestData::INTERESTING_TTL, 1000];

        $interestingVisibilityTimes = [
            -1,
            0,
            QueueServiceFunctionalTestData::INTERESTING_TTL,
            QueueServiceFunctionalTestData::INTERESTING_TTL * 2,
        ];

        $startingMessage = new CreateMessageOptions();
        $startingMessage->setTimeout(QueueServiceFunctionalTestData::INTERESTING_TTL);
        $startingMessage->setTimeToLiveInSeconds(QueueServiceFunctionalTestData::INTERESTING_TTL * 1.5);

        foreach ($interestingTimes as $timeout) {
            foreach ($interestingVisibilityTimes as $visibilityTimeoutInSeconds) {
                $options = new QueueServiceOptions();
                $options->setTimeout($timeout);
                $this->updateMessageWorker(
                    QueueServiceFunctionalTestData::getSimpleMessageText(),
                    $startingMessage,
                    $visibilityTimeoutInSeconds,
                    $options
                );
            }
        }
    }

    private function updateMessageWorker($messageText, $startingMessage, $visibilityTimeoutInSeconds, $options)
    {
        self::println('Trying $options: ' . self::tmptostring($options) .
                ' and $visibilityTimeoutInSeconds: ' . $visibilityTimeoutInSeconds);
        $queue = QueueServiceFunctionalTestData::$testQueueNames;
        $queue = $queue[0];
        $this->restProxy->clearMessages($queue);

        $this->restProxy->createMessage(
            $queue,
            QueueServiceFunctionalTestData::getSimpleMessageText(),
            $startingMessage
        );
        $lmr = $this->restProxy->listMessages($queue);
        $m = $lmr->getQueueMessages();
        $m = $m[0];

        try {
            if (null === $options) {
                $this->restProxy->updateMessage(
                    $queue,
                    $m->getMessageId(),
                    $m->getPopReceipt(),
                    $messageText,
                    $visibilityTimeoutInSeconds
                );
            } else {
                $this->restProxy->updateMessage(
                    $queue,
                    $m->getMessageId(),
                    $m->getPopReceipt(),
                    $messageText,
                    $visibilityTimeoutInSeconds,
                    $options
                );
            }

            if (null === $options) {
                $options = new CreateMessageOptions();
            }

            if ($visibilityTimeoutInSeconds < 0) {
                self::assertTrue(false, 'Expect negative getVisibilityTimeoutInSeconds in $options to throw');
            } elseif (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                self::assertTrue(false, 'Expect negative timeouts in $options to throw');
            }

            // Check that the message matches
            $lmr = $this->restProxy->listMessages($queue);
            if ($visibilityTimeoutInSeconds > 0) {
                self::assertCount(0, $lmr->getQueueMessages(), 'getQueueMessages() count');
                sleep(QueueServiceFunctionalTestData::INTERESTING_TTL);
                // Try again, not that the 4 second visibility has passed
                $lmr = $this->restProxy->listMessages($queue);
                //Because no matter how quick the connection and machine is, the
                //execution between updating the visibility timeout and running
                //the following lines require some time. So if the visibility
                //time out is exactly the same value as waited time, the message
                //is considered visable again because the visibility timeout
                //expired.
                if ($visibilityTimeoutInSeconds > QueueServiceFunctionalTestData::INTERESTING_TTL) {
                    self::assertCount(0, $lmr->getQueueMessages(), 'getQueueMessages() count');
                } else {
                    self::assertCount(1, $lmr->getQueueMessages(), 'getQueueMessages() count');
                    $qm = $lmr->getQueueMessages();
                    $qm = $qm[0];
                    self::assertEquals($messageText, $qm->getMessageText(), '$qm->getMessageText');
                }
            } else {
                self::assertCount(1, $lmr->getQueueMessages(), 'getQueueMessages() count');
                $qm = $lmr->getQueueMessages();
                $qm = $qm[0];
                self::assertEquals($messageText, $qm->getMessageText(), '$qm->getMessageText');
            }
        } catch (ServiceException $e) {
            if (null === $options) {
                $options = new CreateMessageOptions();
            }

            if (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                self::assertEquals(TestResources::STATUS_INTERNAL_SERVER_ERROR, $e->getCode(), 'getCode');
            } elseif ($visibilityTimeoutInSeconds < 0) {
                // Trying to pass bad metadata
                self::assertEquals(TestResources::STATUS_BAD_REQUEST, $e->getCode(), 'getCode');
            } else {
                throw $e;
            }
        }
        $this->restProxy->clearMessages($queue);
    }

    public function testDeleteMessageNoOptions()
    {
        $this->deleteMessageWorker(null);
    }

    public function testDeleteMessage()
    {
        $interestingTimes = [null, -1, 0, QueueServiceFunctionalTestData::INTERESTING_TTL, 1000];
        foreach ($interestingTimes as $timeout) {
            $options = new QueueServiceOptions();
            $options->setTimeout($timeout);
            $this->deleteMessageWorker($options);
        }
    }

    private function deleteMessageWorker($options)
    {
        self::println('Trying $options: ' . self::tmptostring($options));
        $queue = QueueServiceFunctionalTestData::$testQueueNames;
        $queue = $queue[0];
        $this->restProxy->clearMessages($queue);

        $this->restProxy->createMessage($queue, 'test');
        $opts = new ListMessagesOptions();
        $opts->setVisibilityTimeoutInSeconds(QueueServiceFunctionalTestData::INTERESTING_TTL);
        $lmr = $this->restProxy->listMessages($queue, $opts);
        $m = $lmr->getQueueMessages();
        $m = $m[0];

        try {
            if (null === $options) {
                $this->restProxy->deleteMessage($queue, $m->getMessageId(), $m->getPopReceipt());
            } else {
                $this->restProxy->deleteMessage($queue, $m->getMessageId(), $m->getPopReceipt(), $options);
            }

            if (null === $options) {
                $options = new CreateMessageOptions();
            } elseif (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                self::assertTrue(false, 'Expect negative timeouts in $options to throw');
            }

            // Check that the message matches
            $lmr = $this->restProxy->listMessages($queue);
            self::assertCount(0, $lmr->getQueueMessages(), 'getQueueMessages() count');

            // Wait until the popped message should be visible again.
            sleep(QueueServiceFunctionalTestData::INTERESTING_TTL + 1);
            // Try again, to make sure the message really is gone.
            $lmr = $this->restProxy->listMessages($queue);
            self::assertCount(0, $lmr->getQueueMessages(), 'getQueueMessages() count');
        } catch (ServiceException $e) {
            if (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                self::assertEquals(TestResources::STATUS_INTERNAL_SERVER_ERROR, $e->getCode(), 'getCode');
            } else {
                throw $e;
            }
        }
        $this->restProxy->clearMessages($queue);
    }

    public function testListMessagesNoOptions()
    {
        $this->listMessagesWorker(new ListMessagesOptions());
    }

    public function testListMessages()
    {
        $interestingTimes = [null, -1, 0, QueueServiceFunctionalTestData::INTERESTING_TTL, 1000];
        $interestingNums = [null, -1, 0, 2, 10, 1000];
        foreach ($interestingNums as $numberOfMessages) {
            foreach ($interestingTimes as $visibilityTimeoutInSeconds) {
                $options = new ListMessagesOptions();
                $options->setNumberOfMessages($numberOfMessages);
                $options->setVisibilityTimeoutInSeconds($visibilityTimeoutInSeconds);
                $this->listMessagesWorker($options);
            }
        }

        foreach ($interestingTimes as $timeout) {
            $options = new ListMessagesOptions();
            $options->setTimeout($timeout);
            $options->setNumberOfMessages(2);
            $options->setVisibilityTimeoutInSeconds(2);
            $this->listMessagesWorker($options);
        }
    }

    private function listMessagesWorker($options)
    {
        self::println('Trying $options: ' . self::tmptostring($options));
        $queue = QueueServiceFunctionalTestData::$testQueueNames;
        $queue = $queue[0];
        $this->restProxy->clearMessages($queue);

        // Put three messages into the queue.
        $this->restProxy->createMessage($queue, QueueServiceFunctionalTestData::getSimpleMessageText());
        $this->restProxy->createMessage($queue, QueueServiceFunctionalTestData::getSimpleMessageText());
        $this->restProxy->createMessage($queue, QueueServiceFunctionalTestData::getSimpleMessageText());

        // Default is 1 message
        $effectiveNumOfMessages = (null === $options || null === $options->getNumberOfMessages() ?
            1 : $options ->getNumberOfMessages());
        $effectiveNumOfMessages = ($effectiveNumOfMessages < 0 ? 0 : $effectiveNumOfMessages);

        // Default is 30 seconds
        $effectiveVisTimeout = (null === $options || null === $options->getVisibilityTimeoutInSeconds() ?
            30 : $options ->getVisibilityTimeoutInSeconds());
        $effectiveVisTimeout = ($effectiveVisTimeout < 0 ? 0 : $effectiveVisTimeout);

        $expectedNumMessagesFirst = ($effectiveNumOfMessages > 3 ? 3 : $effectiveNumOfMessages);
        $expectedNumMessagesSecond = ($effectiveVisTimeout <= 2 ? 3 : 3 - $effectiveNumOfMessages);
        $expectedNumMessagesSecond = ($expectedNumMessagesSecond < 0 ? 0 : $expectedNumMessagesSecond);

        try {
            $res = (null === $options ?
                $this->restProxy->listMessages($queue) :
                $this->restProxy->listMessages($queue, $options));

            if (null === $options) {
                $options = new ListMessagesOptions();
            }

            if (null !== $options->getVisibilityTimeoutInSeconds() && $options->getVisibilityTimeoutInSeconds() < 1) {
                self::assertTrue(false, 'Expect non-positive getVisibilityTimeoutInSeconds in $options to throw');
            } elseif (null !== $options->getNumberOfMessages()
                    && ($options->getNumberOfMessages() < 1 || $options->getNumberOfMessages() > 32)) {
                self::assertTrue(false, 'Expect  getNumberOfMessages < 1 or 32 < numMessages in $options to throw');
            } elseif (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                self::assertTrue(false, 'Expect negative timeouts in $options to throw');
            }

            self::assertCount(
                $expectedNumMessagesFirst,
                $res->getQueueMessages(),
                'list getQueueMessages() count'
            );
            $opts = new PeekMessagesOptions();
            $opts->setNumberOfMessages(32);
            $pres = $this->restProxy->peekMessages($queue, $opts);
            self::assertEquals(
                3 - $expectedNumMessagesFirst,
                count($pres->getQueueMessages()),
                'peek getQueueMessages() count'
            );

            // The visibilityTimeoutInSeconds controls when the requested messages will be visible again.
            // Wait 2.5 seconds to see when the messages are visible again.
            sleep(2.5);
            $opts = new ListMessagesOptions();
            $opts->setNumberOfMessages(32);
            $res2 = $this->restProxy->listMessages($queue, $opts);
            self::assertCount(
                $expectedNumMessagesSecond,
                $res2->getQueueMessages(),
                'list getQueueMessages() count'
            );
            $opts = new PeekMessagesOptions();
            $opts->setNumberOfMessages(32);
            $pres2 = $this->restProxy->peekMessages($queue, $opts);
            self::assertCount(0, $pres2->getQueueMessages(), 'peek getQueueMessages() count');

            // TODO: These might get screwy if the timing gets off. Might need to use times spaces farther apart.
        } catch (ServiceException $e) {
            if (null === $options) {
                $options = new ListMessagesOptions();
            }

            if (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                self::assertEquals(TestResources::STATUS_INTERNAL_SERVER_ERROR, $e->getCode(), 'getCode');
            } elseif (null !== $options->getVisibilityTimeoutInSeconds()
                && $options->getVisibilityTimeoutInSeconds() < 1) {
                self::assertEquals(TestResources::STATUS_BAD_REQUEST, $e->getCode(), 'getCode');
            } elseif (null !== $options->getNumberOfMessages()
                && ($options->getNumberOfMessages() < 1 || $options->getNumberOfMessages() > 32)) {
                self::assertEquals(TestResources::STATUS_BAD_REQUEST, $e->getCode(), 'getCode');
            } else {
                throw $e;
            }
        }
        $this->restProxy->clearMessages($queue);
    }

    public function testPeekMessagesNoOptions()
    {
        $this->peekMessagesWorker(new PeekMessagesOptions());
    }

    public function testPeekMessages()
    {
        $interestingTimes = [null, -1, 0, QueueServiceFunctionalTestData::INTERESTING_TTL, 1000];
        $interestingNums = [null, -1, 0, 2, 10, 1000];
        foreach ($interestingNums as $numberOfMessages) {
            $options = new PeekMessagesOptions();
            $options->setNumberOfMessages($numberOfMessages);
            $this->peekMessagesWorker($options);
        }

        foreach ($interestingTimes as $timeout) {
            $options = new PeekMessagesOptions();
            $options->setTimeout($timeout);
            $options->setNumberOfMessages(2);
            $this->peekMessagesWorker($options);
        }
    }

    private function peekMessagesWorker($options)
    {
        self::println('Trying $options: ' . self::tmptostring($options));
        $queue = QueueServiceFunctionalTestData::$testQueueNames;
        $queue = $queue[0];
        $this->restProxy->clearMessages($queue);

        // Put three messages into the queue.
        $this->restProxy->createMessage($queue, QueueServiceFunctionalTestData::getSimpleMessageText());
        $this->restProxy->createMessage($queue, QueueServiceFunctionalTestData::getSimpleMessageText());
        $this->restProxy->createMessage($queue, QueueServiceFunctionalTestData::getSimpleMessageText());

        // Default is 1 message
        $effectiveNumOfMessages = (null === $options
            || null === $options->getNumberOfMessages() ? 1 : $options ->getNumberOfMessages());
        $effectiveNumOfMessages = ($effectiveNumOfMessages < 0 ? 0 : $effectiveNumOfMessages);

        $expectedNumMessagesFirst = ($effectiveNumOfMessages > 3 ? 3 : $effectiveNumOfMessages);

        try {
            $res = (null === $options ? $this->restProxy->peekMessages($queue) :
                $this->restProxy->peekMessages($queue, $options));

            if (null === $options) {
                $options = new PeekMessagesOptions();
            }

            if (null !== $options->getNumberOfMessages()
                && ($options->getNumberOfMessages() < 1
                || $options->getNumberOfMessages() > 32)) {
                self::assertTrue(false, 'Expect  getNumberOfMessages < 1 or 32 < numMessages in $options to throw');
            } elseif (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                self::assertTrue(false, 'Expect negative timeouts in $options to throw');
            }

            self::assertCount($expectedNumMessagesFirst, $res->getQueueMessages(), 'getQueueMessages() count');
            $opts = new PeekMessagesOptions();
            $opts->setNumberOfMessages(32);
            $res2 = $this->restProxy->peekMessages($queue, $opts);
            self::assertCount(3, $res2->getQueueMessages(), 'getQueueMessages() count');
            $this->restProxy->listMessages($queue);
            $opts = new PeekMessagesOptions();
            $opts->setNumberOfMessages(32);
            $res3 = $this->restProxy->peekMessages($queue, $opts);
            self::assertCount(2, $res3->getQueueMessages(), 'getQueueMessages() count');
        } catch (ServiceException $e) {
            if (null === $options) {
                $options = new PeekMessagesOptions();
            }

            if (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                self::assertEquals(TestResources::STATUS_INTERNAL_SERVER_ERROR, $e->getCode(), 'getCode');
            } elseif (null !== $options->getNumberOfMessages()
                && ($options->getNumberOfMessages() < 1 || $options->getNumberOfMessages() > 32)) {
                self::assertEquals(TestResources::STATUS_BAD_REQUEST, $e->getCode(), 'getCode');
            } else {
                throw $e;
            }
        }
        $this->restProxy->clearMessages($queue);
    }

    public function testClearMessagesNoOptions()
    {
        $this->clearMessagesWorker(null);
    }

    public function testClearMessages()
    {
        $interestingTimes = [null, -1, 0, QueueServiceFunctionalTestData::INTERESTING_TTL, 1000];
        foreach ($interestingTimes as $timeout) {
            $options = new QueueServiceOptions();
            $options->setTimeout($timeout);
            $this->clearMessagesWorker($options);
        }
    }

    private function clearMessagesWorker($options)
    {
        self::println('Trying $options: ' .
                self::tmptostring($options));
        $queue = QueueServiceFunctionalTestData::$testQueueNames;
        $queue = $queue[0];
        $this->restProxy->clearMessages($queue);

        // Put three messages into the queue.
        $this->restProxy->createMessage($queue, QueueServiceFunctionalTestData::getSimpleMessageText());
        $this->restProxy->createMessage($queue, QueueServiceFunctionalTestData::getSimpleMessageText());
        $this->restProxy->createMessage($queue, QueueServiceFunctionalTestData::getSimpleMessageText());
        // Wait a bit to make sure the messages are there.
        sleep(1);
        // Make sure the messages are there, and use a short visibility timeout
        // to make sure the are visible again later.
        $opts = new ListMessagesOptions();
        $opts->setVisibilityTimeoutInSeconds(1);
        $opts->setNumberOfMessages(32);
        $lmr = $this->restProxy->listMessages($queue, $opts);
        self::assertCount(3, $lmr->getQueueMessages(), 'getQueueMessages() count');
        sleep(2);
        try {
            if (null === $options) {
                $this->restProxy->clearMessages($queue);
            } else {
                $this->restProxy->clearMessages($queue, $options);
            }

            if (null === $options) {
                $options = new CreateMessageOptions();
            } elseif (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                self::assertTrue(false, 'Expect negative timeouts in $options to throw');
            }

            // Wait 2 seconds to make sure the messages would be visible again.
            $opts = new ListMessagesOptions();
            $opts->setVisibilityTimeoutInSeconds(1);
            $opts->setNumberOfMessages(32);
            $lmr = $this->restProxy->listMessages($queue, $opts);
            self::assertCount(0, $lmr->getQueueMessages(), 'getQueueMessages() count');
        } catch (ServiceException $e) {
            if (null === $options) {
                $options = new CreateMessageOptions();
            }

            if (null !== $options->getTimeout() && $options->getTimeout() < 1) {
                self::assertEquals(TestResources::STATUS_INTERNAL_SERVER_ERROR, $e->getCode(), 'getCode');
            } else {
                throw $e;
            }
        }
        $this->restProxy->clearMessages($queue);
    }

    public function testMiddlewares()
    {
        //setup middlewares.
        $historyMiddleware = new HistoryMiddleware();
        $retryMiddleware = RetryMiddlewareFactory::create(
            RetryMiddlewareFactory::GENERAL_RETRY_TYPE,
            3,
            1
        );

        //setup options for the first try.
        $options = new ListQueuesOptions();
        $options->setMiddlewares([$historyMiddleware]);
        //get the response of the server.
        $result = $this->restProxy->listQueues($options);
        $response = $historyMiddleware->getHistory()[0]['response'];
        $request = $historyMiddleware->getHistory()[0]['request'];

        //setup the mock handler
        $mock = MockHandler::createWithMiddleware([
            new RequestException(
                'mock 408 exception',
                $request,
                new Response(408, ['test_header' => 'test_header_value'])
            ),
            new Response(500, ['test_header' => 'test_header_value']),
            $response,
        ]);
        $restOptions = ['http' => ['handler' => $mock]];
        $mockProxy = QueueRestProxy::createQueueService($this->connectionString, $restOptions);
        //test using mock handler.
        $options = new ListQueuesOptions();
        $options->setMiddlewares([$retryMiddleware, $historyMiddleware]);
        $newResult = $mockProxy->listQueues($options);
        self::assertTrue(
            $result == $newResult,
            'Mock result does not match server behavior'
        );
        self::assertTrue(
            $historyMiddleware->getHistory()[1]['reason']->getMessage() == 'mock 408 exception',
            'Mock handler does not gave the first 408 exception correctly'
        );
        self::assertTrue(
            $historyMiddleware->getHistory()[2]['reason']->getCode() == 500,
            'Mock handler does not gave the second 500 response correctly'
        );
    }

    public function testRetryFromSecondary()
    {
        //setup middlewares.
        $historyMiddleware = new HistoryMiddleware();
        $retryMiddleware = RetryMiddlewareFactory::create(
            RetryMiddlewareFactory::GENERAL_RETRY_TYPE,
            3,
            1
        );

        //setup options for the first try.
        $options = new ListQueuesOptions();
        $options->setMiddlewares([$historyMiddleware]);
        //get the response of the server.
        $result = $this->restProxy->listQueues($options);
        $response = $historyMiddleware->getHistory()[0]['response'];
        $request = $historyMiddleware->getHistory()[0]['request'];

        //setup the mock handler
        $mock = MockHandler::createWithMiddleware([
            new Response(500, ['test_header' => 'test_header_value']),
            new RequestException(
                'mock 404 exception',
                $request,
                new Response(404, ['test_header' => 'test_header_value'])
            ),
            $response,
        ]);
        $restOptions = ['http' => ['handler' => $mock]];
        $mockProxy = QueueRestProxy::createQueueService($this->connectionString, $restOptions);
        //test using mock handler.
        $options = new ListQueuesOptions();
        $options->setMiddlewares([$retryMiddleware, $historyMiddleware]);
        $options->setLocationMode(LocationMode::PRIMARY_THEN_SECONDARY);
        $newResult = $mockProxy->listQueues($options);
        self::assertTrue(
            $result == $newResult,
            'Mock result does not match server behavior'
        );
        self::assertTrue(
            $historyMiddleware->getHistory()[2]['reason']->getMessage() == 'mock 404 exception',
            'Mock handler does not gave the first 404 exception correctly'
        );
        self::assertTrue(
            $historyMiddleware->getHistory()[1]['reason']->getCode() == 500,
            'Mock handler does not gave the second 500 response correctly'
        );

        $uri2 = (string) ($historyMiddleware->getHistory()[2]['request']->getUri());
        $uri3 = (string) ($historyMiddleware->getHistory()[3]['request']->getUri());

        self::assertTrue(
            strpos($uri2, '-secondary') !== false,
            'Did not retry to secondary uri.'
        );
        self::assertFalse(
            strpos($uri3, '-secondary'),
            'Did not switch back to primary uri.'
        );
    }
}
