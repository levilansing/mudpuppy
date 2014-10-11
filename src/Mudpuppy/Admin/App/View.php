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
	<title><?php print Config::$appTitle; ?> | App Structure</title>
	<link rel="stylesheet" href="/mudpuppy/content/bootstrap/css/bootstrap.min.css"/>
	<link rel="stylesheet" href="/mudpuppy/content/bootstrap/css/bootstrap-theme.min.css"/>
	<link rel="stylesheet" href="/mudpuppy/content/css/styles.css"/>
	<link rel="stylesheet" href="/mudpuppy/content/css/app.css"/>
	<script src="/mudpuppy/content/js/jquery-1.10.0.min.js"></script>
	<script src="/mudpuppy/content/bootstrap/js/bootstrap.min.js"></script>
	<script src="/mudpuppy/content/js/observer.js"></script>
	<script src="/mudpuppy/content/js/AppView.js"></script>
</head>
<body>
<div class="pageHeader">
	<a href="/mudpuppy/" class="adminBackButton">Admin</a> <span id="pageTitle"><?php print Config::$appTitle; ?> | App Structure</span>
</div>
<br/>
<div class="container-fluid" style="margin: auto; max-width: 1000px">
	<div class="row appListHeader">
		<div class="col-sm-3">
			<span class="title">Files</span>
			<a id="newController" title="Create a new controller from a template"><span class="fileIcon fileAdd white"></span></a>
			<!--
			The ability to create new folders separately from creating controllers seems irrelevant now that there can
			only be a single controller per namespace. Commented out for now in case we need it back for some reason
			in the future. Can't think of any reason why at this point, but just in case.
			-->
			<!--<a id="newNamespace" title="Create a new namespace"><span class="fileIcon folderAdd white"></span></a>-->
		</div>
		<div class="col-sm-9"><span class="title">Details</span></div>
	</div>
	<div class="row">
		<ul id="structureList" class="col-sm-3 noSelect"></ul>
		<div class="col-sm-9" id="details"></div>
	</div>
</div>

<div style="display: none;">
	<div id="dialog" class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-hidden="true">
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

	<div id="newNamespaceDialog">
		<form class="form-horizontal">
			<div class="form-group">
				<label for="folderName" class="col-sm-3 control-label">Namespace</label>
				<div class="col-sm-9">
					<input type="text" class="form-control" id="namespace" value="" placeholder="App\">
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-3"></div>
				<div class="col-sm-9">
					<button id="createNewNamespace" class="btn btn-primary">Create</button>
				</div>
			</div>
		</form>
	</div>

	<div id="newControllerDialog">
		<form class="form-horizontal">
			<div class="form-group">
				<label class="col-sm-3 control-label">Namespace</label>
				<div class="col-sm-9">
					<input type="text" class="form-control" id="controllerNamespace" value="">
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">Traits</label>
				<div class="col-sm-9">
					<div id="ControllerOptions">
						<div class="checkbox">
							<label for="objectPageController">
								<input type="checkbox" id="isPageController"/>
								Page Controller (with associated View)
							</label>
						</div>
						<div class="checkbox">
							<label for="objectDataObjectController">
								<input type="checkbox" id="isDataObjectController"/>
								Data Object Controller
							</label>
						</div>
					</div>
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-3"></div>
				<div class="col-sm-9">
					<button id="createNewController" class="btn btn-primary">Create</button>
				</div>
			</div>
		</form>
	</div>

	<div id="controllerTemplate" class="controllerDetails">
		<h2 id="controllerName"></h2>
		<div class="form-horizontal">
			<div class="form-group">
				<label class="col-sm-3 control-label">File</label>
				<div class="col-sm-9">
					<p class="form-control-static" id="controllerFile"></p>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">Traits</label>
				<div class="col-sm-9">
					<p class="form-control-static" id="controllerTraits"></p>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">Actions</label>
				<div class="col-sm-9">
					<p class="form-control-static" id="controllerActions"></p>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">Permissions</label>
				<div class="col-sm-9">
					<pre class="form-control-static" id="controllerPermissions">&nbsp;</pre>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">Allowable Paths</label>
				<div class="col-sm-9">
					<pre class="form-control-static" id="controllerPaths">&nbsp;</pre>
				</div>
			</div>
		</div>
	</div>

	<div id="basicAuthTemplate" class="basicAuthDetails">
		<div class="form-horizontal">
			<legend>Authorization Realms</legend>
			<div class="form-group">
				<div class="col-sm-3"></div>
				<div class="col-sm-9">
					<select class="form-control" id="realms">
						<option value="" selected>--- Create New ---</option>
					</select>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">Realm Name</label>
				<div class="col-sm-9">
					<input class="form-control" id="realmName" type="text"/>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">Path Pattern</label>
				<div class="col-sm-9">
					<input class="form-control" id="pathPattern" type="text"/>
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-3"></div>
				<div class="col-sm-9">
					<div class="pull-right">
						<button id="saveRealm" class="btn btn-primary">Save</button>
						<button id="deleteRealm" class="btn btn-default">Delete</button>
					</div>
				</div>
			</div>
			<legend>Associated Credentials</legend>
			<div class="form-group">
				<div class="col-sm-3"></div>
				<div class="col-sm-9">
					<select class="form-control" id="credentials" disabled>
						<option value="" selected>--- Create New ---</option>
					</select>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">Username</label>
				<div class="col-sm-9">
					<input class="form-control" id="username" type="text"/>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">Password</label>
				<div class="col-sm-9">
					<input class="form-control" id="password" type="password"/>
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-3"></div>
				<div class="col-sm-9">
					<button id="saveCredential" class="btn btn-primary">Save</button>
					<button id="deleteCredential" class="btn btn-default">Delete</button>
				</div>
			</div>
		</div>
	</div>

	<div id="configTemplate" class="configDetails">
		<div class="form-horizontal">
			<legend>Operational Environment</legend>
			<div class="form-group">
				<div class="col-sm-3"></div>
				<div class="col-sm-9">
					<select class="form-control" id="environment">
						<option value="base" selected>--- Base Config ---</option>
						<option value="">--- Create New ---</option>
					</select>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">Control Variable</label>
				<div class="col-sm-9">
					<input class="form-control" id="controlVar" type="text"/>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">Control Value</label>
				<div class="col-sm-9">
					<input class="form-control" id="controlVal" type="text"/>
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-3"></div>
				<div class="col-sm-9">
					<div class="pull-right">
						<button id="saveEnv" class="btn btn-primary">Save</button>
						<button id="deleteEnv" class="btn btn-default">Delete</button>
					</div>
				</div>
			</div>
			<legend>Environment-Specific Configuration</legend>
		</div>
		<ul class="nav nav-tabs">
			<li class="active"><a href="#configSite" data-toggle="tab">Site Options</a></li>
			<li><a href="#configDatabase" data-toggle="tab">Database Setup</a></li>
			<li><a href="#configLogging" data-toggle="tab">Debug &amp; Logging</a></li>
			<li><a href="#configCustom" data-toggle="tab">Custom Properties</a></li>
		</ul>
		<div class="tab-content">
			<div class="tab-pane active" id="configSite">
				<div class="form-horizontal">
					<div class="form-group">
						<label class="col-sm-3 control-label">Title</label>
						<div class="col-sm-3">
							<select class="form-control typeSelect" id="appTitleType">
								<option value="default" selected>Inherit</option>
								<option value="constant">Constant</option>
								<option value="variable">Variable</option>
							</select>
						</div>
						<div class="col-sm-6">
							<input class="form-control" id="appTitleValue" type="text"/>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label">Time Zone</label>
						<div class="col-sm-3">
							<select class="form-control typeSelect" id="timezoneType">
								<option value="default" selected>Inherit</option>
								<option value="constant">Constant</option>
								<option value="variable">Variable</option>
							</select>
						</div>
						<div class="col-sm-6">
							<select id="timezoneConstant" class="form-control"></select>
							<input class="form-control" id="timezoneVariable" type="text"/>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label">Date Time Format</label>
						<div class="col-sm-3">
							<select class="form-control typeSelect" id="dateTimeFormatType">
								<option value="default" selected>Inherit</option>
								<option value="constant">Constant</option>
								<option value="variable">Variable</option>
							</select>
						</div>
						<div class="col-sm-6">
							<input class="form-control" id="dateTimeFormatValue" type="text"/>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label">Date Only Format</label>
						<div class="col-sm-3">
							<select class="form-control typeSelect" id="dateOnlyFormatType">
								<option value="default" selected>Inherit</option>
								<option value="constant">Constant</option>
								<option value="variable">Variable</option>
							</select>
						</div>
						<div class="col-sm-6">
							<input class="form-control" id="dateOnlyFormatValue" type="text"/>
						</div>
					</div>
				</div>
			</div>
			<div class="tab-pane" id="configDatabase">
				<div class="form-horizontal">
					<div class="form-group">
						<label class="col-sm-3 control-label">Host</label>
						<div class="col-sm-3">
							<select class="form-control typeSelect" id="dbHostType">
								<option value="default" selected>Inherit</option>
								<option value="constant">Constant</option>
								<option value="variable">Variable</option>
							</select>
						</div>
						<div class="col-sm-6">
							<input class="form-control" id="dbHostValue" type="text"/>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label">Port</label>
						<div class="col-sm-3">
							<select class="form-control typeSelect" id="dbPortType">
								<option value="default" selected>Inherit</option>
								<option value="constant">Constant</option>
								<option value="variable">Variable</option>
							</select>
						</div>
						<div class="col-sm-6">
							<input class="form-control" id="dbPortValue" type="text"/>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label">Database Name</label>
						<div class="col-sm-3">
							<select class="form-control typeSelect" id="dbDatabaseType">
								<option value="default" selected>Inherit</option>
								<option value="constant">Constant</option>
								<option value="variable">Variable</option>
							</select>
						</div>
						<div class="col-sm-6">
							<input class="form-control" id="dbDatabaseValue" type="text"/>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label">Username</label>
						<div class="col-sm-3">
							<select class="form-control typeSelect" id="dbUserType">
								<option value="default" selected>Inherit</option>
								<option value="constant">Constant</option>
								<option value="variable">Variable</option>
							</select>
						</div>
						<div class="col-sm-6">
							<input class="form-control" id="dbUserValue" type="text"/>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label">Password</label>
						<div class="col-sm-3">
							<select class="form-control typeSelect" id="dbPassType">
								<option value="default" selected>Inherit</option>
								<option value="constant">Constant</option>
								<option value="variable">Variable</option>
							</select>
						</div>
						<div class="col-sm-6">
							<input class="form-control" id="dbPassConstant" type="password"/>
							<input class="form-control" id="dbPassVariable" type="text"/>
						</div>
					</div>
				</div>
			</div>
			<div class="tab-pane" id="configLogging">
				<div class="form-horizontal">
					<div class="form-group">
						<label class="col-sm-3 control-label">Debug Mode</label>
						<div class="col-sm-3">
							<select class="form-control typeSelect" id="debugType">
								<option value="default" selected>Inherit</option>
								<option value="constant">Constant</option>
								<option value="variable">Variable</option>
							</select>
						</div>
						<div class="col-sm-6">
							<label class="checkbox-inline">
								<input id="debugConstant" type="checkbox"/>
							</label>
							<input class="form-control" id="debugVariable" type="text"/>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label">Log Level</label>
						<div class="col-sm-3">
							<select class="form-control typeSelect" id="logLevelType">
								<option value="default" selected>Inherit</option>
								<option value="constant">Constant</option>
								<option value="variable">Variable</option>
							</select>
						</div>
						<div class="col-sm-6">
							<select id="logLevelConstant" class="form-control">
								<option value="3" selected>Always write logs</option>
								<option value="2">Only write logs when an error occurs</option>
								<option value="1">Never write logs</option>
							</select>
							<input class="form-control" id="logLevelVariable" type="text"/>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label">Log Queries</label>
						<div class="col-sm-3">
							<select class="form-control typeSelect" id="logQueriesType">
								<option value="default" selected>Inherit</option>
								<option value="constant">Constant</option>
								<option value="variable">Variable</option>
							</select>
						</div>
						<div class="col-sm-6">
							<label class="checkbox-inline">
								<input id="logQueriesConstant" type="checkbox"/>
							</label>
							<input class="form-control" id="logQueriesVariable" type="text"/>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label">Log to Database</label>
						<div class="col-sm-3">
							<select class="form-control typeSelect" id="logToDatabaseType">
								<option value="default" selected>Inherit</option>
								<option value="constant">Constant</option>
								<option value="variable">Variable</option>
							</select>
						</div>
						<div class="col-sm-6">
							<label class="checkbox-inline">
								<input id="logToDatabaseConstant" type="checkbox"/>
							</label>
							<input class="form-control" id="logToDatabaseVariable" type="text"/>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label">Log File Directory</label>
						<div class="col-sm-3">
							<select class="form-control typeSelect" id="logFileDirType">
								<option value="default" selected>Inherit</option>
								<option value="constant">Constant</option>
								<option value="variable">Variable</option>
							</select>
						</div>
						<div class="col-sm-6">
							<input class="form-control" id="logFileDirValue" type="text"/>
						</div>
					</div>
				</div>
			</div>
			<div class="tab-pane" id="configCustom">
				<div class="form-horizontal">
					<div class="form-group">
						<div class="col-sm-3"></div>
						<div class="col-sm-9">
							<select class="form-control" id="customProp">
								<option value="">--- Create New ---</option>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label">Property Key</label>
						<div class="col-sm-9">
							<input class="form-control" id="propKey" type="text"/>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label">Property Value</label>
						<div class="col-sm-3">
							<select class="form-control typeSelect" id="propValType">
								<option value="constant" selected>Constant</option>
								<option value="variable">Variable</option>
							</select>
						</div>
						<div class="col-sm-6">
							<input class="form-control" id="propValValue" type="text"/>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-3"></div>
						<div class="col-sm-9">
							<div class="pull-right">
								<button id="saveProp" class="btn btn-primary">Save</button>
								<button id="deleteProp" class="btn btn-default">Delete</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

</body>
</html>