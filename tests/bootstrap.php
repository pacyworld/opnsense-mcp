<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package pacyworld/opnsense-mcp
 */

chdir(dirname(__DIR__));
require_once __DIR__ . '/../system/app.conf.php';
require_once __DIR__ . '/../system/autoload.inc.php';

// Tool classes (no namespace, in tools/ directory)
spl_autoload_register(function ($class) {
    if (str_contains($class, '\\')) {
        return;
    }
    $toolFile = APPLICATION_ROOT . 'tools/' . $class . '.php';
    if (file_exists($toolFile)) {
        require $toolFile;
    }
});
