<?php
/**
 * OPNsense MCP Server - JSON-RPC Server
 *
 * Main MCP protocol server implementation.
 *
 * @package    OPNsenseMCP\Mcp
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace Mcp;

/**
 * McpServer - MCP JSON-RPC Server.
 *
 * Handles MCP protocol messages including initialize, tools/list, and tools/call.
 * Implements the Model Context Protocol specification for tool-based interactions.
 *
 * Example usage:
 * ```php
 * $server = new McpServer('MyServer', '1.0.0');
 * $server->register(new MyToolsClass());
 * $response = $server->handleRequest($jsonRpcRequest);
 * ```
 */
class McpServer
{
    /**
     * Tool registry for registered handlers.
     *
     * @var ToolRegistry
     */
    private ToolRegistry $registry;

    /**
     * Server identification info.
     *
     * @var array{name:string,version:string}
     */
    private array $serverInfo;

    /**
     * MCP protocol version supported.
     *
     * @var string
     */
    private string $protocolVersion = '2025-03-26';

    /**
     * Create a new MCP server instance.
     *
     * @param string $name    Server name for client identification
     * @param string $version Server version string
     */
    public function __construct(string $name = 'opnsense-mcp', string $version = '1.0.0')
    {
        $this->registry = new ToolRegistry();
        $this->serverInfo = [
            'name' => $name,
            'version' => $version,
        ];
    }

    /**
     * Register an object's tools with the server.
     *
     * @param  object $handler Object containing McpTool-annotated methods
     * @return self            Fluent interface
     */
    public function register(object $handler): self
    {
        $this->registry->register($handler);
        return $this;
    }

    /**
     * Handle a JSON-RPC request and return a response.
     *
     * Dispatches to appropriate handler based on method name.
     * Supports: initialize, notifications/initialized, tools/list, tools/call, ping.
     *
     * @param  array<string,mixed> $request JSON-RPC request object
     * @return array<string,mixed>          JSON-RPC response object
     */
    public function handleRequest(array $request): array
    {
        $id = $request['id'] ?? null;
        $method = $request['method'] ?? '';
        $params = $request['params'] ?? [];

        try {
            $result = match($method) {
                'initialize' => $this->handleInitialize($params),
                'notifications/initialized' => null, // Notification, no response
                'tools/list' => $this->handleToolsList($params),
                'tools/call' => $this->handleToolsCall($params),
                'ping' => new \stdClass(), // Empty object per MCP spec
                default => throw new \Exception("Method not found: {$method}", -32601),
            };

            // Notifications don't get responses
            if ($result === null) {
                return [];
            }

            return $this->successResponse($id, $result);

        } catch (\Throwable $e) {
            return $this->errorResponse($id, (int)($e->getCode()) ?: -32603, $e->getMessage());
        }
    }

    /**
     * Handle initialize request.
     *
     * Returns server capabilities and protocol version.
     *
     * @param  array<string,mixed> $params Request parameters
     * @return array<string,mixed>         Initialize response
     */
    private function handleInitialize(array $params): array
    {
        return [
            'protocolVersion' => $this->protocolVersion,
            'capabilities' => [
                'tools' => new \stdClass(), // Empty object = tools supported
            ],
            'serverInfo' => $this->serverInfo,
        ];
    }

    /**
     * Handle tools/list request.
     *
     * Returns all registered tools with their schemas.
     *
     * @param  array<string,mixed> $params Request parameters
     * @return array<string,mixed>         Tools list response
     */
    private function handleToolsList(array $params): array
    {
        return [
            'tools' => $this->registry->listTools(),
        ];
    }

    /**
     * Handle tools/call request.
     *
     * Invokes the named tool with provided arguments.
     *
     * @param  array<string,mixed> $params Request parameters with 'name' and 'arguments'
     * @return array<string,mixed>         Tool call response with content
     * @throws \Exception                  If tool not found (code -32602)
     */
    private function handleToolsCall(array $params): array
    {
        $name = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        if (!$this->registry->hasTool($name)) {
            throw new \Exception("Unknown tool: {$name}", -32602);
        }

        try {
            $result = $this->registry->callTool($name, $arguments);
        } catch (\Throwable $e) {
            // Tool execution error — return as MCP error content, not JSON-RPC error
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode(['error' => $e->getMessage()]),
                    ],
                ],
                'isError' => true,
            ];
        }

        // Format result as MCP content
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_SLASHES),
                ],
            ],
        ];
    }

    /**
     * Build a JSON-RPC success response.
     *
     * @param  mixed                       $id     Request ID
     * @param  array<string,mixed>|object  $result Result data
     * @return array<string,mixed>                 JSON-RPC response
     */
    private function successResponse($id, array|object $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    /**
     * Build a JSON-RPC error response.
     *
     * @param  mixed  $id      Request ID
     * @param  int    $code    Error code
     * @param  string $message Error message
     * @return array<string,mixed> JSON-RPC error response
     */
    private function errorResponse($id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
