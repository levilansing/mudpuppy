<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================
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