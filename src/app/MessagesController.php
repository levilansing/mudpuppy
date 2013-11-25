<?php
namespace app;
defined('MUDPUPPY') or die('Restricted');

class MessagesController extends \Controller {

	public function getRequiredPermissions() {
		return array();
	}

	public function action_check() {
		return App::readMessages();
	}

}