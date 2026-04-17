#!/usr/bin/env php
<?php
/**
 * OPNsense MCP Server — PHAR Build Script
 *
 * Packages the project into a single executable opnsense-mcp.phar file.
 *
 * Usage:
 *   php -d phar.readonly=0 bin/build-phar.php
 *
 * Output:
 *   build/opnsense-mcp.phar
 *
 * @package    OPNsenseMCP
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

if (ini_get('phar.readonly')) {
    fwrite(STDERR, "Error: phar.readonly is enabled.\n");
    fwrite(STDERR, "Run with: php -d phar.readonly=0 bin/build-phar.php\n");
    exit(1);
}

$projectRoot = dirname(__DIR__);
$buildDir = $projectRoot . '/build';
$pharFile = $buildDir . '/opnsense-mcp.phar';

// Clean previous build
if (!is_dir($buildDir)) {
    mkdir($buildDir, 0755, true);
}
if (file_exists($pharFile)) {
    unlink($pharFile);
}

echo "Building opnsense-mcp.phar...\n";

$phar = new Phar($pharFile, 0, 'opnsense-mcp.phar');
$phar->startBuffering();

// Add classes
$addDir = function (string $dir, string $prefix = '') use ($phar, $projectRoot) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    $count = 0;
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $relativePath = substr($file->getPathname(), strlen($projectRoot) + 1);
        $phar->addFile($file->getPathname(), $relativePath);
        $count++;
    }
    return $count;
};

$total = 0;
$classCount = $addDir($projectRoot . '/classes');
$total += $classCount;
echo "  Added classes/ ({$classCount} files)\n";

$toolCount = $addDir($projectRoot . '/tools');
$total += $toolCount;
echo "  Added tools/ ({$toolCount} files)\n";

$libCount = $addDir($projectRoot . '/libraries');
$total += $libCount;
echo "  Added libraries/ ({$libCount} files)\n";

// Add config sample
$phar->addFile($projectRoot . '/config/instances.json.sample', 'config/instances.json.sample');
$total++;

echo "  Added config/instances.json.sample\n";
echo "  Total: {$total} files\n";

// Create the stub — this is the entry point when the PHAR is executed
$stub = <<<'STUB'
#!/usr/bin/env php
<?php
/**
 * OPNsense MCP Server — PHAR Entry Point
 *
 * @package    OPNsenseMCP
 * @license    BSD-2-Clause
 */

Phar::mapPhar('opnsense-mcp.phar');

// Application constants required by EnchiladaHTTP
define('APPLICATION_NAME', 'OPNsenseMCP');
define('APPLICATION_VERSION', '1.0.0');
define('APPLICATION_DEBUG', false);
define('APPLICATION_USERAGENT', sprintf('%s/%s (PHAR; %s) PHP %s', APPLICATION_NAME, APPLICATION_VERSION, php_uname('s'), phpversion()));

// Autoloader
spl_autoload_register(function ($class) {
    // Namespaced classes (Mcp\*, OPNsense\*) — .class.php extension
    $prefixes = [
        'Mcp\\' => 'phar://opnsense-mcp.phar/classes/Mcp/',
        'OPNsense\\' => 'phar://opnsense-mcp.phar/classes/OPNsense/',
    ];
    foreach ($prefixes as $prefix => $baseDir) {
        if (strncmp($prefix, $class, strlen($prefix)) === 0) {
            $relativeClass = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.class.php';
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
    // Non-namespaced classes: tool classes (.php) and libraries (.class.php)
    if (!str_contains($class, '\\')) {
        $toolFile = 'phar://opnsense-mcp.phar/tools/' . $class . '.php';
        if (file_exists($toolFile)) {
            require $toolFile;
            return;
        }
        $libFile = 'phar://opnsense-mcp.phar/libraries/' . $class . '/' . $class . '.class.php';
        if (file_exists($libFile)) {
            require $libFile;
            return;
        }
    }
});

use Mcp\McpServer;
use OPNsense\InstanceManager;

// --- Configuration ---

$configPath = getenv('OPNSENSE_CONFIG') ?: null;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--config=')) {
        $configPath = substr($arg, 9);
    }
}

if ($configPath === null) {
    $candidates = [
        getcwd() . '/instances.json',
        getcwd() . '/config/instances.json',
        getenv('HOME') . '/.config/opnsense-mcp/instances.json',
        '/usr/local/etc/opnsense-mcp/instances.json',
    ];
    foreach ($candidates as $candidate) {
        if (file_exists($candidate)) {
            $configPath = $candidate;
            break;
        }
    }
}

if ($configPath === null || !file_exists($configPath)) {
    fwrite(STDERR, "[opnsense-mcp] ERROR: No configuration file found.\n");
    fwrite(STDERR, "  Set OPNSENSE_CONFIG environment variable or use --config=/path/to/instances.json\n");
    exit(1);
}

// --- Bootstrap ---

function debug(string $message): void
{
    fwrite(STDERR, "[opnsense-mcp] " . $message . "\n");
}

try {
    $manager = InstanceManager::fromFile($configPath);
} catch (\Exception $e) {
    fwrite(STDERR, "[opnsense-mcp] ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

$instanceCount = $manager->count();
debug("Loaded {$instanceCount} instance(s) from {$configPath} (default: {$manager->getDefault()})");

// --- Create MCP Server ---

$server = new McpServer('opnsense-mcp', '1.0.0');

// Register all tool classes
$toolClasses = [
    'BackupTools', 'DhcpTools', 'DiagnosticsTools', 'DnsTools',
    'FirewallTools', 'HaproxyTools', 'InstanceTools', 'InterfaceTools',
    'LogTools', 'NatTools', 'ServiceTools', 'SystemTools', 'VpnTools',
];
foreach ($toolClasses as $className) {
    if (class_exists($className)) {
        $server->register(new $className($manager));
        debug("Registered tools: {$className}");
    }
}

debug("MCP server started (stdio transport, PHAR)");

// --- Main Loop ---

while (($line = fgets(STDIN)) !== false) {
    $line = trim($line);
    if (empty($line)) {
        continue;
    }

    debug("Received: " . substr($line, 0, 200) . (strlen($line) > 200 ? '...' : ''));

    $request = json_decode($line, true);
    if ($request === null) {
        debug("Invalid JSON received");
        continue;
    }

    $response = $server->handleRequest($request);

    if (!empty($response)) {
        $output = json_encode($response, JSON_UNESCAPED_SLASHES);
        debug("Sending: " . substr($output, 0, 200) . (strlen($output) > 200 ? '...' : ''));
        fwrite(STDOUT, $output . "\n");
        fflush(STDOUT);
    }
}

debug("MCP server stopped");

__HALT_COMPILER();
STUB;

$phar->setStub($stub);
$phar->stopBuffering();

// Make executable
chmod($pharFile, 0755);

$size = filesize($pharFile);
$sizeKb = round($size / 1024, 1);
echo "\nBuilt: {$pharFile} ({$sizeKb} KB)\n";
echo "Test:  php {$pharFile} --config=/path/to/instances.json\n";
echo "  or:  OPNSENSE_CONFIG=/path/to/instances.json {$pharFile}\n";
