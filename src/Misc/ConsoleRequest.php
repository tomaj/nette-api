<?php

namespace Tomaj\NetteApi\Misc;

use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Params\InputParam;

class ConsoleRequest
{
    /**
     * @var ApiHandlerInterface
     */
    private $handler;

    /**
     * Create ConsoleRequest
     *
     * @param ApiHandlerInterface $handler
     */
    public function __construct(ApiHandlerInterface $handler)
    {
        $this->handler = $handler;
    }

    /**
     * Make request to API url
     *
     * @param string $url
     * @param string $method
     * @param array $values
     * @param string|null $token
     *
     * @return ConsoleResponse
     */
    public function makeRequest($url, $method, array $values, $token = null)
    {
        list($postFields, $getFields) = $this->processValues($values);

        $postFields = $this->normalizeValues($postFields);
        $getFields = $this->normalizeValues($getFields);

        if (count($getFields)) {
            $parts = [];
            foreach ($getFields as $key => $value) {
                $parts[] = "$key=$value";
            }
            $url = $url . '?' . implode('&', $parts);
        }

        $startTime = microtime();

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_NOBODY, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_VERBOSE, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_HEADER, true);
        if (count($postFields)) {
            curl_setopt($curl, CURLOPT_POST, true);

            curl_setopt($curl, CURLOPT_POSTFIELDS, $postFields);
        }

        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        $headers = [];
        if ($token !== null && $token !== false) {
            $headers = ['Authorization: Bearer ' . $token];
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        $consoleResponse = new ConsoleResponse(
            $url,
            $method,
            $postFields,
            $getFields,
            $headers
        );

        $response = curl_exec($curl);
        $elapsed = intval((microtime() - $startTime) * 1000);

        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        $curlErrorNumber = curl_errno($curl);
        $curlError = curl_error($curl);
        if ($curlErrorNumber > 0) {
            $consoleResponse->logError($curlErrorNumber, $curlError, $elapsed);
        } else {
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $consoleResponse->logRequest($httpCode, $responseBody, $responseHeaders, $elapsed);
        }

        return $consoleResponse;
    }

    /**
     * Process given values to POST and GET fields
     *
     * @param array $values
     *
     * @return array
     */
    private function processValues(array $values)
    {
        $params = $this->handler->params();

        $postFields = [];
        $getFields = [];


        foreach ($values as $key => $value) {
            if (strstr($key, '___') !== false) {
                $parts = explode('___', $key);
                $key = $parts[0];
            }

            foreach ($params as $param) {
                $valueData = $this->processParam($param, $key, $value);

                if ($valueData === null) {
                    continue;
                }

                if ($param->isMulti()) {
                    if (in_array($param->getType(), [InputParam::TYPE_POST, InputParam::TYPE_FILE])) {
                        $postFields[$key][] = $valueData;
                    } else {
                        $getFields[$key][] = $valueData;
                    }
                } else {
                    if (in_array($param->getType(), [InputParam::TYPE_POST, InputParam::TYPE_FILE])) {
                        $postFields[$key] = $valueData;
                    } else {
                        $getFields[$key] = $valueData;
                    }
                }
            }
        }

        return [$postFields, $getFields];
    }

    /**
     * Process one param and returns value
     *
     * @param InputParam  $param   input param
     * @param string      $key     param key
     * @param string      $value   actual value from request
     *
     * @return string
     */
    private function processParam(InputParam $param, $key, $value)
    {
        if ($param->getKey() == $key) {
            if (!$value) {
                return null;
            }

            $valueData = $value;

            if ($param->getType() == InputParam::TYPE_FILE) {
                if ($value->isOk()) {
                    $valueData = curl_file_create($value->getTemporaryFile(), $value->getContentType(), $value->getName());
                } else {
                    $valueData = false;
                }
            }

            return $valueData;
        }
        return null;
    }

    /**
     * Normalize values array.
     *
     * @param $values
     * @return array
     */
    private function normalizeValues($values)
    {
        $result = [];
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $counter = 0;
                foreach ($value as $innerValue) {
                    if ($innerValue != null) {
                        $result[$key . "[".$counter++."]"] = $innerValue;
                    }
                }
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
