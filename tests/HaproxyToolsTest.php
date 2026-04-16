<?php

namespace Tests;

use OPNsense\InstanceManager;
use PHPUnit\Framework\TestCase;

class HaproxyToolsTest extends TestCase
{
    private function createManager(?callable $http = null): InstanceManager
    {
        return InstanceManager::fromFile(__DIR__ . '/fixtures/instances.json', $http);
    }

    public function testHaproxyServersList(): void
    {
        $fixture = json_encode(['rows' => [], 'total' => 0, 'rowCount' => 0, 'current' => 1]);
        $http = fn() => ['code' => 200, 'body' => $fixture];
        $tools = new \HaproxyTools($this->createManager($http));

        $result = $tools->haproxy_servers('list');

        $this->assertEquals('primary', $result['instance']);
        $this->assertIsArray($result['servers']);
    }

    public function testHaproxyServersPluginNotInstalled(): void
    {
        $http = fn() => ['code' => 400, 'body' => '{"errorMessage":"Endpoint not found"}'];
        $tools = new \HaproxyTools($this->createManager($http));

        $result = $tools->haproxy_servers('list');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('HAProxy plugin not available', $result['error']);
    }

    public function testHaproxyBackendsList(): void
    {
        $fixture = json_encode(['rows' => [
            ['uuid' => 'be1', 'name' => 'web-backend', 'mode' => 'http'],
        ], 'total' => 1, 'rowCount' => 1, 'current' => 1]);
        $http = fn() => ['code' => 200, 'body' => $fixture];
        $tools = new \HaproxyTools($this->createManager($http));

        $result = $tools->haproxy_backends('list');

        $this->assertCount(1, $result['backends']);
        $this->assertEquals('web-backend', $result['backends'][0]['name']);
    }

    public function testHaproxyServersGetRequiresUuid(): void
    {
        $tools = new \HaproxyTools($this->createManager());
        $result = $tools->haproxy_servers('get');
        $this->assertArrayHasKey('error', $result);
    }
}
