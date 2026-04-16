<?php
/**
 * Tests for Mcp\ToolRegistry — attribute discovery and schema generation.
 *
 * @package    OPNsenseMCP\Tests
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace Tests;

use Mcp\McpTool;
use Mcp\ToolRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Tool class with various parameter types for testing schema generation.
 */
class SchemaTestTools
{
    #[McpTool(description: 'Tool with all param types')]
    public function allTypes(string $name, int $count, float $ratio, bool $enabled, array $items): string
    {
        return 'ok';
    }

    #[McpTool(description: 'Tool with optional params')]
    public function withDefaults(string $required, string $optional = 'default'): string
    {
        return "{$required}:{$optional}";
    }

    #[McpTool(name: 'custom_name', description: 'Tool with custom name')]
    public function originalMethodName(): string
    {
        return 'custom';
    }

    #[McpTool(description: 'Tool with no params')]
    public function noParams(): string
    {
        return 'no params';
    }

    /** A public method WITHOUT McpTool attribute — should not be registered. */
    public function notATool(): string
    {
        return 'not a tool';
    }
}

class ToolRegistryTest extends TestCase
{
    private ToolRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new ToolRegistry();
        $this->registry->register(new SchemaTestTools());
    }

    public function testDiscoversMcpToolMethods(): void
    {
        $tools = $this->registry->listTools();
        $names = array_column($tools, 'name');

        $this->assertContains('allTypes', $names);
        $this->assertContains('withDefaults', $names);
        $this->assertContains('custom_name', $names);
        $this->assertContains('noParams', $names);
        $this->assertNotContains('notATool', $names);
    }

    public function testSchemaTypeMappings(): void
    {
        $tools = $this->registry->listTools();
        $allTypes = $this->findTool($tools, 'allTypes');

        $props = $allTypes['inputSchema']['properties'];
        $this->assertEquals('string', $props['name']['type']);
        $this->assertEquals('integer', $props['count']['type']);
        $this->assertEquals('number', $props['ratio']['type']);
        $this->assertEquals('boolean', $props['enabled']['type']);
        $this->assertEquals('array', $props['items']['type']);
    }

    public function testRequiredVsOptionalParams(): void
    {
        $tools = $this->registry->listTools();
        $withDefaults = $this->findTool($tools, 'withDefaults');

        $required = $withDefaults['inputSchema']['required'];
        $this->assertContains('required', $required);
        $this->assertNotContains('optional', $required);
    }

    public function testCustomToolName(): void
    {
        $this->assertTrue($this->registry->hasTool('custom_name'));
        $this->assertFalse($this->registry->hasTool('originalMethodName'));
    }

    public function testNoParamsSchema(): void
    {
        $tools = $this->registry->listTools();
        $noParams = $this->findTool($tools, 'noParams');

        $this->assertEquals('object', $noParams['inputSchema']['type']);
        $this->assertArrayNotHasKey('required', $noParams['inputSchema']);
    }

    public function testCallToolWithArguments(): void
    {
        $result = $this->registry->callTool('withDefaults', ['required' => 'hello']);
        $this->assertEquals('hello:default', $result);
    }

    public function testCallToolWithAllArguments(): void
    {
        $result = $this->registry->callTool('withDefaults', [
            'required' => 'hello',
            'optional' => 'world',
        ]);
        $this->assertEquals('hello:world', $result);
    }

    public function testCallToolMissingRequiredArgument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required argument');
        $this->registry->callTool('withDefaults', []);
    }

    public function testCallUnknownTool(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown tool');
        $this->registry->callTool('nonexistent', []);
    }

    public function testHasTool(): void
    {
        $this->assertTrue($this->registry->hasTool('allTypes'));
        $this->assertFalse($this->registry->hasTool('notATool'));
        $this->assertFalse($this->registry->hasTool('doesNotExist'));
    }

    /**
     * Find a tool by name in the tools list.
     */
    private function findTool(array $tools, string $name): array
    {
        foreach ($tools as $tool) {
            if ($tool['name'] === $name) {
                return $tool;
            }
        }
        $this->fail("Tool '{$name}' not found");
    }
}
