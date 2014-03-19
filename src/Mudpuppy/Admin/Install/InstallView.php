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
	<script src="/mudpuppy/content/js/jquery-1.10.0.min.js"></script>
	<script src="/mudpuppy/content/bootstrap/js/bootstrap.min.js"></script>
	<script src="/mudpuppy/content/js/form-helpers.js"></script>
	<script src="/mudpuppy/content/js/InstallView.js"></script>
</head>
<body>
<div class="pageHeader">
	<span id="pageTitle"><?php print Config::$appTitle; ?> | Install</span>
</div>
<form id="installSettings" role="form">
	<div class="col-sm-4 col-sm-offset-2 col-lg-3 col-lg-offset-3" style="padding: 2em;">
		<legend>Application</legend>
		<div class="form-group">
			<label for="appTitle">Title</label>
			<input type="text" class="form-control" id="appTitle" name="appTitle" value="Mudpuppy Sample"/>
		</div>
		<div class="form-group">
			<label for="appClass">Class Name</label>
			<input type="text" class="form-control" id="appClass" name="appClass" value="SampleApp"/>
		</div>
		<br/>
		<legend>Database</legend>
		<div class="form-group">
			<div class="checkbox">
				<label><input id="dbEnabled" name="dbEnabled" type="checkbox" checked/> This application has a
					database</label>
			</div>
		</div>
		<div class="form-group">
			<label for="dbHost">Host</label>
			<input type="text" class="form-control dbField" id="dbHost" name="dbHost" value="localhost"/>
		</div>
		<div class="form-group">
			<label for="dbPort">Port</label>
			<input type="text" class="form-control dbField" id="dbPort" name="dbPort" value="3306"/>
		</div>
		<div class="form-group">
			<label for="dbDatabase">Database Name</label>
			<input type="text" class="form-control dbField" id="dbDatabase" name="dbDatabase" value="MudpuppySample"/>
		</div>
		<div class="form-group">
			<label for="dbUser">Username</label>
			<input type="text" class="form-control dbField" id="dbUser" name="dbUser" value="root"/>
		</div>
		<div class="form-group">
			<label for="dbPass">Password</label>
			<input type="password" class="form-control dbField" id="dbPass" name="dbPass" value=""/>
		</div>
	</div>
	<div class="col-sm-4 col-lg-3" style="padding: 2em;">
		<legend>Logging</legend>
		<div class="form-group">
			<select id="logLevel" name="logLevel" class="form-control">
				<option value="3" selected>Always write logs</option>
				<option value="2">Only write logs when an error occurs</option>
				<option value="1">Never write logs</option>
			</select>
		</div>
		<div class="form-group">
			<div class="checkbox">
				<label><input id="logQueries" name="logQueries" type="checkbox" class="dbField" checked/> Log
					queries</label>
			</div>
		</div>
		<div class="form-group">
			<div class="checkbox">
				<label><input id="logToDatabase" name="logToDatabase" type="checkbox" class="dbField" checked/> Write to
					database</label>
			</div>
		</div>
		<div class="form-group">
			<div class="checkbox">
				<label><input id="logToDir" name="logToDir" type="checkbox"/> Write to directory:</label>
			</div>
			<input type="text" class="form-control" id="logFileDir" name="logFileDir" value="" disabled/>
		</div>
		<br/>
		<legend>Admin</legend>
		<div class="form-group">
			<div class="checkbox">
				<label><input id="adminBasicAuth" name="adminBasicAuth" type="checkbox" checked/> Protect admin area with
					HTTP Basic Authentication</label>
			</div>
		</div>
		<div class="form-group">
			<label for="adminUser">Username</label>
			<input type="text" class="form-control" id="adminUser" name="adminUser" value="admin"/>
		</div>
		<div class="form-group">
			<label for="adminPass">Password</label>
			<input type="password" class="form-control" id="adminPass" name="adminPass" value=""/>
		</div>
		<br/>
		<div class="form-group">
			<button id="install" class="btn btn-primary pull-right">Install</button>
		</div>
	</div>
</form>
</body>
</html>