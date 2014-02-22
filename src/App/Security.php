<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace App;
use Mudpuppy;
use Mudpuppy\Session;

defined('MUDPUPPY') or die('Restricted');

class Security extends Mudpuppy\Security {

	/**
	 * Attempts to log a user in.
	 * @param string $username
	 * @param string $password
	 * @return bool|string true if successful, error message otherwise
	 */
	public function login($username, $password) {
		return "Security login method incomplete";

		/*****************************************************************************************************************
		* Example Implementation
		******************************************************************************************************************
		// Clear any active login
		$this->logout();
		// Must specify both parameters
		if (empty($username) || empty($password)) {
			return 'Username and Password are required';
		}
		// Fetch the user object
		$user = User::fetchOne(['username' => $username]);
		// Verify the password
		if ($user != null && $this->verifyPassword($password, $user->password)) {
			// Store the logged in user object in the session
			Session::set('user', $user);
			// Success
			return true;
		}
		// Failure
		return 'Invalid Username or Password';
		*****************************************************************************************************************/
	}

	/**
	 * Refreshes the current login.
	 */
	public function refreshLogin() {
		/*****************************************************************************************************************
		* Example Implementation
		******************************************************************************************************************
		if ($this->isLoggedIn()) {
			// Re-fetch the user object
			$user = User::fetchOne(['id' => $this->getUser()->id]);
			// Update last activity time, etc, as needed by your application
			// Store back to the session
			Session::set('user', $user);
		}
		*****************************************************************************************************************/
	}

	/**
	 * Gets the current user object.
	 * @return \Mudpuppy\DataObject
	 */
	public function getUser() {
		return null;
		// Example Implementation: Session::get('user');
	}

	/**
	 * Check if user is logged in.
	 * @return boolean
	 */
	public function isLoggedIn() {
		return false;
		// Example Implementation: Session::has('user');
	}

	/**
	 * Logs out the current user by resetting the session.
	 */
	public function logout() {
		Session::reset();
	}

	/**
	 * Checks if the current user has a given permission.
	 * @param $permission
	 * @return bool
	 */
	public function hasPermission($permission) {
		return true;

		/*****************************************************************************************************************
		* Example Implementation
		******************************************************************************************************************
		if (is_null($permission) || !$this->isLoggedIn()) {
			return false;
		}
		$permissions = $this->getUser()->permissions;
		return $permissions && is_array($permissions) && in_array($permission, $permissions);
		*****************************************************************************************************************/
	}

	/**
	 * Checks if the current user has a set of permissions.
	 * @param string[] $permissions
	 * @return bool
	 */
	public function hasPermissions($permissions) {
		return true;

		/*****************************************************************************************************************
		* Example Implementation
		******************************************************************************************************************
		if (empty($permissions)) {
			return true;
		}
		if (!$this->isLoggedIn()) {
			return false;
		}
		$userPermissions = $this->getUser()->permissions;
		if (!is_array($userPermissions)) {
			return false;
		}
		foreach ($permissions as $permission) {
			if (!in_array($permission, $userPermissions)) {
				return false;
			}
		}
		return true;
		*****************************************************************************************************************/
	}

}

?>
