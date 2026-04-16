<?php
/**
 * Tests for OPNsense\Client — HTTP client with mocked responses.
 *
 * @package    OPNsenseMCP\Tests
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace Tests;

use OPNsense\Client;
use OPNsense\ClientException;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    /**
     * Create a mock HTTP callable that records requests and returns a fixed response.
     *
     * @param  int    $code Response HTTP status code
     * @param  string $body Response body
     * @return array{0:callable,1:\ArrayObject} [callable, captured_requests]
     */
    private function mockHttp(int $code = 200, string $body = '{}'): array
    {
        $requests = new \ArrayObject();
        $responseBody = $body;
        $callable = function (string $method, string $url, array $headers, ?string $reqBody) use ($code, $responseBody, $requests) {
            $requests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $reqBody,
            ];
            return ['code' => $code, 'body' => $responseBody];
        };

        return [$callable, $requests];
    }

    public function testGetConstructsCorrectUrl(): void
    {
        [$http, $requests] = $this->mockHttp(200, '{"status":"ok"}');
        $client = new Client('https://10.0.0.1', 'key', 'secret', false, 30, $http);

        $client->get('core/firmware/status');

        $this->assertCount(1, $requests);
        $this->assertEquals('GET', $requests[0]['method']);
        $this->assertEquals('https://10.0.0.1/api/core/firmware/status', $requests[0]['url']);
    }

    public function testGetTrimsTrailingSlash(): void
    {
        [$http, $requests] = $this->mockHttp(200, '{"ok":true}');
        $client = new Client('https://10.0.0.1/', 'key', 'secret', false, 30, $http);

        $client->get('core/system/status');

        $this->assertEquals('https://10.0.0.1/api/core/system/status', $requests[0]['url']);
    }

    public function testPostSendsJsonBody(): void
    {
        [$http, $requests] = $this->mockHttp(200, '{"result":"saved"}');
        $client = new Client('https://10.0.0.1', 'key', 'secret', false, 30, $http);

        $client->post('firewall/filter/addRule', ['action' => 'pass', 'interface' => 'lan']);

        $this->assertEquals('POST', $requests[0]['method']);
        $this->assertEquals('https://10.0.0.1/api/firewall/filter/addRule', $requests[0]['url']);

        $sentBody = json_decode($requests[0]['body'], true);
        $this->assertEquals('pass', $sentBody['action']);
        $this->assertEquals('lan', $sentBody['interface']);
    }

    public function testPostWithEmptyData(): void
    {
        [$http, $requests] = $this->mockHttp(200, '{"status":"ok"}');
        $client = new Client('https://10.0.0.1', 'key', 'secret', false, 30, $http);

        $client->post('firewall/filter/apply');

        $this->assertEquals('POST', $requests[0]['method']);
        $this->assertEquals('[]', $requests[0]['body']);
    }

    public function testGetReturnsDecodedJson(): void
    {
        $fixture = file_get_contents(__DIR__ . '/fixtures/system-status.json');
        [$http] = $this->mockHttp(200, $fixture);
        $client = new Client('https://10.0.0.1', 'key', 'secret', false, 30, $http);

        $result = $client->get('core/firmware/status');

        $this->assertEquals('OPNsense', $result['product']['CORE_PRODUCT']);
        $this->assertEquals('26.1.2', $result['product']['CORE_VERSION']);
    }

    public function testThrowsOn401(): void
    {
        [$http] = $this->mockHttp(401, 'Unauthorized');
        $client = new Client('https://10.0.0.1', 'bad_key', 'bad_secret', false, 30, $http);

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('Authentication failed');
        $client->get('core/firmware/status');
    }

    public function testThrowsOn403(): void
    {
        [$http] = $this->mockHttp(403, 'Forbidden');
        $client = new Client('https://10.0.0.1', 'key', 'secret', false, 30, $http);

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage('Access denied');
        $client->get('core/firmware/status');
    }

    public function testThrowsOn500(): void
    {
        [$http] = $this->mockHttp(500, 'Internal Server Error');
        $client = new Client('https://10.0.0.1', 'key', 'secret', false, 30, $http);

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage('Server error');
        $client->get('core/firmware/status');
    }

    public function testThrowsOnInvalidJson(): void
    {
        [$http] = $this->mockHttp(200, 'not json {{{');
        $client = new Client('https://10.0.0.1', 'key', 'secret', false, 30, $http);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid JSON');
        $client->get('core/firmware/status');
    }

    public function testGetBaseUrl(): void
    {
        [$http] = $this->mockHttp();
        $client = new Client('https://192.168.1.100', 'key', 'secret', false, 30, $http);

        $this->assertEquals('https://192.168.1.100', $client->getBaseUrl());
    }

    public function testGetBaseUrlTrimsTrailingSlash(): void
    {
        [$http] = $this->mockHttp();
        $client = new Client('https://192.168.1.100/', 'key', 'secret', false, 30, $http);

        $this->assertEquals('https://192.168.1.100', $client->getBaseUrl());
    }

    public function testContentTypeHeaders(): void
    {
        [$http, $requests] = $this->mockHttp(200, '{}');
        $client = new Client('https://10.0.0.1', 'key', 'secret', false, 30, $http);

        $client->get('core/firmware/status');

        $this->assertContains('Accept: application/json', $requests[0]['headers']);
        // GET requests should NOT include Content-Type (OPNsense rejects it)
        $this->assertNotContains('Content-Type: application/json', $requests[0]['headers']);
    }

    public function testEmptyJsonResponseReturnsEmptyArray(): void
    {
        [$http] = $this->mockHttp(200, '{}');
        $client = new Client('https://10.0.0.1', 'key', 'secret', false, 30, $http);

        $result = $client->get('some/endpoint');
        $this->assertEquals([], $result);
    }
}
