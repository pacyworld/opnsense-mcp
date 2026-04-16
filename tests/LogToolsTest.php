<?php

namespace Tests;

use OPNsense\InstanceManager;
use PHPUnit\Framework\TestCase;

class LogToolsTest extends TestCase
{
    private function createManager(?callable $http = null): InstanceManager
    {
        return InstanceManager::fromFile(__DIR__ . '/fixtures/instances.json', $http);
    }

    public function testFirewallLog(): void
    {
        $entries = [];
        for ($i = 0; $i < 100; $i++) {
            $entries[] = ['action' => 'block', 'src' => '10.0.0.' . $i, 'dst' => '192.168.1.1', 'interface' => 'wan'];
        }
        $http = fn() => ['code' => 200, 'body' => json_encode($entries)];
        $tools = new \LogTools($this->createManager($http));

        $result = $tools->firewall_log(10);

        $this->assertEquals('primary', $result['instance']);
        $this->assertEquals(10, $result['count']);
        $this->assertCount(10, $result['entries']);
    }

    public function testFirewallLogDefaultLimit(): void
    {
        $entries = array_fill(0, 100, ['action' => 'block']);
        $http = fn() => ['code' => 200, 'body' => json_encode($entries)];
        $tools = new \LogTools($this->createManager($http));

        $result = $tools->firewall_log();

        $this->assertEquals(50, $result['count']);
    }

    public function testFirewallLogMaxLimit(): void
    {
        $entries = array_fill(0, 1000, ['action' => 'pass']);
        $http = fn() => ['code' => 200, 'body' => json_encode($entries)];
        $tools = new \LogTools($this->createManager($http));

        // Requesting more than 1000 should cap at 1000
        $result = $tools->firewall_log(5000);

        $this->assertEquals(1000, $result['count']);
    }

    public function testSystemLog(): void
    {
        $entries = [
            ['message' => 'sshd: login accepted', '__timestamp__' => '2026-04-15T12:00:00'],
        ];
        $http = fn() => ['code' => 200, 'body' => json_encode($entries)];
        $tools = new \LogTools($this->createManager($http));

        $result = $tools->system_log(10);

        $this->assertEquals('primary', $result['instance']);
        $this->assertEquals(1, $result['count']);
    }

    public function testSystemLogNamedInstance(): void
    {
        $http = fn() => ['code' => 200, 'body' => '[]'];
        $tools = new \LogTools($this->createManager($http));

        $result = $tools->system_log(10, 'secondary');

        $this->assertEquals('secondary', $result['instance']);
    }
}
