/**
 * Checklist pubblicazione — popup prompt / frasi (admin).
 */
(function () {
	'use strict';

	var cfg = window.llmStoryChecklist || null;
	if (!cfg || !cfg.ajaxUrl) {
		return;
	}

	var modal = null;
	var titleEl = null;
	var bodyEl = null;
	var copyBtn = null;
	var fileEl = null;

	function i18n(key, fallback) {
		return (cfg.i18n && cfg.i18n[key]) || fallback || key;
	}

	function ensureModal() {
		if (modal) {
			return modal;
		}
		modal = document.createElement('div');
		modal.className = 'llm-checklist-prompt-modal';
		modal.hidden = true;
		modal.innerHTML =
			'<div class="llm-checklist-prompt-modal__backdrop" data-llm-checklist-close="1"></div>' +
			'<div class="llm-checklist-prompt-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="llm-checklist-prompt-title">' +
			'<div class="llm-checklist-prompt-modal__head">' +
			'<div class="llm-checklist-prompt-modal__head-text">' +
			'<h2 id="llm-checklist-prompt-title" class="llm-checklist-prompt-modal__title"></h2>' +
			'<p class="llm-checklist-prompt-modal__file" hidden></p>' +
			'</div>' +
			'<div class="llm-checklist-prompt-modal__actions">' +
			'<button type="button" class="llm-game-theme__btn llm-checklist-prompt-modal__copy"></button>' +
			'<button type="button" class="llm-game-theme__btn llm-checklist-prompt-modal__close" data-llm-checklist-close="1" aria-label="' +
			String(i18n('close', 'Chiudi')).replace(/"/g, '&quot;') +
			'">&times;</button>' +
			'</div></div>' +
			'<pre class="llm-checklist-prompt-modal__body" tabindex="0"></pre>' +
			'</div>';
		document.body.appendChild(modal);
		titleEl = modal.querySelector('.llm-checklist-prompt-modal__title');
		bodyEl = modal.querySelector('.llm-checklist-prompt-modal__body');
		copyBtn = modal.querySelector('.llm-checklist-prompt-modal__copy');
		fileEl = modal.querySelector('.llm-checklist-prompt-modal__file');
		copyBtn.textContent = i18n('copy', 'Copia');
		copyBtn.addEventListener('click', function () {
			var text = bodyEl ? bodyEl.textContent || '' : '';
			if (!text) {
				return;
			}
			function ok() {
				copyBtn.textContent = i18n('copied', 'Copiato');
				window.setTimeout(function () {
					copyBtn.textContent = i18n('copy', 'Copia');
				}, 1600);
			}
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(ok).catch(function () {
					fallbackCopy(text, ok);
				});
			} else {
				fallbackCopy(text, ok);
			}
		});
		modal.addEventListener('click', function (ev) {
			var t = ev.target;
			if (t && t.getAttribute && t.getAttribute('data-llm-checklist-close')) {
				closeModal();
			}
		});
		document.addEventListener('keydown', function (ev) {
			if (ev.key === 'Escape' && modal && !modal.hidden) {
				closeModal();
			}
		});
		return modal;
	}

	function fallbackCopy(text, ok) {
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.setAttribute('readonly', 'readonly');
		ta.style.position = 'fixed';
		ta.style.left = '-9999px';
		document.body.appendChild(ta);
		ta.select();
		try {
			document.execCommand('copy');
			ok();
		} catch (e) {
			/* ignore */
		}
		document.body.removeChild(ta);
	}

	function openModal(title, loading) {
		ensureModal();
		titleEl.textContent = title || i18n('title', 'Prompt');
		bodyEl.textContent = loading ? i18n('loading', 'Caricamento…') : '';
		if (fileEl) {
			fileEl.hidden = true;
			fileEl.textContent = '';
		}
		copyBtn.disabled = !!loading;
		modal.hidden = false;
		document.body.classList.add('llm-checklist-prompt-open');
	}

	function closeModal() {
		if (!modal) {
			return;
		}
		modal.hidden = true;
		document.body.classList.remove('llm-checklist-prompt-open');
	}

	function showPrompt(itemKey) {
		openModal(i18n('title', 'Prompt'), true);
		var body = new FormData();
		body.append('action', 'llm_story_checklist_prompt');
		body.append('nonce', cfg.nonce || '');
		body.append('item_key', itemKey);
		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (json) {
				if (!json || !json.success || !json.data || typeof json.data.text !== 'string') {
					bodyEl.textContent = i18n('error', 'Prompt non disponibile.');
					copyBtn.disabled = true;
					return;
				}
				bodyEl.textContent = json.data.text;
				copyBtn.disabled = false;
				if (fileEl && json.data.file) {
					fileEl.hidden = false;
					fileEl.textContent = json.data.file;
				}
				bodyEl.focus();
			})
			.catch(function () {
				bodyEl.textContent = i18n('error', 'Prompt non disponibile.');
				copyBtn.disabled = true;
			});
	}

	function showInfo(itemKey) {
		openModal(i18n('titleInfo', 'Info'), true);
		var body = new FormData();
		body.append('action', 'llm_story_checklist_info');
		body.append('nonce', cfg.nonce || '');
		body.append('item_key', itemKey);
		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (json) {
				if (!json || !json.success || !json.data || typeof json.data.text !== 'string') {
					bodyEl.textContent = i18n('errorInfo', 'Info non disponibile.');
					copyBtn.disabled = true;
					return;
				}
				bodyEl.textContent = json.data.text;
				copyBtn.disabled = false;
				if (fileEl && json.data.file) {
					fileEl.hidden = false;
					fileEl.textContent = json.data.file;
				}
				bodyEl.focus();
			})
			.catch(function () {
				bodyEl.textContent = i18n('errorInfo', 'Info non disponibile.');
				copyBtn.disabled = true;
			});
	}

	function showPhrases(mode, storyId) {
		var title =
			mode === 'alt' ? i18n('titleAlt', 'Frasi + alternative') : i18n('titlePhrases', 'Frasi');
		openModal(title, true);
		var body = new FormData();
		body.append('action', 'llm_story_checklist_phrases');
		body.append('nonce', cfg.nonce || '');
		body.append('story_id', String(storyId || ''));
		body.append('mode', mode === 'alt' ? 'alt' : 'plain');
		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (json) {
				if (!json || !json.success || !json.data || typeof json.data.text !== 'string') {
					bodyEl.textContent = i18n('errorPhrases', 'Frasi non disponibili.');
					copyBtn.disabled = true;
					return;
				}
				bodyEl.textContent = json.data.text;
				copyBtn.disabled = false;
				bodyEl.focus();
			})
			.catch(function () {
				bodyEl.textContent = i18n('errorPhrases', 'Frasi non disponibili.');
				copyBtn.disabled = true;
			});
	}

	document.addEventListener('click', function (ev) {
		var phrasesBtn =
			ev.target && ev.target.closest ? ev.target.closest('[data-llm-checklist-phrases]') : null;
		if (phrasesBtn) {
			ev.preventDefault();
			var mode = phrasesBtn.getAttribute('data-llm-checklist-phrases') || 'plain';
			var sid =
				phrasesBtn.getAttribute('data-story-id') ||
				(phrasesBtn.closest('[data-story-id]') &&
					phrasesBtn.closest('[data-story-id]').getAttribute('data-story-id')) ||
				'';
			if (!sid) {
				return;
			}
			showPhrases(mode, sid);
			return;
		}
		var infoBtn = ev.target && ev.target.closest ? ev.target.closest('[data-llm-checklist-info]') : null;
		if (infoBtn) {
			ev.preventDefault();
			var infoKey = infoBtn.getAttribute('data-llm-checklist-info') || '';
			if (!infoKey) {
				return;
			}
			showInfo(infoKey);
			return;
		}
		var btn = ev.target && ev.target.closest ? ev.target.closest('[data-llm-checklist-prompt]') : null;
		if (!btn) {
			return;
		}
		ev.preventDefault();
		var key = btn.getAttribute('data-llm-checklist-prompt') || '';
		if (!key) {
			return;
		}
		showPrompt(key);
	});
})();
