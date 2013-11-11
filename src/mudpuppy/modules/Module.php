<?php
defined('MUDPUPPY') or die('Restricted');

/**
 * Abstract class that all API modules must inherit from (directly or indirectly).
 */
abstract class Module {

	/**
	 * Gets an instance of the class mapped to the specified module.
	 * @param string $moduleName name of the module
	 * @return Module
	 * @throws InvalidIdentifierException if the specified module does not exist
	 */
	public static function getModuleObject($moduleName) {
		// Create instance of module if it exists
		$moduleClass = ucfirst($moduleName) . 'Module';
		if (!class_exists($moduleClass, true)) {
			throw new InvalidIdentifierException("Module '$moduleName' does not exist");
		}
		$moduleInstance = new $moduleClass();

		// Verify the class extends Module
		if (!is_subclass_of($moduleInstance, 'Module')) {
			throw new InvalidIdentifierException("Module '$moduleName' does not exist");
		}

		// And return the instance
		return $moduleInstance;
	}

	/**
	 * Gets a cleaned version of the given associative array, according to the given structure. The structure definition
	 * is an associative array with the top level keys indicating the expected parameter names. The value for each key
	 * is an array or metadata that must define the 'type' of the parameter, and optionally the 'default' value and
	 * whether it is 'required'. Supported types are: 'string', 'int', 'double', 'bool', 'date', and 'array'. For
	 * 'array's, one of two additional fields is required. For generic arrays (indexed arrays or associative arrays with
	 * no specific key requirements), the 'children' field must define the structure of all child elements (note, the
	 * 'required' and 'default' fields are irrelevant in this case). For associative arrays with specific keys, the
	 * 'keys' field must be a nested structure definition array. Any level of array nesting is allowed. For example:
	 *
	 * $structure = array(
	 *   'foo' => array('type'=>'int','default'=>10),
	 *   'bar' => array('type'=>'string','required'=>true),
	 *   'boo' => array('type'=>'array','children'=>array('type'=>'double','default'=>5.7)),
	 *   'far' => array('type'=>'array','required'=>'true','keys'=>array(
	 *      'fooBar' => array('type'=>'bool','default'=>false),
	 *      'booFar' => array('type'=>'array','keys'=>array(
	 *         'barFoo' => array('type'=>'int'),
	 *         'farBoo' => array('type'=>'array','children'=>array('type'=>'date'))
	 *      ))
	 *   ))
	 * )
	 *
	 * @param array $input input array
	 * @param array $structure structure definition array
	 * @param string $parentPath field path prefix used when recursing into sub-arrays.
	 * @return array cleaned version of $input
	 * @throws ApiException if there is something wrong with the structure definition
	 * @throws InvalidInputException if the input doesn't match the required structure
	 */
	public static function cleanArray($input, $structure, $parentPath = '') {
		$cleaned = array();
		if (!is_array($structure)) {
			throw new ApiException("No structure defined at path '$parentPath'.");
		}
		foreach ($structure as $key => $meta) {
			if (!isset($meta['type'])) {
				throw new ApiException("Invalid structure definition. Field '$parentPath$key' must define 'type'.");
			}
			if (isset($input[$key])) {
				$cleaned[$key] = self::cleanValue($input[$key], $meta, "$parentPath$key");
			} else {
				if (isset($meta['required']) && $meta['required']) {
					throw new InvalidInputException("Field '$parentPath$key' is required.");
				}
				if (isset($meta['default'])) {
					$cleaned[$key] = $meta['default'];
				}
			}
		}
		return $cleaned;
	}

	/**
	 * Gets a cleaned version of the given input value, according to the given structure definition. A child function of
	 * the more general cleanArray, cleanValue deals with the individual field validation.
	 * @param mixed $value input value
	 * @param array $meta structure definition for this field
	 * @param string $fieldPath path string that fully identifies the field (used for error strings)
	 * @return mixed cleaned version of $value
	 * @throws ApiException if there is something wrong with the structure definition
	 * @throws InvalidInputException if the input doesn't match required structure
	 */
	public static function cleanValue($value, $meta, $fieldPath) {
		if (!isset($meta['type'])) {
			throw new ApiException("Invalid structure definition. Field '$fieldPath' must define 'type'.");
		}
		$type = $meta['type'];
		switch ($type) {
		case 'string':
			return (string)$value;

		case 'int':
			if (is_int($value)) {
				return $value;
			}
			if (is_string($value) && preg_match('/^[-+]?[0-9]+$/', $value)) {
				return (int)$value;
			}
			break;

		case 'double':
			if (is_numeric($value)) {
				return $value;
			}
			if (is_string($value) && preg_match('/^[-+]?[0-9]*\.?[0-9]+([eE][-+]?[0-9]+)?$/', $value)) {
				return (double)$value;
			}
			break;

		case 'bool':
			if (is_bool($value)) {
				return $value;
			}
			if (is_string($value)) {
				if ($value === 'true' || $value === '1' || $value === 'TRUE') {
					return true;
				}
				if ($value === 'false' || $value === '0' || $value === 'FALSE') {
					return false;
				}
			}
			if (is_int($value)) {
				if ($value === 1) {
					return true;
				}
				if ($value === 0) {
					return true;
				}
			}
			break;

		case 'date':
			if (!empty($value)) {
                if (!DateHelper::isValidPHPTimeStamp($value)) {
                    $date = strtotime($value);
                }
                if ($date !== false) {
                    return date($date, Config::$dateFormat);
                }
			}
			break;

		case 'array':
			if (is_array($value)) {
				if (isset($meta['children'])) {
					$childMeta = $meta['children'];
					$cleaned = array();
					foreach ($value as $childKey => $childValue) {
						$cleaned[$childKey] = self::cleanValue($childValue, $childMeta, "$fieldPath[$childKey]");
					}
					return $cleaned;
				}
				if (isset($meta['keys'])) {
					return self::cleanArray($value, $meta['keys'], "$fieldPath.");
				}
				throw new ApiException("Invalid structure definition. Field '$fieldPath' must define either 'children' or 'keys'.");
			}
			break;

		default:
			throw new ApiException("Invalid structure definition. Field '$fieldPath' defined with unknown type '$type'.");
		}
		throw new InvalidInputException("Field '$fieldPath' must be of type '$type'.");
	}

	/**
	 * Gets the set of permissions required for the given method/action and input object/parameters.
	 * @param string $method name of the core method or action
	 * @param array $input array representation of input object or array of query parameters
	 * @return array of strings that identifies the set of required permissions
	 */
	public abstract function getRequiredPermissions($method, $input);

	/**
	 * Gets a single object by id. Override and return an array that represents the object. The default implementation
	 * just throws an exception.
	 * @param int $id the object id
	 * @return array that represents the object
	 * @throws UnsupportedMethodException default implementation
	 */
	public function get($id) {
		throw new UnsupportedMethodException('This module cannot get');
	}

	/**
	 * Gets a collection of objects. Override and return an array of arrays that represent the objects. The default
	 * implementation just throws an exception.
	 * @param array $params array of query parameters that came in with the request
	 * @return array of arrays that represent the objects
	 * @throws UnsupportedMethodException default implementation
	 */
	public function getCollection($params) {
		throw new UnsupportedMethodException('This module cannot get collection');
	}

	/**
	 * Creates an object. Override and create the object, then return it as an array. The default implementation just
	 * throws an exception.
	 * @param array $object an array that defines the object
	 * @returns array that represents the object
	 * @throws UnsupportedMethodException default implementation
	 */
	public function create($object) {
		throw new UnsupportedMethodException('This module cannot create');
	}

	/**
	 * Updates an object. Override and update the object, then return it as an array. The default implementation just
	 * throws an exception.
	 * @param int $id the object id
	 * @param array $object an array that defines the object
	 * @returns array that represents the object
	 * @throws UnsupportedMethodException default implementation
	 */
	public function update($id, $object) {
		throw new UnsupportedMethodException('This module cannot update');
	}

	/**
	 * Deletes an object by id. Override and delete the object. The default implementation just throws an exception.
	 * @param int $id the object id
	 * @throws UnsupportedMethodException default implementation
	 */
	public function delete($id) {
		throw new UnsupportedMethodException('This module cannot delete');
	}

	/**
	 * Executes the specified API action.
	 * @param string $actionName name of the action
	 * @param array $params array of query parameters
	 * @return array that represents the response object
	 * @throws InvalidIdentifierException if the specified action does not exist
	 */
	public function runAction($actionName, $params) {
		// Call the action function if it exists
		$actionFunction = 'action_' . $actionName;
		if (method_exists($this, $actionFunction)) {
			return call_user_func(array($this, $actionFunction), $params);
		}

		// Otherwise, throw an exception
		throw new InvalidIdentifierException("Action '$actionName' does not exist");
	}

}

?>