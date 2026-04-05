(function () {
	'use strict';

	function getConfig() {
		return typeof llmCommunityFeed === 'object' && llmCommunityFeed !== null ? llmCommunityFeed : {};
	}

	function postToggle(activityId, button) {
		var cfg = getConfig();
		if (!cfg.ajaxUrl || !cfg.nonce) {
			return;
		}
		var fd = new FormData();
		fd.append('action', 'llm_community_bravo_toggle');
		fd.append('nonce', cfg.nonce);
		fd.append('activity_id', String(activityId));

		button.setAttribute('aria-busy', 'true');
		button.disabled = true;

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			body: fd,
			credentials: 'same-origin',
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (data) {
				if (!data || !data.success || !data.data) {
					var msg = (data && data.data && data.data.message) || (cfg.i18n && cfg.i18n.error) || 'Errore';
					window.alert(msg);
					return;
				}
				var d = data.data;
				var countEl = button.querySelector('.llm-community-like__count');
				if (countEl) {
					countEl.textContent = String(d.count);
				}
				button.classList.toggle('is-active', !!d.liked);
				button.setAttribute('aria-pressed', d.liked ? 'true' : 'false');
				if (d.liked) {
					button.classList.remove('llm-ui-btn--ghost');
					button.classList.add('llm-ui-btn--primary');
				} else {
					button.classList.add('llm-ui-btn--ghost');
					button.classList.remove('llm-ui-btn--primary');
				}
				var bravoLabel = (cfg.i18n && cfg.i18n.bravoAria) || 'Bravo! — %d ricevuti';
				button.setAttribute(
					'aria-label',
					bravoLabel.replace('%d', String(d.count))
				);
			})
			.catch(function () {
				window.alert((cfg.i18n && cfg.i18n.error) || 'Errore di rete');
			})
			.finally(function () {
				button.removeAttribute('aria-busy');
				button.disabled = false;
			});
	}

	function onClickBravo(e) {
		var btn = e.target.closest('.llm-community-like');
		if (!btn || btn.getAttribute('aria-disabled') === 'true') {
			return;
		}
		if (!btn.matches('button.llm-community-like')) {
			return;
		}
		var id = btn.getAttribute('data-activity-id');
		if (!id) {
			return;
		}
		e.preventDefault();
		postToggle(id, btn);
	}

	function onClickLoadMore(e) {
		var btn = e.target.closest('.llm-community-feed__load-more');
		if (!btn) {
			return;
		}
		var root = btn.closest('.llm-community-feed');
		var list = root && root.querySelector('.llm-community-feed__list');
		var cfg = getConfig();
		if (!root || !list || !cfg.ajaxUrl || !cfg.nonce) {
			return;
		}

		var perPage = parseInt(root.getAttribute('data-per-page'), 10) || 15;
		var page = parseInt(root.getAttribute('data-next-page'), 10);
		if (!page || page < 2) {
			return;
		}

		var loadingText = (cfg.i18n && cfg.i18n.loading) || '…';
		var defaultLabel = (cfg.i18n && cfg.i18n.loadMore) || 'Carica altro';

		btn.setAttribute('aria-busy', 'true');
		btn.disabled = true;
		var prevText = btn.textContent;
		btn.textContent = loadingText;

		var fd = new FormData();
		fd.append('action', 'llm_community_feed_more');
		fd.append('nonce', cfg.nonce);
		fd.append('page', String(page));
		fd.append('per_page', String(perPage));

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			body: fd,
			credentials: 'same-origin',
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (data) {
				if (!data || !data.success || !data.data) {
					window.alert((cfg.i18n && cfg.i18n.error) || 'Errore');
					btn.textContent = prevText;
					return;
				}
				var d = data.data;
				if (d.html) {
					list.insertAdjacentHTML('beforeend', d.html);
				}
				if (d.has_more && d.next_page) {
					root.setAttribute('data-next-page', String(d.next_page));
					root.setAttribute('data-has-more', '1');
				} else {
					root.setAttribute('data-next-page', '0');
					root.setAttribute('data-has-more', '0');
					var wrap = btn.closest('.llm-community-feed__load-wrap');
					if (wrap) {
						wrap.remove();
					}
					return;
				}
				btn.textContent = defaultLabel;
			})
			.catch(function () {
				window.alert((cfg.i18n && cfg.i18n.error) || 'Errore di rete');
				btn.textContent = prevText;
			})
			.finally(function () {
				if (btn.parentNode) {
					btn.setAttribute('aria-busy', 'false');
					btn.disabled = false;
				}
			});
	}

	document.addEventListener('click', function (e) {
		onClickBravo(e);
		onClickLoadMore(e);
	});
})();
