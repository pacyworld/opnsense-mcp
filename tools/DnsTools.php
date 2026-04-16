<?php
/**
 * OPNsense MCP Server - DNS Tools
 *
 * MCP tools for Unbound DNS management (host overrides, domain overrides).
 *
 * @package    OPNsenseMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use Mcp\McpTool;
use OPNsense\InstanceManager;

/**
 * DnsTools - DNS/Unbound management tools.
 */
class DnsTools
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
     * Manage Unbound DNS host overrides.
     *
     * @param  string $action   Action: list, get, create, update, delete
     * @param  string $search   Search phrase (for list)
     * @param  string $uuid     Override UUID (for get, update, delete)
     * @param  array  $override Override data (for create, update): hostname, domain, server (IP), description
     * @param  string $instance Instance name (empty = default)
     * @return array             Override list or operation result
     */
    #[McpTool(description: 'Manage Unbound DNS host overrides. Actions: list, get, create, update, delete')]
    public function dns_host_overrides(
        string $action = 'list',
        string $search = '',
        string $uuid = '',
        array $override = [],
        string $instance = ''
    ): array {
        $client = $this->manager->getClient($instance ?: null);
        $instanceName = $instance ?: $this->manager->getDefault();

        return match ($action) {
            'list' => $this->searchEndpoint($client, 'unbound/settings/searchHostOverride', $search, $instanceName, 'overrides'),
            'get' => $this->getEndpoint($client, 'unbound/settings/getHostOverride', $uuid, $instanceName, 'override'),
            'create' => $this->createEndpoint($client, 'unbound/settings/addHostOverride', $override, $instanceName, 'host'),
            'update' => $this->updateEndpoint($client, 'unbound/settings/setHostOverride', $uuid, $override, $instanceName, 'host'),
            'delete' => $this->deleteEndpoint($client, 'unbound/settings/delHostOverride', $uuid, $instanceName),
            default => ['error' => "Unknown action: {$action}. Valid: list, get, create, update, delete"],
        };
    }

    /**
     * Manage Unbound DNS domain overrides (forwarding zones).
     *
     * @param  string $action   Action: list, get, create, update, delete
     * @param  string $search   Search phrase (for list)
     * @param  string $uuid     Override UUID (for get, update, delete)
     * @param  array  $override Override data (for create, update): domain, server (IP), description
     * @param  string $instance Instance name (empty = default)
     * @return array             Override list or operation result
     */
    #[McpTool(description: 'Manage Unbound DNS domain overrides (forwarding zones). Actions: list, get, create, update, delete')]
    public function dns_domain_overrides(
        string $action = 'list',
        string $search = '',
        string $uuid = '',
        array $override = [],
        string $instance = ''
    ): array {
        $client = $this->manager->getClient($instance ?: null);
        $instanceName = $instance ?: $this->manager->getDefault();

        return match ($action) {
            'list' => $this->searchEndpoint($client, 'unbound/settings/searchDomainOverride', $search, $instanceName, 'overrides'),
            'get' => $this->getEndpoint($client, 'unbound/settings/getDomainOverride', $uuid, $instanceName, 'override'),
            'create' => $this->createEndpoint($client, 'unbound/settings/addDomainOverride', $override, $instanceName, 'domain'),
            'update' => $this->updateEndpoint($client, 'unbound/settings/setDomainOverride', $uuid, $override, $instanceName, 'domain'),
            'delete' => $this->deleteEndpoint($client, 'unbound/settings/delDomainOverride', $uuid, $instanceName),
            default => ['error' => "Unknown action: {$action}. Valid: list, get, create, update, delete"],
        };
    }

    // --- Shared helpers ---

    private function searchEndpoint(\OPNsense\Client $client, string $endpoint, string $search, string $instanceName, string $key): array
    {
        $result = $client->get($endpoint);
        return ['instance' => $instanceName, $key => $result['rows'] ?? [], 'total' => $result['total'] ?? 0];
    }

    private function getEndpoint(\OPNsense\Client $client, string $endpoint, string $uuid, string $instanceName, string $key): array
    {
        if (empty($uuid)) {
            return ['error' => 'UUID is required for get action'];
        }
        $result = $client->get("{$endpoint}/{$uuid}");
        return ['instance' => $instanceName, $key => $result[$key] ?? $result];
    }

    private function createEndpoint(\OPNsense\Client $client, string $endpoint, array $data, string $instanceName, string $key): array
    {
        if (empty($data)) {
            return ['error' => 'Override data is required for create action'];
        }
        $result = $client->post($endpoint, [$key => $data]);
        return ['instance' => $instanceName, 'result' => $result];
    }

    private function updateEndpoint(\OPNsense\Client $client, string $endpoint, string $uuid, array $data, string $instanceName, string $key): array
    {
        if (empty($uuid)) {
            return ['error' => 'UUID is required for update action'];
        }
        if (empty($data)) {
            return ['error' => 'Override data is required for update action'];
        }
        $result = $client->post("{$endpoint}/{$uuid}", [$key => $data]);
        return ['instance' => $instanceName, 'result' => $result];
    }

    private function deleteEndpoint(\OPNsense\Client $client, string $endpoint, string $uuid, string $instanceName): array
    {
        if (empty($uuid)) {
            return ['error' => 'UUID is required for delete action'];
        }
        $result = $client->post("{$endpoint}/{$uuid}");
        return ['instance' => $instanceName, 'result' => $result];
    }
}
