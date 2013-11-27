<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================
namespace app;
defined('MUDPUPPY') or die('Restricted');

class HomeController extends \Controller {
	use \PageController;

	public function getRequiredPermissions() {
		return array();
	}

	public function getScripts() {
		return [
			'js' => [],
			'css' => ['css/style.css']
		];
	}

	public function render() {
		if (sizeof($this->pathOptions) > 0) {
			throw new \PageNotFoundException();
		}

		include('app/HomeView.php');
	}

	/**
	 * @param string $title
	 * @param string $message
	 * @return array
	 */
	public function action_getJson($title, $message = '1') {
		return array('title' => $title, 'message' => $message);
	}

}