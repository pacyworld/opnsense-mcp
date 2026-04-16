<?php

namespace Tests;

use OPNsense\InstanceManager;
use PHPUnit\Framework\TestCase;

class InterfaceToolsTest extends TestCase
{
    private function createManager(?callable $http = null): InstanceManager
    {
        return InstanceManager::fromFile(__DIR__ . '/fixtures/instances.json', $http);
    }

    public function testInterfaces(): void
    {
        $fixture = json_encode([
            [
                'device' => 'vmx0',
                'description' => 'LAN',
                'status' => 'up',
                'macaddr' => '00:50:56:96:88:ca',
                'mtu' => '1500',
                'media' => 'Ethernet autoselect',
                'is_physical' => true,
                'addr4' => '192.168.1.100/24',
                'ipv4' => [['ipaddr' => '192.168.1.100/24']],
                'addr6' => '',
                'ipv6' => [],
            ],
            [
                'device' => 'vmx1',
                'description' => 'WAN',
                'status' => 'up',
                'macaddr' => '00:50:56:96:7d:7a',
                'mtu' => '1500',
                'media' => 'Ethernet autoselect',
                'is_physical' => true,
                'addr4' => '64.33.237.250/29',
                'ipv4' => [['ipaddr' => '64.33.237.250/29']],
                'addr6' => '',
                'ipv6' => [],
            ],
        ]);
        $http = fn() => ['code' => 200, 'body' => $fixture];

        $tools = new \InterfaceTools($this->createManager($http));
        $result = $tools->interfaces();

        $this->assertEquals('primary', $result['instance']);
        $this->assertEquals(2, $result['count']);
        $this->assertEquals('vmx0', $result['interfaces'][0]['device']);
        $this->assertEquals('LAN', $result['interfaces'][0]['description']);
        $this->assertEquals(['192.168.1.100/24'], $result['interfaces'][0]['ipv4']);
        $this->assertEquals('WAN', $result['interfaces'][1]['description']);
    }

    public function testVlansList(): void
    {
        $fixture = json_encode(['rows' => [], 'total' => 0, 'rowCount' => 0, 'current' => 1]);
        $http = fn() => ['code' => 200, 'body' => $fixture];

        $tools = new \InterfaceTools($this->createManager($http));
        $result = $tools->vlans('list');

        $this->assertEquals('primary', $result['instance']);
        $this->assertIsArray($result['vlans']);
        $this->assertEquals(0, $result['total']);
    }

    public function testVlansGetRequiresUuid(): void
    {
        $tools = new \InterfaceTools($this->createManager());
        $result = $tools->vlans('get');
        $this->assertArrayHasKey('error', $result);
    }

    public function testVlansUnknownAction(): void
    {
        $tools = new \InterfaceTools($this->createManager());
        $result = $tools->vlans('bad');
        $this->assertArrayHasKey('error', $result);
    }
}
