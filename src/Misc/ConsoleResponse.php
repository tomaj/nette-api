<?php

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

    /**
     * ConsoleResponse constructor.
     *
     * @param string  $url
     * @param string  $method
     * @param array   $postFields
     * @param array   $getFields
     * @param array   $cookieFields
     * @param array   $headers
     * @param string|boolean $rawPost
     */
    public function __construct($url, $method, array $postFields = [], array $getFields = [], $cookieFields = [], array $headers = [], $rawPost = false)
    {
        $this->url = $url;
        $this->method = $method;
        $this->postFields = $postFields;
        $this->getFields = $getFields;
        $this->cookieFields = $cookieFields;
        $this->headers = $headers;
        $this->rawPost = $rawPost;
    }

    /**
     * Log response from request
     *
     * @param $responseCode
     * @param $responseBody
     * @param $responseHeaders
     * @param $responseTime
     *
     * @return voiud
     */
    public function logRequest($responseCode, $responseBody, $responseHeaders, $responseTime)
    {
        $this->responseCode = $responseCode;
        $this->responseBody = $responseBody;
        $this->responseHeaders = $responseHeaders;
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

    public function getRawPost()
    {
        return $this->rawPost;
    }

    public function getGetFields()
    {
        return $this->getFields;
    }

    public function getCookieFields()
    {
        return $this->cookieFields;
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
            $body = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return $body;
    }

    public function getResponseHeaders()
    {
        return $this->responseHeaders;
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
