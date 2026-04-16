<?php

namespace Tests;

use OPNsense\InstanceManager;
use PHPUnit\Framework\TestCase;

class DnsToolsTest extends TestCase
{
    private function createManager(?callable $http = null): InstanceManager
    {
        return InstanceManager::fromFile(__DIR__ . '/fixtures/instances.json', $http);
    }

    public function testHostOverridesList(): void
    {
        $fixture = json_encode([
            'rows' => [
                ['uuid' => 'abc123', 'hostname' => 'nas', 'domain' => 'local', 'server' => '192.168.1.10'],
            ],
            'total' => 1,
            'rowCount' => 1,
            'current' => 1,
        ]);
        $http = fn() => ['code' => 200, 'body' => $fixture];

        $tools = new \DnsTools($this->createManager($http));
        $result = $tools->dns_host_overrides('list');

        $this->assertEquals('primary', $result['instance']);
        $this->assertCount(1, $result['overrides']);
        $this->assertEquals('nas', $result['overrides'][0]['hostname']);
    }

    public function testHostOverridesGetRequiresUuid(): void
    {
        $tools = new \DnsTools($this->createManager());
        $result = $tools->dns_host_overrides('get');
        $this->assertArrayHasKey('error', $result);
    }

    public function testHostOverridesCreateRequiresData(): void
    {
        $tools = new \DnsTools($this->createManager());
        $result = $tools->dns_host_overrides('create');
        $this->assertArrayHasKey('error', $result);
    }

    public function testHostOverridesUnknownAction(): void
    {
        $tools = new \DnsTools($this->createManager());
        $result = $tools->dns_host_overrides('bad');
        $this->assertArrayHasKey('error', $result);
    }

    public function testDomainOverridesList(): void
    {
        $fixture = json_encode(['rows' => [], 'total' => 0, 'rowCount' => 0, 'current' => 1]);
        $http = fn() => ['code' => 200, 'body' => $fixture];

        $tools = new \DnsTools($this->createManager($http));
        $result = $tools->dns_domain_overrides('list');

        $this->assertEquals('primary', $result['instance']);
        $this->assertIsArray($result['overrides']);
    }
}
