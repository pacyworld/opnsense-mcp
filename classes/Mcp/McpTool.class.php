<?php
/**
 * OPNsense MCP Server - Tool Attribute
 *
 * PHP 8 attribute for marking methods as MCP tools.
 *
 * @package    OPNsenseMCP\Mcp
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace Mcp;

use Attribute;

/**
 * McpTool - Attribute to mark a method as an MCP tool.
 *
 * Methods marked with this attribute are automatically discovered and
 * registered as callable tools in the MCP protocol.
 *
 * Example usage:
 * ```php
 * class MyTools
 * {
 *     #[McpTool(description: 'Search for items by keyword')]
 *     public function search(string $query): array
 *     {
 *         // Implementation
 *     }
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD)]
class McpTool
{
    /**
     * Create a new McpTool attribute instance.
     *
     * @param string|null $name        Tool name (defaults to method name if null)
     * @param string|null $description Tool description for clients (defaults to docblock)
     * @param array|null  $inputSchema JSON Schema for tool parameters (auto-generated if null)
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?array $inputSchema = null
    ) {}
}
