<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy;

defined('MUDPUPPY') or die('Restricted');

class App {
	private static $instance = null;
	private static $dbo = null;
	private static $exited = false;
	private static $exitHandlers = [];
	/** @var Controller|PageController */
	private static $pageController = null;
	/** @var Security */
	private static $security = null;
	private static $autoloadClasses = null;

	private function __construct() {
		if (self::$instance) {
			throw new MudpuppyException('App is a static class; cannot instantiate.');
		}
		self::$instance = $this;
	}

	public function __destruct() {
		if (!self::$exited) {
			App::cleanExit();
		}
	}

	/**
	 * Called by Mudpuppy when the application starts up.
	 */
	public static function start() {
		// Check for forwarded HTTPS (from load balancer)
		if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
			$_SERVER['HTTPS'] = 'on';
		}

		// Setup the random number generators
		function _createSeed() {
			list($uSec, $sec) = explode(' ', microtime());
			return (((int)$sec * 100000) + ((float)$uSec * 100000)) ^ Config::$randomSeedOffset;
		}

		srand(_createSeed());
		mt_srand(_createSeed());

		// Construct the single instance, which is necessary to automatically call App::cleanExit() on destruction
		new App();

		// Buffer the output (see App::cleanExit() for the reason)
		ob_start();

		if (!empty(Config::$appClass) && method_exists('\App\\' . Config::$appClass, 'configure')) {
			// Do any application-specific configuration, such as configuring dynamo db sessions
			forward_static_call(array('\App\\' . Config::$appClass, 'configure'));
		}

		// Setup the database if desired
		if (!empty(Config::$dbHost)) {
			// Create database object
			self::$dbo = new Database();

			// Connect to database
			$connectSuccess = self::$dbo->connect(Config::$dbHost, Config::$dbPort, Config::$dbDatabase, Config::$dbUser, Config::$dbPass);

			// Display log on failed connection
			if (!$connectSuccess) {
				if (Config::$debug) {
					Log::displayFullLog();
				} else {
					print 'Database Connection Error. Please contact your administrator or try again later.';
					die();
				}
			}
		} else {
			Config::$logToDatabase = false;
		}

		// Start the session
		Session::start();

		/** @var Security $security */
		$security = null;

		// The app class must be configured, otherwise we need to run the installer
		if (!empty(Config::$appClass)) {
			// Do any application-specific startup tasks
			forward_static_call(array('\App\\' . Config::$appClass, 'initialize'));

			// Get the security class
			$security = self::$security = forward_static_call(array('App\\' . Config::$appClass, 'getSecurity'));

			// Refresh login, check for session expiration
			$security->refreshLogin();
		} else if (!preg_match('#^/mudpuppy/(install|content)/#i', $_SERVER['PATH_INFO'])) {
			// Redirect to the installer if there's no configured app class (and we're not already in the installer)
			self::redirect('/mudpuppy/install/');
		}

		// Handle HTTP Basic Auth if needed
		if (file_exists('App/BasicAuth.json')) {
			$authenticatedRealms = Session::get('authenticatedRealms', []);
			foreach (json_decode(file_get_contents('App/BasicAuth.json'), true) as $realm => $authInfo) {
				$pathPattern = $authInfo['pathPattern'];
				if (preg_match($pathPattern, $_SERVER['PATH_INFO'])) {
					if (!in_array($realm, $authenticatedRealms) &&
						(!isset($_SERVER['PHP_AUTH_USER']) || !isset($authInfo['credentials'][$_SERVER['PHP_AUTH_USER']])
							|| !Security::verifyPassword($_SERVER['PHP_AUTH_PW'], $authInfo['credentials'][$_SERVER['PHP_AUTH_USER']]))
					) {
						header("WWW-Authenticate: Basic realm=\"$realm\"");
						header('HTTP/1.0 401 Unauthorized');
						Log::dontWrite();
						App::cleanExit(true);
					}
					$authenticatedRealms[] = $realm;
					Session::set('authenticatedRealms', $authenticatedRealms);
					break;
				}
			}
		}

		// Get the page controller and verify the user has permission to proceed
		self::$pageController = Controller::getController();
		if ($security != null && !$security->hasPermissions(self::$pageController->getRequiredPermissions())) {
			$reflectionApp = new \ReflectionClass('App\\' . Config::$appClass);
			if ($reflectionApp->hasMethod('permissionDenied')) {
				forward_static_call(array('App\\' . Config::$appClass, 'permissionDenied'));
			}
			throw new PermissionDeniedException();
		}

		// Process the request
		self::$pageController->processRequest();

		// If we got here, there is no request to process. Continue to load the page.
		if (!self::$pageController->validatePathOptions()) {
			throw new PageNotFoundException(get_class(self::$pageController) . ' says these PathOptions are not valid');
		}
	}

	/**
	 * @return Controller|PageController
	 */
	public static function getPageController() {
		return self::$pageController;
	}

	/**
	 * @return Security
	 */
	public static function getSecurity() {
		return self::$security;
	}

	/**
	 * Get the list of classes organized by namespace used during autoload
	 * Useful to determine if a class does not exist without causing a cache refresh
	 * @return null
	 */
	public static function getAutoloadClassList() {
		if (!self::$autoloadClasses) {
			self::$autoloadClasses = json_decode(file_get_contents('Mudpuppy/cache/autoload.json'), true);
		}
		return self::$autoloadClasses;
	}

	/**
	 * Check if a namespace should be within the autoload list
	 * @param $namespace
	 * @return bool
	 */
	public static function isAutoloadNamespace($namespace) {
		// clean the namespace to ensure it starts with \ and doesn't end with one
		if (substr($namespace, 0, 1) != '\\') {
			$namespace = '\\' . $namespace;
		}
		if (substr($namespace, -1, 1) == '\\') {
			$namespace = substr($namespace, 0, -1);
		}

		foreach (self::getAutoloadFolders() as $baseNamespace => $folder) {
			if (is_numeric($baseNamespace)) {
				continue;
			}

			if (substr($folder, -1, 1) == '*') {
				// if the folder ends in an asterisk, just try to match the base namespace
				if (strncasecmp($namespace, $baseNamespace, strlen($baseNamespace)) == 0) {
					return true;
				}
			} else {
				// otherwise the full namespace must match
				if (strcasecmp($namespace, $baseNamespace) == 0) {
					return true;
				}
			}
		}

		return false;
	}

	private static function getAutoloadFolders() {
		static $autoloadFolders = null;
		if (!$autoloadFolders) {
			$autoloadFolders = array_merge(Config::$autoloadFolders, array(
				'\\Mudpuppy' => 'Mudpuppy/',
				'\\Mudpuppy\\Model' => 'Mudpuppy/Model/',
				'\\Mudpuppy\\Admin' => 'Mudpuppy/Admin/*',
				'\\App' => 'App/*',
				'\\Model' => 'Model/*'
			));
		}
		return $autoloadFolders;
	}

	/**
	 * reload the autoload cache by scanning the filesystem
	 * returns false if it has already reloaded or if we are not in debug
	 * @return bool
	 */
	public static function refreshAutoloadClassList() {
		static $reloaded = false;
		if ($reloaded || Config::$debug == false) {
			return false;
		}
		$reloaded = true;

		$classes = array();

		function _parseFiles(&$classes, &$files, $folder) {
			foreach ($files as $file) {
				$class = str_replace('/', '\\', strtolower(File::getTitle($file, false)));
				if ($class) {
					$classes[strtolower($class)] = $folder . $file;
				}
			}
		}

		$autoloadFolders = self::getAutoloadFolders();

		// global/non-namespaced classes should be listed without a key or with an integer key
		$globalFolders = array_intersect_key(array_filter(array_keys($autoloadFolders), 'is_numeric'), $autoloadFolders);

		// Standard non-namespaced classes, optionally in a directory structure (if * is specified for recursive loading)
		foreach ($globalFolders as $folder) {
			if (substr($folder, -1) == '*') {
				$folder = substr($folder, 0, -1);
				$files = File::getFilesRecursive($folder, '#.*\.php$#');
			} else {
				$files = File::getFiles($folder, '#.*\.php$#');
			}
			_parseFiles($classes, $files, $folder);
		}

		// Namespaced classes are in the form namespace => folder
		$namespaceFolders = array_diff_key($autoloadFolders, $globalFolders);

		foreach ($namespaceFolders as $folder) {
			if (substr($folder, -1) == '*') {
				$folder = substr($folder, 0, -1);
				$paths = array_merge([''], File::getFoldersRecursive($folder));
			} else {
				$paths = [''];
			}

			if ($folder && substr($folder, -1) != '/') {
				$folder .= '/';
			}

			foreach ($paths as $path) {
				$path = $folder . $path;
				if (substr($path, -1) == '/') {
					$path = substr($path, 0, -1);
				}
				$nsClasses = array();
				$files = File::getFiles($path, '#.*\.php$#');
				_parseFiles($nsClasses, $files, $path . '/');
				$classes[strtolower(str_replace('/', '\\', $path))] = $nsClasses;
			}
		}

		self::$autoloadClasses = $classes;
		file_put_contents('Mudpuppy/cache/autoload.json', json_encode($classes, JSON_PRETTY_PRINT));

		Log::error('autoload.json had to be reloaded. This is expected if you recently modified your application structure or added a lib.');

		return true;
	}

	/**
	 * Add a message to the session
	 * @param string $title
	 * @param string $text
	 * @param string $type
	 */
	public static function addMessage($title, $text = '', $type = 'info') {
		$curMessages = & Session::get('messages', array());
		$curMessages[] = array('title' => $title, 'text' => $text, 'type' => $type);
	}

	/**
	 * Retrieve the messages from the session (also removes them from the session)
	 * @return mixed
	 */
	public static function readMessages() {
		return Session::extract('messages', array());
	}

	/**
	 * Add a handler to be called AFTER the connection and session are closed
	 * @param {function} $handler
	 */
	public static function addExitHandler($handler) {
		self::$exitHandlers[] = $handler;
	}

	/**
	 * Exit the app and write to the log if necessary
	 * @param bool $suppressAdditionalOutput if true, log will not be appended to the output in any case
	 */
	public static function cleanExit($suppressAdditionalOutput = false) {

		// Make sure we only do this once, as it could potentially be triggered multiple times during termination
		if (!self::$exited) {
			self::$exited = true;

			// If in debug mode and we don't have any way of storing logs, display the log if necessary. Needs to happen
			// here before the connection is closed.
			if (Config::$debug && !Log::hasStorageOption() && !$suppressAdditionalOutput) {
				Log::write();
			}

			// Flush and close connection
			$size = ob_get_length();
			header('Content-Encoding: none');
			header('Content-Length: ' . $size);
			header('Connection: close');
			ob_end_flush();
			flush();

			// close session
			if (session_id()) {
				try {
					session_write_close();
				} catch (MudpuppyException $e) {
					Log::exception($e);
				}
			}

			// perform registered callbacks
			foreach (self::$exitHandlers as $handler) {
				if (is_callable($handler)) {
					call_user_func($handler);
				} else {
					$handler();
				}
			}

			// Record errors to database (or S3 or local file, depending on configuration)
			Log::write($suppressAdditionalOutput);
		}
		// Then terminate execution
		exit();
	}

	/**
	 * Get the static database object
	 * @return Database
	 */
	public static function getDBO() {
		return self::$dbo;
	}

	/**
	 * Performs an HTTP header redirect to the specified URL.
	 *
	 * @param string $absLocation can be a fully qualified URL or an absolute path on the server
	 * @param int $statusCode
	 */
	public static function redirect($absLocation = '', $statusCode = 302) {
		http_response_code($statusCode);

		if (substr($absLocation, 0, 1) == '/') {
			$absLocation = substr($absLocation, 1);
		}
		if (preg_match('#^https?\:\/\/#i', $absLocation)) {
			header('Location: ' . $absLocation);
		} else {
			header('Location: ' . App::getBaseURL() . $absLocation);
		}
		App::cleanExit();
	}

	/**
	 * Get the fully qualified base url of the app
	 */
	public static function getBaseURL() {
		$url = 'http';
		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] != 'off') {
			$url .= 's';
		}
		$url .= '://' . $_SERVER['HTTP_HOST'] . '/';
		return $url;
	}

	public static function getCurrentUrl($includeParams = true) {
		$pageURL = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
		$uri = $_SERVER["REQUEST_URI"];
		if (!$includeParams) {
			$index = strpos($_SERVER["REQUEST_URI"], '?');
			if ($index !== false) {
				$uri = substr($_SERVER['REQUEST_URI'], 0, $index);
			}
		}

		if ($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443") {
			$pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $uri;
		} else {
			$pageURL .= $_SERVER["SERVER_NAME"] . $uri;
		}
		return $pageURL;
	}

	public static function abort($statusCode) {
		// clear any content that may have already been output
		ob_end_clean();
		ob_start();

		http_response_code($statusCode);
		if (file_exists("content/html/$statusCode.html")) {
			require_once("content/html/$statusCode.html");
		} else {
			require_once('content/html/500.html');
		}
		App::cleanExit();
	}

}

?>