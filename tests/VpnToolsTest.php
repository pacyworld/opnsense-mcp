<?php

namespace Tests;

use OPNsense\InstanceManager;
use PHPUnit\Framework\TestCase;

class VpnToolsTest extends TestCase
{
    private function createManager(?callable $http = null): InstanceManager
    {
        return InstanceManager::fromFile(__DIR__ . '/fixtures/instances.json', $http);
    }

    public function testVpnStatusAll(): void
    {
        $http = fn() => ['code' => 200, 'body' => '{"total":0,"rowCount":0,"current":1,"rows":[]}'];
        $tools = new \VpnTools($this->createManager($http));

        $result = $tools->vpn_status('all');

        $this->assertEquals('primary', $result['instance']);
        $this->assertArrayHasKey('wireguard', $result);
        $this->assertArrayHasKey('openvpn', $result);
        $this->assertArrayHasKey('ipsec', $result);
    }

    public function testVpnStatusWireguard(): void
    {
        $fixture = json_encode(['total' => 1, 'rowCount' => 1, 'current' => 1, 'rows' => [
            ['name' => 'wg0', 'status' => 'up', 'peers' => 2],
        ]]);
        $http = fn() => ['code' => 200, 'body' => $fixture];
        $tools = new \VpnTools($this->createManager($http));

        $result = $tools->vpn_status('wireguard');

        $this->assertArrayHasKey('wireguard', $result);
        $this->assertEquals(1, $result['wireguard']['total']);
    }

    public function testVpnStatusHandlesApiError(): void
    {
        $http = fn() => ['code' => 500, 'body' => 'Internal Server Error'];
        $tools = new \VpnTools($this->createManager($http));

        $result = $tools->vpn_status('wireguard');

        $this->assertArrayHasKey('error', $result['wireguard']);
    }

    public function testOpenvpnInstancesList(): void
    {
        $fixture = json_encode(['rows' => [], 'total' => 0, 'rowCount' => 0, 'current' => 1]);
        $http = fn() => ['code' => 200, 'body' => $fixture];
        $tools = new \VpnTools($this->createManager($http));

        $result = $tools->openvpn_instances('list');

        $this->assertEquals('primary', $result['instance']);
        $this->assertIsArray($result['instances']);
    }

    public function testOpenvpnInstancesGetRequiresUuid(): void
    {
        $tools = new \VpnTools($this->createManager());
        $result = $tools->openvpn_instances('get');
        $this->assertArrayHasKey('error', $result);
    }
}
