<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================
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
		include('mudpuppy/admin/LogView.php');
		App::cleanExit();
	}

	/**
	 * check for new log entries and retrieve them
	 * @param int $lastId
	 * @return array
	 */
	public function action_pull($lastId = -1) {
		Log::dontWrite();
		if ($lastId >= 0) {
			$results = DebugLog::getByFields(array(), "id > $lastId ORDER BY id ASC");
		} else {
			$results = DebugLog::getByFields(array(), "id > $lastId ORDER BY id DESC LIMIT 50");
			$results = array_reverse($results);
		}
		if (!empty($results)) {
			return DataObject::objectListToArrayList($results);
		}
		return array();
	}

	/**
	 * wait for a new log entry and retrieve it/them
	 * @param int $lastId
	 * @return array
	 */
	public function action_waitForNext($lastId = -1) {
		Log::dontWrite();
		session_write_close();
		set_time_limit(60 * 5);

		$pipe = 'mudpuppy/cache/mudpuppy_debugLogPipe';
		if (!file_exists($pipe)) {
			posix_mkfifo($pipe, 0777);
		}

		if ($lastId >= 0) {
			$results = DebugLog::getByFields(array(), "id > $lastId ORDER BY id ASC");
			if (!empty($results)) {
				return DataObject::objectListToArrayList($results);
			}
		}

		$fp = fopen($pipe, 'r+');
		stream_set_timeout($fp, 60);

		$read = array($fp);
		$write = null;
		$except = null;
		$triggered = stream_select($read, $write, $except, 30);
		fclose($fp);
		unlink($pipe);

		if ($triggered && $lastId == -1) {
			return array(DebugLog::getLast()->toArray());
		} else if ($lastId >= 0) {
			$results = DebugLog::getByFields(array(), "id > $lastId ORDER BY id ASC");
			if (!empty($results)) {
				return DataObject::objectListToArrayList($results);
			}
		}
		Log::add('nothing');

		return array();
	}

	/**
	 * @return array
	 */
	public function action_getLast() {
		return DebugLog::getLast()->toArray();
	}

	/**
	 * signal a new log entry was recorded
	 */
	public function action_trigger() {
		$pipe = 'mudpuppy/cache/mudpuppy_debugLogPipe';
		if (!file_exists($pipe)) {
			return;
		}

		$fp = fopen($pipe, 'r+');
		stream_set_timeout($fp, 10);
		fwrite($fp, '1');
		fclose($fp);
		unlink($fp);
	}

	/**
	 * clear the log
	 */
	public function action_clearLog() {
		DebugLog::deleteAll();
	}
}