<?php
defined('MUDPUPPY') or die('Restricted');

class SmartObject implements JsonSerializable {
	private $_data;

	public function SmartObject($assocArray) {
		if (empty($assocArray)) {
			$this->_data = array();
		} else {
			$this->_data = $assocArray;
		}
	}

	public function getData() {
		return $this->_data;
	}

	// "operator overloading" //

	public function __get($key) {
		if (isset($this->_data[$key])) {
			return $this->_data[$key];
		}
		return null;
	}

	public function __set($key, $value) {
		$this->_data[$key] = $value;
	}

	public function __unset($key) {
		unset($this->_data[$key]);
	}

	public function __isset($key) {
		return isset($this->_data[$key]);
	}

	public function jsonSerialize() {
		//return $this->_data;
		// FIXME: what on earth is going on here?
		$q = <<<'EOL'
    INSERT INTO `DebugLogs` (`date`,`requestMethod`,`requestPath`,`request`,`memoryUsage`,`startTime`,`executionTime`,`queries`,`log`,`errors`,`responseCode`)
    VALUES('2013-08-30 00:00:34',
    'POST',
    '/event/',
    '{\"page\":\"event\",\"options\":\"\",\"id\":\"\",\"name\":\"\",\"date\":\"\",\"address1\":\"\",\"address2\":\"\",\"address3\":\"\",\"city\":\"\",\"state\":\"\",\"zip\":\"\",\"details\":\"\",\"action\":\"test\",\"PHPSESSID\":\"f2bdb0e287670578977698dfd024aadc\"}',
    1082776,
    1377820834.47,
    0.012145,
    '{\"errors\":0,\"queries\":[{\"stime\":\"0.008218\",\"query\":\"SELECT *
    FROM Users
    WHERE id=?\",\"etime\":\"0.008558\"},
    {\"stime\":\"0.009803\",\"query\":\"SELECT *
    FROM Events
    WHERE id=?\",\"etime\":\"0.010034\"},
    {\"stime\":\"0.010336\",\"query\":\"SELECT *
    FROM Users
    WHERE id=?\",\"etime\":\"0.010528\"},
    {\"stime\":\"0.01079\",\"query\":\"SELECT *
    FROM Events
    WHERE id=?\",\"etime\":\"0.010953\"},
    {\"stime\":\"0.0112\",\"query\":\"SELECT *
    FROM Events
    WHERE id=?\",\"etime\":\"0.011358\"},
    {\"stime\":\"0.011524\",\"query\":\"SELECT *
    FROM Users
    WHERE id=?\",\"etime\":\"0.011634\"},
    {\"stime\":\"0.01182\",\"query\":\"SELECT *
    FROM Events
    WHERE id=?\",\"etime\":\"0.011928\"}]}',

    NULL,
    '[]',
    NULL,
    200);
EOL;
	}
}

?>