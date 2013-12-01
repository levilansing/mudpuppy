<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy;

defined('MUDPUPPY') or die('Restricted');

class MudpuppyException extends \Exception {
	/** @var string $productionMessage will be sent to the browser if Config::$debug == false */
	protected $productionMessage = 'Internal server error';

	public function __construct($message = 'Internal server error', $statusCode = 500) {
		parent::__construct($message, $statusCode);
	}

	/**
	 * get the production message associated with this exception
	 * @return string
	 */
	public function getProductionMessage() {
		return $this->productionMessage;
	}
}

class InvalidInputException extends MudpuppyException {
	public function __construct($message = 'Invalid input') {
		$this->productionMessage = 'Invalid Input';
		parent::__construct($message, 400);
	}
}

class UnauthorizedException extends MudpuppyException {
	public function __construct($message = 'Authentication is required to perform this request') {
		$this->productionMessage = 'Authentication is required to perform this request';
		parent::__construct($message, 401);
	}
}

class PermissionDeniedException extends MudpuppyException {
	public function __construct($message = 'You do not have permission to perform this request') {
		$this->productionMessage = 'You do not have permission to perform this request';
		parent::__construct($message, 403);
	}
}

class ObjectNotFoundException extends MudpuppyException {
	public function __construct($message = 'Object not found') {
		$this->productionMessage = 'Object not found';
		parent::__construct($message, 404);
	}
}

class PageNotFoundException extends MudpuppyException {
	public function __construct($message = 'Page not found') {
		$this->productionMessage = 'Page not found';
		parent::__construct($message, 404);
	}
}

class UnsupportedMethodException extends MudpuppyException {
	public function __construct($message = 'Request method not supported in this context') {
		$this->productionMessage = 'Request method not supported in this context';
		parent::__construct($message, 405);
	}
}

?>