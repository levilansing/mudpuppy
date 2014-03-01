<?php
//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================
defined('MUDPUPPY') or die('Restricted');
use Mudpuppy\Config;
?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php print Config::$appTitle; ?> | Install</title>
	<link rel="stylesheet" href="/mudpuppy/content/bootstrap/css/bootstrap.min.css"/>
	<link rel="stylesheet" href="/mudpuppy/content/bootstrap/css/bootstrap-theme.min.css"/>
	<link rel="stylesheet" href="/mudpuppy/content/css/styles.css"/>
	<link rel="stylesheet" href="/mudpuppy/content/css/install.css"/>
	<script src="/mudpuppy/content/js/jquery-1.10.0.min.js"></script>
	<script src="/mudpuppy/content/bootstrap/js/bootstrap.min.js"></script>
	<script src="/mudpuppy/content/js/InstallView.js"></script>
</head>
<body>
<div class="pageHeader">
	<span id="pageTitle"><?php print Config::$appTitle; ?> | Install</span>
</div>
</body>
</html>