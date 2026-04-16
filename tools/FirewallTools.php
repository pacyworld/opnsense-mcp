<?php
/**
 * OPNsense MCP Server - Firewall Tools
 *
 * MCP tools for managing OPNsense firewall rules and aliases.
 *
 * @package    OPNsenseMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use Mcp\McpTool;
use OPNsense\InstanceManager;

/**
 * FirewallTools - Firewall rule and alias management tools.
 */
class FirewallTools
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
     * List firewall filter rules with optional search.
     *
     * @param  string $action       Action to perform: list, get, create, update, delete, toggle
     * @param  string $search       Search phrase to filter rules (for list action)
     * @param  string $uuid         Rule UUID (for get, update, delete, toggle actions)
     * @param  array  $rule         Rule data (for create and update actions)
     * @param  string $instance     Instance name (empty = default)
     * @return array                 Rule list or operation result
     */
    #[McpTool(description: 'Manage OPNsense firewall filter rules. Actions: list, get, create, update, delete, toggle')]
    public function firewall_rules(
        string $action = 'list',
        string $search = '',
        string $uuid = '',
        array $rule = [],
        string $instance = ''
    ): array {
        $client = $this->manager->getClient($instance ?: null);
        $instanceName = $instance ?: $this->manager->getDefault();

        return match ($action) {
            'list' => $this->listRules($client, $search, $instanceName),
            'get' => $this->getRule($client, $uuid, $instanceName),
            'create' => $this->createRule($client, $rule, $instanceName),
            'update' => $this->updateRule($client, $uuid, $rule, $instanceName),
            'delete' => $this->deleteRule($client, $uuid, $instanceName),
            'toggle' => $this->toggleRule($client, $uuid, $instanceName),
            default => ['error' => "Unknown action: {$action}. Valid: list, get, create, update, delete, toggle"],
        };
    }

    /**
     * Manage firewall aliases (address groups, port groups, etc.).
     *
     * @param  string $action   Action: list, get, create, update, delete
     * @param  string $search   Search phrase (for list action)
     * @param  string $uuid     Alias UUID (for get, update, delete)
     * @param  array  $alias    Alias data (for create and update)
     * @param  string $instance Instance name (empty = default)
     * @return array             Alias list or operation result
     */
    #[McpTool(description: 'Manage OPNsense firewall aliases (address/port groups). Actions: list, get, create, update, delete')]
    public function firewall_aliases(
        string $action = 'list',
        string $search = '',
        string $uuid = '',
        array $alias = [],
        string $instance = ''
    ): array {
        $client = $this->manager->getClient($instance ?: null);
        $instanceName = $instance ?: $this->manager->getDefault();

        return match ($action) {
            'list' => $this->listAliases($client, $search, $instanceName),
            'get' => $this->getAlias($client, $uuid, $instanceName),
            'create' => $this->createAlias($client, $alias, $instanceName),
            'update' => $this->updateAlias($client, $uuid, $alias, $instanceName),
            'delete' => $this->deleteAlias($client, $uuid, $instanceName),
            default => ['error' => "Unknown action: {$action}. Valid: list, get, create, update, delete"],
        };
    }

    /**
     * Apply pending firewall changes.
     *
     * @param  string $instance Instance name (empty = default)
     * @return array             Apply result
     */
    #[McpTool(description: 'Apply pending firewall changes on an OPNsense instance')]
    public function firewall_apply(string $instance = ''): array
    {
        $client = $this->manager->getClient($instance ?: null);
        $result = $client->post('firewall/filter/apply');

        return [
            'instance' => $instance ?: $this->manager->getDefault(),
            'status' => $result['status'] ?? 'unknown',
            'result' => $result,
        ];
    }

    // --- Rule operations ---

    private function listRules(\OPNsense\Client $client, string $search, string $instanceName): array
    {
        $data = ['current' => 1, 'rowCount' => -1, 'searchPhrase' => $search];
        $result = $client->post('firewall/filter/searchRule', $data);

        return [
            'instance' => $instanceName,
            'rules' => $result['rows'] ?? [],
            'total' => $result['total'] ?? 0,
        ];
    }

    private function getRule(\OPNsense\Client $client, string $uuid, string $instanceName): array
    {
        if (empty($uuid)) {
            return ['error' => 'UUID is required for get action'];
        }
        $result = $client->get("firewall/filter/getRule/{$uuid}");
        return ['instance' => $instanceName, 'rule' => $result['rule'] ?? $result];
    }

    private function createRule(\OPNsense\Client $client, array $rule, string $instanceName): array
    {
        if (empty($rule)) {
            return ['error' => 'Rule data is required for create action'];
        }
        $result = $client->post('firewall/filter/addRule', ['rule' => $rule]);
        return ['instance' => $instanceName, 'result' => $result];
    }

    private function updateRule(\OPNsense\Client $client, string $uuid, array $rule, string $instanceName): array
    {
        if (empty($uuid)) {
            return ['error' => 'UUID is required for update action'];
        }
        if (empty($rule)) {
            return ['error' => 'Rule data is required for update action'];
        }
        $result = $client->post("firewall/filter/setRule/{$uuid}", ['rule' => $rule]);
        return ['instance' => $instanceName, 'result' => $result];
    }

    private function deleteRule(\OPNsense\Client $client, string $uuid, string $instanceName): array
    {
        if (empty($uuid)) {
            return ['error' => 'UUID is required for delete action'];
        }
        $result = $client->post("firewall/filter/delRule/{$uuid}");
        return ['instance' => $instanceName, 'result' => $result];
    }

    private function toggleRule(\OPNsense\Client $client, string $uuid, string $instanceName): array
    {
        if (empty($uuid)) {
            return ['error' => 'UUID is required for toggle action'];
        }
        $result = $client->post("firewall/filter/toggleRule/{$uuid}");
        return ['instance' => $instanceName, 'result' => $result];
    }

    // --- Alias operations ---

    private function listAliases(\OPNsense\Client $client, string $search, string $instanceName): array
    {
        $data = ['current' => 1, 'rowCount' => -1, 'searchPhrase' => $search];
        $result = $client->post('firewall/alias/searchItem', $data);

        return [
            'instance' => $instanceName,
            'aliases' => $result['rows'] ?? [],
            'total' => $result['total'] ?? 0,
        ];
    }

    private function getAlias(\OPNsense\Client $client, string $uuid, string $instanceName): array
    {
        if (empty($uuid)) {
            return ['error' => 'UUID is required for get action'];
        }
        $result = $client->get("firewall/alias/getItem/{$uuid}");
        return ['instance' => $instanceName, 'alias' => $result['alias'] ?? $result];
    }

    private function createAlias(\OPNsense\Client $client, array $alias, string $instanceName): array
    {
        if (empty($alias)) {
            return ['error' => 'Alias data is required for create action'];
        }
        $result = $client->post('firewall/alias/addItem', ['alias' => $alias]);
        return ['instance' => $instanceName, 'result' => $result];
    }

    private function updateAlias(\OPNsense\Client $client, string $uuid, array $alias, string $instanceName): array
    {
        if (empty($uuid)) {
            return ['error' => 'UUID is required for update action'];
        }
        if (empty($alias)) {
            return ['error' => 'Alias data is required for update action'];
        }
        $result = $client->post("firewall/alias/setItem/{$uuid}", ['alias' => $alias]);
        return ['instance' => $instanceName, 'result' => $result];
    }

    private function deleteAlias(\OPNsense\Client $client, string $uuid, string $instanceName): array
    {
        if (empty($uuid)) {
            return ['error' => 'UUID is required for delete action'];
        }
        $result = $client->post("firewall/alias/delItem/{$uuid}");
        return ['instance' => $instanceName, 'result' => $result];
    }
}
