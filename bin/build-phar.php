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

// Version from the application config (the stub is a heredoc, so the
// value is substituted below rather than interpolated here)
$versionSource = file_get_contents($projectRoot . '/system/app.conf.php');
preg_match("/define\\('APPLICATION_VERSION', '([^']+)'\\)/", $versionSource, $vMatch);
$appVersion = $vMatch[1] ?? '0.0.0';

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
    // Namespaced classes (OPNsense\*, EnchiladaMCP\*, Enchilada\Tortilla\*).
    // Tortilla ships plain .php files; the others use .class.php.
    $prefixes = [
        'OPNsense\\' => ['phar://opnsense-mcp.phar/classes/OPNsense/', '.class.php'],
        'EnchiladaMCP\\' => ['phar://opnsense-mcp.phar/libraries/EnchiladaMCP/', '.class.php'],
        'Enchilada\\Tortilla\\' => ['phar://opnsense-mcp.phar/libraries/Enchilada/Tortilla/', '.php'],
    ];
    foreach ($prefixes as $prefix => [$baseDir, $suffix]) {
        if (strncmp($prefix, $class, strlen($prefix)) === 0) {
            $relativeClass = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . $suffix;
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
    }
});

use EnchiladaMCP\Logger;
use EnchiladaMCP\McpServer;
use Enchilada\Tortilla\ComalEventLoop;
use Enchilada\Tortilla\StdioTransport;
use OPNsense\InstanceManager;

// --- Configuration ---

$configPath = getenv('OPNSENSE_CONFIG') ?: null;
$logPath = getenv('OPNSENSE_MCP_LOG') ?: null;
$logLevel = getenv('OPNSENSE_MCP_LOG_LEVEL') ?: 'debug';
// Stderr mirroring is opt-in. stdout is the protocol channel and stderr
// is only as reliable as the host's willingness to drain it: a host that
// captures stderr and never reads it will fill the pipe and stall the
// server (observed on Windows, where the stream cannot be made
// non-blocking). The log file is the durable diagnostic channel.
$stderrEnv = getenv('OPNSENSE_MCP_LOG_STDERR');
$logStderr = ($stderrEnv === false) ? false : (bool)$stderrEnv;
$ioMode = getenv('OPNSENSE_MCP_IO_MODE') ?: null;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--config=')) {
        $configPath = substr($arg, 9);
    }
    if (str_starts_with($arg, '--log=')) {
        $logPath = substr($arg, 6);
    }
    if (str_starts_with($arg, '--log-level=')) {
        $logLevel = substr($arg, 12);
    }
    if (str_starts_with($arg, '--io-mode=')) {
        $ioMode = substr($arg, 10);
    }
}

$logLevelValue = Logger::levelFromString($logLevel) ?? Logger::LEVEL_DEBUG;
$logger = new Logger($logPath, $logLevelValue, $logStderr, 'opnsense-mcp');

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

if ($logPath !== null && @file_put_contents($logPath, '', FILE_APPEND) === false) {
    fwrite(STDERR, "[opnsense-mcp] WARNING: Log file not writable: {$logPath}\n");
    $logger = new Logger(null, $logLevelValue, $logStderr, 'opnsense-mcp');
}

$logger->info(APPLICATION_NAME . ' ' . APPLICATION_VERSION . " starting (pid " . getmypid() . ", php " . PHP_VERSION . ')');
$logger->info("Log file: " . ($logPath ?? '(disabled — set OPNSENSE_MCP_LOG to enable)'));

try {
    $manager = InstanceManager::fromFile($configPath);
} catch (\Exception $e) {
    fwrite(STDERR, "[opnsense-mcp] ERROR: " . $e->getMessage() . "\n");
    $logger->error('Configuration load failed: ' . $e->getMessage());
    exit(1);
}

$instanceCount = $manager->count();
$logger->info("Loaded {$instanceCount} instance(s) from {$configPath} (default: {$manager->getDefault()})");

// --- Create MCP Server ---

$server = new McpServer('opnsense-mcp', APPLICATION_VERSION);
$server->setLogger($logger);

// Register all tool classes
$toolClasses = [
    'BackupTools', 'DhcpTools', 'DiagnosticsTools', 'DnsTools',
    'FirewallTools', 'HaproxyTools', 'InstanceTools', 'InterfaceTools',
    'LogTools', 'NatTools', 'ServiceTools', 'SystemTools', 'VpnTools',
];
foreach ($toolClasses as $className) {
    if (class_exists($className)) {
        $server->register(new $className($manager));
        $logger->debug("Registered tools: {$className}");
    }
}

$logger->info("MCP server started (stdio transport, PHAR)");

// --- Run ---

// The application owns the event loop (strict opt-in): shared with
// the stdio transport (reactor I/O) and the tools' HTTP path, where
// Tortilla\HttpClient parks the dispatch fiber on it during OPNsense
// API waits. null without vendored Comal: blocking I/O, progress flow
// via the HttpClient poll loop.
$loop = ComalEventLoop::create();
$manager->setHttpTransport($loop, $server->tick(...));

// Primitives in (handler, progress emitter), notifier back out —
// only the composition root knows both sides of the contract.
$transport = new StdioTransport($server->handleRequest(...), $server->tick(...));
$transport->setLogger($logger);
if ($loop !== null) {
    $transport->setLoop($loop);      // opts into reactor I/O
}
$server->setNotifier($transport->sendNotification(...));

// I/O mode override. 'auto' (the default) uses the Comal reactor on
// POSIX and blocking reads on Windows, where an anonymous stdin pipe
// cannot be polled. Override only when diagnosing transport behaviour.
if ($ioMode !== null) {
    try {
        $transport->setIoMode($ioMode);
    } catch (\InvalidArgumentException $e) {
        fwrite(STDERR, "[opnsense-mcp] ERROR: " . $e->getMessage() . "\n");
        exit(1);
    }
}

$transport->run();

$logger->info("MCP server stopped");

__HALT_COMPILER();
STUB;

$stub = str_replace("'1.0.0'", "'{$appVersion}'", $stub);

$phar->setStub($stub);
$phar->stopBuffering();

// Make executable
chmod($pharFile, 0755);

$size = filesize($pharFile);
$sizeKb = round($size / 1024, 1);
echo "\nBuilt: {$pharFile} ({$sizeKb} KB)\n";
echo "Test:  php {$pharFile} --config=/path/to/instances.json\n";
echo "  or:  OPNSENSE_CONFIG=/path/to/instances.json {$pharFile}\n";
