<?php
defined('MUDPUPPY') or die('Restricted');

class Security {

	const PASSWORD_SALT_LENGTH = 8;

	/**
	 * Attempts to log a user in.
	 * @param string $email
	 * @param string $password
	 * @return bool|string true if successful, error message otherwise
	 */
	public static function login($email, $password) {
		self::logout();
		if (strlen($email) == 0 || strlen($password) == 0) {
			return 'Email and Password are required';
		}

		return "Security login method incomplete";
		/* FIXME
		$user = User::getByEmail($email);
		if ($user != null) {
			$salt = substr($user->password, 0, self::PASSWORD_SALT_LENGTH);
			$encryptedPass = substr($user->password, self::PASSWORD_SALT_LENGTH);
			if (self::getPasswordHash($salt, $password) == $encryptedPass) {
				Session::set('user', $user);
				return true;
			}
		}*/
		return 'Invalid Login or Password';
	}

	public static function refreshLogin() {
		/* FIXME
		if (self::isLoggedIn()) {
			$currentTime = time();
			$user = User::get(self::getUser()->id);
			if (Config::$noActivityTimeout && $user->lastActivity < $currentTime + Config::$noActivityTimeout) {
				App::sessionExpired();
				return;
			}
			// if it's been more than 10 sec since last activity, update last activity
			if ($currentTime - $user->lastActivity >= 10) {
				$user->lastActivity = time();
				$user->save();
			}
			// remove sensitive information from user object
			$user->clearValue('password');
			Session::set('user', $user);
		}*/
	}

	/**
	 * Gets the current user object.
	 * @return User
	 */
	public static function getUser() {
		return null; // FIXME: Session::get('user');
	}

	/**
	 * Check if user is logged in.
	 * @return boolean
	 */
	public static function isLoggedIn() {
		return false; // FIXME: Session::has('user');
	}

	/**
	 * Logs out the current user by resetting the session.
	 */
	public static function logout() {
		Session::resetAll();
	}

	/**
	 * Checks if the current user has a given permission.
	 * @param $permission
	 * @return bool
	 */
	public static function hasPermission($permission) {
		return true; // FIXME
		/*if (is_null($permission) || !self::isLoggedIn()) {
			return false;
		}
		$permissions = self::getUser()->permissions;
		return $permissions && is_array($permissions) && in_array($permission, $permissions);*/
	}

	/**
	 * Checks if the current user has a set of permissions.
	 * @param string[] $permissions
	 * @internal param $permission
	 * @return bool
	 */
	public static function hasPermissions($permissions) {
		if (empty($permissions)) {
			return true;
		}

		if (!self::isLoggedIn()) {
			return false;
		}

		$userPermissions = self::getUser()->permissions;
		if (!is_array($permissions)) {
			return false;
		}

		foreach ($permissions as $permission) {
			if (!in_array($permission, $userPermissions)) {
				return false;
			}
		}
		return true;
	}

	public static function hashPassword($password) {
		return password_hash($password, PASSWORD_BCRYPT, ["cost" => 11]);
	}

	public static function verifyPassword($password, $hash) {
		return password_verify($password, $hash);
	}
}

?>
