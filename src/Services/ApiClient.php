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
        $options['headers'] = $this->getHeaders();
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
            Logger::log("JSON decode error on response from Cloudflare API request:" . $e->getMessage());
        } catch (\Exception $e) {
            Logger::log("General exception during Cloudflare API request:" . $e->getMessage());
        }
        return null;
    }

    protected function getPurgeResponse(string $zoneId, string $purgeType, array $values) : ApiResponse {
        $chunks = array_chunk($values, self::CHUNK_SIZE);
        $response = new ApiResponse();
        foreach($chunks as $chunk) {
            $body = [
                $purgeType => $chunk
            ];
            if($result = $this->callApi($zoneId, $body)) {
                $response->addResult($result);
            }
        }
        return $response;
    }

    public function purgeUrls(string $zoneId, array $urls) : ApiResponse {
        return $this->getPurgeResponse($zoneId, 'files', $urls);
    }

    public function purgePrefixes(string $zoneId, array $prefixes) : ApiResponse {
        return $this->getPurgeResponse($zoneId, 'prefixes', $prefixes);
    }

    public function purgeTags(string $zoneId, array $tags) : ApiResponse {
        return $this->getPurgeResponse($zoneId, 'tags', $tags);
    }

    public function purgeHosts(string $zoneId, array $hosts) : ApiResponse {
        return $this->getPurgeResponse($zoneId, 'hosts', $hosts);
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
