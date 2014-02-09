<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy\Admin;

use Mudpuppy\Controller;
use Mudpuppy\PageController;
use Mudpuppy\App;
use Mudpuppy\Log;
use Mudpuppy\File;
use Mudpuppy\PageNotFoundException;
use Mudpuppy\Request;
use Mudpuppy\Inflector;

defined('MUDPUPPY') or die('Restricted');

class AdminController extends Controller {
	use PageController;

	public function __construct($options) {
		// Don't write the log for this request
		Log::dontWrite();
		parent::__construct($options);
	}

	public function getRequiredPermissions() {
		return array();
	}

	public function render() {
		// Abort the default template, use the admin view for the entire page
		ob_clean();

		if (count($this->pathOptions) > 1 && strtolower($this->pathOptions[0]) == 'content') {
			if (in_array($this->pathOptions[1], array('bootstrap', 'css', 'js', 'images'))) {
				File::passThrough('Mudpuppy/Admin/content/' . implode('/', array_slice($this->pathOptions, 1)));
			}
			throw new PageNotFoundException();
		}

		include('Mudpuppy/Admin/AdminView.php');
		App::cleanExit();
	}

	public function getAllowablePathPatterns() {
		return array('#^content/(js/|css/|bootstrap/|images/)#i');
	}

	public function action_updateDataObjects() {
		$output = '<pre>';

		if (!file_exists('Model')) {
			mkdir('Model');
		}

		$db = App::getDBO();
		$db->prepare("SHOW TABLES");
		$result = $db->execute();
		$tables = $result->fetchAll(\PDO::FETCH_COLUMN, 0);

		$updates = 0;
		$creates = 0;
		foreach ($tables as $table) {
			if (strtolower($table) == 'debuglogs') {
				continue;
			}

			// Create class name from table name
			$class = self::getClassName($table);

			$file = "Model/$class.php";
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
		$file = file_get_contents('Mudpuppy/Admin/_DataObjectSkeleton');

		// Update class name, table name, etc
		$file = str_replace('$[CLASS]', $class, $file);
		$file = str_replace('$[TABLE]', $table, $file);

		return $file;
	}

	// #BEGIN DEFAULTS
	// #END DEFAULTS
	private static function updateContent($content, $table, $output) {
		App::getDBO()->prepare("SHOW FULL COLUMNS FROM $table");
		$result = App::getDBO()->execute();
		$columns = $result->fetchAll(\PDO::FETCH_ASSOC);

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
			$length = 0;
			$type = strtoupper(preg_replace('#\(.*$#', '', $col['Type']));
			$stringTypes = ['TINYBLOB', 'TINYTEXT', 'BLOB', 'TEXT', 'MEDIUMBLOB', 'MEDIUMTEXT', 'LONGBLOB', 'LONGTEXT'];
			if (in_array($type, ['CHAR', 'VARCHAR', 'BINARY', 'VARBINARY'])) {
				if (preg_match('/\(([0-9]+)\)/', $col['Type'], $matches)) {
					$length = (int)$matches[1];
				}
			} else {
				$index = array_search($type, $stringTypes);
				if ($index !== false) {
					$length = pow(2, ((int)($index/2)+1)*8);
				}
			}
			$type = self::getDbDataType($col['Type'], $col['Comment']);
			$str .= "		\$this->createColumn('$fieldName', $type, $default, $notNull, $length);" . PHP_EOL;
		}
		return $str;
	}

	private static function getForeignKeyProperties($table) {
		App::getDBO()->prepare("SELECT i.CONSTRAINT_TYPE as `type`, k.COLUMN_NAME as `sourceColumn`, k.REFERENCED_TABLE_NAME as `referencedTable`, k.REFERENCED_COLUMN_NAME as `referencedColumn`
FROM information_schema.TABLE_CONSTRAINTS i
LEFT JOIN information_schema.KEY_COLUMN_USAGE k ON i.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE i.CONSTRAINT_TYPE = 'FOREIGN KEY'
AND i.TABLE_SCHEMA = DATABASE() AND k.TABLE_SCHEMA = DATABASE()
AND i.TABLE_NAME = '$table';");
		$result = App::getDBO()->execute();
		$properties = array();
		while ($result && $row = $result->fetch(\PDO::FETCH_ASSOC)) {
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
			$output .= "\t\t\$this->createLookup('$property[name]Id', '$property[name]', 'Model\\$property[type]');" . PHP_EOL;
		}
		return $output;
	}

	private static function getPhpType($type, $comment) {
		$type = strtoupper(preg_replace('#\(.*$#', '', $type));
		$types = [
			'int' => ['INTEGER', 'INT', 'SMALLINT', 'TINYINT', 'MEDIUMINT', 'BIGINT', 'DATE', 'DATETIME', 'TIMESTAMP', 'YEAR'],
			'float' => ['FLOAT', 'DOUBLE'],
			'bool' => ['BIT', 'BOOL'],
			'string' => ['DECIMAL', 'NUMERIC', 'CHAR', 'VARCHAR', 'BINARY', 'VARBINARY', 'ENUM', 'SET', 'BLOB', 'TEXT',
				'TINYBLOB', 'TINYTEXT', 'MEDIUMBLOB', 'MEDIUMTEXT', 'LONGBLOB', 'LONGTEXT'],
		];
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
			'DATATYPE_STRING' => array('CHAR', 'VARCHAR', 'ENUM', 'SET', 'TEXT', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT'),
			'DATATYPE_BINARY' => array('BINARY', 'VARBINARY', 'BLOB', 'TINYBLOB', 'MEDIUMBLOB', 'LONGBLOB'),
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