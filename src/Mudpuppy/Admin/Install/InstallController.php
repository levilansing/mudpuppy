<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace Mudpuppy\Admin\Install;

use Mudpuppy\App;
use Mudpuppy\Controller;
use Mudpuppy\Log;
use Mudpuppy\PageController;

defined('MUDPUPPY') or die('Restricted');

class InstallController extends Controller {
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
		// Abort the default template, use the install view for the entire page
		ob_clean();
		include('Mudpuppy/Admin/Install/InstallView.php');
		App::cleanExit();
	}

}