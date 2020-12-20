<?php
namespace NSWDPC\Utilities\Cloudflare\Tests;

use Cloudflare\API\Adapter\Guzzle;
use Cloudflare\API\Adapter\JSONException;
use Cloudflare\API\Adapter\ResponseException;
use Cloudflare\API\Auth\Auth;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use SilverStripe\Core\Injector\Injector;
use Symbiote\Cloudflare\Cloudflare;

class MockCloudflareAdapter extends Guzzle {

    protected $mock_client;
    protected $uri;
    protected $base_uri;
    protected $data;
    protected $headers;
    protected $client_headers;

    const LOCALHOST_URI = 'http://localhost';

    /**
     * @inheritDoc
     */
    public function __construct(Auth $auth, string $baseURI = null)
    {
        $this->client_headers = $auth->getHeaders();
        $this->mock_client = new Client([
            'base_uri' => $this->getEndpointUri(),
            'headers' => $this->client_headers,
            'Accept' => 'application/json'
        ]);
    }

    protected function getEndpointUri() {
        return self::LOCALHOST_URI;
    }

    public function request(string $method, string $uri, array $data = [], array $headers = [])
    {

        if (!in_array($method, ['get', 'post', 'put', 'patch', 'delete'])) {
            throw new \InvalidArgumentException('Request method must be get, post, put, patch, or delete');
        }

        $this->uri = $uri;
        $this->data = $data;
        $this->headers = $headers;


        $status = 200;
        $headers = $headers;
        $body = $this->getBodyByUri($uri);
        $protocol = '1.1';
        $response = new Response($status, $headers, $body, $protocol);

        $this->mockCheckError($response);

        return $response;
    }

    /**
     * checkError in the Cloudflare Adapter is private
     */
    protected function mockCheckError(ResponseInterface $response)
    {
        $json = json_decode($response->getBody());

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new JSONException();
        }

        if (isset($json->errors) && count($json->errors) >= 1) {
            throw new ResponseException($json->errors[0]->message, $json->errors[0]->code);
        }

        if (isset($json->success) && !$json->success) {
            throw new ResponseException('Request was unsuccessful.');
        }
    }

    /**
     * Set a mock body for the response
     * @todo modify body based on $uri
     */
    public function getBodyByUri($uri) : string {
        $client = Injector::inst()->get( Cloudflare::class );
        $zone_id = $client->getZoneIdentifier();

        $body = [
            'success' => true,
            'errors' => [],
            'messages' => [],
            'result' => [
                'id' => $zone_id
            ]
        ];
        return json_encode($body);
    }

    public function getLastUri() {
        return $this->uri;
    }

    public function getData() {
        return $this->data;
    }

    public function getHeaders() {
        return $this->headers;
    }

    public function getClientHeaders() {
        return $this->client_headers;
    }

    public function getClient() {
        return $this->mock_client;
    }

}
