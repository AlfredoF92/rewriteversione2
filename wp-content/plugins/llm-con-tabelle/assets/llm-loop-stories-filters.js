/**
 * Filtri loop storie: aggiornamento via admin-ajax (render Elementor lato server).
 */
(function () {
	'use strict';

	var AJAX_ACTION = 'llm_hs_loop_fragment';

	function scopeFromSearchParams(params) {
		var v = (params.get('llm_hs_scope') || '').toLowerCase();
		if (!v || v === 'smart') {
			return 'smart';
		}
		return v;
	}

	function catFromSearchParams(params) {
		return (params.get('llm_hs_cat') || '').toLowerCase();
	}

	function applyActive(root) {
		var u = new URL(window.location.href);
		var cat = catFromSearchParams(u.searchParams);
		var scope = scopeFromSearchParams(u.searchParams);

		root.querySelectorAll('a.llm-hs-chip').forEach(function (a) {
			var dc = (a.getAttribute('data-llm-cat') || '').toLowerCase();
			a.classList.toggle('is-active', dc === cat);
		});
		root.querySelectorAll('a.llm-hs-tab').forEach(function (a) {
			var ds = (a.getAttribute('data-llm-scope') || 'smart').toLowerCase();
			a.classList.toggle('is-active', ds === scope);
		});
	}

	function readCatScopeFromLink(href) {
		var u = new URL(href, window.location.origin);
		return {
			cat: u.searchParams.get('llm_hs_cat') || '',
			scope: u.searchParams.get('llm_hs_scope') || '',
		};
	}

	function maybeReinitElementor(target) {
		if (typeof window.jQuery === 'undefined' || typeof window.elementorFrontend === 'undefined') {
			return;
		}
		var $t = window.jQuery(target);
		try {
			if (elementorFrontend.elementsHandler && elementorFrontend.elementsHandler.runReadyTrigger) {
				elementorFrontend.elementsHandler.runReadyTrigger($t);
			}
		} catch (e) {}
		try {
			if (elementorFrontend.hooks) {
				elementorFrontend.hooks.doAction('frontend/element_ready/global', $t);
			}
		} catch (e2) {}
	}

	function getAjaxFilterRoots() {
		return Array.prototype.slice.call(document.querySelectorAll('.llm-hs-filters--ajax'));
	}

	function postFragment(cfg, cat, scope, historyUrl, opts) {
		opts = opts || {};
		var target = document.querySelector(cfg.selector);
		if (!target || !cfg.ajaxUrl || !cfg.nonce || !cfg.postId) {
			if (historyUrl) {
				window.location.href = historyUrl;
			}
			return;
		}

		var roots = getAjaxFilterRoots();
		roots.forEach(function (r) {
			r.classList.add('is-loading');
		});
		target.setAttribute('aria-busy', 'true');

		var fd = new FormData();
		fd.append('action', AJAX_ACTION);
		fd.append('nonce', cfg.nonce);
		fd.append('post_id', String(cfg.postId));
		fd.append('selector', cfg.selector);
		fd.append('cat', cat);
		fd.append('scope', scope);

		window
			.fetch(cfg.ajaxUrl, {
				method: 'POST',
				body: fd,
				credentials: 'same-origin',
			})
			.then(function (res) {
				if (!res.ok) {
					throw new Error('http');
				}
				var ct = res.headers.get('content-type') || '';
				if (ct.indexOf('application/json') === -1) {
					throw new Error('not_json');
				}
				return res.json();
			})
			.then(function (data) {
				if (!data || !data.success || !data.data || typeof data.data.html !== 'string') {
					throw new Error('bad_response');
				}
				target.innerHTML = data.data.html;
				if (!opts.skipPushState && historyUrl && window.history && window.history.pushState) {
					window.history.pushState({ llmHsFilters: 1 }, '', historyUrl);
				}
				getAjaxFilterRoots().forEach(applyActive);
				maybeReinitElementor(target);
				window.dispatchEvent(new CustomEvent('llmLoopStoriesFiltered', { detail: { url: historyUrl } }));
			})
			.catch(function () {
				if (historyUrl) {
					window.location.href = historyUrl;
				} else {
					window.location.reload();
				}
			})
			.finally(function () {
				getAjaxFilterRoots().forEach(function (r) {
					r.classList.remove('is-loading');
				});
				target.removeAttribute('aria-busy');
			});
	}

	function readConfig(root) {
		return {
			ajaxUrl: root.getAttribute('data-llm-ajax-url') || '',
			nonce: root.getAttribute('data-llm-nonce') || '',
			postId: root.getAttribute('data-post-id') || '',
			selector: root.getAttribute('data-loop-target') || '',
		};
	}

	function onClick(e) {
		var a = e.target.closest('.llm-hs-filters--ajax a.llm-hs-chip, .llm-hs-filters--ajax a.llm-hs-tab');
		if (!a) {
			return;
		}
		var root = a.closest('.llm-hs-filters');
		if (!root || !root.classList.contains('llm-hs-filters--ajax')) {
			return;
		}
		var cfg = readConfig(root);
		if (!cfg.selector) {
			return;
		}
		e.preventDefault();
		var qs = readCatScopeFromLink(a.href);
		postFragment(cfg, qs.cat, qs.scope, a.href, {});
	}

	function onPopState() {
		var roots = getAjaxFilterRoots();
		if (!roots.length) {
			return;
		}
		var cfg = readConfig(roots[0]);
		if (!cfg.selector) {
			return;
		}
		var u = new URL(window.location.href);
		postFragment(
			cfg,
			u.searchParams.get('llm_hs_cat') || '',
			u.searchParams.get('llm_hs_scope') || '',
			null,
			{ skipPushState: true }
		);
	}

	function init() {
		getAjaxFilterRoots().forEach(applyActive);
		document.body.addEventListener('click', onClick);
		window.addEventListener('popstate', onPopState);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
