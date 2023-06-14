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

namespace AzureOSS\Storage\Common\Internal\Authentication;

use AzureOSS\Storage\Common\Internal\Resources;
use GuzzleHttp\Psr7\Request;

/**
 * Base class for azure authentication schemes.
 *
 * @ignore
 *
 * @see      https://github.com/azure/azure-storage-php
 */
class SharedAccessSignatureAuthScheme implements IAuthScheme
{
    /**
     * The sas token
     */
    protected $sasToken;

    /**
     * Constructor.
     *
     * @param string $sasToken shared access signature token.
     */
    public function __construct($sasToken)
    {
        // Remove '?' in front of the SAS token if existing
        $this->sasToken = str_replace('?', '', $sasToken, $i);

        if ($i > 1) {
            throw new \InvalidArgumentException(
                sprintf(
                    Resources::INVALID_SAS_TOKEN,
                    $sasToken
                )
            );
        }
    }

    /**
     * Adds authentication header to the request headers.
     *
     * @param \GuzzleHttp\Psr7\Request $request HTTP request object.
     *
     * @abstract
     *
     * @return \GuzzleHttp\Psr7\Request
     */
    public function signRequest(Request $request)
    {
        // initial URI
        $uri = $request->getUri();

        // new query values from SAS token
        $queryValues = explode('&', $this->sasToken);

        // append SAS token query values to existing URI
        foreach ($queryValues as $queryField) {
            [$key, $value] = explode('=', $queryField);

            $uri = \GuzzleHttp\Psr7\Uri::withQueryValue($uri, $key, $value);
        }

        // replace URI
        return $request->withUri($uri, true);
    }
}
