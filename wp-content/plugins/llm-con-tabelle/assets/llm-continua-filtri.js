/**
 * [llm_continua_filtri] — chip-filter per Loop Grid "continua-le-storie".
 */
(function () {
	'use strict';

	var SCOPE_KEY = 'llm_cs_scope';

	/* ── DOM helpers ─────────────────────────────────────────── */

	function findLoopWidget(root, explicitDataId) {
		if (explicitDataId) {
			var el =
				document.querySelector('.elementor-widget-loop-grid[data-id="' + explicitDataId + '"]') ||
				document.querySelector('[data-id="' + explicitDataId + '"]');
			if (el) {
				return el.classList.contains('elementor-widget-loop-grid')
					? el
					: el.querySelector('.elementor-widget-loop-grid') || el;
			}
			return null;
		}
		var all = document.querySelectorAll('.elementor-widget-loop-grid');
		for (var i = 0; i < all.length; i++) {
			if (root.compareDocumentPosition(all[i]) & Node.DOCUMENT_POSITION_FOLLOWING) {
				return all[i];
			}
		}
		return null;
	}

	function getDataId(loopWidget) {
		if (!loopWidget) return '';
		var id = loopWidget.getAttribute('data-id');
		if (id) return id;
		var w = loopWidget.closest('.elementor-widget[data-id]');
		return w ? w.getAttribute('data-id') || '' : '';
	}

	function getWidgetOuter(loopWidget) {
		if (!loopWidget) return null;
		if (loopWidget.getAttribute('data-id') && loopWidget.classList.contains('elementor-widget')) {
			return loopWidget;
		}
		return loopWidget.closest('.elementor-widget[data-id]') || loopWidget;
	}

	/* ── Elementor re-init ──────────────────────────────────── */

	function runElementorHooks(widget) {
		if (typeof elementorFrontend !== 'undefined') {
			try {
				if (elementorFrontend.elementsHandler && elementorFrontend.elementsHandler.runReadyTrigger) {
					elementorFrontend.elementsHandler.runReadyTrigger(widget);
				}
			} catch (e) {}
		}
		if (typeof jQuery !== 'undefined') {
			try { jQuery(window).trigger('elementor-pro/loop-builder/after-insert-posts'); } catch (e) {}
		}
		try { document.dispatchEvent(new Event('elementor/lazyload/observe')); } catch (e) {}
	}

	/* ── Stato chip ─────────────────────────────────────────── */

	function syncChips(root) {
		var u     = new URL(window.location.href);
		var scope = u.searchParams.get(SCOPE_KEY) || '';
		root.querySelectorAll('[data-cf-scope]').forEach(function (btn) {
			var match = (btn.getAttribute('data-cf-scope') || '') === scope;
			btn.classList.toggle('is-active', match);
			btn.setAttribute('aria-pressed', match ? 'true' : 'false');
		});
	}

	/* ── Fetch + replace ────────────────────────────────────── */

	function replaceWidget(doc, dataId, outer) {
		var sel     = '[data-id="' + dataId + '"] > .elementor-widget-container';
		var fresh   = doc.querySelector(sel);
		if (!fresh) {
			var fresh2 = doc.querySelector('[data-id="' + dataId + '"]');
			if (!fresh2 || !outer) return false;
			outer.parentNode && outer.parentNode.replaceChild(document.importNode(fresh2, true), outer);
			return true;
		}
		var current = outer.querySelector(':scope > .elementor-widget-container') ||
		              outer.querySelector('.elementor-widget-container');
		if (!current) return false;
		outer.replaceChild(document.importNode(fresh, true), current);
		return true;
	}

	function setLoading(root, on) {
		root.classList.toggle('llm-continua-filtri--loading', on);
		root.querySelectorAll('.llm-cf-chip').forEach(function (b) { b.disabled = on; });
	}

	function applyFilter(root, url, loopWidget, dataId) {
		var outer = getWidgetOuter(loopWidget);
		if (!outer) { window.location.assign(url.href); return; }

		setLoading(root, true);
		outer.setAttribute('aria-busy', 'true');

		fetch(url.href, {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			cache: 'no-store',
		})
			.then(function (res) {
				if (!res.ok) throw new Error('HTTP ' + res.status);
				return res.text();
			})
			.then(function (html) {
				var doc = new DOMParser().parseFromString(html, 'text/html');
				if (!replaceWidget(doc, dataId, outer)) {
					window.location.assign(url.href);
					return;
				}
				if (window.history && window.history.pushState) {
					window.history.pushState({ llmCf: 1 }, '', url.href);
				}
				syncChips(root);
				runElementorHooks(outer);
				window.dispatchEvent(new CustomEvent('llmContinuaFiltratoAjax', { detail: { url: url.href } }));
			})
			.catch(function () { window.location.assign(url.href); })
			.finally(function () {
				setLoading(root, false);
				outer.removeAttribute('aria-busy');
			});
	}

	function buildUrl(scope) {
		var u = new URL(window.location.href);
		u.searchParams.forEach(function (_, k) {
			if (k.indexOf('e-page-') === 0) u.searchParams.delete(k);
		});
		if (scope) {
			u.searchParams.set(SCOPE_KEY, scope);
		} else {
			u.searchParams.delete(SCOPE_KEY);
		}
		return u;
	}

	/* ── Bind ────────────────────────────────────────────────── */

	function initRoot(root) {
		var explicitId = root.getAttribute('data-loop-data-id') || '';
		var loopWidget = findLoopWidget(root, explicitId);
		var dataId     = getDataId(loopWidget);
		if (!loopWidget || !dataId) {
			if (document.body.classList.contains('elementor-editor-active')) {
				console.warn('[llm_continua_filtri] Loop Grid non trovato.', root);
			}
			return;
		}

		root.querySelectorAll('.llm-cf-chip[data-cf-scope]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var scope = btn.getAttribute('data-cf-scope') || '';
				/* feedback visivo immediato */
				root.querySelectorAll('[data-cf-scope]').forEach(function (b) {
					var match = (b.getAttribute('data-cf-scope') || '') === scope;
					b.classList.toggle('is-active', match);
					b.setAttribute('aria-pressed', match ? 'true' : 'false');
				});
				applyFilter(root, buildUrl(scope), loopWidget, dataId);
			});
		});

		window.addEventListener('popstate', function () {
			syncChips(root);
		});
	}

	function init() {
		document.querySelectorAll('[data-llm-cf-root]').forEach(initRoot);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
