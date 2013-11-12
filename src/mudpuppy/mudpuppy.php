<?php
define('MUDPUPPY', true);
define('MUDPUPPY_VERSION', '1.0.0');


// switch to root directory
chdir(__DIR__);
chdir('../');

error_reporting(E_ALL);

if (!function_exists('http_response_code'))
    include_once('mudpuppy/lib/compatibility/httpstatuscode.php');

if (!defined('PASSWORD_DEFAULT'))
    include_once('mudpuppy/lib/compatibility/password.php');

// required for exceptions & config options
require_once('lib/log.php');

if (!include_once('mudpuppy/config.php')) {
    if (Config::$debug) {
    	echo 'Cannot start application! Config file is missing.';
    }
    http_response_code(500);
    die();
}

if (version_compare(PHP_VERSION, '5.4.0', '<')) {
    if (Config::$debug) {
        echo "PHP 5.4.0 or greater is required";
    }
    http_response_code(500);
    die();
}

mt_srand((microtime(true) * Config::$randomSeedOffset & 0xFFF) ^ Config::$randomSeedOffset);

// disable error reporting and displaying when not in debug
if (!Config::$debug) {
    ini_set('display_errors', '0');
	error_reporting(0);
} else {
	ini_set('display_errors', '1');
}

date_default_timezone_set(Config::$timezone);


// Activate assert and make it quiet
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_WARNING, 0);
assert_options(ASSERT_QUIET_EVAL, 1);

// Set up error/assertion handlers (functions at bottom of file)
assert_options(ASSERT_CALLBACK, '_assert_handler');
set_exception_handler('exception_handler');
set_error_handler('error_handler');
register_shutdown_function('shutdown_handler');

// required for autoload
require_once('lib/file.php');

/////////////////////////////////////
// autoload classes on demand
/////////////////////////////////////
function MPAutoLoad($className) {
	static $classes = null;
    static $reloadedCache = false;
	$classcachefile = 'mudpuppy/cache/classlocationcache.php';

	$parts = explode('\\', $className);
	$class = strtolower($parts[sizeof($parts) - 1]);
	array_pop($parts);
	$namespace = strtolower(implode('/', $parts));

	if (is_null($classes)) {
		// load class location cache
		if (file_exists($classcachefile)) {
			include($classcachefile);
		}
	}


    // first check if it is part of a lib with its own autoloader
    if (strncasecmp($class, 'PHPExcel_', 9) == 0) {
        return false;
    }

    if (sizeof($parts) > 0 && in_array(strtolower($parts[0]), array('aws', 'guzzle', 'symfony', 'doctrine', 'psr', 'monolog'))) {
        // aws.phar must be in the root of the web server
        // mudpuppy should also be running from the root
        require_once($_SERVER['DOCUMENT_ROOT'].'/aws.phar');
        return false;
    }


    if (($namespace && (!isset($classes[$namespace]) || !isset($classes[$namespace][$class]) || !file_exists($classes[$namespace][$class])))
		|| (!$namespace && (!isset($classes[$class]) || !file_exists($classes[$class])))
	) {
		// can't find the class, refresh class list
        if (!$reloadedCache && Config::$debug) {
            $code = _refreshAutoLoadClasses($classes);
            File::putContents($classcachefile, $code);
            $reloadedCache = true;
        }
	}

	$file = null;
	if ($namespace && isset($classes[$namespace]) && isset($classes[$namespace][$class]) && file_exists($classes[$namespace][$class])) {
		$file = $classes[$namespace][$class];
	} else if (isset($classes[$class]) && file_exists($classes[$class])) {
		$file = $classes[$class];
	}

	if ($file) {
		require_once($file);
		return true;
	}

	// we failed, this class is not locateable
	//exception_handler(new Exception("failed to locate class: $className"));
	return false;
}

spl_autoload_register('MPAutoLoad');

function _refreshAutoLoadClasses(&$classes) {
	Log::add("Refreshing auto-load cache");
	$classes = array();

	// note, modules do not need to be cached as they are loaded on demand without __autoload()
	// also, if a model has the same class name as a class in the library or system,
	// the library or system class will take precedence

	$folders = array_merge(array('mudpuppy/dataobjects/', 'controllers/*', 'mudpuppy/system/', 'mudpuppy/lib/'), Config::$autoloadFolders);
	foreach ($folders as $folder) {
        $recursive = false;
        if (substr($folder, -1) == '*') {
            $folder = substr($folder, 0, -1);
            $recursive = true;
        }
		$files = File::getFileList($folder, $recursive, true, false, '#.*\.php$#');
		_ralc_parsefiles($classes, $files, $folder);
	}

	// assume all folders inside dataobjects are a namespace
	$folder = 'mudpuppy/dataobjects/';
	$namespaces = File::getFileList('mudpuppy/dataobjects/', false, false, true);
	foreach ($namespaces as $namespace) {
		$nsClasses = array();
		$files = File::getFileList($folder . $namespace, false, true, false, '#.*\.php$#');
		_ralc_parsefiles($nsClasses, $files, $folder . $namespace . '/');
		$classes[$namespace] = $nsClasses;
	}

	$code = "<?php \$classes = " . var_export($classes, true) . "; ?>";
	return $code;
}

function _ralc_parsefiles(&$classes, &$files, $folder) {
	foreach ($files as $file) {
		$class = strtolower(File::getTitle($file, false));
		if ($class) {
			$classes[$class] = $folder . $file;
		}
	}
}

// need to pre-load ErrorLog data object in order to write to DB during a shutdown
MPAutoLoad('ErrorLog');

App::initialize();

// some error handling

// assertion handling
function _assert_handler($file, $line, $code) {
	if (Config::$debug) {
		throw(new Exception("Assertion failed in file $file($line).\nCode: $code"));
	}
}

// error handler function
function error_handler($errNo, $errStr, $errFile, $errLine) {
	// timestamp for the error entry
	$dt = date("Y-m-d H:i:s (T)");

	// define an assoc array of error string
	// in reality the only entries we should
	// consider are E_WARNING, E_NOTICE, E_USER_ERROR,
	// E_USER_WARNING and E_USER_NOTICE
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
	// set of errors for which a var trace will be saved
	$user_errors = array(E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE);

	$err = $errorType[$errNo] . ": $errStr in $errFile ($errLine)";
	switch ($errNo) {
	case E_ERROR:
	case E_PARSE:
	case E_CORE_ERROR:
	case E_COMPILE_ERROR:
	case E_USER_ERROR:
		exception_handler(new Exception($err));
		break;
	default:
		Log::error($err);
	}

	// Don't execute PHP internal error handler
	return true;
}

// exception handler
function exception_handler(Exception $exception) {
	$error_data = array('type' => 'Exception', 'errno' => $exception->getCode(), 'message' => $exception->getMessage(),
		'file' => $exception->getFile(), 'line' => $exception->getLine(), 'trace' => $exception->getTraceAsString());
	Log::error('An exception has occurred in ' . $exception->getFile() . '(' . $exception->getLine() . "). \nMessage: " . $exception->getMessage());

	if (Config::$debug) {
		Log::write();
	} else {
		/* redirect to error page */
		print('A Fatal Error Occurred.');
	}

	App::cleanExit();
}

// the shutdown handler to make sure we write out to the log or display any serious errors
function shutdown_handler() {
	$error = error_get_last();
	if ($error !== null && Config::$debug) {
		print "SHUTDOWN in file:" . $error['file'] . "(" . $error['line'] . ") - Message:" . $error['message'] . '<br />' . PHP_EOL;
		Log::displayFullLog();
	}
    Log::write();
}

?>