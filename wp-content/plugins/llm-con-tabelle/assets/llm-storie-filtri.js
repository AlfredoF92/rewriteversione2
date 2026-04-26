/**
 * [llm_storie_filtri]: aggiorna il Loop Grid Elementor via fetch + innerHTML swap.
 * Stesso schema di llm-area-personale-filters.js.
 */
(function () {
	'use strict';

	var CAT_KEY   = 'llm_hs_cat';
	var SCOPE_KEY = 'llm_hs_scope';

	/* ── DOM helpers ────────────────────────────────────────── */

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
		/* primo loop-grid che segue root nel DOM */
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
			try {
				jQuery(window).trigger('elementor-pro/loop-builder/after-insert-posts');
			} catch (e) {}
		}
		try {
			document.dispatchEvent(new Event('elementor/lazyload/observe'));
		} catch (e) {}
	}

	/* ── Chip active state ──────────────────────────────────── */

	function syncChips(root) {
		var u = new URL(window.location.href);
		var cat   = (u.searchParams.get(CAT_KEY) || '').toLowerCase();
		var scope = (u.searchParams.get(SCOPE_KEY) || 'smart').toLowerCase();
		if (!scope) scope = 'smart';

		root.querySelectorAll('[data-sf-cat]').forEach(function (btn) {
			var match = (btn.getAttribute('data-sf-cat') || '').toLowerCase() === cat;
			btn.classList.toggle('is-active', match);
			btn.setAttribute('aria-pressed', match ? 'true' : 'false');
		});
		root.querySelectorAll('[data-sf-scope]').forEach(function (btn) {
			var match = (btn.getAttribute('data-sf-scope') || 'smart').toLowerCase() === scope;
			btn.classList.toggle('is-active', match);
			btn.setAttribute('aria-pressed', match ? 'true' : 'false');
		});
	}

	/* ── Fetch + replace ────────────────────────────────────── */

	function replaceWidget(doc, dataId, outer) {
		var sel     = '[data-id="' + dataId + '"] > .elementor-widget-container';
		var fresh   = doc.querySelector(sel);
		if (!fresh) {
			/* fallback: tutto il widget */
			var fresh2 = doc.querySelector('[data-id="' + dataId + '"]');
			if (!fresh2 || !outer) return false;
			var imp2 = document.importNode(fresh2, true);
			outer.parentNode && outer.parentNode.replaceChild(imp2, outer);
			return true;
		}
		var current = outer.querySelector(':scope > .elementor-widget-container') ||
		              outer.querySelector('.elementor-widget-container');
		if (!current) return false;
		var imp = document.importNode(fresh, true);
		outer.replaceChild(imp, current);
		return true;
	}

	function setLoading(root, on) {
		root.classList.toggle('llm-storie-filtri--loading', on);
		root.querySelectorAll('.llm-sf-chip').forEach(function (b) {
			b.disabled = on;
		});
	}

	function applyFilters(root, url, loopWidget, dataId) {
		var outer = getWidgetOuter(loopWidget);
		if (!outer) {
			window.location.assign(url.href);
			return;
		}

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
					window.history.pushState({ llmSf: 1 }, '', url.href);
				}
				syncChips(root);
				runElementorHooks(outer);
				window.dispatchEvent(new CustomEvent('llmStorieFiltratiAjax', { detail: { url: url.href } }));
			})
			.catch(function () {
				window.location.assign(url.href);
			})
			.finally(function () {
				setLoading(root, false);
				outer.removeAttribute('aria-busy');
			});
	}

	function buildUrl(cat, scope) {
		var u = new URL(window.location.href);
		/* rimuovi paginazione Elementor */
		u.searchParams.forEach(function (_, k) {
			if (k.indexOf('e-page-') === 0) u.searchParams.delete(k);
		});
		if (cat) {
			u.searchParams.set(CAT_KEY, cat);
		} else {
			u.searchParams.delete(CAT_KEY);
		}
		if (scope && scope !== 'smart') {
			u.searchParams.set(SCOPE_KEY, scope);
		} else {
			u.searchParams.delete(SCOPE_KEY);
		}
		return u;
	}

	/* ── Bind ────────────────────────────────────────────────── */

	function getCurrentCat(root) {
		var active = root.querySelector('[data-sf-cat].is-active');
		return active ? (active.getAttribute('data-sf-cat') || '') : '';
	}
	function getCurrentScope(root) {
		var active = root.querySelector('[data-sf-scope].is-active');
		return active ? (active.getAttribute('data-sf-scope') || 'smart') : 'smart';
	}

	function initRoot(root) {
		var explicitId = root.getAttribute('data-loop-data-id') || '';
		var loopWidget = findLoopWidget(root, explicitId);
		var dataId     = getDataId(loopWidget);

		if (!loopWidget || !dataId) {
			/* mostra avviso solo in editor/admin */
			if (document.body.classList.contains('elementor-editor-active')) {
				console.warn('[llm_storie_filtri] Loop Grid non trovato. Imposta loop_data_id oppure posiziona il filtro prima del loop.', root);
			}
			return;
		}

		root.querySelectorAll('.llm-sf-chip[data-sf-cat]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var cat   = btn.getAttribute('data-sf-cat') || '';
				var scope = getCurrentScope(root);
				/* aggiorna subito l'active state per feedback visivo */
				root.querySelectorAll('[data-sf-cat]').forEach(function (b) {
					var match = (b.getAttribute('data-sf-cat') || '') === cat;
					b.classList.toggle('is-active', match);
					b.setAttribute('aria-pressed', match ? 'true' : 'false');
				});
				applyFilters(root, buildUrl(cat, scope), loopWidget, dataId);
			});
		});

		root.querySelectorAll('.llm-sf-chip[data-sf-scope]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var scope = btn.getAttribute('data-sf-scope') || 'smart';
				var cat   = getCurrentCat(root);
				root.querySelectorAll('[data-sf-scope]').forEach(function (b) {
					var match = (b.getAttribute('data-sf-scope') || 'smart') === scope;
					b.classList.toggle('is-active', match);
					b.setAttribute('aria-pressed', match ? 'true' : 'false');
				});
				applyFilters(root, buildUrl(cat, scope), loopWidget, dataId);
			});
		});

		window.addEventListener('popstate', function () {
			var u     = new URL(window.location.href);
			var cat   = u.searchParams.get(CAT_KEY) || '';
			var scope = u.searchParams.get(SCOPE_KEY) || 'smart';
			root.querySelectorAll('[data-sf-cat]').forEach(function (b) {
				var match = (b.getAttribute('data-sf-cat') || '') === cat;
				b.classList.toggle('is-active', match);
				b.setAttribute('aria-pressed', match ? 'true' : 'false');
			});
			root.querySelectorAll('[data-sf-scope]').forEach(function (b) {
				var match = (b.getAttribute('data-sf-scope') || 'smart') === scope;
				b.classList.toggle('is-active', match);
				b.setAttribute('aria-pressed', match ? 'true' : 'false');
			});
			/* non ri-fetchiamo sulla navigazione Indietro per non rischiare loop */
		});
	}

	function init() {
		document.querySelectorAll('[data-llm-sf-root]').forEach(initRoot);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
