<?php
defined('MUDPUPPY') or die('Restricted');

class AdminController extends Controller {
	use PageController;

	public function getRequiredPermissions() {
		return array();
	}

	public function render() {
		// Abort the default template, use the admin view for the entire page
		ob_clean();
		include('mudpuppy/admin/AdminView.php');
		App::cleanExit();
	}

	public function action_updateDataObjects() {
		$output = '<pre>';

		if (!file_exists('dataobjects')) {
			mkdir('dataobjects');
		}

		$db = App::getDBO();
		$result = $db->query("SHOW TABLES");
		$tables = $result->fetchAll(PDO::FETCH_COLUMN, 0);

		$updates = 0;
		$creates = 0;
		foreach ($tables as $table) {
			if (strtolower($table) == 'debuglogs') {
				continue;
			}

			// Create class name from table name
			$class = self::getClassName($table);

			$file = "dataobjects/$class.php";
			$exists = File::exists($file);
			if ($exists) {
				$content = file_get_contents($file);
				$content = self::updateContent($content, $table, $output);
			} else {
				$content = self::generateDataObject($table, $class);
				$content = self::updateContent($content, $table, $output);
			}

			if ($content && (!$exists || $content != file_get_contents($file))) {
				if (file_put_contents($file, $content)) {
					if ($exists) {
						$output .= "Updated $file\n";
						$updates++;
					} else {
						$output .= "Created $file\n";
						$creates++;
					}
				} else {
					$output .= "Failed to create or update $file due to file permissions\n";
				}
			}
		}

		$output .= "Finished updating data objects: ($updates update(s), $creates create(s))\n";
		$output .= '</pre>';

		return ['output' => $output];
	}

	private static function getClassName($tableName) {
		// Create class name from table name
		$class = Request::cleanValue($tableName, $tableName, 'cmd');
		// Remove underscores and replace with capitol letters
		$class = preg_replace('#_([a-z])#e', 'strtoupper("$1")', $class);
		$class = Inflector::singularize($class);
		$class = ucfirst($class);
		return $class;
	}

	private static function generateDataObject($table, $class) {
		$file = file_get_contents('/mudpuppy/admin/_DataObjectSkeleton');

		// Update class name, table name, etc
		$file = str_replace('$[CLASS]', $class, $file);
		$file = str_replace('$[TABLE]', $table, $file);

		return $file;
	}

	// #BEGIN DEFAULTS
	// #END DEFAULTS
	private static function updateContent($content, $table, $output) {
		$result = App::getDBO()->query("SHOW FULL COLUMNS FROM $table");
		$columns = $result->fetchAll(PDO::FETCH_ASSOC);

		// Only generate for tables with an auto-incrementing id column
		if ($columns[0]['Field'] != 'id' || strstr($columns[0]['Extra'], 'auto_increment') === false) {
			$output .= "Skipping `$table` because there is no id column with auto_increment set\n";
			return null;
		}

		$foreignKeyProperties = self::getForeignKeyProperties($table);
		if (preg_match('/#BEGIN\ MAGIC\ PROPERTIES/m', $content, $beginMatches, PREG_OFFSET_CAPTURE) == 1
			&& preg_match('/^.*?#END\ MAGIC\ PROPERTIES/m', $content, $endMatches, PREG_OFFSET_CAPTURE) == 1
		) {
			$beginMagic = $beginMatches[0][1];
			$endMagic = $endMatches[0][1];
			$content = substr($content, 0, $beginMagic + strlen($beginMatches[0][0])) . PHP_EOL
				. self::generateMagicProperties($columns)
				. " * " . PHP_EOL
				. " * Foreign Key Lookup Properties" . PHP_EOL
				. self::generateForeignKeyProperties($foreignKeyProperties)
				. substr($content, $endMagic);
		} else {
			// Error begin/end magic properties missing
			$output .= "Error cannot find magic props (table: $table)\n";
		}

		if (preg_match('/#BEGIN\ DEFAULTS/m', $content, $beginMatches, PREG_OFFSET_CAPTURE)
			&& preg_match('/^.*?#END\ DEFAULTS/m', $content, $endMatches, PREG_OFFSET_CAPTURE)
		) {
			$beginDefaults = $beginMatches[0][1];
			$endDefaults = $endMatches[0][1];
			$content = substr($content, 0, $beginDefaults + strlen($beginMatches[0][0]))
				. PHP_EOL . self::generateCreateColumns($columns)
				. PHP_EOL . "\t\t// Foreign Key Lookups" . PHP_EOL
				. self::generateForeignKeyLookups($foreignKeyProperties)
				. substr($content, $endDefaults);
		} else {
			// Error begin/end magic properties missing
			$output .= "Error cannot find defaults/create columns (table: $table)\n";
		}
		return $content;
	}

	private static function generateMagicProperties($columns) {
		$str = '';
		foreach ($columns as $col) {
			$fieldName = $col['Field'];
			$type = self::getPhpType($col['Type'], $col['Comment']);
			$str .= " * @property $type $fieldName" . PHP_EOL;
		}
		return $str;
	}

	private static function generateCreateColumns($columns) {
		$str = '';
		foreach ($columns as $col) {
			$fieldName = $col['Field'];
			$phpType = self::getPhpType($col['Type'], $col['Comment']);
			$default = $col['Default'];
			if (is_numeric($default) && in_array($phpType, array('int', 'float', 'bool'))) {
				// $default = $default;
			} else if (strlen($default) > 0) {
				$default = "'" . $default . "'";
			} else {
				$default = "NULL";
			}
			$notNull = $col['Null'] == 'NO' ? 'true' : 'false';
			$type = self::getDbDataType($col['Type'], $col['Comment']);
			$str .= "		\$this->createColumn('$fieldName', $type, $default, $notNull);" . PHP_EOL;
		}
		return $str;
	}

	private static function getForeignKeyProperties($table) {
		$result = App::getDBO()->query("SELECT i.CONSTRAINT_TYPE as `type`, k.COLUMN_NAME as `sourceColumn`, k.REFERENCED_TABLE_NAME as `referencedTable`, k.REFERENCED_COLUMN_NAME as `referencedColumn`
FROM information_schema.TABLE_CONSTRAINTS i
LEFT JOIN information_schema.KEY_COLUMN_USAGE k ON i.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE i.CONSTRAINT_TYPE = 'FOREIGN KEY'
AND i.TABLE_SCHEMA = DATABASE() AND k.TABLE_SCHEMA = DATABASE()
AND i.TABLE_NAME = '$table';");
		$properties = array();
		while ($result && $row = $result->fetch(PDO::FETCH_ASSOC)) {
			if ($row['type'] == 'FOREIGN KEY' && $row['referencedColumn'] == 'id' && substr($row['sourceColumn'], -2) == 'Id') {
				$type = self::getClassName($row['referencedTable']);
				$name = substr($row['sourceColumn'], 0, -2);
				$properties[$name] = array('type' => $type, 'name' => $name);
			}
		}
		return $properties;
	}

	private static function generateForeignKeyProperties($properties) {
		$output = '';
		foreach ($properties as $property) {
			$output .= " * @property $property[type] $property[name]" . PHP_EOL;
		}
		return $output;
	}

	private static function generateForeignKeyLookups($properties) {
		$output = '';
		foreach ($properties as $property) {
			$output .= "\t\t\$this->createLookup('$property[name]Id', '$property[name]', '$property[type]');" . PHP_EOL;
		}
		return $output;
	}

	private static function getPhpType($type, $comment) {
		$type = strtoupper(preg_replace('#\(.*$#', '', $type));
		$types = array(
			'int' => array('INTEGER', 'INT', 'SMALLINT', 'TINYINT', 'MEDIUMINT', 'BIGINT', 'DATE', 'DATETIME', 'TIMESTAMP', 'YEAR'),
			'float' => array('FLOAT', 'DOUBLE'),
			'bool' => array('BIT', 'BOOL'),
			'string' => array('DECIMAL', 'NUMERIC', 'CHAR', 'VARCHAR', 'BINARY', 'VARBINARY', 'ENUM', 'SET', 'BLOB', 'TEXT',
				'TINYBLOB', 'TINYTEXT', 'MEDIUMBLOB', 'MEDIUMTEXT', 'LONGBLOB', 'LONGTEXT'),
		);
		foreach ($types as $t => $list) {
			if (in_array($type, $list)) {
				if ($t == 'string' && strtoupper(substr($comment, 0, 4)) == 'JSON') {
					return 'array';
				}
				return $t;
			}
		}
		return '';
	}

	private static function getDbDataType($type, $comment) {
		$type = strtoupper(preg_replace('#\(.*$#', '', $type));
		$types = array(
			'DATATYPE_INT' => array('INTEGER', 'INT', 'SMALLINT', 'TINYINT', 'MEDIUMINT', 'BIGINT'),
			'DATATYPE_DATETIME' => array('DATETIME', 'TIMESTAMP'),
			'DATATYPE_DATE' => array('DATE', 'YEAR'),
			'DATATYPE_FLOAT' => array('FLOAT'),
			'DATATYPE_DOUBLE' => array('DOUBLE'),
			'DATATYPE_BOOL' => array('BIT', 'BOOL'),
			'DATATYPE_DECIMAL' => array('DECIMAL', 'NUMERIC'),
			'DATATYPE_STRING' => array('CHAR', 'VARCHAR', 'BINARY', 'VARBINARY', 'ENUM', 'SET', 'BLOB', 'TEXT',
				'TINYBLOB', 'TINYTEXT', 'MEDIUMBLOB', 'MEDIUMTEXT', 'LONGBLOB', 'LONGTEXT'),
		);
		foreach ($types as $t => $list) {
			if (in_array($type, $list)) {
				if ($t == 'DATATYPE_STRING' && strtoupper(substr($comment, 0, 4)) == 'JSON') {
					return 'DATATYPE_JSON';
				}
				return $t;
			}
		}
		return '';
	}

}