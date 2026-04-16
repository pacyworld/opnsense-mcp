<?php
/**
 * Tests for SystemTools — system and firmware status.
 *
 * @package    OPNsenseMCP\Tests
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace Tests;

use OPNsense\InstanceManager;
use PHPUnit\Framework\TestCase;

class SystemToolsTest extends TestCase
{
    private function createManager(?callable $http = null): InstanceManager
    {
        return InstanceManager::fromFile(__DIR__ . '/fixtures/instances.json', $http);
    }

    public function testSystemStatus(): void
    {
        $fixture = file_get_contents(__DIR__ . '/fixtures/system-status.json');
        $http = fn() => ['code' => 200, 'body' => $fixture];

        $manager = $this->createManager($http);
        $tools = new \SystemTools($manager);

        $result = $tools->system_status();

        $this->assertEquals('primary', $result['instance']);
        $this->assertEquals('OPNsense', $result['product']);
        $this->assertEquals('26.1.2_5', $result['version']);
        $this->assertEquals('26.1', $result['series']);
        $this->assertEquals('Witty Woodpecker', $result['nickname']);
        $this->assertEquals('amd64', $result['arch']);
        $this->assertTrue($result['update_available']);
    }

    public function testSystemStatusNamedInstance(): void
    {
        $fixture = file_get_contents(__DIR__ . '/fixtures/system-status.json');
        $http = fn() => ['code' => 200, 'body' => $fixture];

        $manager = $this->createManager($http);
        $tools = new \SystemTools($manager);

        $result = $tools->system_status('secondary');
        $this->assertEquals('secondary', $result['instance']);
    }

    public function testFirmwareStatus(): void
    {
        $fixture = file_get_contents(__DIR__ . '/fixtures/system-status.json');
        $http = fn() => ['code' => 200, 'body' => $fixture];

        $manager = $this->createManager($http);
        $tools = new \SystemTools($manager);

        $result = $tools->firmware_status();

        $this->assertEquals('primary', $result['instance']);
        $this->assertEquals('26.1.2_5', $result['current_version']);
        $this->assertEquals('26.1.2', $result['latest_version']);
        $this->assertEquals('OPNsense', $result['product_name']);
        $this->assertEquals('26.1', $result['product_series']);
        $this->assertEquals('amd64', $result['product_arch']);
        // Version 26.1.2_5 != 26.1.2 so update_available should be true
        $this->assertTrue($result['update_available']);
    }
}
