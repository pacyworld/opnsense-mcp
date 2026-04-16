<?php
/**
 * OPNsense MCP Server - System Tools
 *
 * MCP tools for OPNsense system status and firmware management.
 *
 * @package    OPNsenseMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use Mcp\McpTool;
use OPNsense\InstanceManager;

/**
 * SystemTools - System status and firmware tools.
 */
class SystemTools
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
     * Get system status including product info, version, and update status.
     *
     * @param  string $instance Instance name (empty = default)
     * @return array             System status data
     */
    #[McpTool(description: 'Get OPNsense system status including product version, architecture, and update availability')]
    public function system_status(string $instance = ''): array
    {
        $client = $this->manager->getClient($instance ?: null);

        $firmware = $client->get('core/firmware/status');
        $product = $firmware['product'] ?? [];

        return [
            'instance' => $instance ?: $this->manager->getDefault(),
            'product' => $product['product_name'] ?? 'OPNsense',
            'version' => $product['product_version'] ?? 'unknown',
            'series' => $product['product_series'] ?? '',
            'nickname' => $product['product_nickname'] ?? '',
            'arch' => $product['product_arch'] ?? '',
            'latest_version' => $product['product_latest'] ?? '',
            'update_available' => (
                isset($product['product_version'], $product['product_latest'])
                && $product['product_version'] !== $product['product_latest']
            ),
            'repository' => $product['product_repos'] ?? '',
            'mirror' => $product['product_mirror'] ?? '',
            'status_msg' => $firmware['status_msg'] ?? '',
        ];
    }

    /**
     * Get detailed firmware information and check for available updates.
     *
     * @param  string $instance Instance name (empty = default)
     * @return array             Firmware details with update info
     */
    #[McpTool(description: 'Get detailed firmware information and check for available updates on an OPNsense instance')]
    public function firmware_status(string $instance = ''): array
    {
        $client = $this->manager->getClient($instance ?: null);

        $status = $client->get('core/firmware/status');
        $product = $status['product'] ?? [];

        $result = [
            'instance' => $instance ?: $this->manager->getDefault(),
            'current_version' => $product['product_version'] ?? 'unknown',
            'latest_version' => $product['product_latest'] ?? 'unknown',
            'product_name' => $product['product_name'] ?? 'OPNsense',
            'product_series' => $product['product_series'] ?? '',
            'product_abi' => $product['product_abi'] ?? '',
            'product_arch' => $product['product_arch'] ?? '',
            'status_msg' => $status['status_msg'] ?? '',
        ];

        // Check for available updates
        if (isset($product['product_version'], $product['product_latest'])) {
            $result['update_available'] = ($product['product_version'] !== $product['product_latest']);
        }

        // Try to get changelog/update details
        try {
            $info = $client->get('core/firmware/info');
            if (!empty($info['product_version'])) {
                $result['changelog'] = $info['changelog'] ?? '';
            }
        } catch (\Exception $e) {
            // info endpoint may not be available or may require update check first
        }

        return $result;
    }
}
