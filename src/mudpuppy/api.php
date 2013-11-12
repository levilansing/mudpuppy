<?php
require('mudpuppy.php');
MPAutoLoad('Exceptions');

header('Content-type: application/json');

try {
	$requestMethod = $_SERVER['REQUEST_METHOD'];
	$response = null;
	$path = $_SERVER['PATH_INFO'];
	if (preg_match('/^\/([a-zA-Z]+)\/?$/', $path, $matches)) {
        /** @var DataObjectController|Controller $controller */
        $controller = Controller::getController($matches[1]);

        // to use this API URL Scheme the controller must implement the DataObjectController trait
        if (!in_array('DataObjectController', class_uses($controller)))
            throw new UnsupportedMethodException('Request method ' . $_SERVER['REQUEST_METHOD'] . ' is invalid for this URL');

		switch ($requestMethod) {
		case 'GET':
			// Retrieve a collection of objects: GET /api/<module>?<params>
            Request::setParams($_GET);
			$response = $controller->getCollection($_GET);
			break;
		case 'POST':
			// Create an object: POST /api/<module>
            $db = App::getDBO();
			$db && $db->beginTransaction();
            $params = json_decode(file_get_contents('php://input'), true);
            $params = $params != null ? $params : $_POST;
            Request::setParams($params);
			$response = $controller->create($params);
            $db && $db->commitTransaction();
			break;
		default:
			throw new UnsupportedMethodException('Request method ' . $_SERVER['REQUEST_METHOD'] . ' is invalid for this URL');
		}
	} elseif (preg_match('/^\/([a-zA-Z]+)\/([0-9]+)\/?$/', $path, $matches)) {
        /** @var DataObjectController|Controller $controller */
        $controller = Controller::getController($matches[1]);

        // to use this API URL scheme the controller must implement the DataObjectController trait
        if (!in_array('DataObjectController', class_uses($controller)))
            throw new UnsupportedMethodException('Request method ' . $_SERVER['REQUEST_METHOD'] . ' is invalid for this URL');

		switch ($requestMethod) {
		case 'GET':
			// Retrieve a single object: GET /api/<module>/id
            $params = array('id'=>(int)$matches[2]);
            Request::setParams($params);
			$response = $controller->get((int)$matches[2]);
			break;
		case 'PUT':
			// Update an object: PUT /api/<module>/id
            $db = App::getDBO();
            $db && $db->beginTransaction();
            $params = json_decode(file_get_contents('php://input'), true);
            $params = $params != null ? $params : $_POST;
            Request::setParams($params);
			$response = $controller->update((int)$matches[2], $params);
			$db && $db->commitTransaction();
			break;
		case 'DELETE':
			// Delete an object: DELETE /api/<module>/id
            $db = App::getDBO();
			$db && $db->beginTransaction();
            $params = array('id' => (int)$matches[2]);
            Request::setParams($params);
			$controller->delete((int)$matches[2]);
			$db && $db->commitTransaction();
			break;
		default:
			throw new UnsupportedMethodException('Request method ' . $_SERVER['REQUEST_METHOD'] . ' is invalid for this URL');
		}
	} elseif (preg_match('/^\/([a-zA-Z]+)\/([a-zA-Z]+)\/?$/', $path, $matches)) {
        $controller = Controller::getController($matches[1]);
		switch ($requestMethod) {
		case 'GET':
			// Call an action: GET /api/<module>/<action>?<params>
            Request::setParams($_GET);
			$response = $controller->runAction($matches[2], $_GET);
			break;
		case 'POST':
			// Call an action: POST /api/<module>/<action>
            $db = App::getDBO();
			$db && $db->beginTransaction();
            $params = json_decode(file_get_contents('php://input'), true);
            $params = $params != null ? $params : $_POST;
            Request::setParams($params);
			$response = $controller->runAction($matches[2], $params);
			$db && $db->commitTransaction();
			break;
		default:
			throw new UnsupportedMethodException('Request method ' . $_SERVER['REQUEST_METHOD'] . ' is invalid for this URL');
		}
	} else {
		throw new InvalidIdentifierException('Invalid URL schema');
	}

    if ($response != null)
	    print json_encode($response);

} catch (ApiException $e) {
    Log::error('API Exception: ' . $e->getMessage());
    $db = App::getDBO();
	$db && $db->rollBackTransaction();
	http_response_code($e->getCode());
	print json_encode(array('message' => $e->getMessage()));
} catch (Exception $e) {
	Log::error('Exception in API: ' . $e->getMessage());
    $db = App::getDBO();
	$db && $db->rollBackTransaction();
	http_response_code(500);
	print json_encode(array('message' => 'The server encountered an unexpected error. See log for details.'));
}

App::cleanExit();

?>