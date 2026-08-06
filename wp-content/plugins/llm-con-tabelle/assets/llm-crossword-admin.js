/*
 * llm-crossword-admin.js — anteprima schema, generazione elenco definizioni,
 * import CSV definizioni, copia dello shortcode.
 */
(function (window, document) {
	'use strict';

	var cfg = window.llmCrosswordAdmin || {};
	var i18n = cfg.i18n || {};

	function el(id) {
		return document.getElementById(id);
	}

	function postId() {
		var field = el('post_ID');
		return field ? parseInt(field.value, 10) || 0 : 0;
	}

	function analyze(csv, defs) {
		var body = new window.FormData();
		body.append('action', cfg.action);
		body.append('nonce', cfg.nonce);
		body.append('post_id', String(postId()));
		body.append('csv', csv);
		body.append('defs', defs);

		return window
			.fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (response) {
				return response.json();
			});
	}

	function importDefs(csv, defsCsv) {
		var body = new window.FormData();
		body.append('action', cfg.importAction);
		body.append('nonce', cfg.importNonce);
		body.append('post_id', String(postId()));
		body.append('csv', csv || '');
		body.append('defs_csv', defsCsv);

		return window
			.fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (response) {
				return response.json();
			});
	}

	function init() {
		var csvField = el('llm-cw-csv');
		var defsField = el('llm-cw-defs');
		var defsCsvField = el('llm-cw-defs-csv');
		var statusEl = el('llm-cw-status');
		var importStatusEl = el('llm-cw-import-status');
		var previewBox = el('llm-cw-preview-box');
		var previewBtn = el('llm-cw-preview');
		var skeletonBtn = el('llm-cw-skeleton');
		var importBtn = el('llm-cw-import-defs');
		var copyBtn = el('llm-cw-copy');

		function setStatus(target, text, kind) {
			if (!target) {
				return;
			}
			target.textContent = text || '';
			target.className = 'llm-cw-admin__status' + (kind ? ' is-' + kind : '');
		}

		if (copyBtn) {
			copyBtn.addEventListener('click', function () {
				var field = el('llm-cw-shortcode');
				if (!field) {
					return;
				}
				field.select();
				var done = function () {
					setStatus(statusEl, i18n.copied, 'ok');
				};
				var failed = function () {
					setStatus(statusEl, i18n.copyFailed, 'error');
				};
				if (window.navigator.clipboard) {
					window.navigator.clipboard.writeText(field.value).then(done, failed);
					return;
				}
				try {
					if (document.execCommand('copy')) {
						done();
					} else {
						failed();
					}
				} catch (e) {
					failed();
				}
			});
		}

		if (!csvField || !defsField) {
			return;
		}

		function showPreview(data) {
			if (!previewBox) {
				return;
			}
			var html = '<p class="llm-cw-preview-summary">' + data.summary + '</p>' + data.preview;
			if (data.warnings && data.warnings.length) {
				html += '<ul class="llm-cw-preview-warnings">';
				data.warnings.forEach(function (warning) {
					var li = document.createElement('li');
					li.textContent = warning;
					html += li.outerHTML;
				});
				html += '</ul>';
			}
			previewBox.innerHTML = html;
			previewBox.hidden = false;
		}

		function run(applySkeleton) {
			setStatus(statusEl, i18n.working, '');
			analyze(csvField.value, defsField.value).then(
				function (response) {
					if (!response || !response.success) {
						if (previewBox) {
							previewBox.hidden = true;
						}
						setStatus(statusEl, response && response.data ? response.data.message : i18n.networkError, 'error');
						return;
					}
					showPreview(response.data);
					if (applySkeleton) {
						defsField.value = response.data.skeleton;
					}
					setStatus(statusEl, response.data.summary, 'ok');
				},
				function () {
					setStatus(statusEl, i18n.networkError, 'error');
				}
			);
		}

		if (previewBtn) {
			previewBtn.addEventListener('click', function () {
				run(false);
			});
		}

		if (skeletonBtn) {
			skeletonBtn.addEventListener('click', function () {
				if (defsField.value.trim() && !window.confirm(i18n.confirmDefs)) {
					return;
				}
				run(true);
			});
		}

		if (importBtn && defsCsvField) {
			importBtn.addEventListener('click', function () {
				var raw = defsCsvField.value.trim();
				if (!raw) {
					setStatus(importStatusEl, i18n.importEmpty, 'error');
					return;
				}
				if (defsField.value.trim() && !window.confirm(i18n.confirmImport)) {
					return;
				}
				setStatus(importStatusEl, i18n.importing, '');
				importBtn.disabled = true;
				importDefs(csvField.value, defsCsvField.value).then(
					function (response) {
						importBtn.disabled = false;
						if (!response || !response.success) {
							setStatus(
								importStatusEl,
								response && response.data ? response.data.message : i18n.networkError,
								'error'
							);
							return;
						}
						defsField.value = response.data.defs;
						var msg = response.data.summary || '';
						if (response.data.warnings && response.data.warnings.length) {
							msg += ' ' + response.data.warnings.join(' ');
						}
						setStatus(importStatusEl, msg, response.data.warnings && response.data.warnings.length ? '' : 'ok');
					},
					function () {
						importBtn.disabled = false;
						setStatus(importStatusEl, i18n.networkError, 'error');
					}
				);
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})(window, document);
