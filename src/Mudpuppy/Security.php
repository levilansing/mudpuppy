<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy;

defined('MUDPUPPY') or die('Restricted');

abstract class Security {

	/**
	 * Update time of last activity and optionally refresh a user's permissions
	 */
	abstract public function refreshLogin();

	/**
	 * Gets the current user object
	 */
	abstract public function getUser();

	/**
	 * Check if user is logged in.
	 * @return boolean
	 */
	abstract public function isLoggedIn();

	/**
	 * Logs out the current user.
	 */
	abstract public function logout();

	/**
	 * Checks if the current user has a given permission.
	 * @param $permission
	 * @return bool
	 */
	abstract public function hasPermission($permission);

	/**
	 * Checks if the current user has a set of permissions.
	 * @param string[] $permissions
	 * @internal param $permission
	 * @return bool
	 */
	abstract public function hasPermissions($permissions);


	public static function hashPassword($password) {
		return password_hash($password, PASSWORD_BCRYPT, ["cost" => 11]);
	}

	public static function verifyPassword($password, $hash) {
		return password_verify($password, $hash);
	}
}

?>
