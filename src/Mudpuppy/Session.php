<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy;

defined('MUDPUPPY') or die('Restricted');

/*
* class: Session
* contains simplified session and flash functions
* flash data is only stored between pages.	it is only valid for 1 request/page change.
*/
class Session {
	protected static $sessionHash;
	protected static $_data;

	function __construct() {
		throw new \Exception('Session is a static class; cannot instantiate.');
	}

	/**
	 * to be called by app initialization
	 */
	static function start() {
		session_start();
		if (isset($_SERVER['HTTP_HOST']) || isset($_SERVER['SERVER_NAME'])) {
			self::$sessionHash = (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']);		// add application name
		} else {
			self::$sessionHash = 'noserver-' . Config::$appTitle;
		}
		self::loadSession();
	}

	private static function loadSession() {
		if (!isset($_SESSION[self::$sessionHash])) {
			$_SESSION[self::$sessionHash] = array();
		}

		Session::$_data =& $_SESSION[self::$sessionHash];

		// delete expired temp variables
		if (!isset(Session::$_data['_temp'])) {
			Session::$_data['_temp'] = array();
		}

		foreach (Session::$_data['_temp'] as $k => $t) {
			if ($t['exp'] <= time()) {
				unset(Session::$_data['_temp'][$k]);
			}
		}
	}

	/**
	 * clear the entire session variable for this application
	 */
	static function reset() {
		unset($_SESSION[self::$sessionHash]);
		$_SESSION[self::$sessionHash] = array();

		// reload session
		self::loadSession();
	}

	//////////////////////////////
	// session variable functions

	/**
	 * set a key value pair in the session
	 * @param $key
	 * @param $value
	 */
	static function set($key, $value) {
		Session::$_data[$key] = $value;
	}

	/**
	 * Get a value from the session by a key or return $default if it does not exist
	 * @param $key
	 * @param null $default
	 * @return mixed
	 */
	static function &get($key, $default = null) {
		if (isset(Session::$_data[$key])) {
			return Session::$_data[$key];
		}

		Session::$_data[$key] = $default;
		return Session::$_data[$key];
	}

	/**
	 * get a value from the session and delete it from the session object
	 * @param $key
	 * @param null $default
	 * @return mixed
	 */
	static function extract($key, $default = null) {
		$value = self::get($key, $default);
		self::delete($key);
		return $value;
	}

	/**
	 * Check if the session contains a value referenced by $key
	 * @param $key
	 * @return bool
	 */
	static function has($key) {
		return isset(Session::$_data[$key]);
	}

	/**
	 * Delete a value from the session by the key
	 * @param $key
	 */
	static function delete($key) {
		unset(Session::$_data[$key]);
	}

	/**
	 * clear the regular session data
	 * (excludes temp data)
	 */
	static function clear() {
		foreach (array_keys(Session::$_data) as $key) {
			unset(Session::$_data[$key]);
		}
	}

	/**
	 *
	 * @param $key
	 * @param $value
	 * @param int $ttl time to live in seconds
	 */
	static function setTemp($key, $value, $ttl = 1800) {
		$key = & self::getTemp($key, $value, $ttl);
		$key = $value;
	}

	/**
	 * get a reference to a temporary variable that will expire in ttl seconds of last use
	 * @param $id
	 * @param null $default
	 * @param int $ttl time to live in seconds
	 * @return mixed
	 */
	static function &getTemp($id, $default = null, $ttl = 1800) {
		if (!isset(Session::$_data['_temp'][$id])) {
			Session::$_data['_temp'][$id] = array('exp' => time() + $ttl, 'data' => $default);
		} else {
			Session::$_data['_temp'][$id]['exp'] = time() + $ttl;
		}

		return Session::$_data['_temp'][$id]['data'];
	}

	/**
	 * clear the temp/short term data from the session
	 * @param $id
	 */
	static function clearTemp($id) {
		unset(Session::$_data['_temp'][$id]);
	}

}

?>