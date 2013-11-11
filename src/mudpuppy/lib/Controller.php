<?php
/**
 * @author Levi Lansing
 * Created 8/28/13
 */
defined('MUDPUPPY') or die('Restricted');

abstract class Controller {
    var $options = array();
    var $view;
    var $id;

    /**
     * @param string $name optional name of the controller
     * @return Controller
     */
    public static function getController($name='') {
        if ($name) {
            $controllerName = $name . 'Controller';
        } else {
            $controllerName = 'HomeController';
            $parts = pathinfo($_SERVER['PATH_INFO']);
            if (isset($parts['dirname']) && strlen($parts['dirname'])>1) {
                $path = explode('/', substr($parts['dirname'], 1));
                $controllerName = '';
                $controllers = [];
                foreach ($path as $part) {
                    $controllerName .= ucfirst($part);
                    $controllers[] = $controllerName.'Controller';
                }
                $controllerName = end($controllers);
                while ($controllerName && !class_exists($controllerName)) {
                    $controllerName = prev($controllers);
                }
            }
        }
        if (!class_exists($controllerName)) {
            App::abort(404);
        }
        return new $controllerName();
    }

    public function __construct() {
        $this->options = explode('/', Request::get('options', ''));
        if (is_array($this->options)) {
            $this->view = reset($this->options);
            $this->id = (int)next($this->options);
        }
    }

    /** @returns array */
    abstract public function getRequiredPermissions();

    public function processPost() {
        $action = Request::getCmd('action', null, 'POST');
        if ($action && method_exists($this, 'action_'.$action)) {
            call_user_func(array($this, 'action_'.$action));
        }
    }

    /**
     * run an action on this controller
     * @param $actionName
     */
    public function runAction($actionName) {
        $action = Request::cleanValue($actionName, '', 'cmd');
        if ($action && method_exists($this, 'action_'.$action)) {
            return call_user_func(array($this, 'action_'.$action));
        }
        return null;
    }

    /**
     * render the requested view
     */
//    abstract public function render();

    protected function getOption($index) {
        if (isset($this->options[$index]))
            return $this->options[$index];
        return null;
    }
}