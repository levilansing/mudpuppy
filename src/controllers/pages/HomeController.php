<?php
/**
 * @author Levi Lansing
 * Created 8/28/13
 */
defined('MUDPUPPY') or die('Restricted');

class HomeController extends Controller {
	use PageController;

	public function getRequiredPermissions() {
		return array();
	}

	public function getScripts() {
		return [
			'js' => [],
			'css' => ['css/style.css']
		];
	}

	public function processPost() {

	}

	public function render() {
		if (sizeof($this->options) > 0) {
			App::abort(404);
		}

		include('views/home.php');
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