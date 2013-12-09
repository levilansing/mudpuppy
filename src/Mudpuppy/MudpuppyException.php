<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy;

defined('MUDPUPPY') or die('Restricted');

class MudpuppyException extends \Exception {
	/** @var string $productionMessage will be sent to the browser if Config::$debug == false */
	protected $responseMessage = 'Internal server error';

	public function __construct($internalMessage = 'Internal server error', $statusCode = 500, $responseMessage = null) {
		if ($responseMessage) {
			$this->responseMessage = $responseMessage;
			if (!$internalMessage) {
				$internalMessage = $responseMessage;
			}
		}
		parent::__construct($internalMessage, $statusCode);
	}

	/**
	 * get the production message associated with this exception
	 * @return string
	 */
	public function getResponseMessage() {
		return $this->responseMessage;
	}
}

class InvalidInputException extends MudpuppyException {
	/**
	 * @param string $internalMessage The internal exception message to be logged but not returned to the browser
	 * @param null $responseMessage An optional message that will be returned to the browser
	 */
	public function __construct($internalMessage = 'Invalid input', $responseMessage = null) {
		$this->responseMessage = 'Invalid Input';
		parent::__construct($internalMessage, 400, $responseMessage);
	}
}

class UnauthorizedException extends MudpuppyException {
	/**
	 * @param string $internalMessage The internal exception message to be logged but not returned to the browser
	 * @param null $responseMessage An optional message that will be returned to the browser
	 */
	public function __construct($internalMessage = 'Authentication is required to perform this request', $responseMessage = null) {
		$this->responseMessage = 'Authentication is required to perform this request';
		parent::__construct($internalMessage, 401, $responseMessage);
	}
}

class PermissionDeniedException extends MudpuppyException {
	/**
	 * @param string $internalMessage The internal exception message to be logged but not returned to the browser
	 * @param null $responseMessage An optional message that will be returned to the browser
	 */
	public function __construct($internalMessage = 'You do not have permission to perform this request', $responseMessage = null) {
		$this->responseMessage = 'You do not have permission to perform this request';
		parent::__construct($internalMessage, 403, $responseMessage);
	}
}

class ObjectNotFoundException extends MudpuppyException {
	/**
	 * @param string $internalMessage The internal exception message to be logged but not returned to the browser
	 * @param null $responseMessage An optional message that will be returned to the browser
	 */
	public function __construct($internalMessage = 'Object not found', $responseMessage = null) {
		$this->responseMessage = 'Object not found';
		parent::__construct($internalMessage, 404, $responseMessage);
	}
}

class PageNotFoundException extends MudpuppyException {
	public function __construct($internalMessage = 'Page not found', $responseMessage = null) {
		/**
		 * @param string $internalMessage The internal exception message to be logged but not returned to the browser
		 * @param null $responseMessage An optional message that will be returned to the browser
		 */
		$this->responseMessage = 'Page not found';
		parent::__construct($internalMessage, 404, $responseMessage);
	}
}

class UnsupportedMethodException extends MudpuppyException {
	/**
	 * @param string $internalMessage The internal exception message to be logged but not returned to the browser
	 * @param null $responseMessage An optional message that will be returned to the browser
	 */
	public function __construct($internalMessage = 'Request method not supported in this context', $responseMessage = null) {
		$this->responseMessage = 'Request method not supported in this context';
		parent::__construct($internalMessage, 405, $responseMessage);
	}
}

?>