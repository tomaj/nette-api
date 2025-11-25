<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

class ConsoleResponse
{
    /** @var array<string,mixed> */
    private array $postFields;

    private ?string $rawPost;

    /** @var array<string,mixed> */
    private array $getFields;

    /** @var array<string,mixed> */
    private array $cookieFields;

    private string $url;

    private string $method;

    /** @var array<string,mixed> */
    private array $headers;

    private ?int $responseCode = null;

    private ?string $responseBody = null;

    private ?string $responseHeaders = null;

    private ?int $responseTime = null;

    private bool $isError = false;

    private ?int $errorNumber = null;

    private ?string $errorMessage = null;

    /**
     * @param string $url
     * @param string $method
     * @param array<string,mixed> $postFields
     * @param array<string,mixed> $getFields
     * @param array<string,mixed> $cookieFields
     * @param array<string,mixed> $headers
     * @param string|null $rawPost
     */
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

    /**
     * @return array<string,mixed>
     */
    public function getPostFields(): array
    {
        return $this->postFields;
    }

    public function getRawPost(): ?string
    {
        return $this->rawPost;
    }

    /**
     * @return array<string,mixed>
     */
    public function getGetFields(): array
    {
        return $this->getFields;
    }

    /**
     * @return array<string,mixed>
     */
    public function getCookieFields(): array
    {
        return $this->cookieFields;
    }

    /**
     * @return array<string,mixed>
     */
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

    public function getResponseBody(): ?string
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
        return $body ?: '';
    }

    public function getResponseHeaders(): ?string
    {
        return $this->responseHeaders;
    }

    public function getResponseTime(): ?int
    {
        return $this->responseTime;
    }

    public function getErrorNumber(): ?int
    {
        return $this->errorNumber;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
