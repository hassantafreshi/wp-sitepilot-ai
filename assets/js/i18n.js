/**
 * SitePilot AI — JavaScript i18n bridge.
 *
 * Provides a single sp_i18n() function that resolves keys from
 * the centralized Phrases::all() array.
 *
 * Strategy (priority order):
 *  1. WordPress 7.0+  → wp.i18n.__()  (native)
 *  2. Fallback         → spI18n.strings (server-localized object)
 *  3. Bare key         → returned as-is  (if not found)
 *
 * Usage:
 *   sp_i18n('general.no_permission')
 *   sp_i18n('widget.greeting')
 *   sp_i18n('woocommerce.not_active')
 *
 * @package SitePilot\AI
 */

(function () {
	'use strict';

	var dict = {};
	var domain = 'sitepilot-ai';

	// Try to read the server-localized data first.
	try {
		if (typeof spI18n !== 'undefined' && spI18n.strings) {
			dict = spI18n.strings;
			domain = spI18n.domain || domain;
		}
	} catch (e) {
		// ignore — fallback to bare key
	}

	/**
	 * Translate a dot-path key to a localized string.
	 *
	 * @param {string}  key   Dot-path e.g. 'widget.greeting'
	 * @param {Object=} data  Optional sprintf-style replacements {name: '…'}
	 * @return {string}
	 */
	window.sp_i18n = function (key, data) {
		var text;

		// 1. WordPress 7.0+ native i18n.
		if (typeof wp !== 'undefined' && wp.i18n && wp.i18n.__) {
			// Look up the English source from dict.
			var source = dict[key] || key;
			text = wp.i18n.__(source, domain);
			if (text !== source) {
				// Translation was found.
				return replaceArgs(text, data);
			}
		}

		// 2. Fallback to server-side dictionary.
		if (dict[key]) {
			return replaceArgs(dict[key], data);
		}

		// 3. Return the key itself.
		return replaceArgs(key, data);
	};

	/**
	 * Replace {placeholder} tokens in a string.
	 *
	 * @param {string} text
	 * @param {Object=} data
	 * @return {string}
	 */
	function replaceArgs(text, data) {
		if (!data) {
			return text;
		}
		return text.replace(/\{(\w+)\}/g, function (match, key) {
			return data[key] !== undefined ? String(data[key]) : match;
		});
	}
})();
