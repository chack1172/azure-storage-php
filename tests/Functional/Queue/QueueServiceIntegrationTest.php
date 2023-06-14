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
use AzureOSS\Storage\Queue\Models\CreateQueueOptions;
use AzureOSS\Storage\Queue\Models\ListMessagesOptions;
use AzureOSS\Storage\Queue\Models\ListQueuesOptions;
use AzureOSS\Storage\Queue\Models\PeekMessagesOptions;
use AzureOSS\Storage\Tests\Framework\TestResources;

class QueueServiceIntegrationTest extends IntegrationTestBase
{
    private static $testQueuesPrefix = 'sdktest-';
    private static $createableQueuesPrefix = 'csdktest-';
    private static $testQueueForMessages;
    private static $testQueueForMessages2;
    private static $testQueueForMessages3;
    private static $testQueueForMessages4;
    private static $testQueueForMessages5;
    private static $testQueueForMessages6;
    private static $testQueueForMessages7;
    private static $testQueueForMessages8;
    private static $creatableQueue1;
    private static $creatableQueue2;
    private static $creatableQueue3;
    private static $creatableQueues;
    private static $testQueues;

    protected function setUp(): void
    {
        parent::setUp();
        // Setup container names array (list of container names used by
        // integration tests)
        self::$testQueues = [];
        $rint = mt_rand(0, 1000000);
        for ($i = 0; $i < 10; ++$i) {
            self::$testQueues[$i] = self::$testQueuesPrefix . $rint . ($i + 1);
        }

        self::$creatableQueues = [];
        for ($i = 0; $i < 3; ++$i) {
            self::$creatableQueues[$i] = self::$createableQueuesPrefix . $rint . ($i + 1);
        }

        self::$testQueueForMessages = self::$testQueues[0];
        self::$testQueueForMessages2 = self::$testQueues[1];
        self::$testQueueForMessages3 = self::$testQueues[2];
        self::$testQueueForMessages4 = self::$testQueues[3];
        self::$testQueueForMessages5 = self::$testQueues[4];
        self::$testQueueForMessages6 = self::$testQueues[5];
        self::$testQueueForMessages7 = self::$testQueues[6];
        self::$testQueueForMessages8 = self::$testQueues[7];

        self::$creatableQueue1 = self::$creatableQueues[0];
        self::$creatableQueue2 = self::$creatableQueues[1];
        self::$creatableQueue3 = self::$creatableQueues[2];

        // Create all test containers and their content

        self::createQueues(self::$testQueuesPrefix, self::$testQueues);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        self::deleteQueues(self::$testQueuesPrefix, self::$testQueues);
        self::deleteQueues(self::$createableQueuesPrefix, self::$creatableQueues);
    }

    private function createQueues($prefix, $list)
    {
        $containers = self::listQueues($prefix);
        foreach ($list as $item) {
            if (!in_array($item, $containers, true)) {
                $this->restProxy->createQueue($item);
            }
        }
    }

    private function deleteQueues($prefix, $list)
    {
        $containers = self::listQueues($prefix);
        foreach ($list as $item) {
            if (in_array($item, $containers, true)) {
                $this->restProxy->deleteQueue($item);
            }
        }
    }

    private function listQueues($prefix)
    {
        $result = [];
        $opts = new ListQueuesOptions();
        $opts->setPrefix($prefix);
        $list = $this->restProxy->listQueues($opts);
        foreach ($list->getQueues() as $item) {
            array_push($result, $item->getName());
        }
        return $result;
    }

    public function testGetServicePropertiesWorks()
    {
        // Arrange

        // Act
        $shouldReturn = false;
        try {
            $props = $this->restProxy->getServiceProperties()->getValue();
            self::assertFalse($this->isEmulated(), 'Should succeed when not running in emulator');
        } catch (ServiceException $e) {
            // Expect failure in emulator, as v1.6 doesn't support this method
            if ($this->isEmulated()) {
                self::assertEquals(TestResources::STATUS_BAD_REQUEST, $e->getCode(), 'getCode');
                $shouldReturn = true;
            }
        }
        if ($shouldReturn) {
            return;
        }

        // Assert
        self::assertNotNull($props, '$props');
        self::assertNotNull($props->getLogging(), '$props->getLogging');
        self::assertNotNull($props->getLogging()->getRetentionPolicy(), '$props->getLogging()->getRetentionPolicy');
        self::assertNotNull($props->getLogging()->getVersion(), '$props->getLogging()->getVersion');
        self::assertNotNull($props->getHourMetrics()->getRetentionPolicy(), '$props->getHourMetrics()->getRetentionPolicy');
        self::assertNotNull($props->getHourMetrics()->getVersion(), '$props->getHourMetrics()->getVersion');
    }

    public function testSetServicePropertiesWorks()
    {
        // Arrange

        // Act
        $shouldReturn = false;
        try {
            $props = $this->restProxy->getServiceProperties()->getValue();
            self::assertFalse($this->isEmulated(), 'Should succeed when not running in emulator');
        } catch (ServiceException $e) {
            // Expect failure in emulator, as v1.6 doesn't support this method
            if ($this->isEmulated()) {
                self::assertEquals(TestResources::STATUS_BAD_REQUEST, $e->getCode(), 'getCode');
                $shouldReturn = true;
            }
        }
        if ($shouldReturn) {
            return;
        }

        $props->getLogging()->setRead(true);
        $this->restProxy->setServiceProperties($props);

        $props = $this->restProxy->getServiceProperties()->getValue();

        // Assert
        self::assertNotNull($props, '$props');
        self::assertNotNull($props->getLogging(), '$props->getLogging');
        self::assertNotNull($props->getLogging()->getRetentionPolicy(), '$props->getLogging()->getRetentionPolicy');
        self::assertNotNull($props->getLogging()->getVersion(), '$props->getLogging()->getVersion');
        self::assertTrue($props->getLogging()->getRead(), '$props->getLogging()->getRead');
        self::assertNotNull($props->getHourMetrics()->getRetentionPolicy(), '$props->getHourMetrics()->getRetentionPolicy');
        self::assertNotNull($props->getHourMetrics()->getVersion(), '$props->getHourMetrics()->getVersion');
    }

    public function testCreateQueueWorks()
    {
        // Arrange

        // Act
        $this->restProxy->createQueue(self::$creatableQueue1);
        $result = $this->restProxy->getQueueMetadata(self::$creatableQueue1);
        $this->restProxy->deleteQueue(self::$creatableQueue1);

        // Assert
        self::assertNotNull($result, 'result');
        self::assertEquals(0, $result->getApproximateMessageCount(), '$result->getApproximateMessageCount');
        self::assertNotNull($result->getMetadata(), '$result->getMetadata');
        self::assertCount(0, $result->getMetadata(), 'count($result->getMetadata');
    }

    public function testCreateQueueWithOptionsWorks()
    {
        // Arrange

        // Act
        $opts = new CreateQueueOptions();
        $opts->addMetadata('foo', 'bar');
        $opts->addMetadata('test', 'blah');
        $this->restProxy->createQueue(self::$creatableQueue2, $opts);
        $result = $this->restProxy->getQueueMetadata(self::$creatableQueue2);
        $this->restProxy->deleteQueue(self::$creatableQueue2);

        // Assert
        self::assertNotNull($result, '$result');
        self::assertEquals(0, $result->getApproximateMessageCount(), '$result->getApproximateMessageCount');
        self::assertNotNull($result->getMetadata(), '$result->getMetadata');
        self::assertCount(2, $result->getMetadata(), 'count($result->getMetadata');
        $metadata = $result->getMetadata();
        self::assertEquals('bar', $metadata['foo'], '$metadata[foo]');
        self::assertEquals('blah', $metadata['test'], '$metadata[test]');
    }

    public function testListQueuesWorks()
    {
        // Arrange

        // Act
        $result = $this->restProxy->listQueues();

        // Assert
        self::assertNotNull($result, '$result');
        self::assertNotNull($result->getQueues(), '$result->getQueues');

        // TODO: Uncomment when the following issue is fixed:
        // https://github.com/azure/azure-storage-php/issues/98
        // $this->assertNotNull($result->getAccountName(), '$result->getAccountName()');
        self::assertEquals('', $result->getMarker(), '$result->getMarker');
        self::assertNull($result->getMaxResults(), '$result->getMaxResults');
        self::assertTrue(count(self::$testQueues) <= count($result->getQueues()), 'counts');
    }

    public function testListQueuesWithOptionsWorks()
    {
        // Arrange

        // Act
        $opts = new ListQueuesOptions();
        $opts->setMaxResults(3);
        $opts->setIncludeMetadata(true);
        $opts->setPrefix(self::$testQueuesPrefix);
        $result = $this->restProxy->listQueues($opts);

        $opts = new ListQueuesOptions();
        $opts->setMarker($result->getNextMarker());
        $opts->setIncludeMetadata(false);
        $opts->setPrefix(self::$testQueuesPrefix);
        $result2 = $this->restProxy->listQueues($opts);

        // Assert
        self::assertNotNull($result, '$result');
        self::assertNotNull($result->getQueues(), '$result->getQueues');
        self::assertCount(3, $result->getQueues(), 'count($result->getQueues');
        self::assertEquals(3, $result->getMaxResults(), '$result->getMaxResults');
        // TODO: Uncomment when the following issue is fixed:
        // https://github.com/azure/azure-storage-php/issues/98
        // $this->assertNotNull($result->getAccountName(), '$result->getAccountName()');
        self::assertNull($result->getMarker(), '$result->getMarker');
        $queue0 = $result->getQueues();
        $queue0 = $queue0[0];
        self::assertNotNull($queue0, '$queue0');
        self::assertNotNull(
            $queue0->getMetadata(),
            '$queue0->getMetadata' .
                ' (https://github.com/azure/azure-storage-php/issues/252)'
        );
        self::assertNotNull($queue0->getName(), '$queue0->getName');
        self::assertNotNull($queue0->getUrl(), '$queue0->getUrl');

        self::assertNotNull($result2, '$result2');
        self::assertNotNull($result2->getQueues(), '$result2->getQueues');
        self::assertTrue(count(self::$testQueues) - 3 <= count($result2->getQueues()), 'count');
        self::assertEquals(0, $result2->getMaxResults(), '$result2->getMaxResults');
        // TODO: Uncomment when the following issue is fixed:
        // https://github.com/azure/azure-storage-php/issues/98
        // $this->assertNotNull($result2->getAccountName(), '$result2->getAccountName()');
        self::assertEquals($result->getNextMarker(), $result2->getMarker(), '$result2->getMarker');
        $queue20 = $result2->getQueues();
        $queue20 = $queue20[0];
        self::assertNotNull($queue20, '$queue20');
        self::assertNotNull($queue20->getMetadata(), '$queue20->getMetadata');
        self::assertCount(0, $queue20->getMetadata(), 'count($queue20->getMetadata)');
        self::assertNotNull($queue20->getName(), '$queue20->getName');
        self::assertNotNull($queue20->getUrl(), '$queue20->getUrl');
    }

    public function testSetQueueMetadataWorks()
    {
        // Arrange

        // Act
        $this->restProxy->createQueue(self::$creatableQueue3);

        $metadata = [
            'foo' => 'bar',
            'test' => 'blah',
        ];
        $this->restProxy->setQueueMetadata(self::$creatableQueue3, $metadata);

        $result = $this->restProxy->getQueueMetadata(self::$creatableQueue3);

        $this->restProxy->deleteQueue(self::$creatableQueue3);

        // Assert
        self::assertNotNull($result, '$result');
        self::assertEquals(0, $result->getApproximateMessageCount(), '$result->getApproximateMessageCount');
        self::assertNotNull($result->getMetadata(), '$result->getMetadata');
        self::assertCount(2, $result->getMetadata(), 'count($result->getMetadata');
        $metadata = $result->getMetadata();
        self::assertEquals('bar', $metadata['foo'], '$metadata[\'foo\']');
        self::assertEquals('blah', $metadata['test'], '$metadata[\'test\']');
    }

    public function testCreateMessageWorks()
    {
        // Arrange

        // Act
        $this->restProxy->createMessage(self::$testQueueForMessages, 'message1');
        $this->restProxy->createMessage(self::$testQueueForMessages, 'message2');
        $this->restProxy->createMessage(self::$testQueueForMessages, 'message3');
        $this->restProxy->createMessage(self::$testQueueForMessages, 'message4');

        // Assert
        self::assertTrue(true, 'if get there, it is working');
    }

    public function testListMessagesWorks()
    {
        // Arrange
        $year2010 = new \DateTime();
        $year2010->setDate(2010, 1, 1);

        // Act
        $this->restProxy->createMessage(self::$testQueueForMessages2, 'message1');
        $this->restProxy->createMessage(self::$testQueueForMessages2, 'message2');
        $this->restProxy->createMessage(self::$testQueueForMessages2, 'message3');
        $this->restProxy->createMessage(self::$testQueueForMessages2, 'message4');
        $result = $this->restProxy->listMessages(self::$testQueueForMessages2);

        // Assert
        self::assertNotNull($result, '$result');
        self::assertCount(1, $result->getQueueMessages(), 'count($result->getQueueMessages');

        $entry = $result->getQueueMessages();
        $entry = $entry[0];

        self::assertNotNull($entry->getMessageId(), '$entry->getMessageId');
        self::assertNotNull($entry->getMessageText(), '$entry->getMessageText');
        self::assertNotNull($entry->getPopReceipt(), '$entry->getPopReceipt');
        self::assertEquals(1, $entry->getDequeueCount(), '$entry->getDequeueCount');

        self::assertNotNull($entry->getExpirationDate(), '$entry->getExpirationDate');
        self::assertTrue($year2010 < $entry->getExpirationDate(), 'diff');

        self::assertNotNull($entry->getInsertionDate(), '$entry->getInsertionDate');
        self::assertTrue($year2010 < $entry->getInsertionDate(), 'diff');

        self::assertNotNull($entry->getTimeNextVisible(), '$entry->getTimeNextVisible');
        self::assertTrue($year2010 < $entry->getTimeNextVisible(), 'diff');
    }

    public function testListMessagesWithOptionsWorks()
    {
        // Arrange
        $year2010 = new \DateTime();
        $year2010->setDate(2010, 1, 1);

        // Act
        $this->restProxy->createMessage(self::$testQueueForMessages3, 'message1');
        $this->restProxy->createMessage(self::$testQueueForMessages3, 'message2');
        $this->restProxy->createMessage(self::$testQueueForMessages3, 'message3');
        $this->restProxy->createMessage(self::$testQueueForMessages3, 'message4');
        $opts = new ListMessagesOptions();
        $opts->setNumberOfMessages(4);
        $opts->setVisibilityTimeoutInSeconds(20);
        $result = $this->restProxy->listMessages(self::$testQueueForMessages3, $opts);

        // Assert
        self::assertNotNull($result, '$result');
        self::assertCount(4, $result->getQueueMessages(), 'count($result->getQueueMessages())');
        for ($i = 0; $i < 4; ++$i) {
            $entry = $result->getQueueMessages();
            $entry = $entry[$i];

            self::assertNotNull($entry->getMessageId(), '$entry->getMessageId()');
            self::assertNotNull($entry->getMessageText(), '$entry->getMessageText()');
            self::assertNotNull($entry->getPopReceipt(), '$entry->getPopReceipt()');
            self::assertEquals(1, $entry->getDequeueCount(), '$entry->getDequeueCount()');

            self::assertNotNull($entry->getExpirationDate(), '$entry->getExpirationDate()');
            self::assertTrue($year2010 < $entry->getExpirationDate(), '$year2010 < $entry->getExpirationDate()');

            self::assertNotNull($entry->getInsertionDate(), '$entry->getInsertionDate()');
            self::assertTrue($year2010 < $entry->getInsertionDate(), '$year2010 < $entry->getInsertionDate()');

            self::assertNotNull($entry->getTimeNextVisible(), '$entry->getTimeNextVisible()');
            self::assertTrue($year2010 < $entry->getTimeNextVisible(), '$year2010 < $entry->getTimeNextVisible()');
        }
    }

    public function testPeekMessagesWorks()
    {
        // Arrange

        $year2010 = new \DateTime();
        $year2010->setDate(2010, 1, 1);

        // Act
        $this->restProxy->createMessage(self::$testQueueForMessages4, 'message1');
        $this->restProxy->createMessage(self::$testQueueForMessages4, 'message2');
        $this->restProxy->createMessage(self::$testQueueForMessages4, 'message3');
        $this->restProxy->createMessage(self::$testQueueForMessages4, 'message4');
        $result = $this->restProxy->peekMessages(self::$testQueueForMessages4);

        // Assert
        self::assertNotNull($result, '$result');
        self::assertCount(1, $result->getQueueMessages(), 'count($result->getQueueMessages())');

        $entry = $result ->getQueueMessages();
        $entry = $entry[0];

        self::assertNotNull($entry->getMessageId(), '$entry->getMessageId()');
        self::assertNotNull($entry->getMessageText(), '$entry->getMessageText()');
        self::assertEquals(0, $entry->getDequeueCount(), '$entry->getDequeueCount()');

        self::assertNotNull($entry->getExpirationDate(), '$entry->getExpirationDate()');
        self::assertTrue($year2010 < $entry->getExpirationDate(), '$year2010 < $entry->getExpirationDate()');

        self::assertNotNull($entry->getInsertionDate(), '$entry->getInsertionDate()');
        self::assertTrue($year2010 < $entry->getInsertionDate(), '$year2010 < $entry->getInsertionDate()');
    }

    public function testPeekMessagesWithOptionsWorks()
    {
        // Arrange
        $year2010 = new \DateTime();
        $year2010->setDate(2010, 1, 1);

        // Act
        $this->restProxy->createMessage(self::$testQueueForMessages5, 'message1');
        $this->restProxy->createMessage(self::$testQueueForMessages5, 'message2');
        $this->restProxy->createMessage(self::$testQueueForMessages5, 'message3');
        $this->restProxy->createMessage(self::$testQueueForMessages5, 'message4');
        $opts = new PeekMessagesOptions();
        $opts->setNumberOfMessages(4);
        $result = $this->restProxy->peekMessages(self::$testQueueForMessages5, $opts);

        // Assert
        self::assertNotNull($result, '$result');
        self::assertCount(4, $result->getQueueMessages(), 'count($result->getQueueMessages())');
        for ($i = 0; $i < 4; ++$i) {
            $entry = $result ->getQueueMessages();
            $entry = $entry[$i];

            self::assertNotNull($entry->getMessageId(), '$entry->getMessageId()');
            self::assertNotNull($entry->getMessageText(), '$entry->getMessageText()');
            self::assertEquals(0, $entry->getDequeueCount(), '$entry->getDequeueCount()');

            self::assertNotNull($entry->getExpirationDate(), '$entry->getExpirationDate()');
            self::assertTrue($year2010 < $entry->getExpirationDate(), '$year2010 < $entry->getExpirationDate()');

            self::assertNotNull($entry->getInsertionDate(), '$entry->getInsertionDate()');
            self::assertTrue($year2010 < $entry->getInsertionDate(), '$year2010 < $entry->getInsertionDate()');
        }
    }

    public function testClearMessagesWorks()
    {
        // Arrange

        // Act
        $this->restProxy->createMessage(self::$testQueueForMessages6, 'message1');
        $this->restProxy->createMessage(self::$testQueueForMessages6, 'message2');
        $this->restProxy->createMessage(self::$testQueueForMessages6, 'message3');
        $this->restProxy->createMessage(self::$testQueueForMessages6, 'message4');
        $this->restProxy->clearMessages(self::$testQueueForMessages6);

        $result = $this->restProxy->peekMessages(self::$testQueueForMessages6);

        // Assert
        self::assertNotNull($result, '$result');
        self::assertCount(0, $result->getQueueMessages(), 'count($result->getQueueMessages())');
    }

    public function testDeleteMessageWorks()
    {
        // Arrange

        // Act
        $this->restProxy->createMessage(self::$testQueueForMessages7, 'message1');
        $this->restProxy->createMessage(self::$testQueueForMessages7, 'message2');
        $this->restProxy->createMessage(self::$testQueueForMessages7, 'message3');
        $this->restProxy->createMessage(self::$testQueueForMessages7, 'message4');

        $result = $this->restProxy->listMessages(self::$testQueueForMessages7);
        $message0 = $result->getQueueMessages();
        $message0 = $message0[0];

        $this->restProxy->deleteMessage(
            self::$testQueueForMessages7,
            $message0->getMessageId(),
            $message0->getPopReceipt()
        );
        $opts = new ListMessagesOptions();
        $opts->setNumberOfMessages(32);
        $result2 = $this->restProxy->listMessages(self::$testQueueForMessages7, $opts);

        // Assert
        self::assertNotNull($result2, '$result2');
        self::assertCount(3, $result2->getQueueMessages(), 'count($result2->getQueueMessages())');
    }

    public function testUpdateMessageWorks()
    {
        // Arrange
        $year2010 = new \DateTime();
        $year2010->setDate(2010, 1, 1);

        // Act
        $this->restProxy->createMessage(self::$testQueueForMessages8, 'message1');

        $listResult1 = $this->restProxy->listMessages(self::$testQueueForMessages8);
        $message0 = $listResult1->getQueueMessages();
        $message0 = $message0[0];

        $updateResult = $this->restProxy->updateMessage(
            self::$testQueueForMessages8,
            $message0->getMessageId(),
            $message0->getPopReceipt(),
            'new text',
            0
        );
        $listResult2 = $this->restProxy->listMessages(self::$testQueueForMessages8);

        // Assert
        self::assertNotNull($updateResult, '$updateResult');
        self::assertNotNull($updateResult->getPopReceipt(), '$updateResult->getPopReceipt()');
        self::assertNotNull($updateResult->getTimeNextVisible(), '$updateResult->getTimeNextVisible()');
        self::assertTrue(
            $year2010 < $updateResult->getTimeNextVisible(),
            '$year2010 < $updateResult->getTimeNextVisible()'
        );

        self::assertNotNull($listResult2, '$listResult2');
        $entry = $listResult2->getQueueMessages();
        $entry = $entry[0];

        $blarg = $listResult1->getQueueMessages();
        $blarg = $blarg[0];

        self::assertEquals($blarg->getMessageId(), $entry->getMessageId(), '$entry->getMessageId()');
        self::assertEquals('new text', $entry->getMessageText(), '$entry->getMessageText()');
        self::assertNotNull($entry->getPopReceipt(), '$entry->getPopReceipt()');
        self::assertEquals(2, $entry->getDequeueCount(), '$entry->getDequeueCount()');

        self::assertNotNull($entry->getExpirationDate(), '$entry->getExpirationDate()');
        self::assertTrue($year2010 < $entry->getExpirationDate(), '$year2010 < $entry->getExpirationDate()');

        self::assertNotNull($entry->getInsertionDate(), '$entry->getInsertionDate()');
        self::assertTrue($year2010 < $entry->getInsertionDate(), '$year2010 < $entry->getInsertionDate()');

        self::assertNotNull($entry->getTimeNextVisible(), '$entry->getTimeNextVisible()');
        self::assertTrue($year2010 < $entry->getTimeNextVisible(), '$year2010 < $entry->getTimeNextVisible()');
    }
}
