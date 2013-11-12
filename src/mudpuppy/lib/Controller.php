<?php
/**
 * @author Levi Lansing
 * Created 8/28/13
 */
defined('MUDPUPPY') or die('Restricted');
MPAutoLoad('Exceptions');

abstract class Controller {
    var $options = array();
    var $view;
    var $id;

    /**
     * @param string $name optional name of the controller
     * @return Controller
     */
    public static function getController($name = '') {
        $path = pathinfo($_SERVER['PATH_INFO']);
        if (isset($path['dirname']) && strlen($path['dirname']) > 1) {
            $parts = explode('/', substr($path['dirname'], 1));
        } else {
            $parts = [];
        }
        if (!isset($path['extension']) && isset($path['basename']))
            $parts[] = $path['basename'];

        $controllerName = 'HomeController';
        if ($name) {
            $controllerName = $name . 'Controller';
        } else if (count($parts) > 0 && $parts[0] != '') {
            $controllers = [];
            $controllerName = '';
            foreach ($parts as $part) {
                $controllerName .= ucfirst($part);
                $controllers[] = $controllerName . 'Controller';
            }
            $controllerName = end($controllers);
            while ($controllerName && !class_exists($controllerName)) {
                $controllerName = prev($controllers);
            }
        }
        if (!class_exists($controllerName)) {
            if (Config::$debug)
                Log::error("Controller does not exist for request: ".$_SERVER['PATH_INFO']);
            App::abort(404);
        }

        $options = $parts;
        $cName = '';
        $index = 1;
        foreach ($parts as $part) {
            $cName .= $part;
            if (strcasecmp($cName . 'Controller', $controllerName) == 0) {
                $options = array_slice($parts, $index);
                break;
            }
        }

        if ($controllerName == 'Controller')
            return null;

        return new $controllerName($options);
    }

    public function __construct($options) {
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
        if ($action && method_exists($this, 'action_' . $action)) {
            call_user_func(array($this, 'action_' . $action));
        }
    }

    /**
     * run an action on this controller
     * @param $actionName
     * @return mixed
     * @throws UnsupportedMethodException
     */
    public function runAction($actionName) {
        $action = Request::cleanValue($actionName, '', 'cmd');
        if ($action && method_exists($this, 'action_' . $action)) {
            return call_user_func(array($this, 'action_' . $action));
        }
        throw new UnsupportedMethodException('Request method ' . $_SERVER['REQUEST_METHOD'] . ' is invalid for this URL');
    }


    protected function getOption($index) {
        if (isset($this->options[$index]))
            return $this->options[$index];
        return null;
    }
}