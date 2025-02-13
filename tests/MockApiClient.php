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

    protected static $requestHistory = [];

    protected $mockError = false;

    protected function getApiUrl(string $zoneId) : string {
        $url = "http://localhost/client/v4/zones/{$zoneId}/purge_cache";
        return $url;
    }

    public function setIsMockError(bool $is) {
        $this->mockError = $is;
        return $this;
    }

    protected function logRequest(string $apiUrl, array $options, array $headers): void {
        $reason = $headers[static::HEADER_PURGE_REASON] ?? '';
        $body = json_encode($options['json'] ?? '');
        Logger::log("Calling API url='{$apiUrl}' body='{$body}' reason='{$reason}'", "INFO");
    }

    protected function callApi(string $zoneId, array $body, array $extraHeaders = []) : ?ApiResult {
        $headers = $this->getHeaders($extraHeaders);
        $result = $this->mockError ? $this->errorContents() : $this->successContents();
        $decoded = json_decode($result, false, 512, JSON_THROW_ON_ERROR);
        $apiUrl = $this->getApiUrl($zoneId);
        $options = $this->getOptions($headers, $body);
        static::$requestHistory[] = [
            'url' => $apiUrl,
            'options' => $options,
            'result' => $result,
            'decoded' => $decoded
        ];
        $this->logRequest($apiUrl, $options, $headers);
        return new ApiResult($decoded, $body);
    }

    public static function getRequestHistory(): array {
        return static::$requestHistory;
    }

    public static function clearRequestHistory(): void {
        static::$requestHistory = [];
    }

    public static function getRequestDataIndex(int $index) : ?array {
        return static::$requestHistory[$index] ?? null;
    }

    public static function getFirstRequestData(): ?array {
        return static::getRequestDataIndex(0);
    }

    public static function getLastRequestData(): ?array {
        $last = array_key_last(static::getRequestHistory());
        return !is_null($last) ? static::getRequestDataIndex($last) : null;
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
