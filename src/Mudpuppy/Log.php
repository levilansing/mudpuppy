<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy;

use Mudpuppy\Model\DebugLog;

defined('MUDPUPPY') or die('Restricted');

define('LOG_LEVEL_ALWAYS', 3);
define('LOG_LEVEL_ERROR', 2);
define('LOG_LEVEL_NONE', 1);

class Log {
	private static $startTime;
	private static $log;
	private static $errors;
	private static $writeCompleted = false;

	/**
	 * start up the log and record the start time (called automatically at bottom of this file)
	 */
	public static function initialize() {
		if (Log::$startTime) {
			return;
		}
		Log::$startTime = microtime();
		Log::$log = array();
	}

	/**
	 * Get the elapsed time as a string in seconds since Mudpuppy started up
	 * @param int $time
	 * @return string
	 */
	public static function getElapsedTime($time = null) {
		if (empty($time)) {
			$time = microtime();
		}
		list($u1, $s1) = explode(' ', self::$startTime);
		list($u2, $s2) = explode(' ', $time);
		$s = $s2 - $s1;
		$u = $u2 - $u1;
		if ($u < 0) {
			$u += 1;
			$s -= 1;
		}
		return $s . substr($u, 1);
	}

	/**
	 * Add text to the log (located under "Logs")
	 * @param string $text
	 */
	public static function add($text) {
		Log::$log[] = array('title' => $text, 'time' => self::getElapsedTime(), 'mem' => memory_get_usage());
	}

	/**
	 * @param int $start
	 * @param null $stack
	 * @return string
	 */
	private static function getBacktrace($start = 1, $stack = null) {
		ob_start();
		$outputTrace = function ($a, $b) {
			$path = (isset($a['file']) ? $a['file'] : '');
			$docRoot = $_SERVER['DOCUMENT_ROOT'];
			if (strncasecmp($path, $docRoot, strlen($docRoot)) == 0) {
				$path = substr($path, strlen($docRoot));
			}
			$class = (isset($a['class']) ? $a['class'] . $a['type'] : '');
			$line = (isset($a['line']) ? "($a[line])" : '');
			print "\n" . str_pad("$path $line > ", 55, ' ', STR_PAD_LEFT) . "$class$a[function]()";
		};

		if ($stack == null) {
			$stack = debug_backtrace();
		}
		while ($start-- > 0 && sizeof($stack) > 0) {
			array_shift($stack);
		}
		$first = reset($stack);
		if ($first && $first['function'] == 'error_handler') {
			array_shift($stack);
		}
		array_walk($stack, $outputTrace);
		$trace = ob_get_clean();
		return $trace;
	}

	/**
	 * Log an error
	 * @param string $error
	 */
	public static function error($error) {
		$error .= self::getBacktrace(2);
		Log::$errors[] = array('time' => Log::getElapsedTime(), 'error' => $error);
	}

	/**
	 * Log an exception
	 * @param \Exception $e
	 */
	public static function exception(\Exception $e) {
		$error = get_class($e) . ': ' . $e->getFile() . '(' . $e->getLine() . ') \'' . $e->getMessage() . '\'';
		$error .= self::getBacktrace(0, $e->getTrace());
		Log::$errors[] = array('time' => Log::getElapsedTime(), 'error' => $error);
	}

	/**
	 * get the list of errors
	 * @return mixed
	 */
	public static function getErrors() {
		return Log::$errors;
	}

	/**
	 * format memory in a human readable manner
	 * @param $mem
	 * @return string
	 */
	private static function formatMemory($mem) {
		$u = array('B', 'KB', 'MB', 'GB', 'TB');
		$i = 0;
		while ($mem > 1024) {
			$mem = $mem / 1024;
			$i++;
		}
		return number_format($mem, 2) . ' ' . $u[$i];
	}

	/**
	 * inform the log we don't want to write to the log files/database when we are finished
	 */
	public static function dontWrite() {
		self::$writeCompleted = true;
	}

	/**
	 * write the log out
	 * writes to the database if possible, if not, and if we are in debug,
	 * then display the log because something has gone awry
	 */
	public static function write() {
		if (self::$writeCompleted) {
			return;
		}
		$tend = microtime();
		if (\Config::$logLevel == LOG_LEVEL_NONE) {
			return;
		}
		if (!\Config::$dbHost && \Config::$debug) {
			if (\Config::$logLevel == LOG_LEVEL_ALWAYS || !empty(Log::$errors)) {
				Log::displayFullLog();
				self::$writeCompleted = true;
			}
			return;
		}
		if (\Config::$logLevel == LOG_LEVEL_ALWAYS || !empty(Log::$errors)) {
			try {
				// delete old logs once in a while
				if (rand(0, 100) == 0) {
					$oldAge = strtotime('7 days ago');
					$db = App::getDBO();
					$db->query("DELETE FROM DebugLogs WHERE `date` < '" . Database::formatDate($oldAge, false) . "'");
				}

				list($u, $s) = explode(' ', self::$startTime);
				$startTime = $s . substr($u, 1);

				$log = new DebugLog();
				$log->date = $s;
				$log->requestMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
				$log->requestPath = (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
				$log->request = Request::getParams();
				$log->memoryUsage = memory_get_peak_usage();
				$log->startTime = $startTime;
				$log->executionTime = self::getElapsedTime($tend);
				$log->queries = array('errors' => Database::$errorCount, 'queries' => Database::$queryLog);
				$log->log = self::$log;
				$log->errors = self::$errors;
				$log->responseCode = http_response_code();

				if (!$log->save()) {
					self::displayFullLog();
				}

				self::$writeCompleted = true;

				// notify listeners that we logged something
				$pipe = 'Mudpuppy/cache/mudpuppy_debugLogPipe';
				if (!file_exists($pipe)) {
					return;
				}

				$fp = fopen($pipe, 'r+');
				if ($fp) {
					stream_set_timeout($fp, 10);
					fwrite($fp, '1');
					fclose($fp);
					unlink($fp);
				}

			} catch (\Exception $e) {
				if (\Config::$debug) {
					print "Exception while attempting to record the log: ";
					print $e->getMessage();
				}
			}
		}
	}

	/**
	 * display the entire log in an HTML format
	 */
	public static function displayFullLog() {
		$tend = microtime();

		$nErrors = sizeof(Log::$errors);
		if (!\Config::$logQueries || !\Config::$dbHost) {
			$nQueries = "Log Queries Off";
		} else {
			$nQueries = sizeof(Database::$queryLog) . " Queries";
		}
		$executionTime = sprintf("%0.4fs", Log::getElapsedTime($tend));

		print "<div id=\"debug_preview\" style=\"background:#CCC; color:#335533; padding:2px 10px; font-size: 13px; line-height: 18px; height: 18px; cursor:pointer\" onclick=\"if (window.jQuery) $('#debug_details').toggle(500); else document.getElementById('debug_details').style.display='';\">";
		print "Debug Log (<span" . ($nErrors > 0 ? ' style="color:#C20; font-weight:bold;"' : '') . ">$nErrors Errors</span> | $nQueries | Execution Time: $executionTime)";
		print "</div>\n";
		print "<div id=\"debug_details\" style=\"display:none; background:#EEE; padding:1em 0;\">\n";
		print "<div id=\"debug_info\" style=\"font-family: serif; margin-left: 1em; padding-left: 1em;\">";

		// Errors from the error handler
		print "<h3>Errors</h3>\n";
		if (sizeof(Log::$errors) > 0) {
			foreach (Log::$errors as $e) {
				printf("%.4fs: %s<br />\n", $e['time'], $e['error']);
			}
		} else {
			print "0 errors.<br />\n";
		}
		print "<br />\n";

		// print queries n such
		if (\Config::$logQueries && \Config::$dbHost) {
			print "<h3>SQL</h3>\n";
			$totalt = 0;
			foreach (Database::$queryLog as $q) {
				$totalt += $q['etime'] - $q['stime'];
			}
			$ne = Database::$errorCount;
			print "executed " . sizeof(Database::$queryLog) . " queries in " . number_format($totalt, 4) . "s with $ne error(s): <a href=\"javascript:;\" onclick=\"document.getElementById('dbg_allqueries').style.display='';this.style.display='none';\">show all</a><br />";
			print "<div id=\"dbg_allqueries\" style=\"display: none;\">\n";
			$i = 1;
			foreach (Database::$queryLog as &$q) {
				$query = Database::queryToHTML($q['query']);
				print "<p>" . $i++ . ": " . number_format($q['etime'] - $q['stime'], 4) . "s<br /><span>$query</span><br />";
				if (isset($q['error'])) {
					print "Error: " . $q['error'];
				}
				print "</p>\n";
			}
			print "</div>";
			print "<br />\n";
		}

		// performance information
		print "<h3>Performance Information:</h3>\n";
		print "execution time: " . number_format(Log::getElapsedTime($tend), 4) . "s<br />\n";
		print "end memory usage: " . number_format(memory_get_usage()) . " bytes<br />\n";
		if (function_exists('memory_get_peak_usage')) {
			print "peak memory usage: " . number_format(memory_get_peak_usage()) . " bytes<br />\n";
		}
		print "<br />\n";

		// files
		print "<h3>Files</h3>\n";
		$ifiles = get_included_files();
		print "used " . sizeof($ifiles) . " files<br />";
		print "<div><a href=\"javascript:;\" style=\"margin-left: 1em;\" onclick=\"document.getElementById('dbg_usedfiles').style.display='';this.style.display='none';\">show files</a>";
		print "<ul id=\"dbg_usedfiles\" style=\"display: none;\">\n";
		foreach ($ifiles as $f) {
			print "	<li>$f</li>\n";
		}
		print "</ul></div>\n";

		print "<br /><h3>Log:</h3>";
		if (sizeof(Log::$log) > 0) {
			foreach (Log::$log as $l) {
				printf("%.4fs [%s]: %s", $l['time'], Log::formatMemory($l['mem']), $l['title']);
				print "<br />\n";
			}
		}
		print "<br />php version: " . phpversion() . "<br />\n";
		print "</p>\n";
		print "</div></div>\n";
	}
}

// initialize the log automatically
Log::initialize();
?>