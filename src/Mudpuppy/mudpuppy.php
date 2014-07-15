<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy;

define('MUDPUPPY', true);
define('MUDPUPPY_VERSION', '2.0.0 beta');

// Switch to root directory
chdir(__DIR__);
chdir('../');

// Report all errors
error_reporting(E_ALL);

// Add in compatibility fixes if needed
if (!defined('PASSWORD_DEFAULT')) {
	require_once('Mudpuppy/Compatibility/password.php');
}

// Load the configuration, logging system, file system helper, and Database (required by Log), and App
require_once('Mudpuppy/Log.php');
require_once('Mudpuppy/Config.php');
require_once('Mudpuppy/File.php');
require_once('Mudpuppy/App.php');
require_once('Mudpuppy/Database.php');

// Load the configuration overrides
Config::load('App/Config.json');

// Initialize the log
Log::initialize();

// Must have PHP >= 5.4.0
if (version_compare(PHP_VERSION, '5.4.0', '<')) {
	if (Config::$debug) {
		echo "PHP 5.4.0 or greater is required";
	}
	http_response_code(500);
	die();
}

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
assert_options(ASSERT_CALLBACK, '\Mudpuppy\assert_handler');
set_exception_handler('\Mudpuppy\exception_handler');
set_error_handler('\Mudpuppy\error_handler');
register_shutdown_function('\Mudpuppy\shutdown_handler');

// Register the autoloader function
spl_autoload_register('\Mudpuppy\MPAutoLoad');

// Pre-load the DebugLog and Request classes to ensure they're available during shutdown
MPAutoLoad('\Mudpuppy\Model\DebugLog');
MPAutoLoad('\Mudpuppy\Request');

// Also pre-load the exceptions, which are kind of special because they're combined in a single file
MPAutoLoad('\Mudpuppy\MudpuppyException');

// Initialize the application
App::start();

//======================================================================================================================
// Automatic class loading
//======================================================================================================================

function MPAutoLoad($className) {
	if (substr($className, 0, 1) == '\\') {
		$className = substr($className, 1);
	}

	// break className into namespace parts
	$parts = explode('\\', $className);
	$class = strtolower(array_pop($parts));
	if (strpos($class, '_') !== false) {
		$parts = array_merge($parts, explode('_', $class));
		$class = strtolower(array_pop($parts));
	}
	$namespace = strtolower(implode('\\', $parts));

	// Load class locations
	$classes = App::getAutoloadClassList();

	// Automatically load aws.phar if we're trying to use a class from the AWS SDK
	if (sizeof($parts) > 0 && in_array(strtolower($parts[0]), array('aws', 'guzzle', 'symfony', 'doctrine', 'psr', 'monolog'))) {
		// Both mudpuppy and aws.phar must be in the root of the web server
		require_once($_SERVER['DOCUMENT_ROOT'] . '/aws.phar');

		// The SDK has its own autoloader, so we'll bail and let that handle it
		return false;
	}

	// Try to locate the class file
	$file = null;
	if ($namespace && isset($classes[$namespace]) && isset($classes[$namespace][$class]) && file_exists($classes[$namespace][$class])) {
		$file = $classes[$namespace][$class];
	} else if (isset($classes[$class]) && file_exists($classes[$class])) {
		$file = $classes[$class];
	}

	if (!$file || !file_exists($file)) {
		if (App::isAutoloadNamespace($namespace) && App::refreshAutoloadClassList()) {
			$classes = App::getAutoloadClassList();
			$file = null;

			// try to locate the class again
			if ($namespace && isset($classes[$namespace]) && isset($classes[$namespace][$class]) && file_exists($classes[$namespace][$class])) {
				$file = $classes[$namespace][$class];
			} else if (isset($classes[$class]) && file_exists($classes[$class])) {
				$file = $classes[$class];
			}
		}
	}

	// And load it if found
	if ($file) {
		require_once($file);
		return true;
	}

	// We failed, this class is not locatable
	return false;

}


//======================================================================================================================
// Error handling functions
//======================================================================================================================

// Assertion handler function
function assert_handler($file, $line, $code) {
	if (Config::$debug) {
		throw new MudpuppyException("Assertion failed in file $file($line).\nCode: $code");
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
		E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
		E_DEPRECATED => 'Deprecated'
	);

	// Handle the error
	if (isset($errorType[$errNo])) {
		$err = $errorType[$errNo] . ": $errStr in $errFile ($errLine)";
	} else {
		$err = 'error #$errNo' . ": $errStr in $errFile ($errLine)";
	}
	switch ($errNo) {
	case E_ERROR:
	case E_PARSE:
	case E_CORE_ERROR:
	case E_COMPILE_ERROR:
	case E_USER_ERROR:
		// For serious errors, use the exception handler
		exception_handler(new \Exception($err));
		break;
	default:
		// Otherwise, just log it
		Log::error($err);
	}

	// Don't execute PHP's internal error handler
	return true;
}

// Exception handler function
function exception_handler(\Exception $exception) {
	Log::exception($exception);

	$statusCode = 500;
	if ($exception instanceof MudpuppyException) {
		$statusCode = $exception->getCode();
	}
	App::abort($statusCode);
}

// Shutdown handler to make sure we write out to the log or display any serious errors
function shutdown_handler() {
	$error = error_get_last();
	if ($error !== null) {
		Log::error('SHUTDOWN: ' . $error['file'] . '(' . $error['line'] . ') ' . $error['message']);

		// clear any content that may have already been output
		ob_end_clean();
		ob_start();

		http_response_code(500);

		// during shutdown we can't load external files, so we can't display the normal 500 error message
		print 'Internal Server Error';

		if (Config::$debug) {
			Log::displayFullLog();
		}

		App::cleanExit(true);
	}
}

?>