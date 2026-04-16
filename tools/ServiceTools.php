<?php
/**
 * OPNsense MCP Server - Service Tools
 *
 * MCP tools for OPNsense service management.
 *
 * @package    OPNsenseMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use Mcp\McpTool;
use OPNsense\InstanceManager;

/**
 * ServiceTools - Service management tools.
 */
class ServiceTools
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
     * List all services and their running status.
     *
     * @param  string $instance Instance name (empty = default)
     * @return array             Service list with running/locked status
     */
    #[McpTool(description: 'List all services and their running status on an OPNsense instance')]
    public function service_list(string $instance = ''): array
    {
        $client = $this->manager->getClient($instance ?: null);
        $result = $client->get('core/service/search');

        return [
            'instance' => $instance ?: $this->manager->getDefault(),
            'services' => $result['rows'] ?? [],
            'total' => $result['total'] ?? 0,
        ];
    }

    /**
     * Control a service (start, stop, restart).
     *
     * @param  string $service  Service ID (e.g., "unbound", "ntpd", "cron")
     * @param  string $action   Action: start, stop, restart
     * @param  string $instance Instance name (empty = default)
     * @return array             Action result
     */
    #[McpTool(description: 'Control a service: start, stop, or restart. Provide the service ID from service_list')]
    public function service_control(string $service, string $action = 'restart', string $instance = ''): array
    {
        if (empty($service)) {
            return ['error' => 'Service ID is required (e.g., "unbound", "ntpd")'];
        }

        $validActions = ['start', 'stop', 'restart'];
        if (!in_array($action, $validActions, true)) {
            return ['error' => "Invalid action: {$action}. Valid: " . implode(', ', $validActions)];
        }

        $client = $this->manager->getClient($instance ?: null);
        $result = $client->post("core/service/{$action}/{$service}");

        return [
            'instance' => $instance ?: $this->manager->getDefault(),
            'service' => $service,
            'action' => $action,
            'result' => $result,
        ];
    }
}
