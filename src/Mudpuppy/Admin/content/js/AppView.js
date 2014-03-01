//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================
(function($) {

	function AppView() {
		var self = this;
		$('#structureList').click(function(e) {
			self.select();
			e.stopPropagation();
		});
		$.observer.registerForEvents(this, {
			onNewFile: this.onNewFile,
			onNewFolder: this.onNewFolder,
			onCreateNewFile: this.onCreateNewFile,
			onCreateNewFolder: this.onCreateNewFolder,
			onObjectTypeChange: function(e, item) {
				if ($(item).val() == 'Controller') {
					$(item).parents('form').find('#ControllerOptions').show(300);
				} else {
					$(item).parents('form').find('#ControllerOptions').hide(300);
				}
			}
		});
		this.newFolderDialog = $('#newFolderDialog').detach();
		this.newFileDialog = $('#newFileDialog').detach();
		this.refresh();
	}

	window.AppView = AppView;

	AppView.prototype.callAction = function(action, params, success) {
		$.ajax({
			dataType: 'json',
			url: '/mudpuppy/App/' + action,
			data: params,
			success: success,
			error: function(result) {
				if (result.responseJSON && result.responseJSON.message) {
					alert(result.responseJSON.message);
				} else {
					alert("An unknown error occurred");
				}
			}
		});
	};

	AppView.prototype.refresh = function() {
		var self = this;
		this.callAction('listApp', null, function(data) {
			console.log(data);
			$('#structureList').empty();
			self.update(data);
		});
	};

	AppView.prototype.update = function(data) {
		var self = this;
		var liFolderTemplate = $('<li><div></div></li>').click(function(e) {
			var $this = $(this);
			if ($this.hasClass('open')) {
				$this.find('>ul').hide(300);
				self.select();
			} else {
				$this.find('>ul').show(300);
				self.select($this.find('>ul>li:not(.folder):eq(0)'));
			}
			$this.toggleClass('open');
			e.stopPropagation();
		});
		var liTemplate = $('<li><div></div></li>').click(function(e) {
			self.select(this);
			e.stopPropagation();
		});

		(function update(data, list) {
			if (!list) {
				list = $('#structureList');
			}
			$.each(data, function(key, item) {
				var li;
				if (key.substr(-1) == '\\') {
					li = liFolderTemplate.clone(true);
					li.find('>div').append($('<span class="fileIcon folder"></span>')).append(key);
					li.addClass('folder open');
					var ul = $('<ul></ul>');
					update(item, ul);
					li.append(ul);
				} else {
					li = liTemplate.clone(true);
					li.find('>div').append($('<span class="fileIcon ' + item.type + '"></span>')).append(key);
					li.addClass(item.type);
				}
				li.data('properties', item);
				list.append(li);
			});
		})(data);
	};

	AppView.prototype.select = function(li) {
		$('#structureList').find('li').removeClass('selected').find('.fileIcon.white').removeClass('white');

		if (li) {
			var info = $(li).data('properties');
			$(li).addClass('selected').find('.fileIcon').addClass('white');

			var $template = $();
			var self = this;
			if (info) {
				if (info.type == 'controller' || info.type == 'pageController' || info.type == 'dataObjectController') {
					$template = $('#objectTemplate').clone().attr('id', '');
					var props = info.properties;
					$template.find('#objectName').text(props.namespace + '\\' + props.className);
					$template.find('#objectFile').text(info.file);

					var $actions = $('<ul></ul>');
					$.each(props.actions || {}, function(i, action) {
						$actions.append($('<li></li>').text(action));
					});
					$template.find('#objectActions').append($actions);

					var $traits = $('<ul></ul>');
					$.each(props.traits || {}, function(i, trait) {
						$traits.append($('<li></li>').text(trait));
					});
					$template.find('#objectTraits').append($traits);

					$template.find('#objectPermissions').append(props.permissions);
					$template.find('#objectPaths').append(props.paths);
				} else if (info.type == 'basicAuth') {
					$template = $('#basicAuthTemplate').clone().attr('id', '');
					var $realms = $template.find('#realms');
					for (var n = 0; n < info.properties.realms.length; n++) {
						$realms.append('<option val="' + info.properties.realms[n] + '">' + info.properties.realms[n] + '</option>');
					}
					var $credentials = $template.find('#credentials');
					$credentials.change(function() {
						var username = $(this).val();
						if (username == '') {
							$template.find('#username, #password').val('');
							$template.find('#deleteCredential').prop('disabled', true);
						} else {
							$template.find('#username').val(username);
							$template.find('#password').val('');
							$template.find('#deleteCredential').prop('disabled', false);
						}
					});
					$realms.change(function() {
						var realmName = $(this).val();
						if (realmName == '') {
							$template.find('#realmName, #pathPattern').val('');
							$template.find('#deleteRealm, #credentials, #username, #password, #saveCredential').prop('disabled',
								true);
						} else {
							$template.find('#deleteRealm, #credentials, #username, #password, #saveCredential').prop('disabled',
								false);
							self.callAction('getBasicAuthRealm', {name: realmName}, function(data) {
								$template.find('#realmName').val(data.name);
								$template.find('#pathPattern').val(data.pathPattern);
								$credentials.find('option[value!=""]').remove();
								for (var n = 0; n < data.credentials.length; n++) {
									$credentials.append('<option value="' + data.credentials[n] + '">' + data.credentials[n] + '</option>');
								}
							});
						}
						$credentials.find('option').prop('selected', false).filter('[value=""]').prop('selected', true);
						$credentials.change();
					}).change();
					$template.find('#saveRealm').click(function() {
						var params = {
							oldName: $template.find('#realms').val(),
							newName: $template.find('#realmName').val(),
							pathPattern: $template.find('#pathPattern').val()
						};
						self.callAction('saveBasicAuthRealm', params, function(data) {
							info.properties.realms = data.realms;
							$realms.find('option[value!=""]').remove();
							for (var n = 0; n < data.realms.length; n++) {
								$realms.append('<option value="' + data.realms[n] + '">' + data.realms[n] + '</option>');
							}
							$realms.find('option').prop('selected',
								false).filter('[value="' + params.newName + '"]').prop('selected', true);
							$realms.change();
						});
					});
					$template.find('#deleteRealm').click(function() {
						self.callAction('deleteBasicAuthRealm', {name: $template.find('#realms').val()}, function(data) {
							info.properties.realms = data.realms;
							$realms.find('option[value!=""]').remove();
							for (var n = 0; n < data.realms.length; n++) {
								$realms.append('<option value="' + data.realms[n] + '">' + data.realms[n] + '</option>');
							}
							$realms.find('option').prop('selected', false).filter('[value=""]').prop('selected', true);
							$realms.change();
						});
					});
					$template.find('#saveCredential').click(function() {
						var params = {
							realmName: $template.find('#realms').val(),
							oldUsername: $template.find('#credentials').val(),
							newUsername: $template.find('#username').val(),
							password: $template.find('#password').val()
						};
						self.callAction('saveBasicAuthCredential', params, function(data) {
							$credentials.find('option[value!=""]').remove();
							for (var n = 0; n < data.credentials.length; n++) {
								$credentials.append('<option value="' + data.credentials[n] + '">' + data.credentials[n] + '</option>');
							}
							$credentials.find('option').prop('selected',
								false).filter('[value="' + params.newUsername + '"]').prop('selected', true);
							$credentials.change();
						});
					});
					$template.find('#deleteCredential').click(function() {
						self.callAction('deleteBasicAuthCredential', {
							realmName: $template.find('#realms').val(),
							username: $template.find('#credentials').val()
						}, function(data) {
							$credentials.find('option[value!=""]').remove();
							for (var n = 0; n < data.credentials.length; n++) {
								$credentials.append('<option value="' + data.credentials[n] + '">' + data.credentials[n] + '</option>');
							}
							$credentials.find('option').prop('selected', false).filter('[value=""]').prop('selected', true);
							$credentials.change();
						});
					});
				} else if (info.file) {
					$template = $('<h2>' + info.file + '</h2><div>No additional details for this file are available.</div>');
				}
			}

			$('#details').empty().append($template);
		} else {
			$('#details').empty();
		}
	};

	/**
	 * @returns {{properties:{actions:[],namespace:,traits:[]},file:,type:}|{}}
	 */
	AppView.prototype.getSelectedInfo = function() {
		return $('#structureList').find('li.selected').data('properties') || {};
	};

	AppView.prototype.showModal = function(title, body) {
		var dialog = $('#dialog');
		$('body').append(dialog);
		dialog.find('.modal-title').text(title);
		dialog.find('.modal-body').html(body);
		dialog.modal();
	};

	AppView.prototype.onNewFile = function(e, item) {
		var info = this.getSelectedInfo();
		var namespace = (info.properties && info.properties.namespace) || 'App\\';

		var body = this.newFileDialog.clone(true);
		body.find('input').val('').prop('checked', false);
		body.find('select#objectType').val('Controller').change();
		body.find('#objectNamespace').val(namespace);
		this.showModal("Create a New Object", body);
	};

	AppView.prototype.onNewFolder = function(e, item) {
		var info = this.getSelectedInfo();
		var namespace = (info.properties && info.properties.namespace) || 'App\\';

		var body = this.newFolderDialog.clone();
		this.showModal("Create a New Namespace", body);
	};

	AppView.prototype.onCreateNewFolder = function(e, item) {
		e.preventDefault();
		var $form = $(item).parents('form');
		var name = $form.find('#folderName').val();

		var self = this;
		this.callAction('createFolder', {name: name}, function(result) {
			self.refresh();
			$('#dialog').modal('hide');
		});
	};

	AppView.prototype.onCreateNewFile = function(e, item) {
		e.preventDefault();
		var $form = $(item).parents('form');
		var namespace = $form.find('#objectNamespace').val();
		var name = $form.find('#objectName').val();
		var type = $form.find('#objectType').val();
		var traitPageController = $form.find('#objectPageController').prop('checked');
		var traitDataObjectController = $form.find('#objectDataObjectController').prop('checked');

		var self = this;
		this.callAction('createFile', {
			namespace: namespace,
			name: name,
			type: type,
			isPageController: traitPageController,
			isDataObjectController: traitDataObjectController
		}, function(result) {
			self.refresh();
			$('#dialog').modal('hide');
		});
	};

})(jQuery);

var appView = null;
$(document).ready(function() {
	if (!appView) {
		appView = new AppView();
	}
});
