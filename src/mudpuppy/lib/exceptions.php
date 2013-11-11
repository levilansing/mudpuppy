<?php
defined('MUDPUPPY') or die('Restricted');

class ApiException extends Exception {
	public function __construct($message, $statusCode = 500) {
		parent::__construct($message, $statusCode);
	}
}

class InvalidInputException extends ApiException {
	public function __construct($message) {
		parent::__construct($message, 400);
	}
}

class PermissionsException extends ApiException {
	public function __construct($message) {
		parent::__construct($message, 403);
	}
}

class InvalidIdentifierException extends ApiException {
	public function __construct($message) {
		parent::__construct($message, 404);
	}
}

class UnsupportedMethodException extends ApiException {
	public function __construct($message) {
		parent::__construct($message, 405);
	}
}

?>