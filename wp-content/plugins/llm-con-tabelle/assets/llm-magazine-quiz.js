/*
 * llm-magazine-quiz.js — quiz rivista: click risposta → spiegazione → avanti.
 */
(function (document) {
	'use strict';

	var LETTERS = ['A', 'B', 'C'];

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.textContent = String(str == null ? '' : str);
		return div.innerHTML;
	}

	function format(tpl, args) {
		var auto = 0;
		return String(tpl == null ? '' : tpl).replace(/%(?:(\d+)\$)?[sd]/g, function (match, pos) {
			var idx = pos ? parseInt(pos, 10) - 1 : auto++;
			return args && args[idx] != null ? String(args[idx]) : '';
		});
	}

	function readConfig(root) {
		var node = root.querySelector('.llm-mag-quiz__config');
		if (!node) {
			return null;
		}
		try {
			return JSON.parse(node.textContent || node.innerHTML || '');
		} catch (e) {
			return null;
		}
	}

	function createQuiz(root) {
		var cfg = readConfig(root);
		if (!cfg || !cfg.questions || !cfg.questions.length) {
			return;
		}

		var questions = cfg.questions;
		var i18n = cfg.i18n || {};
		var progressEl = root.querySelector('[data-quiz-progress]');
		var stageEl = root.querySelector('[data-quiz-stage]');
		var index = 0;
		var score = 0;
		var answered = false;

		function setProgress() {
			if (!progressEl) {
				return;
			}
			progressEl.textContent = format(i18n.progress || 'Domanda %1$d di %2$d', [
				index + 1,
				questions.length,
			]);
		}

		function renderQuestion() {
			answered = false;
			var q = questions[index];
			if (!q) {
				renderResult();
				return;
			}

			setProgress();
			if (progressEl) {
				progressEl.hidden = false;
			}

			var cat = q.category ? String(q.category) : '';
			var html = '';
			if (cat) {
				html += '<p class="llm-mag-quiz__category">' + escapeHtml(cat) + '</p>';
			}
			html += '<h4 class="llm-mag-quiz__question">' + escapeHtml(q.question || '') + '</h4>';
			html += '<div class="llm-mag-quiz__answers" role="group" aria-label="' + escapeHtml(i18n.pickAnswer || '') + '">';

			var answers = q.answers || [];
			for (var i = 0; i < answers.length; i++) {
				var a = answers[i] || {};
				html +=
					'<button type="button" class="llm-mag-quiz__answer" data-answer-index="' +
					i +
					'">' +
					'<span class="llm-mag-quiz__answer-letter">' +
					escapeHtml(LETTERS[i] || String(i + 1)) +
					'</span>' +
					'<span class="llm-mag-quiz__answer-text">' +
					escapeHtml(a.text || '') +
					'</span>' +
					'</button>';
			}
			html += '</div>';
			html += '<div class="llm-mag-quiz__feedback" data-quiz-feedback hidden></div>';
			html +=
				'<div class="llm-mag-quiz__actions" data-quiz-actions hidden>' +
				'<button type="button" class="llm-ui-btn llm-ui-btn--primary llm-mag-quiz__next" data-quiz-next></button>' +
				'</div>';

			stageEl.innerHTML = html;
		}

		function showFeedback(chosenIndex) {
			if (answered) {
				return;
			}
			answered = true;

			var q = questions[index];
			var correctIndex = typeof q.correct === 'number' ? q.correct : 0;
			var isCorrect = chosenIndex === correctIndex;
			if (isCorrect) {
				score += 1;
			}

			var buttons = stageEl.querySelectorAll('.llm-mag-quiz__answer');
			buttons.forEach(function (btn, i) {
				btn.disabled = true;
				btn.classList.add('is-disabled');
				if (i === correctIndex) {
					btn.classList.add('is-correct');
				}
				if (i === chosenIndex) {
					btn.classList.add('is-chosen');
					if (!isCorrect) {
						btn.classList.add('is-wrong');
					}
				}
			});

			var answers = q.answers || [];
			var chosen = answers[chosenIndex] || {};
			var feedbackEl = stageEl.querySelector('[data-quiz-feedback]');
			var actionsEl = stageEl.querySelector('[data-quiz-actions]');
			var nextBtn = stageEl.querySelector('[data-quiz-next]');

			if (feedbackEl) {
				var verdict = isCorrect
					? i18n.correct || 'Corretto!'
					: i18n.incorrect || 'Non esatto';
				var explanation = chosen.explanation ? String(chosen.explanation) : '';
				feedbackEl.hidden = false;
				feedbackEl.className =
					'llm-mag-quiz__feedback ' + (isCorrect ? 'is-correct' : 'is-wrong');
				feedbackEl.innerHTML =
					'<p class="llm-mag-quiz__verdict">' +
					escapeHtml(verdict) +
					'</p>' +
					(explanation
						? '<p class="llm-mag-quiz__explanation-label">' +
						  escapeHtml(i18n.explanation || 'Approfondimento') +
						  '</p><p class="llm-mag-quiz__explanation">' +
						  escapeHtml(explanation) +
						  '</p>'
						: '');
			}

			if (actionsEl && nextBtn) {
				var isLast = index >= questions.length - 1;
				nextBtn.textContent = isLast
					? i18n.finish || 'Vedi risultato'
					: i18n.next || 'Prossima domanda';
				actionsEl.hidden = false;
			}
		}

		function renderResult() {
			if (progressEl) {
				progressEl.hidden = true;
			}
			stageEl.innerHTML =
				'<div class="llm-mag-quiz__result">' +
				'<h4 class="llm-mag-quiz__result-title">' +
				escapeHtml(i18n.resultTitle || 'Quiz completato') +
				'</h4>' +
				'<p class="llm-mag-quiz__result-score">' +
				escapeHtml(
					format(i18n.resultScore || 'Hai risposto bene a %1$d su %2$d', [
						score,
						questions.length,
					])
				) +
				'</p>' +
				'<button type="button" class="llm-ui-btn llm-ui-btn--primary" data-quiz-restart>' +
				escapeHtml(i18n.restart || 'Rigioca') +
				'</button>' +
				'</div>';
		}

		root.addEventListener('click', function (e) {
			var t = e.target;
			if (!t || !t.closest) {
				return;
			}

			var answerBtn = t.closest('.llm-mag-quiz__answer');
			if (answerBtn && stageEl.contains(answerBtn) && !answerBtn.disabled) {
				var idx = parseInt(answerBtn.getAttribute('data-answer-index'), 10);
				if (!isNaN(idx)) {
					showFeedback(idx);
				}
				return;
			}

			if (t.closest('[data-quiz-next]')) {
				index += 1;
				if (index >= questions.length) {
					renderResult();
				} else {
					renderQuestion();
				}
				return;
			}

			if (t.closest('[data-quiz-restart]')) {
				index = 0;
				score = 0;
				renderQuestion();
			}
		});

		renderQuestion();
	}

	function boot() {
		document.querySelectorAll('[data-llm-mag-quiz]').forEach(createQuiz);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})(document);
