<?php
/**
 * @author Levi Lansing
 * Created 8/28/13
 */
defined('MUDPUPPY') or die('Restricted');
MPAutoLoad('Exceptions');

abstract class Controller {
	var $options = array();
	var $view;
	var $id;

	/**
	 * @param string $name optional name of the controller
	 * @return Controller
	 */
	public static function getController($name = '') {
		$path = pathinfo($_SERVER['PATH_INFO']);
		if (isset($path['dirname']) && strlen($path['dirname']) > 1) {
			$parts = explode('/', substr($path['dirname'], 1));
		} else {
			$parts = [];
		}
		if (!isset($path['extension']) && isset($path['basename'])) {
			$parts[] = $path['basename'];
		}

		$controllerName = 'HomeController';
		if ($name) {
			$controllerName = $name . 'Controller';
		} else if (count($parts) > 0 && $parts[0] != '') {
			$controllers = [];
			$controllerName = '';
			foreach ($parts as $part) {
				$controllerName .= ucfirst($part);
				$controllers[] = $controllerName . 'Controller';
			}
			$controllerName = end($controllers);
			while ($controllerName && !class_exists($controllerName)) {
				$controllerName = prev($controllers);
			}
		}
		if (!class_exists($controllerName)) {
			if (Config::$debug) {
				Log::error("Controller does not exist for request: " . $_SERVER['PATH_INFO']);
			}
			App::abort(404);
		}

		$options = $parts;
		$cName = '';
		$index = 1;
		foreach ($parts as $part) {
			$cName .= $part;
			if (strcasecmp($cName . 'Controller', $controllerName) == 0) {
				$options = array_slice($parts, $index);
				break;
			}
		}

		if ($controllerName == 'Controller') {
			return null;
		}

		return new $controllerName($options);
	}

	public function __construct($options) {
		$this->options = $options;
	}

	/** @returns array */
	abstract public function getRequiredPermissions();

	public function processPost() {
		$action = Request::getCmd('action', null, 'POST');
		if ($action && method_exists($this, 'action_' . $action)) {
			call_user_func(array($this, 'action_' . $action));
		}
	}

	/**
	 * run an action on this controller
	 * @param $actionName
	 * @return mixed
	 * @throws UnsupportedMethodException
	 * @throws InvalidInputException
	 */
	public function runAction($actionName) {
		$action = Request::cleanValue($actionName, '', 'cmd');

		// verify the action exists in this controller
		$reflection = new ReflectionClass($this);
		if (!$reflection->hasMethod('action_' . $action)) {
			throw new UnsupportedMethodException("The method action_$action does not exist in {$reflection->getName()}");
		}

		// get the method and PhpDoc
		$method = $reflection->getMethod('action_' . $action);
		$documentation = $method->getDocComment();

		// validate, clean, and list the parameters
		$parameters = array();
		foreach ($method->getParameters() as $parameter) {
			$parameterName = $parameter->getName();
			$default = null;
			if ($parameter->isDefaultValueAvailable()) {
				$default = $parameter->getDefaultValue();
			} else {
				if (!Request::isParam($parameterName)) {
					throw new InvalidInputException("{$reflection->getName()}::{$method->getName()} is missing parameter '$parameterName'");
				}
			}

			$className = $parameter->getClass();
			$isArray = false;
			if (!$className) {
				// use the doc comments to clean the input parameter
				if (!preg_match('/\* +@param (\w+)(\[\])? \$' . $parameterName . '(?= |\n)/i', $documentation, $matches)) {
					throw new InvalidInputException("{$reflection->getName()}::{$method->getName()}($$parameterName) is not documented with proper types");
				}
				// $matches[1] is the type name
				$className = $matches[1];
				if (isset($matches[2])) {
					$isArray = true;
				}
			}

			// support arrays of specific types: int[] or DataObject[]
			if ($isArray) {
				$values = Request::getArray($parameterName, $default);
			} else {
				$values = [Request::get($parameterName, $default)];
			}
			$parameterValues = [];
			foreach ($values as $parameterValue) {
				switch (strtolower($className)) {
				case 'bool':
				case 'boolean':
					$v = strtolower($parameterValue);
					if ($v === 'true' || $v === 'on' || $v == '1') {
						$parameterValue = true;
					} else {
						$parameterValue = false;
					}
					break;

					// do we throw exceptions if it is non-numeric? i think we should.
				case 'int':
				case 'integer':
					if (!is_numeric($parameterValue) || (!is_int($parameterValue) && !preg_match('/^-?[0-9]+$/', $parameterValue))) {
						throw new InvalidInputException("$parameterName ($parameterValue) is not an integer");
					}
					$parameterValue = (int)$parameterValue;
					break;

				case 'float':
				case 'double':
					if (!is_numeric($parameterValue)) {
						throw new InvalidInputException("$parameterName ($parameterValue) is not a number");
					}
					$parameterValue = (double)$parameterValue;
					break;

					// non-typed arrays will assume string values
				case 'array':
				case 'string':
					break;

				default:
					// Default to DataObject subclasses
					$dataObject = $this->createDataObject($className, $parameterValue);
					if (!$dataObject) {
						if ($parameter->isDefaultValueAvailable()) {
							$dataObject = $default;
						} else {
							throw new InvalidInputException("DataObject $className(id=" . (int)$parameterValue . ") does not exist");
						}
					}
				}
				$parameterValues[] = $parameterValue;
			}
			if ($isArray) {
				$parameters[] = $parameterValues;
			} else {
				$parameters[] = reset($parameterValues);
			}
		}

		return $method->invokeArgs($this, $parameters);
	}

	/**
	 * @param string $className
	 * @param int $id
	 * @return DataObject|null
	 * @throws UnsupportedMethodException
	 */
	private function createDataObject($className, $id) {
		$id = (int)$id;
		if ($id == 0) {
			return null;
		}

		// verify the class is a DataObject
		$parameterClass = new ReflectionClass($className);
		if (!$parameterClass->isSubclassOf("DataObject")) {
			throw new UnsupportedMethodException("$className does not extend DataObject", 500);
		}

		// create the DataObject and load from the specified id
		/** @var DataObject $param */
		$dataObject = new $className((int)$className);
		if (!$param->load()) {
			return null;
		}

		return $dataObject;
	}

	protected function getOption($index) {
		if (isset($this->options[$index])) {
			return $this->options[$index];
		}
		return null;
	}
}