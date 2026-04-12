(function () {
	'use strict';

	function qs(root, sel) {
		return root.querySelector(sel);
	}

	function stripTagsHtml(s) {
		return String(s || '').replace(/<[^>]*>/g, '');
	}

	function normalizeSentence(s) {
		s = stripTagsHtml(s).toLowerCase();
		s = s.replace(/[^\p{L}\p{N}\s]+/gu, ' ');
		s = s.replace(/\s+/g, ' ').trim();
		return s;
	}

	function tokenizeWords(s) {
		var n = normalizeSentence(s);
		if (!n) {
			return [];
		}
		return n.split(/\s+/).filter(Boolean);
	}

	/** Allineato a PHP similar_text (somma caratteri comuni ricorsiva). */
	function similarTextMatches(first, second) {
		var pos1 = 0;
		var pos2 = 0;
		var max = 0;
		var p;
		var q;
		var l;
		for (p = 0; p < first.length; p++) {
			for (q = 0; q < second.length; q++) {
				l = 0;
				while (
					p + l < first.length &&
					q + l < second.length &&
					first.charAt(p + l) === second.charAt(q + l)
				) {
					l++;
				}
				if (l > max) {
					max = l;
					pos1 = p;
					pos2 = q;
				}
			}
		}
		var sum = max;
		if (max) {
			if (pos1 > 0 && pos2 > 0) {
				sum += similarTextMatches(
					first.substring(0, pos1),
					second.substring(0, pos2)
				);
			}
			if (pos1 + max < first.length && pos2 + max < second.length) {
				sum += similarTextMatches(
					first.substring(pos1 + max),
					second.substring(pos2 + max)
				);
			}
		}
		return sum;
	}

	function similarTextPercent(first, second) {
		if (!first.length && !second.length) {
			return 100;
		}
		if (!first.length || !second.length) {
			return 0;
		}
		var sum = similarTextMatches(first, second);
		return (2 * sum * 100) / (first.length + second.length);
	}

	function referenceWordsFoundRatio(userText, referenceText) {
		var refWords = tokenizeWords(referenceText);
		var userWords = tokenizeWords(userText);
		if (!refWords.length) {
			return 1;
		}
		var userSet = {};
		var i;
		for (i = 0; i < userWords.length; i++) {
			userSet[userWords[i]] = true;
		}
		var hits = 0;
		for (i = 0; i < refWords.length; i++) {
			if (userSet[refWords[i]]) {
				hits++;
			}
		}
		return hits / refWords.length;
	}

	function phase1PassesLocal(userText, targetText, minRatio) {
		return referenceWordsFoundRatio(userText, targetText) >= minRatio;
	}

	function phase2PassesLocal(userText, targetText, minSimilar, minWordRatio) {
		var u = normalizeSentence(userText);
		var r = normalizeSentence(targetText);
		if (!r) {
			return true;
		}
		if (!u) {
			return false;
		}
		return u === r;
	}

	function init(root) {
		if (!root || !window.llmPhraseGame) {
			return;
		}

		var cfg = window.llmPhraseGame;
		var phrases = cfg.phrases || [];
		if (!phrases.length) {
			return;
		}

		var storyId = cfg.storyId;
		var nonce = cfg.nonce;
		var ajaxUrl = cfg.ajaxUrl;
		var i18n = cfg.i18n || {};
		var targetLang = cfg.targetLangLabel || '';
		var speechLang = cfg.speechLang || 'en-US';

		var valCfg = cfg.validation || {};
		var PHASE1_MIN =
			valCfg.phase1MinRatio !== undefined ? Number(valCfg.phase1MinRatio) : 0.2;
		var PHASE2_SIM =
			valCfg.phase2MinSimilar !== undefined ? Number(valCfg.phase2MinSimilar) : 68;
		var PHASE2_WR =
			valCfg.phase2MinWordRatio !== undefined
				? Number(valCfg.phase2MinWordRatio)
				: 0.82;

		var storyEl = qs(root, '.llm-phrase-game__story');
		var progressEl = qs(root, '.llm-phrase-game__progress');
		var phase1 = qs(root, '.llm-phrase-game__phase--1');
		var phase2 = qs(root, '.llm-phrase-game__phase--2');
		var ifaceEl = qs(root, '.llm-phrase-game__interface');
		var promptTrans = qs(root, '.llm-phrase-game__prompt--translate');
		var promptRewrite = qs(root, '.llm-phrase-game__prompt--rewrite');
		var input1 = qs(root, '.llm-phrase-game__input--1');
		var input2 = qs(root, '.llm-phrase-game__input--2');
		var btn1 = qs(root, '.llm-phrase-game__btn--continue1');
		var btn2 = qs(root, '.llm-phrase-game__btn--continue2');
		var messageEl = qs(root, '.llm-phrase-game__message');
		var messagePhase2El = qs(root, '.llm-phrase-game__message-phase2');
		var analysisEl = qs(root, '.llm-phrase-game__analysis');
		var grammarEl = qs(root, '.llm-phrase-game__grammar');
		var targetShow = qs(root, '.llm-phrase-game__target');
		var altShow = qs(root, '.llm-phrase-game__alt');
		var bravoEl = qs(root, '.llm-phrase-game__bravo');
		var labelMainEl = qs(root, '.llm-phrase-game__label-main');
		var labelAltEl = qs(root, '.llm-phrase-game__label-alt');
		var doneEl = qs(root, '.llm-phrase-game__done');
		var cardEl = qs(root, '.llm-phrase-game__card');
		var yourPhraseWrap = qs(root, '.llm-phrase-game__your-phrase-wrap');
		var yourPhraseText = qs(root, '.llm-phrase-game__your-phrase-text');
		var mic1 = qs(root, '.llm-phrase-game__mic--1');
		var mic2 = qs(root, '.llm-phrase-game__mic--2');
		var listenTargetBtn = qs(root, '.llm-phrase-game__listen-target');
		var composePhase1 = qs(root, '.llm-phrase-game__compose--phase1');
		var composePhase2 = qs(root, '.llm-phrase-game__compose--phase2');

		function enterStoryFocusMode() {
			root.classList.add('llm-phrase-game--story-focus');
		}

		function leaveStoryFocusMode() {
			root.classList.remove('llm-phrase-game--story-focus');
		}

		var COMPOSE_FADE_MS = 520;

		function setComposePhaseVisible(phaseNum, visible) {
			var el = phaseNum === 1 ? composePhase1 : composePhase2;
			if (!el) {
				return;
			}
			el.classList.toggle('llm-phrase-game__compose--visible', !!visible);
		}

		var phraseIx = 0;
		var savedPhraseIndexOnLoad =
			cfg.savedPhraseIndex !== undefined && cfg.savedPhraseIndex !== null
				? parseInt(cfg.savedPhraseIndex, 10)
				: 0;
		if (isNaN(savedPhraseIndexOnLoad)) {
			savedPhraseIndexOnLoad = 0;
		}

		var speechRec = null;
		var speechBase = '';
		var speechFinals = '';
		var activeMicTa = null;
		var activeMicBtn = null;

		var TTS_SLOW_RATE = 0.78;

		var bravoSourceText = bravoEl ? String(bravoEl.textContent || '').trim() : '';
		var analysisStreamRun = 0;
		var storyStreamRun = 0;
		var phase2MessageRun = 0;
		/** Millisecondi tra un carattere e il successivo (battitura più lenta = valore più alto). */
		var TYPE_TICK_MS = 36;
		var phraseIntroRun = 0;

		function cancelPhraseIntro() {
			phraseIntroRun++;
		}

		function cancelAnalysisStream() {
			analysisStreamRun++;
		}

		function cancelStoryStream() {
			storyStreamRun++;
		}

		function cancelPhase2MessageStream() {
			phase2MessageRun++;
		}

		function streamAlive(run) {
			return analysisStreamRun === run;
		}

		function streamGap() {
			return Promise.resolve();
		}

		function sleepMs(ms) {
			return new Promise(function (resolve) {
				setTimeout(resolve, ms);
			});
		}

		function typewriterInto(el, text, isAlive) {
			return new Promise(function (resolve) {
				if (!el) {
					resolve();
					return;
				}
				var s = String(text || '');
				el.textContent = '';
				if (!s) {
					resolve();
					return;
				}
				var node = document.createTextNode('');
				var cursor = document.createElement('span');
				cursor.className = 'llm-phrase-game__cursor';
				cursor.setAttribute('aria-hidden', 'true');
				el.appendChild(node);
				el.appendChild(cursor);
				var i = 0;
				function tick() {
					if (!isAlive()) {
						try {
							el.removeChild(cursor);
						} catch (e) {
							/* ignore */
						}
						resolve();
						return;
					}
					if (i >= s.length) {
						try {
							el.removeChild(cursor);
						} catch (e2) {
							/* ignore */
						}
						resolve();
						return;
					}
					i += 1;
					node.textContent = s.slice(0, i);
					setTimeout(tick, TYPE_TICK_MS);
				}
				tick();
			});
		}

		function prepareAnalysisStreamLayout() {
			if (bravoEl) {
				bravoEl.textContent = '';
			}
			grammarEl.textContent = '';
			targetShow.textContent = '';
			altShow.textContent = '';
			if (labelMainEl) {
				labelMainEl.style.opacity = '0';
			}
			if (labelAltEl) {
				labelAltEl.style.opacity = '0';
			}
			if (promptRewrite) {
				promptRewrite.textContent = '';
			}
		}

		function runAnalysisTypestream(opts) {
			var run = ++analysisStreamRun;
			var yourText = opts.yourText != null ? String(opts.yourText) : '';
			var skipYour = !!opts.skipYourPhrase;
			var skipBravo = !!opts.skipBravo;
			var grammar = opts.grammar != null ? String(opts.grammar) : '';
			var target = opts.target != null ? String(opts.target) : '';
			var alt = opts.alt != null ? String(opts.alt) : '';
			var hasBravo = !skipBravo && bravoEl && bravoSourceText;

			prepareAnalysisStreamLayout();
			setComposePhaseVisible(2, false);

			var chain = Promise.resolve();

			if (skipYour && skipBravo) {
				chain = chain.then(function () {
					if (!streamAlive(run)) {
						return;
					}
					var nextResume = grammar
						? grammarEl
						: target
							? targetShow
							: alt
								? altShow
								: null;
					if (nextResume) {
						return streamGap(run, nextResume);
					}
				});
			}

			if (!skipYour && yourPhraseText && yourPhraseWrap) {
				yourPhraseWrap.hidden = false;
				chain = chain.then(function () {
					if (!streamAlive(run)) {
						return;
					}
					return streamGap(run, yourPhraseText);
				});
				chain = chain.then(function () {
					return typewriterInto(yourPhraseText, yourText, function () {
						return streamAlive(run);
					});
				});
				chain = chain.then(function () {
					if (!streamAlive(run)) {
						return;
					}
					if (hasBravo || grammar || target || alt) {
						var nextAfterYour = hasBravo
							? bravoEl
							: grammar
								? grammarEl
								: target
									? targetShow
									: altShow;
						return streamGap(run, nextAfterYour);
					}
				});
			}

			if (hasBravo) {
				chain = chain.then(function () {
					if (!streamAlive(run)) {
						return;
					}
					return typewriterInto(bravoEl, bravoSourceText, function () {
						return streamAlive(run);
					});
				});
				chain = chain.then(function () {
					if (!streamAlive(run)) {
						return;
					}
					if (grammar || target || alt) {
						var nextAfterBravo = grammar
							? grammarEl
							: target
								? targetShow
								: altShow;
						return streamGap(run, nextAfterBravo);
					}
				});
			}

			if (grammar) {
				chain = chain.then(function () {
					if (!streamAlive(run)) {
						return;
					}
					return typewriterInto(grammarEl, grammar, function () {
						return streamAlive(run);
					});
				});
				chain = chain.then(function () {
					if (!streamAlive(run)) {
						return;
					}
					if (target || alt) {
						var nextAfterGrammar = target ? targetShow : altShow;
						return streamGap(run, nextAfterGrammar);
					}
				});
			}

			if (target) {
				chain = chain.then(function () {
					if (!streamAlive(run)) {
						return;
					}
					if (labelMainEl) {
						labelMainEl.style.opacity = '1';
					}
					return typewriterInto(targetShow, target, function () {
						return streamAlive(run);
					});
				});
				chain = chain.then(function () {
					if (!streamAlive(run)) {
						return;
					}
					if (alt) {
						return streamGap(run, altShow);
					}
				});
			} else if (labelMainEl) {
				labelMainEl.style.opacity = '1';
			}

			if (alt) {
				chain = chain.then(function () {
					if (!streamAlive(run)) {
						return;
					}
					if (labelAltEl) {
						labelAltEl.style.opacity = '1';
					}
					return typewriterInto(altShow, alt, function () {
						return streamAlive(run);
					});
				});
			} else if (labelAltEl) {
				labelAltEl.style.opacity = '1';
			}

			chain = chain.then(function () {
				if (!streamAlive(run)) {
					return;
				}
				if (!promptRewrite) {
					return;
				}
				return streamGap(run, promptRewrite);
			});
			chain = chain.then(function () {
				if (!streamAlive(run)) {
					return;
				}
				if (!promptRewrite) {
					return;
				}
				return typewriterInto(promptRewrite, i18n.rewritePrompt || '', function () {
					return streamAlive(run);
				});
			});

			return chain.then(function () {
				if (!streamAlive(run)) {
					return;
				}
				setComposePhaseVisible(2, true);
				if (btn2) {
					btn2.disabled = false;
				}
				if (input2) {
					input2.readOnly = false;
					setTimeout(function () {
						if (streamAlive(run) && input2) {
							input2.focus();
						}
					}, COMPOSE_FADE_MS);
				}
			});
		}

		if (window.speechSynthesis) {
			window.speechSynthesis.onvoiceschanged = function () {
				/* Chrome/Safari: carica elenco voci */
			};
			window.speechSynthesis.getVoices();
		}

		if (cfg.completedStoryLines && cfg.completedStoryLines.length) {
			cfg.completedStoryLines.forEach(function (line) {
				var block = document.createElement('p');
				block.className = 'llm-phrase-game__story-line';
				block.textContent = line;
				storyEl.appendChild(block);
			});
		}

		if (cfg.gameFinished) {
			cardEl.hidden = true;
			doneEl.hidden = false;
			return;
		}

		if (cfg.savedPhraseIndex !== undefined && cfg.savedPhraseIndex !== null) {
			phraseIx = parseInt(cfg.savedPhraseIndex, 10);
			if (isNaN(phraseIx)) {
				phraseIx = 0;
			}
		}

		function stopSpeech() {
			if (speechRec) {
				try {
					speechRec.stop();
				} catch (e) {
					/* ignore */
				}
				speechRec = null;
			}
			if (activeMicTa) {
				activeMicTa.classList.remove('llm-phrase-game__input--listening');
				var sh = activeMicTa.closest('.llm-phrase-game__input-shell');
				if (sh) {
					sh.classList.remove('llm-phrase-game__input-shell--listening');
				}
			}
			if (activeMicBtn) {
				activeMicBtn.classList.remove('llm-phrase-game__mic--active');
			}
			activeMicTa = null;
			activeMicBtn = null;
			speechFinals = '';
		}

		function cancelTts() {
			if (!window.speechSynthesis) {
				return;
			}
			try {
				window.speechSynthesis.cancel();
			} catch (e) {
				/* ignore */
			}
			if (listenTargetBtn) {
				listenTargetBtn.classList.remove('llm-phrase-game__listen-target--playing');
			}
		}

		function normalizeLangTag(l) {
			return String(l || '')
				.replace(/_/g, '-')
				.toLowerCase();
		}

		function pickVoiceForLang(lang) {
			var synth = window.speechSynthesis;
			if (!synth || typeof synth.getVoices !== 'function') {
				return null;
			}
			var voices = synth.getVoices();
			if (!voices || !voices.length) {
				return null;
			}
			var want = normalizeLangTag(lang || 'en-US');
			var prim = want.split('-')[0];
			function matches(v) {
				var vl = normalizeLangTag(v.lang);
				return vl === want || vl.indexOf(prim + '-') === 0 || vl === prim;
			}
			var candidates = voices.filter(matches);
			if (!candidates.length) {
				candidates = voices.slice();
			}
			var prefs = [
				'neural',
				'premium',
				'natural',
				'enhanced',
				'google',
				'microsoft',
				'online',
			];
			var p;
			var i;
			var n;
			for (p = 0; p < prefs.length; p++) {
				for (i = 0; i < candidates.length; i++) {
					n = (candidates[i].name || '').toLowerCase();
					if (n.indexOf(prefs[p]) !== -1) {
						return candidates[i];
					}
				}
			}
			return candidates[0] || null;
		}

		function speakTargetTranslation(text) {
			if (!window.speechSynthesis || !listenTargetBtn) {
				return;
			}
			var trimmed = String(text || '').trim();
			if (!trimmed) {
				return;
			}
			cancelTts();
			var ut = new SpeechSynthesisUtterance(trimmed);
			ut.lang = speechLang;
			ut.rate = TTS_SLOW_RATE;
			ut.pitch = 1;
			var v = pickVoiceForLang(speechLang);
			if (v) {
				ut.voice = v;
			}
			ut.onend = function () {
				if (listenTargetBtn) {
					listenTargetBtn.classList.remove('llm-phrase-game__listen-target--playing');
				}
			};
			ut.onerror = function () {
				if (listenTargetBtn) {
					listenTargetBtn.classList.remove('llm-phrase-game__listen-target--playing');
				}
			};
			listenTargetBtn.classList.add('llm-phrase-game__listen-target--playing');
			window.speechSynthesis.speak(ut);
		}

		function syncListenTargetUi() {
			if (!listenTargetBtn) {
				return;
			}
			var p = phrases[phraseIx];
			var hasSynth = typeof window.speechSynthesis !== 'undefined' && window.speechSynthesis;
			var hasText = p && String(p.target || '').trim();
			listenTargetBtn.hidden = !hasSynth || !hasText || phase1.hidden;
		}

		function startSpeech(textarea, micBtn) {
			var Rec = window.SpeechRecognition || window.webkitSpeechRecognition;
			if (!Rec || speechRec) {
				return;
			}
			stopSpeech();
			cancelTts();
			speechBase = textarea.value;
			if (speechBase.length && !/\s$/.test(speechBase)) {
				speechBase += ' ';
			}
			speechFinals = '';
			speechRec = new Rec();
			speechRec.lang = speechLang;
			speechRec.continuous = true;
			speechRec.interimResults = true;
			speechRec.onresult = function (ev) {
				var interim = '';
				var i;
				for (i = ev.resultIndex; i < ev.results.length; i++) {
					var tr = ev.results[i][0].transcript;
					if (ev.results[i].isFinal) {
						speechFinals += tr;
					} else {
						interim += tr;
					}
				}
				textarea.value = speechBase + speechFinals + interim;
			};
			speechRec.onerror = function () {
				stopSpeech();
			};
			try {
				speechRec.start();
			} catch (e) {
				speechRec = null;
				return;
			}
			activeMicTa = textarea;
			activeMicBtn = micBtn;
			textarea.classList.add('llm-phrase-game__input--listening');
			var shell = textarea.closest('.llm-phrase-game__input-shell');
			if (shell) {
				shell.classList.add('llm-phrase-game__input-shell--listening');
			}
			micBtn.classList.add('llm-phrase-game__mic--active');
		}

		function bindMic(micBtn, textarea) {
			if (!micBtn || !textarea) {
				return;
			}
			if (!window.SpeechRecognition && !window.webkitSpeechRecognition) {
				micBtn.hidden = true;
				return;
			}

			function onUp() {
				if (activeMicTa === textarea) {
					stopSpeech();
				}
			}

			micBtn.addEventListener('pointerdown', function (e) {
				e.preventDefault();
				startSpeech(textarea, micBtn);
			});
			micBtn.addEventListener('pointerup', onUp);
			micBtn.addEventListener('pointercancel', onUp);
			micBtn.addEventListener('pointerleave', function (e) {
				if (e.buttons === 0) {
					onUp();
				}
			});
		}

		document.addEventListener('pointerup', function () {
			stopSpeech();
		});

		bindMic(mic1, input1);
		bindMic(mic2, input2);
		if (input2) {
			input2.addEventListener('input', function () {
				if (!messagePhase2El) {
					return;
				}
				if (
					messagePhase2El.classList.contains('llm-phrase-game__message-phase2--error') ||
					messagePhase2El.classList.contains('llm-phrase-game__message-phase2--pending')
				) {
					setMessagePhase2('', '');
				}
			});
		}

		if (listenTargetBtn) {
			listenTargetBtn.addEventListener('click', function () {
				if (listenTargetBtn.classList.contains('llm-phrase-game__listen-target--playing')) {
					cancelTts();
					return;
				}
				var p = phrases[phraseIx];
				speakTargetTranslation(p ? p.target : '');
			});
		}

		function t(key, a, b) {
			var s = i18n[key] || '';
			if (a !== undefined && b !== undefined) {
				return s.replace('%1$d', String(a)).replace('%2$d', String(b));
			}
			if (a !== undefined) {
				return s.replace('%s', String(a));
			}
			return s;
		}

		function runPhraseIntroTypewriter(ifaceText, promptText, introRunId) {
			if (!ifaceEl || !promptTrans) {
				return Promise.resolve();
			}
			ifaceEl.textContent = '';
			promptTrans.textContent = '';
			function aliveIntro() {
				return phraseIntroRun === introRunId;
			}
			return typewriterInto(ifaceEl, ifaceText, aliveIntro).then(function () {
				if (!aliveIntro()) {
					return;
				}
				return typewriterInto(promptTrans, promptText, aliveIntro);
			});
		}

		function setMessage(text, isError) {
			if (!messageEl) {
				return;
			}
			messageEl.textContent = text || '';
			messageEl.classList.toggle('llm-phrase-game__message--error', !!isError);
		}

		/** Messaggi solo per la fase 2 (secondo Continua): variant 'error' | 'success' | 'pending' | ''. */
		function setMessagePhase2(text, variant) {
			if (!messagePhase2El) {
				return;
			}
			cancelPhase2MessageStream();
			messagePhase2El.textContent = text || '';
			messagePhase2El.classList.toggle('llm-phrase-game__message-phase2--error', variant === 'error');
			messagePhase2El.classList.toggle('llm-phrase-game__message-phase2--success', variant === 'success');
			messagePhase2El.classList.toggle('llm-phrase-game__message-phase2--pending', variant === 'pending');
		}

		function setMessagePhase2Typewriter(text, variant) {
			if (!messagePhase2El) {
				return Promise.resolve();
			}
			cancelPhase2MessageStream();
			var run = phase2MessageRun;
			messagePhase2El.classList.toggle('llm-phrase-game__message-phase2--error', variant === 'error');
			messagePhase2El.classList.toggle('llm-phrase-game__message-phase2--success', variant === 'success');
			messagePhase2El.classList.toggle('llm-phrase-game__message-phase2--pending', false);
			return typewriterInto(messagePhase2El, text || '', function () {
				return phase2MessageRun === run;
			});
		}

		function showPhase(n) {
			phase1.hidden = n !== 1;
			phase2.hidden = n !== 2;
			syncListenTargetUi();
		}

		function resetAnalysis() {
			cancelAnalysisStream();
			cancelPhase2MessageStream();
			analysisEl.hidden = true;
			if (bravoEl) {
				bravoEl.textContent = '';
			}
			grammarEl.textContent = '';
			targetShow.textContent = '';
			altShow.textContent = '';
			if (labelMainEl) {
				labelMainEl.style.opacity = '';
			}
			if (labelAltEl) {
				labelAltEl.style.opacity = '';
			}
			if (yourPhraseWrap) {
				yourPhraseWrap.hidden = true;
			}
			if (yourPhraseText) {
				yourPhraseText.textContent = '';
			}
			if (input2) {
				input2.readOnly = false;
			}
			if (btn2) {
				btn2.disabled = false;
			}
			setMessagePhase2('', '');
		}

		function renderProgress() {
			progressEl.textContent = t('progress', phraseIx + 1, phrases.length);
		}

		function loadPhrase(resumeStep2) {
			leaveStoryFocusMode();
			cancelTts();
			cancelAnalysisStream();
			cancelStoryStream();
			cancelPhraseIntro();
			if (phraseIx >= phrases.length) {
				cardEl.hidden = true;
				doneEl.hidden = false;
				return;
			}
			var p = phrases[phraseIx];
			var useResume =
				resumeStep2 &&
				cfg.resumeAnalysis &&
				parseInt(cfg.savedStep, 10) === 2 &&
				phraseIx === savedPhraseIndexOnLoad;

			if (useResume) {
				setMessage('');
				setMessagePhase2('', '');
				input1.value = '';
				input2.value = '';
				ifaceEl.textContent = p.interface || '';
				promptTrans.textContent = t('translatePrompt', targetLang);
				if (yourPhraseWrap) {
					yourPhraseWrap.hidden = true;
				}
				if (yourPhraseText) {
					yourPhraseText.textContent = '';
				}
				analysisEl.hidden = false;
				showPhase(2);
				renderProgress();
				if (input2) {
					input2.readOnly = true;
				}
				if (btn2) {
					btn2.disabled = true;
				}
				runAnalysisTypestream({
					skipYourPhrase: true,
					skipBravo: true,
					grammar: cfg.resumeAnalysis.grammar || '',
					target: cfg.resumeAnalysis.target || '',
					alt: cfg.resumeAnalysis.alt || '',
				});
				return;
			}

			resetAnalysis();
			input1.value = '';
			input2.value = '';
			setMessage('');
			setMessagePhase2('', '');
			showPhase(1);
			setComposePhaseVisible(1, false);
			var introId = ++phraseIntroRun;
			if (btn1) {
				btn1.disabled = true;
			}
			if (input1) {
				input1.readOnly = true;
			}
			if (promptRewrite) {
				promptRewrite.textContent = '';
			}
			renderProgress();
			runPhraseIntroTypewriter(
				p.interface || '',
				t('translatePrompt', targetLang),
				introId
			).then(function () {
				if (phraseIntroRun !== introId) {
					return;
				}
				setComposePhaseVisible(1, true);
				if (btn1) {
					btn1.disabled = false;
				}
				if (input1) {
					input1.readOnly = false;
				}
				syncListenTargetUi();
			});
		}

		function postCheck(phase, userText, cb) {
			var body = new URLSearchParams();
			body.set('action', 'llm_phrase_game_check');
			body.set('nonce', nonce);
			body.set('story_id', String(storyId));
			body.set('phrase_index', String(phrases[phraseIx].index));
			body.set('phase', String(phase));
			body.set('user_text', userText);

			fetch(ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString(),
			})
				.then(function (r) {
					return r.json();
				})
				.then(function (json) {
					if (!json || typeof json !== 'object') {
						cb({ success: false, data: { message: i18n.ajaxError || '' } });
						return;
					}
					cb(json);
				})
				.catch(function () {
					cb({ success: false, data: { message: i18n.ajaxError || '' } });
				});
		}

		btn1.addEventListener('click', function () {
			stopSpeech();
			cancelTts();
			var txt = (input1.value || '').trim();
			if (!txt) {
				setMessage(i18n.empty || '', true);
				return;
			}
			var p = phrases[phraseIx];
			var targetRef = p && p.target != null ? String(p.target) : '';
			if (!phase1PassesLocal(txt, targetRef, PHASE1_MIN)) {
				setMessage(i18n.phase1Fail || '', true);
				return;
			}
			setMessage('');
			setMessagePhase2('', '');
			btn1.disabled = true;
			if (btn2) {
				btn2.disabled = true;
			}
			if (input2) {
				input2.readOnly = true;
				input2.value = '';
			}
			analysisEl.hidden = false;
			showPhase(2);
			runAnalysisTypestream({
				yourText: txt,
				grammar: (p && p.grammar) || '',
				target: targetRef,
				alt: (p && p.alt) || '',
			});
			postCheck(1, txt, function (json) {
				btn1.disabled = false;
				if (!json || !json.success) {
					cancelAnalysisStream();
					cancelPhraseIntro();
					resetAnalysis();
					showPhase(1);
					var prb = phrases[phraseIx];
					if (ifaceEl) {
						ifaceEl.textContent = prb ? prb.interface || '' : '';
					}
					if (promptTrans) {
						promptTrans.textContent = t('translatePrompt', targetLang);
					}
					setComposePhaseVisible(1, true);
					setMessagePhase2('', '');
					setMessage(
						(json && json.data && json.data.message) || i18n.ajaxError || '',
						true
					);
					return;
				}
			});
		});

		btn2.addEventListener('click', function () {
			stopSpeech();
			cancelTts();
			var txt = (input2.value || '').trim();
			if (!txt) {
				setMessagePhase2Typewriter(i18n.empty || '', 'error');
				return;
			}
			var p2 = phrases[phraseIx];
			var targetRef2 = p2 && p2.target != null ? String(p2.target) : '';
			if (!phase2PassesLocal(txt, targetRef2, PHASE2_SIM, PHASE2_WR)) {
				setMessagePhase2Typewriter(i18n.phase2Fail || '', 'error');
				return;
			}
			setMessagePhase2('', '');
			btn2.disabled = true;
			if (input2) {
				input2.readOnly = true;
			}
			/** Prima feedback positivo (già validato in locale); salvataggio server in parallelo ai 3s dopo il typewriter. */
			setMessagePhase2Typewriter(
				i18n.phase2StoryContinue || i18n.phase2Complete || '',
				'success'
			)
				.then(function () {
					var ajaxPromise = new Promise(function (resolve) {
						postCheck(2, txt, function (json) {
							resolve(json);
						});
					});
					return Promise.all([ajaxPromise, sleepMs(3000)]);
				})
				.then(function (pair) {
					var json = pair && pair[0];
					if (!json || !json.success) {
						btn2.disabled = false;
						if (input2) {
							input2.readOnly = false;
						}
						var msg =
							(json && json.data && json.data.message) || i18n.phase2Fail || '';
						setMessagePhase2Typewriter(msg, 'error');
						return;
					}
					var d = json.data || {};
					if (typeof window.llmUpdateStoryProgressBar === 'function' && d.phrases_total != null) {
						var doneBar = parseInt(d.phrases_done, 10);
						if (isNaN(doneBar)) {
							doneBar = 0;
						}
						var totalBar = parseInt(d.phrases_total, 10);
						if (isNaN(totalBar)) {
							totalBar = phrases.length;
						}
						window.llmUpdateStoryProgressBar(String(storyId), doneBar, totalBar);
					}
					var sentence = d.display_sentence || '';
					function advanceAfterPhrase2() {
						var scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
						leaveStoryFocusMode();
						resetAnalysis();
						if (d.has_more && d.next_index !== null && d.next_index !== undefined) {
							phraseIx = parseInt(d.next_index, 10);
							if (isNaN(phraseIx)) {
								phraseIx = phrases.length;
							}
							loadPhrase(false);
						} else {
							phraseIx = phrases.length;
							loadPhrase(false);
						}
						requestAnimationFrame(function () {
							window.scrollTo(0, scrollY);
							requestAnimationFrame(function () {
								window.scrollTo(0, scrollY);
							});
						});
					}
					if (sentence) {
						enterStoryFocusMode();
					}
					if (!sentence) {
						advanceAfterPhrase2();
						return;
					}
					var block = document.createElement('p');
					block.className = 'llm-phrase-game__story-line';
					storyEl.appendChild(block);
					storyEl.scrollTop = storyEl.scrollHeight;
					var sr = ++storyStreamRun;
					typewriterInto(block, sentence, function () {
						return storyStreamRun === sr;
					}).then(function () {
						if (storyStreamRun === sr) {
							advanceAfterPhrase2();
						} else {
							leaveStoryFocusMode();
						}
					});
				});
		});

		var startResume =
			parseInt(cfg.savedStep, 10) === 2 && cfg.resumeAnalysis;
		loadPhrase(!!startResume);
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.llm-phrase-game').forEach(function (el) {
			init(el);
		});
	});
})();
