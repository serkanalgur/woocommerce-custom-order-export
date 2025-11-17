/**
 * WExport Notification System
 * Provides user-friendly flyout notifications
 */

(function($) {
	'use strict';

	const WExportNotifications = {
		/**
		 * Initialize notification system
		 */
		init: function() {
			this.ensureContainer();
		},

		/**
		 * Ensure notification container exists
		 */
		ensureContainer: function() {
			if ($('.wexport-notification-container').length === 0) {
				$('<div class="wexport-notification-container"></div>').appendTo('body');
			}
		},

		/**
		 * Show notification
		 * @param {string} message - The notification message
		 * @param {string} type - 'success', 'error', 'warning', or 'info' (default: 'info')
		 * @param {number} duration - Auto-dismiss duration in milliseconds (0 = no auto-dismiss)
		 */
		show: function(message, type, duration) {
			type = type || 'info';
			duration = duration !== undefined ? duration : 4000;

			this.ensureContainer();

			const $notification = $(
				'<div class="wexport-notification wexport-notification-' + this.escapeClass(type) + '">' +
				'<div class="wexport-notification-icon"></div>' +
				'<div class="wexport-notification-content">' + this.escapeHtml(message) + '</div>' +
				'<button class="wexport-notification-close"></button>' +
				'</div>'
			);

			const $container = $('.wexport-notification-container');
			$container.append($notification);

			// Handle close button
			$notification.find('.wexport-notification-close').on('click', () => {
				this.dismiss($notification);
			});

			// Auto-dismiss if duration is set
			if (duration > 0) {
				setTimeout(() => {
					this.dismiss($notification);
				}, duration);
			}

			return $notification;
		},

		/**
		 * Show success notification
		 */
		success: function(message, duration) {
			return this.show(message, 'success', duration !== undefined ? duration : 3000);
		},

		/**
		 * Show error notification
		 */
		error: function(message, duration) {
			return this.show(message, 'error', duration !== undefined ? duration : 5000);
		},

		/**
		 * Show warning notification
		 */
		warning: function(message, duration) {
			return this.show(message, 'warning', duration !== undefined ? duration : 4000);
		},

		/**
		 * Show info notification
		 */
		info: function(message, duration) {
			return this.show(message, 'info', duration !== undefined ? duration : 3000);
		},

		/**
		 * Dismiss notification with animation
		 */
		dismiss: function($notification) {
			$notification.addClass('wexport-notification-exit');
			setTimeout(() => {
				$notification.remove();
			}, 300);
		},

		/**
		 * Dismiss all notifications
		 */
		dismissAll: function() {
			$('.wexport-notification').each((index, element) => {
				this.dismiss($(element));
			});
		},

		/**
		 * Escape HTML entities
		 */
		escapeHtml: function(text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text.replace(/[&<>"']/g, (m) => map[m]);
		},

		/**
		 * Escape class name
		 */
		escapeClass: function(name) {
			return name.replace(/[^a-zA-Z0-9-_]/g, '');
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		WExportNotifications.init();
	});

	// Expose globally
	window.WExportNotifications = WExportNotifications;

})(jQuery);
