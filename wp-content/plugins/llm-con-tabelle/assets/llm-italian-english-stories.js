/**
 * Catalogo [italian-english-stories] — popup al click, sopra header e Elementor.
 */
(function () {
	'use strict';

	var Z_BACKDROP = '2147483000';
	var Z_POPUP = '2147483001';

	function cardById(id) {
		if (!id) {
			return null;
		}
		return document.querySelector('[data-llm-ie-card-id="' + id + '"]');
	}

	function popupOf(card) {
		var id = card.getAttribute('data-llm-ie-card-id');
		if (!id) {
			return card.querySelector('.llm-ie-stories__popup');
		}
		return document.querySelector('.llm-ie-stories__popup[data-llm-ie-for="' + id + '"]') ||
			card.querySelector('.llm-ie-stories__popup');
	}

	function catalogRoot(el) {
		var card = el && el.closest ? el.closest('[data-llm-ie-card]') : null;
		if (card) {
			return card.closest('[data-llm-ie-catalog]');
		}
		return el && el.closest ? el.closest('[data-llm-ie-catalog]') : null;
	}

	function openCard(card) {
		var root = catalogRoot(card);
		if (!root) {
			return;
		}
		closeAll(root, card);
		var popup = popupOf(card);
		var trigger = card.querySelector('.llm-ie-stories__trigger');
		var backdrop = root.querySelector('.llm-ie-stories__backdrop');
		if (!popup) {
			return;
		}
		card.classList.add('is-open');
		root.classList.add('is-popup-open');
		popup.hidden = false;
		popup.classList.add('is-front');
		document.body.appendChild(popup);
		popup.style.zIndex = Z_POPUP;
		placePopup(card, popup);
		bindPlace(card, popup);
		window.requestAnimationFrame(function () {
			placePopup(card, popup);
		});
		if (trigger) {
			trigger.setAttribute('aria-expanded', 'true');
		}
		if (backdrop) {
			backdrop.hidden = false;
			document.body.appendChild(backdrop);
			backdrop.style.zIndex = Z_BACKDROP;
			backdrop.setAttribute('data-llm-ie-backdrop-for', root.getAttribute('data-llm-ie-catalog') || '1');
		}
		document.body.classList.add('llm-ie-popup-open');
		var cta = popup.querySelector('.llm-ie-stories__cta');
		if (cta && typeof cta.focus === 'function') {
			cta.focus();
		}
	}

	var placeCard = null;
	var placePop = null;

	function placePopup(card, popup) {
		var pad = 12;
		var header = document.querySelector('header, .ehf-header, #masthead, .elementor-location-header');
		var headerH = 0;
		if (header) {
			var hr = header.getBoundingClientRect();
			if (hr.bottom > 0 && hr.top < 120) {
				headerH = Math.max(0, hr.bottom);
			}
		}
		popup.style.top = '0px';
		popup.style.left = '0px';
		var popW = popup.offsetWidth;
		var popH = popup.offsetHeight;
		var rect = card.getBoundingClientRect();
		var left = rect.left + (rect.width / 2) - (popW / 2);
		left = Math.max(pad, Math.min(left, window.innerWidth - popW - pad));
		var top = rect.top - 10;
		var minTop = headerH + pad;
		if (top < minTop) {
			top = minTop;
		}
		if (top + popH > window.innerHeight - pad) {
			top = Math.max(minTop, window.innerHeight - popH - pad);
		}
		popup.style.left = Math.round(left) + 'px';
		popup.style.top = Math.round(top) + 'px';
	}

	function onPlaceMove() {
		if (!placeCard || !placePop) {
			return;
		}
		placePopup(placeCard, placePop);
	}

	function bindPlace(card, popup) {
		unbindPlace();
		placeCard = card;
		placePop = popup;
		window.addEventListener('resize', onPlaceMove);
		window.addEventListener('scroll', onPlaceMove, true);
	}

	function unbindPlace() {
		placeCard = null;
		placePop = null;
		window.removeEventListener('resize', onPlaceMove);
		window.removeEventListener('scroll', onPlaceMove, true);
	}

	function closeCard(card) {
		var root = catalogRoot(card);
		var popup = popupOf(card);
		var trigger = card.querySelector('.llm-ie-stories__trigger');
		card.classList.remove('is-open');
		if (popup) {
			unbindPlace();
			popup.hidden = true;
			popup.classList.remove('is-front');
			popup.style.zIndex = '';
			popup.style.top = '';
			popup.style.left = '';
			popup.style.right = '';
			popup.style.bottom = '';
			popup.style.maxHeight = '';
			card.appendChild(popup);
		}
		if (trigger) {
			trigger.setAttribute('aria-expanded', 'false');
			trigger.focus();
		}
		if (root && !root.querySelector('.llm-ie-stories__card.is-open')) {
			root.classList.remove('is-popup-open');
			var backdrop = document.querySelector('.llm-ie-stories__backdrop:not([hidden])') ||
				root.querySelector('.llm-ie-stories__backdrop');
			if (backdrop) {
				backdrop.hidden = true;
				backdrop.style.zIndex = '';
				root.insertBefore(backdrop, root.firstChild);
			}
			document.body.classList.remove('llm-ie-popup-open');
		}
	}

	function closeAll(root, except) {
		var cards = document.querySelectorAll('[data-llm-ie-catalog] .llm-ie-stories__card.is-open');
		for (var i = 0; i < cards.length; i++) {
			if (except && cards[i] === except) {
				continue;
			}
			if (root && !root.contains(cards[i])) {
				continue;
			}
			closeCard(cards[i]);
		}
	}

	function closeFromPopup(popup) {
		var id = popup.getAttribute('data-llm-ie-for');
		var card = cardById(id);
		if (card) {
			closeCard(card);
		}
	}

	function onDocClick(event) {
		var closeBtn = event.target.closest('.llm-ie-stories__popup-close');
		if (closeBtn) {
			var pop = closeBtn.closest('.llm-ie-stories__popup');
			if (pop) {
				event.preventDefault();
				closeFromPopup(pop);
			}
			return;
		}

		if (event.target.closest('.llm-ie-stories__cta')) {
			return;
		}

		if (event.target.closest('.llm-ie-stories__backdrop')) {
			var open = document.querySelector('.llm-ie-stories__card.is-open');
			if (open) {
				closeCard(open);
			}
			return;
		}

		if (event.target.closest('.llm-ie-stories__popup')) {
			return;
		}

		var trigger = event.target.closest('.llm-ie-stories__trigger');
		var card = trigger && trigger.closest('[data-llm-ie-card]');
		if (!card) {
			return;
		}
		event.preventDefault();
		if (card.classList.contains('is-open')) {
			closeCard(card);
		} else {
			openCard(card);
		}
	}

	function onDocKey(event) {
		if (event.key === 'Escape') {
			var open = document.querySelector('.llm-ie-stories__card.is-open');
			if (open) {
				closeCard(open);
			}
			return;
		}
		if (event.key !== 'Enter' && event.key !== ' ') {
			return;
		}
		var trigger = event.target.closest && event.target.closest('.llm-ie-stories__trigger');
		if (!trigger) {
			return;
		}
		var card = trigger.closest('[data-llm-ie-card]');
		if (!card) {
			return;
		}
		event.preventDefault();
		if (card.classList.contains('is-open')) {
			closeCard(card);
		} else {
			openCard(card);
		}
	}

	function boot() {
		if (document.documentElement.getAttribute('data-llm-ie-js') === '1') {
			return;
		}
		document.documentElement.setAttribute('data-llm-ie-js', '1');
		document.addEventListener('click', onDocClick);
		document.addEventListener('keydown', onDocKey);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
