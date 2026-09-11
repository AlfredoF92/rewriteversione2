/**
 * Shortcode [menù] — popup sopra header e Elementor.
 */
(function () {
	'use strict';

	var Z_BACKDROP = '2147483000';
	var Z_POPUP = '2147483001';

	function rootOf(el) {
		return el && el.closest ? el.closest('[data-llm-nav-menu]') : null;
	}

	function popupOf(root) {
		return root ? root.querySelector('.llm-nav-menu__popup') : null;
	}

	function backdropOf(root) {
		return root ? root.querySelector('.llm-nav-menu__backdrop') : null;
	}

	function openMenu(root) {
		if (!root) {
			return;
		}
		closeAll(root);
		var popup = popupOf(root);
		var backdrop = backdropOf(root);
		var btn = root.querySelector('.llm-nav-menu__btn');
		if (!popup) {
			return;
		}
		root.classList.add('is-open');
		popup.hidden = false;
		popup.classList.add('is-front');
		document.body.appendChild(popup);
		popup.style.zIndex = Z_POPUP;
		if (btn) {
			btn.setAttribute('aria-expanded', 'true');
		}
		if (backdrop) {
			backdrop.hidden = false;
			backdrop.classList.add('is-front');
			document.body.appendChild(backdrop);
			backdrop.style.zIndex = Z_BACKDROP;
		}
		document.body.classList.add('llm-nav-menu-open');
		var closeBtn = popup.querySelector('.llm-nav-menu__close');
		if (closeBtn && typeof closeBtn.focus === 'function') {
			closeBtn.focus();
		}
	}

	function closeMenu(root) {
		if (!root) {
			return;
		}
		var popup = document.querySelector('.llm-nav-menu__popup.is-front');
		var backdrop = document.querySelector('.llm-nav-menu__backdrop.is-front');
		var btn = root.querySelector('.llm-nav-menu__btn');
		root.classList.remove('is-open');
		if (popup) {
			popup.hidden = true;
			popup.classList.remove('is-front');
			popup.style.zIndex = '';
			if (root && popup.parentNode !== root) {
				root.appendChild(popup);
			}
		}
		if (backdrop) {
			backdrop.hidden = true;
			backdrop.classList.remove('is-front');
			backdrop.style.zIndex = '';
			if (root && backdrop.parentNode !== root) {
				root.appendChild(backdrop);
			}
		}
		if (btn) {
			btn.setAttribute('aria-expanded', 'false');
		}
		if (!document.querySelector('.llm-nav-menu.is-open')) {
			document.body.classList.remove('llm-nav-menu-open');
		}
	}

	function closeAll(exceptRoot) {
		var roots = document.querySelectorAll('[data-llm-nav-menu].is-open');
		roots.forEach(function (root) {
			if (exceptRoot && root === exceptRoot) {
				return;
			}
			closeMenu(root);
		});
	}

	document.addEventListener('click', function (event) {
		var closeBtn = event.target.closest && event.target.closest('.llm-nav-menu__close');
		if (closeBtn) {
			event.preventDefault();
			var pop = closeBtn.closest('.llm-nav-menu__popup');
			var root = document.querySelector('[data-llm-nav-menu].is-open');
			if (!root && pop) {
				root = document.querySelector('[data-llm-nav-menu] [aria-controls="' + pop.id + '"]');
				root = root ? rootOf(root) : null;
			}
			closeMenu(root);
			return;
		}

		if (event.target.closest && event.target.closest('.llm-nav-menu__backdrop')) {
			var open = document.querySelector('[data-llm-nav-menu].is-open');
			closeMenu(open);
			return;
		}

		if (event.target.closest && event.target.closest('.llm-nav-menu__popup')) {
			return;
		}

		var btn = event.target.closest && event.target.closest('.llm-nav-menu__btn');
		if (btn) {
			event.preventDefault();
			var root = rootOf(btn);
			if (root && root.classList.contains('is-open')) {
				closeMenu(root);
			} else {
				openMenu(root);
			}
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key !== 'Escape') {
			return;
		}
		var open = document.querySelector('[data-llm-nav-menu].is-open');
		if (open) {
			closeMenu(open);
		}
	});

	function expandFullWidth() {
		document.querySelectorAll('[data-llm-nav-menu]').forEach(function (root) {
			var el = root.parentElement;
			var hops = 0;
			while (el && hops < 10) {
				var cls = el.classList;
				if (cls && (cls.contains('e-con') || cls.contains('e-con-inner') || cls.contains('elementor-widget') || cls.contains('elementor-element') || cls.contains('elementor-widget-container') || cls.contains('elementor-widget-wrap'))) {
					el.style.width = '100%';
					el.style.maxWidth = '100%';
					el.style.flexGrow = '1';
					el.style.alignSelf = 'stretch';
				}
				if (cls && (cls.contains('elementor-location-header') || el.tagName === 'HEADER')) {
					break;
				}
				el = el.parentElement;
				hops += 1;
			}
		});
	}

	function browserName() {
		if (window.llmGuestBrowserStore && typeof window.llmGuestBrowserStore.getName === 'function') {
			return window.llmGuestBrowserStore.getName() || '';
		}
		try {
			return (window.localStorage.getItem('llm_guest_display_name') || '').trim();
		} catch (e) {
			return '';
		}
	}

	function syncHello(root) {
		var el = root.querySelector('[data-llm-nav-hello]');
		var nameEl = root.querySelector('[data-llm-nav-hello-name]');
		if (!el || !nameEl) {
			return;
		}
		var isGuest = el.getAttribute('data-guest') === '1';
		var fallback = el.getAttribute('data-fallback') || '';
		var name = isGuest ? browserName() : (el.getAttribute('data-name') || '');
		nameEl.textContent = name || fallback;
	}

	function syncAllHellos() {
		document.querySelectorAll('[data-llm-nav-menu]').forEach(syncHello);
	}

	function boot() {
		expandFullWidth();
		syncAllHellos();
	}

	window.addEventListener('llm-guest-name-changed', syncAllHellos);

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
