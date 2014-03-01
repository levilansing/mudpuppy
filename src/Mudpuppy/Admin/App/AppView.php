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
	<script src="/mudpuppy/content/js/Observer.js"></script>
	<script src="/mudpuppy/content/js/AppView.js"></script>
</head>
<body>
<div class="pageHeader">
	<a href="/mudpuppy/" class="adminBackButton">Admin</a> <span id="pageTitle"><?php print Config::$appTitle; ?> | App Structure</span>
</div>
<br/>
<div class="container-fluid" style="margin: auto; max-width: 1000px">
	<div class="row appListHeader">
		<div class="col-sm-4">
			<span class="title">Files</span>
			<a id="newFile" title="Create a new file from a template"><span class="fileIcon fileAdd white"></span></a>
			<a id="newFolder" title="Create a new namespace"><span class="fileIcon folderAdd white"></span></a>
		</div>
		<div class="col-sm-8"><span class="title">Details</span></div>
	</div>
	<div class="row">
		<ul id="structureList" class="col-sm-4 noSelect"></ul>
		<div class="col-sm-8" id="details"></div>
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

	<div id="newFolderDialog">
		<form class="form-horizontal">
			<div class="form-group">
				<label for="folderName" class="col-sm-3 control-label">Namespace</label>
				<div class="col-sm-9">
					<input type="text" class="form-control" id="folderName" value="" placeholder="App\">
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-3"></div>
				<div class="col-sm-9">
					<button id="createNewFolder" class="btn btn-primary">Create</button>
				</div>
			</div>
		</form>
	</div>

	<div id="newFileDialog">
		<form class="form-horizontal">
			<div class="form-group">
				<label class="col-sm-3 control-label">Namespace</label>
				<div class="col-sm-9">
					<input type="text" class="form-control" id="objectNamespace" value="">
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label" for="objectName">Object Name</label>
				<div class="col-sm-9">
					<input type="text" class="form-control" id="objectName" placeholder="Name of Class">
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label" for="objectType">Object Type</label>
				<div class="col-sm-9">
					<select id="objectType" class="form-control">
						<option value="Controller">Controller</option>
						<option value="View">View</option>
					</select>
					<div id="ControllerOptions">
						<div class="checkbox">
							<label for="objectPageController">
								<input type="checkbox" id="objectPageController"/>
								Page Controller
							</label>
						</div>
						<div class="checkbox">
							<label for="objectDataObjectController">
								<input type="checkbox" id="objectDataObjectController"/>
								Data Object Controller
							</label>
						</div>
					</div>
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-3"></div>
				<div class="col-sm-9">
					<button id="createNewFile" class="btn btn-primary">Create</button>
				</div>
			</div>
		</form>
	</div>

	<div id="objectTemplate" class="objectDetails">
		<h2 id="objectName"></h2>
		<div class="form-horizontal">
			<div class="form-group">
				<label class="col-sm-3 control-label">File</label>
				<div class="col-sm-9">
					<p class="form-control-static" id="objectFile"></p>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">Traits</label>
				<div class="col-sm-9">
					<p class="form-control-static" id="objectTraits"></p>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">Actions</label>
				<div class="col-sm-9">
					<p class="form-control-static" id="objectActions"></p>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">Permissions</label>
				<div class="col-sm-9">
					<pre class="form-control-static" id="objectPermissions">&nbsp;</pre>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">Allowable Paths</label>
				<div class="col-sm-9">
					<pre class="form-control-static" id="objectPaths">&nbsp;</pre>
				</div>
			</div>
		</div>
	</div>

	<div id="basicAuthTemplate" class="basicAuthDetails">
		<h2>BasicAuth.json</h2>
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
					<button id="saveRealm" class="btn btn-primary">Save</button>
					<button id="deleteRealm" class="btn btn-default">Delete</button>
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
</div>

</body>
</html>