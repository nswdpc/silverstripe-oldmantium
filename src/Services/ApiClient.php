<?php

namespace NSWDPC\Utilities\Cloudflare;

use GuzzleHttp\ClientInterface;

class ApiClient {

    /**
     * @var string
     */
    protected $apiToken = '';

    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var int
     */
    const CHUNK_SIZE = 30;

    public function __construct(ClientInterface $client, string $apiToken) {
        $this->apiToken = $apiToken;
        $this->client = $client;
    }

    protected function getApiUrl(string $zoneId) : string {
        $url = "https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache";
        return $url;
    }

    protected function getHeaders(array $extraHeaders = []) : array {
        $headers = [
            "Authorization" => "Bearer {$this->apiToken}",
            "Content-Type" => "application/json"
        ];
        if(count($extraHeaders) > 0) {
            $headers = array_merge(
                $extraHeaders,
                $headers
            );
        }
        return $headers;
    }

    protected function getOptions(array $headers, array $body) : array {
        $options = [];
        $options['headers'] = $headers;
        $options['json'] = $body;
        return $options;
    }

    protected function callApi(string $zoneId, array $body, array $extraHeaders = []) : ?ApiResult {
        try {
            $headers = $this->getHeaders($extraHeaders);
            $response = $this->client->request('POST', $this->getApiUrl($zoneId), $this->getOptions($headers, $body));
            $result = $response->getBody()->getContents();
            $decoded = json_decode($result, false, 512, JSON_THROW_ON_ERROR);
            return new ApiResult($decoded, $body);
        } catch (\JsonException $e) {
            Logger::log("JSON decode error on response from Cloudflare API request:" . $e->getMessage(), "WARNING");
        } catch (\Exception $e) {
            Logger::log("Cloudflare API error. " . $e->getMessage(), "WARNING");
        }
        return null;
    }

    protected function getPurgeResponse(string $zoneId, string $purgeType, array $values, array $extraHeaders = []) : ApiResponse {
        $chunks = array_chunk($values, self::CHUNK_SIZE);
        $response = new ApiResponse();
        foreach($chunks as $chunk) {
            $body = [
                $purgeType => $chunk
            ];
            if($result = $this->callApi($zoneId, $body, $extraHeaders)) {
                $response->addResult($result);
            }
        }
        return $response;
    }

    public function purgeUrls(string $zoneId, array $urls, array $extraHeaders = []) : ApiResponse {
        return $this->getPurgeResponse($zoneId, 'files', $urls, $extraHeaders);
    }

    public function purgePrefixes(string $zoneId, array $prefixes, array $extraHeaders = []) : ApiResponse {
        return $this->getPurgeResponse($zoneId, 'prefixes', $prefixes, $extraHeaders);
    }

    public function purgeTags(string $zoneId, array $tags, array $extraHeaders = []) : ApiResponse {
        return $this->getPurgeResponse($zoneId, 'tags', $tags, $extraHeaders);
    }

    public function purgeHosts(string $zoneId, array $hosts, array $extraHeaders = []) : ApiResponse {
        return $this->getPurgeResponse($zoneId, 'hosts', $hosts, $extraHeaders);
    }

    public function purgeEverything(string $zoneId) : ApiResponse {
        $body = [
            'purge_everything' => true
        ];
        $response = new ApiResponse();
        if($result = $this->callApi($zoneId, $body)) {
            $response->addResult($result);
        }
        return $response;
    }

}
