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

namespace AzureOSS\Storage\Table\Models;

use AzureOSS\Storage\Table\Internal\AcceptOptionTrait;

/**
 * Optional parameters for queryTables wrapper.
 *
 * @see      https://github.com/azure/azure-storage-php
 */
class QueryTablesOptions extends TableServiceOptions
{
    use TableContinuationTokenTrait;
    use AcceptOptionTrait;

    private $query;
    private $prefix;

    /**
     * Constructs new QueryTablesOptions object.
     */
    public function __construct()
    {
        parent::__construct();
        $this->query = new Query();
    }

    /**
     * Gets prefix
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Sets prefix
     *
     * @param string $prefix value
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * Gets top.
     *
     * @return int
     */
    public function getTop()
    {
        return $this->query->getTop();
    }

    /**
     * Sets top.
     *
     * @param int $top value.
     */
    public function setTop($top)
    {
        $this->query->setTop($top);
    }

    /**
     * Gets query.
     *
     * @return Query
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Gets filter.
     *
     * @return Filters\Filter
     */
    public function getFilter()
    {
        return $this->query->getFilter();
    }

    /**
     * Sets filter.
     *
     * @param Filters\Filter $filter value.
     */
    public function setFilter(Filters\Filter $filter)
    {
        $this->query->setFilter($filter);
    }

    /**
     * Sets selectFields.
     *
     * You can either use this individual function or use setQuery to set the
     * whole query object.
     *
     * @param array $selectFields value.
     */
    public function setSelectFields(array $selectFields = null)
    {
        $this->query->setSelectFields($selectFields);
    }
}
