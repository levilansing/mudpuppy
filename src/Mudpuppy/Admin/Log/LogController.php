<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy\Admin\log;

use Mudpuppy\Config;
use Mudpuppy\App;
use Mudpuppy\Controller;
use Mudpuppy\DataObject;
use Mudpuppy\File;
use Mudpuppy\Log;
use Mudpuppy\Model\DebugLog;
use Mudpuppy\PageController;
use Mudpuppy\Request;

defined('MUDPUPPY') or die('Restricted');

class LogController extends Controller {
	use PageController;

	public function __construct($pathOptions) {
		// Don't write the log for this request
		Log::dontWrite();

		// Disable browser caching
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate"); // HTTP/1.1
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache"); // HTTP/1.0
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

		parent::__construct($pathOptions);
	}

	public function getRequiredPermissions() {
		return array();
	}

	public function render() {
		// Abort the default template, use the debug log view for the entire page
		ob_clean();
		include('Mudpuppy/Admin/Log/LogView.php');
		App::cleanExit();
	}

	private static function getNewLogs($lastId) {
		$results = [];
		if (Config::$logToDatabase) {
			$db = App::getDBO();
			$db->prepare('SELECT * FROM DebugLogs WHERE id > ? ORDER BY id ASC');
			$db->execute([$lastId]);
			$results = $db->fetchAll('Mudpuppy\Model\DebugLog');
			if (count($results)) {
				$results = DataObject::objectListToArrayList($results);
			}
		} else if (!empty(Config::$logFileDir)) {
			foreach (scandir(Config::$logFileDir, SCANDIR_SORT_DESCENDING) as $file) {
				if ($file == "$lastId.json") {
					break;
				}
				if ($file != '.' && $file != '..' && File::getExtension($file) == 'json') {
					$results[] = json_decode(file_get_contents(Config::$logFileDir . $file));
				}
			}
			$results = array_reverse($results);
		}
		return $results;
	}

	/**
	 * check for new log entries and retrieve them
	 * @param string $lastId
	 * @return array
	 */
	public function action_pull($lastId = null) {
		$results = [];
		if (Config::$logToDatabase) {
			$lastId = Request::cleanValue($lastId, -1, 'int');
			if ($lastId >= 0) {
				$results = self::getNewLogs($lastId);
			} else {
				$db = App::getDBO();
				$db->prepare('SELECT * FROM DebugLogs WHERE id > ? ORDER BY id DESC LIMIT 100');
				$db->execute([$lastId]);
				$results = $db->fetchAll('Mudpuppy\Model\DebugLog');

				$results = DataObject::objectListToArrayList(array_reverse($results));
			}
		} else if (!empty(Config::$logFileDir)) {
			if (!empty($lastId)) {
				$results = self::getNewLogs($lastId);
			} else {
				foreach (array_reverse(array_slice(scandir(Config::$logFileDir, SCANDIR_SORT_DESCENDING), 0, 100)) as $file) {
					if ($file != '.' && $file != '..' && File::getExtension($file) == 'json') {
						$results[] = json_decode(file_get_contents(Config::$logFileDir . $file));
					}
				}
			}
		}
		return $results;
	}

	/**
	 * wait for a new log entry and retrieve it/them
	 * @param string $lastId
	 * @return array
	 */
	public function action_waitForNext($lastId = null) {
		// Release the session object so we're not holding up other requests
		session_write_close();

		// Timeout after 5 minutes
		set_time_limit(60 * 5);

		// If we can, create a pipe to allow for notification from the next request that writes a log
		$pipesSupported = function_exists('posix_mkfifo');
		if ($pipesSupported) {
			$pipe = 'Mudpuppy/cache/mudpuppy_debugLogPipe';
			if (!file_exists($pipe)) {
				posix_mkfifo($pipe, 0777);
			}
		}

		// First check for any new logs and just return those right away if there are any
		$results = [];
		$hasLastId = false;
		if (Config::$logToDatabase) {
			$lastId = Request::cleanValue($lastId, -1, 'int');
			if ($lastId >= 0) {
				$hasLastId = true;
			}
		} else if (!empty(Config::$logFileDir) && !empty($lastId)) {
			$hasLastId = true;
		}
		if ($hasLastId) {
			$results = self::getNewLogs($lastId);
		}
		if (!empty($results)) {
			return $results;
		}

		// Nothing new right now, so do long polling and wait for notification from the pipe if possible
		if ($pipesSupported) {
			$fp = fopen($pipe, 'r+');
			stream_set_timeout($fp, 60);

			$read = array($fp);
			$write = null;
			$except = null;
			$triggered = stream_select($read, $write, $except, 15);
			fclose($fp);
			unlink($pipe);
		} else {
			// Can't do long polling without the notification pipe, so just fallback to a simple 10 second interval
			sleep(10);
			$triggered = true;
		}

		// Now get the latest logs
		if ($triggered && !$hasLastId) {
			if (Config::$logToDatabase) {
				$log = DebugLog::getLast();
				if ($log) {
					$results[] = $log->toArray();
				}
			} else if (!empty(Config::$logFileDir)) {
				$files = scandir(Config::$logFileDir, SCANDIR_SORT_DESCENDING);
				if (!empty($files)) {
					if ($files[0] != '.' && $files[0] != '..' && File::getExtension($files[0]) == 'json') {
						$results[] = json_decode(file_get_contents(Config::$logFileDir . $files[0]));
					}
				}
			}
		} else if ($hasLastId) {
			$results = self::getNewLogs($lastId);
		}
		return $results;
	}

	/**
	 * clear the log
	 */
	public function action_clearLog() {
		if (Config::$logToDatabase) {
			DebugLog::deleteAll();
		}
		if (!empty(Config::$logFileDir)) {
			File::deleteAllFiles(Config::$logFileDir);
		}
	}
}