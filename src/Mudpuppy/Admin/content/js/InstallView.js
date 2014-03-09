function InstallView() {
	// Nothing
}

InstallView.prototype.init = function() {
	$('#dbEnabled').change(function() {
		var enabled = $(this).prop('checked');
		var dbFields = $('.dbField');
		dbFields.prop('disabled', !enabled);
		if (!enabled) {
			dbFields.filter('input[type=text], input[type=number]').val('');
			dbFields.filter('input[type=checkbox]').prop('checked', false);
		}
	});

	$('#logToDir').change(function() {
		var enabled = $(this).prop('checked');
		var valueField = $('#logFileDir');
		valueField.prop('disabled', !enabled);
		if (!enabled) {
			valueField.val('');
		}
	});

	$('#adminBasicAuth').change(function() {
		var enabled = $(this).prop('checked');
		var adminFields = $('#adminUser, #adminPass');
		adminFields.prop('disabled', !enabled);
		if (!enabled) {
			adminFields.val('');
		}
	});

	var self = this;
	$('#install').click(function(e) {
		e.preventDefault();
		var disabledFields = $('#installSettings :disabled');
		disabledFields.prop('disabled', false);
		var settings = $('#installSettings').serializeObject({checkboxesAsBools: true});
		disabledFields.prop('disabled', true);
		self.callAction('doInstall', settings, function() {
			document.location.href = '/';
		});
	});
};

InstallView.prototype.callAction = function(action, params, success) {
	$.ajax({
		dataType: 'json',
		url: '/mudpuppy/Install/' + action,
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

var installView = new InstallView();
$(document).ready(function() {
	installView.init();
});