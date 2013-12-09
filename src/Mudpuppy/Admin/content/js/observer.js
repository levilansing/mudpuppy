//======================================================================================================================
// This file is part of the Mudpuppy PHP framework, released under the MIT License. See LICENSE for full details.
//======================================================================================================================

(function($) {

	function Observer() {
		this.eventRegistration = {};

		var self = this;
		$(function() {
			self.registerLiveEvents($('body'), 'a,button', 'click');
			self.registerLiveEvents($('body'), 'input,select,textarea', 'change');
		});
	}

	$.observer = new Observer();

	Observer.prototype.triggerEvent = function(event, params) {
		if (event && this.eventRegistration[event]) {
			$.each(this.eventRegistration[event], function() {
				return !this[1].apply(this[0], params);
			});
		}
	};

	Observer.prototype.registerForEvents = function(target, handlerList) {
		var self = this;
		$.each(handlerList, function(event, handler) {
			self.registerForEvent(event, target, handler);
		});
	};

	Observer.prototype.registerForEvent = function(event, target, handler) {
		if (!this.eventRegistration[event]) {
			this.eventRegistration[event] = [];
		}
		this.eventRegistration[event].push([target, handler]);
	};

	Observer.prototype.registerEvents = function(jQueryCollection, jQueryEvent, eventName) {
		if ($.isArray(jQueryEvent)) {
			for (var i = 0; i < jQueryEvent.length; i++) {
				this.registerEvents(jQueryCollection, $.trim(jQueryEvent[i]), eventName);
			}
			return;
		}
		var self = this;
		jQueryCollection[jQueryEvent](function(e) {
			var event = eventName || $(this).attr('href') || $(this).attr('name') || $(this).attr('id');
			if (event && event.charAt(0) == '#') {
				event = event.slice(1);
			}
			if (!event) {
				return;
			}
			event = 'on' + event.charAt(0).toUpperCase() + event.slice(1);
			if (jQueryEvent != 'click') {
				event += jQueryEvent.charAt(0).toUpperCase() + jQueryEvent.slice(1);
			}
			if (self.eventRegistration[event]) {
				var element = this;
				$.each(self.eventRegistration[event], function() {
					// there is an event handler, cancel the native event
					e.preventDefault();
					// if event handler returns true, we should stop propagating
					return !this[1].call(this[0], e, element);
				});
			}
		});
	};

	Observer.prototype.registerLiveEvents = function(jQueryCollection, jQuerySelector, jQueryEvent, eventName) {
		if ($.isArray(jQueryEvent)) {
			for (var i = 0; i < jQueryEvent.length; i++) {
				this.registerEvents(jQueryCollection, $.trim(jQueryEvent[i]), eventName);
			}
			return;
		}
		var self = this;
		jQueryCollection.on(jQueryEvent, jQuerySelector, function(e) {
			var event = eventName || $(this).attr('href') || $(this).attr('name') || $(this).attr('id');
			if (event && event.charAt(0) == '#') {
				event = event.slice(1);
			}
			if (!event) {
				return;
			}
			event = 'on' + event.charAt(0).toUpperCase() + event.slice(1);
			if (jQueryEvent != 'click') {
				event += jQueryEvent.charAt(0).toUpperCase() + jQueryEvent.slice(1);
			}
			if (self.eventRegistration[event]) {
				var element = this;
				$.each(self.eventRegistration[event], function() {
					// there is an event handler, cancel the native event
					e.preventDefault();
					// if event handler returns true, we should stop propagating
					return !this[1].call(this[0], e, element);
				});
			}
		});
	};

	Observer.prototype.unregisterForEvents = function(target) {
		this.eventRegistration = $.map(this.eventRegistration, function(event) {
			var reg = $.map(event, function(registration) {
				if (registration.target == target) {
					return null;
				}
				return registration;
			});
			if (reg.length == 0) {
				return null;
			}
			return reg;
		})
	};
})(jQuery);
