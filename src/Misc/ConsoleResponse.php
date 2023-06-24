<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

class ConsoleResponse
{
    private $postFields;

    private $rawPost;

    private $getFields;

    private $cookieFields;

    private $url;

    private $method;

    private $headers;

    private $responseCode;

    private $responseBody;

    private $responseHeaders;

    private $responseTime;

    private $isError = false;

    private $errorNumber;

    private $errorMessage;

    public function __construct(string $url, string $method, array $postFields = [], array $getFields = [], array $cookieFields = [], array $headers = [], ?string $rawPost = null)
    {
        $this->url = $url;
        $this->method = $method;
        $this->postFields = $postFields;
        $this->getFields = $getFields;
        $this->cookieFields = $cookieFields;
        $this->headers = $headers;
        $this->rawPost = $rawPost;
    }

    public function logRequest(int $responseCode, string $responseBody, string $responseHeaders, int $responseTime): void
    {
        $this->responseCode = $responseCode;
        $this->responseBody = $responseBody;
        $this->responseHeaders = $responseHeaders;
        $this->responseTime = $responseTime;
    }

    public function logError(int $errorNumber, string $errorMessage, int $responseTime): void
    {
        $this->isError = true;
        $this->errorNumber = $errorNumber;
        $this->errorMessage = $errorMessage;
        $this->responseTime = $responseTime;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPostFields(): array
    {
        return $this->postFields;
    }

    public function getRawPost(): ?string
    {
        return $this->rawPost;
    }

    public function getGetFields(): array
    {
        return $this->getFields;
    }

    public function getCookieFields(): array
    {
        return $this->cookieFields;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    public function getResponseCode(): ?int
    {
        return $this->responseCode;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }

    public function getFormattedJsonBody(): string
    {
        $body = $this->responseBody;
        if ($body === null) {
            return '';
        }
        $decoded = json_decode($body);
        if ($decoded) {
            $body = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return $body;
    }

    public function getResponseHeaders(): ?string
    {
        return $this->responseHeaders;
    }

    public function getResponseTime(): int
    {
        return $this->responseTime;
    }

    public function getErrorNumber(): int
    {
        return $this->errorNumber;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }
}
