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

namespace AzureOSS\Storage\Queue\Models;

use AzureOSS\Storage\Common\Internal\Utilities;

/**
 * Holds results of listMessages wrapper.
 *
 * @see      https://github.com/azure/azure-storage-php
 */
class ListMessagesResult
{
    private $_queueMessages;

    /**
     * Creates ListMessagesResult object from parsed XML response.
     *
     * @param array $parsedResponse XML response parsed into array.
     *
     * @internal
     *
     * @return ListMessagesResult
     */
    public static function create(array $parsedResponse = null)
    {
        $result = new ListMessagesResult();
        $queueMessages = [];

        if (!empty($parsedResponse)) {
            $rawMessages = Utilities::getArray($parsedResponse['QueueMessage']);
            foreach ($rawMessages as $value) {
                $message = QueueMessage::createFromListMessages($value);

                $queueMessages[] = $message;
            }
        }
        $result->setQueueMessages($queueMessages);

        return $result;
    }

    /**
     * Gets queueMessages field.
     *
     * @return array
     */
    public function getQueueMessages()
    {
        return $this->_queueMessages;
    }

    /**
     * Sets queueMessages field.
     *
     * @param array $queueMessages value to use.
     *
     * @internal
     */
    protected function setQueueMessages(array $queueMessages)
    {
        $this->_queueMessages = [];

        foreach ($queueMessages as $value) {
            $this->_queueMessages[] = clone $value;
        }
    }
}
