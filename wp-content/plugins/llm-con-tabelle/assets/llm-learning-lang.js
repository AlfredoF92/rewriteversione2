(function () {
	'use strict';

	var cfg = typeof llmLearningLang === 'undefined' ? null : llmLearningLang;
	if (!cfg || !cfg.ajaxUrl) {
		return;
	}

	function qs(root, sel) {
		return root.querySelector(sel);
	}

	function qsa(root, sel) {
		return Array.prototype.slice.call(root.querySelectorAll(sel));
	}

	function msg(root, text, isError) {
		var el = qs(root, '[data-llm-learn-message]');
		if (!el) {
			return;
		}
		el.textContent = text || '';
		el.classList.toggle('llm-user-profile__message--error', !!isError);
	}

	function showPanel(root, name) {
		var view = qs(root, '[data-llm-panel="view"]');
		var edit = qs(root, '[data-llm-panel="edit"]');
		if (view) {
			view.hidden = name !== 'view';
		}
		if (edit) {
			edit.hidden = name !== 'edit';
		}
	}

	function bind(root) {
		var form = qs(root, '[data-llm-learn-form]');
		var snapSelect = '';

		qsa(root, '[data-llm-action="edit"]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (form) {
					var sel = form.elements.namedItem('learning_lang');
					if (sel && 'value' in sel) {
						snapSelect = sel.value;
					}
				}
				msg(root, '', false);
				showPanel(root, 'edit');
				var s = form && form.elements.namedItem('learning_lang');
				if (s && s.focus) {
					s.focus();
				}
			});
		});

		qsa(root, '[data-llm-action="cancel"]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (form) {
					var sel = form.elements.namedItem('learning_lang');
					if (sel && snapSelect !== undefined) {
						sel.value = snapSelect;
					}
				}
				msg(root, '', false);
				showPanel(root, 'view');
			});
		});

		if (form) {
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				msg(root, '', false);

				var fd = new FormData(form);
				fd.append('action', cfg.action);
				fd.append('nonce', cfg.nonce);

				var saveBtn = qs(form, '[data-llm-action="save"]');
				if (saveBtn) {
					saveBtn.disabled = true;
				}

				fetch(cfg.ajaxUrl, {
					method: 'POST',
					body: fd,
					credentials: 'same-origin',
				})
					.then(function (r) {
						return r.json();
					})
					.then(function (data) {
						if (saveBtn) {
							saveBtn.disabled = false;
						}
						if (!data || !data.success) {
							var code = data && data.data && data.data.code;
							var t = cfg.i18n || {};
							var err =
								(data.data && data.data.message) ||
								(code === 'lang_invalid' && t.langInvalid) ||
								t.genericError ||
								'Error';
							msg(root, err, true);
							return;
						}

						var p = data.data || {};
						var span = qs(root, '[data-llm-field="learning_label"]');
						if (span && p.label) {
							span.textContent = p.label;
						}

						if (form && p.code) {
							var sel = form.elements.namedItem('learning_lang');
							if (sel) {
								sel.value = p.code;
							}
							snapSelect = p.code;
						}

						msg(root, cfg.i18n.saved || '', false);
						window.location.reload();
					})
					.catch(function () {
						if (saveBtn) {
							saveBtn.disabled = false;
						}
						msg(root, (cfg.i18n && cfg.i18n.networkError) || '', true);
					});
			});
		}
	}

	qsa(document, '[data-llm-learning-lang]').forEach(bind);
})();
