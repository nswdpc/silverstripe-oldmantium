<?php

namespace NSWDPC\Utilities\Cloudflare;

use GuzzleHttp\ClientInterface;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse;

class ApiClient {

    public const HEADER_PURGE_REASON = 'X-Purge-Reason';

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

    /**
     * Get options for request.
     * See https://docs.guzzlephp.org/en/latest/request-options.html#json for json key usage
     */
    protected function getOptions(array $headers, array $body) : array {
        $options = [];
        $options['headers'] = $headers;
        $options['json'] = $body;
        return $options;
    }

    protected function callApi(string $zoneId, array $body, array $extraHeaders = []) : ApiResult {
        try {
            // Perform the API request and return a result as an ApiResult
            $headers = $this->getHeaders($extraHeaders);
            $response = $this->client->request('POST', $this->getApiUrl($zoneId), $this->getOptions($headers, $body));
            $result = $response->getBody()->getContents();
            $decoded = json_decode($result, false, 512, JSON_THROW_ON_ERROR);
            $result = new ApiResult($decoded, $body);
        } catch (\JsonException $jsonException) {
            // Got a result, but could not decoded it
            Logger::log("Cloudflare API response JSON handling error. " . $jsonException->getMessage(), "NOTICE");
            $result = new ApiResult();
            $result->setException($jsonException);
        } catch (\GuzzleHttp\Exception\RequestException $requestException) {
            // when a RequestException occurs, the response could be available
            // it may contain response information
            Logger::log("Cloudflare API client request error. " . $requestException->getMessage(), "NOTICE");
            try {
                $decoded = null;
                if($response = $requestException->getResponse()) {
                    $result = $response->getBody()->getContents();
                    $decoded = json_decode($result, false, 512, JSON_THROW_ON_ERROR);
                }
            } catch (\Exception $e) {}
            // store the result
            $result = new ApiResult($decoded);
            $result->setException($requestException);
        } catch (\Exception $exception) {
            // General exception
            Logger::log("Cloudflare API client general error. " . $exception->getMessage(), "NOTICE");
            $result = new ApiResult();
            $result->setException($exception);
        }
        return $result;
    }

    /**
     * Get the response data as an ApiResponse object, which may contain multiple ApiResult objects
     */
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
        $this->logResults($response);
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

    /**
     * Log results, in the current case by adding response headers that can be inspected
     * when manually publishing/saving/deleting records in the administration area
     * API response exception errors are logged regardless
     * @param ApiResponse $apiResponse the API response data, see getPurgeResponse
     */
    protected function logResults(ApiResponse $apiResponse): ?bool {
        try {
            if(!Controller::has_curr()) {
                return null;
            }
            $controller = Controller::curr();
            if(!($controller instanceof LeftAndMain)) {
                return null;
            }
            $this->logResult($controller->getResponse(), $apiResponse);
            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Log a result to response headers based on an ApiResponse
     * @param HTTPResponse $response the response object for the current request (e.g a publish attempt on a website)
     * @param ApiResponse $apiResponse the API response data, see getPurgeResponse
     */
    protected function logResult(HTTPResponse $response, ApiResponse $apiResponse): void {
        $successes = $apiResponse->getSuccesses();
        $errors = $apiResponse->getErrors();
        $exceptions = $apiResponse->getExceptions();
        if($exceptions !== []) {
            // no response from API
            $exceptionHeader = [];
            array_walk(
                $exceptions,
                function($exception, $key) use (&$exceptionHeader) {
                    $exceptionHeader[] = "(" . $exception->getCode() . ") " . get_class($exception);
                }
            );
            $response->addHeader('X-CF-API-Exceptions', $this->sanitiseHeaderValue(implode(", ", $exceptionHeader)));
        }
        if($successes != []) {
            // has some success, the values are the ids from the response
            $response->addHeader('X-CF-API-Success', $this->sanitiseHeaderValue(implode(", ", $successes)));
        }
        if($errors != []) {
            // has some error
            $errorHeader = [];
            array_walk(
                $errors,
                function($error, $key) use (&$errorHeader) {
                    $errorHeader[] = "(" . $error->code . ") " . $error->message;
                }
            );
            $response->addHeader('X-CF-API-Error', $this->sanitiseHeaderValue(implode(", ", $errorHeader)));
        }
    }

    /**
     * Remove all non-ASCII chrs from the header value
     */
    private function sanitiseHeaderValue(string $value): string {
        return preg_replace("/[[:^ascii:]]+/", " ", $value);
    }

}
