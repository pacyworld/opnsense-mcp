<?php
/**
 * OPNsense MCP Server Bootstrap
 * Enchilada Framework 3.0
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
date_default_timezone_set('UTC');

require_once("system/app.conf.php"); //Application Constants
@include_once("config/local.conf.php"); //User Made Application Options
require_once('system/autoload.inc.php'); // Libraries and Classes autoloader

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

/* 
* Dynamic Component Loader
* 
* Auto include anything in the current directory that ends with '.inc.php' 
* except for this script.
*/
$component_loader = function() {
	$components = array();
	$includes_directory = dir(__DIR__);
	while (false !== ($entry = $includes_directory->read())) {
		$file = realpath(__DIR__ . DIRECTORY_SEPARATOR . $entry);
		if(is_file($file) && $file != __FILE__ && $entry != 'settings.inc.php' && substr($entry, -strlen('.inc.php')) === '.inc.php'){
			$components[] = $file;
		}
	}
	$includes_directory->close();
	return $components;
};

// Load the Settings Component
@include 'includes/settings.inc.php';
// Load Components
foreach($component_loader() as $include_file){ include $include_file; }
// Clean Up
unset($component_loader);
