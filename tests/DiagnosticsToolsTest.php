<?php
/**
 * Tests for DiagnosticsTools — ARP, gateways, routing.
 *
 * @package    OPNsenseMCP\Tests
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace Tests;

use OPNsense\InstanceManager;
use PHPUnit\Framework\TestCase;

class DiagnosticsToolsTest extends TestCase
{
    private function createManager(?callable $http = null): InstanceManager
    {
        return InstanceManager::fromFile(__DIR__ . '/fixtures/instances.json', $http);
    }

    public function testArpTable(): void
    {
        $fixture = file_get_contents(__DIR__ . '/fixtures/arp-table.json');
        $http = fn() => ['code' => 200, 'body' => $fixture];

        $manager = $this->createManager($http);
        $tools = new \DiagnosticsTools($manager);

        $result = $tools->arp_table();

        $this->assertEquals('primary', $result['instance']);
        $this->assertCount(2, $result['entries']);
        $this->assertEquals(2, $result['count']);
        $this->assertEquals('192.168.1.1', $result['entries'][0]['ip']);
        $this->assertEquals('00:50:56:c0:00:08', $result['entries'][0]['mac']);
    }

    public function testArpTableNamedInstance(): void
    {
        $fixture = file_get_contents(__DIR__ . '/fixtures/arp-table.json');
        $http = fn() => ['code' => 200, 'body' => $fixture];

        $manager = $this->createManager($http);
        $tools = new \DiagnosticsTools($manager);

        $result = $tools->arp_table('secondary');
        $this->assertEquals('secondary', $result['instance']);
    }

    public function testGatewayStatus(): void
    {
        $gatewayFixture = json_encode([
            'items' => [
                [
                    'name' => 'WAN_GW',
                    'address' => '10.0.0.1',
                    'status' => 'none',
                    'loss' => '0.0%',
                    'delay' => '1.2ms',
                    'status_translated' => 'Online',
                ],
            ],
            'status' => 'ok',
        ]);
        $http = fn() => ['code' => 200, 'body' => $gatewayFixture];

        $manager = $this->createManager($http);
        $tools = new \DiagnosticsTools($manager);

        $result = $tools->gateway_status();

        $this->assertEquals('primary', $result['instance']);
        $this->assertEquals('ok', $result['status']);
        $this->assertCount(1, $result['gateways']);
        $this->assertEquals('WAN_GW', $result['gateways'][0]['name']);
        $this->assertEquals('Online', $result['gateways'][0]['status_translated']);
    }

    public function testRoutingTable(): void
    {
        $routeFixture = json_encode([
            'rows' => [
                ['network' => '0.0.0.0/0', 'gateway' => '10.0.0.1', 'interface' => 'wan'],
            ],
            'rowCount' => 1,
            'total' => 1,
            'current' => 1,
        ]);
        $http = fn() => ['code' => 200, 'body' => $routeFixture];

        $manager = $this->createManager($http);
        $tools = new \DiagnosticsTools($manager);

        $result = $tools->routing_table();

        $this->assertEquals('primary', $result['instance']);
        $this->assertCount(1, $result['routes']);
        $this->assertEquals(1, $result['total']);
        $this->assertEquals('0.0.0.0/0', $result['routes'][0]['network']);
    }

    public function testNdpTable(): void
    {
        $ndpFixture = json_encode([
            ['ip' => 'fe80::1', 'mac' => 'aa:bb:cc:dd:ee:ff', 'intf' => 'em0'],
        ]);
        $http = fn() => ['code' => 200, 'body' => $ndpFixture];

        $manager = $this->createManager($http);
        $tools = new \DiagnosticsTools($manager);

        $result = $tools->ndp_table();

        $this->assertEquals('primary', $result['instance']);
        $this->assertCount(1, $result['entries']);
        $this->assertEquals(1, $result['count']);
    }
}
