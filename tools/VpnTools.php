<?php
/**
 * OPNsense MCP Server - VPN Tools
 *
 * MCP tools for VPN status and management (WireGuard, OpenVPN, IPsec).
 *
 * @package    OPNsenseMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use Mcp\McpTool;
use OPNsense\InstanceManager;

/**
 * VpnTools - VPN status and management tools.
 */
class VpnTools
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
     * Get VPN status for WireGuard, OpenVPN, and/or IPsec tunnels.
     *
     * @param  string $type     VPN type: all, wireguard, openvpn, ipsec
     * @param  string $instance Instance name (empty = default)
     * @return array             VPN status data
     */
    #[McpTool(description: 'Get VPN tunnel status. Types: all, wireguard, openvpn, ipsec')]
    public function vpn_status(string $type = 'all', string $instance = ''): array
    {
        $client = $this->manager->getClient($instance ?: null);
        $instanceName = $instance ?: $this->manager->getDefault();
        $result = ['instance' => $instanceName];

        $types = ($type === 'all') ? ['wireguard', 'openvpn', 'ipsec'] : [$type];

        foreach ($types as $vpnType) {
            try {
                $result[$vpnType] = match ($vpnType) {
                    'wireguard' => $this->getWireGuard($client),
                    'openvpn' => $this->getOpenVpn($client),
                    'ipsec' => $this->getIpsec($client),
                    default => ['error' => "Unknown VPN type: {$vpnType}"],
                };
            } catch (\Exception $e) {
                $result[$vpnType] = ['error' => $e->getMessage()];
            }
        }

        return $result;
    }

    /**
     * Manage OpenVPN instances (server/client configurations).
     *
     * @param  string $action   Action: list, get, create, update, delete
     * @param  string $search   Search phrase (for list)
     * @param  string $uuid     Instance UUID (for get, update, delete)
     * @param  array  $config   Instance data (for create, update)
     * @param  string $instance Firewall instance name (empty = default)
     * @return array             OpenVPN instances or operation result
     */
    #[McpTool(description: 'Manage OpenVPN instances (server/client configs). Actions: list, get, create, update, delete')]
    public function openvpn_instances(
        string $action = 'list',
        string $search = '',
        string $uuid = '',
        array $config = [],
        string $instance = ''
    ): array {
        $client = $this->manager->getClient($instance ?: null);
        $instanceName = $instance ?: $this->manager->getDefault();

        return match ($action) {
            'list' => $this->searchEndpoint($client, 'openvpn/instances/searchItem', $search, $instanceName, 'instances'),
            'get' => $this->getEndpoint($client, 'openvpn/instances/getItem', $uuid, $instanceName, 'instance'),
            'create' => $this->createEndpoint($client, 'openvpn/instances/addItem', $config, $instanceName, 'instance'),
            'update' => $this->updateEndpoint($client, 'openvpn/instances/setItem', $uuid, $config, $instanceName, 'instance'),
            'delete' => $this->deleteEndpoint($client, 'openvpn/instances/delItem', $uuid, $instanceName),
            default => ['error' => "Unknown action: {$action}. Valid: list, get, create, update, delete"],
        };
    }

    private function getWireGuard(\OPNsense\Client $client): array
    {
        $result = $client->get('wireguard/service/show');
        return ['tunnels' => $result['rows'] ?? [], 'total' => $result['total'] ?? 0];
    }

    private function getOpenVpn(\OPNsense\Client $client): array
    {
        $result = $client->get('openvpn/service/searchSessions');
        return ['sessions' => $result['rows'] ?? [], 'total' => $result['total'] ?? 0];
    }

    private function getIpsec(\OPNsense\Client $client): array
    {
        $result = $client->get('ipsec/tunnel/searchPhase1');
        return ['tunnels' => $result['rows'] ?? [], 'total' => $result['total'] ?? 0];
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
        if (empty($data)) { return ['error' => 'Config data is required for create action']; }
        $result = $client->post($endpoint, [$key => $data]);
        return ['instance' => $instanceName, 'result' => $result];
    }

    private function updateEndpoint(\OPNsense\Client $client, string $endpoint, string $uuid, array $data, string $instanceName, string $key): array
    {
        if (empty($uuid)) { return ['error' => 'UUID is required for update action']; }
        if (empty($data)) { return ['error' => 'Config data is required for update action']; }
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
