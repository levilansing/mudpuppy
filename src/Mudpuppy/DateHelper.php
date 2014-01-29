<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy;

defined('MUDPUPPY') or die('Restricted');

class DateHelper {

	/**
	 * get the first day of the month represented by $date
	 * @param $date
	 * @return int
	 */
	public static function firstDayOfMonth($date) {
		return strtotime(date('m/\\0\\1/Y', $date));
	}

	/**
	 * get the last day of the month represented by $date
	 * @param $date
	 * @return int
	 */
	public static function lastDayOfMonth($date) {
		$first = self::firstDayOfMonth($date);
		$d = new \DateTime();
		$d->setTimestamp($date);
		$d->add(new \DateInterval('P1M'));
		$d->sub(new \DateInterval('P1D'));
		return $d->getTimestamp();
	}

	/**
	 * verify a timestamp/date is a valid PHP date/timestamp
	 * @param $timestamp
	 * @return bool
	 */
	public static function isValidPHPTimeStamp($timestamp) {
		return ((string)(int)$timestamp === (string)$timestamp)
		&& bccomp($timestamp, PHP_INT_MAX) <= 0
		&& bccomp($timestamp, ~PHP_INT_MAX) >= 0;
	}

}

?>