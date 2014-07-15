<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Model;
use Mudpuppy\App;
use Mudpuppy\DataObject;

defined('MUDPUPPY') or die('Restricted');

/**
 * The data object for BrowserSession. This class was auto generated, DO NOT remove or edit # comments.
 *
 * #BEGIN MAGIC PROPERTIES
 * @property int id
 * @property string sessionId
 * @property int lastAccessed
 * @property string data
 * 
 * Foreign Key Lookup Properties
 * #END MAGIC PROPERTIES
 */
class BrowserSession extends DataObject {

	protected function loadDefaults() {
		// Auto-generated code to create columns with default values based on DB schema. DO NOT EDIT.
		// #BEGIN DEFAULTS
		$this->createColumn('id', DATATYPE_INT, NULL, true, 0);
		$this->createColumn('sessionId', DATATYPE_STRING, NULL, true, 32);
		$this->createColumn('lastAccessed', DATATYPE_INT, NULL, true, 0);
		$this->createColumn('data', DATATYPE_STRING, NULL, false, 16777216);

		// Foreign Key Lookups
		// #END DEFAULTS

		// Change defaults here if you want user-defined default values
		// $this->updateColumnDefault('column', DEFAULT_VALUE, NOT_NULL);
	}

	public static function getTableName() {
		return 'BrowserSessions';
	}

	/**
	 * Fetch a collection of BrowserSession objects by specified criteria, either by the id, or by any
	 * set of field value pairs (generates query of ... WHERE field0=value0 && field1=value1)
	 * optionally order using field direction pairs [field=>'ASC']
	 * @param int|array $criteria
	 * @param array $order
	 * @param int $limit
	 * @param int $offset
	 * @return BrowserSession[]
	 */
	public static function fetch($criteria, $order = null, $limit = 0, $offset = 0) {
		return forward_static_call(['Mudpuppy\DataObject', 'fetch'], $criteria, $order, $limit, $offset);
	}

	/**
	 * @param int|array $criteria
	 * @return BrowserSession|null
	 */
	public static function fetchOne($criteria) {
		return forward_static_call(['Mudpuppy\DataObject', 'fetchOne'], $criteria);
	}

	public static function deleteSession($sessionId) {
		$db = App::getDBO();
		$db->prepare("DELETE FROM " . self::getTableName() . " WHERE sessionId = ?");
		$db->execute([$sessionId]);
	}

	public static function deleteSessionsOlderThan($oldest) {
		$db = App::getDBO();
		$db->prepare("DELETE FROM " . self::getTableName() . " WHERE lastAccessed < ?");
		$db->execute([$oldest]);
	}
}

?>