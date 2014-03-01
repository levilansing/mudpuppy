<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy;

defined('MUDPUPPY') or die('Restricted');

/**
 * Trait to use in any controller that directly represents a DataObject. This class provides default implementations of
 * the core controller methods (get, getCollection, create, update, delete) that should be sufficient for most
 * DataObject types. In support of this, the concrete class must implement three methods (getStructureDefinition,
 * isValid, and sanitize), which provide the structure definition array, validate input objects (prior to creating and
 * updating), and sanitize objects (prior to returning to the user). Many objects may not need to perform any special
 * validation or sanitization, but no default implementation is provided in order to force the implementer to consider
 * such situations.
 * In addition to the core methods, the DataObjectcontroller implements an action called 'schema' that
 * returns the data object's structure definition (or schema).
 */
trait DataObjectController {

	private $dataObjectName;

	/**
	 * @param string $dataObjectName name of the data object class (which must implement DataObject)
	 */
	public function setDataObjectName($dataObjectName) {
		$this->dataObjectName = $dataObjectName;
	}

	public function getDataObjectName() {
		if ($this->dataObjectName == null) {
			$className = explode('\\', get_called_class());
			$className = 'Model\\'.end($className);
			if (strrpos($className, 'Controller') == strlen($className) - 10)
				$className = substr($className, 0, -10);

			$this->dataObjectName = $className;
		}
		return $this->dataObjectName;
	}

	/**
	 * Gets the data object for the given id.
	 * @param int $id the object id
	 * @return array that represents the object (sanitized)
	 * @throws ObjectNotFoundException if no object is found for the given id
	 * @throws MudpuppyException if retrieved object is not a DataObject
	 */
	public function get($id) {
		/** @var $dataObject DataObject */
		$dataObject = call_user_func(array($this->getDataObjectName(), 'fetchOne'), $id);
		if ($dataObject === null) {
			throw new ObjectNotFoundException("No object found for id: $id");
		}
		if (!$dataObject instanceof DataObject) {
			throw new MudpuppyException('Failed to retrieve object');
		}
		return $this->sanitize($dataObject->toArray());
	}

	/**
	 * Gets a collection of data objects. The default implementation returns ALL objects. Override the
	 * retrieveDataObjects method to support filtering based on the given query parameters.
	 * @param array $params array of query parameters that came in with the request
	 * @return array of arrays that represent the data objects (sanitized)
	 * @throws MudpuppyException if retrieveDataObjects doesn't return an array of DataObjects
	 */
	public function getCollection($params) {
		$dataObjects = $this->retrieveDataObjects($params);
		if ($dataObjects === null || !is_array($dataObjects)) {
			throw new MudpuppyException('Failed to retrieve objects');
		}
		$output = array();
		foreach ($dataObjects as $dataObject) {
			/** @var $dataObject DataObject */
			if (!($dataObject instanceof DataObject)) {
				throw new MudpuppyException('Failed to retrieve objects');
			}
			$output[] = $this->sanitize($dataObject->toArray());
		}
		return $output;
	}

	/**
	 * Creates a data object from the given array representation.
	 * @param array $object array representation of the object
	 * @return array that represents the newly created object
	 * @throws InvalidInputException if the input object does not validate
	 * @throws ObjectNotFoundException if the object already exists
	 * @throws MudpuppyException if the object fails to create
	 */
	public function create($object) {
		$object = self::cleanArray($object, $this->getStructureDefinition());
		if (!$this->isValid($object)) {
			throw new InvalidInputException('The specified object is invalid');
		}
		/** @var $dataObject DataObject */
		$dataObject = new $this->dataObjectName();
		foreach ($object as $field => $value) {
			$dataObject->$field = $value;
		}
		if ($dataObject->exists()) {
			throw new ObjectNotFoundException('The specified object already exists');
		}
		if (!$dataObject->save()) {
			throw new MudpuppyException('Failed to create object');
		}
		return $this->sanitize($dataObject->toArray());
	}

	/**
	 * Updates a data object based on the given array representation.
	 * @param int $id the object id
	 * @param array $object array representation of the object
	 * @return array that represents the updated object
	 * @throws InvalidInputException if the input object does not validate
	 * @throws ObjectNotFoundException if the object doesn't exists
	 * @throws MudpuppyException if the object fails to update
	 */
	public function update($id, $object) {
		$object = self::cleanArray($object, $this->getStructureDefinition());
		if (!$this->isValid($object)) {
			throw new InvalidInputException('The specified object is invalid');
		}
		/** @var $dataObject DataObject */
		$dataObject = call_user_func(array($this->getDataObjectName(), 'fetchOne'), $id);
		if ($dataObject === null) {
			throw new ObjectNotFoundException('The specified object does not exist');
		}
		foreach ($object as $field => $value) {
			$dataObject->$field = $value;
		}
		if (!$dataObject->save()) {
			throw new MudpuppyException('Failed to update object');
		}
		return $this->sanitize($dataObject->toArray());
	}

	/**
	 * Deletes a data object for the given id.
	 * @param int $id the object id
	 * @throws ObjectNotFoundException if no object is found for the given id
	 * @throws MudpuppyException if the object fails to delete
	 */
	public function delete($id) {
		/** @var $dataObject DataObject */
		$dataObjectClass = $this->getDataObjectName();
		$dataObject = new $dataObjectClass($id);
		if (!$dataObject->exists()) {
			throw new ObjectNotFoundException("No object found for id: $id");
		}
		if (!$dataObject->delete()) {
			throw new MudpuppyException("Failed to delete object with id: $id");
		}
	}

	/**
	 * Action handler to get the data object structure definition (or schema).
	 * @param $params
	 * @return array
	 */
	public function action_schema($params) {
		return $this->getStructureDefinition();
	}

	/**
	 * Retrieves an array of DataObjects for use by getCollection. The default implementation returns ALL objects.
	 * Override to support filtering based on the query parameters.
	 * @param array $params array of query parameters that came in with the request
	 * @return array(DataObject)
	 */
	protected function retrieveDataObjects($params) {
		return call_user_func(array($this->getDataObjectName(), 'fetchAll'));
	}

	/**
	 * Gets the structure definition array for the data object. See self::cleanArray() for details.
	 * Defaults to the defined data object structure.
	 * Override to customize.
	 * @return array
	 */
	protected function getStructureDefinition() {
		return call_user_func(array($this->getDataObjectName(), 'getStructureDefinition'));
	}

	/**
	 * Determines whether an input object is valid prior to creating or updating it. Note: the input array has already
	 * been cleaned and validated against the structure definition.
	 * @param $object array representation of the object
	 * @return boolean true if valid
	 */
	protected abstract function isValid($object);

	/**
	 * Sanitizes the array representation of the object prior to returning to the user.
	 * @param array $object array representation of the object
	 * @return array that represents the sanitized object
	 */
	protected abstract function sanitize($object);


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
	 * @throws MudpuppyException if there is something wrong with the structure definition
	 * @throws InvalidInputException if the input doesn't match the required structure
	 */
	public static function cleanArray($input, $structure, $parentPath = '') {
		$cleaned = array();
		if (!is_array($structure)) {
			throw new MudpuppyException("No structure defined at path '$parentPath'.");
		}
		foreach ($structure as $key => $meta) {
			if (!isset($meta['type'])) {
				throw new MudpuppyException("Invalid structure definition. Field '$parentPath$key' must define 'type'.");
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
	 * @throws MudpuppyException if there is something wrong with the structure definition
	 * @throws InvalidInputException if the input doesn't match required structure
	 */
	public static function cleanValue($value, $meta, $fieldPath) {
		if (!isset($meta['type'])) {
			throw new MudpuppyException("Invalid structure definition. Field '$fieldPath' must define 'type'.");
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

		// todo issue #27 allow time stamp? handle date vs datetime differently?
		case 'date':
			if (!empty($value)) {
				$date = false;
				if (!DateHelper::isValidPHPTimeStamp($value)) {
					$date = strtotime($value);
				}
				if ($date !== false) {
					return date($date, Config::$dateTimeFormat);
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
				throw new MudpuppyException("Invalid structure definition. Field '$fieldPath' must define either 'children' or 'keys'.");
			}
			break;

		default:
			throw new MudpuppyException("Invalid structure definition. Field '$fieldPath' defined with unknown type '$type'.");
		}
		throw new InvalidInputException("Field '$fieldPath' must be of type '$type'.");
	}

}

?>