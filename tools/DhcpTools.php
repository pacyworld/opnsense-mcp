<?php
/**
 * OPNsense MCP Server - DHCP Tools
 *
 * MCP tools for DHCP lease and reservation management.
 * Supports OPNsense 26.x Kea DHCP endpoints.
 *
 * @package    OPNsenseMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use Mcp\McpTool;
use OPNsense\InstanceManager;

/**
 * DhcpTools - DHCP lease and reservation management tools.
 */
class DhcpTools
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
     * Get DHCP leases showing active IP address assignments.
     *
     * @param  string $instance Instance name (empty = default)
     * @return array             Lease list
     */
    #[McpTool(description: 'Get DHCPv4 leases showing active IP address assignments from an OPNsense instance')]
    public function dhcp_leases(string $instance = ''): array
    {
        $client = $this->manager->getClient($instance ?: null);
        $instanceName = $instance ?: $this->manager->getDefault();

        // OPNsense 26.x uses Kea DHCP
        try {
            $result = $client->get('kea/leases4/search');
            return [
                'instance' => $instanceName,
                'leases' => $result['rows'] ?? [],
                'total' => $result['total'] ?? 0,
                'interfaces' => $result['interfaces'] ?? [],
            ];
        } catch (\Exception $e) {
            // Fall back to legacy endpoint
            try {
                $result = $client->get('dhcpv4/leases/searchLease');
                return [
                    'instance' => $instanceName,
                    'leases' => $result['rows'] ?? [],
                    'total' => $result['total'] ?? 0,
                ];
            } catch (\Exception $e2) {
                return [
                    'instance' => $instanceName,
                    'error' => 'DHCP lease endpoint not available: ' . $e->getMessage(),
                ];
            }
        }
    }

    /**
     * Manage DHCP static reservations (static maps).
     *
     * @param  string $action      Action: list, get, create, update, delete
     * @param  string $search      Search phrase (for list)
     * @param  string $uuid        Reservation UUID (for get, update, delete)
     * @param  array  $reservation Reservation data (for create, update)
     * @param  string $instance    Instance name (empty = default)
     * @return array                Reservation list or operation result
     */
    #[McpTool(description: 'Manage DHCP static reservations (static maps). Actions: list, get, create, update, delete')]
    public function dhcp_reservations(
        string $action = 'list',
        string $search = '',
        string $uuid = '',
        array $reservation = [],
        string $instance = ''
    ): array {
        $client = $this->manager->getClient($instance ?: null);
        $instanceName = $instance ?: $this->manager->getDefault();

        return match ($action) {
            'list' => $this->listReservations($client, $search, $instanceName),
            'get' => $this->getReservation($client, $uuid, $instanceName),
            'create' => $this->createReservation($client, $reservation, $instanceName),
            'update' => $this->updateReservation($client, $uuid, $reservation, $instanceName),
            'delete' => $this->deleteReservation($client, $uuid, $instanceName),
            default => ['error' => "Unknown action: {$action}. Valid: list, get, create, update, delete"],
        };
    }

    private function listReservations(\OPNsense\Client $client, string $search, string $instanceName): array
    {
        $result = $client->get('kea/dhcpv4/searchReservation');
        return [
            'instance' => $instanceName,
            'reservations' => $result['rows'] ?? [],
            'total' => $result['total'] ?? 0,
        ];
    }

    private function getReservation(\OPNsense\Client $client, string $uuid, string $instanceName): array
    {
        if (empty($uuid)) {
            return ['error' => 'UUID is required for get action'];
        }
        $result = $client->get("kea/dhcpv4/getReservation/{$uuid}");
        return ['instance' => $instanceName, 'reservation' => $result['reservation'] ?? $result];
    }

    private function createReservation(\OPNsense\Client $client, array $data, string $instanceName): array
    {
        if (empty($data)) {
            return ['error' => 'Reservation data is required for create action'];
        }
        $result = $client->post('kea/dhcpv4/addReservation', ['reservation' => $data]);
        return ['instance' => $instanceName, 'result' => $result];
    }

    private function updateReservation(\OPNsense\Client $client, string $uuid, array $data, string $instanceName): array
    {
        if (empty($uuid)) {
            return ['error' => 'UUID is required for update action'];
        }
        if (empty($data)) {
            return ['error' => 'Reservation data is required for update action'];
        }
        $result = $client->post("kea/dhcpv4/setReservation/{$uuid}", ['reservation' => $data]);
        return ['instance' => $instanceName, 'result' => $result];
    }

    private function deleteReservation(\OPNsense\Client $client, string $uuid, string $instanceName): array
    {
        if (empty($uuid)) {
            return ['error' => 'UUID is required for delete action'];
        }
        $result = $client->post("kea/dhcpv4/delReservation/{$uuid}");
        return ['instance' => $instanceName, 'result' => $result];
    }
}
