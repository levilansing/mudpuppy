<?php
	defined('MUDPUPPY') or die('Restricted');

	//////////////////////////////////
	//
	//  E X A M P L E   Module
	//
	//    do not keep as is
	//
    // for API action calls use format
    // http://{domain}/api/{moduleName}/{actionName}
    // http://mudpuppy/api/login/logout => calls action_logout in LoginModule
    //
	//////////////////////////////////
	
class LoginModule extends Module {

    public function getRequiredPermissions($method, $input) {
        // TODO: add permissions as needed
        return array();
    }

    public function action_login() {
        $email = Request::get('email');
        $password = Request::get('password');
        $result = Security::login($email, $password);
        if ($result === true) {
            // login successful
            header('Location: '.App::getBaseURL().'home/');
            return null;
            return array('gamestate'=>$game->gameState);
        }

        // login failed
        return array('message'=>$result);
    }

    public function action_logout() {
        // End/delete session
        Security::logout();

        header('Location: '.App::getBaseURL().'login/');
        return null;
    }


}