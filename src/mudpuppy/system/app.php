<?php
defined('MUDPUPPY') or die('Restricted');
$__app = new App();
class App {
    private static $instance = null;
	private static $dbo = null;
    private static $exited = false;
    private static $completionHandlers = array();
    private static $pageController = null;

	public function __construct() {
        if (self::$instance)
		    throw new Exception('App is a static class; cannot instantiate.');
        self::$instance = $this;
	}

    public function __destruct() {
        if (!self::$exited)
            App::cleanExit();
    }

    /**
     * Called by Mudpuppy when the application starts up
     * @return bool
     */
    public static function initialize() {
        register_shutdown_function(function() { App::cleanExit(); });

        // buffer the output (see App::cleanExit() for why)
        ob_start();

		// Start the session
		Session::start();

        if (Config::$dbHost) {
            // Create database object
            self::$dbo = new Database();

            // Connect to database
            $connectSuccess = self::$dbo->connect(Config::$dbHost, Config::$dbDatabase, Config::$dbUser, Config::$dbPass);

            // Display log on failed connection
            if (!$connectSuccess) {
                if (Config::$debug) {
                    Log::displayFullLog();
                } else {
                    print 'Database Connection Error. Please contact your administrator or try again later.';
                    die();
                }
            }
        }


		// Refresh login, check for session expiration
		Security::refreshLogin();

		// Application specific startup goes here


        $controller = Controller::getController();
        if (!Security::hasPermissions($controller->getRequiredPermissions())) {
            App::abort(403);
        }
        $controller->processPost();
        self::$pageController = $controller;

        return true;
	}

    public static function getPageController() {
        return self::$pageController;
    }

    /**
     * Called from Security when a session has expired
     */
    public static function sessionExpired() {
        Security::logout();
        self::redirect();
    }


    /**
     * Add a message to the session
     * @param $title
     * @param string $text
     * @param string $type
     */
    public static function addMessage($title, $text="", $type="info") {
        $currMessages = & Session::get('messages', array());
        $currMessages[] = array('title'=>$title, 'text'=>$text, 'type'=>$type);
    }

    /**
     * Retrieve the messages from the session (also removes them from the session)
     * @return mixed
     */
    public static function readMessages() {
		$currMessages = Session::extract('messages', array());
		return $currMessages;
	}

    /**
     * add a handler to be called AFTER the connection and session are closed
     * @param {function} $handler
     */
    public static function addExitHandler($handler) {
        self::$completionHandlers[] = $handler;
    }

    /**
     * exit the app and write to the log if necessary
     */
    public static function cleanExit() {
        if (!self::$exited) {
            self::$exited = true;

            // if in debug mode and we don't have a database connection, display the log if necessary
            if (Config::$debug && !Config::$dbHost) {
                Log::write();
            }

            // flush and close connection
            $size = ob_get_length();
            header('Content-Encoding: none');
            header('Content-Length: '.$size);
            header('Connection: close');
            ob_end_flush();
            flush();

            // close session
            if (session_id())
                session_write_close();

            // perform registered callbacks
            foreach (self::$completionHandlers as $handler) {
                $handler();
            }

            // record errors to database
            Log::write();
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

    public static function redirect($absLocation='', $statusCode=302) {
        http_response_code($statusCode);

        if (substr($absLocation, 0, 1) == '/')
            $absLocation = substr($absLocation, 1);
        if (preg_match('#^https?\:\/\/#i', $absLocation))
            header('Location: '.$absLocation);
        else
            header('Location: '.App::getBaseURL().$absLocation);
        App::cleanExit();
    }

	/**
	 * Get the FQ base url of the app
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
        http_response_code($statusCode);
        if (file_exists("html/$statusCode.html"))
            require_once("html/$statusCode.html");
        else
            require_once('html/500.html');
        App::cleanExit();
    }
}

?>