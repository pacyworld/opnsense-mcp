<?php
/**
 * Tests for FirewallTools — rules and aliases management.
 *
 * @package    OPNsenseMCP\Tests
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace Tests;

use OPNsense\InstanceManager;
use PHPUnit\Framework\TestCase;

class FirewallToolsTest extends TestCase
{
    private function createManager(?callable $http = null): InstanceManager
    {
        return InstanceManager::fromFile(__DIR__ . '/fixtures/instances.json', $http);
    }

    public function testListRules(): void
    {
        $fixture = file_get_contents(__DIR__ . '/fixtures/firewall-rules.json');
        $http = fn() => ['code' => 200, 'body' => $fixture];

        $manager = $this->createManager($http);
        $tools = new \FirewallTools($manager);

        $result = $tools->firewall_rules('list');

        $this->assertEquals('primary', $result['instance']);
        $this->assertCount(2, $result['rules']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals('pass', $result['rules'][0]['action']);
        $this->assertEquals('block', $result['rules'][1]['action']);
    }

    public function testListRulesNamedInstance(): void
    {
        $fixture = file_get_contents(__DIR__ . '/fixtures/firewall-rules.json');
        $http = fn() => ['code' => 200, 'body' => $fixture];

        $manager = $this->createManager($http);
        $tools = new \FirewallTools($manager);

        $result = $tools->firewall_rules('list', '', '', [], 'secondary');
        $this->assertEquals('secondary', $result['instance']);
    }

    public function testGetRuleRequiresUuid(): void
    {
        $manager = $this->createManager();
        $tools = new \FirewallTools($manager);

        $result = $tools->firewall_rules('get');
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('UUID', $result['error']);
    }

    public function testCreateRuleRequiresData(): void
    {
        $manager = $this->createManager();
        $tools = new \FirewallTools($manager);

        $result = $tools->firewall_rules('create');
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('required', $result['error']);
    }

    public function testDeleteRuleRequiresUuid(): void
    {
        $manager = $this->createManager();
        $tools = new \FirewallTools($manager);

        $result = $tools->firewall_rules('delete');
        $this->assertArrayHasKey('error', $result);
    }

    public function testToggleRuleRequiresUuid(): void
    {
        $manager = $this->createManager();
        $tools = new \FirewallTools($manager);

        $result = $tools->firewall_rules('toggle');
        $this->assertArrayHasKey('error', $result);
    }

    public function testUnknownActionReturnsError(): void
    {
        $manager = $this->createManager();
        $tools = new \FirewallTools($manager);

        $result = $tools->firewall_rules('invalid');
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Unknown action', $result['error']);
    }

    public function testListAliases(): void
    {
        $aliasFixture = json_encode([
            'rows' => [
                ['uuid' => 'bogons', 'name' => 'bogons', 'type' => 'external', 'description' => 'bogon networks'],
            ],
            'rowCount' => 1,
            'total' => 1,
            'current' => 1,
        ]);
        $http = fn() => ['code' => 200, 'body' => $aliasFixture];

        $manager = $this->createManager($http);
        $tools = new \FirewallTools($manager);

        $result = $tools->firewall_aliases('list');

        $this->assertEquals('primary', $result['instance']);
        $this->assertCount(1, $result['aliases']);
        $this->assertEquals('bogons', $result['aliases'][0]['name']);
    }

    public function testFirewallApply(): void
    {
        $http = fn() => ['code' => 200, 'body' => '{"status":"ok"}'];

        $manager = $this->createManager($http);
        $tools = new \FirewallTools($manager);

        $result = $tools->firewall_apply();

        $this->assertEquals('primary', $result['instance']);
        $this->assertEquals('ok', $result['status']);
    }
}
