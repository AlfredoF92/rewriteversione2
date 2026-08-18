/**
 * Pulsanti lingua [llm_riviste_indice]: ricarica le card via JSON.
 */
(function () {
	'use strict';

	function esc(value) {
		return String(value == null ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function coverStyle(url) {
		if (!url) {
			return '';
		}
		return ' style="background-image:url(\'' + esc(url) + '\');"';
	}

	function cardHtml(card) {
		var url = card && card.url ? String(card.url) : '';
		var title = card && card.title ? String(card.title) : '';
		var cover = card && card.cover ? String(card.cover) : '';
		var date = card && card.date ? String(card.date) : '';
		var dateLabel = card && card.dateLabel ? String(card.dateLabel) : '';
		var learn = card && card.learn ? String(card.learn) : '';
		var kicker = card && card.kicker ? String(card.kicker) : '';
		var contents = card && card.contents ? String(card.contents) : '';
		var knownFlag = card && card.knownFlag ? String(card.knownFlag) : '';
		var targetFlag = card && card.targetFlag ? String(card.targetFlag) : '';
		var tag = url ? 'a' : 'div';
		var attrs = url ? ' href="' + esc(url) + '"' : ' role="group"';
		var coverClass = 'llm-mag-index__cover' + (cover ? '' : ' llm-mag-index__cover--empty');
		var html = '<article class="llm-mag-index__card">';
		html += '<' + tag + ' class="llm-mag-index__card-link"' + attrs + '>';
		html += '<div class="' + coverClass + '"' + coverStyle(cover) + ' role="img" aria-label="' + esc(title || 'Copertina rivista') + '">';
		html += '<span class="llm-mag-index__flags" aria-hidden="true">';
		html += '<span class="llm-mag-index__flag">' + esc(knownFlag) + '</span>';
		html += '<span class="llm-mag-index__flags-arrow">→</span>';
		html += '<span class="llm-mag-index__flag">' + esc(targetFlag) + '</span>';
		html += '</span></div><div class="llm-mag-index__body">';
		if (learn) {
			html += '<p class="llm-mag-index__learn">' + esc(learn) + '</p>';
		}
		if (kicker) {
			html += '<p class="llm-mag-index__kicker">' + esc(kicker) + '</p>';
		}
		if (title) {
			html += '<h3 class="llm-mag-index__title">' + esc(title) + '</h3>';
		}
		if (dateLabel) {
			html += '<p class="llm-mag-index__date"><time datetime="' + esc(date) + '">' + esc(dateLabel) + '</time></p>';
		}
		if (contents) {
			html += '<p class="llm-mag-index__contents">' + esc(contents) + '</p>';
		}
		html += '</div></' + tag + '></article>';
		return html;
	}

	function renderPayload(grid, payload) {
		var cards = payload && Array.isArray(payload.cards) ? payload.cards : [];
		if (!cards.length) {
			grid.innerHTML = '<p class="llm-mag-index__empty">' + esc(payload && payload.empty ? payload.empty : '') + '</p>';
			return;
		}
		var html = '';
		for (var i = 0; i < cards.length; i++) {
			html += cardHtml(cards[i]);
		}
		grid.innerHTML = html;
	}

	function setActive(root, known) {
		root.setAttribute('data-known', known);
		var buttons = root.querySelectorAll('.llm-mag-index__lang');
		for (var i = 0; i < buttons.length; i++) {
			var active = buttons[i].getAttribute('data-known') === known;
			buttons[i].classList.toggle('is-active', active);
			buttons[i].setAttribute('aria-pressed', active ? 'true' : 'false');
		}
	}

	function loadCards(root, known) {
		var grid = root.querySelector('[data-llm-mag-grid]');
		if (!grid) {
			return;
		}
		if (root._llmMagCache && root._llmMagCache[known]) {
			renderPayload(grid, root._llmMagCache[known]);
			setActive(root, known);
			return;
		}

		var ajaxUrl = root.getAttribute('data-ajax-url') || '';
		var action = root.getAttribute('data-action') || '';
		var nonce = root.getAttribute('data-nonce') || '';
		if (!ajaxUrl || !action) {
			return;
		}

		root.classList.add('is-loading');
		var body = new URLSearchParams();
		body.set('action', action);
		body.set('nonce', nonce);
		body.set('known', known);

		fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (json) {
				if (!json || !json.success || !json.data) {
					throw new Error('bad json');
				}
				if (!root._llmMagCache) {
					root._llmMagCache = {};
				}
				root._llmMagCache[known] = json.data;
				if (root.getAttribute('data-pending-known') === known || root.getAttribute('data-known') === known) {
					renderPayload(grid, json.data);
					setActive(root, known);
				}
			})
			.catch(function () {
				grid.innerHTML = '<p class="llm-mag-index__empty">Impossibile caricare le riviste.</p>';
			})
			.then(function () {
				root.classList.remove('is-loading');
				root.removeAttribute('data-pending-known');
			});
	}

	function init(root) {
		if (root.getAttribute('data-llm-mag-ready') === '1') {
			return;
		}
		root.setAttribute('data-llm-mag-ready', '1');
		root.addEventListener('click', function (event) {
			var btn = event.target.closest('.llm-mag-index__lang');
			if (!btn || !root.contains(btn)) {
				return;
			}
			var known = btn.getAttribute('data-known') || '';
			if (!known || known === root.getAttribute('data-known')) {
				return;
			}
			root.setAttribute('data-pending-known', known);
			setActive(root, known);
			loadCards(root, known);
		});
	}

	function boot() {
		var roots = document.querySelectorAll('[data-llm-mag-index]');
		for (var i = 0; i < roots.length; i++) {
			init(roots[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
