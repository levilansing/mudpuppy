<?php
defined('MUDPUPPY') or die('Restricted');

/**
 * The data object for User
 * This class was auto generated, DO NOT remove or edit # comments
 * #BEGIN MAGIC PROPERTIES
 * @property int id
 * @property string userName
 * @property string email
 * @property string password
 * @property string displayName
 * @property int lastLogin
 * @property int lastActivity
 * @property int active
 * @property array permissions
 *
 * Foreign Key Lookup Properties
 * #END MAGIC PROPERTIES
 */ 
class User extends DataObject {
	
	protected function loadDefaults() {
		// auto-generated code to create columns with default values based on DB schema. DO NOT EDIT.
		// #BEGIN DEFAULTS
		$this->createColumn('id', DATATYPE_INT, NULL, true);
		$this->createColumn('userName', DATATYPE_STRING, NULL, true);
        $this->createColumn('password', DATATYPE_STRING, NULL, true);
        $this->createColumn('email', DATATYPE_STRING, NULL, true);
		$this->createColumn('displayName', DATATYPE_STRING, NULL, true);
        $this->createColumn('lastLogin', DATATYPE_DATETIME, NULL, false);
        $this->createColumn('lastActivity', DATATYPE_DATETIME, NULL, false);
		$this->createColumn('active', DATATYPE_INT, NULL, true);
		$this->createColumn('permissions', DATATYPE_JSON, NULL, true);

		// Foreign Key Lookups
		// #END DEFAULTS
		
		// change defaults here if you want user-defined default values
		// $this->updateColumnDefault('column', DEFAULT_VALUE, NOT_NULL);
	}	
	
	public static function getTableName() {
		return 'Users';
	}

	/**
	 * @param int $id
	 * @return User
	 */
	public static function get($id) {
		return forward_static_call(array('DataObject','get'), $id);
	}

	/**
	 * @param int $start
	 * @param int $limit
	 * @return User[]
	 */
	public static function getAll($start, $limit) {
		return forward_static_call(array('DataObject','getByFields'), null, 1, $start, $limit);
	}


    /**
     * @param $fieldSet array in format { fieldName => value }
     * @param $condition string conditional logic in addition to $fieldSet
     * @return User[]
     */
    public static function getByFields($fieldSet, $condition = '', $start=0, $limit=0) {
        return forward_static_call(array('DataObject','getByFields'), $fieldSet, $condition, $start, $limit);
    }


    /**
     * find a user by their username
     * @param $userName
     * @return User|false
     */
    public static function getByUserName($userName) {
        $user = User::getByFields(array('userName'=>$userName));
        return reset($user);
    }

    /**
     * find a user by their email
     * @param $email
     * @return User|false
     */
    public static function getByEmail($email) {
        $user = User::getByFields(array('email'=>$email));
        return reset($user);
    }

}

?>