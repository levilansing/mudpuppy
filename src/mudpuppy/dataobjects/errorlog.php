<?php
defined('MUDPUPPY') or die('Restricted');

/**
 * The data object for ErrorLog
 * This class was auto generated, DO NOT remove or edit # comments
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
class ErrorLog extends DataObject {

    protected function loadDefaults() {
        // auto-generated code to create columns with default values based on DB schema. DO NOT EDIT.
        // #BEGIN DEFAULTS
		$this->createColumn('id', DATATYPE_INT, NULL, true);
		$this->createColumn('date', DATATYPE_DATETIME, NULL, true);
		$this->createColumn('requestMethod', DATATYPE_STRING, NULL, true);
		$this->createColumn('requestPath', DATATYPE_STRING, NULL, true);
		$this->createColumn('request', DATATYPE_JSON, NULL, false);
		$this->createColumn('memoryUsage', DATATYPE_INT, NULL, true);
		$this->createColumn('startTime', DATATYPE_DOUBLE, NULL, true);
		$this->createColumn('executionTime', DATATYPE_DOUBLE, NULL, true);
		$this->createColumn('queries', DATATYPE_JSON, NULL, false);
		$this->createColumn('log', DATATYPE_JSON, NULL, false);
		$this->createColumn('errors', DATATYPE_JSON, NULL, false);
		$this->createColumn('responseCode', DATATYPE_INT, NULL, true);

		// Foreign Key Lookups
        // #END DEFAULTS

        // change defaults here if you want user-defined default values
        // $this->updateColumnDefault('column', DEFAULT_VALUE);
    }

    public static function getTableName() {
        return 'ErrorLogs';
    }

    /**
     * @param int $id
     * @return ErrorLog
     */
    public static function get($id) {
        $statement = App::getDBO()->prepare('SELECT * FROM ' . self::getTableName() . ' WHERE id=?');
        $statement->bindValue(1, $id, PDO::PARAM_INT);
        $result = App::getDBO()->query();
        $dataObject = null;
        if ($result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
            $dataObject = new ErrorLog($row);
        }
        return $dataObject;
    }

    /**
     * @return ErrorLog[]
     */
    public static function getAll($start, $limit) {
        $limitString = $start||$limit ? (' LIMIT '.(int)$start).($limit ? ','.(int)$limit : '') : '';
        $statement = App::getDBO()->prepare('SELECT * FROM ' . self::getTableName().$limitString);
        $result = App::getDBO()->query();
        $dataObjects = array();
        while ($result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
            $dataObjects[] = new ErrorLog($row);
        }
        return $dataObjects;
    }


    /**
     * @return ErrorLog
     */
    public static function getLast() {
        $db = App::getDBO();
        $statement = $db->prepare("SELECT * FROM " . self::getTableName() . " ORDER BY id DESC LIMIT 100");
        $result = $db->query();
        $dataObject = null;
        if ($result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
            $dataObject = new ErrorLog($row);
        }
        return $dataObject;
    }

    public static function deleteAll() {
        return App::getDBO()->query('DELETE FROM ' . self::getTableName()) !== false;
    }


    public function save() {
        if (!parent::save()) {
            // make sure the table exists
            $db = App::getDBO();
            if (preg_match("#Table '[^']*\\.errorlogs' doesn't exist#i", $db->getLastError(), $matches) > 0) {
                $db->query("CREATE TABLE IF NOT EXISTS `ErrorLogs` (
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