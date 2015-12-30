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
     * @return ConsoleResponse
     */
    public function makeRequest($url, $method, $values, $token = null)
    {
        list($postFields, $getFields) = $this->processValues($values);

        if (count($getFields)) {
            $url = $url . '?' . implode('&', $getFields);
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
        if (count($postFields)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, implode('&', $postFields));
        }

        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        $headers = [];
        if ($token) {
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

        $responseBody = curl_exec($curl);
        $elapsed = intval((microtime() - $startTime) * 1000);

        $curlErrorNumber = curl_errno($curl);
        $curlError = curl_error($curl);
        if ($curlErrorNumber > 0) {
            $consoleResponse->logError($curlErrorNumber, $curlError, $elapsed);
        } else {
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $consoleResponse->logRequest($httpCode, $responseBody, $elapsed);
        }

        return $consoleResponse;
    }

    private function processValues($values)
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
                if ($valueData == null) {
                    continue;
                }

                if ($param->getType() == InputParam::TYPE_POST) {
                    $postFields[] = $valueData;
                } else {
                    $getFields[] = $valueData;
                }
            }
        }

        return [$postFields, $getFields];
    }

    private function processParam($param, $key, $value)
    {
        if ($param->getKey() == $key) {
            if (!$value) {
                return null;
            }
            if ($param->isMulti()) {
                $valueKey = '';
                if (strstr($value, '=') !== false) {
                    $parts = explode('=', $value);
                    $valueKey = $parts[0];
                    $value = $parts[1];
                }
                $valueData = $key . "[$valueKey]=$value";
            } else {
                $valueData = "$key=$value";
            }

            return $valueData;
        }
        return null;
    }
}