/*
 * llm-magazine-admin.js — filtra storie/brani per coppia, titolo dinamico, copia shortcode.
 */
(function (window, document) {
	'use strict';

	var cfg = window.llmMagazineAdmin || {};
	var SLUGS = {
		en: 'english',
		it: 'italian',
		pl: 'polish',
		es: 'spanish'
	};

	function el(id) {
		return document.getElementById(id);
	}

	function shortcodeFor(known, target) {
		if (!known || !target || known === target || !SLUGS[known] || !SLUGS[target]) {
			return '';
		}
		return '[rivista-primapagina-' + SLUGS[known] + '-' + SLUGS[target] + ']';
	}

	/**
	 * @param {string} listId
	 * @param {string} emptyId
	 * @param {boolean} wantMusic true = solo brani, false = solo storie non-brani
	 * @param {boolean} hasPair
	 * @param {string} pairKey
	 * @param {string} emptyMsg
	 */
	function syncList(listId, emptyId, wantMusic, hasPair, pairKey, emptyMsg) {
		var list = el(listId);
		var empty = el(emptyId);
		if (!list) {
			return;
		}
		var visible = 0;

		list.querySelectorAll('.llm-mag-admin__story').forEach(function (row) {
			var pairs = (row.getAttribute('data-pairs') || '').split(',').filter(Boolean);
			var isMusic = row.getAttribute('data-music') === '1';
			var match = hasPair && pairs.indexOf(pairKey) !== -1 && isMusic === wantMusic;
			if (match) {
				row.removeAttribute('hidden');
				visible++;
			} else {
				row.setAttribute('hidden', 'hidden');
				var cb = row.querySelector('input[type="checkbox"]');
				if (cb) {
					cb.checked = false;
				}
			}
		});

		if (empty) {
			if (!hasPair) {
				empty.hidden = false;
				empty.textContent = cfg.pickPairFirst || '';
			} else if (!visible) {
				empty.hidden = false;
				empty.textContent = emptyMsg || '';
			} else {
				empty.hidden = true;
			}
		}
	}

	function syncStories() {
		var known = el('llm_mag_known');
		var target = el('llm_mag_target');
		var titleEl = el('llm-mag-stories-title');
		if (!known || !target) {
			return;
		}
		var k = known.value;
		var t = target.value;
		var hasPair = !!(k && t && k !== t);
		var pairKey = k + '-' + t;

		if (titleEl) {
			var titles = cfg.storyTitles || {};
			titleEl.textContent = hasPair && titles[pairKey] ? titles[pairKey] : (cfg.defaultStoryTitle || 'Storie del giorno');
		}

		syncList('llm-mag-stories', 'llm-mag-stories-empty', false, hasPair, pairKey, cfg.noStories || '');
		syncList('llm-mag-music', 'llm-mag-music-empty', true, hasPair, pairKey, cfg.noMusic || '');

		var field = el('llm-mag-shortcode');
		var sc = shortcodeFor(k, t);
		if (field && sc) {
			field.value = sc;
		}
	}

	function init() {
		var known = el('llm_mag_known');
		var target = el('llm_mag_target');
		if (known) {
			known.addEventListener('change', syncStories);
		}
		if (target) {
			target.addEventListener('change', syncStories);
		}
		syncStories();

		var copyBtn = el('llm-mag-copy');
		if (copyBtn) {
			copyBtn.addEventListener('click', function () {
				var field = el('llm-mag-shortcode');
				if (!field) {
					return;
				}
				field.select();
				if (window.navigator.clipboard) {
					window.navigator.clipboard.writeText(field.value);
				} else {
					try {
						document.execCommand('copy');
					} catch (e) {
						/* ignore */
					}
				}
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})(window, document);
