//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================
(function($) {

	function AdminPage() {
		// Nothing
	}

	window.AdminPage = AdminPage;

	AdminPage.prototype.updateDataObjects = function() {
		showResults('/mudpuppy/updateDataObjects', 'Synchronize DataObjects');
	};

	function showResults(url, title) {
		$.getJSON(url, function(data) {
			var dialog = $('#resultsDialog').clone();
			dialog.find('.modal-title').text(title);
			dialog.find('.modal-body').html(data.output);
			dialog.modal();
		});
	}

})(jQuery);

var adminPage = null;
$(document).ready(function() {
	adminPage = new AdminPage();
});
