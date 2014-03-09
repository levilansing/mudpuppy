<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy\Admin\Install;

use Mudpuppy\App;
use Mudpuppy\Config;
use Mudpuppy\Controller;
use Mudpuppy\Database;
use Mudpuppy\File;
use Mudpuppy\InvalidInputException;
use Mudpuppy\Log;
use Mudpuppy\MudpuppyException;
use Mudpuppy\PageController;
use Mudpuppy\PermissionDeniedException;
use Mudpuppy\Request;
use Mudpuppy\Security;
use Mudpuppy\Session;

defined('MUDPUPPY') or die('Restricted');

class InstallController extends Controller {
	use PageController;

	public function __construct($pathOptions) {
		Log::dontWrite();
		parent::__construct($pathOptions);
	}

	/**
	 * @throws \Mudpuppy\PermissionDeniedException
	 * @returns array
	 */
	public function getRequiredPermissions() {
		if (!empty(Config::$appClass)) {
			throw new PermissionDeniedException('The installer is only available when app class is not configured');
		}
		return array();
	}

	/**
	 * Renders the page body.
	 */
	public function render() {
		// Abort the default template, use the install view for the entire page
		ob_clean();
		include('Mudpuppy/Admin/Install/InstallView.php');
		App::cleanExit();
	}

	/**
	 * @param string $appTitle
	 * @param string $appClass
	 * @param boolean $dbEnabled
	 * @param string $dbHost
	 * @param string $dbPort
	 * @param string $dbDatabase
	 * @param string $dbUser
	 * @param string $dbPass
	 * @param int $logLevel
	 * @param boolean $logQueries
	 * @param boolean $logToDatabase
	 * @param boolean $logToDir
	 * @param string $logFileDir
	 * @param boolean $adminBasicAuth
	 * @param string $adminUser
	 * @param string $adminPass
	 * @throws \Mudpuppy\MudpuppyException
	 * @return array
	 */
	public function action_doInstall($appTitle, $appClass, $dbEnabled, $dbHost, $dbPort, $dbDatabase, $dbUser, $dbPass,
		$logLevel, $logQueries, $logToDatabase, $logToDir, $logFileDir, $adminBasicAuth, $adminUser, $adminPass) {
		Session::clear();
		$baseConfig = ['randomSeedOffset' => self::configSetting(rand(1, PHP_INT_MAX))];
		$writableDirs = ['App/', 'Model/', 'Mudpuppy/cache/'];
		$baseConfig['appTitle'] = self::configSetting(htmlentities(self::clean($appTitle, 'str', 'Application Title')));
		$baseConfig['appClass'] = self::configSetting(self::clean($appClass, 'cmd', 'Application Class Name'));
		if ($dbEnabled) {
			$baseConfig['dbHost'] = self::configSetting(self::clean($dbHost, 'host', 'Database Host'));
			$baseConfig['dbPort'] = self::configSetting((int)self::clean($dbPort, 'int', 'Database Port'));
			$baseConfig['dbDatabase'] = self::configSetting(self::clean($dbDatabase, 'cmd', 'Database Name'));
			$baseConfig['dbUser'] = self::configSetting(self::clean($dbUser, 'cmd', 'Database Username'));
			$baseConfig['dbPass'] = self::configSetting(trim($dbPass));
		}
		$baseConfig['logLevel'] = self::configSetting($logLevel);
		$baseConfig['logQueries'] = self::configSetting($logQueries);
		$baseConfig['logToDatabase'] = self::configSetting($logToDatabase);
		if ($logToDir) {
			$logFileDir = self::clean($logFileDir, 'abspath', 'Log Directory');
			if (substr($logFileDir, -1) != '/') {
				$logFileDir .= '/';
			}
			$baseConfig['logFileDir'] = self::configSetting($logFileDir);
			$writableDirs[] = $logFileDir;
		}

		$basicAuthRealms = null;
		if ($adminBasicAuth) {
			$adminUser = self::clean($adminUser, 'cmd', 'Admin Username');
			$adminPass = self::clean($adminPass, 'str', 'Admin Password');
			$basicAuthRealms = [
				'Mudpuppy Admin' => [
					'pathPattern' => '#^/mudpuppy/#i',
					'credentials' => [
						$adminUser => Security::hashPassword($adminPass)
					]
				]
			];
		}

		foreach ($writableDirs as $dir) {
			if (!is_writable($dir)) {
				throw new MudpuppyException(null, "Directory must be writable: $dir");
			}
		}

		$dbo = new Database();
		if (!$dbo->connect($dbHost, $dbPort, $dbDatabase, $dbUser, $dbPass)) {
			throw new MudpuppyException(null, 'Database connection failed with specified parameters');
		}

		if (!File::putContents("App/$appClass.php", str_replace('___CLASS_NAME___', $appClass, file_get_contents('Mudpuppy/Admin/Install/_AppStub')))) {
			throw new MudpuppyException(null, "Error creating file: App/$appClass.php");
		}
		if (!File::putContents('App/Config.json', json_encode(['base' => $baseConfig]))) {
			unlink("App/$appClass.php");
			throw new MudpuppyException(null, 'Error creating file: App/Config.json');
		}
		if ($basicAuthRealms != null && !File::putContents('App/BasicAuth.json', json_encode($basicAuthRealms))) {
			unlink("App/$appClass.php");
			unlink("App/Config.json");
			throw new MudpuppyException(null, 'Error creating file: App/BasicAuth.json');
		}
		return [];
	}

	private static function clean($value, $type, $name) {
		$value = Request::cleanValue(trim($value), null, $type);
		if (empty($value) && !($type == 'int' && $value === 0)) {
			throw new InvalidInputException(null, "Invalid value for $name");
		}
		return $value;
	}

	private static function configSetting($value) {
		return ['type' => 'constant', 'value' => $value];
	}

}