/*
 * llm-magazine-admin.js — filtra storie/brani/quiz per coppia, auto-quiz, shortcode.
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

	function postId() {
		var f = document.getElementById('post_ID');
		return f ? parseInt(f.value, 10) || 0 : 0;
	}

	/**
	 * @param {string} listId
	 * @param {string} emptyId
	 * @param {boolean|null} wantMusic true/false per storie, null = ignora music flag (quiz)
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
			var pairMatch = hasPair && pairs.indexOf(pairKey) !== -1;
			var match = pairMatch;
			if (wantMusic !== null) {
				var isMusic = row.getAttribute('data-music') === '1';
				match = pairMatch && isMusic === wantMusic;
			}
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

	function applyQuizSelection(ids) {
		var list = el('llm-mag-quiz');
		if (!list) {
			return;
		}
		var set = {};
		(ids || []).forEach(function (id) {
			set[id] = true;
		});
		list.querySelectorAll('.llm-mag-admin__quiz-row').forEach(function (row) {
			if (row.hasAttribute('hidden')) {
				return;
			}
			var cb = row.querySelector('input[type="checkbox"]');
			var qid = row.getAttribute('data-qid') || (cb && cb.value);
			if (cb) {
				cb.checked = !!(qid && set[qid]);
			}
		});
	}

	function setQuizStatus(text, isError) {
		var status = el('llm-mag-quiz-status');
		if (!status) {
			return;
		}
		status.textContent = text || '';
		status.classList.toggle('is-error', !!isError);
	}

	function autoPickQuiz() {
		var known = el('llm_mag_known');
		var target = el('llm_mag_target');
		if (!known || !target || !known.value || !target.value || known.value === target.value) {
			setQuizStatus(cfg.pickPairFirst || '', true);
			return;
		}

		setQuizStatus('Scelgo…', false);
		var body = new window.FormData();
		body.append('action', cfg.suggestAction || 'llm_magazine_suggest_quiz');
		body.append('nonce', cfg.suggestNonce || '');
		body.append('known', known.value);
		body.append('target', target.value);
		body.append('post_id', String(postId()));
		body.append('count', String(cfg.quizPerIssue || 3));

		window
			.fetch(cfg.ajaxUrl || '', {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			})
			.then(function (r) {
				return r.json();
			})
			.then(function (json) {
				if (!json || !json.success) {
					var err =
						json && json.data && json.data.message
							? json.data.message
							: cfg.noQuiz || 'Errore';
					setQuizStatus(err, true);
					return;
				}
				applyQuizSelection((json.data && json.data.question_ids) || []);
				setQuizStatus((json.data && json.data.message) || 'OK', false);
			})
			.catch(function () {
				setQuizStatus('Errore di rete', true);
			});
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
		syncList('llm-mag-quiz', 'llm-mag-quiz-empty', null, hasPair, pairKey, cfg.noQuiz || '');

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
			known.addEventListener('change', function () {
				syncStories();
				autoPickQuiz();
			});
		}
		if (target) {
			target.addEventListener('change', function () {
				syncStories();
				autoPickQuiz();
			});
		}
		syncStories();

		var autoBtn = el('llm-mag-quiz-auto');
		if (autoBtn) {
			autoBtn.addEventListener('click', function (e) {
				e.preventDefault();
				autoPickQuiz();
			});
		}

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
