<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy;

defined('MUDPUPPY') or die('Restricted');

// dataTypes defined in database.php
define("DATAFLAG_LOADED", 1);
define("DATAFLAG_CHANGED", 2);
define("DATAFLAG_NOTNULL", 4);

class DataValue {

	var $dataType;
	var $value;
	var $flags;
	var $maxLength;

	// set flag to loaded if sending value
	function __construct($dataType, $default = null, $notNull = false, $maxLength = 0) {
		$this->dataType = $dataType;
		$this->value = $default;
		$this->flags = (is_null($default) ? 0 : DATAFLAG_CHANGED) | ($notNull ? DATAFLAG_NOTNULL : 0);
		$this->maxLength = $maxLength;
	}

	function isUnloadedOrChanged() {
		return !($this->flags & (DATAFLAG_LOADED | DATAFLAG_CHANGED));
	}

	function isChanged() {
		return $this->flags & DATAFLAG_CHANGED;
	}

	function isLoaded() {
		return $this->flags & DATAFLAG_LOADED;
	}

}

class DataLookup {

	var $column;
	var $values = array();
	var $type;

	function __construct($type, $column) {
		$this->type = $type;
		$this->column = $column;
	}

	/**
	 * called from DataObject::__get($column)
	 * @param DataObject $dataObject the current data object
	 * @return DataObject
	 */
	function &performLookup($dataObject) {
		$id = (int)$dataObject->{$this->column};
		if (isset($this->values[$id])) {
			return $this->values[$id];
		}

		$this->values[$id] = call_user_func($this->type . '::fetchOne', $id);
		return $this->values[$id];
	}

	/**
	 * @param DataObject $dataObject
	 * @param DataObject $value
	 */
	function setLookup($dataObject, $value) {
		if (is_int($value)) {
			$dataObject->{$this->column} = $value;
		} else if ($value instanceof DataObject) {
			$dataObject->{$this->column} = $value->id;
			$this->values[$value->id] = $value;
		}
	}

	/**
	 * called from DataObject::__unset($column)
	 */
	function clearLookup($dataObject, $clearAll = false) {
		$id = $dataObject->{$this->column};
		if ($clearAll) {
			$this->values = array();
		} else {
			unset($this->values[$id]);
		}
	}

}

abstract class DataObject implements \JsonSerializable {

	/** @var DataValue[] $_lookup */
	protected $_data; // array of DataValue; key = col name
	/** @var DataValue[][] $_defaults */
	protected static $_defaults = array();
	/** @var DataLookup[][] $_lookup */
	protected static $_lookups = array();
	/** @var array $_extra */
	protected $_extra; // array of extra data found when loading rows.  this way queries can pull additional data from joined tables

	/**
	 * Create a new data object and initialize the data with $row
	 * @param $row array
	 */
	function __construct($row = null) {
		$this->_data = array();
		$this->_extra = array();
		$className = $this->getObjectName();
		if (!isset(self::$_defaults[$className])) {
			// maintain the default data (column values) statically
			self::$_defaults[$className] = array();
			self::$_lookups[$className] = array();
			$this->loadDefaults();
		}

		// clone from the default data- much faster than reloading the column values every time
		$def =& self::$_defaults[$className];
		foreach ($def as $k => &$d) {
			$this->_data[$k] = clone $d;
		}

		// load the data or set the id
		if (is_array($row)) {
			$this->loadFromRow($row);
		}
	}

	public static function ensureDefaults($dataObjectClassName) {
		if (!isset(self::$_defaults[$dataObjectClassName])) {
			new $dataObjectClassName();
		}
	}

	/////////////////////////////////////////////////////////////////
	// these functions are to be implemented by child class

	// should load column names with default data (id column required)
	// ex: $this->createColumn('id',DATATYPE_INT);
	abstract protected function loadDefaults();

	//
	//////////////////////////////////////////////////////////////////

	function __clone() {
		foreach ($this->_data as $k => $data) {
			$this->_data[$k] = clone $data;
		}
	}

	//////////////////////////////////////////
	// overridable functions

	protected function getObjectName() {
		return get_called_class();
	}

	/**
	 * @throws MudpuppyException default implementation
	 * @returns string
	 */
	public static function getTableName() {
		throw new MudpuppyException('Concrete DataObjects must override the static method getTableName');
	}

	//////////////////////////////////////////
	// the core

	/**
	 * ONLY CALL FROM loadDefaults() function
	 * Note: createColumn functions are now autogenerated from database schema,
	 * so we don't need to manage the default value manually anymore.
	 * Default value still exists for readability.
	 *
	 * @param $col
	 * @param $type
	 * @param null $dbDefault IGNORED allowing DB to set default value upon commit
	 * @param bool $notNull
	 */
	protected function createColumn($col, $type, $dbDefault = null, $notNull = false) {
		self::$_defaults[$this->getObjectName()][$col] = new DataValue($type, $dbDefault, $notNull);
	}

	/**
	 * ONLY CALL FROM loadDefaults() function
	 * Change the default value for a column.
	 *
	 * @param mixed $column
	 * @param string $default =null
	 */
	protected function updateColumnDefault($column, $default = null) {
		$col = self::$_defaults[$this->getObjectName()][$column];
		self::$_defaults[$this->getObjectName()][$column] = new DataValue($col->dataType, $default, $col->flags & DATAFLAG_NOTNULL);
	}

	function getColumns() {
		return array_keys($this->_data);
	}

	/**
	 * For internal use only
	 * @param $column
	 * @param $name
	 * @param $type
	 */
	protected function createLookup($column, $name, $type) {
		self::$_lookups[$this->getObjectName()][$name] = new DataLookup($type, $column);
	}

	/**
	 * get the column defaults for the specified class
	 * @param null $objectClass
	 * @return \Mudpuppy\DataValue[]
	 */
	protected static function getColumnDefaults($objectClass = null) {
		if (empty($objectClass)) {
			$objectClass = get_called_class();
		}
		if (empty(self::$_defaults[$objectClass])) {
			new $objectClass();
		}

		return self::$_defaults[$objectClass];
	}

	/**
	 * get the id of this object
	 * @return int
	 */
	function getId() {
		return (int)$this->id;
	}

	/**
	 * save changes to database (if there are any)
	 * update id if this is an insert
	 * @throws DatabaseException if error occurs when updating or saving data
	 * @return bool
	 */
	function save() {
		$id = $this->getId();
		$fields = array();
		foreach ($this->_data as $col => &$value) {

			// @todo for JSON, encode json object and check if it has changed instead of requiring mark change

			if (($value->flags & DATAFLAG_CHANGED)) // only save/update values that have been changed
			{
				$fields[] = new DBColumnValue($col, $value->dataType, $value->value);
			}
		}
		if (sizeof($fields) == 0 && $id != 0) {
			return true;
		}

		// insert or update database
		if (!$this->insertOrUpdate($fields)) {
			throw new DatabaseException('Database error when attempting to save data object');
		}

		// the save was successful, remove changed flag
		foreach ($this->_data as &$value) {
			if (($value->flags & DATAFLAG_CHANGED)) {
				$value->flags &= ~DATAFLAG_CHANGED;
			}
		}

		return true;
	}

	/**
	 * Copy a data object into a new object with id=0
	 * @return DataObject
	 */
	function copy() {
		$copy = clone $this;
		$copy->id = 0;
		// set changed flags for all values
		foreach ($copy->_data as &$value) {
			$value->flags |= DATAFLAG_CHANGED;
		}

		return $copy;
	}

	/**
	 * Reload any changed fields from the database
	 */
	function reload() {
		$fields = array();
		foreach ($this->_data as $col => $value) {
			if ($value->flags & DATAFLAG_CHANGED) // only reload values that have been changed
			{
				$fields[] = $col;
			}
		}

		if (sizeof($fields) > 0) {
			$this->load($fields);
		}
	}

	/**
	 * load the specified columns (or all if not specified) from the database
	 * assumes the data object has an id
	 * @param array $cols
	 * @return bool
	 * @throws MudpuppyException if id=0 or columns are requested that do not exist
	 */
	function load($cols = array()) {
		if (!is_array($cols)) {
			$cols = array($cols);
		}
		$id = $this->getId();
		// make sure we have an id
		if ($id == 0) {
			throw new MudpuppyException("Assertion Error: Cannot load a data object (" . get_called_class() . ") with an id of 0");
		}

		// verify we asked only for existing columns
		foreach ($cols as $col) {
			if (!isset($this->_data[$col])) {
				throw new MudpuppyException("Column '$col' of DataObject '" . $this->getObjectName() . "' not defined in load().");
			}
		}

		// load all columns if not specified
		if (sizeof($cols) == 0) {
			$cols = array_keys($this->_data);
		}

		$bNeedLoad = false;
		foreach ($cols as $col) {
			$f = $this->_data[$col]->flags;
			if (!($f & DATAFLAG_LOADED) || ($f & DATAFLAG_CHANGED)) {
				$bNeedLoad = true;
				break;
			}
		}

		if ($bNeedLoad) {
			$db = App::getDBO();
			$db->prepare('SELECT `' . implode('`,`', $cols) . '` FROM ' . static::getTableName() . ' WHERE id=?');
			$db->bindValue(1, $id, \PDO::PARAM_INT);
			$db->execute();

			if ($row = $db->fetch()) {
				$this->loadFromRow($row);
			} else {
				return false;
			}
		}
		return true;
	}

	/**
	 * load any columns that have not yet been loaded
	 * @return bool
	 */
	public function loadMissing() {
		$cols = array();
		foreach ($this->_data as $k => $data) {
			if ($data->isUnloadedOrChanged()) {
				$cols[] = $k;
			}
		}
		if (sizeof($cols) == 0) {
			return true;
		}
		return $this->load($cols);
	}

	/**
	 * Delete the row from the database
	 * @return bool
	 * @throws DatabaseException if delete fails or if id == 0
	 */
	public function delete() {
		if ($this->getId() == 0) {
			throw new DatabaseException('Attempting to delete item with id 0 (DNE)');
		}

		$db = App::getDBO();
		$db->prepare('DELETE FROM ' . static::getTableName() . ' WHERE id=?');
		$db->bindValue(1, $this->getId(), \PDO::PARAM_INT);
		if ($db->execute()) {
			// the delete was successful
			// set the id to 0 because it's been deleted
			$this->id = 0;
			foreach ($this->_data as &$value) {
				// flag all valid data as changed & not loaded
				if ($value->flags & DATAFLAG_LOADED) {
					$value->flags &= ~DATAFLAG_LOADED;
					$value->flags |= DATAFLAG_CHANGED;
				}
			}
			return true;
		}

		throw new DatabaseException('Delete failed on ' . static::getTableName() . ' id=' . $this->getId());
	}

	/**
	 * Load the data object from an array
	 * @param $row
	 */
	public function loadFromRow($row) {
		if (!$row) {
			return;
		}

		$setChanged = (isset($row['id']) && $row['id']) ? false : true;
		$db = App::getDBO();
		foreach ($row as $col => &$value) {
			if (isset($this->_data[$col])) {
				$data = & $this->_data[$col];
				if (is_null($value)) {
					$data->value = null;
				} else {
					if (($data->dataType == DATATYPE_DATETIME || $data->dataType == DATATYPE_DATE) && is_string($value)) {
						$data->value = $db->readDate($value, $data->dataType == DATATYPE_DATETIME);
					} else {
						if ($data->dataType == DATATYPE_INT && $value === (string)(int)$value) {
							$data->value = (int)$value;
						} else {
							$data->value =& $value;
						}
					}
				}
				$data->flags &= ~DATAFLAG_CHANGED;
				$data->flags |= DATAFLAG_LOADED;
				if ($setChanged) {
					$data->flags |= DATAFLAG_CHANGED;
				}
			} else {
				$this->_extra[$col] =& $value;
			}
		}

		// assume we loaded the id (or from the id)
		$this->_data['id']->value = (int)$this->_data['id']->value;
		if ($this->_data['id']->value > 0) {
			$this->_data['id']->flags &= ~DATAFLAG_CHANGED;
			$this->_data['id']->flags |= DATAFLAG_LOADED;
		}
	}

	/**
	 * Update or insert a subset of fields and values into the database and update our id
	 * @param DBColumnValue[] $fields
	 * @return bool success
	 */
	private function insertOrUpdate($fields) {
		$db = App::getDBO();
		$table = static::getTableName();
		$id = $this->getId();
		if (sizeof($fields) == 0) {
			if ($id == 0) {
				$db->prepare("INSERT INTO `$table` () VALUES ()");
			} else {
				return true;
			}
		} else {
			// insert, return new id
			$columns = [];
			$questionMarks = [];
			foreach ($fields as $val) {
				$columns[] = $val->getColumn();
				$questionMarks[] = '?';
			}
			if ($id == 0) {
				$db->prepare("INSERT INTO `$table` (`" . implode('`,`', $columns) . '`) VALUES (' . implode(',', $questionMarks) . ')');
			} else {
				$db->prepare("UPDATE `$table` SET `" . implode('`=?,`', $columns) . '`=? WHERE id=?');
			}

			$i = 1;
			foreach ($fields as $val) {
				$type = $val->getDataType();
				if ($val->isNull()) {
					$db->bindValue($i, null, \PDO::PARAM_INT);
				} else if ($type == DATATYPE_BOOL) {
					$db->bindValue($i, $val->getValue(), \PDO::PARAM_BOOL);
				} else if ($type <= DATATYPE_INT) {
					$db->bindValue($i, $val->getValue(), \PDO::PARAM_INT);
				} else if ($type == DATATYPE_DATETIME) {
					$db->bindValue($i, $db->formatDate($val->getValue()), \PDO::PARAM_STR);
				} else if ($type == DATATYPE_DATE) {
					$db->bindValue($i, $db->formatDate($val->getValue(), false), \PDO::PARAM_STR);
				} else if ($type == DATATYPE_BINARY) {
					$db->bindValue($i, $val->getValue(), \PDO::PARAM_LOB);
				} else if ($type == DATATYPE_JSON) {
					$value = $val->getValue();
					if (empty($value)) {
						$db->bindValue($i, null, \PDO::PARAM_INT);
					} else if (is_string($val->getValue())) {
						$db->bindValue($i, $val->getValue(), \PDO::PARAM_STR);
					} else {
						$db->bindValue($i, json_encode($val->getValue()), \PDO::PARAM_STR);
					}
				} else {
					$db->bindValue($i, $val->getValue(), \PDO::PARAM_STR);
				}
				$i++;
			}

			if ($id != 0) {
				$db->bindValue($i, $id, \PDO::PARAM_INT);
			}
		}

		try {

			if ($db->execute()) {
				// get generated id
				if ($id == 0) {
					if ($id = $db->lastInsertId()) {
						$this->_data['id']->value = $id;
						$this->_data['id']->flags = DATAFLAG_LOADED;
						return true;
					}
				} else {
					return true;
				}
			}

			throw new \Exception('DataObject::insertOrUpdate failed.');
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * @deprecated use fetchOne instead
	 * @param int $id
	 * @return DataObject|null
	 */
	public static function get($id) {
		Log::error('DataObject::get is deprecated');
		if (!$id) {
			return null;
		}

		/** @var DataObject $objectClass */
		$objectClass = get_called_class();

		$db = App::getDBO();
		$db->prepare('SELECT * FROM `' . static::getTableName() . '` WHERE id=?');
		$db->bindValue(1, $id, \PDO::PARAM_INT);
		$db->execute();
		if ($db->execute()) {
			return $db->fetch($objectClass);
		}
		return null;
	}

	/**
	 * @deprecated use fetch instead
	 * @param int $start
	 * @param int $limit
	 * @return DataObject[]
	 */
	public static function getAll($start, $limit) {
		Log::error('DataObject::getAll is deprecated');
		return self::getByFields(null, '1', $start, $limit);
	}

	/**
	 * @deprecated use fetch instead
	 * @param array $fieldSet in format { fieldName => value }
	 * @param string $condition conditional logic in addition to $fieldSet
	 * @param int $offset
	 * @param int $limit
	 * @return DataObject[]
	 */
	public static function getByFields($fieldSet, $condition = null, $offset = 0, $limit = 0) {
		Log::error('DataObject::getByFields is deprecated');
		$objectClass = get_called_class();
		// create an empty data object to read structure
		$emptyObject = new $objectClass();
		$fieldSet = $fieldSet == null ? array() : $fieldSet;

		$db = App::getDBO();

		// build query
		/** @var DataObject $objectClass */
		$query = 'SELECT * FROM `' . static::getTableName() . '` WHERE ';
		if (!empty($fieldSet)) {
			$query .= '(`' . implode('`=? AND `', array_keys($fieldSet)) . '`=?' . ')';
		}
		if (!empty($condition)) {
			$query .= (empty($fieldSet) ? '' : ' AND ') . $condition;
		}

		if (empty($fieldSet) && empty($condition)) {
			$query .= '1=1';
		}

		if ($limit != 0) {
			$query .= ' LIMIT ' . (int)$limit;
		}
		if ($offset != 0) {
			$query .= ' OFFSET ' . (int)$offset;
		}

		$db->prepare($query);

		// bind params
		$i = 1;
		foreach ($fieldSet as $field => $value) {
			$type = \PDO::PARAM_STR;
			if ($emptyObject->_data[$field]->dataType == DATATYPE_BOOL) {
				$type = \PDO::PARAM_BOOL;
			} else if ($emptyObject->_data[$field]->dataType < DATATYPE_INT) {
				$type = \PDO::PARAM_INT;
			}
			$db->bindValue($i++, $value, $type);
		}

		// query
		$db->execute();
		return $db->fetchAll($objectClass);
	}

	/**
	 * Check if a row exists by id
	 * note: each call performs a query
	 * @param int $id
	 * @return bool
	 */
	public static function exists($id) {
		$db = App::getDBO();

		$db->prepare('SELECT COUNT(*) FROM `' . static::getTableName() . '` WHERE id=?');
		$db->bindValue(1, $id, \PDO::PARAM_INT);
		$db->execute();

		return $db->fetchFirstValue() != 0;
	}

	/**
	 * Fetch by an id, key value pair map, or a condition created using the Sql class
	 * @param int|array $criteria the integer id or an array of column value pairs
	 * @param array $order an array of column direction pairs ['column'=>'ASC']
	 * @param int $limit result limit
	 * @param int $offset result offset
	 * @throws MudpuppyException
	 * @return \Mudpuppy\DataObject[]
	 */
	public static function fetch($criteria, $order = null, $limit = 0, $offset = 0) {
		/** @var DataObject $$objectClass */
		$objectClass = get_called_class();
		$db = App::getDBO();

		if (is_int($criteria)) {
			if (!$criteria) {
				return null;
			}

			$db->prepare('SELECT * FROM `' . static::getTableName() . '` WHERE id=?');
			$db->bindValue(1, $criteria, \PDO::PARAM_INT);
			$db->execute();
			return $db->fetch($objectClass);
		}

		if (is_array($criteria)) {
			// get columns to validate criteria before injecting in a query
			/** @var array $columnDefaults */
			$columnDefaults = self::getColumnDefaults($objectClass);

			// create an empty data object to read structure
			$fieldSet = $criteria == null ? array() : $criteria;

			// build the query
			/** @var DataObject $objectClass */
			$query = 'SELECT * FROM `' . static::getTableName() . '`';
			$values = [];
			$fields = [];
			if (!empty($fieldSet)) {
				list($where, $fields, $values, $unmatchedFields) = Sql::_generateWhere($criteria);

				foreach (array_merge($fields, $unmatchedFields) as $field) {
					MPAssert(array_key_exists($field, $columnDefaults), 'DataObject::fetch Invalid field ' . $field);
				}

				$query .= ' WHERE ' . $where;
			}

			if (is_array($order) && count($order) > 0) {
				$orderFields = [];
				foreach ($order as $field => $direction) {
					MPAssert(array_key_exists($field, $columnDefaults), 'DataObject::fetch Invalid order field ' . $field);
					MPAssert(strcasecmp($direction, 'asc') == 0 || strcasecmp($direction, 'DESC') == 0);
					$orderFields[] = "`$field` $direction";
				}
				$query .= ' ORDER BY ' . implode(", ", $orderFields);
			}

			if ($limit != 0) {
				$query .= ' LIMIT ' . (int)$limit;
				if ($offset != 0) {
					$query .= ' OFFSET ' . (int)$offset;
				}
			}

			$db->prepare($query);

			// bind values
			for ($i=0; $i < count($values); $i++) {
				$value = $values[$i];
				$type = \PDO::PARAM_STR;
				$dataType = $columnDefaults[$fields[$i]]->dataType;
				if (is_null($value)) {
					$type = \PDO::PARAM_INT;
				} else if ($dataType == DATATYPE_BOOL) {
					$type = \PDO::PARAM_BOOL;
				} else if ($dataType <= DATATYPE_INT) {
					$type = \PDO::PARAM_INT;
				} else if ($dataType == DATATYPE_DATETIME) {
					$value = $db->formatDate($value);
				} else if ($dataType == DATATYPE_DATE) {
					$value = $db->formatDate($value, false);
				} else if ($dataType == DATATYPE_JSON) {
					if (empty($value)) {
						$type = \PDO::PARAM_INT;
						$value = null;
					} else if (!is_string($value)) {
						$value = json_encode($value);
					}
				}
				$db->bindValue($i+1, $value, $type);
			}

			// query
			$db->execute();
			return $db->fetchAll($objectClass);
		}

		throw new MudpuppyException("Unrecognized \$criteria type");
	}

	/**
	 * Fetch by an id or key value pair map, but only return the first result or null
	 * @param int|array $criteria
	 * @return DataObject|null
	 */
	public static function fetchOne($criteria) {
		$result = self::fetch($criteria, null, 1, 0);
		if (is_array($result)) {
			if (count($result) > 0) {
				return $result[0];
			} else {
				return null;
			}
		}
		return $result;
	}

	function getDate($col, $format, $default = null) {
		if ($this->$col) {
			return date($format, $this->$col);
		}
		return $default;
	}

	function setDate($col, $datestring, $default = null) {
		if (strlen($datestring) > 0) {
			$this->$col = strtotime($datestring);
		} else {
			$this->$col = $default;
		}
	}

	public function markChanged($col) {
		if (isset($this->_data[$col])) {
			$data =& $this->_data[$col];
			$data->flags |= DATAFLAG_CHANGED;
		}
	}

	// "operator overloading"
	public function &__get($col) {
		if (isset($this->_data[$col])) {
			$dataValue = $this->_data[$col];
			if ($dataValue->dataType == DATATYPE_JSON) {
				if (is_string($dataValue->value)) {
					$dataValue->value = json_decode($dataValue->value, true);
				}
			}
			return $dataValue->value;
		} else {
			/** @var DataLookup[] $lookups */
			$lookups = self::$_lookups[$this->getObjectName()];
			if (isset($lookups[$col])) {
				return $lookups[$col]->performLookup($this);
			}
			return $this->_extra[$col];
		}
	}

	function &getValue($col) {
		return $this->__get($col);
	}

	// "operator overloading"
	public function __set($col, $value) {
		if (!isset($this->_data[$col])) {
			/** @var DataLookup[] $lookups */
			$lookups = self::$_lookups[$this->getObjectName()];
			if (isset($lookups[$col])) {
				$lookups[$col]->setLookup($this, $value);
			}
			$this->_extra[$col] = $value;
			return;
		}
		$data =& $this->_data[$col];
		if (!($data->flags & DATAFLAG_LOADED) || $data->value != $value || $data->dataType == DATATYPE_JSON) {
			$data->value = $value;
			$data->flags |= DATAFLAG_CHANGED;
		}
	}

	function setValue($col, $value) {
		$this->__set($col, $value);
	}

	public function __unset($key) {
		if (isset($this->_data[$key])) {
			$this->_data[$key]->value = null; // clear the value
			$this->_data[$key]->flags &= ~DATAFLAG_CHANGED; // don't want to overwrite it in db
		} else {
			if (isset($this->_lookup[$key])) {
				self::$_lookups[$this->getObjectName()][$key]->clearLookup($this);
			} else {
				unset($this->_extra[$key]);
			}
		}
	}

	function clearValue($col) {
		$this->__unset($col);
	}

	public function __isset($key) {
		return $this->hasValue($key) || isset($this->_extra[$key]);
	}

	function hasValue($col) {
		//if(App::isDebug() && !isset($this->_data[$col]))
		//	throw new Exception("Column '$col' of DataObject '".$this->getObjectName()."' is not a valid column.");

		return isset($this->_data[$col]) && ($this->_data[$col]->flags & DATAFLAG_LOADED);
	}

	function hasValueChanged($col) {
		return isset($this->_data[$col]) && ($this->_data[$col]->flags & DATAFLAG_CHANGED);
	}

	public function jsonSerialize() {
		return $this->toArray();
	}

	/**
	 * creates an associative array from the data object
	 * after running each value through htmlentities()
	 * @return array
	 */
	public function htmlEntities() {
		$data = $this->toArray();
		array_walk($data, function (&$value, $key) {
			$value = htmlentities($value);
		});
		return $data;
	}

	/**
	 * Convert data object into a JSON friendly array
	 *
	 * @param null $dateTimeFormat
	 * @param null $dateOnlyFormat
	 * @param array $include list of keys to include
	 * @param null $exclude list of keys to exclude
	 * @internal param string $dateFormat or null to use the date format from Config
	 * @return array
	 */
	function &toArray($dateTimeFormat = null, $dateOnlyFormat = null, $include = null, $exclude = null) {
		if (empty($dateTimeFormat)) {
			$dateTimeFormat = Config::$dateTimeFormat;
		}
		if (empty($dateOnlyFormat)) {
			$dateOnlyFormat = Config::$dateOnlyFormat;
		}
		$a = array();
		foreach ($this->_data as $k => $v) {
			if ($include && !in_array($k, $include)) {
				continue;
			}
			if ($exclude && in_array($k, $exclude)) {
				continue;
			}
			$dataType = $v->dataType;
			if ($v->value === null) {
				$a[$k] = null;
			} else if ($dataType == DATATYPE_DATETIME) {
				if ($v->value == null || $v->value == '') {
					$a[$k] = null;
				} else {
					$a[$k] = date($dateTimeFormat, $v->value);
				}
			} else if ($dataType == DATATYPE_DATE) {
				if ($v->value == null || $v->value == '') {
					$a[$k] = null;
				} else {
					$a[$k] = date($dateOnlyFormat, $v->value);
				}
			} else if ($dataType == DATATYPE_JSON) {
				if (is_string($v->value)) {
					$v->value = json_decode($v->value, true);
				}
				$a[$k] = $v->value;
			} else if ($dataType == DATATYPE_BOOL) {
				$a[$k] = (bool)$v->value;
			} else if ($dataType <= DATATYPE_INT) {
				$a[$k] = (int)$v->value;
			} else if ($dataType <= DATATYPE_DOUBLE) {
				$a[$k] = (double)$v->value;
			} else {
				$a[$k] = $v->value;
			}
		}

		foreach ($this->_extra as $k => $v) {
			if ($include && !in_array($k, $include)) {
				continue;
			}
			if (is_object($v) && $v instanceof DataObject) {
				/** @var DataObject $v */
				$a[$k] = $v->toArray($dateTimeFormat, $dateOnlyFormat);
			} else if (is_array($v)) {
				$a[$k] = self::objectListToArrayList($v, $dateTimeFormat, $dateOnlyFormat);
			} else {
				$a[$k] = $v;
			}
		}
		return $a;
	}

	/**
	 * Convert an array of data objects to an array of arrays
	 *
	 * @param array $array of data objects (can be nested in arrays)
	 * @param null $dateTimeFormat
	 * @param null $dateOnlyFormat
	 * @internal param null $dateFormat
	 * @return array
	 */
	public static function objectListToArrayList($array, $dateTimeFormat = null, $dateOnlyFormat = null) {
		if (empty($dateTimeFormat)) {
			$dateTimeFormat = Config::$dateTimeFormat;
		}
		if (empty($dateOnlyFormat)) {
			$dateOnlyFormat = Config::$dateOnlyFormat;
		}
		if (!is_array($array)) {
			if (is_object($array) && $array instanceof DataObject) {
				/** @var DataObject $array */
				return $array->toArray($dateTimeFormat, $dateOnlyFormat);
			}
			return array();
		}

		$result = array();
		foreach ($array as $key => $object) {
			if (is_array($object)) {
				$result[$key] = self::objectListToArrayList($object, $dateTimeFormat, $dateOnlyFormat);
			} else if (is_object($object) && $object instanceof DataObject) {
				/** @var DataObject $object */
				$result[$key] = $object->toArray($dateTimeFormat, $dateOnlyFormat);
			} else {
				$result[$key] = $object;
			}
		}
		return $result;
	}

	/**
	 * Generates a structure definition recognizable by the Module/API system
	 * @return array structure definition
	 */
	public static function getStructureDefinition() {
		$objectClass = get_called_class();
		$objectDef =& self::$_defaults[$objectClass];
		$definition = array();

		/** @var DataValue $d */
		foreach ($objectDef as $k => &$d) {
			if ($d->dataType == DATATYPE_DATETIME) {
				$type = 'datetime';
			} else if ($d->dataType == DATATYPE_DATE) {
				$type = 'date';
			} else if ($d->dataType == DATATYPE_BOOL) {
				$type = 'bool';
			} else if ($d->dataType <= DATATYPE_INT) {
				$type = 'int';
			} else if ($d->dataType <= DATATYPE_DOUBLE) {
				$type = 'double';
			} else if ($d->dataType == DATATYPE_JSON) {
				$type = 'string';
			} else {
				$type = 'string';
			}
			$required = $d->flags & DATAFLAG_NOTNULL;
			$definition[$k] = array(
				'type' => $type,
				'required' => $required
			);
		}

		// id should not be required or else you cannot validate when creating
		$definition['id']['required'] = false;

		return $definition;
	}

}

?>