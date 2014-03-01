<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy\Admin\Install;

use Mudpuppy\Config;
use Mudpuppy\Controller;
use Mudpuppy\Log;
use Mudpuppy\PageController;

defined('MUDPUPPY') or die('Restricted');

class InstallController extends Controller {
	use PageController;

	public function __construct($pathOptions) {
		Log::dontWrite();
		$this->pageTitle = Config::$appTitle . ' | Install';
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
				'/mudpuppy/content/bootstrap/js/bootstrap.min.js'
			],
			'css' => [
				'/mudpuppy/content/bootstrap/css/bootstrap.min.css',
				'/mudpuppy/content/css/styles.css'
			]
		];
	}

	/**
	 * Renders the page body.
	 */
	public function render() {
		include('Mudpuppy/Admin/Install/InstallView.php');
	}

}