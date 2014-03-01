<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy;

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
define('DATATYPE_BINARY', 9);
// removed 10 & 11
define('DATATYPE_JSON', 12);
define('DATATYPE_DATETIME', 13);
define('DATATYPE_DATE', 14);

define('DB_MAX_LOG_VALUE_LENGTH', 100);

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

	public static $queryLog = array();
	public static $additionalQueryLogs = 0;
	const LOG_LIMIT = 999;

	static $errorCount = 0;

	/** @var \PDOStatement */
	private $lastResult = null;
	/** @var \PDOStatement */
	private $statement = null;

	private $boundParams = null;

	/**
	 * Connect to a database
	 * @param string $server name of the server
	 * @param int $port server port
	 * @param string $database name of database to use
	 * @param string $user username for authentication
	 * @param string $pass password for authentication
	 * @return bool
	 */
	public function connect($server, $port, $database, $user, $pass) {
		try {

			$this->pdo = new \PDO(sprintf(Config::$dbProtocol, $server, $port, $database), $user, $pass, array(
				\PDO::ATTR_AUTOCOMMIT => true
			));

			$now = new \DateTime();
			$min = $now->getOffset() / 60;
			$sgn = ($min < 0 ? -1 : 1);
			$min = abs($min);
			$hrs = floor($min / 60);
			$min -= $hrs * 60;
			$offset = sprintf('%+d:%02d', $hrs * $sgn, $min);

			// PDO doesn't default to the requested database automatically with MSSQL
			$this->pdo->query("SET time_zone='$offset'; USE $database; SET sql_mode = 'TRADITIONAL';");

		} catch (\PDOException $e) {
			Log::error("DB Connection failed:" . $e->getMessage());
			return false;
		}
		return true;
	}

	private static function logQueryStart($query) {
		if (count(Database::$queryLog) >= self::LOG_LIMIT) {
			self::$additionalQueryLogs++;
		}
		Database::$queryLog[] = array('stime' => Log::getElapsedTime(), 'query' => $query);
	}

	private static function logQueryEndTime() {
		if (count(Database::$queryLog) == 0 || count(Database::$queryLog) > self::LOG_LIMIT) {
			return;
		}
		Database::$queryLog[count(Database::$queryLog) - 1]['etime'] = Log::getElapsedTime();
	}

	private static function logQueryError($error) {
		if (count(Database::$queryLog) == 0 || count(Database::$queryLog) > self::LOG_LIMIT) {
			Log::error($error);
			return;
		}
		Database::$queryLog[sizeof(Database::$queryLog) - 1]['error'] = $error;
	}

	private $pdoTypeList = [
		\PDO::PARAM_BOOL => 'PARAM_BOOL',
		\PDO::PARAM_INT => 'PARAM_INT',
		\PDO::PARAM_LOB => 'PARAM_LOB',
		\PDO::PARAM_NULL => 'PARAM_NULL',
		\PDO::PARAM_STMT => 'PARAM_STMT',
		\PDO::PARAM_STR => 'PARAM_STR'
	];

	/**
	 * Record to the log a parameter that is being bound to a PDO statement
	 * @param string|int $label index of use or identifier used in the statement
	 * @param mixed $value the value being bound
	 * @param int $dataType PDO::TYPE_ type
	 */
	public function logBindParam($label, $value, $dataType) {
		if (!$this->boundParams) {
			$this->boundParams = [];
		}

		if (is_null($value)) {
			$displayValue = 'NULL';
		} else if (is_numeric($value)) {
			$displayValue = $value;
		} else {
			if (strlen($value) > DB_MAX_LOG_VALUE_LENGTH) {
				$displayValue = '"' . substr($value, 0, DB_MAX_LOG_VALUE_LENGTH) . '" [TRUNCATED FROM LENGTH' . strlen($value) . ']';
			} else {
				$displayValue = '"' . $value . '"';
			}
		}

		$displayValue .= ' (';
		if ($dataType & \PDO::PARAM_INPUT_OUTPUT) {
			$displayValue .= 'PARAM_INPUT_OUTPUT|';
			$dataType &= ~\PDO::PARAM_INPUT_OUTPUT;
		}
		if (isset($this->pdoTypeList[$dataType])) {
			$displayValue .= $this->pdoTypeList[$dataType];
		}
		$displayValue .= ')';

		$this->boundParams[$label] = $displayValue;
	}

	/**
	 * Begin a transaction on the PDO connection
	 * @return bool success
	 */
	public function beginTransaction() {
		if (Config::$debug && Config::$logQueries) {
			self::logQueryStart('PDO::beginTransaction()');
		}
		$result = $this->pdo->beginTransaction();
		self::logQueryEndTime();
		return $result;
	}

	/**
	 * Cancel a transaction
	 * @return bool success
	 */
	public function rollBackTransaction() {
		if (!$this->pdo->inTransaction()) {
			return false;
		}
		if (Config::$debug && Config::$logQueries) {
			self::logQueryStart('PDO::rollback()');
		}
		$result = $this->pdo->rollBack();
		self::logQueryEndTime();
		return $result;
	}

	/**
	 * Commit a transaction
	 * @return bool success
	 */
	public function commitTransaction() {
		if (!$this->pdo->inTransaction()) {
			return false;
		}
		if (Config::$debug && Config::$logQueries) {
			self::logQueryStart('PDO::commit()');
		}

		$result = $this->pdo->commit();
		self::logQueryEndTime();
		return $result;
	}

	/**
	 * Prepare a statement using PDO
	 * @param string $query The sql statement to be prepared for execution
	 * @param array $driverOptions optional PDO driver options
	 * @return bool
	 */
	public function prepare($query, array $driverOptions = []) {
		$this->boundParams = null;
		$this->statement = $this->pdo->prepare($query, $driverOptions);
		return $this->statement ? true : false;
	}

	/**
	 * Execute the current prepared statement
	 * @param array|null $bindArray optional array of values or associative array of [named parameter => value] to bind
	 * @return \PDOStatement|bool
	 */
	public function execute($bindArray = null) {
		try {

			if (Config::$debug && Config::$logQueries) {
				if ($bindArray != null) {
					foreach ($bindArray as $label => $value) {
						if (is_null($value) || is_int($value)) {
							$this->logBindParam((is_int($label) ? $label + 1 : $label), $value, \PDO::PARAM_INT);
						} else {
							$this->logBindParam((is_int($label) ? $label + 1 : $label), $value, \PDO::PARAM_STR);
						}
					}
				}
				self::logQueryStart($this->statement->queryString . "\n" . json_encode($this->boundParams, JSON_PRETTY_PRINT));

			}
			if ($bindArray != null) {
				$this->statement->execute($bindArray);
			} else {
				$this->statement->execute();
			}

			if (Config::$debug && Config::$logQueries) {
				self::logQueryEndTime();
			}
			$this->lastResult = $this->statement;

			if ($this->hasError()) {
				$error = $this->getLastError();
				Log::error($error);
				if (Config::$debug && Config::$logQueries) {
					self::logQueryError($error);
					Database::$errorCount++;
				} else if (Config::$debug) {
					Log::error('SQL Error: ' . $error);
					Database::$errorCount++;
				}
				return false;
			}

			return $this->statement;
		} catch (\Exception $e) {
			Log::exception($e);
		}
		return false;
	}

	/**
	 * Get the last insert ID from PDO
	 * @param string $name
	 * @return int|null|string
	 */
	public function lastInsertId($name = null) {
		$id = $this->pdo->lastInsertId($name);
		if ($this->pdo->errorCode() == 'IM001') {
			$this->lastResult = $this->pdo->query('SELECT LAST_INSERT_ID()');
			return $this->fetchFirstValue();
		}
		return $id;
	}

	/**
	 * See PDOStatement::setAttribute
	 * @param int $attribute
	 * @param mixed $value
	 * @return bool
	 */
	public function setStatementAttribute($attribute, $value) {
		return $this->statement->setAttribute($attribute, $value);
	}

	/**
	 * See PDOStatement::getAttribute
	 * @param int $attribute
	 * @return mixed
	 */
	public function getStatementAttribute($attribute) {
		return $this->statement->getAttribute($attribute);
	}

	/**
	 * See PDOStatement::setFetchMode
	 * @param int $mode
	 * @return bool
	 */
	public function setStatementFetchMode($mode) {
		return call_user_func_array([$this->statement, 'setFetchMode'], func_get_args());
	}

	/**
	 * Bind a list of parameters using a list of types or PARAM_STR if types is null
	 * Params can be a 0 based list for ? params, or key value pare for named params (types must match keys of params)
	 * @param array $params
	 * @param int[] $types
	 * @return bool
	 * @throws MudpuppyException
	 */
	public function bindParams($params, $types=null) {
		if ($types && count($types) != count($params))
			throw new MudpuppyException('length of $params and $types must be the same');
		if (isset($params[0])) {
			for ($i=0; $i<count($params); $i++) {
				if (!$this->bindParam($i+1, $params[$i], $types ? $types[$i] : \PDO::PARAM_STR))
					return false;
			}
		} else {
			foreach ($params as $name=>$param) {
				if (!$this->bindParam($name, $params[$name], $types ? $types[$name] : \PDO::PARAM_STR))
					return false;
			}
		}
		return true;
	}

	/**
	 * Bind a param to the current pdo statement
	 * @param $parameter
	 * @param $variable
	 * @param int $data_type
	 * @param null $length
	 * @param null $driver_options
	 * @return bool
	 */
	public function bindParam($parameter, &$variable, $data_type = \PDO::PARAM_STR, $length = null, $driver_options = null) {
		// log parameter to db
		$this->logBindParam($parameter, $variable, $data_type);

		// forward call
		return $this->statement->bindParam($parameter, $variable, $data_type, $length, $driver_options);
	}

	/**
	 * Bind a value to the current pdo statement
	 * @param $parameter
	 * @param $value
	 * @param int $data_type
	 * @return bool
	 */
	public function bindValue($parameter, $value, $data_type = \PDO::PARAM_STR) {
		// log parameter to db
		$this->logBindParam($parameter, $value, $data_type);

		// forward call
		return $this->statement->bindValue($parameter, $value, $data_type);
	}

	/**
	 * Fetch the first value of the next result
	 * @return int|string|null
	 */
	public function fetchFirstValue() {
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
	 * Fetch one row into a data object of type $type or an associative array
	 * @param $type string Class name of the data object
	 * @return DataObject|array|null
	 */
	public function fetch($type = 'array') {
		if (!$this->lastResult) {
			return null;
		}
		$row = $this->lastResult->fetch(\PDO::FETCH_ASSOC);
		if (!empty($row)) {
			if ($type == 'array') {
				return $row;
			} else {
				return new $type($row);
			}
		}
		return null;
	}

	/**
	 * Fetch all results into data objects of type $type or an associative array
	 * @param $type string Class name of the data object
	 * @return DataObject[]|array
	 */
	public function fetchAll($type = 'array') {
		if (!$this->lastResult) {
			return array();
		}

		$list = array();
		if ($type == 'array') {
			while ($row = $this->lastResult->fetch(\PDO::FETCH_ASSOC)) {
				$list[] = $row;
			}
		} else {
			while ($row = $this->lastResult->fetch(\PDO::FETCH_ASSOC)) {
				$list[] = new $type($row);
			}
		}
		return $list;
	}

	public function hasError() {
		return ($this->pdo->errorCode() != "00000") || ($this->lastResult && $this->lastResult->errorCode() != "00000");
	}

	/**
	 * Get the error string from the most recent query if an error exists
	 * @return string
	 */
	public function getLastError() {
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

	/**
	 * Get the row count of hte most recent query
	 * @param $result
	 * @return int
	 */
	public function numRows($result = -1) {
		if ($result === -1) {
			$result = $this->lastResult;
		}
		if (!$result) {
			return 0;
		}
		return $result->rowCount();
	}

	/**
	 * Get the PDO object for this database connection
	 * warning: accessing PDO directly will not show any queries/errors in the logs
	 * @return \PDO
	 */
	public function getPDO() {
		return $this->pdo;
	}


	/**
	 * Convert a date string from MySQL into a PHP timestamp
	 * @param string $mysqlTime
	 * @param bool $time
	 * @return int
	 */
	public function readDate($mysqlTime, $time = true) {
		if (strncmp($mysqlTime, '0000-00-00', 10) == 0) {
			return 0;
		}
		// MySQL is set to the application's timezone, no need to convert between timezones
		return strtotime($time ? $mysqlTime : substr($mysqlTime, 0, 10));
	}

	/**
	 * @deprecated use a prepared statement!
	 * @param $datetime
	 * @param bool $time
	 * @return string
	 */
	public static function formatDateAndEscape($datetime, $time = true) {
		return "'" . App::getDBO()->formatDate($datetime, $time) . "'";
	}

	/**
	 * Format a PHP timestamp into a string MySQL recognizes
	 * @param $datetime
	 * @param bool $time
	 * @return bool|null|string
	 */
	public function formatDate($datetime, $time = true) {
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
			return date("Y-m-d H:i:s", $datetime);
		}
		return date("Y-m-d", $datetime);
	}

	/**
	 * make a string query friendly
	 * @deprecated use a prepared statement!
	 * @param $string
	 * @return string
	 */
	public function escapeString($string) {
		$str = $this->pdo->quote($string);
		return substr($str, 1, strlen($str) - 2);
	}

	/**
	 * make a string query friendly and surround with quotes
	 * @deprecated use a prepared statement!
	 * @param $string
	 * @return string
	 */
	public function formatString($string) {
		return $this->pdo->quote($string);
	}

	/**
	 * Make sure a number is really a number
	 * @deprecated use a prepared statement!
	 * @param $number
	 * @return float|int
	 */
	public function formatNumber($number) {
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

	public static function queryToHTML($query) {
		$q = preg_replace('#(\sFROM\s|\sWHERE\s|\sORDER BY\s|\sVALUES)#i', "<br />\n&nbsp; &nbsp; $1", htmlentities($query));
		return "&nbsp; &nbsp; $q";
	}

}

?>