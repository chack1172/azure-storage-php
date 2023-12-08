<?php

namespace AzureOSS\Storage\Blob\Models;

use AzureOSS\Storage\Blob\Internal\BlobResources as Resources;
use AzureOSS\Storage\Common\Internal\Utilities;
use AzureOSS\Storage\Common\Internal\Validate;

class SetBlobPropertiesResult
{
    private $_lastModified;
    private $_etag;
    private $_sequenceNumber;

    /**
     * Creates SetBlobPropertiesResult from response headers.
     *
     * @param array $headers response headers
     *
     * @internal
     *
     * @return SetBlobPropertiesResult
     */
    public static function create(array $headers)
    {
        $result = new SetBlobPropertiesResult();
        $date = Utilities::tryGetValueInsensitive(
            Resources::LAST_MODIFIED,
            $headers
        );
        $result->setLastModified(Utilities::rfc1123ToDateTime($date));
        $result->setETag(Utilities::tryGetValueInsensitive(
            Resources::ETAG,
            $headers
        ));
        $result->setSequenceNumber((int) Utilities::tryGetValueInsensitive(
            Resources::X_MS_BLOB_SEQUENCE_NUMBER,
            $headers
        ));

        return $result;
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
    protected function setLastModified(\DateTime $lastModified)
    {
        Validate::isDate($lastModified);
        $this->_lastModified = $lastModified;
    }

    /**
     * Gets blob etag.
     *
     * @return string
     */
    public function getETag()
    {
        return $this->_etag;
    }

    /**
     * Sets blob etag.
     *
     * @param string $etag value.
     */
    protected function setETag($etag)
    {
        Validate::canCastAsString($etag, 'etag');
        $this->_etag = $etag;
    }

    /**
     * Gets blob sequenceNumber.
     *
     * @return int
     */
    public function getSequenceNumber()
    {
        return $this->_sequenceNumber;
    }

    /**
     * Sets blob sequenceNumber.
     *
     * @param int $sequenceNumber value.
     */
    protected function setSequenceNumber($sequenceNumber)
    {
        Validate::isInteger($sequenceNumber, 'sequenceNumber');
        $this->_sequenceNumber = $sequenceNumber;
    }
}
