<?php

namespace Tests;

use OPNsense\InstanceManager;
use PHPUnit\Framework\TestCase;

class BackupToolsTest extends TestCase
{
    private function createManager(?callable $http = null): InstanceManager
    {
        return InstanceManager::fromFile(__DIR__ . '/fixtures/instances.json', $http);
    }

    public function testBackupList(): void
    {
        $fixture = json_encode(['backups' => [
            ['id' => '20260415', 'description' => 'Auto backup', 'time' => '2026-04-15 12:00'],
        ]]);
        $http = fn() => ['code' => 200, 'body' => $fixture];
        $tools = new \BackupTools($this->createManager($http));

        $result = $tools->backup_list();

        $this->assertEquals('primary', $result['instance']);
        $this->assertCount(1, $result['backups']);
    }

    public function testBackupListHandlesError(): void
    {
        $http = fn() => ['code' => 500, 'body' => 'Internal Server Error'];
        $tools = new \BackupTools($this->createManager($http));

        $result = $tools->backup_list();

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not available', $result['error']);
    }

    public function testBackupCreate(): void
    {
        $http = fn() => ['code' => 200, 'body' => '{"status":"ok"}'];
        $tools = new \BackupTools($this->createManager($http));

        $result = $tools->backup_create();

        $this->assertEquals('primary', $result['instance']);
        $this->assertArrayHasKey('result', $result);
    }

    public function testBackupDeleteRequiresId(): void
    {
        $tools = new \BackupTools($this->createManager());
        $result = $tools->backup_delete();
        $this->assertArrayHasKey('error', $result);
    }

    public function testBackupDelete(): void
    {
        $http = fn() => ['code' => 200, 'body' => '{"status":"ok"}'];
        $tools = new \BackupTools($this->createManager($http));

        $result = $tools->backup_delete('20260415');

        $this->assertEquals('primary', $result['instance']);
        $this->assertArrayHasKey('result', $result);
    }
}
