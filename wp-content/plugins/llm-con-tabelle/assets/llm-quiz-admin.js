/*
 * llm-quiz-admin.js — aggiungi/elimina/riordina domande + import CSV.
 */
(function (window, document) {
	'use strict';

	var cfg = window.llmQuizAdmin || {};

	function el(id) {
		return document.getElementById(id);
	}

	function list() {
		return el('llm-quiz-questions');
	}

	function questionBlocks() {
		var root = list();
		return root ? Array.prototype.slice.call(root.querySelectorAll('.llm-quiz-admin__question')) : [];
	}

	function reindex() {
		var blocks = questionBlocks();
		blocks.forEach(function (block, index) {
			block.setAttribute('data-index', String(index));
			var label = block.querySelector('.llm-quiz-admin__question-label');
			if (label) {
				label.textContent = (cfg.i18n && cfg.i18n.questionLabel ? cfg.i18n.questionLabel : 'Domanda') + ' ' + (index + 1);
			}
			block.querySelectorAll('[name]').forEach(function (field) {
				var name = field.getAttribute('name') || '';
				field.setAttribute('name', name.replace(/llm_quiz_q\[[^\]]+\]/, 'llm_quiz_q[' + index + ']'));
			});
		});
		var root = list();
		if (root) {
			root.setAttribute('data-count', String(blocks.length));
		}
	}

	function newId() {
		return 'q_' + Math.random().toString(36).slice(2, 10) + Date.now().toString(36).slice(-4);
	}

	function addQuestion() {
		var tpl = el('llm-quiz-question-template');
		var root = list();
		if (!tpl || !root) {
			return;
		}
		var html = tpl.innerHTML.replace(/__INDEX__/g, String(questionBlocks().length));
		var wrap = document.createElement('div');
		wrap.innerHTML = html.trim();
		var block = wrap.firstElementChild;
		if (!block) {
			return;
		}
		var idField = block.querySelector('.llm-quiz-q-id');
		if (idField) {
			idField.value = newId();
		}
		root.appendChild(block);
		reindex();
		block.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	}

	function onListClick(e) {
		var t = e.target;
		if (!t || !t.closest) {
			return;
		}
		var block = t.closest('.llm-quiz-admin__question');
		if (!block) {
			return;
		}

		if (t.classList.contains('llm-quiz-remove') || t.closest('.llm-quiz-remove')) {
			e.preventDefault();
			var msg = (cfg.i18n && cfg.i18n.removeQ) || 'Eliminare?';
			if (!window.confirm(msg)) {
				return;
			}
			block.parentNode.removeChild(block);
			if (!questionBlocks().length) {
				addQuestion();
			} else {
				reindex();
			}
			return;
		}

		if (t.classList.contains('llm-quiz-move-up') || t.closest('.llm-quiz-move-up')) {
			e.preventDefault();
			var prev = block.previousElementSibling;
			if (prev) {
				block.parentNode.insertBefore(block, prev);
				reindex();
			}
			return;
		}

		if (t.classList.contains('llm-quiz-move-down') || t.closest('.llm-quiz-move-down')) {
			e.preventDefault();
			var next = block.nextElementSibling;
			if (next) {
				block.parentNode.insertBefore(next, block);
				reindex();
			}
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
			setStatus((cfg.i18n && cfg.i18n.importEmpty) || 'CSV vuoto', 'is-error');
			return;
		}

		var confirmMsg =
			mode === 'replace'
				? (cfg.i18n && cfg.i18n.confirmReplace) || 'Sostituire?'
				: (cfg.i18n && cfg.i18n.confirmAppend) || 'Aggiungere?';
		if (!window.confirm(confirmMsg)) {
			return;
		}

		var postIdField = document.getElementById('post_ID');
		var postId = postIdField ? postIdField.value : '0';
		if (!postId || postId === '0') {
			setStatus('Salva prima il quiz come bozza, poi importa.', 'is-error');
			return;
		}

		setStatus((cfg.i18n && cfg.i18n.importing) || 'Importo…', '');

		var body = new window.FormData();
		body.append('action', cfg.action || 'llm_quiz_import_csv');
		body.append('nonce', cfg.nonce || '');
		body.append('post_id', postId);
		body.append('mode', mode);
		body.append('csv', csv);

		window
			.fetch(cfg.ajaxUrl || ajaxurl, {
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
							: (cfg.i18n && cfg.i18n.networkError) || 'Errore';
					setStatus(err, 'is-error');
					return;
				}
				var root = list();
				if (root && json.data && json.data.html) {
					root.innerHTML = json.data.html;
					reindex();
				}
				setStatus((json.data && json.data.message) || 'OK', 'is-ok');
			})
			.catch(function () {
				setStatus((cfg.i18n && cfg.i18n.networkError) || 'Errore di rete', 'is-error');
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

		var root = list();
		if (root) {
			root.addEventListener('click', onListClick);
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

		reindex();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})(window, document);
