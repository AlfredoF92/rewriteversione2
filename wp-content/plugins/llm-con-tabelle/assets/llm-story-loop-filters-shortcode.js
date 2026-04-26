/**
 * Shortcode [llm_story_loop_filters]: aggiorna elenco via admin-ajax.
 */
(function () {
	'use strict';

	function syncBrowserUrl(cat, scope, sync) {
		if (!sync) {
			return;
		}
		var url = new URL(window.location.href);
		if (cat) {
			url.searchParams.set('llm_hs_cat', cat);
		} else {
			url.searchParams.delete('llm_hs_cat');
		}
		if (scope && scope !== 'smart') {
			url.searchParams.set('llm_hs_scope', scope);
		} else {
			url.searchParams.delete('llm_hs_scope');
		}
		var next = url.pathname + url.search + url.hash;
		if (window.history && window.history.replaceState) {
			window.history.replaceState({ llmStoryLoopSc: 1 }, '', next);
		}
	}

	function fetchList(root) {
		if (typeof window.llmStoryLoopFilters === 'undefined' || !window.llmStoryLoopFilters.ajaxUrl) {
			return;
		}
		var cat = root.querySelector('.llm-sl-cat');
		var scopeEl = root.querySelector('.llm-sl-scope');
		var results = root.querySelector('.llm-sl-results');
		if (!cat || !scopeEl || !results) {
			return;
		}

		var sync = root.getAttribute('data-sync-url') === '1';
		var catVal = cat.value || '';
		var scopeVal = scopeEl.value || 'smart';

		root.classList.add('is-loading');
		results.setAttribute('aria-busy', 'true');

		var fd = new FormData();
		fd.append('action', 'llm_story_loop_filters');
		fd.append('nonce', root.getAttribute('data-nonce') || '');
		fd.append('query_id', root.getAttribute('data-query-id') || '');
		fd.append('posts_per_page', root.getAttribute('data-posts-per-page') || '12');
		fd.append('cat', catVal);
		fd.append('scope', scopeVal);

		window
			.fetch(window.llmStoryLoopFilters.ajaxUrl, {
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
					throw new Error('bad');
				}
				results.innerHTML = data.data.html;
				syncBrowserUrl(catVal, scopeVal, sync);
			})
			.catch(function () {
				results.textContent = '';
				var p = document.createElement('p');
				p.className = 'llm-sl-msg llm-sl-msg--error';
				p.textContent =
					window.llmStoryLoopFilters && window.llmStoryLoopFilters.errMsg
						? window.llmStoryLoopFilters.errMsg
						: 'Aggiornamento non riuscito.';
				results.appendChild(p);
			})
			.finally(function () {
				root.classList.remove('is-loading');
				results.removeAttribute('aria-busy');
			});
	}

	function bind(root) {
		var cat = root.querySelector('.llm-sl-cat');
		var scopeEl = root.querySelector('.llm-sl-scope');
		if (!cat) {
			return;
		}
		cat.addEventListener('change', function () {
			fetchList(root);
		});
		if (scopeEl) {
			scopeEl.addEventListener('change', function () {
				fetchList(root);
			});
		}
	}

	function init() {
		document.querySelectorAll('[data-llm-sl-root]').forEach(bind);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
