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

namespace AzureOSS\Storage\Blob\Models;

use AzureOSS\Storage\Common\Models\ServiceOptions;

/**
 * Blob service options.
 *
 * @see      https://github.com/azure/azure-storage-php
 */
class BlobServiceOptions extends ServiceOptions
{
    private $_leaseId;
    private $_accessConditions;

    /**
     * Gets lease Id for the blob
     *
     * @return string
     */
    public function getLeaseId()
    {
        return $this->_leaseId;
    }

    /**
     * Sets lease Id for the blob
     *
     * @param string $leaseId the blob lease id.
     */
    public function setLeaseId($leaseId)
    {
        $this->_leaseId = $leaseId;
    }

    /**
     * Gets access condition
     *
     * @return \AzureOSS\Storage\Blob\Models\AccessCondition[]
     */
    public function getAccessConditions()
    {
        return $this->_accessConditions;
    }

    /**
     * Sets access condition
     *
     * @param mixed $accessConditions value to use.
     */
    public function setAccessConditions($accessConditions)
    {
        if (null !== $accessConditions
            && is_array($accessConditions)) {
            $this->_accessConditions = $accessConditions;
        } else {
            $this->_accessConditions = [$accessConditions];
        }
    }
}
