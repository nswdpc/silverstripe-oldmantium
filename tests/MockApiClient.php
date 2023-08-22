<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\ApiResult;
use NSWDPC\Utilities\Cloudflare\ApiClient;
use NSWDPC\Utilities\Cloudflare\Logger;

/**
 * Mock adapter to test requests and responses
 * @author James
 */
class MockApiClient extends ApiClient {

    protected $mockRequestData = [];

    protected $mockError = false;

    protected function getApiUrl(string $zoneId) : string {
        $url = "http://localhost/client/v4/zones/{$zoneId}/purge_cache";
        return $url;
    }

    public function setIsMockError(bool $is) {
        $this->mockError = $is;
        return $this;
    }

    protected function callApi(string $zoneId, array $body, array $extraHeaders = []) : ?ApiResult {
        $headers = $this->getHeaders($extraHeaders);
        $result = $this->mockError ? $this->errorContents() : $this->successContents();
        $decoded = json_decode($result, false, 512, JSON_THROW_ON_ERROR);
        $this->mockRequestData = [
            'url' => $this->getApiUrl($zoneId),
            'options' => $this->getOptions($headers, $body),
            'result' => $result,
            'decoded' => $decoded
        ];
        return new ApiResult($decoded, $body);
    }

    public function getMockRequestData() : array {
        return $this->mockRequestData;
    }

    protected function errorContents() : string {
        $response = <<<JSON
{
  "errors": [
    {
      "code": 7003,
      "message": "No route for the URI"
    }
  ],
  "messages": [],
  "result": null,
  "success": false
}
JSON;
        return $response;
    }

    protected function successContents() : string {
        $response = <<<JSON
{
  "errors": [],
  "messages": [],
  "result": {
    "id": "023e105f4ecef8ad9ca31a8372d0c353"
  },
  "success": true
}
JSON;
        return $response;
    }

}
