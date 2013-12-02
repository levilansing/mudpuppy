<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy;
use App\Config;

defined('MUDPUPPY') or die('Restricted');
MPAutoLoad('Mudpuppy\DateHelper');

// dataTypes that can be used with db when using automated functions
define('DATATYPE_BOOL', 0);
define('DATATYPE_TINYINT', 1);
define('DATATYPE_INT', 2);
define('DATATYPE_FLOAT', 3);
define('DATATYPE_DOUBLE', 4);
define('_DATATYPE_END_NUMERIC', 5);
define('DATATYPE_CHAR', 6);
define('DATATYPE_DECIMAL', 7);
define('DATATYPE_STRING', 8);
define('DATATYPE_TEXT', 9);
define('DATATYPE_MEDIUMTEXT', 10);
define('DATATYPE_LONGTEXT', 11);
define('DATATYPE_JSON', 12);
define('DATATYPE_DATETIME', 13);
define('DATATYPE_DATE', 14);

class DBColumnValue {

	var $column;
	var $dataType;
	var $value;

	function __construct($column, $dataType, $value) {
		$this->column = $column;
		$this->dataType = $dataType;
		$this->value = $value;
	}

	function setColumn($column) {
		$this->column = $column;
	}

	function setDataType($dataType) {
		$this->dataType = $dataType;
	}

	function setValue($value) {
		$this->value = $value;
	}

	function getColumn() {
		return $this->column;
	}

	function getDataType() {
		return $this->dataType;
	}

	function getValue() {
		return $this->value;
	}

	function isNull() {
		return is_null($this->value);
	}

}

class Database {

	/** @var \PDO */
	private $pdo = null;
	static $queryLog = array();
	static $errorCount = 0;
	var $prefix = "";

	/** @var \PDOStatement */
	var $lastResult = null;
	/** @var \PDOStatement */
	var $statement = null;

	function __construct() {
	}

	function connect($server, $port, $database, $user, $pass) {
		try {

			$this->pdo = new \PDO(sprintf(Config::$dbProtocol, $server, $port, $database), $user, $pass, array(
				\PDO::ATTR_AUTOCOMMIT => true
			));
			// PDO doesn't default to the requested database automatically with MSSQL
			$this->pdo->query("USE $database; SET sql_mode = 'TRADITIONAL';");
		} catch (\PDOException $e) {
			Log::error("DB Connection failed:" . $e->getMessage());
			return false;
		}
		return true;
	}

	/**
	 *
	 * @param $query
	 * @return \PDOStatement
	 */
	function prepare($query) {
		$this->statement = $this->pdo->prepare($query);
		return $this->statement;
	}

	/**
	 * begin a transaction on the PDO connection
	 * @return bool success
	 */
	function beginTransaction() {
		if (Config::$debug && Config::$logQueries) {
			Database::$queryLog[] = array('stime' => Log::getElapsedTime(), 'query' => 'PDO::beginTransaction()');
		}
		$result = $this->pdo->beginTransaction();
		Database::$queryLog[sizeof(Database::$queryLog) - 1]['etime'] = Log::getElapsedTime();
		return $result;
	}

	/**
	 * cancel a transaction
	 * @return bool success
	 */
	function rollBackTransaction() {
		if (!$this->pdo->inTransaction()) {
			return false;
		}
		if (Config::$debug && Config::$logQueries) {
			Database::$queryLog[] = array('stime' => Log::getElapsedTime(), 'query' => 'PDO::rollback()');
		}
		$result = $this->pdo->rollBack();
		Database::$queryLog[sizeof(Database::$queryLog) - 1]['etime'] = Log::getElapsedTime();
		return $result;
	}

	/**
	 * cancel a transaction
	 * @return bool success
	 */
	function commitTransaction() {
		if (!$this->pdo->inTransaction()) {
			return false;
		}
		if (Config::$debug && Config::$logQueries) {
			Database::$queryLog[] = array('stime' => Log::getElapsedTime(), 'query' => 'PDO::commit()');
		}

		$result = $this->pdo->commit();
		Database::$queryLog[sizeof(Database::$queryLog) - 1]['etime'] = Log::getElapsedTime();
		return $result;
	}

	/**
	 * perform the specified query, or query the prepared statement if $query = NULL
	 * @param string $query
	 * @return \PDOStatement
	 */
	function query($query = null) {
		try {
			//$query = str_replace("#__",$this->prefix,$query);
			$bUseStatement = false;
			if ($query == null) {
				$bUseStatement = true;
				if (!$this->statement) {
					throw new \Exception('Prepared Statement does not exist', 0);
				}
				$query = $this->statement->queryString;
			}

			if (Config::$debug && Config::$logQueries) {
				Database::$queryLog[] = array('stime' => Log::getElapsedTime(), 'query' => $query);
			}

			if ($bUseStatement) {
				$this->statement->execute();
				$this->lastResult = $this->statement;
				$this->statement = null;
			} else {
				$this->lastResult = $this->pdo->query($query);
			}

			if (Config::$debug && Config::$logQueries) {
				Database::$queryLog[sizeof(Database::$queryLog) - 1]['etime'] = Log::getElapsedTime();
			}

			if ($this->hasError()) {
				$error = $this->getLastError();
				Log::error($error);
				if (Config::$debug && Config::$logQueries) {
					Database::$queryLog[sizeof(Database::$queryLog) - 1]['error'] = $error;
					Database::$errorCount++;
				} else if (Config::$debug) {
					Log::error('SQL Error: ' . $error);
					Database::$errorCount++;
				}
				return false;
			}

			return $this->lastResult;
		} catch (\Exception $e) {
			$file = $e->getFile();
			$line = $e->getLine();
			$message = $e->getMessage();
			Log::error("Error: <b>$message</b> in $file on line $line");
		}
		return false;
	}

	/**
	 * @param \PDOStatement $query
	 * @param null $argArray
	 * @return bool
	 */
	function execute($query, $argArray = null) {
		try {

			if (!$query) {
				throw new \Exception('Prepared Statement does not exist', 0);
			}

			if (Config::$debug && Config::$logQueries) {
				Database::$queryLog[] = array('stime' => Log::getElapsedTime(), 'query' => $query->queryString);
				if ($argArray != null) {
					Database::$queryLog[] = array('stime' => Log::getElapsedTime(), 'query' => json_encode($argArray));
				}
			}
			if ($argArray != null) {
				$query->execute($argArray);
			} else {
				$query->execute();
			}

			if (Config::$debug && Config::$logQueries) {
				Database::$queryLog[sizeof(Database::$queryLog) - 1]['etime'] = Log::getElapsedTime();
			}

			if ($this->hasError()) {
				$error = $this->getLastError();
				Log::error($error);
				if (Config::$debug && Config::$logQueries) {
					Database::$queryLog[sizeof(Database::$queryLog) - 1]['error'] = $error;
					Database::$errorCount++;
				} else if (Config::$debug) {
					Log::error('SQL Error: ' . $error);
					Database::$errorCount++;
				}
				return false;
			}

			return $query;
		} catch (\Exception $e) {
			$file = $e->getFile();
			$line = $e->getLine();
			$message = $e->getMessage();
			Log::error("Error: <b>$message</b> in $file on line $line");
		}
		return false;
	}

	/**
	 * fetch the first value of the next result
	 * @return int|null
	 */
	function fetchFirstValue() {
		if (!$this->lastResult) {
			return null;
		}
		$row = $this->lastResult->fetch(\PDO::FETCH_NUM);
		if (!empty($row)) {
			return $row[0];
		}
		return null;
	}

	/**
	 * fetch into a data object of type $type
	 * @param $type string Class name of the data object
	 * @return null
	 */
	function fetchObject($type) {
		if (!$this->lastResult) {
			return null;
		}
		$row = $this->lastResult->fetch(\PDO::FETCH_ASSOC);
		if (!empty($row)) {
			return new $type($row);
		}
		return null;
	}

	/**
	 * perform an assoc fetch (required for DataObject)
	 * @param \PDOStatement $result
	 * @return mixed
	 */
	function fetchRowAssoc($result = -1) {
		if ($result === -1) {
			$result = $this->lastResult;
		}
		if (!$result) {
			return null;
		}
		return $result->fetch(\PDO::FETCH_ASSOC);
	}

	function fetchAllWithKey($type, $key, $result = -1) {
		if ($result === -1) {
			$result = $this->lastResult;
		}
		if (!$result) {
			return array();
		}

		$list = array();
		if ($type == 'array') {
			while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
				$list[$row[$key]] = $row;
			}
		} else {
			while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
				$list[$row[$key]] = new $type($row);
			}
		}
		return $list;
	}

	function fetchAll($type, $result = -1) {
		if ($result === -1) {
			$result = $this->lastResult;
		}
		if (!$result) {
			return array();
		}

		$list = array();
		if ($type == 'array') {
			while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
				$list[] = $row;
			}
		} else {
			while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
				$list[] = new $type($row);
			}
		}
		return $list;
	}

	function hasError() {
		return ($this->pdo->errorCode() != "00000") || ($this->lastResult && $this->lastResult->errorCode() != "00000");
	}

	function getLastError() {
		if ($this->lastResult && $this->lastResult->errorCode() != '00000') {
			$info = $this->lastResult->errorInfo();
		} else {
			$info = $this->pdo->errorInfo();
		}
		if ($info) {
			return $info[0] . ':' . $info[1] . ': ' . $info[2];
		}
		return '';
	}

	function numRows($result = -1) {
		if ($result === -1) {
			$result = $this->lastResult;
		}
		if (!$result) {
			return 0;
		}
		return $result->rowCount();
	}

	/**
	 * get the PDO object for this database connection
	 * @return \PDO
	 */
	function getPDO() {
		return $this->pdo;
	}

	////////////////////
	// fully automated functions for DataObject

	private function genSelect($fields, $table, $where = null, $order = null, $limit = null, $offset = null) {
		if (is_array($fields)) {
			$f = "`$fields[0]`";
			for ($i = 1; $i < sizeof($fields); $i++) {
				$f .= ",`$fields[$i]`";
			}
			$fields = $f;
		}

		$query = "SELECT $fields FROM $this->prefix$table";
		if (!is_null($where)) {
			$query .= " WHERE $where";
		}
		if (!is_null($order)) {
			$query .= " ORDER BY $order";
		}
		if (!is_null($limit)) {
			$query .= " LIMIT $limit";
		}
		if (!is_null($offset)) {
			$query .= " OFFSET $offset";
		}

		return $query;
	}

	function select($fields, $table, $where = null, $order = null, $limit = null, $offset = null) {
		$query = $this->genSelect($fields, $table, $where, $order, $limit, $offset);
		$this->query($query);

		return $this->lastResult;
	}

	/**
	 * @todo switch to prepared statements
	 * perform an insert using column values from a dataobject
	 * @param string $table
	 * @param DBColumnValue[] $columnValues
	 * @throws MudpuppyException
	 * @return int the inserted id OR false if failed
	 */
	function insert($table, $columnValues) {
		if (sizeof($columnValues) == 0) {
			throw new MudpuppyException("error: database::add - 2nd parameter colvals is of size 0<br />\n");
		}

		// insert, return new id
		$query = "INSERT INTO `$this->prefix$table` (";
		$queryp2 = " VALUES(";
		foreach ($columnValues as $val) {
			$col = $val->getColumn();
			$query .= "`$col`,";
			$type = $val->getDataType();
			if ($val->isNull()) {
				$queryp2 .= 'NULL,';
			} else if ($type < _DATATYPE_END_NUMERIC) {
				$queryp2 .= $this->formatNumber($val->getValue()) . ',';
			} else if ($type == DATATYPE_DATETIME) {
				$queryp2 .= self::formatDateAndEscape($val->getValue()) . ',';
			} else if ($type == DATATYPE_DATE) {
				$queryp2 .= self::formatDateAndEscape($val->getValue(), false) . ',';
			} else if ($type == DATATYPE_JSON) {
				$value = $val->getValue();
				if (empty($value)) {
					$queryp2 .= 'NULL,';
				} else if (is_string($val->getValue())) {
					$queryp2 .= $this->formatString($val->getValue()) . ',';
				} else {
					$queryp2 .= $this->formatString(json_encode($val->getValue())) . ',';
				}
			} else {
				$queryp2 .= $this->formatString($val->getValue()) . ',';
			}
		}
		$query = substr($query, 0, strlen($query) - 1) . ")";
		$query .= substr($queryp2, 0, strlen($queryp2) - 1);
		$query .= ")";

		try {
			$this->query($query);

			if (!$this->hasError()) {
				// get generated id
				$this->query("SELECT LAST_INSERT_ID()");
				if ($this->lastResult && $row = $this->lastResult->fetch(\PDO::FETCH_NUM)) {
					return $row[0];
				}
			}

			throw new \Exception("database::insert failed.");
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * @todo switch to prepared statements
	 * @param $table
	 * @param DBColumnValue[] $colvals
	 * @param null $where
	 * @return \PDOStatement
	 */
	function update($table, $colvals, $where = null) {
		$query = "UPDATE `$this->prefix$table` SET ";
		foreach ($colvals as $val) {
			$col = $val->getColumn();
			$type = $val->getDataType();
			if ($val->isNull()) {
				$query .= "`$col`=" . 'NULL,';
			} else if ($type <= _DATATYPE_END_NUMERIC) {
				$query .= "`$col`=" . $this->formatNumber($val->getValue()) . ',';
			} else if ($type == DATATYPE_DATETIME) {
				$query .= "`$col`=" . self::formatDateAndEscape($val->getValue()) . ',';
			} else if ($type == DATATYPE_DATE) {
				$query .= "`$col`=" . self::formatDateAndEscape($val->getValue(), false) . ',';
			} else if ($type == DATATYPE_JSON) {
				$value = $val->getValue();
				if (empty($value)) {
					$query .= "`$col`=" . 'NULL,';
				} else if (is_string($val->getValue())) {
					$query .= "`$col`=" . $this->formatString($val->getValue()) . ',';
				} else {
					$query .= "`$col`=" . $this->formatString(json_encode($val->getValue())) . ',';
				}
			} else {
				$query .= "`$col`=" . $this->formatString($val->getValue()) . ',';
			}
		}
		$query = substr($query, 0, strlen($query) - 1);
		if ($where) {
			$query .= " WHERE $where";
		}

		return $this->query($query);
	}

	function delete($table, $where = null) {
		$query = "DELETE FROM `$this->prefix$table`";
		if ($where) {
			$query .= " WHERE $where";
		}

		return $this->query($query);
	}

	public static function readDate($mysqltime, $time = true) {
		if (strncmp($mysqltime, '0000-00-00', 10) == 0) {
			return 0;
		}
		if ($time) {
			return strtotime($mysqltime . ' GMT');
		}
		return strtotime($mysqltime);
	}

	public static function formatDateAndEscape($datetime, $time = true) {
		return "'" . self::formatDate($datetime, $time) . "'";
	}

	public static function formatDate($datetime, $time = true) {
		if (DateHelper::isValidPHPTimeStamp($datetime)) {
			$datetime = (int)$datetime;
		}

		if (is_string($datetime)) {
			$datetime = strtotime($datetime);
		}

		if (!$datetime) {
			return null;
		} // MSSQL: "0000-00-00";

		if ($time) {
			return gmdate("Y-m-d H:i:s", $datetime);
		}
		return date("Y-m-d", $datetime);
	}

	// make a string query friendly
	function escapeString($string) {
		$str = $this->pdo->quote($string);
		return substr($str, 1, strlen($str) - 2);
	}

	function formatString($string) {
		return $this->pdo->quote($string);
	}

	// make sure a number is really a number
	function formatNumber($number) {
		if ($number == 'NULL' || is_int($number) || is_float($number) || is_double($number)) {
			return $number;
		}

		if (is_bool($number)) {
			return ($number ? 1 : 0);
		}

		if (preg_match('#^[\+\-]?[0-9]+$#i', $number, $matches) > 0) {
			return intval($number);
		}

		return doubleval($number);
	}

	static function queryToHTML($query) {
		$q = preg_replace('#(\sFROM\s|\sWHERE\s|\sORDER BY\s|\sVALUES)#i', "<br />\n&nbsp; &nbsp; $1", htmlentities($query));
		return "&nbsp; &nbsp; $q";
	}

}

?>