/**
 * Accordion Consigli + popup prompt + mostra/nascondi frasi — Confronto modelli.
 */
(function () {
	'use strict';

	function closestToggle(el) {
		while (el && el !== document) {
			if (el.classList && el.classList.contains('llm-phrase-game__grammar-section-toggle')) {
				return el;
			}
			el = el.parentNode;
		}
		return null;
	}

	document.addEventListener('click', function (e) {
		var phrasesBtn = e.target && e.target.closest ? e.target.closest('.llm-mc-phrases-toggle') : null;
		if (phrasesBtn && phrasesBtn.closest && phrasesBtn.closest('.llm-mc-wrap')) {
			e.preventDefault();
			var panelId = phrasesBtn.getAttribute('aria-controls');
			var panel = panelId ? document.getElementById(panelId) : null;
			if (!panel) {
				return;
			}
			var open = phrasesBtn.getAttribute('aria-expanded') === 'true';
			var nextOpen = !open;
			phrasesBtn.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
			panel.hidden = !nextOpen;
			var label = nextOpen
				? (phrasesBtn.getAttribute('data-label-hide') || 'Nascondi frasi')
				: (phrasesBtn.getAttribute('data-label-show') || 'Visualizza frasi');
			phrasesBtn.textContent = label;
			return;
		}

		var btn = closestToggle(e.target);
		if (btn && btn.closest && btn.closest('.llm-mc-wrap')) {
			e.preventDefault();
			var sectionPanelId = btn.getAttribute('aria-controls');
			var sectionPanel = sectionPanelId ? document.getElementById(sectionPanelId) : null;
			if (!sectionPanel) {
				return;
			}
			var sectionOpen = btn.getAttribute('aria-expanded') === 'true';
			btn.setAttribute('aria-expanded', sectionOpen ? 'false' : 'true');
			sectionPanel.hidden = sectionOpen;
			return;
		}

		var promptBtn = e.target && e.target.closest ? e.target.closest('.llm-mc-prompt-btn') : null;
		if (promptBtn && promptBtn.closest && promptBtn.closest('.llm-mc-wrap')) {
			e.preventDefault();
			var id = promptBtn.getAttribute('data-prompt-target');
			var dlg = id ? document.getElementById(id) : null;
			if (dlg && typeof dlg.showModal === 'function') {
				dlg.showModal();
			} else if (dlg) {
				dlg.setAttribute('open', 'open');
			}
		}
	});
})();
