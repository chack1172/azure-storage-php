# Azure-OSS Storage PHP Client Libraries

This project was forked due the retirement of the official Azure SDK. For more information visit
 [Retirement notice: The Azure Storage PHP client libraries will be retired on 17 March 2024](https://aka.ms/AzStoragePHPSDKRetirement).

---

This project provides a set of PHP client libraries that make it easy to access Microsoft Azure Storage services (blobs, tables, queues and files). For documentation on how to host PHP applications on Microsoft Azure, please see the [Microsoft Azure PHP Developer Center](http://www.windowsazure.com/en-us/develop/php/).

* azure-storage-blob [![Latest Stable Version](https://poser.pugx.org/microsoft/azure-storage-blob/v/stable)](https://packagist.org/packages/microsoft/azure-storage-blob)
* azure-storage-table [![Latest Stable Version](https://poser.pugx.org/microsoft/azure-storage-table/v/stable)](https://packagist.org/packages/microsoft/azure-storage-table)
* azure-storage-queue [![Latest Stable Version](https://poser.pugx.org/microsoft/azure-storage-queue/v/stable)](https://packagist.org/packages/microsoft/azure-storage-queue)
* azure-storage-file [![Latest Stable Version](https://poser.pugx.org/microsoft/azure-storage-file/v/stable)](https://packagist.org/packages/microsoft/azure-storage-file)
* azure-storage-common [![Latest Stable Version](https://poser.pugx.org/microsoft/azure-storage-common/v/stable)](https://packagist.org/packages/microsoft/azure-storage-common)

## Features

* Blobs
  * create, list, and delete containers, work with container metadata and permissions, list blobs in container
  * create block and page blobs (from a stream or a string), work with blob blocks and pages, delete blobs
  * work with blob properties, metadata, leases, snapshot a blob
* Tables
  * create and delete tables
  * create, query, insert, update, merge, and delete entities
  * batch operations
* Queues
  * create, list, and delete queues, and work with queue metadata and properties
  * create, get, peek, update, delete messages
* Files
  * create, list, and delete file shares and directories
  * create, delete and download files

Please check details on [API reference documents](http://azure.github.io/azure-storage-php).

## Minimum Requirements

* PHP 5.6 or above
* See [composer.json](composer.json) for dependencies
* Required extension for PHP:
  * php_fileinfo.dll
  * php_mbstring.dll
  * php_openssl.dll
  * php_xsl.dll

* Recommended extension for PHP:
  * php_curl.dll

## Install via Composer

1. Create a file named **composer.json** in the root of your project and add the following code to it:

```json
{
  "require": {
    "microsoft/azure-storage-blob": "*",
    "microsoft/azure-storage-table": "*",
    "microsoft/azure-storage-queue": "*",
    "microsoft/azure-storage-file": "*"
  }
}
```

1. Download **[composer.phar](http://getcomposer.org/composer.phar)** in your project root.

1. Open a command prompt and execute this in your project root

```shell
php composer.phar install
```

## Usage

There are four basic steps that have to be performed before you can make a call to any Microsoft Azure Storage API when using the libraries.

* First, include the autoloader script:

```php
require_once "vendor/autoload.php"; 
```

* Include the namespaces you are going to use.

  To create any Microsoft Azure service client you need to use the rest proxy classes, such as **BlobRestProxy** class:

```php
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
```

  To process exceptions you need:

```php
use MicrosoftAzure\Storage\Common\ServiceException;
```
  
* To instantiate the service client you will also need a valid [connection string](https://azure.microsoft.com/en-us/documentation/articles/storage-configure-connection-string/). The format is:

```json
DefaultEndpointsProtocol=[http|https];AccountName=[yourAccount];AccountKey=[yourKey]
```

Or:
  
```json
BlobEndpoint=myBlobEndpoint;QueueEndpoint=myQueueEndpoint;TableEndpoint=myTableEndpoint;FileEndpoint=myFileEndpoint;SharedAccessSignature=sasToken
```

Or if AAD authentication is used:

```json
BlobEndpoint=myBlobEndpoint;QueueEndpoint=myQueueEndpoint;TableEndpoint=myTableEndpoint;FileEndpoint=myFileEndpoint;AccountName=[yourAccount]
```

Note that account name is required.

* Instantiate a client object - a wrapper around the available calls for the given service.

```php
$blobClient = BlobRestProxy::createBlobService($connectionString);
$tableClient = TableRestProxy::createTableService($connectionString);
$queueClient = QueueRestProxy::createQueueService($connectionString);
$fileClient = FileRestProxy::createFileService($connectionString);
```

Or for AAD authentication:

```php
$blobClient = BlobRestProxy::createBlobServiceWithTokenCredential($token, $connectionString);
$queueClient = QueueRestProxy::createQueueServiceWithTokenCredential($token, $connectionString);
```

Note that Blob and Queue service supports AAD authentication.

### Using Middlewares

To specify the middlewares, user have to create an array with middlewares
and put it in the `$requestOptions` with key 'middlewares'. The sequence of
the array will affect the sequence in which the middleware is invoked. The
`$requestOptions` can usually be set in the options of an API call, such as
`MicrosoftAzure\Storage\Blob\Models\ListBlobOptions`.

The user can push the middleware into the array with key 'middlewares' in
services' `$_options` instead when creating them if the middleware is to be
applied to each of the API call for a rest proxy. These middlewares will always
be invoked after the middlewares in the `$requestOptions`.
e.g.:

```php
$tableClient = TableRestProxy::createTableService(
    $connectionString,
    $optionsWithMiddlewares
);
```

Each of the middleware should be either an instance of a sub-class that
implements `MicrosoftAzure\Storage\Common\Internal\IMiddleware`, or a
`callable` that follows the Guzzle middleware implementation convention.

User can create self-defined middleware that inherits from `MicrosoftAzure\Storage\Common\Internal\Middlewares\MiddlewareBase`.

## Retrying failures

You can use bundled middlewares to retry requests in case they fail for some reason. First you create the middleware:

```php
$retryMiddleware = RetryMiddlewareFactory::create(
    RetryMiddlewareFactory::GENERAL_RETRY_TYPE,  // Specifies the retry logic
    3,  // Number of retries
    1000,  // Interval
    RetryMiddlewareFactory::EXPONENTIAL_INTERVAL_ACCUMULATION,  // How to increase the wait interval
    true  // Whether to retry connection failures too, default false
);
```

Then you add the middleware when creating the service as explained above:

```php
$optionsWithMiddlewares = [
    'middlewares' = [
        $retryMiddleware
    ],
];
$tableClient = TableRestProxy::createTableService(
    $connectionString,
    $optionsWithMiddlewares
);
```

Or by pushing it to the existing service:

```php
$tableClient->pushMiddleware($retryMiddleware);
```

Following errors are not retried in current retry middleware:

* Authentication failures.
* "Resource Not Found" errors.
* Guzzle request exceptions that does not bear an HTTP response, e.g. failed to open stream, or cURL Connection reset by peer, etc.
*Note:* Community contribution to cover the Guzzle request exceptions are welcomed.

### Retry types

* `RetryMiddlewareFactory::GENERAL_RETRY_TYPE` - General type of logic that handles retry
* `RetryMiddlewareFactory::APPEND_BLOB_RETRY_TYPE` * For the append blob retry only, currently the same as the general type

### Interval accumulations

* `RetryMiddlewareFactory::LINEAR_INTERVAL_ACCUMULATION` - The interval will be increased linearly, the *nth* retry will have a wait time equal to *n \* interval*
* `RetryMiddlewareFactory::EXPONENTIAL_INTERVAL_ACCUMULATION` - The interval will be increased exponentially, the *nth* retry will have a wait time equal to *pow(2, n) \* interval*

### Using proxies

To use proxies during HTTP requests, set system variable `HTTP_PROXY` and the proxy will be used.

## Troubleshooting

### Error: Unable to get local issuer certificate

cURL can't verify the validity of Microsoft certificate when trying to issue a request call to Azure Storage Services. You must configure cURL to use a certificate when issuing https requests by the following steps:

1. Download the cacert.pem file from [cURL site](http://curl.haxx.se/docs/caextract.html).

2. Then either:
    * Open your php.ini file and add the following line:

        ```ini
        curl.cainfo = "<absolute path to cacert.pem>"
        ```

        OR
    * Point to the cacert in the options when creating the Relevant Proxy.

        ```php
        //example of creating the FileRestProxy
        $options["http"] = ["verify" => "<absolute path to cacert.pem>"];
        FileRestProxy::createFileService($connectionString, $options);
        ```

## Code samples

You can find samples in the [samples folder](https://github.com/Azure-OSS/azure-storage-php/tree/main/samples).

## Contribute Code or Provide Feedback

You can find more details for contributing in the [CONTRIBUTING.md](CONTRIBUTING.md).

If you encounter any bugs with the library please file an issue in the [Issues](https://github.com/Azure-OSS/azure-storage-php/issues) section of the project.
