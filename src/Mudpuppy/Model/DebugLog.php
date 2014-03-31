<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy\Model;
use Mudpuppy\App;
use Mudpuppy\DataObject;

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
 * @property bool https
 * @property string ip
 * @property string userAgent
 * @property string sessionHash
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
		$this->createColumn('https', DATATYPE_BOOL, null, true);
		$this->createColumn('ip', DATATYPE_STRING, null, true);
		$this->createColumn('userAgent', DATATYPE_STRING, null, true);
		$this->createColumn('sessionHash', DATATYPE_STRING, null, true);
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
	 * Fetch a collection of $[CLASS] objects by specified criteria, either by the id, or by any
	 * set of field value pairs (generates query of ... WHERE field0=value0 && field1=value1)
	 * optionally order using field direction pairs [field=>'ASC']
	 * @param int|array $criteria
	 * @param array $order
	 * @param int $limit
	 * @param int $offset
	 * @return DebugLog[]
	 */
	public static function fetch($criteria, $order = null, $limit = 0, $offset = 0) {
		return forward_static_call(['Mudpuppy\DataObject', 'fetch'], $criteria, $limit, $offset);
	}

	/**
	 * @param int|array $criteria
	 * @return DebugLog|null
	 */
	public static function fetchOne($criteria) {
		return forward_static_call(['Mudpuppy\DataObject', 'fetchOne'], $criteria);
	}

	/**
	 * @return DebugLog
	 */
	public static function getLast() {
		$db = App::getDBO();
		$db->prepare("SELECT * FROM " . static::getTableName() . " ORDER BY id DESC LIMIT 100");
		$result = $db->execute();
		$dataObject = null;
		if ($result && ($row = $result->fetch(\PDO::FETCH_ASSOC))) {
			$dataObject = new DebugLog($row);
		}
		return $dataObject;
	}

	public static function deleteAll() {
		App::getDBO()->prepare('DELETE FROM ' . static::getTableName() . ' WHERE 1=1');
		return App::getDBO()->execute() !== false;
	}

	public function save() {
		if (!parent::save()) {
			// Make sure the table exists
			$db = App::getDBO();
			if (preg_match("#Table '[^']*\\.DebugLogs' doesn't exist#i", $db->getLastError(), $matches) > 0) {
				$db->prepare("CREATE TABLE IF NOT EXISTS `DebugLogs` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '	',
  `date` datetime NOT NULL,
  `requestMethod` varchar(16) NOT NULL,
  `requestPath` text NOT NULL,
  `request` text COMMENT 'JSON',
  `https` bit NOT NULL,
  `ip` varchar(50) NOT NULL,
  `userAgent` text NOT NULL,
  `sessionHash` char(32) NOT NULL,
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
				$db->execute();
				return parent::save();
			}
			return false;
		}
		return true;
	}

}

?>