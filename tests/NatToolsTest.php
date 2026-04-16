<?php

namespace Tests;

use OPNsense\InstanceManager;
use PHPUnit\Framework\TestCase;

class NatToolsTest extends TestCase
{
    private function createManager(?callable $http = null): InstanceManager
    {
        return InstanceManager::fromFile(__DIR__ . '/fixtures/instances.json', $http);
    }

    public function testNatOutboundList(): void
    {
        $fixture = json_encode(['rows' => [], 'total' => 0, 'rowCount' => 0, 'current' => 1]);
        $http = fn() => ['code' => 200, 'body' => $fixture];

        $tools = new \NatTools($this->createManager($http));
        $result = $tools->nat_outbound('list');

        $this->assertEquals('primary', $result['instance']);
        $this->assertIsArray($result['rules']);
        $this->assertEquals(0, $result['total']);
    }

    public function testNatOutboundGetRequiresUuid(): void
    {
        $tools = new \NatTools($this->createManager());
        $result = $tools->nat_outbound('get');
        $this->assertArrayHasKey('error', $result);
    }

    public function testNatOutboundCreateRequiresData(): void
    {
        $tools = new \NatTools($this->createManager());
        $result = $tools->nat_outbound('create');
        $this->assertArrayHasKey('error', $result);
    }

    public function testNatOutboundUnknownAction(): void
    {
        $tools = new \NatTools($this->createManager());
        $result = $tools->nat_outbound('bad');
        $this->assertArrayHasKey('error', $result);
    }

    public function testNatPortForwardList(): void
    {
        $fixture = json_encode([
            'rows' => [
                ['uuid' => 'pf1', 'interface' => 'wan', 'protocol' => 'TCP', 'target' => '192.168.1.10', 'local_port' => '443'],
            ],
            'total' => 1,
            'rowCount' => 1,
            'current' => 1,
        ]);
        $http = fn() => ['code' => 200, 'body' => $fixture];

        $tools = new \NatTools($this->createManager($http));
        $result = $tools->nat_port_forward('list');

        $this->assertEquals('primary', $result['instance']);
        $this->assertCount(1, $result['rules']);
        $this->assertEquals('wan', $result['rules'][0]['interface']);
    }

    public function testNatPortForwardGetRequiresUuid(): void
    {
        $tools = new \NatTools($this->createManager());
        $result = $tools->nat_port_forward('get');
        $this->assertArrayHasKey('error', $result);
    }

    public function testNatPortForwardDeleteRequiresUuid(): void
    {
        $tools = new \NatTools($this->createManager());
        $result = $tools->nat_port_forward('delete');
        $this->assertArrayHasKey('error', $result);
    }
}
