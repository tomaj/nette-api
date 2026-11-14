<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

use Nette\Http\FileUpload;
use Tomaj\NetteApi\EndpointInterface;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Input\MediaTypes;
use Tomaj\NetteApi\Link\ApiLink;
use Tomaj\NetteApi\Params\InputParam;
use Tomaj\NetteApi\Params\MappedInputParam;
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
        [$postFields, $getFields, $cookieFields, $rawPost, $putFields, $pathFields, $headerFields, $bodyFields] = $this->processValues($values);

        foreach ($pathFields as $name => $value) {
            if (trim($value) === '') {
                // the request is not sent: the URL would have an empty segment and the server could only answer 404
                $response = new ConsoleResponse($url, $method, [], [], [], [], null);
                $response->logError(0, sprintf("The path value '%s' is empty. Fill it in and send the request again.", $name), 0);

                return $response;
            }
        }

        $postFields = array_merge($postFields, $additionalValues['postFields'] ?? []);
        $getFields = array_merge($getFields, $additionalValues['getFields'] ?? []);
        $cookieFields = array_merge($cookieFields, $additionalValues['cookieFields'] ?? []);
        $putFields = array_merge($putFields, $additionalValues['putFields'] ?? []);

        $postFields = $this->normalizeValues($postFields);
        $getFields = $this->normalizeValues($getFields);
        $putFields = $this->normalizeValues($putFields);

        if ($this->endpoint && $this->apiLink) {
            // link() builds the URL, so an overridden link() still decides it; the path values go into the {name} segments afterwards
            $url = $this->apiLink->link($this->endpoint, $getFields);
        }
        $url = self::substitutePath($url, $pathFields);

        if (!($this->endpoint && $this->apiLink) && $getFields !== []) {
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

        $headers = $additionalValues['headers'] ?? [];
        foreach ($headerFields as $name => $value) {
            $headers[] = sprintf('%s: %s', $name, str_replace(["\r", "\n"], '', TypeHelper::toString($value)));
        }

        if ($bodyFields !== []) {
            $mediaType = $postFields !== [] ? MediaTypes::MULTIPART : $this->bodyMediaType();
            if ($mediaType === MediaTypes::MULTIPART) {
                // an array of fields makes curl send multipart/form-data
                $postFields = array_merge($postFields, self::flatten($bodyFields));
            } elseif ($mediaType === MediaTypes::FORM) {
                $rawPost = http_build_query(self::flatten($bodyFields));
                $headers[] = 'Content-Type: ' . MediaTypes::FORM;
            } else {
                $rawPost = json_encode($bodyFields, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
                $headers[] = 'Content-Type: ' . MediaTypes::JSON;
            }
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
     * @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<string,mixed>,3:string|null,4:array<string,mixed>,5:array<string,string>,6:array<string,mixed>,7:array<string,mixed>}
     */
    private function processValues(array $values): array
    {
        $params = $this->handler->params();

        $rawPost = isset($values['post_raw']) ? TypeHelper::toString($values['post_raw']) : null;
        $postFields = [];
        $getFields = [];
        $putFields = [];
        $cookieFields = [];
        $pathFields = [];
        $headerFields = [];
        $bodyFields = [];

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

                $type = $param->getType();
                if ($type === InputParam::TYPE_PATH) {
                    $pathFields[$key] = TypeHelper::toString($valueData);
                } elseif ($type === InputParam::TYPE_HEADER) {
                    $headerFields[$key] = $valueData;
                } elseif ($type === InputParam::TYPE_BODY) {
                    $schema = $param instanceof InputParam ? $param->getOpenApiSchema() : null;
                    if ($param->isMulti()) {
                        $bodyFields[$key] = $this->appendValue($bodyFields[$key] ?? null, self::bodyValue($valueData, $schema['items'] ?? null));
                    } else {
                        $bodyFields[$key] = self::bodyValue($valueData, $schema);
                    }
                } elseif ($param->isMulti()) {
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

        return [$postFields, $getFields, $cookieFields, $rawPost, $putFields, $pathFields, $headerFields, $bodyFields];
    }

    /**
     * A typed field that is left blank in the console is not sent. The server does not turn an empty string into
     * null or an empty list item, so a blank number, date, enum, nested object or list slot would only be a 400.
     * A blank optional text field is not sent either, so the server uses its default. A required text field keeps
     * its empty string, so that its length and pattern constraints can be tried.
     */
    private static function isBlankSlot(ParamInterface $param, mixed $value): bool
    {
        if (!$param instanceof MappedInputParam || $param->getType() === InputParam::TYPE_PATH || !is_string($value) || trim($value) !== '') {
            return false;
        }

        $schema = $param->getOpenApiSchema();
        $plainText = ($schema['type'] ?? null) === 'string' && !isset($schema['format']) && !isset($schema['enum']);

        return $param->getType() === InputParam::TYPE_HEADER || !$plainText || !$param->isRequired();
    }

    /**
     * The media type the typed body is sent in: the first one the handler reads that the console can build.
     * A handler that restricts itself to a form type with #[Consumes] must not receive JSON.
     */
    private function bodyMediaType(): string
    {
        foreach ($this->handler->params() as $param) {
            if (!$param instanceof InputParam) {
                continue;
            }
            foreach ($param->getContentTypes() as $contentType) {
                if (in_array($contentType, MediaTypes::ALL, true)) {
                    return $contentType;
                }
            }
        }

        return MediaTypes::JSON;
    }

    /**
     * Replaces {name} segments with URL-encoded values.
     *
     * @param array<string,string> $pathFields
     */
    private static function substitutePath(string $url, array $pathFields): string
    {
        foreach ($pathFields as $name => $value) {
            $url = str_replace('{' . $name . '}', rawurlencode($value), $url);
        }

        return $url;
    }

    /**
     * Console fields are strings. A JSON body needs the declared wire type.
     *
     * @param mixed $schema OpenAPI schema of the field
     */
    private static function bodyValue(mixed $value, mixed $schema): mixed
    {
        if (!is_string($value) || !is_array($schema)) {
            return $value;
        }

        $type = $schema['type'] ?? null;
        $trimmed = trim($value);
        return match (true) {
            $type === 'integer' && is_int(filter_var($trimmed, FILTER_VALIDATE_INT)) => (int) $trimmed,
            $type === 'number' && is_numeric($trimmed) && is_finite((float) $trimmed) => (float) $trimmed,
            $type === 'boolean' && in_array($trimmed, ['true', '1'], true) => true,
            $type === 'boolean' && in_array($trimmed, ['false', '0'], true) => false,
            // not an associative array: the JSON text {} is an empty object, which the server accepts, and [] is a list
            ($type === 'object' || $type === 'array') && json_validate($trimmed) => json_decode($trimmed),
            default => $value,
        };
    }

    /**
     * Nested body values as multipart keys, for example profile[name].
     *
     * @param array<array-key,mixed> $values
     * @return array<string,mixed>
     */
    private static function flatten(array $values, string $prefix = ''): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $name = $prefix === '' ? "{$key}" : "{$prefix}[{$key}]";
            if (is_object($value)) {
                $value = get_object_vars($value);
            }
            if (is_array($value)) {
                $result += self::flatten($value, $name);
            } elseif (is_bool($value)) {
                $result[$name] = $value ? '1' : '0';
            } elseif ($value !== null) {
                $result[$name] = $value;
            }
        }

        return $result;
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

            return self::isBlankSlot($param, $valueData) ? null : $valueData;
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
