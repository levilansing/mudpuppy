<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy;

use App\Config;

defined('MUDPUPPY') or die('Restricted');

class App {
	private static $instance = null;
	private static $dbo = null;
	private static $exited = false;
	private static $completionHandlers = array();
	/** @var Controller|PageController */
	private static $pageController = null;
	/** @var Security */
	private static $security = null;

	public function __construct() {
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

		// Start the session
		Session::start();

		// Setup the database if desired
		if (Config::$dbHost) {
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

		// Do any application-specific startup tasks
		forward_static_call(array('App\\' . Config::$appClass, 'initialize'));

		/** @var Security $security */
		$security = self::$security = forward_static_call(array('App\\' . Config::$appClass, 'getSecurity'));

		// Refresh login, check for session expiration
		$security->refreshLogin();

		// Handle HTTP Basic Auth if needed
		foreach (json_decode(file_get_contents('App/BasicAuth.json'), true) as $realm => $authInfo) {
			$pathPattern = $authInfo['pathPattern'];
			if (preg_match($pathPattern, $_SERVER['PATH_INFO'])) {
				if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($authInfo['credentials'][$_SERVER['PHP_AUTH_USER']])
					|| !$security->verifyPassword($_SERVER['PHP_AUTH_PW'], $authInfo['credentials'][$_SERVER['PHP_AUTH_USER']])) {
					header("WWW-Authenticate: Basic realm=\"$realm\"");
					header('HTTP/1.0 401 Unauthorized');
					Log::dontWrite();
					App::cleanExit(true);
				}
				break;
			}
		}

		// Get the page controller and verify the user has permission to proceed
		self::$pageController = Controller::getController();
		if (!$security->hasPermissions(self::$pageController->getRequiredPermissions())) {
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
			throw new PageNotFoundException("PathOptions are not valid");
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
		self::$completionHandlers[] = $handler;
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
				session_write_close();
			}

			// perform registered callbacks
			foreach (self::$completionHandlers as $handler) {
				$handler();
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
		$url .= '://' . $_SERVER['HTTP_HOST'];
		if ($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) {
			$url .= ":$_SERVER[SERVER_PORT]";
		}
		$url .= '/';
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