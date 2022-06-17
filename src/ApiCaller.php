<?php
namespace CarloNicora\Minimalism\ApiCaller;

use CarloNicora\Minimalism\Abstracts\AbstractService;
use CarloNicora\Minimalism\ApiCaller\Data\ApiResponse;
use CarloNicora\Minimalism\ApiCaller\Data\ApiRequest;
use Exception;

class ApiCaller extends AbstractService
{
    /**
     * @param ApiRequest $request
     * @param string $serverUrl
     * @param string|null $hostName
     * @return ApiResponse
     * @throws Exception
     */
    public function call(
        ApiRequest $request,
        string $serverUrl,
        ?string $hostName=null,
    ): ApiResponse
    {
        if (!str_ends_with(haystack: $serverUrl, needle: '/')){
            $serverUrl .= '/';
        }

        $curl = curl_init();

        $options = $request->getCurlOpts(
            serverUrl: $serverUrl,
            hostName: $hostName,
            requestHeaders: $request->requestHeader
        );

        curl_setopt_array($curl, $options);

        $curlResponse = curl_exec($curl);

        $result = new ApiResponse($curl, $curlResponse, $request::$responseHeaders);

        if (isset($curl)) {
            curl_close($curl);
        }

        unset($curl);

        return $result;
    }
}