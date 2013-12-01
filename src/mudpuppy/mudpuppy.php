<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================
define('MUDPUPPY', true);
define('MUDPUPPY_VERSION', '2.0.0 beta');

// Switch to root directory
chdir(__DIR__);
chdir('../');

// Report all errors
error_reporting(E_ALL);

// Add in a couple compatibility fixes if needed
if (!function_exists('http_response_code')) {
	require_once('mudpuppy/lib/compatibility/httpstatuscode.php');
}
if (!defined('PASSWORD_DEFAULT')) {
	require_once('mudpuppy/lib/compatibility/password.php');
}

// Load the configuration, logging system, and file system helper
require_once('app/Config.php');
require_once('mudpuppy/lib/log.php');
require_once('mudpuppy/lib/file.php');

// Must have PHP >= 5.4.0
if (version_compare(PHP_VERSION, '5.4.0', '<')) {
	if (Config::$debug) {
		echo "PHP 5.4.0 or greater is required";
	}
	http_response_code(500);
	die();
}

// Setup the random number generator
mt_srand((microtime(true) * Config::$randomSeedOffset & 0xFFF) ^ Config::$randomSeedOffset);

// Disable error reporting and displaying when not in debug
if (!Config::$debug) {
	ini_set('display_errors', '0');
	error_reporting(0);
} else {
	ini_set('display_errors', '1');
}

// Set the default timezone
date_default_timezone_set(Config::$timezone);

// Activate assert and make it quiet
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_WARNING, 0);
assert_options(ASSERT_QUIET_EVAL, 1);

// Setup error/assertion handlers (functions at bottom of file)
assert_options(ASSERT_CALLBACK, '_assert_handler');
set_exception_handler('exception_handler');
set_error_handler('error_handler');
register_shutdown_function('shutdown_handler');

// Register the autoloader function
spl_autoload_register('MPAutoLoad');

// Pre-load the DebugLog and Request classes to ensure they're available during shutdown
MPAutoLoad('DebugLog');
MPAutoLoad('Request');

// Also pre-load the exceptions, which are kind of special because they're combined in a single file
MPAutoLoad('MudpuppyException');

// Initialize the application
App::initialize();

//======================================================================================================================
// Automatic class loading functions
//======================================================================================================================

function MPAutoLoad($className) {
	static $classes = null;
	static $reloadedCache = false;
	static $cacheDir = 'mudpuppy/cache';
	if (!file_exists($cacheDir)) {
		mkdir($cacheDir);
	}
	$classCacheFile = "$cacheDir/ClassLocationCache.json";

	$parts = explode('\\', $className);
	$class = strtolower($parts[sizeof($parts) - 1]);
	array_pop($parts);
	$namespace = strtolower(implode('/', $parts));
	if (!empty($namespace)) {
		$namespace .= '/';
	}

	// Load class location cache
	if (is_null($classes) && file_exists($classCacheFile)) {
		$classes = json_decode(file_get_contents($classCacheFile), true);
	}

	// Automatically load aws.phar if we're trying to use a class from the AWS SDK
	if (sizeof($parts) > 0 && in_array(strtolower($parts[0]), array('aws', 'guzzle', 'symfony', 'doctrine', 'psr', 'monolog'))) {
		// Both mudpuppy and aws.phar must be in the root of the web server
		require_once($_SERVER['DOCUMENT_ROOT'] . '/aws.phar');

		// The SDK has its own autoloader, so we'll bail and let that handle it
		return false;
	}

	// Refresh the class cache if we can't find the class
	if ((($namespace && (!isset($classes[$namespace]) || !isset($classes[$namespace][$class]) || !file_exists($classes[$namespace][$class])))
			|| (!$namespace && (!isset($classes[$class]) || !file_exists($classes[$class])))) && !$reloadedCache && Config::$debug
	) {
		File::putContents($classCacheFile, _refreshAutoLoadClasses($classes));
		$reloadedCache = true;
	}

	// Try to locate the class file
	$file = null;
	if ($namespace && isset($classes[$namespace]) && isset($classes[$namespace][$class]) && file_exists($classes[$namespace][$class])) {
		$file = $classes[$namespace][$class];
	} else if (isset($classes[$class]) && file_exists($classes[$class])) {
		$file = $classes[$class];
	}

	// And load it if found
	if ($file) {
		require_once($file);
		return true;
	}

	// We failed, this class is not locatable
	return false;
}

function _refreshAutoLoadClasses(&$classes) {
	Log::add('Refreshing auto-load cache');
	$classes = array();

	// Standard non-namespaced classes, optionally in a directory structure (if * is specified for recursive loading)
	$folders = array_merge(Config::$autoloadFolders, array('dataobjects/', 'mudpuppy/dataobjects/', 'mudpuppy/admin/', 'mudpuppy/lib/'));
	foreach ($folders as $folder) {
		$recursive = false;
		if (substr($folder, -1) == '*') {
			$folder = substr($folder, 0, -1);
			$recursive = true;
		}
		$files = File::getFileList($folder, $recursive, true, false, '#.*\.php$#');
		_ralc_parseFiles($classes, $files, $folder);
	}

	// Namespaced classes in a directory structure
	$folders = array('app/');
	foreach ($folders as $folder) {
		$paths = array_merge([''], File::getFileList($folder, true, false, true));
		foreach ($paths as $path) {
			$path = $folder . $path;
			$nsClasses = array();
			$files = File::getFileList($path, false, true, false, '#.*\.php$#');
			_ralc_parseFiles($nsClasses, $files, $path);
			$classes[$path] = $nsClasses;
		}
	}

	return json_encode($classes, JSON_PRETTY_PRINT);
}

function _ralc_parseFiles(&$classes, &$files, $folder) {
	foreach ($files as $file) {
		$class = strtolower(File::getTitle($file, false));
		if ($class) {
			$classes[$class] = $folder . $file;
		}
	}
}

//======================================================================================================================
// Error handling functions
//======================================================================================================================

// Assertion handler function
function _assert_handler($file, $line, $code) {
	if (Config::$debug) {
		throw(new Exception("Assertion failed in file $file($line).\nCode: $code"));
	}
}

// Error handler function
function error_handler($errNo, $errStr, $errFile, $errLine) {
	// Define an associative array of error strings. In reality, the only entries we should consider are:
	// E_WARNING, E_NOTICE, E_USER_ERROR, E_USER_WARNING and E_USER_NOTICE
	$errorType = array(
		E_ERROR => 'Error',
		E_WARNING => 'Warning',
		E_PARSE => 'Parsing Error',
		E_NOTICE => 'Notice',
		E_CORE_ERROR => 'Core Error',
		E_CORE_WARNING => 'Core Warning',
		E_COMPILE_ERROR => 'Compile Error',
		E_COMPILE_WARNING => 'Compile Warning',
		E_USER_ERROR => 'User Error',
		E_USER_WARNING => 'User Warning',
		E_USER_NOTICE => 'User Notice',
		E_STRICT => 'Runtime Notice',
		E_RECOVERABLE_ERROR => 'Catchable Fatal Error'
	);

	// Handle the error
	$err = $errorType[$errNo] . ": $errStr in $errFile ($errLine)";
	switch ($errNo) {
	case E_ERROR:
	case E_PARSE:
	case E_CORE_ERROR:
	case E_COMPILE_ERROR:
	case E_USER_ERROR:
		// For serious errors, use the exception handler
		exception_handler(new Exception($err));
		break;
	default:
		// Otherwise, just log it
		Log::error($err);
	}

	// Don't execute PHP's internal error handler
	return true;
}

// Exception handler function
function exception_handler(Exception $exception) {
	Log::exception($exception);
	if (Config::$debug) {
		if (!empty(Config::$dbHost)) {
			Log::displayFullLog();
		}
		App::cleanExit();
	} else {
		$statusCode = 500;
		if ($exception instanceof MudpuppyException) {
			$statusCode = $exception->getCode();
		}
		App::abort($statusCode);
	}
}

// Shutdown handler to make sure we write out to the log or display any serious errors
function shutdown_handler() {
	$error = error_get_last();
	if ($error !== null) {
		Log::error('SHUTDOWN: ' . $error['file'] . '(' . $error['line'] . ') ' . $error['message']);
		if (Config::$debug) {
			if (!empty(Config::$dbHost)) {
				Log::displayFullLog();
			}
			App::cleanExit();
		} else {
			App::abort(500);
		}
	}
}

?>