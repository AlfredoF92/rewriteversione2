/*
 * Catalogo avatar: ospite (localStorage) e header [menu].
 */
(function (window, document) {
	'use strict';

	function cfg() {
		return window.llmUserAvatars || { files: [], baseUrl: '', version: '' };
	}

	function url(file) {
		var c = cfg();
		if (!file || !c.baseUrl) {
			return '';
		}
		return String(c.baseUrl).replace(/\/?$/, '/') + file + (c.version ? '?v=' + encodeURIComponent(c.version) : '');
	}

	function applyGuest() {
		var store = window.llmGuestBrowserStore;
		if (!store || typeof store.ensureAvatar !== 'function') {
			return;
		}
		var file = store.ensureAvatar();
		var src = url(file);
		if (!src) {
			return;
		}
		document.querySelectorAll('[data-llm-guest-avatar]').forEach(function (img) {
			img.setAttribute('src', src);
			img.setAttribute('data-llm-scheda-avatar', file);
		});
	}

	function boot() {
		applyGuest();
	}

	window.llmAvatarCatalog = {
		url: url,
		applyGuest: applyGuest
	};

	window.addEventListener('llm-guest-avatar-changed', applyGuest);

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})(window, document);
