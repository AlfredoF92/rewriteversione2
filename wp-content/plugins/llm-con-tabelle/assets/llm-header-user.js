/* llm-header-user.js — Ciao+nome da localStorage e bandiera lingua UI. */
(function () {
	'use strict';

	var cfg = window.llmHeaderUser || {};

	function readBrowserLang() {
		try {
			return (window.localStorage.getItem('llm_interface_lang') || '').toLowerCase();
		} catch (e) {
			return '';
		}
	}

	function readBrowserName() {
		if (window.llmGuestBrowserStore && typeof window.llmGuestBrowserStore.getName === 'function') {
			return window.llmGuestBrowserStore.getName() || '';
		}
		try {
			return (window.localStorage.getItem('llm_guest_display_name') || '').trim();
		} catch (e) {
			return '';
		}
	}

	function greetingTplFor(lang) {
		var map = cfg.greetings || {};
		if (cfg.greetingTpl && (!lang || lang === cfg.uiLang)) {
			return cfg.greetingTpl;
		}
		return map[lang] || map.it || 'Ciao, %s';
	}

	function guestLabelFor(lang) {
		var map = cfg.guestLabels || {};
		if (cfg.guestLabel && (!lang || lang === cfg.uiLang)) {
			return cfg.guestLabel;
		}
		return map[lang] || map.it || 'Accedi';
	}

	function browserUserLabelFor(lang) {
		var map = cfg.browserUserLabels || {};
		if (cfg.browserUserLabel && (!lang || lang === cfg.uiLang)) {
			return cfg.browserUserLabel;
		}
		return map[lang] || map.it || 'Utente browser';
	}

	function syncRoot(root) {
		var textEl = root.querySelector('[data-llm-header-user-text]');
		var flagEl = root.querySelector('[data-llm-header-user-flag]');
		var tagEl = root.querySelector('[data-llm-header-user-browser-tag]');
		if (!textEl) {
			return;
		}

		var lang = readBrowserLang() || cfg.uiLang || 'it';
		var flagMap = cfg.flagMap || {};
		if (flagEl) {
			var flag = flagMap[lang] || '';
			if (flag) {
				flagEl.textContent = flag;
				flagEl.hidden = false;
			}
		}

		var browserName = readBrowserName();
		var isGuest = root.getAttribute('data-is-guest') === '1' || !!cfg.isGuest;

		if (isGuest) {
			if (browserName) {
				textEl.textContent = greetingTplFor(lang).replace('%s', browserName);
				if (tagEl) {
					tagEl.textContent = browserUserLabelFor(lang);
					tagEl.hidden = false;
				}
			} else {
				textEl.textContent = guestLabelFor(lang);
				if (tagEl) {
					tagEl.hidden = true;
				}
			}
			return;
		}

		if (tagEl) {
			tagEl.hidden = true;
		}

		var name = browserName || cfg.displayName || '';
		if (name) {
			textEl.textContent = greetingTplFor(lang).replace('%s', name);
		}
	}

	function init() {
		document.querySelectorAll('[data-llm-header-user]').forEach(syncRoot);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
