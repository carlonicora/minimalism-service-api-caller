<?php
namespace CarloNicora\Minimalism\ApiCaller\Data;

use CarloNicora\JsonApi\Document;
use CarloNicora\JsonApi\Objects\ResourceObject;
use CarloNicora\Minimalism\Enums\HttpCode;
use CurlHandle;
use Exception;
use RuntimeException;
use Throwable;

class ApiResponse
{
    /** @var HttpCode  */
    protected HttpCode $responseHttpCode;

    /** @var string  */
    protected string $rawResponse;

    /** @var Document  */
    protected Document $response;

    /** @var string  */
    protected string $error = '';

    /**
     * ApiResponse constructor.
     * @param false|CurlHandle|resource $curl
     * @param string|bool $curlResponse
     * @param array $responseHttpHeaders
     * @throws Exception
     */
    public function __construct(
        false|CurlHandle $curl,
        string|bool      $curlResponse,
        protected array  $responseHttpHeaders,
    )
    {
        if (curl_error($curl)) {
            $this->responseHttpCode = HttpCode::ImATeapot;
            $this->error = 'Curl Error: ' . curl_error($curl);
            return;
        }

        $this->responseHttpCode = HttpCode::from(curl_getinfo($curl, option: CURLINFO_RESPONSE_CODE));

        $this->rawResponse = substr($curlResponse, curl_getinfo($curl, option: CURLINFO_HEADER_SIZE));

        if ($this->responseHttpCode->value >= 400) {
            try {
                $apiResponse = json_decode($this->rawResponse, true, 512, JSON_THROW_ON_ERROR);
                $this->response = new Document($apiResponse);

                $this->error = $this->response->errors[0]->getTitle();
            } catch (Exception) {
                $this->error = 'API returned error: ' . $this->rawResponse;
            }
        }

        if (!empty($this->rawResponse)) {
            try {
                $apiResponse = json_decode($this->rawResponse, true, 512, JSON_THROW_ON_ERROR);
                $this->response = new Document($apiResponse);
            } catch (Exception| Throwable) {
            }
        }
    }

    /**
     * @return string
     */
    public function getRawResponse(
    ): string
    {
        return $this->rawResponse;
    }

    /**
     * @return HttpCode
     */
    public function getHttpCode(
    ): HttpCode
    {
        return $this->responseHttpCode;
    }

    /**
     * @return array
     */
    public function getHttpHeaders(
    ): array
    {
        return $this->responseHttpHeaders;
    }

    /**
     * @return ResourceObject
     */
    public function getFirstResource(
    ): ResourceObject
    {
        return $this->response->resources[0] ?? throw new RuntimeException(empty($this->rawResponse) ? 'Response is empty' : $this->rawResponse, HttpCode::TemporaryRedirect->value);
    }

    /**
     * @return ResourceObject[]
     */
    public function getResources(
    ): array
    {
        return $this->response->resources ?? throw new RuntimeException($this->rawResponse, HttpCode::TemporaryRedirect->value);
    }

    /**
     * @return int
     */
    public function getResourceCount(
    ): int
    {
        return count($this->response->resources);
    }

    /**
     * @return Document
     */
    public function getResponse(
    ): Document
    {
        return $this->response ?? throw new RuntimeException($this->rawResponse, HttpCode::TemporaryRedirect->value);
    }

    /**
     * @return string
     */
    public function getError(
    ): string
    {
        return $this->error;
    }
}