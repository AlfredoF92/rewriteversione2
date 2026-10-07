/* llm-story-play-time.js — tempo di gioco sulla storia: solo se tab visibile e utente attivo. */
(function () {
	'use strict';

	var IDLE_MS = 45000;
	var TICK_MS = 20000;
	var MAX_TICK = 40;
	var lastActivity = Date.now();
	var unsaved = 0;
	var totalSeconds = 0;
	var tickTimer = null;
	var sending = false;

	function cfg() {
		var g = window.llmPhraseGame || {};
		return g.playTime || {};
	}

	function storyId() {
		var n = parseInt((window.llmPhraseGame || {}).storyId, 10) || 0;
		if (n) {
			return n;
		}
		return parseInt(cfg().storyId, 10) || 0;
	}

	function root() {
		return document.querySelector('[data-llm-play-time]');
	}

	function valueEl() {
		var el = root();
		return el ? el.querySelector('[data-llm-play-time-value]') : null;
	}

	function minutesTpl() {
		return cfg().minutesTpl || '%d min';
	}

	function formatMinutes(seconds) {
		var m = Math.floor(Math.max(0, seconds) / 60);
		return minutesTpl().replace('%d', String(m));
	}

	function paint() {
		var el = valueEl();
		if (el) {
			el.textContent = formatMinutes(totalSeconds);
		}
	}

	function isLoggedIn() {
		return !!cfg().loggedIn;
	}

	function isVisible() {
		return !document.hidden;
	}

	function isActive() {
		return isVisible() && (Date.now() - lastActivity) < IDLE_MS;
	}

	function markActivity() {
		lastActivity = Date.now();
	}

	function store() {
		return window.llmGuestBrowserStore || null;
	}

	function guestGet(id) {
		var s = store();
		if (s && typeof s.getPlaySeconds === 'function') {
			return s.getPlaySeconds(id);
		}
		return 0;
	}

	function guestAdd(id, delta) {
		var s = store();
		if (s && typeof s.addPlaySeconds === 'function') {
			return s.addPlaySeconds(id, delta);
		}
		return guestGet(id);
	}

	function flush(force) {
		var id = storyId();
		var add = Math.min(MAX_TICK, Math.max(0, Math.floor(unsaved)));
		if (!id || (!add && !force)) {
			return;
		}
		unsaved = Math.max(0, unsaved - add);
		if (!add) {
			return;
		}
		if (!isLoggedIn()) {
			totalSeconds = guestAdd(id, add);
			paint();
			return;
		}
		if (sending) {
			unsaved += add;
			return;
		}
		sending = true;
		var body = new FormData();
		body.append('action', cfg().action || 'llm_story_play_time_tick');
		body.append('nonce', cfg().nonce || '');
		body.append('story_id', String(id));
		body.append('seconds', String(add));
		fetch((window.llmPhraseGame || {}).ajaxUrl || cfg().ajaxUrl || '', {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		})
			.then(function (res) { return res.json(); })
			.then(function (json) {
				sending = false;
				if (json && json.success && json.data) {
					totalSeconds = parseInt(json.data.seconds, 10) || totalSeconds;
					paint();
				} else {
					unsaved += add;
				}
			})
			.catch(function () {
				sending = false;
				unsaved += add;
			});
	}

	function onTick() {
		if (!isActive()) {
			return;
		}
		unsaved += TICK_MS / 1000;
		if (unsaved >= 15) {
			flush(false);
		}
	}

	function boot() {
		var el = root();
		if (!el || !storyId()) {
			return;
		}
		if (isLoggedIn()) {
			totalSeconds = parseInt(el.getAttribute('data-seconds'), 10) || parseInt(cfg().seconds, 10) || 0;
		} else {
			totalSeconds = guestGet(storyId());
			el.setAttribute('data-seconds', String(totalSeconds));
		}
		paint();

		['pointerdown', 'keydown', 'scroll', 'touchstart', 'mousemove'].forEach(function (ev) {
			document.addEventListener(ev, markActivity, { passive: true });
		});
		document.addEventListener('visibilitychange', function () {
			if (document.hidden) {
				flush(false);
			} else {
				markActivity();
			}
		});
		window.addEventListener('pagehide', function () {
			flush(false);
		});
		tickTimer = window.setInterval(onTick, TICK_MS);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
