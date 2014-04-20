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
		var timeZones = $('#timezoneConstant');
		var optionTemplate = $('<option value=""></option>');
		for (var i = 0; i < AppView.timeZones.length; i++) {
			var option = optionTemplate.clone();
			option.attr('value', AppView.timeZones[i]).text(AppView.timeZones[i]);
			timeZones.append(option);
		}
		this.refresh();
	}

	window.AppView = AppView;

	AppView.prototype.callAction = function(action, params, success) {
		$.ajax({
			method: 'post',
			dataType: 'json',
			url: '/mudpuppy/app/' + action,
			data: JSON.stringify(params),
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
			if (info) {
				if (info.type == 'controller' || info.type == 'pageController' || info.type == 'dataObjectController') {
					$template = this.setupControllerDetails(info);
				} else if (info.type == 'basicAuth') {
					$template = this.setupBasicAuthDetails(info);
				} else if (info.type == 'config') {
					$template = this.setupConfigDetails(info);
				} else if (info.file) {
					$template = $('<h2>' + info.file + '</h2><div>No additional details for this file are available.</div>');
				}
			}

			$('#details').empty().append($template);
		} else {
			$('#details').empty();
		}
	};

	AppView.prototype.setupControllerDetails = function(info) {
		var $template = $('#objectTemplate').clone().attr('id', '');
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
		return $template;
	};

	AppView.prototype.setupBasicAuthDetails = function(info) {
		var self = this;
		var $template = $('#basicAuthTemplate').clone().attr('id', '');
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
			$credentials.val('').change();
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
				$realms.val(params.newName).change();
			});
		});
		$template.find('#deleteRealm').click(function() {
			self.callAction('deleteBasicAuthRealm', {name: $template.find('#realms').val()}, function(data) {
				info.properties.realms = data.realms;
				$realms.find('option[value!=""]').remove();
				for (var n = 0; n < data.realms.length; n++) {
					$realms.append('<option value="' + data.realms[n] + '">' + data.realms[n] + '</option>');
				}
				$realms.val('').change();
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
				$credentials.val(params.newUsername).change();
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
				$credentials.val('').change();
			});
		});
		return $template;
	};

	AppView.prototype.setupConfigDetails = function(info) {
		var self = this;
		var config = info.properties.config;
		var $template = $('#configTemplate').clone().attr('id', '');
		var $environment = $template.find('#environment');
		var $controlVar = $template.find('#controlVar');
		var $controlVal = $template.find('#controlVal');
		if (config.environments) {
			for (var envVal in config.environments) {
				$environment.append('<option value="controlVal:' + envVal + '">' + envVal + '</option>');
			}
		}
		if (config.controlVar) {
			$controlVar.val(config.controlVar);
		} else {
			$controlVar.val('');
		}
		var $typeSelects = $template.find('.typeSelect');
		var $customProp = $template.find('#customProp');
		$environment.change(function() {
			var env = $environment.val();
			var envVal = env.substr(11);
			$controlVal.prop('disabled', (env == 'base')).val(envVal);
			$template.find('#deleteEnv').prop('disabled', (envVal.length == 0));
			$template.find('.typeSelect').val('default').change();
			$customProp.val('').change().find('option[value!=""]').remove();
			if (env != '') {
				var settings = config.base;
				if (envVal.length > 0) {
					settings = config.environments[envVal];
				}
				for (var key in settings) {
					if (key == 'custom') {
						for (var prop in settings.custom) {
							$customProp.append('<option value="' + prop + '">' + prop + '</option>');
						}
					} else {
						$template.find('#' + key + 'Type').val(settings[key].type).change();
						if (key == 'timezone' || key == 'dbPass' || key == 'logLevel') {
							var suffix = ((settings[key].type == 'constant') ? 'Constant' : 'Variable');
							$template.find('#' + key + suffix).val(settings[key].value);
						} else if (key == 'debug' || key == 'logQueries' || key == 'logToDatabase') {
							if (settings[key].type == 'constant') {
								$template.find('#' + key + 'Constant').prop('checked', settings[key].value);
							} else {
								$template.find('#' + key + 'Variable').val(settings[key].value);
							}
						} else {
							$template.find('#' + key + 'Value').val(settings[key].value);
						}
					}
				}
			}
		});
		$typeSelects.change(function() {
			var $select = $(this);
			var key = $select.attr('id');
			key = key.substr(0, key.length - 4);
			var type = $select.val();
			if (type == 'default') {
				$template.find('#' + key + 'Value').val('').hide();
			} else {
				$template.find('#' + key + 'Value').val('').show();
			}
			if (key == 'timezone' || key == 'dbPass' || key == 'logLevel' || key == 'debug' || key == 'logQueries' || key == 'logToDatabase') {
				var constant = $template.find('#' + key + 'Constant');
				if (constant.parent().hasClass('checkbox-inline')) {
					constant = constant.parent();
				}
				var variable = $template.find('#' + key + 'Variable');
				if (type == 'constant') {
					constant.show();
					variable.val('').hide();
				} else if (type == 'variable') {
					constant.filter('input').val('');
					constant.hide();
					variable.show();
				} else {
					constant.filter('input').val('');
					constant.hide();
					variable.val('').hide();
				}
			}
		});
		$customProp.change(function() {
			var prop = $customProp.val();
			if (prop == '') {
				$template.find('#propKey').val(prop);
				$template.find('#propValType').val('constant').change();
				$template.find('#deleteProp').prop('disabled', true);
			} else {
				var env = $environment.val();
				if (env != '') {
					var envVal = env.substr(11);
					var settings = config.base;
					if (envVal.length > 0) {
						settings = config.environments[envVal];
					}
					$template.find('#propKey').val(prop);
					$template.find('#propValType').val(settings.custom[prop].type).change();
					$template.find('#propValValue').val(settings.custom[prop].value);
				}
				$template.find('#deleteProp').prop('disabled', false);
			}
		});
		$template.find('#saveEnv, #saveProp').click(function() {
			config.controlVar = $controlVar.val();
			var env = $environment.val();
			var settings = config.base;
			if (env == '') {
				if (!config.environments || config.environments instanceof Array) {
					config.environments = {};
				}
				var envVal = $controlVal.val();
				if (envVal == '') {
					alert('Control Value cannot be empty.');
					return;
				}
				if (config.environments[envVal]) {
					alert('The specified Control Value already exists. If you wish to modify that environment, please choose it from the list.');
					return;
				}
				$environment.append('<option value="controlVal:' + envVal + '">' + envVal + '</option>');
				$environment.val('controlVal:' + envVal);
				settings = config.environments[envVal] = {};
			} else {
				var envVal = env.substr(11);
				if (envVal.length > 0) {
					settings = config.environments[envVal];
					var newEnvVal = $controlVal.val();
					if (envVal != newEnvVal) {
						if (newEnvVal == '') {
							alert('Control Value cannot be empty.');
							return;
						}
						if (config.environments[newEnvVal]) {
							alert('The specified Control Value already exists. If you wish to modify that environment, please choose it from the list.');
							return;
						}
						$environment.find('option[value="controlVal:' + envVal + '"]').remove();
						$environment.append('<option value="controlVal:' + newEnvVal + '">' + newEnvVal + '</option>');
						$environment.val('controlVal:' + newEnvVal);
						config.environments[newEnvVal] = settings;
						delete config.environments[envVal];
					}
				}
			}
			var prop = $customProp.val();
			var propSetting = null;
			if (prop == '') {
				if (!settings.custom || settings.custom instanceof Array) {
					settings.custom = {};
				}
				prop = $template.find('#propKey').val();
				if (prop != '') {
					if (settings.custom[prop]) {
						alert('The specified Property Key already exists. If you wish to modify that property, please choose it from the list.');
						return;
					}
					$customProp.append('<option value="' + prop + '">' + prop + '</option>');
					$customProp.val(prop);
					$template.find('#deleteProp').prop('disabled', false);
					propSetting = settings.custom[prop] = {};
				} else if (this.id == 'saveProp') {
					alert('Property Key cannot be empty.');
					return;
				}
			} else {
				propSetting = settings.custom[prop];
				var newProp = $template.find('#propKey').val();
				if (prop != newProp) {
					if (newProp == '') {
						alert('Property Key cannot be empty.');
						return;
					}
					if (settings.custom[newProp]) {
						alert('The specified Property Key already exists. If you wish to modify that property, please choose it from the list.');
						return;
					}
					$customProp.find('option[value="' + prop + '"]').remove();
					$customProp.append('<option value="' + newProp + '">' + newProp + '</option>');
					$customProp.val(newProp);
					settings.custom[newProp] = propSetting;
					delete settings.custom[prop];
				}
			}
			if (propSetting != null) {
				propSetting.type = $template.find('#propValType').val();
				propSetting.value = $template.find('#propValValue').val();
			}
			var fields = ['appTitle', 'timezone', 'dateTimeFormat', 'dateOnlyFormat', 'dbHost', 'dbPort', 'dbDatabase',
				'dbUser', 'dbPass', 'debug', 'logLevel', 'logQueries', 'logToDatabase', 'logFileDir'];
			for (var i = 0; i < fields.length; i++) {
				var type = $template.find('#' + fields[i] + 'Type').val();
				if (type == 'default') {
					delete settings[fields[i]];
				} else {
					if (!settings[fields[i]]) {
						settings[fields[i]] = {};
					}
					settings[fields[i]].type = type;
					if (fields[i] == 'debug' || fields[i] == 'logQueries' || fields[i] == 'logToDatabase') {
						if (type == 'constant') {
							settings[fields[i]].value = ($template.find('#' + fields[i] + 'Constant').is(':checked') ? 'true' : 'false');
						} else {
							settings[fields[i]].value = $template.find('#' + fields[i] + 'Variable').val();
						}
					} else if (fields[i] == 'timezone' || fields[i] == 'dbPass' || fields[i] == 'logLevel') {
						if (type == 'constant') {
							settings[fields[i]].value = $template.find('#' + fields[i] + 'Constant').val();
						} else {
							settings[fields[i]].value = $template.find('#' + fields[i] + 'Variable').val();
						}
					} else {
						settings[fields[i]].value = $template.find('#' + fields[i] + 'Value').val();
					}
				}
			}
			self.callAction('storeConfig', {config: config});
		});
		$template.find('#deleteEnv').click(function() {
			delete config.environments[$environment.val().substr(11)];
			self.callAction('storeConfig', {config: config});
			$environment.find('option[value="' + $environment.val() + '"]').remove();
			$environment.val('base').change();
		});
		$template.find('#deleteProp').click(function() {
			var settings = config.base;
			var env = $environment.val().substr(11);
			if (env.length > 0) {
				settings = config.environments[env];
			}
			delete settings.custom[$customProp.val()];
			self.callAction('storeConfig', {config: config});
			$customProp.find('option[value="' + $customProp.val() + '"]').remove();
			$customProp.val('').change();
		});
		$environment.change();
		return $template;
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

	AppView.timeZones = [
		'Africa/Abidjan', 'Africa/Accra', 'Africa/Addis_Ababa', 'Africa/Algiers', 'Africa/Asmara', 'Africa/Asmera',
		'Africa/Bamako', 'Africa/Bangui', 'Africa/Banjul', 'Africa/Bissau', 'Africa/Blantyre', 'Africa/Brazzaville',
		'Africa/Bujumbura', 'Africa/Cairo', 'Africa/Casablanca', 'Africa/Ceuta', 'Africa/Conakry', 'Africa/Dakar',
		'Africa/Dar_es_Salaam', 'Africa/Djibouti', 'Africa/Douala', 'Africa/El_Aaiun', 'Africa/Freetown',
		'Africa/Gaborone', 'Africa/Harare', 'Africa/Johannesburg', 'Africa/Juba', 'Africa/Kampala', 'Africa/Khartoum',
		'Africa/Kigali', 'Africa/Kinshasa', 'Africa/Lagos', 'Africa/Libreville', 'Africa/Lome', 'Africa/Luanda',
		'Africa/Lubumbashi', 'Africa/Lusaka', 'Africa/Malabo', 'Africa/Maputo', 'Africa/Maseru', 'Africa/Mbabane',
		'Africa/Mogadishu', 'Africa/Monrovia', 'Africa/Nairobi', 'Africa/Ndjamena', 'Africa/Niamey',
		'Africa/Nouakchott', 'Africa/Ouagadougou', 'Africa/Porto-Novo', 'Africa/Sao_Tome', 'Africa/Timbuktu',
		'Africa/Tripoli', 'Africa/Tunis', 'Africa/Windhoek', 'America/Adak', 'America/Anchorage', 'America/Anguilla',
		'America/Antigua', 'America/Araguaina', 'America/Argentina/Buenos_Aires', 'America/Argentina/Catamarca',
		'America/Argentina/ComodRivadavia', 'America/Argentina/Cordoba', 'America/Argentina/Jujuy',
		'America/Argentina/La_Rioja', 'America/Argentina/Mendoza', 'America/Argentina/Rio_Gallegos',
		'America/Argentina/Salta', 'America/Argentina/San_Juan', 'America/Argentina/San_Luis',
		'America/Argentina/Tucuman', 'America/Argentina/Ushuaia', 'America/Aruba', 'America/Asuncion',
		'America/Atikokan', 'America/Atka', 'America/Bahia', 'America/Bahia_Banderas', 'America/Barbados',
		'America/Belem', 'America/Belize', 'America/Blanc-Sablon', 'America/Boa_Vista', 'America/Bogota',
		'America/Boise', 'America/Buenos_Aires', 'America/Cambridge_Bay', 'America/Campo_Grande', 'America/Cancun',
		'America/Caracas', 'America/Catamarca', 'America/Cayenne', 'America/Cayman', 'America/Chicago',
		'America/Chihuahua', 'America/Coral_Harbour', 'America/Cordoba', 'America/Costa_Rica', 'America/Creston',
		'America/Cuiaba', 'America/Curacao', 'America/Danmarkshavn', 'America/Dawson', 'America/Dawson_Creek',
		'America/Denver', 'America/Detroit', 'America/Dominica', 'America/Edmonton', 'America/Eirunepe',
		'America/El_Salvador', 'America/Ensenada', 'America/Fort_Wayne', 'America/Fortaleza', 'America/Glace_Bay',
		'America/Godthab', 'America/Goose_Bay', 'America/Grand_Turk', 'America/Grenada', 'America/Guadeloupe',
		'America/Guatemala', 'America/Guayaquil', 'America/Guyana', 'America/Halifax', 'America/Havana',
		'America/Hermosillo', 'America/Indiana/Indianapolis', 'America/Indiana/Knox', 'America/Indiana/Marengo',
		'America/Indiana/Petersburg', 'America/Indiana/Tell_City', 'America/Indiana/Vevay', 'America/Indiana/Vincennes',
		'America/Indiana/Winamac', 'America/Indianapolis', 'America/Inuvik', 'America/Iqaluit', 'America/Jamaica',
		'America/Jujuy', 'America/Juneau', 'America/Kentucky/Louisville', 'America/Kentucky/Monticello',
		'America/Knox_IN', 'America/Kralendijk', 'America/La_Paz', 'America/Lima', 'America/Los_Angeles',
		'America/Louisville', 'America/Lower_Princes', 'America/Maceio', 'America/Managua', 'America/Manaus',
		'America/Marigot', 'America/Martinique', 'America/Matamoros', 'America/Mazatlan', 'America/Mendoza',
		'America/Menominee', 'America/Merida', 'America/Metlakatla', 'America/Mexico_City', 'America/Miquelon',
		'America/Moncton', 'America/Monterrey', 'America/Montevideo', 'America/Montreal', 'America/Montserrat',
		'America/Nassau', 'America/New_York', 'America/Nipigon', 'America/Nome', 'America/Noronha',
		'America/North_Dakota/Beulah', 'America/North_Dakota/Center', 'America/North_Dakota/New_Salem',
		'America/Ojinaga', 'America/Panama', 'America/Pangnirtung', 'America/Paramaribo', 'America/Phoenix',
		'America/Port-au-Prince', 'America/Port_of_Spain', 'America/Porto_Acre', 'America/Porto_Velho',
		'America/Puerto_Rico', 'America/Rainy_River', 'America/Rankin_Inlet', 'America/Recife', 'America/Regina',
		'America/Resolute', 'America/Rio_Branco', 'America/Rosario', 'America/Santa_Isabel', 'America/Santarem',
		'America/Santiago', 'America/Santo_Domingo', 'America/Sao_Paulo', 'America/Scoresbysund', 'America/Shiprock',
		'America/Sitka', 'America/St_Barthelemy', 'America/St_Johns', 'America/St_Kitts', 'America/St_Lucia',
		'America/St_Thomas', 'America/St_Vincent', 'America/Swift_Current', 'America/Tegucigalpa', 'America/Thule',
		'America/Thunder_Bay', 'America/Tijuana', 'America/Toronto', 'America/Tortola', 'America/Vancouver',
		'America/Virgin', 'America/Whitehorse', 'America/Winnipeg', 'America/Yakutat', 'America/Yellowknife',
		'Antarctica/Casey', 'Antarctica/Davis', 'Antarctica/DumontDUrville', 'Antarctica/Macquarie',
		'Antarctica/Mawson', 'Antarctica/McMurdo', 'Antarctica/Palmer', 'Antarctica/Rothera', 'Antarctica/South_Pole',
		'Antarctica/Syowa', 'Antarctica/Troll', 'Antarctica/Vostok', 'Arctic/Longyearbyen', 'Asia/Aden', 'Asia/Almaty',
		'Asia/Amman', 'Asia/Anadyr', 'Asia/Aqtau', 'Asia/Aqtobe', 'Asia/Ashgabat', 'Asia/Ashkhabad', 'Asia/Baghdad',
		'Asia/Bahrain', 'Asia/Baku', 'Asia/Bangkok', 'Asia/Beirut', 'Asia/Bishkek', 'Asia/Brunei', 'Asia/Calcutta',
		'Asia/Choibalsan', 'Asia/Chongqing', 'Asia/Chungking', 'Asia/Colombo', 'Asia/Dacca', 'Asia/Damascus',
		'Asia/Dhaka', 'Asia/Dili', 'Asia/Dubai', 'Asia/Dushanbe', 'Asia/Gaza', 'Asia/Harbin', 'Asia/Hebron',
		'Asia/Ho_Chi_Minh', 'Asia/Hong_Kong', 'Asia/Hovd', 'Asia/Irkutsk', 'Asia/Istanbul', 'Asia/Jakarta',
		'Asia/Jayapura', 'Asia/Jerusalem', 'Asia/Kabul', 'Asia/Kamchatka', 'Asia/Karachi', 'Asia/Kashgar',
		'Asia/Kathmandu', 'Asia/Katmandu', 'Asia/Khandyga', 'Asia/Kolkata', 'Asia/Krasnoyarsk', 'Asia/Kuala_Lumpur',
		'Asia/Kuching', 'Asia/Kuwait', 'Asia/Macao', 'Asia/Macau', 'Asia/Magadan', 'Asia/Makassar', 'Asia/Manila',
		'Asia/Muscat', 'Asia/Nicosia', 'Asia/Novokuznetsk', 'Asia/Novosibirsk', 'Asia/Omsk', 'Asia/Oral',
		'Asia/Phnom_Penh', 'Asia/Pontianak', 'Asia/Pyongyang', 'Asia/Qatar', 'Asia/Qyzylorda', 'Asia/Rangoon',
		'Asia/Riyadh', 'Asia/Saigon', 'Asia/Sakhalin', 'Asia/Samarkand', 'Asia/Seoul', 'Asia/Shanghai',
		'Asia/Singapore', 'Asia/Taipei', 'Asia/Tashkent', 'Asia/Tbilisi', 'Asia/Tehran', 'Asia/Tel_Aviv', 'Asia/Thimbu',
		'Asia/Thimphu', 'Asia/Tokyo', 'Asia/Ujung_Pandang', 'Asia/Ulaanbaatar', 'Asia/Ulan_Bator', 'Asia/Urumqi',
		'Asia/Ust-Nera', 'Asia/Vientiane', 'Asia/Vladivostok', 'Asia/Yakutsk', 'Asia/Yekaterinburg', 'Asia/Yerevan',
		'Atlantic/Azores', 'Atlantic/Bermuda', 'Atlantic/Canary', 'Atlantic/Cape_Verde', 'Atlantic/Faeroe',
		'Atlantic/Faroe', 'Atlantic/Jan_Mayen', 'Atlantic/Madeira', 'Atlantic/Reykjavik', 'Atlantic/South_Georgia',
		'Atlantic/St_Helena', 'Atlantic/Stanley', 'Australia/ACT', 'Australia/Adelaide', 'Australia/Brisbane',
		'Australia/Broken_Hill', 'Australia/Canberra', 'Australia/Currie', 'Australia/Darwin', 'Australia/Eucla',
		'Australia/Hobart', 'Australia/LHI', 'Australia/Lindeman', 'Australia/Lord_Howe', 'Australia/Melbourne',
		'Australia/North', 'Australia/NSW', 'Australia/Perth', 'Australia/Queensland', 'Australia/South',
		'Australia/Sydney', 'Australia/Tasmania', 'Australia/Victoria', 'Australia/West', 'Australia/Yancowinna',
		'Europe/Amsterdam', 'Europe/Andorra', 'Europe/Athens', 'Europe/Belfast', 'Europe/Belgrade', 'Europe/Berlin',
		'Europe/Bratislava', 'Europe/Brussels', 'Europe/Bucharest', 'Europe/Budapest', 'Europe/Busingen',
		'Europe/Chisinau', 'Europe/Copenhagen', 'Europe/Dublin', 'Europe/Gibraltar', 'Europe/Guernsey',
		'Europe/Helsinki', 'Europe/Isle_of_Man', 'Europe/Istanbul', 'Europe/Jersey', 'Europe/Kaliningrad',
		'Europe/Kiev', 'Europe/Lisbon', 'Europe/Ljubljana', 'Europe/London', 'Europe/Luxembourg', 'Europe/Madrid',
		'Europe/Malta', 'Europe/Mariehamn', 'Europe/Minsk', 'Europe/Monaco', 'Europe/Moscow', 'Europe/Nicosia',
		'Europe/Oslo', 'Europe/Paris', 'Europe/Podgorica', 'Europe/Prague', 'Europe/Riga', 'Europe/Rome',
		'Europe/Samara', 'Europe/San_Marino', 'Europe/Sarajevo', 'Europe/Simferopol', 'Europe/Skopje', 'Europe/Sofia',
		'Europe/Stockholm', 'Europe/Tallinn', 'Europe/Tirane', 'Europe/Tiraspol', 'Europe/Uzhgorod', 'Europe/Vaduz',
		'Europe/Vatican', 'Europe/Vienna', 'Europe/Vilnius', 'Europe/Volgograd', 'Europe/Warsaw', 'Europe/Zagreb',
		'Europe/Zaporozhye', 'Europe/Zurich', 'Indian/Antananarivo', 'Indian/Chagos', 'Indian/Christmas',
		'Indian/Cocos', 'Indian/Comoro', 'Indian/Kerguelen', 'Indian/Mahe', 'Indian/Maldives', 'Indian/Mauritius',
		'Indian/Mayotte', 'Indian/Reunion', 'Pacific/Apia', 'Pacific/Auckland', 'Pacific/Chatham', 'Pacific/Chuuk',
		'Pacific/Easter', 'Pacific/Efate', 'Pacific/Enderbury', 'Pacific/Fakaofo', 'Pacific/Fiji', 'Pacific/Funafuti',
		'Pacific/Galapagos', 'Pacific/Gambier', 'Pacific/Guadalcanal', 'Pacific/Guam', 'Pacific/Honolulu',
		'Pacific/Johnston', 'Pacific/Kiritimati', 'Pacific/Kosrae', 'Pacific/Kwajalein', 'Pacific/Majuro',
		'Pacific/Marquesas', 'Pacific/Midway', 'Pacific/Nauru', 'Pacific/Niue', 'Pacific/Norfolk', 'Pacific/Noumea',
		'Pacific/Pago_Pago', 'Pacific/Palau', 'Pacific/Pitcairn', 'Pacific/Pohnpei', 'Pacific/Ponape',
		'Pacific/Port_Moresby', 'Pacific/Rarotonga', 'Pacific/Saipan', 'Pacific/Samoa', 'Pacific/Tahiti',
		'Pacific/Tarawa', 'Pacific/Tongatapu', 'Pacific/Truk', 'Pacific/Wake', 'Pacific/Wallis', 'Pacific/Yap',
		'Brazil/Acre', 'Brazil/DeNoronha', 'Brazil/East', 'Brazil/West', 'Canada/Atlantic', 'Canada/Central',
		'Canada/East-Saskatchewan', 'Canada/Eastern', 'Canada/Mountain', 'Canada/Newfoundland', 'Canada/Pacific',
		'Canada/Saskatchewan', 'Canada/Yukon', 'CET', 'Chile/Continental', 'Chile/EasterIsland', 'CST6CDT', 'Cuba',
		'EET', 'Egypt', 'Eire', 'EST', 'EST5EDT', 'Etc/GMT', 'Etc/GMT+0', 'Etc/GMT+1', 'Etc/GMT+10', 'Etc/GMT+11',
		'Etc/GMT+12', 'Etc/GMT+2', 'Etc/GMT+3', 'Etc/GMT+4', 'Etc/GMT+5', 'Etc/GMT+6', 'Etc/GMT+7', 'Etc/GMT+8',
		'Etc/GMT+9', 'Etc/GMT-0', 'Etc/GMT-1', 'Etc/GMT-10', 'Etc/GMT-11', 'Etc/GMT-12', 'Etc/GMT-13', 'Etc/GMT-14',
		'Etc/GMT-2', 'Etc/GMT-3', 'Etc/GMT-4', 'Etc/GMT-5', 'Etc/GMT-6', 'Etc/GMT-7', 'Etc/GMT-8', 'Etc/GMT-9',
		'Etc/GMT0', 'Etc/Greenwich', 'Etc/UCT', 'Etc/Universal', 'Etc/UTC', 'Etc/Zulu', 'Factory', 'GB', 'GB-Eire',
		'GMT', 'GMT+0', 'GMT-0', 'GMT0', 'Greenwich', 'Hongkong', 'HST', 'Iceland', 'Iran', 'Israel', 'Jamaica',
		'Japan', 'Kwajalein', 'Libya', 'MET', 'Mexico/BajaNorte', 'Mexico/BajaSur', 'Mexico/General', 'MST', 'MST7MDT',
		'Navajo', 'NZ', 'NZ-CHAT', 'Poland', 'Portugal', 'PRC', 'PST8PDT', 'ROC', 'ROK', 'Singapore', 'Turkey', 'UCT',
		'Universal', 'US/Alaska', 'US/Aleutian', 'US/Arizona', 'US/Central', 'US/East-Indiana', 'US/Eastern',
		'US/Hawaii', 'US/Indiana-Starke', 'US/Michigan', 'US/Mountain', 'US/Pacific', 'US/Pacific-New', 'US/Samoa',
		'UTC', 'W-SU', 'WET', 'Zulu'
	];

})(jQuery);

var appView = null;
$(document).ready(function() {
	if (!appView) {
		appView = new AppView();
	}
});
