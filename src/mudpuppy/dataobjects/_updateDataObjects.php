<html><body><pre>
<?php
	// use this script to generate and update data objects directly from your database schema
	// dataobjects folder and files must be writable in your dev environment (NOT IN PRODUCTION!)

	require("../mudpuppy.php");

	$baseFolder = 'mudpuppy/dataobjects/';
	
	$db = App::getDBO();
	$result = $db->query("SHOW TABLES");
	$tables = $result->fetchAll(PDO::FETCH_COLUMN, 0);

	$changes = array('updates'=>0, 'creates'=>0);
	foreach ($tables as $table) {
		// create class name from table name
		$class = getClassName($table);

		$file = $baseFolder.strtolower($class).'.php';
		$exists = File::exists($file);
		if ($exists) {
			$content = file_get_contents($file);
			$content = updateContent($content, $table);
		} else {
			$content = generateDataObject($table, $class);
			$content = updateContent($content, $table);
		}

		if ($content && (!$exists || $content != file_get_contents($file))) {
			if (file_put_contents($file, $content)) {
				if ($exists) {
					print "Updated $file\n";
					$changes['updates']++;
				} else {
					print "Created $file\n";
					$changes['creates']++;
				}
			} else {
				print "Failed to create or update $file due to file permissions\n";
			}
		}
	}
	
	print "Finished updating data objects: ($changes[updates] update(s), $changes[creates] create(s))\n";

    function getClassName($tableName) {
        // create class name from table name
        $class = Request::cleanValue($tableName, $tableName, 'cmd');
        // remove underscores and replace with capitol letters
        $class = preg_replace('#_([a-z])#e', 'strtoupper("$1")', $class);
        $class = Inflector::singularize($class);
        $class = ucfirst($class);
        return $class;
    }
	
	function generateDataObject($table, $class) {
		global $baseFolder;
		$file = file_get_contents($baseFolder . '_skeleton');
		
		
		// update class name, table name, etc
		$file = str_replace('$[CLASS]', $class, $file);
		$file = str_replace('$[TABLE]', $table, $file);
		
		return $file;
	}
	
 		// #BEGIN DEFAULTS
		// #END DEFAULTS
 	function updateContent($content, $table) {
 		global $db;
		$db = App::getDBO();
		$result = $db->query("SHOW FULL COLUMNS FROM $table");
		$columns = $result->fetchAll(PDO::FETCH_ASSOC);
		
		// only generate for tables with an id column
		if ($columns[0]['Field'] != 'id' || strstr($columns[0]['Extra'], 'auto_increment') === false) {
			print "Skipping `$table` because there is no id column with auto_increment set\n";
			return NULL;
		}

        $foreignKeyProperties = getForeignKeyProperties($table);
		if (preg_match('/#BEGIN\ MAGIC\ PROPERTIES.*?$/m', $content, $beginMatches, PREG_OFFSET_CAPTURE) == 1
 			&& preg_match('/^.*?#END\ MAGIC\ PROPERTIES/m', $content, $endMatches, PREG_OFFSET_CAPTURE) == 1) {
			$beginMagic = $beginMatches[0][1];
			$endMagic = $endMatches[0][1];
			$content = substr($content, 0, $beginMagic+strlen($beginMatches[0][0])) . "\n"
                . generateMagicProperties($columns)
                . " * \n"
                . " * Foreign Key Lookup Properties\n"
                . generateForeignKeyProperties($foreignKeyProperties)
                . substr($content, $endMagic);
 		} else {
 			// error begin/end magic properties missing
 			print "Error cannot find magic props (table: $table)\n";
 		}

 		if (preg_match('/#BEGIN\ DEFAULTS.*?$/m', $content, $beginMatches, PREG_OFFSET_CAPTURE)
 			&& preg_match('/^.*?#END\ DEFAULTS/m', $content, $endMatches, PREG_OFFSET_CAPTURE)) {
			$beginDefaults = $beginMatches[0][1];
			$endDefaults = $endMatches[0][1];
			$content = substr($content, 0, $beginDefaults+strlen($beginMatches[0][0]))
                . "\n" . generateCreateColumns($columns)
                . "\n\t\t// Foreign Key Lookups\n"
                . generateForeignKeyLookups($foreignKeyProperties)
                . substr($content, $endDefaults);
 		} else {
 			// error begin/end magic properties missing
 			print "Error cannot find defaults/create columns (table: $table)\n";
 		}
 		return $content;
 	}
	
	function generateMagicProperties($columns) {
		$str = '';
		foreach ($columns as $col) {
			$fieldName = $col['Field'];
			$type = getPhpType($col['Type'], $col['Comment']);
			$str .= " * @property $type $fieldName\n";
		}
		return $str;
	}
	
	function generateCreateColumns($columns) {
		$str = '';
		foreach ($columns as $col) {
			$fieldName = $col['Field'];
			$phpType = getPhpType($col['Type'], $col['Comment']);
			$default = $col['Default'];
			if (is_numeric($default) && in_array($phpType, array('int','float','bool'))) {
                // $default = $default;
            } else if (strlen($default) > 0)
				$default = "'".$default."'";
			else
				$default = "NULL";
            $notNull = $col['Null'] == 'NO' ? 'true' : 'false';
			$type = getDbDataType($col['Type'], $col['Comment']);
			$str .= "		\$this->createColumn('$fieldName', $type, $default, $notNull);\n";
		}
		return $str;
	}

    function getForeignKeyProperties($table) {
        global $db;
        $result = $db->query("SELECT i.CONSTRAINT_TYPE as `type`, k.COLUMN_NAME as `sourceColumn`, k.REFERENCED_TABLE_NAME as `referencedTable`, k.REFERENCED_COLUMN_NAME as `referencedColumn`
FROM information_schema.TABLE_CONSTRAINTS i
LEFT JOIN information_schema.KEY_COLUMN_USAGE k ON i.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE i.CONSTRAINT_TYPE = 'FOREIGN KEY'
AND i.TABLE_SCHEMA = DATABASE() AND k.TABLE_SCHEMA = DATABASE()
AND i.TABLE_NAME = '$table';");
        $properties = array();
        while ($result && $row = $result->fetch(PDO::FETCH_ASSOC)) {
            if ($row['type'] == 'FOREIGN KEY' && $row['referencedColumn'] == 'id' && substr($row['sourceColumn'],-2) == 'Id') {
                $type = getClassName($row['referencedTable']);
                $name = substr($row['sourceColumn'], 0, -2);
                $properties[$name] = array('type'=>$type, 'name'=>$name);
            }
        }
        return $properties;
    }

    function generateForeignKeyProperties($properties) {
        $output = '';
        foreach ($properties as $property) {
            $output .= " * @property $property[type] $property[name]\n";
        }
        return $output;
    }

    function generateForeignKeyLookups($properties) {
        $output = '';
        foreach ($properties as $property) {
            $output .= "\t\t\$this->createLookup('$property[name]Id', '$property[name]', '$property[type]');\n";
        }
        return $output;
    }

	function getPhpType($type, $comment) {
		$type = strtoupper(preg_replace('#\(.*$#', '', $type));
		$types = array(
			'int' => array('INTEGER', 'INT', 'SMALLINT', 'TINYINT', 'MEDIUMINT', 'BIGINT', 'DATE', 'DATETIME', 'TIMESTAMP', 'YEAR'),
			'float' => array('FLOAT', 'DOUBLE'),
			'bool' => array('BIT', 'BOOL'),
			'string' => array('DECIMAL', 'NUMERIC', 'CHAR', 'VARCHAR', 'BINARY', 'VARBINARY', 'ENUM', 'SET', 'BLOB', 'TEXT',
							'TINYBLOB', 'TINYTEXT', 'MEDIUMBLOB', 'MEDIUMTEXT', 'LONGBLOB','LONGTEXT'),
		);
		foreach ($types as $t=>$list) {
			if (in_array($type, $list)) {
				if ($t == 'string' && strtoupper(substr($comment,0,4)) == 'JSON')
					return 'array';
				return $t;
			}
		}
		return '';
	}

	function getDbDataType($type, $comment) {
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
										'TINYBLOB', 'TINYTEXT', 'MEDIUMBLOB', 'MEDIUMTEXT', 'LONGBLOB','LONGTEXT'),
		);
		foreach ($types as $t=>$list) {
			if (in_array($type, $list)) {
				if ($t == 'DATATYPE_STRING' && strtoupper(substr($comment,0,4)) == 'JSON')
					return 'DATATYPE_JSON';
				return $t;
			}
		}
		return '';
	}
?></pre></body></html><?php App::cleanExit(); ?>