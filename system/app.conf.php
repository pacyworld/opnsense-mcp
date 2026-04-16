<?php
/**
 * OPNsense MCP Server Application Configuration
 * Enchilada Framework 3.0
 */

// Application Info
define('APPLICATION_NAME', 'OPNsenseMCP');
define('APPLICATION_VERSION', '1.0.0');
define('APPLICATION_WEBSITE', 'https://pacyworld.dev/pacyworld/opnsense-mcp');

// Directory Structure
define('APPLICATION_ROOT', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('APPLICATION_CONFDIR', APPLICATION_ROOT . 'config' . DIRECTORY_SEPARATOR);
define('APPLICATION_LIBDIR', APPLICATION_ROOT . 'libraries' . DIRECTORY_SEPARATOR);
define('APPLICATION_CLASSDIR', APPLICATION_ROOT . 'classes' . DIRECTORY_SEPARATOR);
define('APPLICATION_MODULEDIR', APPLICATION_ROOT . 'modules' . DIRECTORY_SEPARATOR);
define('APPLICATION_DEBUG', getenv('ENCHILADA_DEBUG_ENABLE'));
define('APPLICATION_USERAGENT', sprintf('%s/%s (%s; U; %s %s) PHP %s', APPLICATION_NAME, APPLICATION_VERSION, php_uname('s'), php_uname('s'), php_uname('r'), phpversion()));
define('APPLICATION_TEMPDIR', (getenv('ENCHILADA_TEMP_DIR') ?: APPLICATION_ROOT . 'temp' . DIRECTORY_SEPARATOR));
