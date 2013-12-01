<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy;

defined('MUDPUPPY') or die('Restricted');

class Ldap {

	public $conn = null;
	private $host = null;
	public $baseDn = '';
	private $error = null;

	public function connect($hostname, $user = null, $pass = null) {
		$this->host = $hostname;
		$this->baseDn = 'DC=' . implode(',DC=', explode('.', $hostname));

		$this->conn = ldap_connect($hostname);
		if (!$this->conn) {
			$this->checkConnForErrors();
			$this->error = "Failed to connect to LDAP Server at $hostname. " . $this->error;
			return false;
		}
		ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($this->conn, LDAP_OPT_REFERRALS, 0);

		if ($user && $pass) {
			return $this->bind($user, $pass);
		}
		return true;
	}

	public function bind($user, $pass) {
		if (!$this->conn) {
			$this->error = 'No active connection to LDAP server while attempting bind';
			return false;
		}
		if (!ldap_bind($this->conn, $user, $pass)) {
			$this->checkConnForErrors();
			return false;
		}
		return true;
	}

	private function checkConnForErrors() {
		if (ldap_errno($this->conn)) {
			$this->error = ldap_error($this->conn);
			return true;
		}
		$this->error = null;
		return false;
	}

	public function hasError() {
		return $this->error != null;
	}

	public function getLastError() {
		return $this->error;
	}

	/**
	 * perform multiple searches and combine the results using the key field
	 * @param array $searchFilters
	 * @param array $attributes
	 * @param string $keyField
	 * @return array
	 */
	public function multiQuery($searchFilters, $attributes, $keyField) {
		$allResults = array();
		$errors = array();

		foreach ($searchFilters as $searchFilter) {
			$results = $this->query($searchFilter, $attributes, $keyField);
			if ($this->hasError()) {
				$errors[] = $this->getLastError();
			}
			foreach ($results as $key => $entry) {
				$allResults[$key] = $entry;
			}
		}
		if (count($errors) > 0) {
			$this->error = implode("\n", $errors);
		}
		return $allResults;
	}

	/**
	 * perform a search on the active LDAP connection and return an array optionally keyed by $keyField
	 * if keyed, any results without the key field will be ignored
	 * @param string $searchFilter
	 * @param string $attributes
	 * @param string $keyField
	 * @return array
	 */
	public function query($searchFilter, $attributes, $keyField = null) {
		$result = ldap_search($this->conn, $this->baseDn, $searchFilter, $attributes);
		$this->checkConnForErrors();

		$entries = ldap_get_entries($this->conn, $result);

		if (is_null($keyField) || strlen($keyField) == 0) {
			return $entries;
		}

		$keyedData = array();
		if ($entries) {
			foreach ($entries as $entry) {
				$field = self::getFirstValue($entry, $keyField);
				if ($field != null) {
					$keyedData[$field] = $entry;
				}
			}
		}
		return $keyedData;
	}

	public static function getFirstValue($entry, $key) {
		if (isset($entry[$key])) {
			$field = $entry[$key];
			if (is_array($field) && count($field) > 0) {
				return $field[0];
			} else if (!is_array($field)) {
				return $field;
			}
		}
		return null;
	}

	public static function getArrayValue($entry, $key) {
		$value = array();
		if (isset($entry[$key])) {
			$field = $entry[$key];
			if (is_array($field)) {
				if (isset($field['count'])) {
					unset($field['count']);
				}
				return $field;
			}
			return array($field);
		}
		return array();
	}

	public static function ldapTimestampToDate($timestamp) {
		if ($timestamp == 0) {
			return null;
		}
		return bcsub(bcdiv($timestamp, 10000000, 0), 11644473600, 0);
	}

	public static function dateToLdapTimestamp($unix_timestamp) {
		$unix_timestamp += 11644473600.0;
		$str = '';
		while ($unix_timestamp > 0.9999999) {
			$str = ($unix_timestamp % 10) . $str;
			$unix_timestamp /= 10;
		}
		return $str . '0000000';
	}

	public static function dateToLdapDatestamp($date) {
		return gmdate('YmdHis.0\Z', $date);
	}

	public static function ldapDatestampToDate($datestamp) {
		return strtotime(substr($datestamp, 0, 4) . '-' . substr($datestamp, 4, 2) . '-' . substr($datestamp, 6, 2)
			. ' ' . substr($datestamp, 8, 2) . ':' . substr($datestamp, 10, 2) . ':' . substr($datestamp, 12, 2) . ' GMT');
	}

}

?>