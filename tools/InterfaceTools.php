<?php
/**
 * OPNsense MCP Server - Interface Tools
 *
 * MCP tools for OPNsense interface and VLAN management.
 *
 * @package    OPNsenseMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use Mcp\McpTool;
use OPNsense\InstanceManager;

/**
 * InterfaceTools - Interface and VLAN management tools.
 */
class InterfaceTools
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
     * List all network interfaces with their status, addresses, and configuration.
     *
     * @param  string $instance Instance name (empty = default)
     * @return array             Interface list
     */
    #[McpTool(description: 'List all network interfaces with status, addresses, and configuration from an OPNsense instance')]
    public function interfaces(string $instance = ''): array
    {
        $client = $this->manager->getClient($instance ?: null);
        $interfaces = $client->get('interfaces/overview/export');

        // Summarize each interface for readability
        $summary = [];
        foreach ($interfaces as $iface) {
            $entry = [
                'device' => $iface['device'] ?? '',
                'description' => $iface['description'] ?? '',
                'status' => $iface['status'] ?? 'unknown',
                'macaddr' => $iface['macaddr'] ?? '',
                'mtu' => $iface['mtu'] ?? '',
                'media' => $iface['media'] ?? '',
                'is_physical' => $iface['is_physical'] ?? false,
            ];

            // Collect IPv4 addresses
            $addrs = [];
            if (is_string($iface['addr4'] ?? null) && !empty($iface['addr4'])) {
                $addrs[] = $iface['addr4'];
            } else {
                foreach ($iface['ipv4'] ?? $iface['addr4'] ?? [] as $a) {
                    $addrs[] = is_array($a) ? ($a['ipaddr'] ?? '') : $a;
                }
            }
            $entry['ipv4'] = array_filter($addrs);

            // Collect IPv6 addresses
            $addrs6 = [];
            $raw6 = $iface['addr6'] ?? null;
            if (is_string($raw6) && !empty($raw6)) {
                $addrs6[] = $raw6;
            } elseif (is_array($raw6) || is_array($iface['ipv6'] ?? null)) {
                foreach ($iface['ipv6'] ?? $raw6 ?? [] as $a) {
                    $addrs6[] = is_array($a) ? ($a['ipaddr'] ?? '') : $a;
                }
            }
            $entry['ipv6'] = array_filter($addrs6);

            $summary[] = $entry;
        }

        return [
            'instance' => $instance ?: $this->manager->getDefault(),
            'interfaces' => $summary,
            'count' => count($summary),
        ];
    }

    /**
     * List and manage VLANs.
     *
     * @param  string $action   Action: list, get, create, update, delete
     * @param  string $search   Search phrase (for list)
     * @param  string $uuid     VLAN UUID (for get, update, delete)
     * @param  array  $vlan     VLAN data (for create, update)
     * @param  string $instance Instance name (empty = default)
     * @return array             VLAN list or operation result
     */
    #[McpTool(description: 'Manage VLANs on an OPNsense instance. Actions: list, get, create, update, delete')]
    public function vlans(
        string $action = 'list',
        string $search = '',
        string $uuid = '',
        array $vlan = [],
        string $instance = ''
    ): array {
        $client = $this->manager->getClient($instance ?: null);
        $instanceName = $instance ?: $this->manager->getDefault();

        return match ($action) {
            'list' => $this->searchEndpoint($client, 'interfaces/vlan_settings/searchItem', $search, $instanceName, 'vlans'),
            'get' => $this->getEndpoint($client, 'interfaces/vlan_settings/getItem', $uuid, $instanceName, 'vlan'),
            'create' => $this->mutateEndpoint($client, 'interfaces/vlan_settings/addItem', $vlan, $instanceName, 'vlan'),
            'update' => $this->mutateEndpointWithUuid($client, 'interfaces/vlan_settings/setItem', $uuid, $vlan, $instanceName, 'vlan'),
            'delete' => $this->deleteEndpoint($client, 'interfaces/vlan_settings/delItem', $uuid, $instanceName),
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

    private function mutateEndpoint(\OPNsense\Client $client, string $endpoint, array $data, string $instanceName, string $key): array
    {
        if (empty($data)) {
            return ['error' => ucfirst($key) . ' data is required'];
        }
        $result = $client->post($endpoint, [$key => $data]);
        return ['instance' => $instanceName, 'result' => $result];
    }

    private function mutateEndpointWithUuid(\OPNsense\Client $client, string $endpoint, string $uuid, array $data, string $instanceName, string $key): array
    {
        if (empty($uuid)) {
            return ['error' => 'UUID is required for update action'];
        }
        if (empty($data)) {
            return ['error' => ucfirst($key) . ' data is required'];
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
