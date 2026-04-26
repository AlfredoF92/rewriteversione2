(function () {
	'use strict';

	/** Millisecondi tra un carattere e il successivo. */
	var TICK_MS = 55;

	/** Ritardo iniziale prima che parta la scrittura (ms). */
	var START_DELAY_MS = 400;

	/**
	 * Anima il testo di un elemento con effetto typewriter.
	 *
	 * @param {HTMLElement} el      Elemento target.
	 * @param {string}      text    Testo da scrivere.
	 * @param {Function}    [onDone] Callback opzionale al termine.
	 */
	function typewriter(el, text, onDone) {
		el.textContent = '';
		el.setAttribute('aria-busy', 'true');

		var cursor = document.createElement('span');
		cursor.className = 'llm-hero-typewriter__cursor';
		cursor.setAttribute('aria-hidden', 'true');
		el.appendChild(cursor);

		var node = document.createTextNode('');
		el.insertBefore(node, cursor);

		var i = 0;

		function tick() {
			if (i >= text.length) {
				// Animazione completata: rimuovi cursor e sblocca aria
				try { el.removeChild(cursor); } catch (e) { /* ignore */ }
				el.removeAttribute('aria-busy');
				el.classList.add('llm-hero-typewriter--done');
				if (typeof onDone === 'function') {
					onDone();
				}
				return;
			}
			i += 1;
			node.textContent = text.slice(0, i);
			setTimeout(tick, TICK_MS);
		}

		setTimeout(tick, START_DELAY_MS);
	}

	/**
	 * Inizializza tutti gli elementi con classe llm-hero-typewriter.
	 */
	function initAll() {
		var elements = document.querySelectorAll('.llm-hero-typewriter');
		elements.forEach(function (el) {
			// Il testo viene letto da data-text (impostato dal Dynamic Tag PHP).
			// Fallback: testo già presente nel DOM.
			var text = el.getAttribute('data-text') || el.textContent || '';
			text = text.trim();
			if (!text) {
				return;
			}
			typewriter(el, text);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		// DOM già pronto (es. script caricato con defer)
		initAll();
	}
})();
