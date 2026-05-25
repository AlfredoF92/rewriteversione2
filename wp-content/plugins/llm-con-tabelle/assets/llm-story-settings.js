/* llm-story-settings.js */
(function () {
	'use strict';

	function init() {
		document.addEventListener('click', function (e) {
			var toggle = e.target.closest('.llm-story-settings__toggle');
			var restartBtn = e.target.closest('.llm-story-settings__restart-btn');

			if (toggle) {
				handleToggle(toggle);
			} else if (restartBtn) {
				handleRestart(restartBtn);
			}
		});
	}

	function handleToggle(btn) {
		var expanded = btn.getAttribute('aria-expanded') === 'true';
		var panelId = btn.getAttribute('aria-controls');
		var panel = panelId ? document.getElementById(panelId) : btn.closest('.llm-story-settings').querySelector('.llm-story-settings__panel');
		if (!panel) { return; }

		if (expanded) {
			btn.setAttribute('aria-expanded', 'false');
			panel.hidden = true;
		} else {
			btn.setAttribute('aria-expanded', 'true');
			panel.hidden = false;
		}
	}

	function handleRestart(btn) {
		var cfg = (typeof window.llmStorySettings !== 'undefined') ? window.llmStorySettings : null;
		if (!cfg) { return; }

		var storyId = btn.dataset.storyId || btn.closest('[data-story-id]').dataset.storyId;
		if (!storyId) { return; }

		var confirmText = cfg.restartConfirm || 'Ricominciare dall\'inizio?';
		if (!window.confirm(confirmText)) { return; }

		var panel = btn.closest('.llm-story-settings__panel');
		var msgEl = panel ? panel.querySelector('.llm-story-settings__msg') : null;

		btn.disabled = true;

		var body = new URLSearchParams();
		body.append('action', cfg.action);
		body.append('nonce', cfg.nonce);
		body.append('story_id', storyId);

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString(),
		})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (data.success) {
					if (msgEl) {
						msgEl.textContent = cfg.restartedMsg || 'Ricarico...';
					}
					setTimeout(function () {
						window.location.reload();
					}, 900);
				} else {
					btn.disabled = false;
					if (msgEl) {
						msgEl.textContent = (data.data && data.data.message) ? data.data.message : 'Errore.';
					}
				}
			})
			.catch(function () {
				btn.disabled = false;
				if (msgEl) { msgEl.textContent = 'Errore di rete.'; }
			});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
