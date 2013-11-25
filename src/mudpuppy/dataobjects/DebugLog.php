<?php
defined('MUDPUPPY') or die('Restricted');

/**
 * The data object for DebugLog. This class was auto generated, DO NOT remove or edit # comments.
 *
 * #BEGIN MAGIC PROPERTIES
 * @property int id
 * @property int date
 * @property string requestMethod
 * @property string requestPath
 * @property array request
 * @property int memoryUsage
 * @property float startTime
 * @property float executionTime
 * @property array queries
 * @property array log
 * @property array errors
 * @property int responseCode
 *
 * Foreign Key Lookup Properties
 * #END MAGIC PROPERTIES
 */
class DebugLog extends DataObject {

	protected function loadDefaults() {
		// Auto-generated code to create columns with default values based on DB schema. DO NOT EDIT.
		// #BEGIN DEFAULTS
		$this->createColumn('id', DATATYPE_INT, null, true);
		$this->createColumn('date', DATATYPE_DATETIME, null, true);
		$this->createColumn('requestMethod', DATATYPE_STRING, null, true);
		$this->createColumn('requestPath', DATATYPE_STRING, null, true);
		$this->createColumn('request', DATATYPE_JSON, null, false);
		$this->createColumn('memoryUsage', DATATYPE_INT, null, true);
		$this->createColumn('startTime', DATATYPE_DOUBLE, null, true);
		$this->createColumn('executionTime', DATATYPE_DOUBLE, null, true);
		$this->createColumn('queries', DATATYPE_JSON, null, false);
		$this->createColumn('log', DATATYPE_JSON, null, false);
		$this->createColumn('errors', DATATYPE_JSON, null, false);
		$this->createColumn('responseCode', DATATYPE_INT, null, true);

		// Foreign Key Lookups
		// #END DEFAULTS

		// Change defaults here if you want user-defined default values
		// $this->updateColumnDefault('column', DEFAULT_VALUE, NOT_NULL);
	}

	public static function getTableName() {
		return 'DebugLogs';
	}

	/**
	 * @param int $id
	 * @return DebugLog
	 */
	public static function get($id) {
		return forward_static_call(array('DataObject', 'get'), $id);
	}

	/**
	 * @param int $start first index of results
	 * @param int $limit max number of results to return
	 * @return DebugLog[]
	 */
	public static function getAll($start, $limit) {
		return forward_static_call(array('DataObject', 'getByFields'), null, 1, $start, $limit);
	}

	/**
	 * @param array $fieldSet in format { fieldName => value }
	 * @param string $condition conditional logic in addition to $fieldSet
	 * @param int $start first index of results
	 * @param int $limit max number of results to return
	 * @return DebugLog[]
	 */
	public static function getByFields($fieldSet, $condition = '', $start = 0, $limit = 0) {
		return forward_static_call(array('DataObject', 'getByFields'), $fieldSet, $condition, $start, $limit);
	}

	/**
	 * @return DebugLog
	 */
	public static function getLast() {
		$db = App::getDBO();
		$statement = $db->prepare("SELECT * FROM " . self::getTableName() . " ORDER BY id DESC LIMIT 100");
		$result = $db->query();
		$dataObject = null;
		if ($result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
			$dataObject = new DebugLog($row);
		}
		return $dataObject;
	}

	public static function deleteAll() {
		return App::getDBO()->query('DELETE FROM ' . self::getTableName()) !== false;
	}

	public function save() {
		if (!parent::save()) {
			// Make sure the table exists
			$db = App::getDBO();
			if (preg_match("#Table '[^']*\\.debuglogs' doesn't exist#i", $db->getLastError(), $matches) > 0) {
				$db->query("CREATE TABLE IF NOT EXISTS `DebugLogs` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '	',
  `date` datetime NOT NULL,
  `requestMethod` varchar(16) NOT NULL,
  `requestPath` text NOT NULL,
  `request` text COMMENT 'JSON',
  `memoryUsage` int(11) NOT NULL,
  `startTime` double NOT NULL,
  `executionTime` double NOT NULL,
  `queries` text COMMENT 'JSON',
  `log` text COMMENT 'JSON',
  `errors` text COMMENT 'JSON',
  `responseCode` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_date` (`date`),
  KEY `ix_error` (`date`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;");
				return parent::save();
			}
			return false;
		}
		return true;
	}

}

?>