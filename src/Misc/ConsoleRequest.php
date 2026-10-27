<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

use Nette\Http\FileUpload;
use Tomaj\NetteApi\EndpointInterface;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Link\ApiLink;
use Tomaj\NetteApi\Params\InputParam;
use Tomaj\NetteApi\Params\ParamInterface;

final readonly class ConsoleRequest
{
    public const int DEFAULT_TIMEOUT = 30;

    public const int ELAPSED_MILLISECONDS = 1000;

    public function __construct(private ApiHandlerInterface $handler, private ?EndpointInterface $endpoint = null, private ?ApiLink $apiLink = null)
    {
    }

    /**
     * @param array<string,mixed> $values
     * @param array{postFields?: array<string, mixed>, getFields?: array<string, mixed>, cookieFields?: array<string, mixed>, putFields?: array<string, mixed>, headers?: list<string>, timeout?: int} $additionalValues
     */
    public function makeRequest(string $url, string $method, array $values, array $additionalValues = [], ?string $token = null): ConsoleResponse
    {
        [$postFields, $getFields, $cookieFields, $rawPost, $putFields] = $this->processValues($values);

        $postFields = array_merge($postFields, $additionalValues['postFields'] ?? []);
        $getFields = array_merge($getFields, $additionalValues['getFields'] ?? []);
        $cookieFields = array_merge($cookieFields, $additionalValues['cookieFields'] ?? []);
        $putFields = array_merge($putFields, $additionalValues['putFields'] ?? []);

        $postFields = $this->normalizeValues($postFields);
        $getFields = $this->normalizeValues($getFields);
        $putFields = $this->normalizeValues($putFields);

        if ($this->endpoint && $this->apiLink) {
            $url = $this->apiLink->link($this->endpoint, $getFields);
        } elseif ($getFields !== []) {
            $parts = [];
            foreach ($getFields as $key => $value) {
                $parts[] = sprintf('%s=%s', $key, TypeHelper::toString($value));
            }

            $parsedUrl = parse_url($url);

            $url = $url . (isset($parsedUrl['query']) ? '&' : '?') . implode('&', $parts);
        }

        $putRawPost = null;
        if ($putFields !== []) {
            $parts = [];
            foreach ($putFields as $key => $value) {
                $parts[] = sprintf('%s=%s', $key, TypeHelper::toString($value));
            }

            $putRawPost = implode('&', $parts);
        }

        $startTime = microtime(true);

        $curl = curl_init();
        /** @phpstan-ignore-next-line */
        curl_setopt($curl, CURLOPT_URL, $url);
        /** @phpstan-ignore-next-line */
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_NOBODY, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_VERBOSE, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, $additionalValues['timeout'] ?? self::DEFAULT_TIMEOUT);
        curl_setopt($curl, CURLOPT_HEADER, true);

        if (count($postFields) || $rawPost || $putRawPost !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            /** @phpstan-ignore-next-line */
            curl_setopt($curl, CURLOPT_POSTFIELDS, count($postFields) ? $postFields : ($rawPost ?: $putRawPost));
        }

        $headers = $additionalValues['headers'] ?? [];
        if ($cookieFields !== []) {
            $parts = [];
            foreach ($cookieFields as $key => $value) {
                $parts[] = sprintf('%s=%s', $key, TypeHelper::toString($value));
            }

            $headers[] = 'Cookie: ' . implode('&', $parts);
        }

        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        if (count($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        $basicAuthUsername = $values['basic_authentication_username'] ?? null;
        $basicAuthPassword = $values['basic_authentication_password'] ?? null;
        if ($basicAuthUsername && $basicAuthPassword) {
            curl_setopt($curl, CURLOPT_USERPWD, sprintf('%s:%s', TypeHelper::toString($basicAuthUsername), TypeHelper::toString($basicAuthPassword)));
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
        $elapsed = (int) ((microtime(true) - $startTime) * self::ELAPSED_MILLISECONDS);

        if ($response === false) {
            $response = '';
        }

        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $response = (string) $response;
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
     * @param array<string,mixed> $values
     * @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<string,mixed>,3:string|null,4:array<string,mixed>}
     */
    private function processValues(array $values): array
    {
        $params = $this->handler->params();

        $rawPost = isset($values['post_raw']) ? TypeHelper::toString($values['post_raw']) : null;
        $postFields = [];
        $getFields = [];
        $putFields = [];
        $cookieFields = [];

        foreach ($values as $key => $value) {
            if (str_contains($key, '___')) {
                $parts = explode('___', $key);
                $key = $parts[0];
            }

            foreach ($params as $param) {
                $valueData = $this->processParam($param, $key, $value);
                if ($valueData === null) {
                    continue;
                }

                if ($param->isMulti()) {
                    if (in_array($param->getType(), [InputParam::TYPE_POST, InputParam::TYPE_FILE], true)) {
                        $postFields[$key] = $this->appendValue($postFields[$key] ?? null, $valueData);
                    } elseif ($param->getType() === InputParam::TYPE_PUT) {
                        $putFields[$key] = $this->appendValue($putFields[$key] ?? null, $valueData);
                    } elseif ($param->getType() === InputParam::TYPE_COOKIE) {
                        $cookieFields[$key] = $this->appendValue($cookieFields[$key] ?? null, $valueData);
                    } else {
                        $getFields[$key] = $this->appendValue($getFields[$key] ?? null, urlencode(TypeHelper::toString($valueData)));
                    }
                } else {
                    if (in_array($param->getType(), [InputParam::TYPE_POST, InputParam::TYPE_FILE], true)) {
                        $postFields[$key] = $valueData;
                    } elseif ($param->getType() === InputParam::TYPE_PUT) {
                        $putFields[$key] = $valueData;
                    } elseif ($param->getType() === InputParam::TYPE_COOKIE) {
                        $cookieFields[$key] = $valueData;
                    } else {
                        $getFields[$key] = urlencode(TypeHelper::toString($valueData));
                    }
                }
            }
        }

        return [$postFields, $getFields, $cookieFields, $rawPost, $putFields];
    }

    /**
     * @return list<mixed>
     */
    private function appendValue(mixed $current, mixed $value): array
    {
        $list = is_array($current) ? array_values($current) : [];
        $list[] = $value;
        return $list;
    }

    /**
     * Process one param and returns value
     *
     * @param ParamInterface  $param   input param
     * @param string          $key     param key
     * @param mixed           $value   actual value from request
     *
     * @return mixed
     */
    private function processParam(ParamInterface $param, string $key, $value)
    {
        if ($param->getKey() === $key) {
            $valueData = $value;

            if ($param->getType() === InputParam::TYPE_FILE) {
                /** @var FileUpload $file */
                $file = $value;
                if ($file->isOk()) {
                    $valueData = curl_file_create($file->getTemporaryFile(), $file->getContentType(), $file->getName());
                } else {
                    $valueData = null;
                }
            } elseif ($param->getType() === InputParam::TYPE_POST_RAW) {
                $valueData = file_get_contents('php://input');
            }

            return $valueData;
        }

        return null;
    }

    /**
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    private function normalizeValues(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            if (!is_array($value)) {
                $result[$key] = $value;
                continue;
            }

            foreach ($value as $innerKey => $innerValue) {
                if ($innerValue !== '' && $innerValue !== null) {
                    $result[$key . '[' . $innerKey . ']'] = $innerValue;
                }
            }
        }

        return $result;
    }
}
