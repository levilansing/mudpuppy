//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================
(function($) {

	function DebugLog() {
		this.logs = {};
		this.logList = [];
		this.paused = false;

		/** @var XMLHttpRequest this.request */
		this.request = null;
		this.lastId = null;
		this.logTemplate = $('#logTemplate').attr('id', null);

		this.loading = true;
		this.pull();
		this.updateInterval = setInterval($.proxy(this.update, this), 15000);

		if (notify.permissionLevel() == notify.PERMISSION_GRANTED) {
			$("#allowNotifications").remove();
		}
	}

	window.DebugLog = DebugLog;

	DebugLog.prototype.requestNotifications = function() {
		notify.requestPermission();
	};

	DebugLog.prototype.sendNotification = function(title, body, closeAfter) {
		var notification = notify.createNotification(title, {
			body: body,
			icon: '/mudpuppy/admin/images/Error.ico'
		});
		if (closeAfter) {
			setTimeout(function() {
				notification.close();
			}, closeAfter);
		}
	};

	DebugLog.prototype.pull = function() {
		var self = this;
		$.ajax({
			type: 'GET',
			url: '/mudpuppy/Log/pull',
			data: {lastId: this.lastId},
			success: function(data, textStatus, jqXHR) {
				self.receive(data, textStatus, jqXHR);
				self.longPull();
			},
			error: function() {
				self.longPull();
			}
		});
		this.update();
	};

	DebugLog.prototype.longPull = function() {
		if (this.request && this.request.readyState != 4) {
			this.request.abort();
		}
		if (this.paused) {
			return;
		}

		clearInterval(this.updateInterval);
		this.updateInterval = setInterval($.proxy(this.update, this), 15000);

		var self = this;
		this.request = $.ajax({
			type: 'GET',
			url: '/mudpuppy/Log/waitForNext',
			data: {lastId: this.lastId, t: new Date().getTime()},
			dataType: 'json',
			success: function(data, textStatus, jqXHR) {
				self.receive(data, textStatus, jqXHR);
				if (jqXHR == self.request) {
					self.request = null;
					if (data && data.length !== undefined) {
						self.longPull()
					}
				}

			},
			error: function(jqXHR, textStatus, errorThrown) {
				if (jqXHR == self.request) {
					self.request = null;
				}
			}
		})
	};

	DebugLog.prototype.pause = function() {
		this.paused = true;
		$('a#pauseButton').hide();
		$('a#resumeButton').show();
		if (this.request) {
			this.request.abort();
		}
	};

	DebugLog.prototype.resume = function() {
		this.paused = false;
		$('a#pauseButton').show();
		$('a#resumeButton').hide();
		this.longPull();
	};

	DebugLog.prototype.clearLog = function(andDelete) {
		$('#logList').empty();
		this.logs = {};
		this.logList = [];

		if (andDelete) {
			$.ajax({
				type: 'GET',
				url: '/mudpuppy/Log/clearLog',
				data: null
			});
		}
	};

	DebugLog.prototype.receive = function(data, textStatus, jqXHR) {
		for (var i = 0; i < data.length; i++) {
			var log = data[i];
			if (!this.logs[log.id]) {
				if (this.lastId == null || log.id > this.lastId) {
					this.lastId = log.id;
				}
				this.logs[log.id] = log;
				this.logList.push(log);
				this.displayLog(log);
			}
		}
		this.loading = false;
	};

	DebugLog.prototype.update = function() {
		var now = new Date();
		var self = this;
		$('#debugLog .logHeader td:nth-child(4)').each(function(index, td) {
			var $td = $(td);
			var id = $td.parents('.panel-toggle').attr('href').substr(4);
			var time = getTimeDifference(new Date(self.logs[id].date), now);
			if (time != $td.text()) {
				$td.text(time);
			}
		});

		if (!this.paused && (!this.request || this.request.readyState == 4)) {
			this.longPull();
		}
	};

	DebugLog.prototype.displayLog = function(log) {
		var container = this.logTemplate.clone();
		container.find('.panel-toggle').attr('href', '#log' + log.id);
		container.find('.panel-body').attr('id', 'log' + log.id);

		// generate header
		var header = container.find('.logHeader td');
		var nErrors = (log.errors ? log.errors.length : 0) || 0;
		if (nErrors) {
			$(header.get(0)).html('<span class="badge badge-danger">' + nErrors + '</span>');
			if (!this.loading) {
				this.sendNotification('Mudpuppy Error', nErrors + ' Error(s) logged in your request', 8000);
			}
		} else {
			$(header.get(0)).html('<span class="label label-success">OK</span>');
		}
		$(header.get(1)).html(labelIt(log.responseCode, 400, 500, '', ''));
		$(header.get(2)).text(log.requestMethod);
		$(header.get(3)).text(getTimeDifference(new Date(log.date), new Date()));
		$(header.get(4)).text(log.requestPath || '/');

		var nQueries = (log.queries && log.queries.queries ? log.queries.queries.length : 0);
		$(header.get(5)).html(labelIt(nQueries, 25, 50, ' queries', ' query'));

		var time = parseFloat(log.executionTime).toFixed(4) + 's';
		$(header.get(6)).html(labelIt(time, 0.2, 0.5, '', ''));

		var nLogs = (log.log && log.log.length) || 0;

		// fill in request data
		var tabs = container.find('.nav-tabs li a');
		$(tabs[0]).attr('href', '#t0' + log.id);
		$(tabs[1]).attr('href', '#t1' + log.id).find('div').text(nErrors);
		$(tabs[2]).attr('href', '#t2' + log.id).find('div').text(nLogs);
		$(tabs[3]).attr('href', '#t3' + log.id).find('div').text(nQueries);

		var content = container.find('.tab-content > div');
		$(content[0]).attr('id', 't0' + log.id).html('<pre class="prettyprint">' + JSON.stringify(log.request, undefined,
			2) + '</pre>');
		$(content[1]).attr('id', 't1' + log.id).append(errorsToList(log.errors));
		$(content[2]).attr('id', 't2' + log.id).append(logsToList(log));
		$(content[3]).attr('id', 't3' + log.id).html(queriesToList(log.queries));

		var selectedTab = 0
		if (nErrors) {
			selectedTab = 1;
		} else if (nLogs) {
			selectedTab = 2;
		}
		$(tabs[selectedTab]).parent().add(content[selectedTab]).addClass('active');

		$('#logList').prepend(container.children());
		// keep only 100 visible
		var logs = $('#logList > div');
		for (var i = 100; i < logs.length; i++) {
			logs[i].remove();
		}
		window.prettyPrint && prettyPrint();
		// we don't update these elements, so no need to re-prettify them
		$('.prettyprint').removeClass('prettyprint');
	};

	function labelIt(value, warning, error, postfix, singularPostfix) {
		if (value == 1 && singularPostfix !== undefined) {
			postfix = singularPostfix;
		}

		if (value >= error) {
			return '<span class="label label-danger">' + value + postfix + '</span>';
		}
		if (value >= warning) {
			return '<span class="label label-warning">' + value + postfix + '</span>';
		}
		return value + postfix;
	}

	function logsToList(log) {
		if (!log || !log.log) {
			return '';
		}

		var logs = log.log;
		var ol = $('<ol></ol>');
		for (var i = 0; i < logs.length; i++) {
			var item = logs[i];
			var time = item.time;
			ol.append($('<li></li>').text(parseFloat(time).toFixed(4) + 's: ' + item.title));
		}

		// log summary
		var date = new Date(log.date);
		var summary = 'Date: ' + date.toString() + '<br />' + 'Execution Time: ' + parseFloat(log.executionTime).toFixed(4) + 's' + '<br />' + 'Peak Memory Usage: ' + formatMemory(log.memoryUsage);
		var p = $('<p></p>').html(summary);

		return [p, ol];
	}

	function errorsToList(errors) {
		if (!errors || !errors.length) {
			return 'No Errors';
		}

		var ol = $('<ol></ol>');
		for (var i = 0; i < errors.length; i++) {
			var item = errors[i];
			var time = item.time;
			ol.append($('<li></li>').append($('<pre class="prettyprint lang-log"/>').text(parseFloat(time).toFixed(4) + 's: ' + item.error)));
		}
		return ol;
	}

	function queriesToList(sql) {
		if (!sql || !sql.queries) {
			return '';
		}

		var ol = $('<ol></ol>');
		var duration = 0;
		for (var i = 0; i < sql.queries.length; i++) {
			var item = sql.queries[i];
			var dt = item.etime - item.stime;
			var time = parseFloat(item.stime).toFixed(4) + 's (' + parseFloat(dt).toFixed(4) + 's)';
			duration += dt;
			ol.append($('<li></li>').append($('<pre class="prettyprint lang-sql"/>').text(time + ': ' + item.query + (item.error ? '\nERROR: ' + item.error : ''))));
		}

		// summary
		var p = $('<p></p>').text("Executed " + (sql.queries.length == 1 ? '1 query' : sql.queries.length + ' queries') + " in " + parseFloat(duration).toFixed(4) + 's with ' + (sql.errors == 1 ? '1 error' : sql.errors + ' errors'));
		return [p, ol];
	}

	function getTimeDifference(date1, date2) {
		var td = Math.round(date2 - date1) / 1000;
		var t = {};
		if (td < 90) {
			t.units = ['second', 'seconds'];
			t.value = Math.floor(td);
		} else if (td < 60 * 60) {
			t.units = ['minute', 'minutes'];
			t.value = Math.floor(td / 60);
		} else if (td < 60 * 60 * 24) {
			t.units = ['hour', 'hours'];
			t.value = Math.floor(td / 60 / 60);
		} else {
			t.units = ['day', 'days'];
			t.value = Math.floor(td / 60 / 60 / 24);
		}
		return t.value + ' ' + (t.value == 1 ? t.units[0] : t.units[1]) + ' ago';
	}

	function formatMemory(mem) {
		var u = ['B', 'KB', 'MB', 'GB', 'TB'];
		var i = 0;
		while (mem > 1024) {
			mem = mem / 1024;
			i++;
		}
		return parseFloat(mem).toFixed(2) + ' ' + u[i];
	}

})(jQuery);

var debugLog = null;
$(document).ready(function() {
	debugLog = new DebugLog();
});
