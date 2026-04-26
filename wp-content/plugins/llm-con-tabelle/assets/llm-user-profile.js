(function () {
	'use strict';

	var cfg = typeof llmUserProfile === 'undefined' ? null : llmUserProfile;
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
		var el = qs(root, '[data-profile-message]');
		if (!el) {
			return;
		}
		el.textContent = text || '';
		el.classList.toggle('llm-user-profile__message--error', !!isError);
	}

	function showPanel(root, name) {
		var view = qs(root, '[data-panel="view"]');
		var edit = qs(root, '[data-panel="edit"]');
		if (view) {
			view.hidden = name !== 'view';
		}
		if (edit) {
			edit.hidden = name !== 'edit';
		}
	}

	function snapshotForm(form) {
		var out = {};
		qsa(form, 'input, select').forEach(function (field) {
			if (field.name) {
				out[field.name] = field.value;
			}
		});
		return out;
	}

	function restoreForm(form, snap) {
		Object.keys(snap).forEach(function (name) {
			var field = form.elements.namedItem(name);
			if (field && 'value' in field) {
				field.value = snap[name];
			}
		});
	}

	function bind(root) {
		var form = qs(root, '[data-profile-form]');
		var editBtn = qs(root, '[data-action="edit"]');
		var snap = null;

		if (editBtn) {
			editBtn.addEventListener('click', function () {
				if (form) {
					snap = snapshotForm(form);
				}
				msg(root, '', false);
				showPanel(root, 'edit');
				var first = form && qs(form, 'input:not([readonly]), select');
				if (first && first.focus) {
					first.focus();
				}
			});
		}

		qsa(root, '[data-action="cancel"]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (form && snap) {
					restoreForm(form, snap);
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

				var btn = qs(form, '[data-action="save"]');
				if (btn) {
					btn.disabled = true;
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
						if (btn) {
							btn.disabled = false;
						}
						if (!data || !data.success) {
							var code = data && data.data && data.data.code;
							var t = cfg.i18n || {};
							var err =
								(data.data && data.data.message) ||
								(code && t[mapCode(code)]) ||
								t.genericError ||
								'Error';
							msg(root, err, true);
							return;
						}

						var p = data.data || {};
						var spanEmail = qs(root, '[data-field="email"]');
						var spanIface = qs(root, '[data-field="interface_label"]');
						if (spanEmail && p.email) {
							spanEmail.textContent = p.email;
						}
						if (spanIface && p.interface_label) {
							spanIface.textContent = p.interface_label;
						}

						if (form) {
							['old_password', 'new_password', 'new_password_confirm'].forEach(function (nm) {
								var el = form.elements.namedItem(nm);
								if (el && 'value' in el) {
									el.value = '';
								}
							});
						}

						msg(root, cfg.i18n.saved || '', false);
						window.location.reload();
					})
					.catch(function () {
						if (btn) {
							btn.disabled = false;
						}
						msg(root, (cfg.i18n && cfg.i18n.networkError) || '', true);
					});
			});
		}
	}

	function mapCode(code) {
		var map = {
			invalid_email: 'invalidEmail',
			email_in_use: 'emailInUse',
			password_mismatch: 'passwordMismatch',
			password_short: 'passwordShort',
			password_wrong: 'passwordWrong',
			lang_invalid: 'langInvalid',
		};
		return map[code] || 'genericError';
	}

	qsa(document, '[data-llm-profile]').forEach(bind);
})();
