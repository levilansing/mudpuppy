<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

namespace App;

use Mudpuppy\App;

defined('MUDPUPPY') or die('Restricted');

// Get the current page controller to provide data for rendering our view
/** @var Controller $controller */
$controller = App::getPageController();

?>
<h1>Mudpuppy</h1><p>This is your home page.</p>
<ul>
	<li><a href="/getJson?title=hello&message=This is the result of a REST get">Send a sample REST get</a></li>
	<li><a href="/getJson">Send a malformed REST get</a></li>
	<li><a href="/mudpuppy">View the Mudpuppy admin area</a></li>
</ul>