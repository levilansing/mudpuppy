<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy\Admin\App;

use Mudpuppy\Controller, Mudpuppy\PageController;
use App\Config;
use Mudpuppy\File;
use Mudpuppy\InvalidInputException;
use Mudpuppy\Log;
use Mudpuppy\MudpuppyException;
use Mudpuppy\Request;

defined('MUDPUPPY') or die('Restricted');

class AppController extends Controller {
	use PageController;

	public function __construct($pathOptions) {
		Log::dontWrite();
		$this->pageTitle = Config::$appTitle . ' | App Structure';
		parent::__construct($pathOptions);
	}

	/** @returns array */
	public function getRequiredPermissions() {
		return array();
	}

	public function getScripts() {
		return [
			'js' => [
				'/mudpuppy/content/js/jquery-1.10.0.min.js',
				'/mudpuppy/content/bootstrap/js/bootstrap.min.js',
				'/mudpuppy/content/js/Observer.js',
				'/mudpuppy/content/js/AppView.js'
			],
			'css' => [
				'/mudpuppy/content/bootstrap/css/bootstrap.min.css',
				'/mudpuppy/content/css/styles.css',
				'/mudpuppy/content/css/app.css'
			]
		];
	}

	/**
	 * Renders the page body.
	 */
	public function render() {
		include('mudpuppy/Admin/app/AppView.php');
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
		foreach (File::getFiles($directory, '#\.php$#') as $file) {
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

				case 'Config.php':
					$type = 'config';
					break;

				case Config::$appClass . '.php':
					$type = 'app';
				}
			}
			$list[$file] = ['type' => $type, 'file' => $file, 'properties' => $properties];
		}

		$folders = [];
		foreach (File::getFolders($directory) as $folder) {
			$folders[$folder . '/'] = $this->generateFileListing($directory . '/' . $folder);
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
				throw new MudpuppyException(null, 500, 'Failed to create directory ' . $currentFolder . $folder);
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

			$stub = file_get_contents('Mudpuppy/Admin/app/' . $stubFile);
			if (!$stub) {
				throw new MudpuppyException(null, 500, 'Controller stub is missing: ' . $stubFile);
			}

			// populate stub file
			$stub = str_replace('___NAMESPACE___', $namespace, $stub);
			$stub = str_replace('___CLASS_NAME___', $fileName, $stub);
			$stub = str_replace('___VIEW_FILE_PATH___', implode('/', explode('\\', $namespace)) . '/'. substr($fileName, 0, -10) . 'View.php', $stub);

			if (file_put_contents($currentFolder . $fileName . '.php', $stub) === false) {
				throw new MudpuppyException(null, 500, 'Unable to create file. May not have appropriate file permissions.');
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

		$stubFile = 'Mudpuppy/Admin/app/_ViewStub';
		$stub = file_get_contents($stubFile);
		if (!$stub) {
			throw new MudpuppyException(null, 500, 'View stub is missing: ' . $stubFile);
		}

		// populate stub file

		if (file_put_contents($currentFolder . $fileName . '.php', $stub) === false) {
			throw new MudpuppyException(null, 500, 'Unable to create file. May not have appropriate file permissions.');
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

		$folders = explode('/', $name);
		if (empty($folders) || $folders[0] != 'App') {
			throw new InvalidInputException(null, "Folder name is invalid. It must start with 'App/': ".$name);
		}

		$currentFolder = '';
		foreach ($folders as $folder) {
			if (!file_exists($currentFolder . $folder) && !mkdir($currentFolder . $folder)) {
				throw new MudpuppyException(null, 500, 'Failed to create directory ' . $currentFolder . $folder);
			}
			$currentFolder .= $folder . '/';
		}

		return [];
	}
}