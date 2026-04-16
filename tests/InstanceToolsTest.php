<?php
/**
 * Tests for InstanceTools — list and info tools.
 *
 * @package    OPNsenseMCP\Tests
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace Tests;

use OPNsense\InstanceManager;
use PHPUnit\Framework\TestCase;

class InstanceToolsTest extends TestCase
{
    private function createManager(?callable $http = null): InstanceManager
    {
        return InstanceManager::fromFile(__DIR__ . '/fixtures/instances.json', $http);
    }

    public function testListInstances(): void
    {
        $manager = $this->createManager();
        $tools = new \InstanceTools($manager);

        $result = $tools->list_instances();

        $this->assertEquals('primary', $result['default']);
        $this->assertEquals(2, $result['count']);
        $this->assertArrayHasKey('primary', $result['instances']);
        $this->assertArrayHasKey('secondary', $result['instances']);
        $this->assertTrue($result['instances']['primary']['is_default']);
    }

    public function testInstanceInfoDefault(): void
    {
        $fixture = file_get_contents(__DIR__ . '/fixtures/system-status.json');
        $http = fn() => ['code' => 200, 'body' => $fixture];

        $manager = $this->createManager($http);
        $tools = new \InstanceTools($manager);

        $result = $tools->instance_info();

        $this->assertEquals('primary', $result['instance']);
        $this->assertEquals('https://10.0.0.1', $result['url']);
        $this->assertEquals('OPNsense', $result['product']);
        $this->assertEquals('26.1.2_5', $result['version']);
        $this->assertEquals('Witty Woodpecker', $result['nickname']);
        $this->assertEquals('amd64', $result['arch']);
        $this->assertTrue($result['update_available']);
    }

    public function testInstanceInfoNamed(): void
    {
        $fixture = file_get_contents(__DIR__ . '/fixtures/system-status.json');
        $http = fn() => ['code' => 200, 'body' => $fixture];

        $manager = $this->createManager($http);
        $tools = new \InstanceTools($manager);

        $result = $tools->instance_info('secondary');

        $this->assertEquals('secondary', $result['instance']);
        $this->assertEquals('https://10.0.0.2', $result['url']);
    }

    public function testInstanceInfoHandlesApiError(): void
    {
        $http = fn() => ['code' => 401, 'body' => 'Unauthorized'];

        $manager = $this->createManager($http);
        $tools = new \InstanceTools($manager);

        $result = $tools->instance_info();

        $this->assertEquals('primary', $result['instance']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Authentication', $result['error']);
    }
}
