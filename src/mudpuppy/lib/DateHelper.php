<?php
defined('MUDPUPPY') or die('Restricted');

class DateHelper {
	public static function firstDayOfMonth($date) {
		return strtotime(date('m/\\0\\1/Y', $date));
	}

	public static function lastDayOfMonth($date) {
		$first = self::firstDayOfMonth($date);
		$d = new DateTime();
		$d->setTimestamp($date);
		$d->add(new DateInterval('P1M'));
		$d->sub(new DateInterval('P1D'));
		return $d->getTimestamp();
	}

	public static function isValidPHPTimeStamp($timestamp) {
		return ((string)(int)$timestamp === $timestamp)
		&& bccomp($timestamp, PHP_INT_MAX) <= 0
		&& bccomp($timestamp, ~PHP_INT_MAX) >= 0;
	}

}

?>