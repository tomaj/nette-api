<?php

namespace Tomaj\NetteApi\Misc;

class ConsoleResponse
{
    private $postFields;

    private $getFields;

    private $url;

    private $method;

    private $headers;

    private $responseCode;

    private $responseBody;

    private $responseTime;

    private $isError = false;

    private $errorNumber;

    private $errorMessage;

    public function __construct($url, $method, $postFields, $getFields, $headers)
    {
        $this->url = $url;
        $this->method = $method;
        $this->postFields = $postFields;
        $this->getFields = $getFields;
        $this->headers = $headers;
    }

    public function logRequest($responseCode, $responseBody, $responseTime)
    {
        $this->responseCode = $responseCode;
        $this->responseBody = $responseBody;
        $this->responseTime = $responseTime;
    }

    public function logError($errorNumber, $errorMessage, $responseTime)
    {
        $this->isError = true;
        $this->errorNumber = $errorNumber;
        $this->errorMessage = $errorMessage;
        $this->responseTime = $responseTime;
    }

    public function getPostFields()
    {
        return $this->postFields;
    }

    public function getGetFields()
    {
        return $this->getFields;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function isError()
    {
        return $this->isError;
    }

    /**
     * @return integer
     */
    public function getResponseCode()
    {
        return $this->responseCode;
    }

    /**
     * @return string
     */
    public function getResponseBody()
    {
        return $this->responseBody;
    }

    /**
     * @return string
     */
    public function getFormattedJsonBody()
    {
        $body = $this->responseBody;
        $decoded = json_decode($body);
        if ($decoded) {
            $body = json_encode($decoded, JSON_PRETTY_PRINT);
        }
        return $body;
    }

    /**
     * @return integer
     */
    public function getResponseTime()
    {
        return $this->responseTime;
    }

    /**
     * @return integer
     */
    public function getErrorNumber()
    {
        return $this->errorNumber;
    }

    /**
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }
}
