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
	<title><?php print Config::$appTitle; ?> | Admin</title>
	<link rel="stylesheet" href="/mudpuppy/content/bootstrap/css/bootstrap.min.css"/>
	<link rel="stylesheet" href="/mudpuppy/content/bootstrap/css/bootstrap-theme.min.css"/>
	<link rel="stylesheet" href="/mudpuppy/content/css/styles.css"/>
	<script src="/mudpuppy/content/js/jquery-1.10.0.min.js"></script>
	<script src="/mudpuppy/content/bootstrap/js/bootstrap.min.js"></script>
	<script src="/mudpuppy/content/js/AdminPage.js"></script>
</head>
<body>
<div class="pageHeader">
	<span id="pageTitle"><?php print Config::$appTitle; ?> | Admin</span>
</div>
<div class="logo"></div>
<div class="adminPage">
	<div class="adminButton"></div>
	<a href="/mudpuppy/Log/" class="adminButton"><span class="icon log"></span>View Debug Log</a>
	<div class="adminButton"></div>
	<div class="adminButton"></div>
	<a href="/mudpuppy/App/" class="adminButton"><span class="icon structure"></span>Manage App Structure</a>
	<div class="adminButton"></div>
	<div class="adminButton"></div>
	<?php if (!empty(Config::$dbHost)) { ?>
	<a href="javascript:adminPage.updateDataObjects()" class="adminButton"><span class="icon database"></span>Synchronize DataObjects</a>
	<?php } else { ?>
	<div class="adminButton disabled"><span class="icon database"></span>Synchronize DataObjects</div>
	<?php } ?>
	<div class="clearfix"></div>
</div>
<div style="display: none;">
	<div id="resultsDialog" class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
					<h4 class="modal-title"></h4>
				</div>
				<div class="modal-body"></div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
	</div>
</div>
</body>
</html>