<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================
namespace Mudpuppy;
defined('MUDPUPPY') or die('Restricted');

// Base configuration
class Config {
	// Database settings
	public static $dbProtocol = 'mysql:host=%s;port=%s;dbname=%s';
	public static $dbHost = null;
	public static $dbPort = null;
	public static $dbDatabase = null;
	public static $dbUser = null;
	public static $dbPass = null;

	// Site configuration
	public static $appTitle = 'Mudpuppy';
	public static $timezone = 'America/New_York';
	public static $dateTimeFormat = 'd M Y H:i:s \\G\\M\\TO';
	public static $dateOnlyFormat = 'd M Y';

	// Application configuration
	public static $appClass = null;
	public static $rootControllerName = 'Root';
	public static $randomSeedOffset = 0x5C7474D1;
	// Array of folders for global classes or key-value pairs for namespace => folder
	public static $autoloadFolders = array();

	// Debugging
	public static $debug = true;
	public static $logQueries = false;
	public static $logLevel = LOG_LEVEL_NONE;
	public static $logToDatabase = false;
	// Path to directory for storing local log files (must include trailing slash). Leave blank or null to disable.
	public static $logFileDir = '';

	// AWS configuration
	public static $awsConfig = '';

	// Custom application settings (user-defined associative array of properties)
	public static $custom = [];

	public static function load($file) {
		if (file_exists($file)) {
			$options = json_decode(file_get_contents($file), true);
			if (isset($options['base'])) {
				self::applySettings($options['base']);
			}
			if (isset($options['envVar']) && isset($_SERVER[$options['envVar']]) && isset($options['envs'])) {
				foreach ($options['envs'] as $envVal) {
					if ($_SERVER[$options['envVar']] == $envVal) {
						self::applySettings($options['envs'][$envVal]);
						break;
					}
				}
			}
		}
	}

	private static function applySettings($settings) {
		// Database settings
		if (isset($settings['dbHost'])) {
			self::$dbHost = self::getSettingValue($settings['dbHost']);
		}
		if (isset($settings['dbPort'])) {
			self::$dbPort = self::getSettingValue($settings['dbPort']);
		}
		if (isset($settings['dbDatabase'])) {
			self::$dbDatabase = self::getSettingValue($settings['dbDatabase']);
		}
		if (isset($settings['dbDatabase'])) {
			self::$dbUser = self::getSettingValue($settings['dbUser']);
		}
		if (isset($settings['dbPass'])) {
			self::$dbPass = self::getSettingValue($settings['dbPass']);
		}

		// Site configuration
		if (isset($settings['appTitle'])) {
			self::$appTitle = self::getSettingValue($settings['appTitle']);
		}
		if (isset($settings['timezone'])) {
			self::$timezone = self::getSettingValue($settings['timezone']);
		}
		if (isset($settings['dateTimeFormat'])) {
			self::$dateTimeFormat = self::getSettingValue($settings['dateTimeFormat']);
		}
		if (isset($settings['dateOnlyFormat'])) {
			self::$dateOnlyFormat = self::getSettingValue($settings['dateOnlyFormat']);
		}

		// Application configuration
		if (isset($settings['appClass'])) {
			self::$appClass = self::getSettingValue($settings['appClass']);
		}
		if (isset($settings['rootControllerName'])) {
			self::$rootControllerName = self::getSettingValue($settings['rootControllerName']);
		}
		if (isset($settings['randomSeedOffset'])) {
			self::$randomSeedOffset = self::getSettingValue($settings['randomSeedOffset']);
		}
		if (isset($settings['autoloadFolders'])) {
			self::$autoloadFolders = self::getSettingValue($settings['autoloadFolders']);
		}

		// Debugging
		if (isset($settings['debug'])) {
			self::$debug = self::getSettingValue($settings['debug']);
		}
		if (isset($settings['logQueries'])) {
			self::$logQueries = self::getSettingValue($settings['logQueries']);
		}
		if (isset($settings['logLevel'])) {
			self::$logLevel = self::getSettingValue($settings['logLevel']);
		}
		if (isset($settings['logToDatabase'])) {
			self::$logToDatabase = self::getSettingValue($settings['logToDatabase']);
		}
		if (isset($settings['logFileDir'])) {
			self::$logFileDir = self::getSettingValue($settings['logFileDir']);
		}

		// AWS configuration
		if (isset($settings['awsConfig'])) {
			self::$awsConfig = self::getSettingValue($settings['awsConfig']);
		}

		// Custom application settings
		if (isset($settings['custom'])) {
			foreach ($settings['custom'] as $key => $customSetting) {
				self::$custom[$key] = self::getSettingValue($customSetting);
			}
		}
	}

	private static function getSettingValue($setting) {
		if (isset($setting['type']) && isset($setting['value'])) {
			if ($setting['type'] == 'envVar') {
				return $_SERVER[$setting['value']];
			}
			return $setting['value'];
		}
		return null;
	}

}

?>