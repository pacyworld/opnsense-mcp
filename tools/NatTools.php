<?php
/**
 * OPNsense MCP Server - NAT Tools
 *
 * MCP tools for NAT rule management (port forwarding, outbound NAT).
 *
 * @package    OPNsenseMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use Mcp\McpTool;
use OPNsense\InstanceManager;

/**
 * NatTools - NAT rule management tools.
 */
class NatTools
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
     * Manage outbound NAT (source NAT) rules.
     *
     * @param  string $action   Action: list, get, create, update, delete
     * @param  string $search   Search phrase (for list)
     * @param  string $uuid     Rule UUID (for get, update, delete)
     * @param  array  $rule     Rule data (for create, update)
     * @param  string $instance Instance name (empty = default)
     * @return array             Rule list or operation result
     */
    #[McpTool(description: 'Manage outbound NAT (source NAT) rules. Actions: list, get, create, update, delete')]
    public function nat_outbound(
        string $action = 'list',
        string $search = '',
        string $uuid = '',
        array $rule = [],
        string $instance = ''
    ): array {
        $client = $this->manager->getClient($instance ?: null);
        $instanceName = $instance ?: $this->manager->getDefault();

        return match ($action) {
            'list' => $this->searchEndpoint($client, 'firewall/source_nat/searchRule', $search, $instanceName, 'rules'),
            'get' => $this->getEndpoint($client, 'firewall/source_nat/getRule', $uuid, $instanceName, 'rule'),
            'create' => $this->createEndpoint($client, 'firewall/source_nat/addRule', $rule, $instanceName, 'rule'),
            'update' => $this->updateEndpoint($client, 'firewall/source_nat/setRule', $uuid, $rule, $instanceName, 'rule'),
            'delete' => $this->deleteEndpoint($client, 'firewall/source_nat/delRule', $uuid, $instanceName),
            default => ['error' => "Unknown action: {$action}. Valid: list, get, create, update, delete"],
        };
    }

    /**
     * Manage port forwarding (destination NAT) rules.
     *
     * @param  string $action   Action: list, get, create, update, delete
     * @param  string $search   Search phrase (for list)
     * @param  string $uuid     Rule UUID (for get, update, delete)
     * @param  array  $rule     Rule data (for create, update)
     * @param  string $instance Instance name (empty = default)
     * @return array             Rule list or operation result
     */
    #[McpTool(description: 'Manage port forwarding (destination NAT) rules. Actions: list, get, create, update, delete')]
    public function nat_port_forward(
        string $action = 'list',
        string $search = '',
        string $uuid = '',
        array $rule = [],
        string $instance = ''
    ): array {
        $client = $this->manager->getClient($instance ?: null);
        $instanceName = $instance ?: $this->manager->getDefault();

        return match ($action) {
            'list' => $this->searchEndpoint($client, 'firewall/destination_nat/searchRule', $search, $instanceName, 'rules'),
            'get' => $this->getEndpoint($client, 'firewall/destination_nat/getRule', $uuid, $instanceName, 'rule'),
            'create' => $this->createEndpoint($client, 'firewall/destination_nat/addRule', $rule, $instanceName, 'rule'),
            'update' => $this->updateEndpoint($client, 'firewall/destination_nat/setRule', $uuid, $rule, $instanceName, 'rule'),
            'delete' => $this->deleteEndpoint($client, 'firewall/destination_nat/delRule', $uuid, $instanceName),
            default => ['error' => "Unknown action: {$action}. Valid: list, get, create, update, delete"],
        };
    }

    // --- Shared helpers ---

    private function searchEndpoint(\OPNsense\Client $client, string $endpoint, string $search, string $instanceName, string $key): array
    {
        $result = $client->post($endpoint, ['current' => 1, 'rowCount' => -1, 'searchPhrase' => $search]);
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
            return ['error' => 'Rule data is required for create action'];
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
            return ['error' => 'Rule data is required for update action'];
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
