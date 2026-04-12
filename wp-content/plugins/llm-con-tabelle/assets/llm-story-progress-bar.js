(function () {
	'use strict';

	function qs(root, sel) {
		return root.querySelector(sel);
	}

	function pct(done, total) {
		if (!total || total < 1) {
			return 0;
		}
		return Math.min(100, Math.round((100 * done) / total));
	}

	function setBar(el, done, total) {
		var fill = qs(el, '.llm-story-progress-bar__fill');
		var track = qs(el, '.llm-story-progress-bar__track');
		var p = pct(done, total);
		if (fill) {
			fill.style.width = p + '%';
		}
		if (track) {
			track.setAttribute('aria-valuenow', String(done));
			track.setAttribute('aria-valuemax', String(total));
		}
	}

	function formatSr(template, done, total) {
		return String(template || '')
			.replace('%1$d', String(done))
			.replace('%2$d', String(total));
	}

	/**
	 * Aggiorna tutte le barre [llm_story_progress_bar] per la storia (es. dopo completamento frase).
	 *
	 * @param {string|number} storyId
	 * @param {number} done
	 * @param {number} total
	 */
	function updateBarsForStory(storyId, done, total) {
		var sid = String(storyId || '');
		var d = Math.max(0, parseInt(done, 10) || 0);
		var t = Math.max(0, parseInt(total, 10) || 0);
		if (!sid || t < 1) {
			return;
		}
		var cfg = window.llmStoryProgressBar || {};
		var srTpl = (cfg.i18n && cfg.i18n.sr) || '';
		document.querySelectorAll('.llm-story-progress-bar').forEach(function (wrap) {
			if (wrap.getAttribute('data-story-id') !== sid) {
				return;
			}
			setBar(wrap, d, t);
			var count = qs(wrap, '.llm-story-progress-bar__count');
			if (count) {
				count.textContent = d + ' / ' + t;
			}
			var track = qs(wrap, '.llm-story-progress-bar__track');
			if (track && srTpl) {
				track.setAttribute('aria-label', formatSr(srTpl, d, t));
			}
		});
	}

	window.llmUpdateStoryProgressBar = updateBarsForStory;

	document.addEventListener('DOMContentLoaded', function () {
		var cfg = window.llmStoryProgressBar || {};
		var ajaxUrl = cfg.ajaxUrl || '';
		var i18n = cfg.i18n || {};

		document.querySelectorAll('.llm-story-progress-bar').forEach(function (wrap) {
			var btn = qs(wrap, '.llm-story-progress-bar__restart');
			if (!btn || btn.disabled) {
				return;
			}
			btn.addEventListener('click', function () {
				var msg = i18n.confirm || '';
				if (msg && !window.confirm(msg)) {
					return;
				}
				var storyId = wrap.getAttribute('data-story-id') || '';
				var nonce = wrap.getAttribute('data-nonce') || '';
				if (!ajaxUrl || !storyId || !nonce) {
					return;
				}
				btn.disabled = true;
				var body = new URLSearchParams();
				body.set('action', 'llm_story_progress_reset');
				body.set('nonce', nonce);
				body.set('story_id', storyId);
				fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
					},
					body: body.toString(),
				})
					.then(function (r) {
						return r.json();
					})
					.then(function (json) {
						if (json && json.success) {
							window.location.reload();
							return;
						}
						btn.disabled = false;
						var m =
							(json && json.data && json.data.message) ||
							i18n.ajaxError ||
							'';
						if (m) {
							window.alert(m);
						}
					})
					.catch(function () {
						btn.disabled = false;
						if (i18n.ajaxError) {
							window.alert(i18n.ajaxError);
						}
					});
			});
		});
	});
})();
