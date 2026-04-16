<?php
/**
 * OPNsense MCP Server - HAProxy Tools
 *
 * MCP tools for HAProxy management (requires os-haproxy plugin).
 *
 * @package    OPNsenseMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use Mcp\McpTool;
use OPNsense\InstanceManager;

/**
 * HaproxyTools - HAProxy management tools.
 *
 * Requires the os-haproxy plugin to be installed on the OPNsense instance.
 */
class HaproxyTools
{
    /**
     * @var InstanceManager
     */
    private InstanceManager $manager;

    /**
     * @param InstanceManager $manager Instance manager
     */
    public function __construct(InstanceManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Manage HAProxy servers. Requires os-haproxy plugin.
     *
     * @param  string $action   Action: list, get, create, update, delete
     * @param  string $search   Search phrase (for list)
     * @param  string $uuid     Server UUID (for get, update, delete)
     * @param  array  $server   Server data (for create, update)
     * @param  string $instance Instance name (empty = default)
     * @return array             Server list or operation result
     */
    #[McpTool(description: 'Manage HAProxy servers (requires os-haproxy plugin). Actions: list, get, create, update, delete')]
    public function haproxy_servers(
        string $action = 'list',
        string $search = '',
        string $uuid = '',
        array $server = [],
        string $instance = ''
    ): array {
        $client = $this->manager->getClient($instance ?: null);
        $instanceName = $instance ?: $this->manager->getDefault();

        try {
            return match ($action) {
                'list' => $this->searchEndpoint($client, 'haproxy/settings/searchServers', $search, $instanceName, 'servers'),
                'get' => $this->getEndpoint($client, 'haproxy/settings/getServer', $uuid, $instanceName, 'server'),
                'create' => $this->createEndpoint($client, 'haproxy/settings/addServer', $server, $instanceName, 'server'),
                'update' => $this->updateEndpoint($client, 'haproxy/settings/setServer', $uuid, $server, $instanceName, 'server'),
                'delete' => $this->deleteEndpoint($client, 'haproxy/settings/delServer', $uuid, $instanceName),
                default => ['error' => "Unknown action: {$action}. Valid: list, get, create, update, delete"],
            };
        } catch (\OPNsense\ClientException $e) {
            return ['instance' => $instanceName, 'error' => 'HAProxy plugin not available: ' . $e->getMessage()];
        }
    }

    /**
     * Manage HAProxy backends. Requires os-haproxy plugin.
     *
     * @param  string $action   Action: list, get, create, update, delete
     * @param  string $search   Search phrase (for list)
     * @param  string $uuid     Backend UUID (for get, update, delete)
     * @param  array  $backend  Backend data (for create, update)
     * @param  string $instance Instance name (empty = default)
     * @return array             Backend list or operation result
     */
    #[McpTool(description: 'Manage HAProxy backends (requires os-haproxy plugin). Actions: list, get, create, update, delete')]
    public function haproxy_backends(
        string $action = 'list',
        string $search = '',
        string $uuid = '',
        array $backend = [],
        string $instance = ''
    ): array {
        $client = $this->manager->getClient($instance ?: null);
        $instanceName = $instance ?: $this->manager->getDefault();

        try {
            return match ($action) {
                'list' => $this->searchEndpoint($client, 'haproxy/settings/searchBackends', $search, $instanceName, 'backends'),
                'get' => $this->getEndpoint($client, 'haproxy/settings/getBackend', $uuid, $instanceName, 'backend'),
                'create' => $this->createEndpoint($client, 'haproxy/settings/addBackend', $backend, $instanceName, 'backend'),
                'update' => $this->updateEndpoint($client, 'haproxy/settings/setBackend', $uuid, $backend, $instanceName, 'backend'),
                'delete' => $this->deleteEndpoint($client, 'haproxy/settings/delBackend', $uuid, $instanceName),
                default => ['error' => "Unknown action: {$action}. Valid: list, get, create, update, delete"],
            };
        } catch (\OPNsense\ClientException $e) {
            return ['instance' => $instanceName, 'error' => 'HAProxy plugin not available: ' . $e->getMessage()];
        }
    }

    // --- Shared helpers ---

    private function searchEndpoint(\OPNsense\Client $client, string $endpoint, string $search, string $instanceName, string $key): array
    {
        $result = $client->post($endpoint, ['current' => 1, 'rowCount' => -1, 'searchPhrase' => $search]);
        return ['instance' => $instanceName, $key => $result['rows'] ?? [], 'total' => $result['total'] ?? 0];
    }

    private function getEndpoint(\OPNsense\Client $client, string $endpoint, string $uuid, string $instanceName, string $key): array
    {
        if (empty($uuid)) { return ['error' => 'UUID is required for get action']; }
        $result = $client->get("{$endpoint}/{$uuid}");
        return ['instance' => $instanceName, $key => $result[$key] ?? $result];
    }

    private function createEndpoint(\OPNsense\Client $client, string $endpoint, array $data, string $instanceName, string $key): array
    {
        if (empty($data)) { return ['error' => ucfirst($key) . ' data is required']; }
        $result = $client->post($endpoint, [$key => $data]);
        return ['instance' => $instanceName, 'result' => $result];
    }

    private function updateEndpoint(\OPNsense\Client $client, string $endpoint, string $uuid, array $data, string $instanceName, string $key): array
    {
        if (empty($uuid)) { return ['error' => 'UUID is required for update action']; }
        if (empty($data)) { return ['error' => ucfirst($key) . ' data is required']; }
        $result = $client->post("{$endpoint}/{$uuid}", [$key => $data]);
        return ['instance' => $instanceName, 'result' => $result];
    }

    private function deleteEndpoint(\OPNsense\Client $client, string $endpoint, string $uuid, string $instanceName): array
    {
        if (empty($uuid)) { return ['error' => 'UUID is required for delete action']; }
        $result = $client->post("{$endpoint}/{$uuid}");
        return ['instance' => $instanceName, 'result' => $result];
    }
}
