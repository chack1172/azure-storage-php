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

namespace AzureOSS\Storage\Table;

use AzureOSS\Storage\Common\Internal\Authentication\SharedAccessSignatureAuthScheme;
use AzureOSS\Storage\Common\Internal\Http\HttpCallContext;
use AzureOSS\Storage\Common\Internal\Http\HttpFormatter;
use AzureOSS\Storage\Common\Internal\Middlewares\CommonRequestMiddleware;
use AzureOSS\Storage\Common\Internal\ServiceRestProxy;
use AzureOSS\Storage\Common\Internal\ServiceRestTrait;
use AzureOSS\Storage\Common\Internal\StorageServiceSettings;
use AzureOSS\Storage\Common\Internal\Utilities;
use AzureOSS\Storage\Common\Internal\Validate;
use AzureOSS\Storage\Common\LocationMode;
use AzureOSS\Storage\Table\Internal\Authentication\TableSharedKeyLiteAuthScheme;
use AzureOSS\Storage\Table\Internal\IMimeReaderWriter;
use AzureOSS\Storage\Table\Internal\IODataReaderWriter;
use AzureOSS\Storage\Table\Internal\ITable;
use AzureOSS\Storage\Table\Internal\JsonODataReaderWriter;
use AzureOSS\Storage\Table\Internal\MimeReaderWriter;
use AzureOSS\Storage\Table\Internal\TableResources as Resources;
use AzureOSS\Storage\Table\Models\BatchOperationParameterName;
use AzureOSS\Storage\Table\Models\BatchOperationType;
use AzureOSS\Storage\Table\Models\BatchResult;
use AzureOSS\Storage\Table\Models\DeleteEntityOptions;
use AzureOSS\Storage\Table\Models\EdmType;
use AzureOSS\Storage\Table\Models\Entity;
use AzureOSS\Storage\Table\Models\Filters\BinaryFilter;
use AzureOSS\Storage\Table\Models\Filters\ConstantFilter;
use AzureOSS\Storage\Table\Models\Filters\Filter;
use AzureOSS\Storage\Table\Models\Filters\PropertyNameFilter;
use AzureOSS\Storage\Table\Models\Filters\QueryStringFilter;
use AzureOSS\Storage\Table\Models\Filters\UnaryFilter;
use AzureOSS\Storage\Table\Models\GetEntityOptions;
use AzureOSS\Storage\Table\Models\GetEntityResult;
use AzureOSS\Storage\Table\Models\GetTableOptions;
use AzureOSS\Storage\Table\Models\GetTableResult;
use AzureOSS\Storage\Table\Models\InsertEntityResult;
use AzureOSS\Storage\Table\Models\Query;
use AzureOSS\Storage\Table\Models\QueryEntitiesOptions;
use AzureOSS\Storage\Table\Models\QueryEntitiesResult;
use AzureOSS\Storage\Table\Models\QueryTablesOptions;
use AzureOSS\Storage\Table\Models\QueryTablesResult;
use AzureOSS\Storage\Table\Models\TableACL;
use AzureOSS\Storage\Table\Models\TableServiceCreateOptions;
use AzureOSS\Storage\Table\Models\TableServiceOptions;
use AzureOSS\Storage\Table\Models\UpdateEntityResult;

/**
 * This class constructs HTTP requests and receive HTTP responses for table
 * service layer.
 *
 * @see      https://github.com/azure/azure-storage-php
 */
class TableRestProxy extends ServiceRestProxy implements ITable
{
    use ServiceRestTrait;

    /**
     * @var Internal\IODataReaderWriter
     */
    private $odataSerializer;

    /**
     * @var Internal\IMimeReaderWriter
     */
    private $mimeSerializer;

    /**
     * Builds a table service object, it accepts the following
     * options:
     *
     * - http: (array) the underlying guzzle options. refer to
     *   http://docs.guzzlephp.org/en/latest/request-options.html for detailed available options
     * - middlewares: (mixed) the middleware should be either an instance of a sub-class that
     *   implements {@see AzureOSS\Storage\Common\Middlewares\IMiddleware}, or a
     *   `callable` that follows the Guzzle middleware implementation convention
     *
     * Please refer to
     * https://azure.microsoft.com/en-us/documentation/articles/storage-configure-connection-string
     * for how to construct a connection string with storage account name/key, or with a shared
     * access signature (SAS Token).
     *
     * @param string $connectionString The configuration connection string.
     * @param array  $options          Array of options to pass to the service
     *
     * @return TableRestProxy
     */
    public static function createTableService(
        $connectionString,
        array $options = []
    ) {
        $settings = StorageServiceSettings::createFromConnectionString(
            $connectionString
        );

        $odataSerializer = new JsonODataReaderWriter();
        $mimeSerializer = new MimeReaderWriter();

        $primaryUri = Utilities::tryAddUrlScheme(
            $settings->getTableEndpointUri()
        );
        $secondaryUri = Utilities::tryAddUrlScheme(
            $settings->getTableSecondaryEndpointUri()
        );

        $tableWrapper = new TableRestProxy(
            $primaryUri,
            $secondaryUri,
            $odataSerializer,
            $mimeSerializer,
            $options
        );

        // Adding headers filter
        $headers = [];
        $currentVersion = Resources::DATA_SERVICE_VERSION_VALUE;
        $maxVersion = Resources::MAX_DATA_SERVICE_VERSION_VALUE;
        $accept = Resources::ACCEPT_HEADER_VALUE;
        $acceptCharset = Resources::ACCEPT_CHARSET_VALUE;

        $headers[Resources::DATA_SERVICE_VERSION] = $currentVersion;
        $headers[Resources::MAX_DATA_SERVICE_VERSION] = $maxVersion;
        $headers[Resources::ACCEPT_HEADER] = $accept;
        $headers[Resources::ACCEPT_CHARSET] = $acceptCharset;

        // Getting authentication scheme
        if ($settings->hasSasToken()) {
            $authScheme = new SharedAccessSignatureAuthScheme(
                $settings->getSasToken()
            );
        } else {
            $authScheme = new TableSharedKeyLiteAuthScheme(
                $settings->getName(),
                $settings->getKey()
            );
        }

        // Adding common request middleware
        $commonRequestMiddleware = new CommonRequestMiddleware(
            $authScheme,
            Resources::STORAGE_API_LATEST_VERSION,
            Resources::TABLE_SDK_VERSION,
            $headers
        );
        $tableWrapper->pushMiddleware($commonRequestMiddleware);

        return $tableWrapper;
    }

    /**
     * Creates contexts for batch operations.
     *
     * @param array $operations The batch operations array.
     *
     * @throws \InvalidArgumentException
     *
     * @return array
     */
    private function createOperationsContexts(array $operations)
    {
        $contexts = [];

        foreach ($operations as $operation) {
            $context = null;
            $type = $operation->getType();

            switch ($type) {
                case BatchOperationType::INSERT_ENTITY_OPERATION:
                case BatchOperationType::UPDATE_ENTITY_OPERATION:
                case BatchOperationType::MERGE_ENTITY_OPERATION:
                case BatchOperationType::INSERT_REPLACE_ENTITY_OPERATION:
                case BatchOperationType::INSERT_MERGE_ENTITY_OPERATION:
                    $table = $operation->getParameter(
                        BatchOperationParameterName::BP_TABLE
                    );
                    $entity = $operation->getParameter(
                        BatchOperationParameterName::BP_ENTITY
                    );
                    $context = $this->getOperationContext($table, $entity, $type);
                    break;

                case BatchOperationType::DELETE_ENTITY_OPERATION:
                    $table = $operation->getParameter(
                        BatchOperationParameterName::BP_TABLE
                    );
                    $partitionKey = $operation->getParameter(
                        BatchOperationParameterName::BP_PARTITION_KEY
                    );
                    $rowKey = $operation->getParameter(
                        BatchOperationParameterName::BP_ROW_KEY
                    );
                    $etag = $operation->getParameter(
                        BatchOperationParameterName::BP_ETAG
                    );
                    $options = new DeleteEntityOptions();
                    $options->setETag($etag);
                    $context = $this->constructDeleteEntityContext(
                        $table,
                        $partitionKey,
                        $rowKey,
                        $options
                    );
                    break;

                default:
                    throw new \InvalidArgumentException();
            }

            $contexts[] = $context;
        }

        return $contexts;
    }

    /**
     * Creates operation context for the API.
     *
     * @param string $table  The table name.
     * @param Entity $entity The entity object.
     * @param string $type   The API type.
     *
     * @throws \InvalidArgumentException
     *
     * @return \AzureOSS\Storage\Common\Internal\Http\HttpCallContext
     */
    private function getOperationContext($table, Entity $entity, $type)
    {
        switch ($type) {
            case BatchOperationType::INSERT_ENTITY_OPERATION:
                return $this->constructInsertEntityContext($table, $entity, null);

            case BatchOperationType::UPDATE_ENTITY_OPERATION:
                return $this->constructPutOrMergeEntityContext(
                    $table,
                    $entity,
                    Resources::HTTP_PUT,
                    true,
                    null
                );

            case BatchOperationType::MERGE_ENTITY_OPERATION:
                return $this->constructPutOrMergeEntityContext(
                    $table,
                    $entity,
                    Resources::HTTP_MERGE,
                    true,
                    null
                );

            case BatchOperationType::INSERT_REPLACE_ENTITY_OPERATION:
                return $this->constructPutOrMergeEntityContext(
                    $table,
                    $entity,
                    Resources::HTTP_PUT,
                    false,
                    null
                );

            case BatchOperationType::INSERT_MERGE_ENTITY_OPERATION:
                return $this->constructPutOrMergeEntityContext(
                    $table,
                    $entity,
                    Resources::HTTP_MERGE,
                    false,
                    null
                );

            default:
                throw new \InvalidArgumentException();
        }
    }

    /**
     * Creates MIME part body for batch API.
     *
     * @param array $operations The batch operations.
     * @param array $contexts   The contexts objects.
     *
     * @throws \InvalidArgumentException
     *
     * @return array
     */
    private function createBatchRequestBody(array $operations, array $contexts)
    {
        $mimeBodyParts = [];
        $contentId = 1;
        $count = count($operations);

        Validate::isTrue(
            count($operations) == count($contexts),
            Resources::INVALID_OC_COUNT_MSG
        );

        for ($i = 0; $i < $count; ++$i) {
            $operation = $operations[$i];
            $context = $contexts[$i];
            $type = $operation->getType();

            switch ($type) {
                case BatchOperationType::INSERT_ENTITY_OPERATION:
                case BatchOperationType::UPDATE_ENTITY_OPERATION:
                case BatchOperationType::MERGE_ENTITY_OPERATION:
                case BatchOperationType::INSERT_REPLACE_ENTITY_OPERATION:
                case BatchOperationType::INSERT_MERGE_ENTITY_OPERATION:
                    $contentType = $context->getHeader(Resources::CONTENT_TYPE);
                    $body = $context->getBody();
                    $contentType .= ';type=entry';
                    $context->addOptionalHeader(Resources::CONTENT_TYPE, $contentType);
                    // Use mb_strlen instead of strlen to get the length of the string
                    // in bytes instead of the length in chars.
                    $context->addOptionalHeader(
                        Resources::CONTENT_LENGTH,
                        strlen($body)
                    );
                    break;

                case BatchOperationType::DELETE_ENTITY_OPERATION:
                    break;

                default:
                    throw new \InvalidArgumentException();
            }

            $context->addOptionalHeader(Resources::CONTENT_ID, $contentId);
            $mimeBodyPart = $context->__toString();
            $mimeBodyParts[] = $mimeBodyPart;
            ++$contentId;
        }

        return $this->mimeSerializer->encodeMimeMultipart($mimeBodyParts);
    }

    /**
     * Constructs HTTP call context for deleteEntity API.
     *
     * @param string              $table        The name of the table.
     * @param string              $partitionKey The entity partition key.
     * @param string              $rowKey       The entity row key.
     * @param DeleteEntityOptions $options      The optional parameters.
     *
     * @return HttpCallContext
     */
    private function constructDeleteEntityContext(
        $table,
        $partitionKey,
        $rowKey,
        DeleteEntityOptions $options = null
    ) {
        Validate::canCastAsString($table, 'table');
        Validate::notNullOrEmpty($table, 'table');
        Validate::isTrue(null !== $partitionKey, Resources::NULL_TABLE_KEY_MSG);
        Validate::isTrue(null !== $rowKey, Resources::NULL_TABLE_KEY_MSG);

        $method = Resources::HTTP_DELETE;
        $headers = [];
        $queryParams = [];
        $statusCode = Resources::STATUS_NO_CONTENT;
        $path = $this->getEntityPath($table, $partitionKey, $rowKey);

        if (null === $options) {
            $options = new DeleteEntityOptions();
        }

        $etagObj = $options->getETag();
        $ETag = null !== $etagObj;
        $this->addOptionalHeader(
            $headers,
            Resources::IF_MATCH,
            $ETag ? $etagObj : Resources::ASTERISK
        );

        $this->addOptionalHeader(
            $headers,
            Resources::ACCEPT_HEADER,
            Resources::JSON_CONTENT_TYPE
        );

        $options->setLocationMode(LocationMode::PRIMARY_ONLY);

        $context = new HttpCallContext();
        $context->setHeaders($headers);
        $context->setMethod($method);
        $context->setPath($path);
        $context->setQueryParameters($queryParams);
        $context->addStatusCode($statusCode);
        $context->setBody('');
        $context->setServiceOptions($options);

        return $context;
    }

    /**
     * Constructs HTTP call context for updateEntity, mergeEntity,
     * insertOrReplaceEntity and insertOrMergeEntity.
     *
     * @param string              $table   The table name.
     * @param Entity              $entity  The entity instance to use.
     * @param string              $verb    The HTTP method.
     * @param bool                $useETag The flag to include etag or not.
     * @param TableServiceOptions $options The optional parameters.
     *
     * @return HttpCallContext
     */
    private function constructPutOrMergeEntityContext(
        $table,
        Entity $entity,
        $verb,
        $useETag,
        TableServiceOptions $options = null
    ) {
        Validate::canCastAsString($table, 'table');
        Validate::notNullOrEmpty($table, 'table');
        Validate::notNullOrEmpty($entity, 'entity');
        Validate::isTrue($entity->isValid($msg), $msg);

        $method = $verb;
        $headers = [];
        $queryParams = [];
        $statusCode = Resources::STATUS_NO_CONTENT;
        $partitionKey = $entity->getPartitionKey();
        $rowKey = $entity->getRowKey();
        $path = $this->getEntityPath($table, $partitionKey, $rowKey);
        $body = $this->odataSerializer->getEntity($entity);

        if (null === $options) {
            $options = new TableServiceOptions();
        }

        if ($useETag) {
            $etag = $entity->getETag();
            $ifMatchValue = null === $etag ? Resources::ASTERISK : $etag;

            $this->addOptionalHeader($headers, Resources::IF_MATCH, $ifMatchValue);
        }

        $this->addOptionalHeader(
            $headers,
            Resources::CONTENT_TYPE,
            Resources::JSON_CONTENT_TYPE
        );
        $this->addOptionalHeader(
            $headers,
            Resources::ACCEPT_HEADER,
            Resources::JSON_FULL_METADATA_CONTENT_TYPE
        );

        $options->setLocationMode(LocationMode::PRIMARY_ONLY);
        $context = new HttpCallContext();
        $context->setBody($body);
        $context->setHeaders($headers);
        $context->setMethod($method);
        $context->setPath($path);
        $context->setQueryParameters($queryParams);
        $context->addStatusCode($statusCode);
        $context->setServiceOptions($options);

        return $context;
    }

    /**
     * Constructs HTTP call context for insertEntity API.
     *
     * @param string                    $table   The name of the table.
     * @param Entity                    $entity  The table entity.
     * @param TableServiceCreateOptions $options The optional parameters.
     *
     * @return HttpCallContext
     */
    private function constructInsertEntityContext(
        $table,
        Entity $entity,
        TableServiceCreateOptions $options = null
    ) {
        Validate::canCastAsString($table, 'table');
        Validate::notNullOrEmpty($table, 'table');
        Validate::notNullOrEmpty($entity, 'entity');
        Validate::isTrue($entity->isValid($msg), $msg);

        $method = Resources::HTTP_POST;
        $context = new HttpCallContext();
        $headers = [];
        $queryParams = [];
        $statusCode = Resources::STATUS_CREATED;
        $path = $table;
        $body = $this->odataSerializer->getEntity($entity);

        if (null === $options) {
            $options = new TableServiceCreateOptions();
        }

        $this->addOptionalHeader(
            $headers,
            Resources::CONTENT_TYPE,
            Resources::JSON_CONTENT_TYPE
        );
        $this->addOptionalHeader(
            $headers,
            Resources::ACCEPT_HEADER,
            $options->getAccept()
        );
        $this->addOptionalHeader(
            $headers,
            Resources::PREFER,
            $options->getDoesReturnContent() ? Resources::RETURN_CONTENT : null
        );

        $options->setLocationMode(LocationMode::PRIMARY_ONLY);
        $context->setBody($body);
        $context->setHeaders($headers);
        $context->setMethod($method);
        $context->setPath($path);
        $context->setQueryParameters($queryParams);
        $context->addStatusCode($statusCode);
        $context->setServiceOptions($options);

        return $context;
    }

    /**
     * Constructs URI path for entity.
     *
     * @param string $table        The table name.
     * @param string $partitionKey The entity's partition key.
     * @param string $rowKey       The entity's row key.
     *
     * @return string
     */
    private function getEntityPath($table, $partitionKey, $rowKey)
    {
        $encodedPK = $this->encodeODataUriValue($partitionKey);
        $encodedRK = $this->encodeODataUriValue($rowKey);

        return "$table(PartitionKey='$encodedPK',RowKey='$encodedRK')";
    }

    /**
     * Creates a promie that does the actual work for update and merge entity
     * APIs.
     *
     * @param string              $table   The table name.
     * @param Entity              $entity  The entity instance to use.
     * @param string              $verb    The HTTP method.
     * @param bool                $useETag The flag to include etag or not.
     * @param TableServiceOptions $options The optional parameters.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    private function putOrMergeEntityAsyncImpl(
        $table,
        Entity $entity,
        $verb,
        $useETag,
        TableServiceOptions $options = null
    ) {
        $context = $this->constructPutOrMergeEntityContext(
            $table,
            $entity,
            $verb,
            $useETag,
            $options
        );

        return $this->sendContextAsync($context)->then(static function ($response) {
            return UpdateEntityResult::create(
                HttpFormatter::formatHeaders($response->getHeaders())
            );
        }, null);
    }

    /**
     * Builds filter expression
     *
     * @param Filter $filter The filter object
     *
     * @return string
     */
    private function buildFilterExpression(Filter $filter)
    {
        $e = Resources::EMPTY_STRING;
        $this->buildFilterExpressionRec($filter, $e);

        return $e;
    }

    /**
     * Builds filter expression
     *
     * @param Filter $filter The filter object
     * @param string &$e     The filter expression
     *
     * @return string
     */
    private function buildFilterExpressionRec(Filter $filter, &$e)
    {
        if (null === $filter) {
            return;
        }

        if ($filter instanceof PropertyNameFilter) {
            $e .= $filter->getPropertyName();
        } elseif ($filter instanceof ConstantFilter) {
            $value = $filter->getValue();
            // If the value is null we just append null regardless of the edmType.
            if (null === $value) {
                $e .= 'null';
            } else {
                $type = $filter->getEdmType();
                $e .= EdmType::serializeQueryValue($type, $value);
            }
        } elseif ($filter instanceof UnaryFilter) {
            $e .= $filter->getOperator();
            $e .= '(';
            $this->buildFilterExpressionRec($filter->getOperand(), $e);
            $e .= ')';
        } elseif ($filter instanceof BinaryFilter) {
            $e .= '(';
            $this->buildFilterExpressionRec($filter->getLeft(), $e);
            $e .= ' ';
            $e .= $filter->getOperator();
            $e .= ' ';
            $this->buildFilterExpressionRec($filter->getRight(), $e);
            $e .= ')';
        } elseif ($filter instanceof QueryStringFilter) {
            $e .= $filter->getQueryString();
        }

        return $e;
    }

    /**
     * Adds query object to the query parameter array
     *
     * @param array $queryParam The URI query parameters
     * @param Query $query      The query object
     *
     * @return array
     */
    private function addOptionalQuery(array $queryParam, Query $query)
    {
        if (null !== $query) {
            $selectedFields = $query->getSelectFields();
            if (!empty($selectedFields)) {
                $final = $this->encodeODataUriValues($selectedFields);

                $this->addOptionalQueryParam(
                    $queryParam,
                    Resources::QP_SELECT,
                    implode(',', $final)
                );
            }

            if (null !== $query->getTop()) {
                $final = (string) ($this->encodeODataUriValue($query->getTop()));

                $this->addOptionalQueryParam(
                    $queryParam,
                    Resources::QP_TOP,
                    $final
                );
            }

            if (null !== $query->getFilter()) {
                $final = $this->buildFilterExpression($query->getFilter());
                $this->addOptionalQueryParam(
                    $queryParam,
                    Resources::QP_FILTER,
                    $final
                );
            }
        }

        return $queryParam;
    }

    /**
     * Encodes OData URI values
     *
     * @param array $values The OData URL values
     *
     * @return array
     */
    private function encodeODataUriValues(array $values)
    {
        $list = [];

        foreach ($values as $value) {
            $list[] = $this->encodeODataUriValue($value);
        }

        return $list;
    }

    /**
     * Encodes OData URI value
     *
     * @param string $value The OData URL value
     *
     * @return string
     */
    private function encodeODataUriValue($value)
    {
        // Replace each single quote (') with double single quotes ('') not doudle
        // quotes (")
        $value = str_replace('\'', '\'\'', $value);

        // Encode the special URL characters
        return rawurlencode($value);
    }

    /**
     * Initializes new TableRestProxy object.
     *
     * @param string             $primaryUri      The storage account primary uri.
     * @param string             $secondaryUri    The storage account secondary uri.
     * @param IODataReaderWriter $odataSerializer The odata serializer.
     * @param IMimeReaderWriter  $mimeSerializer  The MIME serializer.
     * @param array              $options         Array of options to pass to
     *                                            the service
     */
    public function __construct(
        $primaryUri,
        $secondaryUri,
        IODataReaderWriter $odataSerializer,
        IMimeReaderWriter $mimeSerializer,
        array $options = []
    ) {
        parent::__construct(
            $primaryUri,
            $secondaryUri,
            Resources::EMPTY_STRING,
            $options
        );
        $this->odataSerializer = $odataSerializer;
        $this->mimeSerializer = $mimeSerializer;
    }

    /**
     * Quries tables in the given storage account.
     *
     * @param Filter|QueryTablesOptions|string $options Could be optional
     *                                                  parameters, table prefix
     *                                                  or filter to apply.
     *
     * @return QueryTablesResult
     *
     * @see https://docs.microsoft.com/en-us/rest/api/storageservices/query-tables
     */
    public function queryTables($options = null)
    {
        return $this->queryTablesAsync($options)->wait();
    }

    /**
     * Creates promise to query the tables in the given storage account.
     *
     * @param Filter|QueryTablesOptions|string $options Could be optional
     *                                                  parameters, table prefix
     *                                                  or filter to apply.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     *
     * @see https://docs.microsoft.com/en-us/rest/api/storageservices/query-tables
     */
    public function queryTablesAsync($options = null)
    {
        $method = Resources::HTTP_GET;
        $headers = [];
        $postParams = [];
        $queryParams = [];
        $path = 'Tables';

        if (null === $options) {
            $options = new QueryTablesOptions();
        } elseif (is_string($options)) {
            $prefix = $options;
            $options = new QueryTablesOptions();
            $options->setPrefix($prefix);
        } elseif ($options instanceof Filter) {
            $filter = $options;
            $options = new QueryTablesOptions();
            $options->setFilter($filter);
        }

        $query = $options->getQuery();
        $next = $options->getNextTableName();
        $prefix = $options->getPrefix();

        if (!empty($prefix)) {
            // Append Max char to end '{' is 1 + 'z' in AsciiTable ==> upperBound
            // is prefix + '{'
            $prefixFilter = Filter::applyAnd(
                Filter::applyGe(
                    Filter::applyPropertyName('TableName'),
                    Filter::applyConstant($prefix, EdmType::STRING)
                ),
                Filter::applyLe(
                    Filter::applyPropertyName('TableName'),
                    Filter::applyConstant($prefix . '{', EdmType::STRING)
                )
            );

            if (null === $query) {
                $query = new Query();
            }

            if (null === $query->getFilter()) {
                // use the prefix filter if the query filter is null
                $query->setFilter($prefixFilter);
            } else {
                // combine and use the prefix filter if the query filter exists
                $combinedFilter = Filter::applyAnd(
                    $query->getFilter(),
                    $prefixFilter
                );
                $query->setFilter($combinedFilter);
            }
        }

        $queryParams = $this->addOptionalQuery($queryParams, $query);

        $this->addOptionalQueryParam(
            $queryParams,
            Resources::QP_NEXT_TABLE_NAME,
            $next
        );
        $this->addOptionalHeader(
            $headers,
            Resources::ACCEPT_HEADER,
            $options->getAccept()
        );

        // One can specify the NextTableName option to get table entities starting
        // from the specified name. However, there appears to be an issue in the
        // Azure Table service where this does not engage on the server unless
        // $filter appears in the URL. The current behavior is to just ignore the
        // NextTableName options, which is not expected or easily detectable.
        if (array_key_exists(Resources::QP_NEXT_TABLE_NAME, $queryParams)
            && !array_key_exists(Resources::QP_FILTER, $queryParams)
        ) {
            $queryParams[Resources::QP_FILTER] = Resources::EMPTY_STRING;
        }

        $odataSerializer = $this->odataSerializer;

        return $this->sendAsync(
            $method,
            $headers,
            $queryParams,
            $postParams,
            $path,
            Resources::STATUS_OK,
            Resources::EMPTY_STRING,
            $options
        )->then(static function ($response) use ($odataSerializer) {
            $tables = $odataSerializer->parseTableEntries($response->getBody());
            return QueryTablesResult::create(
                HttpFormatter::formatHeaders($response->getHeaders()),
                $tables
            );
        }, null);
    }

    /**
     * Creates new table in the storage account
     *
     * @param string                    $table   The name of the table.
     * @param TableServiceCreateOptions $options The optional parameters.
     *
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @see https://docs.microsoft.com/en-us/rest/api/storageservices/create-table
     */
    public function createTable($table, TableServiceCreateOptions $options = null)
    {
        return $this->createTableAsync($table, $options)->wait();
    }

    /**
     * Creates promise to create new table in the storage account
     *
     * @param string                    $table   The name of the table.
     * @param TableServiceCreateOptions $options The optional parameters.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     *
     * @see https://docs.microsoft.com/en-us/rest/api/storageservices/create-table
     */
    public function createTableAsync(
        $table,
        TableServiceCreateOptions $options = null
    ) {
        Validate::canCastAsString($table, 'table');
        Validate::notNullOrEmpty($table, 'table');

        $method = Resources::HTTP_POST;
        $headers = [];
        $postParams = [];
        $queryParams = [];
        $path = 'Tables';
        $body = $this->odataSerializer->getTable($table);

        if (null === $options) {
            $options = new TableServiceCreateOptions();
        }

        $this->addOptionalHeader(
            $headers,
            Resources::CONTENT_TYPE,
            Resources::JSON_CONTENT_TYPE
        );
        $this->addOptionalHeader(
            $headers,
            Resources::ACCEPT_HEADER,
            $options->getAccept()
        );

        $this->addOptionalHeader(
            $headers,
            Resources::PREFER,
            $options->getDoesReturnContent() ? Resources::RETURN_CONTENT : null
        );
        $options->setLocationMode(LocationMode::PRIMARY_ONLY);

        return $this->sendAsync(
            $method,
            $headers,
            $queryParams,
            $postParams,
            $path,
            Resources::STATUS_CREATED,
            $body,
            $options
        );
    }

    /**
     * Gets the table.
     *
     * @param string          $table   The name of the table.
     * @param GetTableOptions $options The optional parameters.
     *
     * @return GetTableResult
     */
    public function getTable($table, GetTableOptions $options = null)
    {
        return $this->getTableAsync($table, $options)->wait();
    }

    /**
     * Creates the promise to get the table.
     *
     * @param string          $table   The name of the table.
     * @param GetTableOptions $options The optional parameters.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function getTableAsync(
        $table,
        GetTableOptions $options = null
    ) {
        Validate::canCastAsString($table, 'table');
        Validate::notNullOrEmpty($table, 'table');

        $method = Resources::HTTP_GET;
        $headers = [];
        $postParams = [];
        $queryParams = [];
        $path = "Tables('$table')";

        if (null === $options) {
            $options = new GetTableOptions();
        }

        $this->addOptionalHeader(
            $headers,
            Resources::CONTENT_TYPE,
            Resources::JSON_CONTENT_TYPE
        );
        $this->addOptionalHeader(
            $headers,
            Resources::ACCEPT_HEADER,
            $options->getAccept()
        );

        $odataSerializer = $this->odataSerializer;

        return $this->sendAsync(
            $method,
            $headers,
            $queryParams,
            $postParams,
            $path,
            Resources::STATUS_OK,
            Resources::EMPTY_STRING,
            $options
        )->then(static function ($response) use ($odataSerializer) {
            return GetTableResult::create($response->getBody(), $odataSerializer);
        }, null);
    }

    /**
     * Deletes the specified table and any data it contains.
     *
     * @param string              $table   The name of the table.
     * @param TableServiceOptions $options optional parameters
     *
     * @see http://msdn.microsoft.com/en-us/library/windowsazure/dd179387.aspx
     */
    public function deleteTable($table, TableServiceOptions $options = null)
    {
        $this->deleteTableAsync($table, $options)->wait();
    }

    /**
     * Creates promise to delete the specified table and any data it contains.
     *
     * @param string              $table   The name of the table.
     * @param TableServiceOptions $options optional parameters
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     *
     * @see http://msdn.microsoft.com/en-us/library/windowsazure/dd179387.aspx
     */
    public function deleteTableAsync(
        $table,
        TableServiceOptions $options = null
    ) {
        Validate::canCastAsString($table, 'table');
        Validate::notNullOrEmpty($table, 'table');

        $method = Resources::HTTP_DELETE;
        $headers = [];
        $postParams = [];
        $queryParams = [];
        $path = "Tables('$table')";

        if (null === $options) {
            $options = new TableServiceOptions();
        }

        return $this->sendAsync(
            $method,
            $headers,
            $queryParams,
            $postParams,
            $path,
            Resources::STATUS_NO_CONTENT,
            Resources::EMPTY_STRING,
            $options
        );
    }

    /**
     * Quries entities for the given table name
     *
     * @param string                             $table   The name of
     *                                                    the table.
     * @param Filter|QueryEntitiesOptions|string $options Coule be optional
     *                                                    parameters, query
     *                                                    string or filter to
     *                                                    apply.
     *
     * @return QueryEntitiesResult
     *
     * @see https://docs.microsoft.com/en-us/rest/api/storageservices/query-entities
     */
    public function queryEntities($table, $options = null)
    {
        return $this->queryEntitiesAsync($table, $options)->wait();
    }

    /**
     * Quries entities for the given table name
     *
     * @param string                             $table   The name of the table.
     * @param Filter|QueryEntitiesOptions|string $options Coule be optional
     *                                                    parameters, query
     *                                                    string or filter to
     *                                                    apply.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     *
     * @see https://docs.microsoft.com/en-us/rest/api/storageservices/query-entities
     */
    public function queryEntitiesAsync($table, $options = null)
    {
        Validate::canCastAsString($table, 'table');
        Validate::notNullOrEmpty($table, 'table');

        $method = Resources::HTTP_GET;
        $headers = [];
        $postParams = [];
        $queryParams = [];
        $path = $table;

        if (null === $options) {
            $options = new QueryEntitiesOptions();
        } elseif (is_string($options)) {
            $queryString = $options;
            $options = new QueryEntitiesOptions();
            $options->setFilter(Filter::applyQueryString($queryString));
        } elseif ($options instanceof Filter) {
            $filter = $options;
            $options = new QueryEntitiesOptions();
            $options->setFilter($filter);
        }

        $queryParams = $this->addOptionalQuery($queryParams, $options->getQuery());

        $this->addOptionalQueryParam(
            $queryParams,
            Resources::QP_NEXT_PK,
            $options->getNextPartitionKey()
        );
        $this->addOptionalQueryParam(
            $queryParams,
            Resources::QP_NEXT_RK,
            $options->getNextRowKey()
        );

        $this->addOptionalHeader(
            $headers,
            Resources::CONTENT_TYPE,
            Resources::JSON_CONTENT_TYPE
        );

        $this->addOptionalHeader(
            $headers,
            Resources::ACCEPT_HEADER,
            $options->getAccept()
        );

        if (null !== $options->getQuery()) {
            $dsHeader = Resources::DATA_SERVICE_VERSION;
            $maxdsValue = Resources::MAX_DATA_SERVICE_VERSION_VALUE;
            $fields = $options->getQuery()->getSelectFields();
            $hasSelect = !empty($fields);
            if ($hasSelect) {
                $this->addOptionalHeader($headers, $dsHeader, $maxdsValue);
            }
        }

        $odataSerializer = $this->odataSerializer;

        return $this->sendAsync(
            $method,
            $headers,
            $queryParams,
            $postParams,
            $path,
            Resources::STATUS_OK,
            Resources::EMPTY_STRING,
            $options
        )->then(static function ($response) use ($odataSerializer) {
            $entities = $odataSerializer->parseEntities($response->getBody());

            return QueryEntitiesResult::create(
                HttpFormatter::formatHeaders($response->getHeaders()),
                $entities
            );
        }, null);
    }

    /**
     * Inserts new entity to the table.
     *
     * @param string                    $table   name of the table.
     * @param Entity                    $entity  table entity.
     * @param TableServiceCreateOptions $options optional parameters.
     *
     * @return InsertEntityResult
     *
     * @see https://docs.microsoft.com/en-us/rest/api/storageservices/insert-entity
     */
    public function insertEntity(
        $table,
        Entity $entity,
        TableServiceCreateOptions $options = null
    ) {
        return $this->insertEntityAsync($table, $entity, $options)->wait();
    }

    /**
     * Inserts new entity to the table.
     *
     * @param string                    $table   name of the table.
     * @param Entity                    $entity  table entity.
     * @param TableServiceCreateOptions $options optional parameters.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     *
     * @see https://docs.microsoft.com/en-us/rest/api/storageservices/insert-entity
     */
    public function insertEntityAsync(
        $table,
        Entity $entity,
        TableServiceCreateOptions $options = null
    ) {
        $context = $this->constructInsertEntityContext(
            $table,
            $entity,
            $options
        );

        $odataSerializer = $this->odataSerializer;

        return $this->sendContextAsync($context)->then(
            static function ($response) use ($odataSerializer) {
                $body = $response->getBody();
                $headers = HttpFormatter::formatHeaders($response->getHeaders());
                return InsertEntityResult::create(
                    $body,
                    $headers,
                    $odataSerializer
                );
            },
            null
        );
    }

    /**
     * Updates an existing entity or inserts a new entity if it does not exist
     * in the table.
     *
     * @param string              $table   name of the table
     * @param Entity              $entity  table entity
     * @param TableServiceOptions $options optional parameters
     *
     * @return UpdateEntityResult
     *
     * @see http://msdn.microsoft.com/en-us/library/windowsazure/hh452241.aspx
     */
    public function insertOrMergeEntity(
        $table,
        Entity $entity,
        TableServiceOptions $options = null
    ) {
        return $this->insertOrMergeEntityAsync($table, $entity, $options)->wait();
    }

    /**
     * Creates promise to update an existing entity or inserts a new entity if
     * it does not exist in the table.
     *
     * @param string              $table   name of the table
     * @param Entity              $entity  table entity
     * @param TableServiceOptions $options optional parameters
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     *
     * @see http://msdn.microsoft.com/en-us/library/windowsazure/hh452241.aspx
     */
    public function insertOrMergeEntityAsync(
        $table,
        Entity $entity,
        TableServiceOptions $options = null
    ) {
        return $this->putOrMergeEntityAsyncImpl(
            $table,
            $entity,
            Resources::HTTP_MERGE,
            false,
            $options
        );
    }

    /**
     * Replaces an existing entity or inserts a new entity if it does not exist in
     * the table.
     *
     * @param string              $table   name of the table
     * @param Entity              $entity  table entity
     * @param TableServiceOptions $options optional parameters
     *
     * @return UpdateEntityResult
     *
     * @see http://msdn.microsoft.com/en-us/library/windowsazure/hh452242.aspx
     */
    public function insertOrReplaceEntity(
        $table,
        Entity $entity,
        TableServiceOptions $options = null
    ) {
        return $this->insertOrReplaceEntityAsync(
            $table,
            $entity,
            $options
        )->wait();
    }

    /**
     * Creates a promise to replace an existing entity or inserts a new entity if it does not exist in the table.
     *
     * @param string              $table   name of the table
     * @param Entity              $entity  table entity
     * @param TableServiceOptions $options optional parameters
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     *
     * @see http://msdn.microsoft.com/en-us/library/windowsazure/hh452242.aspx
     */
    public function insertOrReplaceEntityAsync(
        $table,
        Entity $entity,
        TableServiceOptions $options = null
    ) {
        return $this->putOrMergeEntityAsyncImpl(
            $table,
            $entity,
            Resources::HTTP_PUT,
            false,
            $options
        );
    }

    /**
     * Updates an existing entity in a table. The Update Entity operation replaces
     * the entire entity and can be used to remove properties.
     *
     * @param string              $table   The table name.
     * @param Entity              $entity  The table entity.
     * @param TableServiceOptions $options The optional parameters.
     *
     * @return UpdateEntityResult
     *
     * @see http://msdn.microsoft.com/en-us/library/windowsazure/dd179427.aspx
     */
    public function updateEntity(
        $table,
        Entity $entity,
        TableServiceOptions $options = null
    ) {
        return $this->updateEntityAsync($table, $entity, $options)->wait();
    }

    /**
     * Creates promise to update an existing entity in a table. The Update Entity
     * operation replaces the entire entity and can be used to remove properties.
     *
     * @param string              $table   The table name.
     * @param Entity              $entity  The table entity.
     * @param TableServiceOptions $options The optional parameters.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     *
     * @see http://msdn.microsoft.com/en-us/library/windowsazure/dd179427.aspx
     */
    public function updateEntityAsync(
        $table,
        Entity $entity,
        TableServiceOptions $options = null
    ) {
        return $this->putOrMergeEntityAsyncImpl(
            $table,
            $entity,
            Resources::HTTP_PUT,
            true,
            $options
        );
    }

    /**
     * Updates an existing entity by updating the entity's properties. This operation
     * does not replace the existing entity, as the updateEntity operation does.
     *
     * @param string              $table   The table name.
     * @param Entity              $entity  The table entity.
     * @param TableServiceOptions $options The optional parameters.
     *
     * @return Models\UpdateEntityResult
     *
     * @see http://msdn.microsoft.com/en-us/library/windowsazure/dd179392.aspx
     */
    public function mergeEntity(
        $table,
        Entity $entity,
        TableServiceOptions $options = null
    ) {
        return $this->mergeEntityAsync($table, $entity, $options)->wait();
    }

    /**
     * Creates promise to update an existing entity by updating the entity's
     * properties. This operation does not replace the existing entity, as the
     * updateEntity operation does.
     *
     * @param string              $table   The table name.
     * @param Entity              $entity  The table entity.
     * @param TableServiceOptions $options The optional parameters.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     *
     * @see http://msdn.microsoft.com/en-us/library/windowsazure/dd179392.aspx
     */
    public function mergeEntityAsync(
        $table,
        Entity $entity,
        TableServiceOptions $options = null
    ) {
        return $this->putOrMergeEntityAsyncImpl(
            $table,
            $entity,
            Resources::HTTP_MERGE,
            true,
            $options
        );
    }

    /**
     * Deletes an existing entity in a table.
     *
     * @param string              $table        The name of the table.
     * @param string              $partitionKey The entity partition key.
     * @param string              $rowKey       The entity row key.
     * @param DeleteEntityOptions $options      The optional parameters.
     *
     * @see http://msdn.microsoft.com/en-us/library/windowsazure/dd135727.aspx
     */
    public function deleteEntity(
        $table,
        $partitionKey,
        $rowKey,
        DeleteEntityOptions $options = null
    ) {
        $this->deleteEntityAsync($table, $partitionKey, $rowKey, $options)->wait();
    }

    /**
     * Creates promise to delete an existing entity in a table.
     *
     * @param string              $table        The name of the table.
     * @param string              $partitionKey The entity partition key.
     * @param string              $rowKey       The entity row key.
     * @param DeleteEntityOptions $options      The optional parameters.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     *
     * @see http://msdn.microsoft.com/en-us/library/windowsazure/dd135727.aspx
     */
    public function deleteEntityAsync(
        $table,
        $partitionKey,
        $rowKey,
        DeleteEntityOptions $options = null
    ) {
        $context = $this->constructDeleteEntityContext(
            $table,
            $partitionKey,
            $rowKey,
            $options
        );

        return $this->sendContextAsync($context);
    }

    /**
     * Gets table entity.
     *
     * @param string                $table        The name of the table.
     * @param string                $partitionKey The entity partition key.
     * @param string                $rowKey       The entity row key.
     * @param GetEntityOptions|null $options      The optional parameters.
     *
     * @return GetEntityResult
     *
     * @see http://msdn.microsoft.com/en-us/library/windowsazure/dd179421.aspx
     */
    public function getEntity(
        $table,
        $partitionKey,
        $rowKey,
        GetEntityOptions $options = null
    ) {
        return $this->getEntityAsync(
            $table,
            $partitionKey,
            $rowKey,
            $options
        )->wait();
    }

    /**
     * Creates promise to get table entity.
     *
     * @param string                $table        The name of the table.
     * @param string                $partitionKey The entity partition key.
     * @param string                $rowKey       The entity row key.
     * @param GetEntityOptions|null $options      The optional parameters.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     *
     * @see http://msdn.microsoft.com/en-us/library/windowsazure/dd179421.aspx
     */
    public function getEntityAsync(
        $table,
        $partitionKey,
        $rowKey,
        GetEntityOptions $options = null
    ) {
        Validate::canCastAsString($table, 'table');
        Validate::notNullOrEmpty($table, 'table');
        Validate::isTrue(null !== $partitionKey, Resources::NULL_TABLE_KEY_MSG);
        Validate::isTrue(null !== $rowKey, Resources::NULL_TABLE_KEY_MSG);

        $method = Resources::HTTP_GET;
        $headers = [];
        $queryParams = [];
        $path = $this->getEntityPath($table, $partitionKey, $rowKey);

        if (null === $options) {
            $options = new GetEntityOptions();
        }

        // TODO: support payload format options
        $this->addOptionalHeader(
            $headers,
            Resources::CONTENT_TYPE,
            Resources::JSON_CONTENT_TYPE
        );
        $this->addOptionalHeader(
            $headers,
            Resources::ACCEPT_HEADER,
            $options->getAccept()
        );

        $context = new HttpCallContext();
        $context->setHeaders($headers);
        $context->setMethod($method);
        $context->setPath($path);
        $context->setQueryParameters($queryParams);
        $context->setStatusCodes([Resources::STATUS_OK]);
        $context->setServiceOptions($options);

        $odataSerializer = $this->odataSerializer;

        return $this->sendContextAsync($context)->then(
            static function ($response) use ($odataSerializer) {
                return GetEntityResult::create(
                    $response->getBody(),
                    $odataSerializer
                );
            },
            null
        );
    }

    /**
     * Does batch of operations on the table service.
     *
     * @param BatchOperations     $batchOperations The operations to apply.
     * @param TableServiceOptions $options         The optional parameters.
     *
     * @return BatchResult
     */
    public function batch(
        Models\BatchOperations $batchOperations,
        Models\TableServiceOptions $options = null
    ) {
        return $this->batchAsync($batchOperations, $options)->wait();
    }

    /**
     * Creates promise that does batch of operations on the table service.
     *
     * @param BatchOperations     $batchOperations The operations to apply.
     * @param TableServiceOptions $options         The optional parameters.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function batchAsync(
        Models\BatchOperations $batchOperations,
        Models\TableServiceOptions $options = null
    ) {
        Validate::notNullOrEmpty($batchOperations, 'batchOperations');

        $method = Resources::HTTP_POST;
        $operations = $batchOperations->getOperations();
        $contexts = $this->createOperationsContexts($operations);
        $mime = $this->createBatchRequestBody($operations, $contexts);
        $body = $mime['body'];
        $headers = $mime['headers'];
        $postParams = [];
        $queryParams = [];
        $path = '$batch';

        if (null === $options) {
            $options = new TableServiceOptions();
        }

        $odataSerializer = $this->odataSerializer;
        $mimeSerializer = $this->mimeSerializer;

        $options->setLocationMode(LocationMode::PRIMARY_ONLY);

        $this->addOptionalHeader(
            $headers,
            Resources::ACCEPT_HEADER,
            Resources::JSON_FULL_METADATA_CONTENT_TYPE
        );

        return $this->sendAsync(
            $method,
            $headers,
            $queryParams,
            $postParams,
            $path,
            Resources::STATUS_ACCEPTED,
            $body,
            $options
        )->then(static function ($response) use (
            $operations,
            $contexts,
            $odataSerializer,
            $mimeSerializer
        ) {
            return BatchResult::create(
                $response->getBody(),
                $operations,
                $contexts,
                $odataSerializer,
                $mimeSerializer
            );
        }, null);
    }

    /**
     * Gets the access control list (ACL)
     *
     * @param string              $table   The table name.
     * @param TableServiceOptions $options The optional parameters.
     *
     * @return TableACL
     *
     * @see https://docs.microsoft.com/en-us/rest/api/storageservices/fileservices/get-table-acl
     */
    public function getTableAcl(
        $table,
        Models\TableServiceOptions $options = null
    ) {
        return $this->getTableAclAsync($table, $options)->wait();
    }

    /**
     * Creates the promise to gets the access control list (ACL)
     *
     * @param string              $table   The table name.
     * @param TableServiceOptions $options The optional parameters.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     *
     * @see https://docs.microsoft.com/en-us/rest/api/storageservices/fileservices/get-table-acl
     */
    public function getTableAclAsync(
        $table,
        Models\TableServiceOptions $options = null
    ) {
        Validate::canCastAsString($table, 'table');

        $method = Resources::HTTP_GET;
        $headers = [];
        $postParams = [];
        $queryParams = [];
        $statusCode = Resources::STATUS_OK;
        $path = $table;

        if (null === $options) {
            $options = new TableServiceOptions();
        }

        $this->addOptionalQueryParam(
            $queryParams,
            Resources::QP_COMP,
            'acl'
        );

        $this->addOptionalHeader(
            $headers,
            Resources::ACCEPT_HEADER,
            Resources::XML_CONTENT_TYPE
        );

        $dataSerializer = $this->dataSerializer;

        $promise = $this->sendAsync(
            $method,
            $headers,
            $queryParams,
            $postParams,
            $path,
            Resources::STATUS_OK,
            Resources::EMPTY_STRING,
            $options
        );

        return $promise->then(static function ($response) use ($dataSerializer) {
            $parsed = $dataSerializer->unserialize($response->getBody());
            return TableACL::create($parsed);
        }, null);
    }

    /**
     * Sets the ACL.
     *
     * @param string              $table   name
     * @param TableACL            $acl     access control list for Table
     * @param TableServiceOptions $options optional parameters
     *
     * @see https://docs.microsoft.com/en-us/rest/api/storageservices/fileservices/set-table-acl
     */
    public function setTableAcl(
        $table,
        TableACL $acl,
        TableServiceOptions $options = null
    ) {
        $this->setTableAclAsync($table, $acl, $options)->wait();
    }

    /**
     * Creates promise to set the ACL
     *
     * @param string              $table   name
     * @param TableACL            $acl     access control list for Table
     * @param TableServiceOptions $options optional parameters
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     *
     * @see https://docs.microsoft.com/en-us/rest/api/storageservices/fileservices/set-table-acl
     */
    public function setTableAclAsync(
        $table,
        TableACL $acl,
        TableServiceOptions $options = null
    ) {
        Validate::canCastAsString($table, 'table');
        Validate::notNullOrEmpty($acl, 'acl');

        $method = Resources::HTTP_PUT;
        $headers = [];
        $postParams = [];
        $queryParams = [];
        $body = $acl->toXml($this->dataSerializer);
        $path = $table;

        if (null === $options) {
            $options = new TableServiceOptions();
        }

        $this->addOptionalQueryParam(
            $queryParams,
            Resources::QP_COMP,
            'acl'
        );

        $this->addOptionalHeader(
            $headers,
            Resources::ACCEPT_HEADER,
            Resources::XML_CONTENT_TYPE
        );

        $options->setLocationMode(LocationMode::PRIMARY_ONLY);

        return $this->sendAsync(
            $method,
            $headers,
            $queryParams,
            $postParams,
            $path,
            Resources::STATUS_NO_CONTENT,
            $body,
            $options
        );
    }
}
