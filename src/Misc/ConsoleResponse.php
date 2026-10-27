<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

final class ConsoleResponse
{
    private ?int $responseCode = null;

    private ?string $responseBody = null;

    private ?string $responseHeaders = null;

    private ?int $responseTime = null;

    private bool $isError = false;

    private ?int $errorNumber = null;

    private ?string $errorMessage = null;

    /**
     * @param array<string,mixed> $postFields
     * @param array<string,mixed> $getFields
     * @param array<string,mixed> $cookieFields
     * @param array<array-key,string> $headers
     */
    public function __construct(private readonly string $url, private readonly string $method, private readonly array $postFields = [], private readonly array $getFields = [], private readonly array $cookieFields = [], private readonly array $headers = [], private readonly ?string $rawPost = null)
    {
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
     * @return array<array-key,string>
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
