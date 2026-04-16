<?php

namespace Tests;

use OPNsense\InstanceManager;
use PHPUnit\Framework\TestCase;

class ServiceToolsTest extends TestCase
{
    private function createManager(?callable $http = null): InstanceManager
    {
        return InstanceManager::fromFile(__DIR__ . '/fixtures/instances.json', $http);
    }

    public function testServiceList(): void
    {
        $fixture = json_encode([
            'total' => 3, 'rowCount' => 3, 'current' => 1,
            'rows' => [
                ['id' => 'unbound', 'name' => 'unbound', 'running' => 1, 'description' => 'Unbound DNS', 'locked' => 0],
                ['id' => 'ntpd', 'name' => 'ntpd', 'running' => 1, 'description' => 'Network Time Daemon', 'locked' => 0],
                ['id' => 'pf', 'name' => 'pf', 'running' => 1, 'description' => 'Packet Filter', 'locked' => 1],
            ],
        ]);
        $http = fn() => ['code' => 200, 'body' => $fixture];
        $tools = new \ServiceTools($this->createManager($http));

        $result = $tools->service_list();

        $this->assertEquals('primary', $result['instance']);
        $this->assertCount(3, $result['services']);
        $this->assertEquals(3, $result['total']);
        $this->assertEquals('unbound', $result['services'][0]['id']);
    }

    public function testServiceControlRestart(): void
    {
        $http = fn() => ['code' => 200, 'body' => '{"response":"OK"}'];
        $tools = new \ServiceTools($this->createManager($http));

        $result = $tools->service_control('unbound', 'restart');

        $this->assertEquals('primary', $result['instance']);
        $this->assertEquals('unbound', $result['service']);
        $this->assertEquals('restart', $result['action']);
    }

    public function testServiceControlRequiresServiceId(): void
    {
        $tools = new \ServiceTools($this->createManager());
        $result = $tools->service_control('');
        $this->assertArrayHasKey('error', $result);
    }

    public function testServiceControlInvalidAction(): void
    {
        $tools = new \ServiceTools($this->createManager());
        $result = $tools->service_control('unbound', 'invalid');
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Invalid action', $result['error']);
    }
}
