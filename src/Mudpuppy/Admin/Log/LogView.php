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
	<title><?php print Config::$appTitle; ?> | Debug Log</title>
	<link rel="stylesheet" href="/mudpuppy/content/bootstrap/css/bootstrap.min.css"/>
	<link rel="stylesheet" href="/mudpuppy/content/bootstrap/css/bootstrap-theme.min.css"/>
	<link rel="stylesheet" href="/mudpuppy/content/css/prettify.css"/>
	<link rel="stylesheet" href="/mudpuppy/content/css/styles.css"/>
	<script src="/mudpuppy/content/js/jquery-1.10.0.min.js"></script>
	<script src="/mudpuppy/content/js/desktop-notify-min.js"></script>
	<script src="/mudpuppy/content/bootstrap/js/bootstrap.min.js"></script>
	<script src="/mudpuppy/content/js/prettify.min.js"></script>
	<script src="/mudpuppy/content/js/pretty-langs.js"></script>
	<script src="/mudpuppy/content/js/DebugLog.js"></script>
</head>
<body>
<div class="pageHeader">
	<a href="/mudpuppy/" class="adminBackButton">Admin</a>
	<span id="pageTitle"><?php print Config::$appTitle; ?> | Debug Log</span>
	<div>
		<div class="btn-group" style="float:left">
			<button class="btn btn-default btn-sm" onclick="javascript:debugLog.clearLog()">
				<span class="glyphicon glyphicon-ban-circle"></span> Clear
			</button>
			<button class="btn dropdown-toggle btn-default btn-sm" data-toggle="dropdown">
				<span class="caret"></span>
			</button>
			<ul class="dropdown-menu">
				<li><a href="javascript:debugLog.clearLog(true);">Delete All Logs</a></li>
			</ul>
		</div>
		<a class="btn btn-default btn-sm" href="javascript:debugLog.requestNotifications()" id="allowNotifications">Allow Error Notifications</a>
		<a class="btn btn-default btn-sm" href="javascript:debugLog.pull()"><span class="glyphicon glyphicon-refresh"></span> Pull</a>
		<a id="pauseButton" class="btn btn-info btn-sm" href="javascript:debugLog.pause()"><span class="glyphicon glyphicon-pause"></span> Pause</a>
		<a id="resumeButton" class="btn btn-danger btn-sm" style="display:none;" href="javascript:debugLog.resume()"><span class="glyphicon glyphicon-play"></span> Resume</a>
	</div>
</div>
<div class="panel-group" id="debugLog">
	<div id="logList" class="panel panel-default"></div>
</div>
<div style="display:none;">
	<div id="logTemplate">
		<div class="panel-heading">
			<div class="panel-toggle" data-toggle="collapse">
				<table class="logHeader">
					<tr>
						<td style="width:35px; text-align: center;">OK</td>
						<td style="width:50px; text-align: center;">200</td>
						<td>GET</td>
						<td>1 min ago</td>
						<td>PATH/To/Request/</td>
						<td style="text-align: right;">0 queries</td>
						<td style="text-align: right;">0.0134s</td>
					</tr>
				</table>
			</div>
		</div>
		<div id="" class="panel-collapse">
			<div class="panel-body collapse">
				<div class="tabbable tabs-left">
					<ul class="nav nav-tabs">
						<li><a data-toggle="tab">Request</a></li>
						<li><a data-toggle="tab">Errors <div style="float:right">2</div></a></li>
						<li><a data-toggle="tab">Logs <div style="float:right">0</div></a></li>
						<li><a data-toggle="tab">Queries <div style="float:right">13</div></a></li>
					</ul>
					<div class="tab-content">
						<div class="tab-pane"></div>
						<div class="tab-pane"></div>
						<div class="tab-pane"></div>
						<div class="tab-pane"></div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
</body>
</html>