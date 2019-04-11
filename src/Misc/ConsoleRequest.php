<?php

namespace Tomaj\NetteApi\Misc;

use Nette\Http\FileUpload;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Params\InputParam;
use Tomaj\NetteApi\Params\ParamInterface;

class ConsoleRequest
{
    /** @var ApiHandlerInterface */
    private $handler;

    public function __construct(ApiHandlerInterface $handler)
    {
        $this->handler = $handler;
    }

    public function makeRequest(string $url, string $method, array $values, array $additionalValues = [], ?string $token = null): ConsoleResponse
    {
        list($postFields, $getFields, $cookieFields, $rawPost, $putFields) = $this->processValues($values);

        if (isset($additionalValues['postFields'])) {
            $postFields = array_merge($postFields, $additionalValues['postFields']);
        }

        if (isset($additionalValues['getFields'])) {
            $getFields = array_merge($postFields, $additionalValues['getFields']);
        }

        if (isset($additionalValues['cookieFields'])) {
            $cookieFields = array_merge($postFields, $additionalValues['cookieFields']);
        }

        if (isset($additionalValues['putFields'])) {
            $putFields = array_merge($putFields, $additionalValues['putFields']);
        }

        $postFields = $this->normalizeValues($postFields);
        $getFields = $this->normalizeValues($getFields);
        $putFields = $this->normalizeValues($putFields);

        if (count($getFields)) {
            $parts = [];
            foreach ($getFields as $key => $value) {
                $parts[] = "$key=$value";
            }
            $url = $url . '?' . implode('&', $parts);
        }

        $putRawPost = null;
        if (count($putFields)) {
            $parts = [];
            foreach ($putFields as $key => $value) {
                $parts[] = "$key=$value";
            }
            $putRawPost = implode('&', $parts);
        }

        $startTime = microtime(true);

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
        if ($rawPost) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $rawPost);
        }
        if ($putRawPost) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $putRawPost);
        }
        if (count($cookieFields)) {
            $parts = [];
            foreach ($cookieFields as $key => $value) {
                $parts[] = "$key=$value";
            }
            curl_setopt($curl, CURLOPT_HTTPHEADER, ["Cookie: " . implode('&', $parts)]);
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
            $cookieFields,
            $headers,
            $rawPost
        );

        $response = curl_exec($curl);
        $elapsed = intval((microtime(true) - $startTime) * 1000);

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
    private function processValues(array $values): array
    {
        $params = $this->handler->params();

        $postFields = [];
        $rawPost = isset($values['post_raw']) ? $values['post_raw'] : false;
        $getFields = [];
        $putFields = [];
        $cookieFields = [];

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
                    } elseif ($param->getType() == InputParam::TYPE_PUT) {
                        $putFields[$key][] = $valueData;
                    } elseif ($param->getType() == InputParam::TYPE_COOKIE) {
                        $cookieFields[$key][] = $valueData;
                    } else {
                        $getFields[$key][] = $valueData;
                    }
                } else {
                    if (in_array($param->getType(), [InputParam::TYPE_POST, InputParam::TYPE_FILE])) {
                        $postFields[$key] = $valueData;
                    } elseif ($param->getType() == InputParam::TYPE_PUT) {
                        $putFields[$key] = $valueData;
                    } elseif ($param->getType() == InputParam::TYPE_COOKIE) {
                        $cookieFields[$key] = $valueData;
                    } else {
                        $getFields[$key] = $valueData;
                    }
                }
            }
        }

        return [$postFields, $getFields, $cookieFields, $rawPost, $putFields];
    }

    /**
     * Process one param and returns value
     *
     * @param ParamInterface  $param   input param
     * @param string          $key     param key
     * @param mixed           $value   actual value from request
     *
     * @return string|null
     */
    private function processParam(ParamInterface $param, string $key, $value): ?string
    {
        if ($param->getKey() == $key) {
            $valueData = $value;

            if ($param->getType() === InputParam::TYPE_FILE) {
                /** @var FileUpload $file */
                $file = $value;
                if ($file->isOk()) {
                    $valueData = curl_file_create($file->getTemporaryFile(), $file->getContentType(), $file->getName());
                } else {
                    $valueData = false;
                }
            } elseif ($param->getType() === InputParam::TYPE_POST_RAW) {
                $valueData = file_get_contents('php://input');
            }

            return $valueData;
        }
        return null;
    }

    private function normalizeValues(array $values): array
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
