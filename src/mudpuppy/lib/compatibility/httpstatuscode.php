<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================
defined('MUDPUPPY') or die('Restricted');

/**
 * Compatibility code for http_response_code / PHP < 5.4
 */
if (!function_exists('http_response_code')) {
	function http_response_code($code = null) {
		static $lastCode = 200;
		if (empty($code)) {
			return $lastCode;
		}
		$lastCode = $code;

		if ($code !== null) {
			switch ($code) {
			case 100:
				$text = 'Continue';
				break;
			case 101:
				$text = 'Switching Protocols';
				break;
			case 102:
				$text = 'Processing';
				break;
			case 200:
				$text = 'OK';
				break;
			case 201:
				$text = 'Created';
				break;
			case 202:
				$text = 'Accepted';
				break;
			case 203:
				$text = 'Non-Authoritative Information';
				break;
			case 204:
				$text = 'No Content';
				break;
			case 205:
				$text = 'Reset Content';
				break;
			case 206:
				$text = 'Partial Content';
				break;
			case 207:
				$text = 'Multi-Status';
				break;
			case 300:
				$text = 'Multiple Choices';
				break;
			case 301:
				$text = 'Moved Permanently';
				break;
			case 302:
				$text = 'Moved Temporarily';
				break;
			case 303:
				$text = 'See Other';
				break;
			case 304:
				$text = 'Not Modified';
				break;
			case 305:
				$text = 'Use Proxy';
				break;
			case 307:
				$text = 'Temporary Redirect';
				break;
			case 400:
				$text = 'Bad Request';
				break;
			case 401:
				$text = 'Unauthorized';
				break;
			case 402:
				$text = 'Payment Required';
				break;
			case 403:
				$text = 'Forbidden';
				break;
			case 404:
				$text = 'Not Found';
				break;
			case 405:
				$text = 'Method Not Allowed';
				break;
			case 406:
				$text = 'Not Acceptable';
				break;
			case 407:
				$text = 'Proxy Authentication Required';
				break;
			case 408:
				$text = 'Request Time-out';
				break;
			case 409:
				$text = 'Conflict';
				break;
			case 410:
				$text = 'Gone';
				break;
			case 411:
				$text = 'Length Required';
				break;
			case 412:
				$text = 'Precondition Failed';
				break;
			case 413:
				$text = 'Request Entity Too Large';
				break;
			case 414:
				$text = 'Request-URI Too Large';
				break;
			case 415:
				$text = 'Unsupported Media Type';
				break;
			case 416:
				$text = 'Requested Range Not Satisfiable';
				break;
			case 417:
				$text = 'Expectation Failed';
				break;
			case 422:
				$text = 'Unprocessable Entity';
				break;
			case 423:
				$text = 'Locked';
				break;
			case 424:
				$text = 'Failed Dependency';
				break;
			case 426:
				$text = 'Upgrade Required';
				break;
			case 500:
				$text = 'Internal Server Error';
				break;
			case 501:
				$text = 'Not Implemented';
				break;
			case 502:
				$text = 'Bad Gateway';
				break;
			case 503:
				$text = 'Service Unavailable';
				break;
			case 504:
				$text = 'Gateway Time-out';
				break;
			case 505:
				$text = 'HTTP Version not supported';
				break;
			case 506:
				$text = 'Variant Also Negotiates';
				break;
			case 507:
				$text = 'Insufficient Storage';
				break;
			case 509:
				$text = 'Bandwidth Limit Exceeded';
				break;
			case 510:
				$text = 'Not Extended';
				break;
			default:
				exit('Unknown http status code "' . htmlentities($code) . '"');
				break;
			}

			$protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1');
			header($protocol . ' ' . $code . ' ' . $text);
		}

		return $code;

	}
}
?>