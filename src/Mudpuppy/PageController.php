<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy;

defined('MUDPUPPY') or die('Restricted');

/**
 * Class PageController
 * @package Mudpuppy
 * @property array pathOptions
 */
trait PageController {
	/** @var string optional page title override */
	protected $pageTitle;

	/**
	 * Renders the page header. The default implementation adds the page title and imports any js and css files
	 * specified by getScripts().
	 */
	public function renderHeader() {
		$title = $this->pageTitle ? $this->pageTitle : Config::$appTitle;
		print "<title>$title</title>\n";
		$scripts = $this->getScripts();
		if (isset($scripts['css'])) {
			foreach ($scripts['js'] as $script) {
				echo "\t<script type=\"text/javascript\" src=\"$script\"></script>\n";
			}
		}
		if (isset($scripts['css'])) {
			foreach ($scripts['css'] as $css) {
				echo "\t<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\" />\n";
			}
		}
	}


	/**
	 * return a list of regular expressions or strings that the page options must match
	 * example: a url of "this-controller/get/42" can be validated by array('#^get/[0-9]+$#');
	 * @return array
	 */
	public function getAllowablePathPatterns() {
		return array();
	}

	/**
	 * validate the path options for this controller
	 * specifically applies to page controllers
	 * @return bool
	 */
	public function validatePathOptions() {
		$options = implode('/', $this->pathOptions);
		$patterns = $this->getAllowablePathPatterns();

		if (empty($options)) {
			return true;
		}

		if (empty($patterns)) {
			return false;
		}

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $options)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Renders the page body.
	 */
	abstract public function render();

	/**
	 * @return array associative array with two keys, 'js' and 'css', each being an array of script paths for use by the
	 * default implementation of renderHeader()
	 */
	protected function getScripts() {
		return [
			'js' => [],
			'css' => []
		];
	}

}

?>