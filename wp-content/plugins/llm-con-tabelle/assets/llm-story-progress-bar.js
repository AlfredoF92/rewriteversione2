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
	 * Aggiorna tutte le barre per la storia (es. dopo completamento frase).
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
			var tr = qs(wrap, '.llm-story-progress-bar__track');
			if (tr && srTpl) {
				tr.setAttribute('aria-label', formatSr(srTpl, d, t));
			}
		});
	}

	window.llmUpdateStoryProgressBar = updateBarsForStory;
})();
