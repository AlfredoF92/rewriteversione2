/*
 * llm-crossword-admin.js — anteprima e rigenerazione definizioni dal file unificato.
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

	function analyze(bundle) {
		var body = new window.FormData();
		body.append('action', cfg.action);
		body.append('nonce', cfg.nonce);
		body.append('post_id', String(postId()));
		body.append('bundle', bundle);

		return window
			.fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (response) {
				return response.json();
			});
	}

	function init() {
		var bundleField = el('llm-cw-bundle');
		var statusEl = el('llm-cw-status');
		var previewBox = el('llm-cw-preview-box');
		var previewBtn = el('llm-cw-preview');
		var skeletonBtn = el('llm-cw-skeleton');
		var copyBtn = el('llm-cw-copy');

		function setStatus(text, kind) {
			if (!statusEl) {
				return;
			}
			statusEl.textContent = text || '';
			statusEl.className = 'llm-cw-admin__status' + (kind ? ' is-' + kind : '');
		}

		if (copyBtn) {
			copyBtn.addEventListener('click', function () {
				var field = el('llm-cw-shortcode');
				if (!field) {
					return;
				}
				field.select();
				var done = function () {
					setStatus(i18n.copied, 'ok');
				};
				var failed = function () {
					setStatus(i18n.copyFailed, 'error');
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

		if (!bundleField) {
			return;
		}

		var knownSel = el('llm_cw_known');
		var targetSel = el('llm_cw_target');
		var langNames = {
			it: 'Italiano',
			en: 'Inglese',
			pl: 'Polacco',
			es: 'Spagnolo'
		};

		function syncLangInBundle() {
			if (!knownSel || !targetSel || !bundleField) {
				return;
			}
			var k = knownSel.value;
			var t = targetSel.value;
			if (!k || !t || k === t) {
				return;
			}
			var line = (langNames[k] || k) + ' → ' + (langNames[t] || t);
			var text = bundleField.value;
			if (/^#{2,}\s*LINGUA\b/im.test(text)) {
				bundleField.value = text.replace(
					/(#{2,}\s*LINGUA\b[^\n]*\n)([^\n#]*(?:\n(?!#{2,})[^\n]*)*)/i,
					function (full, header) {
						return header + line + '\n';
					}
				);
			}
		}

		if (knownSel) {
			knownSel.addEventListener('change', syncLangInBundle);
		}
		if (targetSel) {
			targetSel.addEventListener('change', syncLangInBundle);
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
			var raw = bundleField.value;
			if (!String(raw).trim()) {
				setStatus(i18n.bundleEmpty, 'error');
				return;
			}
			setStatus(i18n.working, '');
			analyze(raw).then(
				function (response) {
					if (!response || !response.success) {
						if (previewBox) {
							previewBox.hidden = true;
						}
						setStatus(response && response.data ? response.data.message : i18n.networkError, 'error');
						return;
					}
					showPreview(response.data);
					if (applySkeleton && response.data.bundle) {
						bundleField.value = response.data.bundle;
					}
					setStatus(response.data.summary, 'ok');
				},
				function () {
					setStatus(i18n.networkError, 'error');
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
				if (!window.confirm(i18n.confirmDefs)) {
					return;
				}
				run(true);
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})(window, document);
