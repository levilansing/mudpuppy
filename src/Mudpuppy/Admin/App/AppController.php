<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy\Admin\App;

use Mudpuppy\App;
use Mudpuppy\Config;
use Mudpuppy\Controller;
use Mudpuppy\File;
use Mudpuppy\InvalidInputException;
use Mudpuppy\Log;
use Mudpuppy\MudpuppyException;
use Mudpuppy\PageController;
use Mudpuppy\Request;
use Mudpuppy\Security;

defined('MUDPUPPY') or die('Restricted');

class AppController extends Controller {
	use PageController;

	public function __construct($pathOptions) {
		Log::dontWrite();
		parent::__construct($pathOptions);
	}

	/** @returns array */
	public function getRequiredPermissions() {
		return array();
	}

	/**
	 * Renders the page body.
	 */
	public function render() {
		// Abort the default template, use the app view for the entire page
		ob_clean();
		include('Mudpuppy/Admin/App/AppView.php');
		App::cleanExit();
	}

	/**
	 * List the contents of the app
	 * @param bool $refresh
	 * @return array
	 */
	public function action_listApp($refresh = false) {
		return $this->generateFileListing('App');
	}

	private function generateFileListing($directory) {
		$list = [];
		foreach (File::getFiles($directory, '#(\.php|^BasicAuth.json|^Config.json)$#') as $file) {
			$namespace = implode('\\', explode('/', $directory));
			$type = 'phpClass';
			$properties = [];
			if (strcasecmp(substr($file, -strlen('Controller.php')), 'Controller.php') == 0) {
				$type = 'controller';
				$properties = $this->getControllerProperties($namespace, File::getTitle($file, false));
				$pageController = in_array('Mudpuppy\PageController', $properties['traits']);
				$dataObjectController = in_array('Mudpuppy\DataObjectController', $properties['traits']);
				if ($pageController && !$dataObjectController) {
					$type = 'pageController';
				} else if ($dataObjectController && !$pageController) {
					$type = 'dataObjectController';
				}
			}
			if (strcasecmp(substr($file, -strlen('View.php')), 'View.php') == 0) {
				$type = 'view';
				$properties = ['namespace' => $namespace];
			}
			// special files in App namespace
			if ($namespace == 'App') {
				switch ($file) {
				case 'Security.php':
				case 'Permissions.php':
					$type = 'permissions';
					break;

				case 'BasicAuth.json':
					$type = 'basicAuth';
					$properties['realms'] = array_keys(json_decode(file_get_contents('App/BasicAuth.json'), true));
					break;

				case 'Config.json':
					$type = 'config';
					$properties['config'] = json_decode(file_get_contents('App/Config.json', true));
					break;

				case Config::$appClass . '.php':
					$type = 'app';
				}
			}
			$list[$file] = ['type' => $type, 'file' => $file, 'properties' => $properties];
		}

		$folders = [];
		foreach (File::getFolders($directory) as $folder) {
			$folders[$folder . '\\'] = $this->generateFileListing($directory . '/' . $folder);
		}

		// sort files & directories
		ksort($list);
		ksort($folders);

		return array_merge($folders, $list);
	}

	/**
	 * Use reflection to get information on the controller
	 * @param $namespace
	 * @param $className
	 * @return array
	 */
	private function getControllerProperties($namespace, $className) {
		ob_start();
		$properties = ['namespace' => $namespace, 'className' => $className, 'traits' => [], 'actions' => []];
		try {
			$reflection = new \ReflectionClass($namespace . '\\' . $className);

			// get list of traits
			$traits = $reflection->getTraitNames();
			$properties['traits'] = $traits;

			// get actions
			foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
				if (preg_match('/^action_(.+)/', $method->name, $matches)) {
					$properties['actions'][] = $matches[1];
				}
			}

			// get code for permissions and allowable patterns
			$method = $reflection->getMethod('getRequiredPermissions');
			$permStart = -1;
			$permEnd = -1;
			if ($method) {
				$permStart = $method->getStartLine();
				$permEnd = $method->getEndLine();
			}

			$method = $reflection->getMethod('getAllowablePathPatterns');
			$pathStart = -1;
			$pathEnd = -1;
			if ($method) {
				$pathStart = $method->getStartLine();
				$pathEnd = $method->getEndLine();
			}

			$perm = '';
			$path = '';
			$fp = fopen($reflection->getFileName(), 'r');
			$lineNumber = 0;
			while (!feof($fp) && $lineNumber <= $pathEnd && $lineNumber <= $permEnd) {
				$lineNumber++;
				$line = fgets($fp);

				if ($lineNumber > $permStart && $lineNumber < $permEnd) {
					$perm .= $line;
				}

				if ($lineNumber > $pathStart && $lineNumber < $pathEnd) {
					$path .= $line;
				}
			}
			fclose($fp);
			$properties['permissions'] = $perm;
			$properties['paths'] = $path;

		} catch (\Exception $exception) {
			$properties['error'] = $exception->getMessage();
		}
		ob_end_clean();
		return $properties;
	}

	/**
	 * Create a new controller or view
	 *
	 * @param string $namespace
	 * @param string $name
	 * @param string $type
	 * @param bool $isPageController
	 * @param bool $isDataObjectController
	 * @throws \Mudpuppy\MudpuppyException
	 * @throws \Mudpuppy\InvalidInputException
	 * @return array
	 */
	public function action_createFile($namespace, $name, $type, $isPageController, $isDataObjectController) {
		$name = Request::cleanValue($name, '', 'cmd');
		if (substr($namespace, -1) == '\\') {
			$namespace = substr($namespace, 0, -1);
		}

		if (empty($name)) {
			throw new InvalidInputException(null, "Object Name is invalid");
		}

		$name = ucfirst($name);

		$folders = explode('\\', $namespace);
		if (count($folders) == 0 || $folders[0] != 'App') {
			throw new InvalidInputException(null, "Namespace must begin with App");
		}

		$currentFolder = '';
		foreach ($folders as $folder) {
			if (!file_exists($currentFolder . $folder) && !mkdir($currentFolder . $folder)) {
				throw new MudpuppyException(null, 'Failed to create directory ' . $currentFolder . $folder);
			}
			$currentFolder .= $folder . '/';
		}

		if ($type == 'Controller') {
			$fileName = str_replace('.php', '', $name);
			if (substr($fileName, -10) != 'Controller') {
				$fileName .= 'Controller';
			}

			if (file_exists($currentFolder . $fileName . '.php')) {
				throw new InvalidInputException(null, "Object already exists");
			}

			$stubFile = '_';
			if ($isDataObjectController) {
				$stubFile .= 'Data';
			}
			if ($isPageController) {
				$stubFile .= 'Page';
			}
			$stubFile .= 'ControllerStub';

			$stub = file_get_contents('Mudpuppy/Admin/App/' . $stubFile);
			if (!$stub) {
				throw new MudpuppyException(null, 'Controller stub is missing: ' . $stubFile);
			}

			// populate stub file
			$stub = str_replace('___NAMESPACE___', $namespace, $stub);
			$stub = str_replace('___CLASS_NAME___', $fileName, $stub);
			$stub = str_replace('___VIEW_FILE_PATH___', implode('/', explode('\\', $namespace)) . '/' . substr($fileName, 0, -10) . 'View.php', $stub);

			if (file_put_contents($currentFolder . $fileName . '.php', $stub) === false) {
				throw new MudpuppyException(null, 'Unable to create file. May not have appropriate file permissions.');
			}

			// if it's not a page controller, we're done
			if (!$isPageController) {
				return [];
			}

			// update $name so we can create a view for the page controller
			$name = substr($fileName, 0, -10) . 'View';
		}

		// create a view

		$fileName = str_replace('.php', '', $name);
		if (substr($fileName, -4) != 'View') {
			$fileName .= 'View';
		}

		if (file_exists($currentFolder . $fileName . '.php')) {
			throw new InvalidInputException(null, "Object already exists");
		}

		$stubFile = 'Mudpuppy/Admin/App/_ViewStub';
		$stub = file_get_contents($stubFile);
		if (!$stub) {
			throw new MudpuppyException(null, 'View stub is missing: ' . $stubFile);
		}

		// populate stub file

		if (file_put_contents($currentFolder . $fileName . '.php', $stub) === false) {
			throw new MudpuppyException(null, 'Unable to create file. May not have appropriate file permissions.');
		}

		return [];
	}

	/**
	 * create a new folder in the app namespace
	 * @param string $name
	 * @throws \Mudpuppy\MudpuppyException
	 * @throws \Mudpuppy\InvalidInputException
	 * @return array
	 */
	public function action_createFolder($name) {
		$name = File::cleanPath($name);

		$folders = preg_split('#[\\\\/]#', $name);
		if (empty($folders) || $folders[0] != 'App') {
			throw new InvalidInputException(null, "Folder name is invalid. It must start with 'App\\': " . $name);
		}

		$currentFolder = '';
		foreach ($folders as $folder) {
			if (!file_exists($currentFolder . $folder) && !mkdir($currentFolder . $folder)) {
				throw new MudpuppyException(null, 'Failed to create directory ' . $currentFolder . $folder);
			}
			$currentFolder .= $folder . '/';
		}

		return [];
	}

	/**
	 * @param string $name
	 * @return array
	 * @throws \Mudpuppy\InvalidInputException
	 */
	public function action_getBasicAuthRealm($name) {
		$realms = json_decode(file_get_contents('App/BasicAuth.json'), true);
		$name = trim($name);
		if (!isset($realms[$name])) {
			throw new InvalidInputException(null, "Unknown realm \"$name\"");
		}
		return [
			'name' => $name,
			'pathPattern' => $realms[$name]['pathPattern'],
			'credentials' => array_keys($realms[$name]['credentials'])
		];
	}

	/**
	 * @param string $oldName
	 * @param string $newName
	 * @param string $pathPattern
	 * @return array
	 * @throws \Mudpuppy\InvalidInputException
	 */
	public function action_saveBasicAuthRealm($oldName, $newName, $pathPattern) {
		$realms = json_decode(file_get_contents('App/BasicAuth.json'), true);
		$oldName = trim($oldName);
		$newName = trim($newName);
		$pathPattern = trim($pathPattern);
		if (empty($newName)) {
			throw new InvalidInputException(null, 'Must specify Realm Name');
		}
		if (empty($pathPattern)) {
			throw new InvalidInputException(null, 'Must specify Path Pattern');
		}
		if (empty($oldName)) {
			$realms[$newName] = ['pathPattern' => $pathPattern, 'credentials' => []];
		} else if (isset($realms[$oldName])) {
			if ($newName != $oldName) {
				$realms[$newName] = $realms[$oldName];
				unset($realms[$oldName]);
			}
			$realms[$newName]['pathPattern'] = $pathPattern;
		} else {
			throw new InvalidInputException(null, "Unknown realm \"$oldName\"");
		}
		file_put_contents('App/BasicAuth.json', json_encode($realms));
		return ['realms' => array_keys($realms)];
	}

	/**
	 * @param string $name
	 * @return array
	 * @throws \Mudpuppy\InvalidInputException
	 */
	public function action_deleteBasicAuthRealm($name) {
		$realms = json_decode(file_get_contents('App/BasicAuth.json'), true);
		$name = trim($name);
		if (!isset($realms[$name])) {
			throw new InvalidInputException(null, "Unknown realm \"$name\"");
		}
		unset($realms[$name]);
		file_put_contents('App/BasicAuth.json', json_encode($realms));
		return ['realms' => array_keys($realms)];
	}

	/**
	 * @param string $realmName
	 * @param string $oldUsername
	 * @param string $newUsername
	 * @param string $password
	 * @return array
	 * @throws \Mudpuppy\InvalidInputException
	 */
	public function action_saveBasicAuthCredential($realmName, $oldUsername, $newUsername, $password) {
		$realms = json_decode(file_get_contents('App/BasicAuth.json'), true);
		$realmName = trim($realmName);
		$oldUsername = trim($oldUsername);
		$newUsername = trim($newUsername);
		$password = trim($password);
		if (!isset($realms[$realmName])) {
			throw new InvalidInputException(null, "Unknown realm \"$realmName\"");
		}
		if (empty($newUsername)) {
			throw new InvalidInputException(null, 'Must specify Username');
		}
		if (empty($password)) {
			throw new InvalidInputException(null, 'Must specify Password');
		}
		if (empty($oldUsername)) {
			$realms[$realmName]['credentials'][$newUsername] = Security::hashPassword($password);
		} else if (isset($realms[$realmName]['credentials'][$oldUsername])) {
			unset($realms[$realmName]['credentials'][$oldUsername]);
			$realms[$realmName]['credentials'][$newUsername] = Security::hashPassword($password);
		} else {
			throw new InvalidInputException(null, "Unknown credential \"$oldUsername\"");
		}
		file_put_contents('App/BasicAuth.json', json_encode($realms));
		return ['credentials' => array_keys($realms[$realmName]['credentials'])];
	}

	/**
	 * @param string $realmName
	 * @param string $username
	 * @return array
	 * @throws \Mudpuppy\InvalidInputException
	 */
	public function action_deleteBasicAuthCredential($realmName, $username) {
		$realms = json_decode(file_get_contents('App/BasicAuth.json'), true);
		$realmName = trim($realmName);
		$username = trim($username);
		if (!isset($realms[$realmName])) {
			throw new InvalidInputException(null, "Unknown realm \"$realmName\"");
		}
		unset($realms[$realmName]['credentials'][$username]);
		file_put_contents('App/BasicAuth.json', json_encode($realms));
		return ['credentials' => array_keys($realms[$realmName]['credentials'])];
	}

}