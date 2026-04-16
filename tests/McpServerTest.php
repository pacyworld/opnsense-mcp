<?php
/**
 * Tests for Mcp\McpServer — JSON-RPC protocol compliance.
 *
 * @package    OPNsenseMCP\Tests
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace Tests;

use Mcp\McpServer;
use Mcp\McpTool;
use PHPUnit\Framework\TestCase;

/**
 * Dummy tool class for testing McpServer.
 */
class DummyTools
{
    #[McpTool(description: 'Returns a greeting')]
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }

    #[McpTool(description: 'Adds two numbers')]
    public function add(int $a, int $b): array
    {
        return ['sum' => $a + $b];
    }
}

class McpServerTest extends TestCase
{
    private McpServer $server;

    protected function setUp(): void
    {
        $this->server = new McpServer('test-server', '1.0.0');
        $this->server->register(new DummyTools());
    }

    public function testInitializeReturnsProtocolAndCapabilities(): void
    {
        $response = $this->server->handleRequest([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => [],
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
        ]);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(1, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('2025-03-26', $response['result']['protocolVersion']);
        $this->assertArrayHasKey('tools', $response['result']['capabilities']);
        $this->assertEquals('test-server', $response['result']['serverInfo']['name']);
        $this->assertEquals('1.0.0', $response['result']['serverInfo']['version']);
    }

    public function testNotificationReturnsEmptyArray(): void
    {
        $response = $this->server->handleRequest([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ]);

        $this->assertEmpty($response);
    }

    public function testToolsListReturnsRegisteredTools(): void
    {
        $response = $this->server->handleRequest([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
            'params' => [],
        ]);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(2, $response['id']);
        $tools = $response['result']['tools'];
        $this->assertCount(2, $tools);

        $toolNames = array_column($tools, 'name');
        $this->assertContains('greet', $toolNames);
        $this->assertContains('add', $toolNames);

        // Verify schema structure
        $greet = $tools[array_search('greet', $toolNames)];
        $this->assertEquals('Returns a greeting', $greet['description']);
        $this->assertEquals('object', $greet['inputSchema']['type']);
        $this->assertArrayHasKey('name', $greet['inputSchema']['properties']);
        $this->assertContains('name', $greet['inputSchema']['required']);
    }

    public function testToolsCallStringResult(): void
    {
        $response = $this->server->handleRequest([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'greet',
                'arguments' => ['name' => 'World'],
            ],
        ]);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(3, $response['id']);
        $content = $response['result']['content'];
        $this->assertCount(1, $content);
        $this->assertEquals('text', $content[0]['type']);
        $this->assertEquals('Hello, World!', $content[0]['text']);
    }

    public function testToolsCallArrayResult(): void
    {
        $response = $this->server->handleRequest([
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => [
                'name' => 'add',
                'arguments' => ['a' => 3, 'b' => 7],
            ],
        ]);

        $content = $response['result']['content'];
        $this->assertEquals('text', $content[0]['type']);
        $decoded = json_decode($content[0]['text'], true);
        $this->assertEquals(10, $decoded['sum']);
    }

    public function testToolsCallUnknownToolReturnsError(): void
    {
        $response = $this->server->handleRequest([
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => [
                'name' => 'nonexistent',
                'arguments' => [],
            ],
        ]);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32602, $response['error']['code']);
        $this->assertStringContainsString('nonexistent', $response['error']['message']);
    }

    public function testUnknownMethodReturnsError(): void
    {
        $response = $this->server->handleRequest([
            'jsonrpc' => '2.0',
            'id' => 6,
            'method' => 'unknown/method',
            'params' => [],
        ]);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32601, $response['error']['code']);
    }

    public function testPingReturnsEmptyResult(): void
    {
        $response = $this->server->handleRequest([
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => 'ping',
        ]);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(7, $response['id']);
        $this->assertIsObject($response['result']);
    }

    public function testToolsCallExecutionErrorReturnsIsError(): void
    {
        // Call 'add' tool with wrong types — should return isError content, not JSON-RPC error
        $response = $this->server->handleRequest([
            'jsonrpc' => '2.0',
            'id' => 9,
            'method' => 'tools/call',
            'params' => [
                'name' => 'greet',
                // Missing required 'name' argument
                'arguments' => [],
            ],
        ]);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(9, $response['id']);
        $this->assertTrue($response['result']['isError']);
        $this->assertEquals('text', $response['result']['content'][0]['type']);
    }

    public function testServerWithNoToolsReturnsEmptyList(): void
    {
        $server = new McpServer('empty', '0.0.1');
        $response = $server->handleRequest([
            'jsonrpc' => '2.0',
            'id' => 8,
            'method' => 'tools/list',
            'params' => [],
        ]);

        $this->assertEmpty($response['result']['tools']);
    }
}
