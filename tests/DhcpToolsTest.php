<?php

namespace Tests;

use OPNsense\InstanceManager;
use PHPUnit\Framework\TestCase;

class DhcpToolsTest extends TestCase
{
    private function createManager(?callable $http = null): InstanceManager
    {
        return InstanceManager::fromFile(__DIR__ . '/fixtures/instances.json', $http);
    }

    public function testDhcpLeases(): void
    {
        $fixture = json_encode([
            'rows' => [
                ['address' => '192.168.1.50', 'hwaddr' => 'aa:bb:cc:dd:ee:ff', 'hostname' => 'laptop'],
            ],
            'total' => 1,
            'rowCount' => 1,
            'current' => 1,
            'interfaces' => ['lan'],
        ]);
        $http = fn() => ['code' => 200, 'body' => $fixture];

        $tools = new \DhcpTools($this->createManager($http));
        $result = $tools->dhcp_leases();

        $this->assertEquals('primary', $result['instance']);
        $this->assertCount(1, $result['leases']);
        $this->assertEquals('192.168.1.50', $result['leases'][0]['address']);
    }

    public function testDhcpReservationsList(): void
    {
        $fixture = json_encode(['rows' => [], 'total' => 0, 'rowCount' => 0, 'current' => 1]);
        $http = fn() => ['code' => 200, 'body' => $fixture];

        $tools = new \DhcpTools($this->createManager($http));
        $result = $tools->dhcp_reservations('list');

        $this->assertEquals('primary', $result['instance']);
        $this->assertIsArray($result['reservations']);
    }

    public function testDhcpReservationsGetRequiresUuid(): void
    {
        $tools = new \DhcpTools($this->createManager());
        $result = $tools->dhcp_reservations('get');
        $this->assertArrayHasKey('error', $result);
    }

    public function testDhcpReservationsCreateRequiresData(): void
    {
        $tools = new \DhcpTools($this->createManager());
        $result = $tools->dhcp_reservations('create');
        $this->assertArrayHasKey('error', $result);
    }

    public function testDhcpReservationsUnknownAction(): void
    {
        $tools = new \DhcpTools($this->createManager());
        $result = $tools->dhcp_reservations('bad');
        $this->assertArrayHasKey('error', $result);
    }
}
