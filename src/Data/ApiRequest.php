<?php
namespace CarloNicora\Minimalism\ApiCaller\Data;

use CarloNicora\Minimalism\ApiCaller\Enums\Verbs;
use CURLFile;
use JsonException;

class ApiRequest
{
    public static array $responseHeaders = [];

    /**
     * Data constructor.
     * @param Verbs $verb
     * @param string $endpoint
     * @param array|null $body
     * @param array|null $payload
     * @param string|null $bearer
     * @param array $files
     * @param array $requestHeader
     */
    public function __construct(
        public Verbs $verb,
        public string $endpoint,
        public ?array $body = null,
        public ?array $payload = null,
        public ?string $bearer = null,
        public array $files = [],
        public array $requestHeader = []
    )
    {
        if (str_starts_with(haystack: $this->endpoint, needle: '/')){
            $this->endpoint = substr($this->endpoint, 1);
        }
    }

    /**
     * @param string $bearer
     */
    public function setBearer(
        string $bearer,
    ): void
    {
        $this->bearer = $bearer;
    }

    /**
     * @param bool $isTestEnvironment
     * @param string|null $hostName
     * @param array $customHeaders
     * @return array
     */
    protected function getCurlHttpHeaders(
        bool $isTestEnvironment=false,
        ?string $hostName=null,
        array $customHeaders = []
    ): array
    {
        $httpHeaders = [];

        if ($hostName === null && array_key_exists('MINIMALISM_SERVICE_TESTER_HOSTNAME', $_ENV)){
            $hostName = (string)$_ENV['MINIMALISM_SERVICE_TESTER_HOSTNAME'];
        }

        if ($hostName !== null) {
            $httpHeaders[] = 'Host:' . $hostName;

        }

        if ($isTestEnvironment){
            $httpHeaders[] = 'Test-Environment:1';
        }

        if (!empty($this->files)) {
            $httpHeaders[] = 'Content-Type:multipart/form-data';
        } else {
            $httpHeaders[] ='Content-Type:application/vnd.api+json';
        }

        if ($this->bearer !== null) {
            $httpHeaders[] = 'Authorization:Bearer ' . $this->bearer;
        }

        if (!empty($customHeaders)) {
            foreach ($customHeaders as $key => $value) {
                $httpHeaders[]= $key . ':' . $value;
            }
        }
        return array_merge($httpHeaders, $this->requestHeader);
    }

    /**
     * @param string $serverUrl
     * @param string|null $hostName
     * @param bool $isTestEnvironment
     * @param array $requestHeaders
     * @return array
     * @throws JsonException
     */
    public function getCurlOpts(
        string $serverUrl,
        ?string $hostName=null,
        bool $isTestEnvironment=false,
        array $requestHeaders = []
    ): array
    {
        /** @noinspection CurlSslServerSpoofingInspection */
        $opts = [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => $this->getCurlHttpHeaders(isTestEnvironment: $isTestEnvironment, hostName: $hostName, customHeaders: $requestHeaders),
            CURLOPT_HEADER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ];

        $endpointWithUriParams = null;

        switch ($this->verb){
            case Verbs::Post:
                $opts [CURLOPT_POST] = 1;
                $opts[CURLOPT_POSTFIELDS] = [];

                if (!empty($this->files)) {
                    $buildFiles = static function(
                        array $files,
                        bool $subLevel = false
                    ) use (&$buildFiles): array
                    {
                        $fileArray = [];
                        foreach ($files as $fileKey => $file) {
                            $multidimensionalKey = $subLevel ? '[' . $fileKey . ']' : $fileKey;
                            if (!empty($file['path'])) {
                                $cFile = new CURLFile(
                                    $file['path'],
                                    $file['mimeType'],
                                    $file['name']
                                );

                                $fileArray [$multidimensionalKey] = $cFile;
                            } elseif (!empty($file['tmp_name'])){
                                $cFile = new CURLFile(
                                    $file['tmp_name'],
                                    $file['type'],
                                    $file['name']
                                );

                                $fileArray [$multidimensionalKey] = $cFile;
                            } else {
                                foreach ($buildFiles($file, true) as $subFileKey => $subFile) {
                                    $fileArray [$multidimensionalKey . $subFileKey ] = $subFile;
                                }
                            }
                        }

                        return $fileArray;
                    };

                    $opts[CURLOPT_POSTFIELDS] = $buildFiles($this->files);
                }

                if ($this->body !== null){
                    if ($opts[CURLOPT_POSTFIELDS] === []){
                        $opts[CURLOPT_POSTFIELDS] = http_build_query($this->body) ;
                    } else {
                        $opts[CURLOPT_POSTFIELDS] = array_merge($opts[CURLOPT_POSTFIELDS], $this->body);
                    }
                }

                if ($this->payload !== null){
                    if ($opts[CURLOPT_POSTFIELDS] === []){
                        $opts[CURLOPT_POSTFIELDS] = json_encode($this->payload, JSON_THROW_ON_ERROR);
                    } else {
                        $opts[CURLOPT_POSTFIELDS]['payload'] = json_encode($this->payload, JSON_THROW_ON_ERROR);
                    }
                }

                break;
            case Verbs::Delete:
            case Verbs::Patch:
            case Verbs::Put:
                $opts [CURLOPT_CUSTOMREQUEST] = $this->verb->value;

                if ($this->body !== null){
                    $opts[CURLOPT_POSTFIELDS] = http_build_query($this->body) ;
                } elseif ($this->payload !== null){
                    $opts[CURLOPT_POSTFIELDS] = json_encode($this->payload, JSON_THROW_ON_ERROR);
                }

                break;
            default:
                if (isset($this->body)) {
                    $query = http_build_query($this->body);
                    if (!empty($query)) {
                        $endpointWithUriParams .= ((str_contains($this->endpoint, '?')) ? '&' : '?') . $query;
                    }
                }
                break;
        }

        $opts[CURLOPT_URL] = $serverUrl . ($endpointWithUriParams ?? $this->endpoint);

        $opts[CURLOPT_HEADERFUNCTION] = static function($stub, $header)
        {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) < 2) // ignore invalid headers
            {
                return $len;
            }

            static::$responseHeaders[strtolower(trim($header[0]))][] = trim($header[1]);

            return $len;
        };

        return $opts;
    }
}