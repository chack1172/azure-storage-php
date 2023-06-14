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

use AzureOSS\Storage\Blob\Internal\BlobResources as Resources;
use AzureOSS\Storage\Common\Internal\Utilities;

/**
 * The result of creating Blob snapshot.
 *
 * @see      https://github.com/azure/azure-storage-php
 */
class CreateBlobSnapshotResult
{
    private $_snapshot;
    private $_etag;
    private $_lastModified;

    /**
     * Creates CreateBlobSnapshotResult object from the response of the
     * create Blob snapshot request.
     *
     * @param array $headers The HTTP response headers in array representation.
     *
     * @internal
     *
     * @return CreateBlobSnapshotResult
     */
    public static function create(array $headers)
    {
        $result = new CreateBlobSnapshotResult();
        $headerWithLowerCaseKey = array_change_key_case($headers);

        $result->setETag($headerWithLowerCaseKey[Resources::ETAG]);

        $result->setLastModified(
            Utilities::rfc1123ToDateTime(
                $headerWithLowerCaseKey[Resources::LAST_MODIFIED]
            )
        );

        $result->setSnapshot($headerWithLowerCaseKey[Resources::X_MS_SNAPSHOT]);

        return $result;
    }

    /**
     * Gets snapshot.
     *
     * @return string
     */
    public function getSnapshot()
    {
        return $this->_snapshot;
    }

    /**
     * Sets snapshot.
     *
     * @param string $snapshot value.
     */
    protected function setSnapshot($snapshot)
    {
        $this->_snapshot = $snapshot;
    }

    /**
     * Gets ETag.
     *
     * @return string
     */
    public function getETag()
    {
        return $this->_etag;
    }

    /**
     * Sets ETag.
     *
     * @param string $etag value.
     */
    protected function setETag($etag)
    {
        $this->_etag = $etag;
    }

    /**
     * Gets blob lastModified.
     *
     * @return \DateTime
     */
    public function getLastModified()
    {
        return $this->_lastModified;
    }

    /**
     * Sets blob lastModified.
     *
     * @param \DateTime $lastModified value.
     */
    protected function setLastModified($lastModified)
    {
        $this->_lastModified = $lastModified;
    }
}
