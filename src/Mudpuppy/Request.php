<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy;

defined('MUDPUPPY') or die('Restricted');

// static class to assist with accessing request variables (GET,POST,REQUEST,etc)
// handles magic quotes automatically
// modeled after Joomla's Request class (v1.5), but not copied
class Request {

	static private $params;

	/**
	 * set the object to represent the 'PARAMS' request dataset. defaults to $_REQUEST
	 * @param $params
	 */
	static function setParams(&$params) {
		self::$params = & $params;
	}

	static function &getParams() {
		return self::$params;
	}

	/**
	 * Get a string value from the input location and trim it
	 * @param $key
	 * @param null $default
	 * @param string $inputLocation
	 * @return string
	 */
	static function getTrimmed($key, $default = null, $inputLocation = 'PARAMS') {
		return trim(self::get($key, $default, $inputLocation));
	}

	/**
	 * Get a variable from the given input location or return $default if it does not exist
	 * @param $key
	 * @param null $default
	 * @param string $inputLocation =PARAMS (or POST, GET, REQUEST, FILE)
	 * @return *|$default
	 * @throws MudpuppyException if input location is invalid
	 */
	static function get($key, $default = null, $inputLocation = 'PARAMS') {
		$inputLocation = strtoupper($inputLocation);

		$input = null;
		switch ($inputLocation) {
		case 'GET':
			$input =& $_GET;
			break;
		case 'POST':
			$input =& $_POST;
			break;
		case 'COOKIE':
			$input =& $_COOKIE;
			break;
		case 'FILES':
			$input =& $_FILES;
			break;
		case 'REQUEST':
			$input =& $_REQUEST;
			break;
		case 'PARAMS':
			$input =& self::$params;
			break;
		default:
			if (Config::$debug) {
				throw new MudpuppyException("'$inputLocation' is not a valid input location");
			}
			return null;
		}

		if (!isset($input[$key])) {
			return $default;
		}

		$var =& $input[$key];

		return $var;
	}

	/**
	 * Get a value from the input location and convert it to a boolean
	 * @param $key
	 * @param bool $default
	 * @param string $inputLocation
	 * @return bool
	 */
	static function getBool($key, $default = false, $inputLocation = 'PARAMS') {
		$var = self::get($key, $default, $inputLocation);
		if (!$var || $var == '0' || strncasecmp($var, 'false', 5) == 0 || strncasecmp($var, 'off', 3) == 0 || strncasecmp($var, 'no', 2) == 0) {
			return false;
		}
		return true;
	}

	/**
	 * Get a value from the input location and convert it to an int
	 * @param $key
	 * @param null $default
	 * @param string $inputLocation
	 * @return int
	 */
	static function getInt($key, $default = null, $inputLocation = 'PARAMS') {
		$var = self::get($key, $default, $inputLocation);
		return self::cleanValue($var, $default, 'int');
	}

	/**
	 * Get a value from the input location and convert it to a number
	 * @param $key
	 * @param null $default
	 * @param string $inputLocation
	 * @return int
	 */
	static function getNum($key, $default = null, $inputLocation = 'PARAMS') {
		$var = self::get($key, $default, $inputLocation);
		return self::cleanValue($var, $default, 'num');
	}

	/**
	 * Get a value from the input location and convert it to a date
	 * @param $key
	 * @param null $default
	 * @param string $inputLocation
	 * @return int
	 */
	static function getDate($key, $default = null, $inputLocation = 'PARAMS') {
		$var = self::get($key, $default, $inputLocation);
		return self::cleanValue($var, $default, 'date');
	}

	/**
	 * get a safe string to be used as a command or filename (not path)
	 * @param $key
	 * @param null $default
	 * @param string $inputLocation
	 * @return int
	 */
	static function getCmd($key, $default = null, $inputLocation = 'PARAMS') {
		$var = self::get($key, $default, $inputLocation);
		return self::cleanValue($var, $default, 'cmd');
	}

	/**
	 * get a safe path (no ../), can't end with / or .
	 * @param $key
	 * @param null $default
	 * @param string $inputLocation
	 * @return int
	 */
	static function getPath($key, $default = null, $inputLocation = 'PARAMS') {
		$var = self::get($key, $default, $inputLocation);
		return self::cleanValue($var, $default, 'path');
	}

	/**
	 * Get a value from the input location and convert it to an array if it is not already
	 * @param $key
	 * @param array $default
	 * @param string $inputLocation
	 * @param null $type
	 * @return array|int|null
	 */
	static function &getArray($key, $default = array(), $inputLocation = 'PARAMS', $type = null) {
		$var = self::get($key, $default, $inputLocation);
		if (!is_array($var)) {
			$var = array($var);
		}
		if (!is_null($type)) {
			$var = self::cleanValue($var, $default, $type);
		}
		return $var;
	}

	/**
	 * Cleans an input value. Accepts the follow types: str, int, num, cmd, path, abspath, host, date
	 *
	 * @param $var
	 * @param $default
	 * @param $type
	 * @return int
	 */
	static function cleanValue($var, $default, $type) {
		if ($var === $default) {
			return $default;
		}

		if (is_array($var)) {
			foreach ($var as &$v) {
				$v = self::cleanValue($v, $default, $type);
			}
			return $var;
		}

		$pat = '#.*#';
		switch ($type) {
		case 'int':
		case 'num':
			$pat = '#^(\-?[0-9]*\.?[0-9]*)$#';
			break;
		case 'cmd':
			$pat = '#^[a-zA-Z_][a-zA-Z_0-9]*$#';
			break;
		case 'path':
			$var = str_replace('\\', '/', $var);
			$pat = '#^([a-zA-Z_\- 0-9])(/?\.?[a-zA-Z_\- 0-9]+?)+$#'; // accepts a . but not a .. to avoid ../
			break;
		case 'abspath':
			$var = str_replace('\\', '/', $var);
			$pat = '#^([a-zA-Z]:)?/[a-zA-Z_\- 0-9\./]*$#';
			break;
		case 'host':
			$pat = '#^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$#';
			break;
		case 'date':
			if (empty($var)) {
				return $default;
			}
			$date = strtotime($var);
			if ($date === false) {
				return $default;
			}
			return $date;
		}

		$matches = array();
		if (preg_match($pat, $var, $matches)) {
			$match = $matches[0];
			if (($type == 'num' || $type == 'int') && $match == '') {
				return $default;
			}
			if ($type == 'int') {
				return (int)$match;
			}
			return $matches[0];
		}

		return $default;
	}

	/**
	 * Get a value specifically from POST
	 * @param $key
	 * @param null $default
	 * @param null $type
	 * @return int|null
	 */
	static function getPost($key, $default = null, $type = null) {
		$v = self::get($key, $default, 'POST');
		if ($type != null) {
			$v = self::cleanValue($v, $default, $type);
		}
		return $v;
	}

	/**
	 * Get a value specifically from GET
	 * @param $key
	 * @param null $default
	 * @param null $type
	 * @return int|null
	 */
	static function getGet($key, $default = null, $type = null) {
		$v = self::get($key, $default, 'GET');
		if ($type != null) {
			$v = self::cleanValue($v, $default, $type);
		}
		return $v;
	}

	/**
	 * check if an input value exists in the given input location
	 * @param $key
	 * @param string $inputLocation
	 * @return bool
	 */
	static function has($key, $inputLocation = 'PARAMS') {
		$inputLocation = strtoupper($inputLocation);
		switch ($inputLocation) {
		case 'GET':
			return isset($_GET[$key]);
		case 'POST':
			return isset($_POST[$key]);
		case 'FILE':
		case 'FILES':
			return isset($_FILES[$key]);
		case 'COOKIE':
			return isset($_COOKIE[$key]);
		case 'REQUEST':
			return isset($_REQUEST[$key]);
		case 'PARAMS':
			return isset(self::$params[$key]);
		}
		return false;
	}

	/**
	 * check if an input value exists specifically in POST
	 * @param $key
	 * @return bool
	 */
	static function isPost($key) {
		return self::has($key, 'POST');
	}

	/**
	 * check if an input value exists specifically in PARAMS
	 * @param $key
	 * @return bool
	 */
	static function isParam($key) {
		return self::has($key, 'PARAMS');
	}

	/**
	 * check if an input value exists specifically in GET
	 * @param $key
	 * @return bool
	 */
	static function isGet($key) {
		return self::has($key, 'GET');
	}

	/**
	 * check if an input value exists for a FILE
	 * @param $key
	 * @return bool
	 */
	static function isFile($key) {
		return self::has($key, 'FILE');
	}

	/**
	 * sets an input variable at the specified location AND _REQUEST
	 * @param $key
	 * @param $value
	 * @param string $inputType
	 */
	static function setVar($key, $value, $inputType = 'PARAMS') {
		$inputType = strtoupper($inputType);

		switch ($inputType) {
		case 'GET':
			$_GET[$key] = $value;
			break;
		case 'POST':
			$_POST[$key] = $value;
			break;
		case 'COOKIE':
			$_COOKIE[$key] = $value;
			break;
		case 'REQUEST':
			break;
		case 'PARAMS':
			self::$params[$key] = $value;
			break;
		}
		$_REQUEST[$key] = $value;
	}

	/**
	 * unset a value in a specified input location
	 * @param $key
	 * @param string $inputType
	 */
	static function unsetValue($key, $inputType = 'ALL') {
		$inputType = strtoupper($inputType);

		switch ($inputType) {
		case 'ALL':
			unset($_GET[$key]);
			unset($_POST[$key]);
			unset($_COOKIE[$key]);
			unset(self::$params[$key]);
			break;
		case 'GET':
			unset($_GET[$key]);
			break;
		case 'POST':
			unset($_POST[$key]);
			break;
		case 'COOKIE':
			unset($_COOKIE[$key]);
			break;
		case 'REQUEST':
			break;
		case 'PARAMS':
			unset(self::$params[$key]);
			break;
		}
		unset($_REQUEST[$key]);
	}

	/*
	  * strip slashes (recursively) - specifically for resolving magic quotes
	  * stripSlashesRecursive is called for every array element
	  * stripslashes() is called for each non-array variable
	  * @param $var
	  * @return string|array
	  */
	private static function stripSlashesRecursive($var) {
		if (is_array($var)) {
			foreach ($var as &$v) {
				$v = self::stripSlashesRecursive($v);
			}
			return $var;
		}
		return stripslashes($var);
	}

}

Request::setParams($_REQUEST);

?>