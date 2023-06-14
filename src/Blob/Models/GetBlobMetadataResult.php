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

use AzureOSS\Storage\Common\Internal\MetadataTrait;

/**
 * Holds results of calling getBlobMetadata wrapper
 *
 * @see      https://github.com/azure/azure-storage-php
 */
class GetBlobMetadataResult
{
    use MetadataTrait;

    /**
     * Creates the instance from the parsed headers.
     *
     * @param array $parsed Parsed headers
     *
     * @return GetBlobMetadataResult
     */
    public static function create(array $parsed)
    {
        return static::createMetadataResult($parsed);
    }
}
