<?php
/**
 * OPNsense MCP Server - Diagnostics Tools
 *
 * MCP tools for OPNsense network diagnostics.
 *
 * @package    OPNsenseMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use Mcp\McpTool;
use OPNsense\InstanceManager;

/**
 * DiagnosticsTools - Network diagnostics tools.
 */
class DiagnosticsTools
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
     * Get the ARP table showing MAC-to-IP mappings on the network.
     *
     * @param  string $instance Instance name (empty = default)
     * @return array             ARP table entries
     */
    #[McpTool(description: 'Get the ARP table (MAC-to-IP mappings) from an OPNsense instance')]
    public function arp_table(string $instance = ''): array
    {
        $client = $this->manager->getClient($instance ?: null);
        $entries = $client->get('diagnostics/interface/getArp');

        return [
            'instance' => $instance ?: $this->manager->getDefault(),
            'entries' => $entries,
            'count' => count($entries),
        ];
    }

    /**
     * Get gateway status including online/offline state, latency, and packet loss.
     *
     * @param  string $instance Instance name (empty = default)
     * @return array             Gateway status data
     */
    #[McpTool(description: 'Get gateway status (online/offline, latency, packet loss) from an OPNsense instance')]
    public function gateway_status(string $instance = ''): array
    {
        $client = $this->manager->getClient($instance ?: null);
        $result = $client->get('routes/gateway/status');

        return [
            'instance' => $instance ?: $this->manager->getDefault(),
            'gateways' => $result['items'] ?? [],
            'status' => $result['status'] ?? 'unknown',
        ];
    }

    /**
     * Get the routing table showing configured static routes.
     *
     * @param  string $search   Search phrase to filter routes
     * @param  string $instance Instance name (empty = default)
     * @return array             Routing table entries
     */
    #[McpTool(description: 'Get the routing table (static routes) from an OPNsense instance')]
    public function routing_table(string $search = '', string $instance = ''): array
    {
        $client = $this->manager->getClient($instance ?: null);
        $data = ['current' => 1, 'rowCount' => -1, 'searchPhrase' => $search];
        $result = $client->post('routes/routes/searchroute', $data);

        return [
            'instance' => $instance ?: $this->manager->getDefault(),
            'routes' => $result['rows'] ?? [],
            'total' => $result['total'] ?? 0,
        ];
    }

    /**
     * Get the NDP (IPv6 Neighbor Discovery) table.
     *
     * @param  string $instance Instance name (empty = default)
     * @return array             NDP table entries
     */
    #[McpTool(description: 'Get the NDP table (IPv6 neighbor discovery) from an OPNsense instance')]
    public function ndp_table(string $instance = ''): array
    {
        $client = $this->manager->getClient($instance ?: null);
        $entries = $client->get('diagnostics/interface/getNdp');

        return [
            'instance' => $instance ?: $this->manager->getDefault(),
            'entries' => is_array($entries) ? $entries : [],
            'count' => is_array($entries) ? count($entries) : 0,
        ];
    }
}
