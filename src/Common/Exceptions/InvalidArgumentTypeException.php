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

namespace AzureOSS\Storage\Common\Exceptions;

use AzureOSS\Storage\Common\Internal\Resources;

/**
 * Exception thrown if an argument type does not match with the expected type.
 *
 * @see      https://github.com/azure/azure-storage-php
 */
class InvalidArgumentTypeException extends \InvalidArgumentException
{
    /**
     * Constructor.
     *
     * @param string $validType The valid type that should be provided by the user.
     * @param string $name      The parameter name.
     *
     * @return InvalidArgumentTypeException
     */
    public function __construct($validType, $name = null)
    {
        parent::__construct(
            sprintf(Resources::INVALID_PARAM_MSG, $name, $validType)
        );
    }
}
