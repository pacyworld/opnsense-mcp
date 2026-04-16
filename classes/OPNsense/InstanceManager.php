<?php
/**
 * OPNsense MCP Server - Instance Manager
 *
 * Multi-instance configuration registry and client factory.
 *
 * @package    OPNsenseMCP\OPNsense
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace OPNsense;

/**
 * InstanceManager - Multi-instance OPNsense registry.
 *
 * Loads instance configurations from a JSON file and provides
 * Client instances for named firewalls.
 *
 * Example usage:
 * ```php
 * $manager = InstanceManager::fromFile('/path/to/instances.json');
 * $client = $manager->getClient('stargate');
 * $defaultClient = $manager->getClient(); // uses default instance
 * ```
 */
class InstanceManager
{
    /**
     * Instance configurations indexed by name.
     *
     * @var array<string,array{url:string,api_key:string,api_secret:string,verify_ssl?:bool,description?:string}>
     */
    private array $instances;

    /**
     * Name of the current default instance.
     *
     * @var string
     */
    private string $default;

    /**
     * Cache of Client instances indexed by name.
     *
     * @var array<string,Client>
     */
    private array $clients = [];

    /**
     * Optional HTTP client callable for dependency injection in tests.
     *
     * @var callable|null
     */
    private $httpClient;

    /**
     * Create a new InstanceManager.
     *
     * @param array<string,array>  $instances  Instance configurations
     * @param string               $default    Default instance name
     * @param callable|null        $httpClient Optional HTTP callable for testing
     */
    public function __construct(array $instances, string $default, ?callable $httpClient = null)
    {
        if (empty($instances)) {
            throw new \InvalidArgumentException('At least one instance must be configured.');
        }

        if (!isset($instances[$default])) {
            throw new \InvalidArgumentException("Default instance '{$default}' not found in configuration.");
        }

        $this->instances = $instances;
        $this->default = $default;
        $this->httpClient = $httpClient;
    }

    /**
     * Create an InstanceManager from a JSON configuration file.
     *
     * @param  string        $path       Path to instances.json
     * @param  callable|null $httpClient Optional HTTP callable for testing
     * @return self
     * @throws \RuntimeException         If file cannot be read or parsed
     */
    public static function fromFile(string $path, ?callable $httpClient = null): self
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Configuration file not found: {$path}");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Failed to read configuration file: {$path}");
        }

        $config = json_decode($json, true);
        if ($config === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                "Invalid JSON in configuration file {$path}: " . json_last_error_msg()
            );
        }

        $instances = $config['instances'] ?? [];
        $default = $config['default'] ?? '';

        if (empty($default) && !empty($instances)) {
            $default = array_key_first($instances);
        }

        return new self($instances, $default, $httpClient);
    }

    /**
     * Get a Client for the named instance (or default).
     *
     * Clients are cached — the same Client instance is returned
     * for repeated calls with the same name.
     *
     * @param  string|null $name Instance name (null = default)
     * @return Client             OPNsense API client
     * @throws \InvalidArgumentException If instance not found
     */
    public function getClient(?string $name = null): Client
    {
        $name = $name ?: $this->default;

        if (!isset($this->instances[$name])) {
            $available = implode(', ', array_keys($this->instances));
            throw new \InvalidArgumentException(
                "Unknown instance '{$name}'. Available: {$available}"
            );
        }

        if (!isset($this->clients[$name])) {
            $config = $this->instances[$name];
            $this->clients[$name] = new Client(
                $config['url'],
                $config['api_key'],
                $config['api_secret'],
                $config['verify_ssl'] ?? false,
                $config['timeout'] ?? 30,
                $this->httpClient
            );
        }

        return $this->clients[$name];
    }

    /**
     * List all configured instances.
     *
     * @return array<string,array{url:string,description:string,is_default:bool}> Instance summaries
     */
    public function listInstances(): array
    {
        $result = [];
        foreach ($this->instances as $name => $config) {
            $result[$name] = [
                'url' => $config['url'],
                'description' => $config['description'] ?? '',
                'is_default' => ($name === $this->default),
            ];
        }
        return $result;
    }

    /**
     * Get the current default instance name.
     *
     * @return string Default instance name
     */
    public function getDefault(): string
    {
        return $this->default;
    }

    /**
     * Set the default instance (runtime only, not persisted).
     *
     * @param  string $name Instance name to set as default
     * @throws \InvalidArgumentException If instance not found
     */
    public function setDefault(string $name): void
    {
        if (!isset($this->instances[$name])) {
            $available = implode(', ', array_keys($this->instances));
            throw new \InvalidArgumentException(
                "Unknown instance '{$name}'. Available: {$available}"
            );
        }

        $this->default = $name;
    }

    /**
     * Check if an instance exists.
     *
     * @param  string $name Instance name
     * @return bool         True if instance is configured
     */
    public function hasInstance(string $name): bool
    {
        return isset($this->instances[$name]);
    }

    /**
     * Get the number of configured instances.
     *
     * @return int Instance count
     */
    public function count(): int
    {
        return count($this->instances);
    }
}
