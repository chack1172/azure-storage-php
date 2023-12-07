<?php

namespace AzureOSS\Storage\Common\Models;

use AzureOSS\Storage\Common\Internal\Resources;
use AzureOSS\Storage\Common\Internal\Serialization\XmlSerializer;

class ServiceProperties
{
    private $logging;
    private $hourMetrics;
    private $minuteMetrics;
    private $corses;
    private $defaultServiceVersion;

    private static $xmlRootName = 'StorageServiceProperties';

    /**
     * Creates ServiceProperties object from parsed XML response.
     *
     * @internal
     *
     * @param array $parsedResponse XML response parsed into array.
     *
     * @return ServiceProperties.
     */
    public static function create(array $parsedResponse)
    {
        $result = new ServiceProperties();

        if (
            array_key_exists(Resources::XTAG_DEFAULT_SERVICE_VERSION, $parsedResponse)
            && $parsedResponse[Resources::XTAG_DEFAULT_SERVICE_VERSION] != null
        ) {
            $result->setDefaultServiceVersion($parsedResponse[Resources::XTAG_DEFAULT_SERVICE_VERSION]);
        }

        if (array_key_exists(Resources::XTAG_LOGGING, $parsedResponse)) {
            $result->setLogging(Logging::create($parsedResponse[Resources::XTAG_LOGGING]));
        }
        if (array_key_exists(Resources::XTAG_HOUR_METRICS, $parsedResponse)) {
            $result->setHourMetrics(Metrics::create($parsedResponse[Resources::XTAG_HOUR_METRICS]));
        }
        if (array_key_exists(Resources::XTAG_MINUTE_METRICS, $parsedResponse)) {
            $result->setMinuteMetrics(Metrics::create($parsedResponse[Resources::XTAG_MINUTE_METRICS]));
        }
        if (
            array_key_exists(Resources::XTAG_CORS, $parsedResponse)
            && $parsedResponse[Resources::XTAG_CORS] != null
        ) {
            //There could be multiple CORS rules, so need to extract them all.
            $corses = [];
            $corsArray =
                $parsedResponse[Resources::XTAG_CORS][Resources::XTAG_CORS_RULE];
            if (count(array_filter(array_keys($corsArray), 'is_string')) > 0) {
                //single cors rule
                $corses[] = CORS::create($corsArray);
            } else {
                //multiple cors rule
                foreach ($corsArray as $cors) {
                    $corses[] = CORS::create($cors);
                }
            }

            $result->setCorses($corses);
        } else {
            $result->setCorses([]);
        }

        return $result;
    }

    /**
     * Gets logging element.
     *
     * @return Logging
     */
    public function getLogging()
    {
        return $this->logging;
    }

    /**
     * Sets logging element.
     *
     * @param Logging $logging new element.
     */
    public function setLogging(Logging $logging)
    {
        $this->logging = clone $logging;
    }

    /**
     * Gets hour metrics element.
     *
     * @return Metrics
     */
    public function getHourMetrics()
    {
        return $this->hourMetrics;
    }

    /**
     * Sets hour metrics element.
     */
    public function setHourMetrics(Metrics $hourMetrics)
    {
        $this->hourMetrics = clone $hourMetrics;
    }

    /**
     * Gets minute metrics element.
     *
     * @return Metrics
     */
    public function getMinuteMetrics()
    {
        return $this->minuteMetrics;
    }

    /**
     * Sets minute metrics element.
     */
    public function setMinuteMetrics(Metrics $minuteMetrics)
    {
        $this->minuteMetrics = clone $minuteMetrics;
    }

    /**
     * Gets corses element.
     *
     * @return CORS[]
     */
    public function getCorses()
    {
        return $this->corses;
    }

    /**
     * Sets corses element.
     *
     * @param CORS[] $corses new elements.
     */
    public function setCorses(array $corses)
    {
        $this->corses = $corses;
    }

    /**
     * Gets the default service version.
     *
     * @return string
     */
    public function getDefaultServiceVersion()
    {
        return $this->defaultServiceVersion;
    }

    /**
     * Sets the default service version. This can obly be set for the blob service.
     *
     * @param string $defaultServiceVersion the default service version
     */
    public function setDefaultServiceVersion($defaultServiceVersion)
    {
        $this->defaultServiceVersion = $defaultServiceVersion;
    }

    /**
     * Converts this object to array with XML tags
     *
     * @internal
     *
     * @return array
     */
    public function toArray()
    {
        $result = [];

        if (!empty($this->getLogging())) {
            $result[Resources::XTAG_LOGGING] =
                $this->getLogging()->toArray();
        }

        if (!empty($this->getHourMetrics())) {
            $result[Resources::XTAG_HOUR_METRICS] =
                $this->getHourMetrics()->toArray();
        }

        if (!empty($this->getMinuteMetrics())) {
            $result[Resources::XTAG_MINUTE_METRICS] =
                $this->getMinuteMetrics()->toArray();
        }

        $corsesArray = $this->getCorsesArray();
        if (!empty($corsesArray)) {
            $result[Resources::XTAG_CORS] = $corsesArray;
        }

        if ($this->defaultServiceVersion != null) {
            $result[Resources::XTAG_DEFAULT_SERVICE_VERSION] = $this->defaultServiceVersion;
        }

        return $result;
    }

    /**
     * Gets the array that contains all the CORSes.
     *
     * @return array
     */
    private function getCorsesArray()
    {
        $corsesArray = [];
        if (count($this->getCorses()) == 1) {
            $corsesArray = [
                Resources::XTAG_CORS_RULE => $this->getCorses()[0]->toArray(),
            ];
        } elseif ($this->getCorses() != []) {
            foreach ($this->getCorses() as $cors) {
                $corsesArray[] = [Resources::XTAG_CORS_RULE => $cors->toArray()];
            }
        }

        return $corsesArray;
    }

    /**
     * Converts this current object to XML representation.
     *
     * @internal
     *
     * @param XmlSerializer $xmlSerializer The XML serializer.
     *
     * @return string
     */
    public function toXml(XmlSerializer $xmlSerializer)
    {
        $properties = [XmlSerializer::ROOT_NAME => self::$xmlRootName];
        return $xmlSerializer->serialize($this->toArray(), $properties);
    }
}
