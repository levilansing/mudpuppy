<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy;

use Mudpuppy\MudpuppyException;
use Mudpuppy\Request;

defined('MUDPUPPY') or die('Restricted');

define('SQL_EQUAL', 1);
define('SQL_NOT_EQUAL', 2);
define('SQL_IS', 3);
define('SQL_IS_NOT', 4);
define('SQL_IS_NULL', 5);
define('SQL_IS_NOT_NULL', 6);
define('SQL_LESS', 7);
define('SQL_LESS_OR_EQUAL', 8);
define('SQL_GREATER', 9);
define('SQL_GREATER_OR_EQUAL', 10);
define('SQL_LIKE', 11);
define('SQL_NOT_LIKE', 12);
define('SQL_AND', 13);
define('SQL_OR', 14);

class Sql {

	/**
	 * Create an equals comparison
	 * @param $field
	 * @param $value
	 * @return array
	 */
	public static function equal($field, $value) {
		return [SQL_EQUAL, $field, $value];
	}

	/**
	 * Create a not equals comparison
	 * @param $field
	 * @param $value
	 * @return array
	 */
	public static function notEqual($field, $value) {
		return [SQL_NOT_EQUAL, $field, $value];
	}

	/**
	 * Create a comparison to see if field is NOT NULL
	 * @param $field
	 * @return array
	 */
	public static function isNotNull($field) {
		return [SQL_IS_NOT_NULL, $field];
	}

	/**
	 * Create a comparison to see if field is NULL
	 * @param $field
	 * @return array
	 */
	public static function isNull($field) {
		return [SQL_IS_NULL, $field];
	}

	/**
	 * Create a less than comparison
	 * @param $field
	 * @param $value
	 * @return array
	 */
	public static function less($field, $value) {
		return [SQL_LESS, $field, $value];
	}

	/**
	 * Create a less than or equal to comparison
	 * @param $field
	 * @param $value
	 * @return array
	 */
	public static function lessOrEqual($field, $value) {
		return [SQL_LESS_OR_EQUAL, $field, $value];
	}

	/**
	 * Create a greater than comparison
	 * @param $field
	 * @param $value
	 * @return array
	 */
	public static function greater($field, $value) {
		return [SQL_GREATER, $field, $value];
	}

	/**
	 * Create a greater than or equal to comparison
	 * @param $field
	 * @param $value
	 * @return array
	 */
	public static function greaterOrEqual($field, $value) {
		return [SQL_GREATER_OR_EQUAL, $field, $value];
	}

	/**
	 * Create a LIKE comparison (eg, field LIKE 'value')
	 * @param $field
	 * @param $value
	 * @return array
	 */
	public static function like($field, $value) {
		return [SQL_LIKE, $field, $value];
	}

	/**
	 * Create a NOT LIKE comparison (eg, field NO LIKE 'value')
	 * @param $field
	 * @param $value
	 * @return array
	 */
	public static function notLike($field, $value) {
		return [SQL_NOT_LIKE, $field, $value];
	}

	/**
	 * Use boolean AND to combine all provided conditions (default)
	 * @param array $conditions, ... any conditions to also combine using AND
	 * @return array
	 */
	public static function combineAnd($conditions) {
		return [SQL_AND, func_get_args()];
	}

	/**
	 * Use boolean OR to combine all provided conditions
	 * @param array $conditions, ... any conditions to also combine using OR
	 * @return array
	 */
	public static function combineOr($conditions) {
		return [SQL_OR, func_get_args()];
	}

	/**
	 * FOR INTERNAL USE. Used by DataObject
	 * @param $conditions
	 * @param string $joinOperator
	 * @return array
	 * @throws MudpuppyException
	 */
	public static function _generateWhere($conditions, $joinOperator='AND') {
		$fields = [];
		$values = [];
		$unmatchedFields = [];
		$query = '';

		// check if this is a single expression composed above, if so wrap it in an array to be a list of conditions
		if (count($conditions) > 1 && count($conditions) <= 3) {
			if (isset($conditions[0]) && is_int($conditions[0])) {
				$conditions = [$conditions];
			}
		}

		foreach ($conditions as $key => $val) {
			$field = null;
			$operator = 1;
			$value = null;
			// determine if it's a standard column => value pair
			if (!is_numeric($key)) {
				$field = $key;
				$value = $val;
				// determine if it's a composed sql type
			} else if (is_array($val) && isset($val[0]) && is_int($val[0])) {
				$operator = $val[0];
				if (count($val) > 1) {
					$field = $val[1];
					if (count($val) > 2) {
						$value = $val[2];
					}
				}
				// lastly check if it's an array of more conditions
			} else if (is_array($val)) {
				if (count($val) == 1) {
					$field = array_keys($val)[0];
					$value = reset($val);
				} else {
					$operator = SQL_AND;
					$field = $val;
				}
			} else {
				// malformed
				throw new MudpuppyException("Malformed SQL Condition");
			}
			if(is_string($field) && !self::isValidField($field)) {
				throw new MudpuppyException("Invalid Field Name: $field");
			}

			$query .= strlen($query) ? ' ' . $joinOperator . ' ' : '(';
			switch ($operator) {
			case SQL_EQUAL:
				$query .= "`$field` = ?";
				$fields[] = $field;
				$values[] = $value;
				break;

			case SQL_NOT_EQUAL:
				$query .= "`$field` != ?";
				$fields[] = $field;
				$values[] = $value;
				break;

			case SQL_IS:
				$query .= "`$field` IS " . ($value ? 'TRUE' : 'FALSE');
				$unmatchedFields[] = $field;
				break;

			case SQL_IS_NOT:
				$query .= "`$field` IS NOT " . ($value ? 'TRUE' : 'FALSE');
				$unmatchedFields[] = $field;
				break;

			case SQL_IS_NULL:
				$query .= "`$field` IS NULL";
				$unmatchedFields[] = $field;
				break;

			case SQL_IS_NOT_NULL:
				$query .= "`$field` IS NOT NULL";
				$unmatchedFields[] = $field;
				break;

			case SQL_LESS:
				$query .= "`$field` < ?";
				$fields[] = $field;
				$values[] = $value;
				break;

			case SQL_LESS_OR_EQUAL:
				$query .= "`$field` <= ?";
				$fields[] = $field;
				$values[] = $value;
				break;

			case SQL_GREATER:
				$query .= "`$field` > ?";
				$fields[] = $field;
				$values[] = $value;
				break;

			case SQL_GREATER_OR_EQUAL:
				$query .= "`$field` >= ?";
				$fields[] = $field;
				$values[] = $value;
				break;

			case SQL_LIKE:
				$query .= "`$field` LIKE ?";
				$fields[] = $field;
				$values[] = $value;
				break;

			case SQL_NOT_LIKE:
				$query .= "`$field` NOT LIKE ?";
				$fields[] = $field;
				$values[] = $value;
				break;

			case SQL_AND:
				$result = self::_generateWhere($field, 'AND');
				if (count($conditions) == 1)
					return $result;
				$query .= $result[0];
				$fields = array_merge($fields, $result[1]);
				$values = array_merge($values, $result[2]);
				$unmatchedFields = array_merge($unmatchedFields, $result[3]);
				break;

			case SQL_OR:
				$result = self::_generateWhere($field, 'OR');
				if (count($conditions) == 1)
					return $result;
				$query .= $result[0];
				$fields = array_merge($fields, $result[1]);
				$values = array_merge($values, $result[2]);
				$unmatchedFields = array_merge($unmatchedFields, $result[3]);
				break;

			default:
				throw new MudpuppyException("Invalid SQL Condition Type `$operator`");
			}
		}
		if (strlen($query) > 0)
			$query .= ')';
		return [$query, $fields, $values, $unmatchedFields];
	}

	private static function isValidField($field) {
		return preg_match('#^[a-zA-Z_0-9]+$#', $field);
	}
}
