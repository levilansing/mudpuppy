/**
 * Author: Levi
 * Created: 7/8/13
 */
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
})
