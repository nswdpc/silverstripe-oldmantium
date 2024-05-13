<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\ApiClient;

require_once(dirname(__FILE__) . '/CloudflarePurgeTestAbstract.php');

/**
 * Test API client
 * @author James
 */
class ApiClientTest extends CloudflarePurgeTestAbstract
{

    protected $usesDatabase = false;

    /**
     * Validate successes are captured
     */
    public function testRequestSuccess() {
        $apiClient = $this->client->getAdapter();
        $urls = [
            'https://example.com/foo',
            'https://example.com/bar'
        ];
        $apiClient->setIsMockError(false);
        $response = $apiClient->purgeUrls('test-zone-id', $urls);
        $this->assertTrue( $response->allSuccess() );
        $this->assertFalse( $response->hasErrors() );
    }

    /**
     * Validate successes are captured
     */
    public function testRequestExtraHeaders() {
        $apiClient = $this->client->getAdapter();
        $urls = [
            'https://example.com/foo',
            'https://example.com/bar'
        ];

        $headers = [
            'CF-Device-Type' => 'mobile',
            'CF-IPCountry' => 'AU'

        ];
        $apiClient->setIsMockError(false);
        $response = $apiClient->purgeUrls('test-zone-id', $urls, $headers);
        $this->assertTrue( $response->allSuccess() );
        $this->assertFalse( $response->hasErrors() );
        $data = $apiClient->getMockRequestData();
        $this->assertArrayHasKey('CF-Device-Type', $data['options']['headers']);
        $this->assertArrayHasKey('CF-IPCountry', $data['options']['headers']);
        $this->assertEquals('mobile', $data['options']['headers']['CF-Device-Type']);
        $this->assertEquals('AU', $data['options']['headers']['CF-IPCountry']);
    }

    /**
     * Validate successes are captured
     */
    public function testRequestWithMultipleChunks() {
        $apiClient = $this->client->getAdapter();
        $max = 80;
        $final = 80 % ApiClient::CHUNK_SIZE;
        $urls= [];
        $chunks = ceil($max / ApiClient::CHUNK_SIZE);
        for($i=0;$i<80;$i++) {
            $urls[] = 'https://example.com/foo' . $i;
        }
        $apiClient->setIsMockError(false);
        $response = $apiClient->purgeUrls('test-zone-id', $urls);
        $this->assertTrue( $response->allSuccess() );
        $this->assertFalse( $response->hasErrors() );
        $this->assertEquals($chunks, $response->getResultCount());

        $results = $response->getResults();
        $counts = [];
        foreach($results as $result) {
            $counts[] = count($result->getBody()['files']);
        }
        sort($counts);
        $expected = [ $final, ApiClient::CHUNK_SIZE , ApiClient::CHUNK_SIZE ];

        $this->assertEquals($expected, $counts);
    }

    /**
     * Validate errors are collected
     */
    public function testRequestErrors() {
        $apiClient = $this->client->getAdapter();
        $urls = [
            'https://example.com/foo',
            'https://example.com/bar'
        ];
        $apiClient->setIsMockError(true);
        $response = $apiClient->purgeUrls('test-zone-id', $urls);
        $this->assertFalse( $response->allSuccess() );
        $this->assertTrue( $response->hasErrors() );
        $errors = $response->getErrors();
    }

}
