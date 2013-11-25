<?php
defined('MUDPUPPY') or die('Restricted');

class MudpuppyException extends Exception {
	public function __construct($message = 'Internal server error', $statusCode = 500) {
		parent::__construct($message, $statusCode);
	}
}

class InvalidInputException extends MudpuppyException {
	public function __construct($message = 'Invalid input') {
		parent::__construct($message, 400);
	}
}

class PermissionDeniedException extends MudpuppyException {
	public function __construct($message = 'You do not have permission to perform this request') {
		parent::__construct($message, 403);
	}
}

class ObjectNotFoundException extends MudpuppyException {
	public function __construct($message = 'Object not found') {
		parent::__construct($message, 404);
	}
}

class PageNotFoundException extends MudpuppyException {
	public function __construct($message = 'Page not found') {
		parent::__construct($message, 404);
	}
}

class UnsupportedMethodException extends MudpuppyException {
	public function __construct($message = 'Request method not supported in this context') {
		parent::__construct($message, 405);
	}
}

?>