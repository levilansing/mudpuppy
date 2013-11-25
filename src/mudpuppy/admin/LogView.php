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
	<title><?php print Config::$appTitle; ?> | Debug Log</title>
	<link href="/mudpuppy/admin/bootstrap/css/bootstrap.min.css" rel="stylesheet" media="screen"/>
	<link href="/mudpuppy/admin/bootstrap/css/prettify.css" rel="stylesheet" media="screen"/>
	<link href="/mudpuppy/admin/css/styles.css" rel="stylesheet" media="screen"/>
	<script src="/mudpuppy/admin/js/jquery-1.10.0.min.js"></script>
	<script src="/mudpuppy/admin/js/desktop-notify-min.js"></script>
	<script src="/mudpuppy/admin/bootstrap/js/bootstrap.min.js"></script>
	<script src="/mudpuppy/admin/bootstrap/js/prettify.min.js"></script>
	<script src="/mudpuppy/admin/bootstrap/js/pretty-langs.js"></script>
	<script src="/mudpuppy/admin/js/DebugLog.js"></script>
</head>
<body>
<div class="pageHeader">
	<span id="pageTitle"><?php print Config::$appTitle; ?> | Debug Log</span>
	<div>
		<div class="btn-group" style="float:left">
			<button class="btn btn-inverse btn-small" onclick="javascript:debugLog.clearLog()">
				<i class="icon-ban-circle icon-white"></i> Clear
			</button>
			<button class="btn dropdown-toggle btn-inverse btn-small" data-toggle="dropdown">
				<span class="caret"></span>
			</button>
			<ul class="dropdown-menu">
				<li><a href="javascript:debugLog.clearLog(true);">Delete All Logs</a></li>
			</ul>
		</div>
		<a class="btn btn-small" href="javascript:debugLog.requestNotifications()" id="allowNotifications">Allow Error
			Notifications</a> <a class="btn btn-small" href="javascript:debugLog.pull()"><i class="icon-refresh"></i> Pull</a>
		<a id="pauseButton" class="btn btn-info btn-small" href="javascript:debugLog.pause()"><i class="icon-pause icon-white"></i>
			Pause</a>
		<a id="resumeButton" class="btn btn-warning btn-small" style="display:none;" href="javascript:debugLog.resume()"><i class="icon-play icon-white"></i>
			Resume</a>
	</div>
</div>
<div class="accordion" id="debugLog">
	<div id="logList" class="accordion-group"></div>
</div>
<div style="display:none;">
	<div id="logTemplate">
		<div class="accordion-heading">
			<div class="accordion-toggle" data-toggle="collapse">
				<table class="logHeader">
					<tr>
						<td></td>
						<td>GET</td>
						<td>200</td>
						<td>1 min ago</td>
						<td>PATH/To/Request/</td>
						<td>0 queries</td>
						<td>0.0134s</td>
					</tr>
				</table>
			</div>
			<div id="" class="accordion-body collapse">
				<div class="accordion-inner">
					<div class="tabbable tabs-left">
						<div class="background"></div>
						<ul class="nav nav-tabs">
							<li><a data-toggle="tab">Request</a></li>
							<li><a data-toggle="tab">Errors <span style="float:right">2</span></a></li>
							<li><a data-toggle="tab">Logs <span style="float:right">0</span></a></li>
							<li><a data-toggle="tab">Queries <span style="float:right">13</span></a></li>
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
</div>
</body>
</html>