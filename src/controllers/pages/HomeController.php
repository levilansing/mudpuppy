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

    public function action_getJson() {
        $title = Request::get('title');
        $message = Request::get('message', '');
        if (!$title)
            throw new InvalidInputException('Title field is missing');

        return array('title'=>$title, 'message'=>$message);
    }
}