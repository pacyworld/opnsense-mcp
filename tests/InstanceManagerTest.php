<?php
/**
 * Tests for OPNsense\InstanceManager — multi-instance config and client factory.
 *
 * @package    OPNsenseMCP\Tests
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace Tests;

use OPNsense\Client;
use OPNsense\InstanceManager;
use PHPUnit\Framework\TestCase;

class InstanceManagerTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__ . '/fixtures';
    }

    public function testFromFileLoadsConfig(): void
    {
        $manager = InstanceManager::fromFile($this->fixturesDir . '/instances.json');

        $this->assertEquals('primary', $manager->getDefault());
        $this->assertEquals(2, $manager->count());
    }

    public function testFromFileThrowsOnMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not found');
        InstanceManager::fromFile('/nonexistent/path/instances.json');
    }

    public function testFromFileThrowsOnInvalidJson(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'opnsense_test_');
        file_put_contents($tmpFile, 'not valid json {{{');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Invalid JSON');
            InstanceManager::fromFile($tmpFile);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testConstructorRequiresAtLeastOneInstance(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one instance');
        new InstanceManager([], 'primary');
    }

    public function testConstructorRequiresValidDefault(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("not found in configuration");
        new InstanceManager(
            ['fw1' => ['url' => 'https://10.0.0.1', 'api_key' => 'k', 'api_secret' => 's']],
            'nonexistent'
        );
    }

    public function testGetClientReturnsClientForDefault(): void
    {
        $manager = InstanceManager::fromFile($this->fixturesDir . '/instances.json');
        $client = $manager->getClient();

        $this->assertInstanceOf(Client::class, $client);
        $this->assertEquals('https://10.0.0.1', $client->getBaseUrl());
    }

    public function testGetClientReturnsClientForNamedInstance(): void
    {
        $manager = InstanceManager::fromFile($this->fixturesDir . '/instances.json');
        $client = $manager->getClient('secondary');

        $this->assertInstanceOf(Client::class, $client);
        $this->assertEquals('https://10.0.0.2', $client->getBaseUrl());
    }

    public function testGetClientCachesInstances(): void
    {
        $manager = InstanceManager::fromFile($this->fixturesDir . '/instances.json');
        $client1 = $manager->getClient('primary');
        $client2 = $manager->getClient('primary');

        $this->assertSame($client1, $client2);
    }

    public function testGetClientThrowsForUnknownInstance(): void
    {
        $manager = InstanceManager::fromFile($this->fixturesDir . '/instances.json');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown instance');
        $this->expectExceptionMessage('Available:');
        $manager->getClient('nonexistent');
    }

    public function testListInstances(): void
    {
        $manager = InstanceManager::fromFile($this->fixturesDir . '/instances.json');
        $list = $manager->listInstances();

        $this->assertCount(2, $list);
        $this->assertArrayHasKey('primary', $list);
        $this->assertArrayHasKey('secondary', $list);

        $this->assertEquals('https://10.0.0.1', $list['primary']['url']);
        $this->assertEquals('Primary test firewall', $list['primary']['description']);
        $this->assertTrue($list['primary']['is_default']);

        $this->assertEquals('https://10.0.0.2', $list['secondary']['url']);
        $this->assertFalse($list['secondary']['is_default']);
    }

    public function testSetDefault(): void
    {
        $manager = InstanceManager::fromFile($this->fixturesDir . '/instances.json');

        $this->assertEquals('primary', $manager->getDefault());
        $manager->setDefault('secondary');
        $this->assertEquals('secondary', $manager->getDefault());

        // getClient() without name should now return secondary
        $client = $manager->getClient();
        $this->assertEquals('https://10.0.0.2', $client->getBaseUrl());
    }

    public function testSetDefaultThrowsForUnknownInstance(): void
    {
        $manager = InstanceManager::fromFile($this->fixturesDir . '/instances.json');

        $this->expectException(\InvalidArgumentException::class);
        $manager->setDefault('nonexistent');
    }

    public function testHasInstance(): void
    {
        $manager = InstanceManager::fromFile($this->fixturesDir . '/instances.json');

        $this->assertTrue($manager->hasInstance('primary'));
        $this->assertTrue($manager->hasInstance('secondary'));
        $this->assertFalse($manager->hasInstance('nonexistent'));
    }

    public function testFromFileDefaultsToFirstInstance(): void
    {
        // Config without explicit "default" key
        $tmpFile = tempnam(sys_get_temp_dir(), 'opnsense_test_');
        file_put_contents($tmpFile, json_encode([
            'instances' => [
                'only_one' => [
                    'url' => 'https://10.0.0.99',
                    'api_key' => 'k',
                    'api_secret' => 's',
                ],
            ],
        ]));

        try {
            $manager = InstanceManager::fromFile($tmpFile);
            $this->assertEquals('only_one', $manager->getDefault());
        } finally {
            unlink($tmpFile);
        }
    }

    public function testHttpClientPassedToClients(): void
    {
        $called = false;
        $mockHttp = function () use (&$called) {
            $called = true;
            return ['code' => 200, 'body' => '{"ok":true}'];
        };

        $manager = InstanceManager::fromFile($this->fixturesDir . '/instances.json', $mockHttp);
        $client = $manager->getClient('primary');
        $client->get('core/firmware/status');

        $this->assertTrue($called, 'Mock HTTP client should have been called');
    }
}
