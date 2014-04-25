<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy;

defined('MUDPUPPY') or die('Restricted');

abstract class Controller {
	protected $pathOptions = array();

	/**
	 * Parses the request and returns an instance of the associated controller.
	 *
	 * @return Controller|PageController|DataObjectController
	 * @throws PageNotFoundException if no controller exists
	 */
	public static function getController() {
		// Carve up the path info into an array of parts
		$path = pathinfo($_SERVER['PATH_INFO']);
		if (isset($path['dirname']) && strlen($path['dirname']) > 1) {
			$parts = explode('/', substr($path['dirname'], 1));
		} else {
			$parts = [];
		}
		if (!isset($path['extension']) && isset($path['basename'])) {
			$parts[] = $path['basename'];
		}

		// Try to find the controller
		$controllerName = 'App\\Controller';
		$classes = App::getAutoloadClassList();
		$options = [];
		$nameIndex = -1;
		if (count($parts) > 0 && $parts[0] != '') {
			$namespace = 'App';

			// Check if we are viewing the admin area
			if (strcasecmp($parts[0], 'mudpuppy') == 0) {
				if (!Config::$debug) {
					throw new PageNotFoundException();
				}
				$namespace = 'Mudpuppy';
				array_splice($parts, 0, 1, ['Admin']);
			}

			for ($i = 0; $i < count($parts); $i++) {
				// Give precedence to existing directories
				if (isset($classes[strtolower($namespace . '\\' . $parts[$i])])) {
					// Append that directory to the current search and continue along
					$namespace .= '\\' . ucfirst($parts[$i]);
				} else {
					break;
				}
			}

			// Walk backwards and check each folder for its own controller
			for (--$i; $i >= 0; $i--) {
				if (isset($classes[strtolower($namespace)]) && isset($classes[strtolower($namespace)]['controller'])) {
					// Use the fully qualified class name, where directories equal namespaces
					$controllerName = $namespace . '\\Controller';
					$nameIndex = $i;
					break;
				}
				$namespace = substr($namespace, 0, strlen($namespace) - strlen($parts[$i]) - 1);
			}

			// only when in debug
			// if the controller still can't be found, determine if we should refresh the autoload and try again
			if ($nameIndex == -1 && Config::$debug) {
				$folder = $namespace;
				for ($i = 0; $i < count($parts); $i++) {
					$part = $parts[$i];
					if (file_exists($folder . '/' . ucfirst($part) . '/Controller.php')) {
						if (App::refreshAutoloadClassList()) {
							return self::getController();
						}
						break;
					}
					$folder .= '/' . ucfirst($part);
				}
			}
		}

		// Grab the input options
		$options = array_slice($parts, $nameIndex + 1);
		if (isset($path['extension'])) {
			$options[] = $path['basename'];
		}

		// Have to filter out the index.php that comes in for root requests (thanks apache)
		if (count($options) == 1 && ($options[0] == 'index.php' || $options[0] == '')) {
			$options = [];
		}

		// Make sure the class exists and is a subclass of Controller
		if (!class_exists($controllerName) || !(new \ReflectionClass($controllerName))->isSubclassOf('Mudpuppy\Controller')) {
			throw new PageNotFoundException('No controller found for request: ' . $_SERVER['PATH_INFO']);
		}

		// Instantiate and return the controller
		return new $controllerName($options);
	}

	public function __construct($pathOptions) {
		$this->pathOptions = $pathOptions;
	}

	/** @returns array */
	abstract public function getRequiredPermissions();

	public function processRequest() {
		$response = null;
		try {
			$method = $_SERVER['REQUEST_METHOD'];
			$reflection = new \ReflectionClass($this);
			$traits = $reflection->getTraitNames();
			$isPage = in_array('Mudpuppy\PageController', $traits);
			$isDataObject = in_array('Mudpuppy\DataObjectController', $traits);
			$option = $this->getOption(0);
			if ($option === null) {
				if (!$isDataObject) {
					if ($isPage) {
						return;
					}

					throw new UnsupportedMethodException("Request method $method is invalid for this URL");
				}
				/** @var DataObjectController $this */
				switch ($method) {
				case 'GET':
					// Retrieve a collection of objects: GET <module>?<params>
					Request::setParams($_GET);
					$response = $this->getCollection($_GET);
					break;
				case 'POST':
					// Create an object: POST <module>
					$db = App::getDBO();
					$db && $db->beginTransaction();
					$params = json_decode(file_get_contents('php://input'), true);
					$params = $params != null ? $params : $_POST;
					Request::setParams($params);
					$response = $this->create($params);
					$db && $db->commitTransaction();
					break;
				default:
					throw new UnsupportedMethodException("Request method $method is invalid for this URL");
				}
			} else if (preg_match('/[0-9]+/', $option)) {
				if (!$isDataObject) {
					if ($isPage) {
						return;
					}
					throw new UnsupportedMethodException("Request method $method is invalid for this URL");
				}

				if (count($this->pathOptions) > 1) {
					throw new PageNotFoundException("Additional \$pathOptions are not allowed for DataObjectController api calls");
				}

				/** @var DataObjectController $this */
				$id = (int)$option;
				switch ($method) {
				case 'GET':
					// Retrieve a single object: GET <module>/<id>
					$response = $this->get($id);
					break;
				case 'PUT':
					// Update an object: PUT <module>/<id>
					$db = App::getDBO();
					$db && $db->beginTransaction();
					$params = json_decode(file_get_contents('php://input'), true);
					$params = $params != null ? $params : $_POST;
					Request::setParams($params);
					$response = $this->update($id, $params);
					$db && $db->commitTransaction();
					break;
				case 'DELETE':
					// Delete an object: DELETE <module>/<id>
					$db = App::getDBO();
					$db && $db->beginTransaction();
					$this->delete((int)$id);
					$db && $db->commitTransaction();
					break;
				default:
					throw new UnsupportedMethodException("Request method $method is invalid for this URL");
				}
			} else {
				$actionName = Request::cleanValue($option, '', 'cmd');
				if (!$reflection->hasMethod('action_' . $actionName)) {
					if ($isPage) {
						return;
					}
					throw new UnsupportedMethodException("The method action_$actionName does not exist in {$reflection->getName()}");
				}

				if (count($this->pathOptions) > 1) {
					throw new PageNotFoundException("Additional \$pathOptions are not allowed for action api calls");
				}

				switch ($method) {
				case 'GET':
					// Call an action: GET <module>/<action>?<params>
					Request::setParams($_GET);
					$response = $this->runAction($actionName, $_GET);
					break;
				case 'POST':
					// Call an action: POST <module>/<action>
					$db = App::getDBO();
					$db && $db->beginTransaction();

					// check if we should decode the params from php://input, default to $_POST params
					$input = file_get_contents('php://input');

					// try json decode first
					$params = json_decode($input, true);
					if (!empty($params)) {
						Request::setParams($params);
					} else {
						// attempt URL decode next
						$params = [];
						foreach (explode('&', $input) as $chunk) {
							$param = explode("=", $chunk);

							if (count($param) == 2) {
								$params[urldecode($param[0])] = urldecode($param[1]);
							}
						}
						if (count($params) > 0) {
							Request::setParams($params);
						} else {
							Request::setParams($_POST);
						}
					}

					$response = $this->runAction($actionName, $params);
					$db && $db->commitTransaction();
					break;
				default:
					throw new UnsupportedMethodException("Request method $method is invalid for this URL");
				}
			}

			header('Content-type: application/json');
			$output = json_encode($response);
			if ($error = json_last_error()) {
				throw new MudpuppyException("JSON Encoding error: (#$error) " . json_last_error_msg());
			} else {
				print $output;
			}

		} catch (\Exception $e) {
			Log::exception($e);
			$db = App::getDBO();
			$db && $db->rollBackTransaction();
			$statusCode = 500;
			if ($e instanceof MudpuppyException) {
				$statusCode = $e->getCode();
			}
			http_response_code($statusCode);
			$message = $e->getMessage();
			if (!Config::$debug) {
				if ($e instanceof MudpuppyException) {
					$message = $e->getResponseMessage();
				} else {
					$message = 'Internal Sever Error';
				}
			}
			$response = array('error' => $statusCode, 'message' => $message);

			header('Content-type: application/json');
			$output = json_encode($response);
			if ($error = json_last_error()) {
				Log::error("JSON Encoding error: (#$error) " . json_last_error_msg());
				print '{"error": 500, "message" : "Internal Server Error"}';
			} else {
				print $output;
			}

		}

		App::cleanExit(true);
	}

	/**
	 * Runs an action on this controller
	 *
	 * @param string $actionName
	 * @return mixed
	 * @throws UnsupportedMethodException
	 * @throws InvalidInputException
	 */
	private function runAction($actionName) {
		// Get the method and PhpDoc
		$reflection = new \ReflectionClass($this);
		$method = $reflection->getMethod('action_' . $actionName);
		$documentation = $method->getDocComment();

		// Validate, clean, and list the parameters
		$parameters = array();
		foreach ($method->getParameters() as $parameter) {
			$parameterName = $parameter->getName();
			$default = null;
			if ($parameter->isDefaultValueAvailable()) {
				$default = $parameter->getDefaultValue();
			} else {
				if (!Request::isParam($parameterName)) {
					throw new InvalidInputException("call to {$reflection->getName()}::{$method->getName()}(...) is missing parameter '$parameterName'");
				}
			}

			$className = $parameter->getClass();
			$isArray = false;
			if (!$className) {
				// Use the doc comments to clean the input parameter
				if (!preg_match('/\*\s+@param\s+([\w\\\\]+)(\[\])?\s+\$' . $parameterName . '\s+/i', $documentation, $matches)) {
					throw new InvalidInputException("{$reflection->getName()}::{$method->getName()}($$parameterName) is not documented with proper types");
				}
				// $matches[1] is the type name
				$className = $matches[1];
				if (isset($matches[2])) {
					$isArray = true;
				}
			}

			// Support arrays of specific types: int[] or DataObject[]
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

				case 'array':
				case 'string':
					// Non-typed arrays will assume string values
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
					$parameterValue = $dataObject;
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
	 * Create a data object by $className and load with row $id
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
		$parameterClass = new \ReflectionClass($className);
		if (!$parameterClass->isSubclassOf('Mudpuppy\DataObject')) {
			throw new UnsupportedMethodException("$className does not extend DataObject", 500);
		}

		// create the DataObject and load from the specified id
		/** @var DataObject $dataObject */
		$dataObject = new $className();
		$dataObject->id = (int)$id;
		if (!$dataObject->load()) {
			return null;
		}

		return $dataObject;
	}

	protected function getOption($index) {
		if (isset($this->pathOptions[$index])) {
			return $this->pathOptions[$index];
		}
		return null;
	}

}