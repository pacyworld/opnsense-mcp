<?php
/**
 * OPNsense MCP Server - Log Tools
 *
 * MCP tools for retrieving OPNsense logs (firewall, system).
 *
 * @package    OPNsenseMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use Mcp\McpTool;
use OPNsense\InstanceManager;

/**
 * LogTools - Log retrieval tools.
 */
class LogTools
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
     * Get recent firewall log entries (blocked/passed packets).
     *
     * @param  int    $limit    Number of entries to return (default 50, max 1000)
     * @param  string $instance Instance name (empty = default)
     * @return array             Firewall log entries
     */
    #[McpTool(description: 'Get recent firewall log entries showing blocked and passed packets')]
    public function firewall_log(int $limit = 50, string $instance = ''): array
    {
        $client = $this->manager->getClient($instance ?: null);
        $limit = min(max($limit, 1), 1000);

        $entries = $client->get('diagnostics/firewall/log/firewall');

        // API returns up to 1000 entries, slice to requested limit
        if (is_array($entries)) {
            $entries = array_slice($entries, 0, $limit);
        }

        return [
            'instance' => $instance ?: $this->manager->getDefault(),
            'entries' => $entries,
            'count' => is_array($entries) ? count($entries) : 0,
        ];
    }

    /**
     * Get recent system log entries.
     *
     * @param  int    $limit    Number of entries to return (default 50, max 1000)
     * @param  string $instance Instance name (empty = default)
     * @return array             System log entries
     */
    #[McpTool(description: 'Get recent system log entries from an OPNsense instance')]
    public function system_log(int $limit = 50, string $instance = ''): array
    {
        $client = $this->manager->getClient($instance ?: null);
        $limit = min(max($limit, 1), 1000);

        $entries = $client->get('diagnostics/log/core/syslog');

        if (is_array($entries)) {
            $entries = array_slice($entries, 0, $limit);
        }

        return [
            'instance' => $instance ?: $this->manager->getDefault(),
            'entries' => $entries,
            'count' => is_array($entries) ? count($entries) : 0,
        ];
    }
}
