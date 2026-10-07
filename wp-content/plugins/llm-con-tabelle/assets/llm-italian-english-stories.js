/**
 * Catalogo [italian-english-stories] — popup al click, sopra header e Elementor.
 */
(function () {
	'use strict';

	var Z_BACKDROP = '2147483000';
	var Z_POPUP = '2147483001';
	var HOVER_OPEN_MS = 500;
	var HOVER_CLOSE_MS = 180;
	var FADE_MS = 240;
	var openedByHover = false;
	var hoverTimer = null;
	var closeHoverTimer = null;
	var fadeTimer = null;
	var hoverCard = null;

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

	function openCard(card, opts) {
		var root = catalogRoot(card);
		if (!root) {
			return;
		}
		opts = opts || {};
		closeAll(root, card);
		var popup = popupOf(card);
		var trigger = card.querySelector('.llm-ie-stories__trigger');
		var backdrop = document.querySelector('.llm-ie-stories__backdrop') ||
			root.querySelector('.llm-ie-stories__backdrop');
		if (!popup) {
			return;
		}
		var stray = document.querySelectorAll('.llm-ie-stories__popup.is-front');
		for (var s = 0; s < stray.length; s++) {
			if (stray[s] === popup) {
				continue;
			}
			resetPopup(stray[s], cardById(stray[s].getAttribute('data-llm-ie-for')));
		}
		openedByHover = !!opts.fromHover;
		clearFadeTimer();
		card.classList.add('is-open');
		root.classList.add('is-popup-open');
		popup.hidden = false;
		popup.classList.add('is-front');
		popup.classList.remove('is-visible');
		document.body.appendChild(popup);
		popup.style.zIndex = Z_POPUP;
		placePopup(card, popup);
		bindPlace(card, popup);
		window.requestAnimationFrame(function () {
			placePopup(card, popup);
			window.requestAnimationFrame(function () {
				popup.classList.add('is-visible');
				if (backdrop) {
					backdrop.classList.add('is-visible');
				}
			});
		});
		if (trigger) {
			trigger.setAttribute('aria-expanded', 'true');
		}
		if (backdrop) {
			backdrop.hidden = false;
			backdrop.classList.remove('is-visible');
			document.body.appendChild(backdrop);
			backdrop.style.zIndex = Z_BACKDROP;
			backdrop.setAttribute('data-llm-ie-backdrop-for', root.getAttribute('data-llm-ie-catalog') || '1');
		}
		document.body.classList.add('llm-ie-popup-open');
		if (!openedByHover) {
			var cta = popup.querySelector('.llm-ie-stories__cta');
			if (cta && typeof cta.focus === 'function') {
				cta.focus();
			}
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

	function clearFadeTimer() {
		if (fadeTimer) {
			window.clearTimeout(fadeTimer);
			fadeTimer = null;
		}
	}

	function resetPopup(popup, card) {
		unbindPlace();
		popup.hidden = true;
		popup.classList.remove('is-front');
		popup.classList.remove('is-visible');
		popup.style.zIndex = '';
		popup.style.top = '';
		popup.style.left = '';
		popup.style.right = '';
		popup.style.bottom = '';
		popup.style.maxHeight = '';
		if (card) {
			card.appendChild(popup);
		}
	}

	function resetBackdrop(root) {
		var backdrop = document.querySelector('.llm-ie-stories__backdrop.is-visible') ||
			document.querySelector('.llm-ie-stories__backdrop:not([hidden])') ||
			(root && root.querySelector('.llm-ie-stories__backdrop'));
		if (backdrop) {
			backdrop.classList.remove('is-visible');
			backdrop.hidden = true;
			backdrop.style.zIndex = '';
			if (root) {
				root.insertBefore(backdrop, root.firstChild);
			}
		}
		document.body.classList.remove('llm-ie-popup-open');
	}

	function closeCard(card, opts) {
		opts = opts || {};
		var instant = !!opts.instant;
		var root = catalogRoot(card);
		var popup = popupOf(card);
		var trigger = card.querySelector('.llm-ie-stories__trigger');
		var backdrop = document.querySelector('.llm-ie-stories__backdrop:not([hidden])') ||
			(root && root.querySelector('.llm-ie-stories__backdrop'));
		card.classList.remove('is-open');
		if (trigger) {
			trigger.setAttribute('aria-expanded', 'false');
			if (!openedByHover && !instant) {
				trigger.focus();
			}
		}
		openedByHover = false;
		clearFadeTimer();
		if (popup) {
			popup.classList.remove('is-visible');
		}
		if (backdrop) {
			backdrop.classList.remove('is-visible');
		}
		function finish() {
			fadeTimer = null;
			if (popup) {
				resetPopup(popup, card);
			}
			if (root && !root.querySelector('.llm-ie-stories__card.is-open')) {
				root.classList.remove('is-popup-open');
				resetBackdrop(root);
			}
		}
		if (instant || !popup) {
			finish();
			return;
		}
		fadeTimer = window.setTimeout(finish, FADE_MS);
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
			closeCard(cards[i], { instant: true });
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
		clearHoverTimers();
		openedByHover = false;
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

	function canHoverOpen() {
		return window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
	}

	function clearHoverTimers() {
		if (hoverTimer) {
			window.clearTimeout(hoverTimer);
			hoverTimer = null;
		}
		if (closeHoverTimer) {
			window.clearTimeout(closeHoverTimer);
			closeHoverTimer = null;
		}
		hoverCard = null;
	}

	function keepHoverTarget(card, related) {
		if (!related || !related.closest) {
			return false;
		}
		if (card.contains(related)) {
			return true;
		}
		var popup = popupOf(card);
		if (popup && popup.contains(related)) {
			return true;
		}
		var pop = related.closest('.llm-ie-stories__popup');
		if (pop && pop.getAttribute('data-llm-ie-for') === card.getAttribute('data-llm-ie-card-id')) {
			return true;
		}
		return false;
	}

	function scheduleHoverOpen(card) {
		clearHoverTimers();
		if (!canHoverOpen() || !card || card.classList.contains('llm-ie-stories__card--soon')) {
			return;
		}
		if (card.classList.contains('is-open')) {
			return;
		}
		hoverCard = card;
		hoverTimer = window.setTimeout(function () {
			hoverTimer = null;
			if (hoverCard === card && !card.classList.contains('is-open')) {
				openCard(card, { fromHover: true });
			}
			hoverCard = null;
		}, HOVER_OPEN_MS);
	}

	function scheduleHoverClose(card) {
		if (hoverTimer) {
			window.clearTimeout(hoverTimer);
			hoverTimer = null;
			hoverCard = null;
		}
		if (!openedByHover || !card || !card.classList.contains('is-open')) {
			return;
		}
		if (closeHoverTimer) {
			window.clearTimeout(closeHoverTimer);
		}
		closeHoverTimer = window.setTimeout(function () {
			closeHoverTimer = null;
			if (openedByHover && card.classList.contains('is-open')) {
				closeCard(card);
			}
		}, HOVER_CLOSE_MS);
	}

	function onDocMouseOver(event) {
		if (!canHoverOpen()) {
			return;
		}
		var card = event.target.closest && event.target.closest('[data-llm-ie-card]');
		if (!card && event.target.closest) {
			var pop = event.target.closest('.llm-ie-stories__popup');
			if (pop) {
				card = cardById(pop.getAttribute('data-llm-ie-for'));
			}
		}
		if (!card) {
			return;
		}
		if (closeHoverTimer) {
			window.clearTimeout(closeHoverTimer);
			closeHoverTimer = null;
		}
		if (card.classList.contains('is-open')) {
			return;
		}
		if (hoverCard === card && hoverTimer) {
			return;
		}
		scheduleHoverOpen(card);
	}

	function onDocMouseOut(event) {
		if (!canHoverOpen()) {
			return;
		}
		var card = event.target.closest && event.target.closest('[data-llm-ie-card]');
		if (!card && event.target.closest) {
			var pop = event.target.closest('.llm-ie-stories__popup');
			if (pop) {
				card = cardById(pop.getAttribute('data-llm-ie-for'));
			}
		}
		if (!card) {
			return;
		}
		if (keepHoverTarget(card, event.relatedTarget)) {
			return;
		}
		scheduleHoverClose(card);
	}

	function boot() {
		if (document.documentElement.getAttribute('data-llm-ie-js') === '1') {
			return;
		}
		document.documentElement.setAttribute('data-llm-ie-js', '1');
		document.addEventListener('click', onDocClick);
		document.addEventListener('keydown', onDocKey);
		document.addEventListener('mouseover', onDocMouseOver);
		document.addEventListener('mouseout', onDocMouseOut);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
