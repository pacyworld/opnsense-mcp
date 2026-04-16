<?php
/**
 * OPNsense MCP Server - Backup Tools
 *
 * MCP tools for OPNsense configuration backup and restore.
 *
 * @package    OPNsenseMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use Mcp\McpTool;
use OPNsense\InstanceManager;

/**
 * BackupTools - Configuration backup tools.
 */
class BackupTools
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
     * List configuration backups available on the firewall.
     *
     * @param  string $instance Instance name (empty = default)
     * @return array             Backup list
     */
    #[McpTool(description: 'List available configuration backups on an OPNsense instance')]
    public function backup_list(string $instance = ''): array
    {
        $client = $this->manager->getClient($instance ?: null);

        try {
            $result = $client->get('core/backup/backups');
            return [
                'instance' => $instance ?: $this->manager->getDefault(),
                'backups' => $result['backups'] ?? $result,
            ];
        } catch (\Exception $e) {
            return [
                'instance' => $instance ?: $this->manager->getDefault(),
                'error' => 'Backup endpoint not available: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create a new configuration backup on the firewall.
     *
     * @param  string $instance Instance name (empty = default)
     * @return array             Backup result
     */
    #[McpTool(description: 'Create a new configuration backup on an OPNsense instance')]
    public function backup_create(string $instance = ''): array
    {
        $client = $this->manager->getClient($instance ?: null);

        try {
            $result = $client->post('core/backup/backup');
            return [
                'instance' => $instance ?: $this->manager->getDefault(),
                'result' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'instance' => $instance ?: $this->manager->getDefault(),
                'error' => 'Backup endpoint not available: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a configuration backup from the firewall.
     *
     * @param  string $id       Backup ID to delete
     * @param  string $instance Instance name (empty = default)
     * @return array             Delete result
     */
    #[McpTool(description: 'Delete a configuration backup from an OPNsense instance')]
    public function backup_delete(string $id = '', string $instance = ''): array
    {
        if (empty($id)) {
            return ['error' => 'Backup ID is required'];
        }

        $client = $this->manager->getClient($instance ?: null);

        try {
            $result = $client->post("core/backup/deleteBackup/{$id}");
            return [
                'instance' => $instance ?: $this->manager->getDefault(),
                'result' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'instance' => $instance ?: $this->manager->getDefault(),
                'error' => 'Backup endpoint not available: ' . $e->getMessage(),
            ];
        }
    }
}
