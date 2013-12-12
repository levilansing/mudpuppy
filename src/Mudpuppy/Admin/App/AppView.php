<?php
use Mudpuppy\App, App\Config;

$controller = App::getPageController();
?>
<!DOCTYPE html>
<html>
<head>
	<?php $controller->renderHeader(); ?>
</head>
<body>
<div class="pageHeader">
	<a href="/mudpuppy/" class="adminBackButton">Admin</a> <span id="pageTitle"><?php print Config::$appTitle; ?> | App Structure</span>
</div>
<br/>
<div class="container-fluid" style="margin: auto; max-width: 1000px">
	<div class="row-fluid appListHeader">
		<div class="span4">
			<span class="title">Files</span>
			<a id="newFile" title="Create a new file from a template"><span class="fileIcon fileAdd white"></span></a>
			<a id="newFolder" title="Create a new folder"><span class="fileIcon folderAdd white"></span></a>
		</div>
		<div class="span8"><span class="title">Details</span></div>
	</div>
	<div class="row-fluid">
		<div class="span4">
			<ul id="structureList" class="noSelect"></ul>
		</div>
		<div class="span8" id="details"></div>
	</div>
</div>

<div style="display: none;">
	<div id="dialog" class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-hidden="true">
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

	<div id="newFolderDialog">
		<form class="form-horizontal">
			<div class="control-group">
				<label class="control-label">Folder Name</label>
				<div class="controls">
					<input type="text" id="folderName" value="" placeholder="App/">
				</div>
			</div>
			<div class="control-group">
				<div class="controls">
					<button id="createNewFolder" class="btn btn-info">Create</button>
				</div>
			</div>
		</form>
	</div>

	<div id="newFileDialog">
		<form class="form-horizontal">
			<div class="control-group">
				<label class="control-label">Namespace</label>
				<div class="controls">
					<input type="text" id="objectNamespace" value="">
				</div>
			</div>
			<div class="control-group">
				<label class="control-label" for="objectName">Object Name</label>
				<div class="controls">
					<input type="text" id="objectName" placeholder="Name of Class">
				</div>
			</div>
			<div class="control-group">
				<label class="control-label" for="objectType">Object Type</label>
				<div class="controls">
					<select id="objectType">
						<option value="Controller">Controller</option>
						<option value="View">View</option>
					</select>
				</div>
				<div id="ControllerOptions" class="controls">
					<label for="objectPageController"><input type="checkbox" id="objectPageController" /> Page Controller</label>
					<label for="objectDataObjectController"><input type="checkbox" id="objectDataObjectController" /> Data Object Controller</label>
				</div>
			</div>
			<div class="control-group">
				<div class="controls">
					<button id="createNewFile" class="btn btn-info">Create</button>
				</div>
			</div>
		</form>
	</div>

	<div id="objectTemplate" class="objectDetails">
		<h2 id="objectName"></h2>
		<div class="form-horizontal">
			<div class="control-group">
				<label class="control-label">File</label>
				<div class="controls">
					<div id="objectFile"></div>
				</div>
			</div>
			<div class="control-group">
				<label class="control-label">Traits</label>
				<div class="controls">
					<div id="objectTraits"></div>
				</div>
			</div>
			<div class="control-group">
				<label class="control-label">Actions</label>
				<div class="controls">
					<div id="objectActions"></div>
				</div>
			</div>
			<div class="control-group">
				<label class="control-label">Permissions</label>
				<div class="controls">
					<pre id="objectPermissions"></pre>
				</div>
			</div>
			<div class="control-group">
				<label class="control-label">Allowable Paths</label>
				<div class="controls">
					<pre id="objectPaths"></pre>
				</div>
			</div>
		</div>
	</div>
</div>

</body>
</html>