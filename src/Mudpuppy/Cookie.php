<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy;

defined('MUDPUPPY') or die('Restricted');

class Cookie {
	private static $data = array();

	public static function init($name) {
		self::$data = json_decode($_COOKIE[$name], true);
	}

	public static function write($name) {
		setcookie($name, json_encode(self::$data));
	}

	public static function set($key, $value) {
		self::$data[$key] = $value;
	}

	public static function get($key, $default = null) {
		if (isset(self::$data[$key])) {
			return self::$data[$key];
		}
		return $default;
	}
}

?>
