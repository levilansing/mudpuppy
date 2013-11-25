<?php
/**
 * @author Levi Lansing
 * Created 8/28/13
 */
defined('MUDPUPPY') or die('Restricted');

abstract class Controller {

	/**
	 * Parses the request and returns an instance of the associated controller.
	 *
	 * @return Controller
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
		$controllerName = 'app\\HomeController';
		$options = [];
		if (count($parts) > 0 && $parts[0] != '') {
			// Parse out the controller name
			$nameIndex = -1;
			if ($parts[0] == 'mudpuppy') {
				$controllerName = 'AdminController';
				$nameIndex = 0;
				if (count($parts) > 1 && $parts[1] == 'log') {
					$controllerName = 'LogController';
					$nameIndex = 1;
				}
			} else {
				$searchPath = 'app/';
				for ($i = 0; $i < count($parts); $i++) {
					// Give precedence to existing directories
					if (file_exists($searchPath . $parts[$i] . '/')) {
						// Append that directory to the current search and continue along
						$searchPath .= $parts[$i] . '/';
					} else {
						// Otherwise, check for the class file
						if (file_exists($searchPath . ucfirst($parts[$i]) . 'Controller.php')) {
							// Use that fully qualified class name, in which directories equal namespaces
							$controllerName = implode('\\', array_merge(['app'], array_slice($parts, 0, $i), [ucfirst($parts[$i] . 'Controller')]));
							$nameIndex = $i;
						}
						// And stop the search either way
						break;
					}
				}
			}

			// Grab the input options
			$options = array_slice($parts, $nameIndex + 1);
			if (isset($path['extension'])) {
				$options[] = $path['basename'];
			}
		}

		// Make sure the class exists and is a subclass of Controller
		if (!class_exists($controllerName) || !(new ReflectionClass($controllerName))->isSubclassOf('Controller')) {
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
			$reflection = new ReflectionClass($this);
			$traits = $reflection->getTraitNames();
			$isPage = in_array('PageController', $traits);
			$isDataObject = in_array('DataObjectController', $traits);
			$option = $this->getOption(0);
			if ($option === null) {
				if (!$isDataObject) {
					if ($isPage) {
						return;
					}
					throw new UnsupportedMethodException("Request method $method is invalid for this URL");
				}
				$isApi = true;
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
					$params = json_decode(file_get_contents('php://input'), true);
					$params = $params != null ? $params : $_POST;
					Request::setParams($params);
					$response = $this->runAction($actionName, $params);
					$db && $db->commitTransaction();
					break;
				default:
					throw new UnsupportedMethodException("Request method $method is invalid for this URL");
				}
			}
		} catch (Exception $e) {
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
				switch ($statusCode) {
				case 400:
					$message = 'Invalid input';
					break;
				case 403:
					$message = 'You do not have permission to perform this request';
					break;
				case 404:
					$message = 'Object not found';
					break;
				default:
					$message = 'Request method not supported in this context';
				}
			}
			$response = array('error' => $statusCode, 'message' => $message);
		}

		header('Content-type: application/json');
		if ($response != null) {
			print json_encode($response);
		}
		App::cleanExit();
	}

	/**
	 * Runs an action on this controller.
	 *
	 * @param string $actionName
	 * @return mixed
	 * @throws UnsupportedMethodException
	 * @throws InvalidInputException
	 */
	private function runAction($actionName) {
		// Get the method and PhpDoc
		$reflection = new ReflectionClass($this);
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
					throw new InvalidInputException("{$reflection->getName()}::{$method->getName()} is missing parameter '$parameterName'");
				}
			}

			$className = $parameter->getClass();
			$isArray = false;
			if (!$className) {
				// Use the doc comments to clean the input parameter
				if (!preg_match('/\*\s+@param\s+(\w+)(\[\])?\s+\$' . $parameterName . '\s/i', $documentation, $matches)) {
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
		if (!$parameterClass->isSubclassOf('DataObject')) {
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
		if (isset($this->pathOptions[$index])) {
			return $this->pathOptions[$index];
		}
		return null;
	}

	protected $pathOptions = array();

}