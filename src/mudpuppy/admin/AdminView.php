<?php
defined('MUDPUPPY') or die('Restricted');
Log::dontWrite();
?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php print Config::$appTitle; ?> | Admin</title>
	<link href="/mudpuppy/admin/bootstrap/css/bootstrap.min.css" rel="stylesheet" media="screen"/>
	<link href="/mudpuppy/admin/css/styles.css" rel="stylesheet" media="screen"/>
	<script src="/mudpuppy/admin/js/jquery-1.10.0.min.js"></script>
	<script src="/mudpuppy/admin/bootstrap/js/bootstrap.min.js"></script>
	<script src="/mudpuppy/admin/js/AdminPage.js"></script>
	<script></script>
</head>
<body>
<div class="pageHeader">
	<span id="pageTitle"><?php print Config::$appTitle; ?> | Admin</span>
</div>
<div class="logo"></div>
<div class="adminPage">
	<div class="adminButton"></div>
	<a href="/mudpuppy/log/" class="adminButton"><span class="icon log"></span>View Debug Log</a>
	<a href="javascript:adminPage.updateDataObjects()" class="adminButton"><span class="icon database"></span>Synchronize DataObjects</a>
	<div class="adminButton"></div>
	<div class="clearfix"></div>
</div>
<div style="display: none;">
	<div id="resultsDialog" class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
				<h4 class="modal-title"></h4>
			</div>
			<div class="modal-content">
				<div class="modal-body"></div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>
</body>
</html>