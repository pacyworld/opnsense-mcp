<?php
/**
 * OPNsense MCP Server - Instance Management Tools
 *
 * MCP tools for listing and inspecting configured OPNsense instances.
 *
 * @package    OPNsenseMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use Mcp\McpTool;
use OPNsense\InstanceManager;

/**
 * InstanceTools - Tools for managing OPNsense instance context.
 *
 * Provides tools to list configured firewalls and get detailed
 * information about specific instances.
 */
class InstanceTools
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
     * List all configured OPNsense firewall instances.
     *
     * @return array Instance list with names, URLs, descriptions, and default status
     */
    #[McpTool(description: 'List all configured OPNsense firewall instances')]
    public function list_instances(): array
    {
        return [
            'default' => $this->manager->getDefault(),
            'instances' => $this->manager->listInstances(),
            'count' => $this->manager->count(),
        ];
    }

    /**
     * Get detailed info about an OPNsense instance including live system and firmware status.
     *
     * @param  string $instance Instance name (empty = default)
     * @return array             Instance info with system and firmware data
     */
    #[McpTool(description: 'Get detailed info about an OPNsense instance including live system and firmware status')]
    public function instance_info(string $instance = ''): array
    {
        $client = $this->manager->getClient($instance ?: null);
        $name = $instance ?: $this->manager->getDefault();

        $info = [
            'instance' => $name,
            'url' => $client->getBaseUrl(),
        ];

        try {
            $firmware = $client->get('core/firmware/status');
            $product = $firmware['product'] ?? [];
            $info['product'] = $product['product_name'] ?? 'OPNsense';
            $info['version'] = $product['product_version'] ?? 'unknown';
            $info['series'] = $product['product_series'] ?? '';
            $info['nickname'] = $product['product_nickname'] ?? '';
            $info['arch'] = $product['product_arch'] ?? '';
            $info['latest'] = $product['product_latest'] ?? '';
            $info['update_available'] = (
                isset($product['product_version'], $product['product_latest'])
                && $product['product_version'] !== $product['product_latest']
            );
        } catch (\Exception $e) {
            $info['error'] = $e->getMessage();
        }

        return $info;
    }
}
