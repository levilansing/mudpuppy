<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy;

defined('MUDPUPPY') or die('Restricted');

class MudpuppyException extends \Exception {
	/** @var string $productionMessage will be sent to the browser if Config::$debug == false */
	protected $responseMessage = 'Internal server error';

	public function __construct($internalMessage = 'Internal server error', $responseMessage = null, $statusCode = 500) {
		if (!empty($responseMessage)) {
			$this->responseMessage = $responseMessage;
			if (empty($internalMessage)) {
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
		parent::__construct($internalMessage, $responseMessage, 400);
	}
}

class UnauthorizedException extends MudpuppyException {
	/**
	 * @param string $internalMessage The internal exception message to be logged but not returned to the browser
	 * @param null $responseMessage An optional message that will be returned to the browser
	 */
	public function __construct($internalMessage = 'Authentication is required to perform this request', $responseMessage = null) {
		$this->responseMessage = 'Authentication is required to perform this request';
		parent::__construct($internalMessage, $responseMessage, 401);
	}
}

class PermissionDeniedException extends MudpuppyException {
	/**
	 * @param string $internalMessage The internal exception message to be logged but not returned to the browser
	 * @param null $responseMessage An optional message that will be returned to the browser
	 */
	public function __construct($internalMessage = 'You do not have permission to perform this request', $responseMessage = null) {
		$this->responseMessage = 'You do not have permission to perform this request';
		parent::__construct($internalMessage, $responseMessage, 403);
	}
}

class ObjectNotFoundException extends MudpuppyException {
	/**
	 * @param string $internalMessage The internal exception message to be logged but not returned to the browser
	 * @param null $responseMessage An optional message that will be returned to the browser
	 */
	public function __construct($internalMessage = 'Object not found', $responseMessage = null) {
		$this->responseMessage = 'Object not found';
		parent::__construct($internalMessage, $responseMessage, 404);
	}
}

class PageNotFoundException extends MudpuppyException {
	public function __construct($internalMessage = 'Page not found', $responseMessage = null) {
		/**
		 * @param string $internalMessage The internal exception message to be logged but not returned to the browser
		 * @param null $responseMessage An optional message that will be returned to the browser
		 */
		$this->responseMessage = 'Page not found';
		parent::__construct($internalMessage, $responseMessage, 404);
	}
}

class UnsupportedMethodException extends MudpuppyException {
	/**
	 * @param string $internalMessage The internal exception message to be logged but not returned to the browser
	 * @param null $responseMessage An optional message that will be returned to the browser
	 */
	public function __construct($internalMessage = 'Request method not supported in this context', $responseMessage = null) {
		$this->responseMessage = 'Request method not supported in this context';
		parent::__construct($internalMessage, $responseMessage, 405);
	}
}

/**
 * Assert a truth value is true. if not, throw a standard MudpuppyException.
 * @param bool $truth
 * @param string $internalMessage
 * @param string $responseMessage
 * @throws MudpuppyException
 */
function MPAssert($truth, $internalMessage='', $responseMessage='') {
	if (!$truth) {
		if (empty($internalMessage) && empty($responseMessage)) {
			$internalMessage = 'Mudpuppy Assertion Failed';
		}
		throw new MudpuppyException($internalMessage, $responseMessage);
	}
}

?>