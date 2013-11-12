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
            'css' => []
        ];
    }

    public function processPost() {

    }

    public function render() {
        include('views/home.php');
    }
}