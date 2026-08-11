/*
 * llm-quiz-admin.js — elenco compatto, categorie, edit, import CSV.
 */
(function (window, document) {
	'use strict';

	var cfg = window.llmQuizAdmin || {};

	function el(id) {
		return document.getElementById(id);
	}

	function root() {
		return el('llm-quiz-questions');
	}

	function i18n(key, fallback) {
		return (cfg.i18n && cfg.i18n[key]) || fallback;
	}

	function questionBlocks() {
		var r = root();
		return r ? Array.prototype.slice.call(r.querySelectorAll('.llm-quiz-admin__question')) : [];
	}

	function uncategorizedLabel() {
		return i18n('uncategorized', 'Senza categoria');
	}

	function truncate(text, max) {
		text = String(text || '').replace(/\s+/g, ' ').trim();
		if (!text) {
			return '(vuota)';
		}
		if (text.length <= max) {
			return text;
		}
		return text.slice(0, max - 1) + '…';
	}

	function syncRowPreview(block) {
		var textEl = block.querySelector('.llm-quiz-q-text');
		var correctEl = block.querySelector('.llm-quiz-q-correct');
		var preview = block.querySelector('.llm-quiz-admin__preview');
		var badge = block.querySelector('.llm-quiz-admin__badge--correct');
		var catEl = block.querySelector('.llm-quiz-q-category');
		if (preview && textEl) {
			var t = truncate(textEl.value, 110);
			preview.textContent = t;
			preview.setAttribute('title', textEl.value || '');
		}
		if (badge && correctEl) {
			var letter = String.fromCharCode(65 + (parseInt(correctEl.value, 10) || 0));
			badge.textContent = i18n('correctLetter', 'Corretta') + ' ' + letter;
		}
		if (catEl) {
			block.setAttribute('data-category', catEl.value || '');
		}
		block.querySelectorAll('.llm-quiz-admin__answer').forEach(function (ans, i) {
			if ((parseInt(correctEl && correctEl.value, 10) || 0) === i) {
				ans.classList.add('is-correct');
			} else {
				ans.classList.remove('is-correct');
			}
		});
	}

	function reindexNames() {
		questionBlocks().forEach(function (block, index) {
			block.setAttribute('data-index', String(index));
			var label = block.querySelector('.llm-quiz-admin__question-label');
			if (label) {
				label.textContent = '#' + (index + 1);
			}
			block.querySelectorAll('[name]').forEach(function (field) {
				var name = field.getAttribute('name') || '';
				field.setAttribute('name', name.replace(/llm_quiz_q\[[^\]]+\]/, 'llm_quiz_q[' + index + ']'));
			});
			syncRowPreview(block);
		});
		var r = root();
		if (r) {
			r.setAttribute('data-count', String(questionBlocks().length));
		}
		var countEl = el('llm-quiz-count');
		if (countEl) {
			countEl.textContent = questionBlocks().length + ' domande';
		}
	}

	function regroupByCategory() {
		var r = root();
		if (!r) {
			return;
		}
		var blocks = questionBlocks();
		var groups = {};
		var order = [];

		blocks.forEach(function (block) {
			var catInput = block.querySelector('.llm-quiz-q-category');
			var cat = catInput && catInput.value.trim() ? catInput.value.trim() : uncategorizedLabel();
			if (!groups[cat]) {
				groups[cat] = [];
				order.push(cat);
			}
			groups[cat].push(block);
		});

		order.sort(function (a, b) {
			var ua = a === uncategorizedLabel();
			var ub = b === uncategorizedLabel();
			if (ua !== ub) {
				return ua ? 1 : -1;
			}
			return a.localeCompare(b, 'it');
		});

		r.innerHTML = '';
		if (!blocks.length) {
			r.innerHTML = '<p class="llm-quiz-admin__empty">Nessuna domanda. Aggiungine una o importa un CSV.</p>';
			reindexNames();
			return;
		}

		order.forEach(function (cat) {
			var section = document.createElement('section');
			section.className = 'llm-quiz-admin__cat';
			section.setAttribute('data-category', cat);
			var title = document.createElement('h4');
			title.className = 'llm-quiz-admin__cat-title';
			title.innerHTML =
				cat +
				' <span class="llm-quiz-admin__cat-count">(' +
				groups[cat].length +
				')</span>';
			var list = document.createElement('div');
			list.className = 'llm-quiz-admin__cat-list';
			groups[cat].forEach(function (block) {
				list.appendChild(block);
			});
			section.appendChild(title);
			section.appendChild(list);
			r.appendChild(section);
		});

		reindexNames();
	}

	function newId() {
		return 'q_' + Math.random().toString(36).slice(2, 10) + Date.now().toString(36).slice(-4);
	}

	function setOpen(block, open) {
		var btn = block.querySelector('.llm-quiz-toggle');
		if (open) {
			block.classList.add('is-open');
			if (btn) {
				btn.textContent = i18n('close', 'Chiudi');
			}
		} else {
			block.classList.remove('is-open');
			if (btn) {
				btn.textContent = i18n('edit', 'Modifica');
			}
		}
	}

	function addQuestion() {
		var tpl = el('llm-quiz-question-template');
		var r = root();
		if (!tpl || !r) {
			return;
		}
		var html = tpl.innerHTML.replace(/__INDEX__/g, String(questionBlocks().length));
		var wrap = document.createElement('div');
		wrap.innerHTML = html.trim();
		var block = wrap.querySelector('.llm-quiz-admin__question');
		if (!block) {
			return;
		}
		var idField = block.querySelector('.llm-quiz-q-id');
		if (idField) {
			idField.value = newId();
		}
		setOpen(block, true);
		/* append into last category list or create temp then regroup */
		var lists = r.querySelectorAll('.llm-quiz-admin__cat-list');
		if (lists.length) {
			lists[lists.length - 1].appendChild(block);
		} else {
			r.appendChild(block);
		}
		regroupByCategory();
		block.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	}

	function onRootClick(e) {
		var t = e.target;
		if (!t || !t.closest) {
			return;
		}
		var block = t.closest('.llm-quiz-admin__question');
		if (!block) {
			return;
		}

		if (t.classList.contains('llm-quiz-toggle') || t.closest('.llm-quiz-toggle')) {
			e.preventDefault();
			setOpen(block, !block.classList.contains('is-open'));
			return;
		}

		if (t.classList.contains('llm-quiz-remove') || t.closest('.llm-quiz-remove')) {
			e.preventDefault();
			if (!window.confirm(i18n('removeQ', 'Eliminare?'))) {
				return;
			}
			block.parentNode.removeChild(block);
			regroupByCategory();
			return;
		}

		if (t.classList.contains('llm-quiz-move-up') || t.closest('.llm-quiz-move-up')) {
			e.preventDefault();
			var prev = block.previousElementSibling;
			if (prev && prev.classList.contains('llm-quiz-admin__question')) {
				block.parentNode.insertBefore(block, prev);
				reindexNames();
			}
			return;
		}

		if (t.classList.contains('llm-quiz-move-down') || t.closest('.llm-quiz-move-down')) {
			e.preventDefault();
			var next = block.nextElementSibling;
			if (next && next.classList.contains('llm-quiz-admin__question')) {
				block.parentNode.insertBefore(next, block);
				reindexNames();
			}
		}
	}

	function onRootInput(e) {
		var t = e.target;
		if (!t) {
			return;
		}
		var block = t.closest && t.closest('.llm-quiz-admin__question');
		if (!block) {
			return;
		}
		syncRowPreview(block);
		if (t.classList.contains('llm-quiz-q-category')) {
			/* debounce regroup lightly */
			window.clearTimeout(onRootInput._t);
			onRootInput._t = window.setTimeout(regroupByCategory, 400);
		}
	}

	function setStatus(text, kind) {
		var status = el('llm-quiz-import-status');
		if (!status) {
			return;
		}
		status.textContent = text || '';
		status.classList.remove('is-ok', 'is-error');
		if (kind) {
			status.classList.add(kind);
		}
	}

	function importCsv(mode) {
		var csvEl = el('llm_quiz_csv');
		var csv = csvEl ? csvEl.value : '';
		if (!csv || !String(csv).trim()) {
			setStatus(i18n('importEmpty', 'CSV vuoto'), 'is-error');
			return;
		}
		var confirmMsg =
			mode === 'replace'
				? i18n('confirmReplace', 'Sostituire?')
				: i18n('confirmAppend', 'Aggiungere?');
		if (!window.confirm(confirmMsg)) {
			return;
		}
		var postIdField = document.getElementById('post_ID');
		var postId = postIdField ? postIdField.value : '0';
		if (!postId || postId === '0') {
			setStatus('Salva prima il quiz come bozza, poi importa.', 'is-error');
			return;
		}

		setStatus(i18n('importing', 'Importo…'), '');
		var body = new window.FormData();
		body.append('action', cfg.action || 'llm_quiz_import_csv');
		body.append('nonce', cfg.nonce || '');
		body.append('post_id', postId);
		body.append('mode', mode);
		body.append('csv', csv);

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
							: i18n('networkError', 'Errore');
					setStatus(err, 'is-error');
					return;
				}
				var r = root();
				if (r && json.data && json.data.html) {
					r.innerHTML = json.data.html;
					reindexNames();
				}
				setStatus((json.data && json.data.message) || 'OK', 'is-ok');
			})
			.catch(function () {
				setStatus(i18n('networkError', 'Errore di rete'), 'is-error');
			});
	}

	function init() {
		var addBtn = el('llm-quiz-add-question');
		if (addBtn) {
			addBtn.addEventListener('click', function (e) {
				e.preventDefault();
				addQuestion();
			});
		}
		var collapseBtn = el('llm-quiz-collapse-all');
		if (collapseBtn) {
			collapseBtn.addEventListener('click', function (e) {
				e.preventDefault();
				questionBlocks().forEach(function (b) {
					setOpen(b, false);
				});
			});
		}

		var r = root();
		if (r) {
			r.addEventListener('click', onRootClick);
			r.addEventListener('input', onRootInput);
			r.addEventListener('change', onRootInput);
		}

		var appendBtn = el('llm-quiz-import-append');
		var replaceBtn = el('llm-quiz-import-replace');
		if (appendBtn) {
			appendBtn.addEventListener('click', function (e) {
				e.preventDefault();
				importCsv('append');
			});
		}
		if (replaceBtn) {
			replaceBtn.addEventListener('click', function (e) {
				e.preventDefault();
				importCsv('replace');
			});
		}

		reindexNames();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})(window, document);
