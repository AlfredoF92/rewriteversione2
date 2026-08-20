/**
 * Solo TTS dell'introduzione hero. Non tocca il gioco frasi.
 */
(function () {
	'use strict';

	var current = null;
	var currentBtn = null;

	function stop() {
		if (window.speechSynthesis) {
			window.speechSynthesis.cancel();
		}
		if (currentBtn) {
			currentBtn.classList.remove('is-playing');
			currentBtn = null;
		}
		current = null;
	}

	document.addEventListener('click', function (event) {
		var btn = event.target.closest('.llm-story-hero__listen');
		if (!btn) {
			return;
		}

		if (currentBtn === btn) {
			stop();
			return;
		}

		stop();

		var text = (btn.getAttribute('data-intro') || '').trim();
		if (!text || !window.speechSynthesis || typeof window.SpeechSynthesisUtterance === 'undefined') {
			return;
		}

		var utter = new window.SpeechSynthesisUtterance(text);
		utter.lang = btn.getAttribute('data-lang') || 'it-IT';
		utter.onend = function () {
			if (currentBtn === btn) {
				btn.classList.remove('is-playing');
				currentBtn = null;
				current = null;
			}
		};
		utter.onerror = stop;

		current = utter;
		currentBtn = btn;
		btn.classList.add('is-playing');
		window.speechSynthesis.speak(utter);
	});
})();
