(function () {
	'use strict';

	function qs(root, sel) {
		return root.querySelector(sel);
	}

	function stripTagsHtml(s) {
		return String(s || '').replace(/<[^>]*>/g, '');
	}

	/** Testo senza tag, spazi normalizzati (TTS, confronti sulla frase letta). */
	function plainSpeechText(s) {
		return stripTagsHtml(s).replace(/\s+/g, ' ').trim();
	}

	/**
	 * Spezza HTML in sequenza di tag (stringa completa) e blocchi di testo (animabili).
	 * Non usa il tag-split naive: un maggiore in attributo tra virgolette o un minore da confronto
	 * (es. "x < 3") romperebbero il buffer incrementale e l’analisi grammaticale resterebbe vuota.
	 *
	 * @param {string} html
	 * @returns {Array<{type:string,value:string}>}
	 */
	function splitHtmlChunks(html) {
		var s = String(html || '');
		var out = [];
		var n = s.length;
		var i = 0;
		while (i < n) {
			if (s.charCodeAt(i) !== 60) {
				var t0 = i;
				while (i < n && s.charCodeAt(i) !== 60) {
					i++;
				}
				if (i > t0) {
					out.push({ type: 'text', value: s.slice(t0, i) });
				}
				continue;
			}
			var rest = s.slice(i);
			var looksLikeTag =
				/^<\s*\/\s*[a-zA-Z]/.test(rest) || /^<[a-zA-Z!?]/.test(rest);
			if (!looksLikeTag) {
				out.push({ type: 'text', value: '<' });
				i++;
				continue;
			}
			var j = i + 1;
			var quote = '';
			while (j < n) {
				var ch = s.charAt(j);
				if (quote) {
					if (ch === quote) {
						quote = '';
					}
				} else {
					if (ch === '"' || ch === "'") {
						quote = ch;
					} else if (ch === '>') {
						out.push({ type: 'tag', value: s.slice(i, j + 1) });
						i = j + 1;
						break;
					}
				}
				j++;
			}
			if (j >= n) {
				out.push({ type: 'text', value: s.slice(i) });
				break;
			}
		}
		return out;
	}

	/**
	 * Typewriter su HTML: i tag compaiono interi; solo il testo viene “battuto” (innerHTML sempre sensato).
	 *
	 * @param {Element|null} el
	 * @param {string} html
	 * @param {function():boolean} isAlive
	 * @param {number} [tickMs]
	 * @returns {Promise<void>}
	 */
	function typewriterHtmlInto(el, html, isAlive, tickMs) {
		tickMs = tickMs == null ? 30 : tickMs;
		var fullHtml = String(html || '');
		return new Promise(function (resolve) {
			if (!el) {
				resolve();
				return;
			}
			function applyHtml(h) {
				try {
					el.innerHTML = String(h || '');
				} catch (e) {
					try {
						el.textContent = stripTagsHtml(h);
					} catch (e2) {
						/* ignore */
					}
				}
			}
			el.innerHTML = '';
			var chunks = splitHtmlChunks(fullHtml);
			if (!chunks.length) {
				resolve();
				return;
			}
			var chunkIdx = 0;
			var charInChunk = 0;
			var buf = '';

			function charsPerTickFor(len) {
				if (len > 600) {
					return 12;
				}
				if (len > 300) {
					return 6;
				}
				if (len > 120) {
					return 3;
				}
				return 1;
			}

			function tick() {
				if (typeof isAlive === 'function' && !isAlive()) {
					applyHtml(fullHtml);
					resolve();
					return;
				}
				if (chunkIdx >= chunks.length) {
					applyHtml(fullHtml);
					resolve();
					return;
				}
				var ch = chunks[chunkIdx];
				if (ch.type === 'tag') {
					buf += ch.value;
					try {
						el.innerHTML = buf;
					} catch (e3) {
						applyHtml(fullHtml);
						resolve();
						return;
					}
					chunkIdx += 1;
					charInChunk = 0;
					window.setTimeout(tick, Math.min(14, tickMs));
					return;
				}
				var tv = ch.value;
				if (!tv.length) {
					chunkIdx += 1;
					charInChunk = 0;
					window.setTimeout(tick, 0);
					return;
				}
				var cpt = charsPerTickFor(tv.length);
				var take = Math.min(cpt, tv.length - charInChunk);
				buf += tv.slice(charInChunk, charInChunk + take);
				charInChunk += take;
				try {
					el.innerHTML = buf;
				} catch (e4) {
					applyHtml(fullHtml);
					resolve();
					return;
				}
				if (charInChunk >= tv.length) {
					chunkIdx += 1;
					charInChunk = 0;
				}
				window.setTimeout(tick, tickMs);
			}

			tick();
		});
	}

	function removeAccents(s) {
		return s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
	}

	function normalizeSentence(s) {
		s = stripTagsHtml(s).toLowerCase();
		var strict = window.llmPhraseGame && window.llmPhraseGame.strictAccents !== false;
		if (!strict) {
			s = removeAccents(s);
		}
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
		var clear1 = qs(root, '.llm-phrase-game__clear-input--1');
		var clear2 = qs(root, '.llm-phrase-game__clear-input--2');
		var phase2RecapCounter   = qs(root, '.llm-phrase-game__phase2-recap__counter');
	var phase2RecapIface     = qs(root, '.llm-phrase-game__phase2-recap__interface');
	var phase2RecapPrompt    = qs(root, '.llm-phrase-game__phase2-recap__prompt');
	var listenTargetBtn      = qs(root, '.llm-phrase-game__listen-target:not(.llm-phrase-game__listen-target--phase2)');
		var listenTargetBtnPhase2 = qs(root, '.llm-phrase-game__listen-target--phase2');
		var composePhase1 = qs(root, '.llm-phrase-game__compose--phase1');
		var composePhase2 = qs(root, '.llm-phrase-game__compose--phase2');

		/* Intro storia: typewriter alla prima visita — blocca pulsante ascolto fino al termine */
		var pendingStoryIntroTypewriter =
			!!(cfg.storyIntro && storyEl) &&
			!(cfg.completedStoryLines && cfg.completedStoryLines.length > 0);
		var introComplete = !pendingStoryIntroTypewriter;
		var introReady = Promise.resolve();

		function setListenTargetVisible(visible) {
			if (!listenTargetBtn) {
				return;
			}
			var show = !!visible;
			listenTargetBtn.hidden = !show;
			listenTargetBtn.classList.toggle(
				'llm-phrase-game__listen-target--force-hidden',
				!show
			);
			if (show) {
				listenTargetBtn.removeAttribute('aria-hidden');
			} else {
				listenTargetBtn.setAttribute('aria-hidden', 'true');
			}
		}

		if (pendingStoryIntroTypewriter) {
			root.classList.add('llm-phrase-game--story-intro-active');
			setListenTargetVisible(false);
			if (cardEl) {
				cardEl.hidden = true;
			}
		}

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
	var micWordsThisPhrase = 0;
	var micState = 'idle'; // 'idle' | 'pending' | 'listening'
	var micLastFinalIndex = 0;
	var micPermissionGranted = false;
	var MIC_SESSION_MS = 5000;
	var micSessionActive = false;
	var micSessionTimer = null;

	/** Unisce testo finale evitando duplicati (motori mobile spesso inviano frasi cumulative). */
	function mergeFinalTranscript(existing, chunk) {
		chunk = String(chunk || '');
		if (!chunk) {
			return existing;
		}
		if (!existing) {
			return chunk;
		}
		var ex = existing.replace(/\s+/g, ' ').trim();
		var ch = chunk.replace(/\s+/g, ' ').trim();
		if (!ex) {
			return chunk;
		}
		if (!ch) {
			return existing;
		}
		if (ch === ex || existing.indexOf(chunk) !== -1) {
			return existing;
		}
		/* Chunk cumulativo: contiene già tutto il testo precedente */
		if (ch.indexOf(ex) === 0) {
			return chunk;
		}
		if (ex.indexOf(ch) === 0) {
			return existing;
		}
		if (existing.slice(-chunk.length) === chunk || existing.endsWith(ch)) {
			return existing;
		}
		return existing + (/\s$/.test(existing) ? '' : ' ') + chunk;
	}

	function countNewWords(oldText, newText) {
		var oldLen = tokenizeWords(oldText).length;
		var newLen = tokenizeWords(newText).length;
		return Math.max(0, newLen - oldLen);
	}

	function trimInterimOverlap(finals, interim) {
		interim = String(interim || '');
		if (!interim) {
			return '';
		}
		if (!finals) {
			return dedupeRepeatedPhrase(interim);
		}
		var f = finals.replace(/\s+/g, ' ').trim();
		var it = interim.replace(/\s+/g, ' ').trim();
		if (it.indexOf(f) === 0) {
			var tail = it.slice(f.length).replace(/^\s+/, '');
			return tail ? dedupeRepeatedPhrase(tail) : '';
		}
		if (f.indexOf(it) === 0 || f.endsWith(it)) {
			return '';
		}
		return dedupeRepeatedPhrase(interim);
	}

	function dedupeRepeatedPhrase(text) {
		text = String(text || '').replace(/\s+/g, ' ').trim();
		if (!text) {
			return '';
		}
		var words = text.split(/\s+/);
		var w;
		for (w = Math.floor(words.length / 2); w >= 1; w--) {
			var first = words.slice(0, w).join(' ');
			var second = words.slice(w, w * 2).join(' ');
			if (first && first === second) {
				return first;
			}
		}
		return text;
	}

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

		/** Scroll morbido: elemento allineato circa al centro del viewport. */
		function smoothScrollIntoCenter(el) {
			if (!el || typeof el.scrollIntoView !== 'function') {
				return Promise.resolve();
			}
			return new Promise(function (resolve) {
				try {
					el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
				} catch (e) {
					try {
						el.scrollIntoView(true);
					} catch (e2) {
						resolve();
						return;
					}
				}
				window.setTimeout(resolve, 600);
			});
		}

		function smoothScrollStoryToCenter() {
			var wrap = storyEl ? storyEl.closest('.llm-phrase-game__story-wrap') : null;
			return smoothScrollIntoCenter(wrap || storyEl);
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
		if (grammarEl) {
			grammarEl.innerHTML = '';
		}
		if (targetShow) {
			targetShow.innerHTML = '';
		}
		if (altShow) {
			altShow.innerHTML = '';
			altShow.style.opacity = '0';
		}
		if (labelMainEl) {
			labelMainEl.style.opacity = '0';
		}
		if (labelAltEl) {
			labelAltEl.style.opacity = '0';
		}
		if (promptRewrite) {
			promptRewrite.textContent = '';
			promptRewrite.style.opacity = '0';
		}
	}

	/**
	 * Splitta HTML della grammatica in blocchi: usa <p> tag se presenti,
	 * altrimenti \n\n, altrimenti \n.
	 */
	function splitGrammarBlocks(html) {
		var s = String(html || '').trim();
		if (!s) { return []; }
		if (/<p[\s>]/i.test(s)) {
			var div = document.createElement('div');
			try { div.innerHTML = s; } catch (e) { return [s]; }
			var pNodes = div.querySelectorAll('p');
			if (pNodes.length > 1) {
				var arr = [];
				pNodes.forEach(function (n) {
					var t = n.innerHTML.trim();
					if (t) { arr.push(t); }
				});
				if (arr.length > 1) { return arr; }
			}
		}
		var blocks = s.split(/\n\n+/).map(function (b) { return b.trim(); }).filter(Boolean);
		if (blocks.length > 1) { return blocks; }
		blocks = s.split(/\n/).map(function (b) { return b.trim(); }).filter(Boolean);
		if (blocks.length > 1) { return blocks; }
		return [s];
	}

	function runAnalysisTypestream(opts) {
		var run = ++analysisStreamRun;
		var yourText  = opts.yourText  != null ? String(opts.yourText)  : '';
		var skipYour  = !!opts.skipYourPhrase;
		var skipBravo = !!opts.skipBravo;
		var grammar   = opts.grammar   != null ? String(opts.grammar)   : '';
		var target    = opts.target    != null ? String(opts.target)    : '';
		var alt       = opts.alt       != null ? String(opts.alt)       : '';
		var hasBravo  = !skipBravo && bravoEl && bravoSourceText;

		var FADE_DUR     = 480;   /* durata fade grammatica */
		var FADE_GAP     = 120;   /* pausa dopo ogni blocco */
		var ELEMENT_GAP  = 120;   /* pausa tra elementi diversi */

		prepareAnalysisStreamLayout();
		setComposePhaseVisible(2, false);

		function alive() { return streamAlive(run); }

		/** Fade lento: elemento inizia opacity 0, transisce a 1. */
		function fadeReveal(el, dur) {
			var d = dur || FADE_DUR;
			return new Promise(function (resolve) {
				if (!el) { resolve(); return; }
				el.style.opacity = '0';
				el.style.transition = 'opacity ' + d + 'ms ease';
				requestAnimationFrame(function () {
					requestAnimationFrame(function () {
						el.style.opacity = '1';
						setTimeout(resolve, d);
					});
				});
			});
		}

		var chain = Promise.resolve();

		function addStep(fn, gapMs) {
			chain = chain.then(function () {
				if (!alive()) { return; }
				return fn();
			}).then(function () {
				if (!alive()) { return; }
				return sleepMs(gapMs != null ? gapMs : ELEMENT_GAP);
			});
		}

		/* ── La tua frase → typewriter ─────────────────────────────── */
		if (!skipYour && yourPhraseText && yourPhraseWrap) {
			addStep(function () {
				if (!alive()) { return; }
				yourPhraseWrap.hidden = false;
				return typewriterInto(yourPhraseText, yourText, alive);
			});
		}

		/* ── Bravo → typewriter ─────────────────────────────────────── */
		if (hasBravo) {
			addStep(function () {
				if (!alive()) { return; }
				return typewriterInto(bravoEl, bravoSourceText, alive);
			});
		}

		/* ── Grammatica → fade lento per paragrafo ──────────────────── */
		if (grammar) {
			var blocks = splitGrammarBlocks(grammar);
			blocks.forEach(function (blockHtml) {
				addStep((function (bHtml) {
					return function () {
						if (!alive()) { return; }
						var p = document.createElement('p');
						p.className = 'llm-phrase-game__grammar-block';
						try { p.innerHTML = bHtml; } catch (e) { p.textContent = bHtml; }
						p.style.margin = '0 0 0.45em';
						grammarEl.appendChild(p);
						return fadeReveal(p);
					};
				})(blockHtml), FADE_GAP);
			});
		}

		/* ── Frase corretta → typewriter ────────────────────────────── */
		if (target) {
			addStep(function () {
				if (!alive()) { return; }
				if (labelMainEl) { labelMainEl.style.opacity = '1'; }
				return typewriterHtmlInto(targetShow, target, alive, TYPE_TICK_MS);
			});
		} else if (labelMainEl) {
			labelMainEl.style.opacity = '1';
		}

		/* ── Alternativa → fade ─────────────────────────────────────── */
		if (alt) {
			addStep(function () {
				if (!alive()) { return; }
				try { altShow.innerHTML = alt; } catch (e) { altShow.textContent = alt; }
				if (labelAltEl) {
					labelAltEl.style.transition = 'opacity 400ms ease';
					labelAltEl.style.opacity = '1';
				}
				return fadeReveal(altShow, 400);
			});
		} else if (labelAltEl) {
			labelAltEl.style.opacity = '1';
		}

		/* ── Prompt riscrivi → fade ─────────────────────────────────── */
		if (promptRewrite) {
			addStep(function () {
				if (!alive()) { return; }
				promptRewrite.textContent = i18n.rewritePrompt || '';
				return fadeReveal(promptRewrite, 350);
			});
		}

	return chain.then(function () {
		if (!alive()) { return; }
		/* Popola il recap fase-1 visibile dentro il blocco fase-2 */
		var p = phrases[phraseIx];
		if (phase2RecapCounter) {
			var ctr = (i18n.progress || '%1$d / %2$d')
				.replace('%1$d', String(phraseIx + 1))
				.replace('%2$d', String(phrases.length));
			phase2RecapCounter.textContent = ctr;
		}
		if (phase2RecapIface) {
			phase2RecapIface.textContent = p && p.interface ? p.interface : '';
		}
		if (phase2RecapPrompt) {
			var tpl = i18n.translatePrompt || '';
			phase2RecapPrompt.textContent = tpl.replace('%s', cfg.targetLangLabel || '');
		}
		setComposePhaseVisible(2, true);
		if (btn2) { btn2.disabled = false; }
		if (input2) { input2.readOnly = false; }
	});
}

	var openStoryChip = null;

	function closeOpenStoryChip() {
		if (openStoryChip && openStoryChip.parentNode) {
			openStoryChip.parentNode.removeChild(openStoryChip);
		}
		openStoryChip = null;
	}

	function attachStoryTranslationChip(lineEl, translationText) {
		if (!lineEl || !translationText) {
			return null;
		}
		var oldChip = qs(lineEl, '.llm-phrase-game__story-chip');
		if (oldChip) {
			oldChip.remove();
		}
		var chip = document.createElement('span');
		chip.className = 'llm-phrase-game__story-chip';
		chip.innerHTML = String(translationText);
		lineEl.appendChild(chip);
		return chip;
	}

	function getStoryLineElementFromEventTarget(target) {
		var node = target;
		while (node && node !== storyEl) {
			if (node.nodeType === 1 && node.classList && node.classList.contains('llm-phrase-game__story-line')) {
				return node;
			}
			node = node.parentNode;
		}
		return null;
	}

	function hydrateStoryLineTranslations() {
		if (!storyEl || !cfg.phrases || !cfg.phrases.length) {
			return;
		}
		var lines = storyEl.querySelectorAll('.llm-phrase-game__story-line');
		lines.forEach(function (line) {
			if (line.dataset.translation) {
				return;
			}
			var text = plainSpeechText(line.textContent || '');
			var translation = findInterfaceForStoryLine(text);
			if (translation) {
				line.dataset.translation = translation;
			}
		});
	}

	/* ── Blocco introduzione storia (post_content), prima delle frasi completate ── */
	if (cfg.storyIntro && storyEl) {
		var introWrap = document.createElement('div');
		introWrap.className = 'llm-phrase-game__story-intro';
		var introLabel = document.createElement('span');
		introLabel.className = 'llm-phrase-game__story-intro-label';
		introLabel.textContent = i18n.introLabel || 'Introduzione:';
		var introText = document.createElement('div');
		introText.className = 'llm-phrase-game__story-intro-text';
		introWrap.appendChild(introLabel);
		introWrap.appendChild(introText);
		if (storyEl.firstChild) {
			storyEl.insertBefore(introWrap, storyEl.firstChild);
		} else {
			storyEl.appendChild(introWrap);
		}
		var hasCompleted = cfg.completedStoryLines && cfg.completedStoryLines.length > 0;
		if (hasCompleted) {
			/* Frasi già presenti: fade-in rapido, loadPhrase non aspetta */
			introText.textContent = String(cfg.storyIntro);
			requestAnimationFrame(function () {
				requestAnimationFrame(function () {
					introWrap.classList.add('llm-phrase-game__story-intro--visible');
				});
			});
		} else if (pendingStoryIntroTypewriter) {
			/* Prima visita: typewriter — loadPhrase e pulsante ascolto aspettano */
			introWrap.classList.add('llm-phrase-game__story-intro--visible');
			var introStreamRun2 = ++storyStreamRun;
			introReady = typewriterInto(introText, String(cfg.storyIntro), function () {
				return storyStreamRun === introStreamRun2;
			}).then(function () {
				introComplete = true;
				root.classList.remove('llm-phrase-game--story-intro-active');
			});
		}
	}

	if (cfg.completedStoryLines && cfg.completedStoryLines.length) {
		cfg.completedStoryLines.forEach(function (line) {
			var block = document.createElement('div');
			block.className = 'llm-phrase-game__story-line';
			var target = typeof line === 'object' ? (line.target || '') : String(line);
			var iface = typeof line === 'object' ? (line.interface || '') : '';
			block.innerHTML = String(target);
			if (iface) {
				block.dataset.translation = iface;
			}
			storyEl.appendChild(block);
		});
	}
	hydrateStoryLineTranslations();

		var restartBtnEl = doneEl ? qs(doneEl, '.llm-phrase-game__restart-btn') : null;

	/* ── Click su riga storia: TTS + etichetta traduzione ─── */
	function findInterfaceForStoryLine(targetText) {
		var wanted = normalizeSentence(targetText || '');
		if (!wanted || !cfg.phrases || !cfg.phrases.length) {
			return '';
		}
		var i;
		for (i = 0; i < cfg.phrases.length; i++) {
			var p = cfg.phrases[i] || {};
			if (normalizeSentence(p.target || '') === wanted) {
				return p.interface || '';
			}
		}
		return '';
	}
	storyEl && storyEl.addEventListener('click', function (e) {
		var line = getStoryLineElementFromEventTarget(e.target);
		if (!line) {
			return;
		}
		/* TTS (solo testo, senza tag) */
		var text = plainSpeechText(line.textContent || '');
		if (text) {
			speakTargetTranslation(text, null);
		}
		/* Traduzione */
		var translation = line.dataset.translation || findInterfaceForStoryLine(text);
		if (!translation) {
			return;
		}
		line.dataset.translation = translation;
		/* Mostra/nascondi etichetta traduzione al click. */
		var existingChip = qs(line, '.llm-phrase-game__story-chip');
		if (existingChip) {
			closeOpenStoryChip();
			return;
		}
		closeOpenStoryChip();
		openStoryChip = attachStoryTranslationChip(line, translation);
	});
	document.addEventListener('click', function (e) {
		if (!openStoryChip) {
			return;
		}
		var t = e.target;
		if (!t || t.nodeType !== 1) {
			closeOpenStoryChip();
			return;
		}
		if (t.closest && (t.closest('.llm-phrase-game__story-line') || t.closest('.llm-phrase-game__story-chip'))) {
			return;
		}
		closeOpenStoryChip();
	});
		if (restartBtnEl) {
			restartBtnEl.addEventListener('click', function () {
				if (i18n.restartConfirm && !window.confirm(i18n.restartConfirm)) {
					return;
				}
				restartBtnEl.disabled = true;
				var body = new URLSearchParams();
				body.set('action', 'llm_phrase_game_restart');
				body.set('nonce', nonce);
				body.set('story_id', String(storyId));
				fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body.toString(),
				})
					.then(function () {
						window.location.reload();
					})
					.catch(function () {
						restartBtnEl.disabled = false;
					});
			});
		}

	if (cfg.gameFinished) {
		cardEl.hidden = true;
		if (cfg.storyFinale && storyEl) {
			var finaleWrapFinished = document.createElement('div');
			finaleWrapFinished.className = 'llm-phrase-game__story-finale';
			var finaleTextFinished = document.createElement('div');
			finaleTextFinished.className = 'llm-phrase-game__story-finale-text';
			finaleTextFinished.textContent = String(cfg.storyFinale);
			finaleWrapFinished.appendChild(finaleTextFinished);
			storyEl.appendChild(finaleWrapFinished);
			requestAnimationFrame(function () {
				requestAnimationFrame(function () {
					finaleWrapFinished.classList.add('llm-phrase-game__story-finale--visible');
				});
			});
		}
		doneEl.hidden = false;
		return;
	}

	if (cfg.savedPhraseIndex !== undefined && cfg.savedPhraseIndex !== null) {
		phraseIx = parseInt(cfg.savedPhraseIndex, 10);
		if (isNaN(phraseIx)) {
			phraseIx = 0;
		}
	}

	/* Inizializza barra al caricamento usando phrase_done come fonte di verità. */
	if (typeof window.llmUpdateStoryProgressBar === 'function' && phrases.length > 0) {
		var initDone = cfg.savedPhrasesCount !== undefined && cfg.savedPhrasesCount !== null
			? parseInt(cfg.savedPhrasesCount, 10)
			: phraseIx;
		if (isNaN(initDone)) {
			initDone = 0;
		}
		window.llmUpdateStoryProgressBar(String(storyId), initDone, phrases.length);
	}

	function showMicCountdown(btn) {
		var wrap = btn && btn._llmMicCountdownWrap;
		var bar = btn && btn._llmMicCountdownBar;
		if (!wrap || !bar) {
			return;
		}
		wrap.hidden = false;
		wrap.classList.add('llm-phrase-game__mic-countdown--active');
		bar.style.animation = 'none';
		void bar.offsetWidth;
		bar.style.animation = 'llm-mic-countdown ' + (MIC_SESSION_MS / 1000) + 's linear forwards';
	}

	function hideMicCountdown(btn) {
		if (!btn || !btn._llmMicCountdownWrap || !btn._llmMicCountdownBar) {
			return;
		}
		btn._llmMicCountdownWrap.hidden = true;
		btn._llmMicCountdownWrap.classList.remove('llm-phrase-game__mic-countdown--active');
		btn._llmMicCountdownBar.style.animation = 'none';
	}

	function restoreMicBtnText(btn) {
		if (!btn || !btn._llmMicOrigText) { return; }
		var el = btn.querySelector('.llm-phrase-game__mic-text');
		if (el) { el.textContent = btn._llmMicOrigText; }
	}

	function setMicBtnText(btn, text) {
		if (!btn) { return; }
		var el = btn.querySelector('.llm-phrase-game__mic-text');
		if (el) { el.textContent = text; }
	}

	function updateMicStatusEl(btnEl, state) {
		var el = btnEl && btnEl._llmMicStatusEl;
		if (!el) { return; }
		el.classList.remove(
			'llm-phrase-game__mic-status--visible',
			'llm-phrase-game__mic-status--pending',
			'llm-phrase-game__mic-status--listening'
		);
		if (state === 'idle') {
			el.textContent = '';
			return;
		}
		if (state === 'pending') {
			el.textContent = i18n.micPending || '…';
			el.classList.add('llm-phrase-game__mic-status--pending');
		} else if (state === 'listening') {
			el.textContent = i18n.micListening || '…';
			el.classList.add('llm-phrase-game__mic-status--listening');
		}
		requestAnimationFrame(function () {
			el.classList.add('llm-phrase-game__mic-status--visible');
		});
	}

	function setMicButtonsDisabled(disabled) {
		if (mic1) { mic1.disabled = disabled; }
		if (mic2) { mic2.disabled = disabled; }
	}

	function applyMicStateClasses() {
		var btnEl = activeMicBtn;
		var taEl = activeMicTa;
		var shell = taEl ? taEl.closest('.llm-phrase-game__input-shell') : null;
		if (btnEl) {
			btnEl.classList.remove(
				'llm-phrase-game__mic--active',
				'llm-phrase-game__mic--pending',
				'llm-phrase-game__mic--listening',
				'llm-phrase-game__mic--session'
			);
		}
		if (taEl) { taEl.classList.remove('llm-phrase-game__input--listening'); }
		if (shell) { shell.classList.remove('llm-phrase-game__input-shell--listening'); }

		if (micState === 'idle') {
			setMicButtonsDisabled(false);
			if (btnEl) {
				hideMicCountdown(btnEl);
			}
			updateMicStatusEl(btnEl, 'idle');
			return;
		}
		setMicButtonsDisabled(true);
		if (btnEl) {
			btnEl.classList.add('llm-phrase-game__mic--session');
		}
		if (micState === 'pending') {
			if (btnEl) { btnEl.classList.add('llm-phrase-game__mic--pending'); }
		} else if (micState === 'listening') {
			if (btnEl) { btnEl.classList.add('llm-phrase-game__mic--listening'); }
			if (taEl) { taEl.classList.add('llm-phrase-game__input--listening'); }
			if (shell) { shell.classList.add('llm-phrase-game__input-shell--listening'); }
		}
		updateMicStatusEl(btnEl, micState === 'pending' ? 'pending' : 'listening');
	}

	function stopSpeech() {
		micSessionActive = false;
		if (micSessionTimer !== null) {
			clearTimeout(micSessionTimer);
			micSessionTimer = null;
		}
		micState = 'idle';
		micLastFinalIndex = 0;
		speechFinals = '';
		if (speechRec) {
			try { speechRec.stop(); } catch (e) { /* ignore */ }
			speechRec = null;
		}
		if (activeMicBtn) {
			hideMicCountdown(activeMicBtn);
		}
		setMicButtonsDisabled(false);
		applyMicStateClasses();
		activeMicTa = null;
		activeMicBtn = null;
	}

	function finishMicSession() {
		stopSpeech();
	}

	function showMicError(btn, msg) {
		if (!btn || !msg) { return; }
		var status = btn._llmMicStatusEl;
		if (!status) { return; }
		status.textContent = msg;
		status.classList.add(
			'llm-phrase-game__mic-status--visible',
			'llm-phrase-game__mic-status--error'
		);
		setTimeout(function () {
			status.textContent = '';
			status.classList.remove(
				'llm-phrase-game__mic-status--visible',
				'llm-phrase-game__mic-status--error'
			);
		}, 3500);
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
			if (listenTargetBtnPhase2) {
				listenTargetBtnPhase2.classList.remove('llm-phrase-game__listen-target--playing');
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

		function speakTargetTranslation(text, triggerBtn) {
			if (!window.speechSynthesis) {
				return;
			}
			var btnEl = triggerBtn || listenTargetBtn;
			if (!btnEl) {
				return;
			}
			var trimmed = plainSpeechText(text);
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
				if (btnEl) {
					btnEl.classList.remove('llm-phrase-game__listen-target--playing');
				}
			};
			ut.onerror = function () {
				if (btnEl) {
					btnEl.classList.remove('llm-phrase-game__listen-target--playing');
				}
			};
			btnEl.classList.add('llm-phrase-game__listen-target--playing');
			window.speechSynthesis.speak(ut);
		}

		function syncListenTargetUi() {
			if (!listenTargetBtn) {
				return;
			}
			var p = phrases[phraseIx];
			var hasSynth = typeof window.speechSynthesis !== 'undefined' && window.speechSynthesis;
			var hasText = p && plainSpeechText(p.target || '');
			var inPhase2 = phase2 && !phase2.hidden;
			// Fase 1: pulsante visibile solo se intro finita e testo disponibile.
			var show =
				introComplete && hasSynth && hasText && !inPhase2;
			setListenTargetVisible(show);
			// Fase 2: il pulsante è dentro compose--phase2 e appare con la textarea automaticamente.
		}

		if (window.speechSynthesis) {
			window.speechSynthesis.onvoiceschanged = function () {
				syncListenTargetUi();
			};
			window.speechSynthesis.getVoices();
		}

	function startMicSession(textarea, micBtn) {
		var Rec = window.SpeechRecognition || window.webkitSpeechRecognition;
		if (!Rec || micSessionActive) { return; }

		stopSpeech();
		cancelTts();

		micSessionActive = true;
		activeMicTa = textarea;
		activeMicBtn = micBtn;
		speechBase = textarea.value;
		if (speechBase.length && !/\s$/.test(speechBase)) { speechBase += ' '; }
		speechFinals = '';
		micLastFinalIndex = 0;
		micState = 'pending';

		micBtn.disabled = true;
		if (micBtn !== mic1 && mic1) { mic1.disabled = true; }
		if (micBtn !== mic2 && mic2) { mic2.disabled = true; }
		showMicCountdown(micBtn);
		applyMicStateClasses();

		micSessionTimer = setTimeout(function () {
			finishMicSession();
		}, MIC_SESSION_MS);

		function attachSpeechHandlers(rec) {
			rec.onstart = function () {
				if (!micSessionActive) { return; }
				if (micState === 'pending') {
					micState = 'listening';
					applyMicStateClasses();
				}
			};

			rec.onresult = function (ev) {
				if (!micSessionActive) { return; }
				var interim = '';
				var prevFinals = speechFinals;
				var i;
				for (i = ev.resultIndex; i < ev.results.length; i++) {
					var tr = ev.results[i][0].transcript;
					if (ev.results[i].isFinal) {
						if (i >= micLastFinalIndex) {
							speechFinals = mergeFinalTranscript(speechFinals, tr);
							micLastFinalIndex = i + 1;
						}
					} else {
						interim += tr;
					}
				}
				micWordsThisPhrase += countNewWords(prevFinals, speechFinals);
				interim = trimInterimOverlap(speechFinals, interim);
				textarea.value = speechBase + speechFinals + interim;
				if (typeof textarea._llmSyncClearBtn === 'function') {
					textarea._llmSyncClearBtn();
				}
			};

			rec.onerror = function (ev) {
				var code = ev && ev.error;
				if (code === 'not-allowed' || code === 'service-not-allowed') {
					micPermissionGranted = false;
					finishMicSession();
					showMicError(micBtn, i18n.micDenied || '');
				}
			};

			rec.onend = function () {
				if (!micSessionActive) { return; }
				try {
					rec.start();
				} catch (e) {
					/* Il timer da 4 secondi chiude la sessione */
				}
			};
		}

		function doStart() {
			if (!micSessionActive) { return; }
			speechRec = new Rec();
			speechRec.lang = speechLang;
			speechRec.continuous = true;
			speechRec.interimResults = true;
			attachSpeechHandlers(speechRec);
			try {
				speechRec.start();
			} catch (e) {
				finishMicSession();
			}
		}

		if (!micPermissionGranted && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
			navigator.mediaDevices.getUserMedia({ audio: true })
				.then(function (stream) {
					micPermissionGranted = true;
					stream.getTracks().forEach(function (t) { t.stop(); });
					doStart();
				})
				.catch(function () {
					finishMicSession();
					showMicError(micBtn, i18n.micDenied || '');
				});
		} else {
			doStart();
		}
	}

	function bindMic(micBtn, textarea) {
		if (!micBtn || !textarea) { return; }
		if (!window.SpeechRecognition && !window.webkitSpeechRecognition) {
			micBtn.hidden = true;
			return;
		}

		var origTextEl = micBtn.querySelector('.llm-phrase-game__mic-text');
		micBtn._llmMicOrigText = origTextEl ? origTextEl.textContent : '';

		var statusEl = document.createElement('p');
		statusEl.className = 'llm-phrase-game__mic-status';
		statusEl.setAttribute('aria-live', 'polite');
		statusEl.setAttribute('aria-atomic', 'true');
		micBtn.parentNode.insertBefore(statusEl, micBtn);
		micBtn._llmMicStatusEl = statusEl;

		var countdownWrap = document.createElement('div');
		countdownWrap.className = 'llm-phrase-game__mic-countdown';
		countdownWrap.hidden = true;
		countdownWrap.innerHTML = '<div class="llm-phrase-game__mic-countdown__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="100"></div>';
		micBtn.parentNode.insertBefore(countdownWrap, micBtn);
		micBtn._llmMicCountdownWrap = countdownWrap;
		micBtn._llmMicCountdownBar = countdownWrap.querySelector('.llm-phrase-game__mic-countdown__bar');

		micBtn.addEventListener('click', function () {
			if (micSessionActive || micBtn.disabled) {
				return;
			}
			startMicSession(textarea, micBtn);
		});
	}

		bindMic(mic1, input1);
		bindMic(mic2, input2);

		var phase1WarnActive = false;

		function syncClearInputVisibility(textarea, clearBtn) {
			if (!textarea || !clearBtn) {
				return;
			}
			clearBtn.hidden = !(textarea.value || '').trim();
		}

		function bindClearInput(clearBtn, textarea, onClear) {
			if (!clearBtn || !textarea) {
				return;
			}
			function sync() {
				syncClearInputVisibility(textarea, clearBtn);
			}
			textarea._llmSyncClearBtn = sync;
			textarea.addEventListener('input', sync);
			sync();
			clearBtn.addEventListener('click', function () {
				stopSpeech();
				textarea.value = '';
				sync();
				if (typeof onClear === 'function') {
					onClear();
				}
				textarea.focus();
			});
		}

		bindClearInput(clear1, input1, function () {
			if (phase1WarnActive) {
				setMessage('');
				phase1WarnActive = false;
			}
		});
		bindClearInput(clear2, input2, function () {
			if (messagePhase2El) {
				setMessagePhase2('', '');
			}
		});

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
				speakTargetTranslation(p ? p.target : '', listenTargetBtn);
			});
		}

		if (listenTargetBtnPhase2) {
			listenTargetBtnPhase2.addEventListener('click', function () {
				if (listenTargetBtnPhase2.classList.contains('llm-phrase-game__listen-target--playing')) {
					cancelTts();
					return;
				}
				var p = phrases[phraseIx];
				speakTargetTranslation(p ? p.target : '', listenTargetBtnPhase2);
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
			ifaceEl.innerHTML = '';
			promptTrans.textContent = '';
			function aliveIntro() {
				return phraseIntroRun === introRunId;
			}
			return typewriterHtmlInto(ifaceEl, ifaceText, aliveIntro, TYPE_TICK_MS).then(function () {
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

		function appendMessagePhase2Typewriter(text) {
			if (!messagePhase2El) {
				return Promise.resolve();
			}
			phase2MessageRun++;
			var run = phase2MessageRun;
			var line = document.createElement('p');
			line.className = 'llm-phrase-game__message-phase2-line';
			messagePhase2El.appendChild(line);
			return typewriterInto(line, text || '', function () {
				return phase2MessageRun === run;
			});
		}

		function showPhase(n) {
			phase2.hidden = n !== 2;
			phase1.hidden = false;
			root.classList.toggle('llm-phrase-game--phase2-active', n === 2);
			if (n === 2) {
				setComposePhaseVisible(1, false);
				if (input1) {
					input1.readOnly = true;
					input1.setAttribute('tabindex', '-1');
				}
				if (btn1) {
					btn1.setAttribute('tabindex', '-1');
				}
				if (mic1) {
					mic1.setAttribute('tabindex', '-1');
				}
			} else {
				if (input1) {
					input1.readOnly = false;
					input1.removeAttribute('tabindex');
				}
				if (btn1) {
					btn1.removeAttribute('tabindex');
				}
				if (mic1) {
					mic1.removeAttribute('tabindex');
				}
			}
			/* Fase 1: il pulsante ascolto si mostra solo a fine typewriter frase (non qui). */
			if (n === 2) {
				syncListenTargetUi();
			}
		}

	function resetAnalysis() {
		cancelAnalysisStream();
		cancelPhase2MessageStream();
		analysisEl.hidden = true;
		if (bravoEl) {
			bravoEl.textContent = '';
		}
		if (grammarEl) {
			grammarEl.innerHTML = '';
		}
		if (targetShow) {
			targetShow.innerHTML = '';
		}
		if (altShow) {
			altShow.innerHTML = '';
			altShow.style.opacity = '';
			altShow.style.transition = '';
		}
		if (labelMainEl) {
			labelMainEl.style.opacity = '';
			labelMainEl.style.transition = '';
		}
		if (labelAltEl) {
			labelAltEl.style.opacity = '';
			labelAltEl.style.transition = '';
		}
		if (yourPhraseWrap) {
			yourPhraseWrap.hidden = true;
		}
		if (yourPhraseText) {
			yourPhraseText.textContent = '';
		}
		if (promptRewrite) {
			promptRewrite.style.opacity = '';
			promptRewrite.style.transition = '';
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
		micWordsThisPhrase = 0;
		phase1WarnActive = false;
		cancelTts();
		cancelAnalysisStream();
			cancelStoryStream();
			cancelPhraseIntro();
		if (phraseIx >= phrases.length) {
			cardEl.hidden = true;
			if (cfg.storyFinale && storyEl && !storyEl.querySelector('.llm-phrase-game__story-finale')) {
				var finaleWrap = document.createElement('div');
				finaleWrap.className = 'llm-phrase-game__story-finale';
				var finaleText = document.createElement('div');
				finaleText.className = 'llm-phrase-game__story-finale-text';
				finaleWrap.appendChild(finaleText);
				storyEl.appendChild(finaleWrap);
				smoothScrollStoryToCenter().then(function () {
					finaleWrap.classList.add('llm-phrase-game__story-finale--visible');
					var sr = ++storyStreamRun;
					typewriterInto(finaleText, String(cfg.storyFinale), function () {
						return storyStreamRun === sr;
					}).then(function () {
						if (doneEl) {
							doneEl.hidden = false;
						}
					});
				});
			} else {
				doneEl.hidden = false;
			}
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
				if (input1 && input1._llmSyncClearBtn) { input1._llmSyncClearBtn(); }
				if (input2 && input2._llmSyncClearBtn) { input2._llmSyncClearBtn(); }
				ifaceEl.innerHTML = String(p.interface || '');
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
			if (input1 && input1._llmSyncClearBtn) { input1._llmSyncClearBtn(); }
			if (input2 && input2._llmSyncClearBtn) { input2._llmSyncClearBtn(); }
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

	function postCheck(phase, userText, micUsed, cb, bypass) {
		if (typeof micUsed === 'function') {
			cb = micUsed;
			micUsed = false;
		}
		var body = new URLSearchParams();
		body.set('action', 'llm_phrase_game_check');
		body.set('nonce', nonce);
		body.set('story_id', String(storyId));
		body.set('phrase_index', String(phrases[phraseIx].index));
		body.set('phase', String(phase));
		body.set('user_text', userText);
		body.set('mic_used', micUsed ? '1' : '0');
		body.set('phase1_bypass', bypass ? '1' : '0');
		var strictAccents = window.llmPhraseGame && window.llmPhraseGame.strictAccents !== false;
		body.set('strict_accents', strictAccents ? '1' : '0');

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

		input1.addEventListener('input', function () {
			if (phase1WarnActive) {
				setMessage('');
				phase1WarnActive = false;
			}
		});

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
			var bypassPhase1 = false;
			if (!phase1PassesLocal(txt, targetRef, PHASE1_MIN)) {
				if (phase1WarnActive) {
					/* Seconda pressione con feedback visibile: si procede comunque */
					bypassPhase1 = true;
					phase1WarnActive = false;
				} else {
					setMessage(i18n.phase1Fail || '', true);
					phase1WarnActive = true;
					return;
				}
			} else {
				phase1WarnActive = false;
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
			/* Stesso stato che runAnalysisTypestream applica subito: altrimenti, finché non parte lo stream (es. dopo lo scroll), le etichette statiche in analisi resterebbero visibili un attimo. */
			prepareAnalysisStreamLayout();
			setComposePhaseVisible(2, false);
			analysisEl.hidden = false;
			showPhase(2);
			requestAnimationFrame(function () {
				requestAnimationFrame(function () {
					smoothScrollIntoCenter(analysisEl).then(function () {
						runAnalysisTypestream({
							yourText: txt,
							grammar: (p && p.grammar) || '',
							target: targetRef,
							alt: (p && p.alt) || '',
						});
					});
				});
			});
			postCheck(1, txt, false, function (json) {
				btn1.disabled = false;
				if (!json || !json.success) {
					cancelAnalysisStream();
					cancelPhraseIntro();
					resetAnalysis();
					showPhase(1);
					var prb = phrases[phraseIx];
					if (ifaceEl) {
						ifaceEl.innerHTML = String(prb ? prb.interface || '' : '');
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
			}, bypassPhase1);
		});

		btn2.addEventListener('click', function () {
			stopSpeech();
			cancelTts();
			/* Sincronizza strictAccents direttamente dal DOM per evitare disallineamenti */
			var accentsToggleEl = document.querySelector('.llm-story-settings__accents-input');
			if (accentsToggleEl && window.llmPhraseGame) {
				window.llmPhraseGame.strictAccents = accentsToggleEl.checked;
			}
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
		var phase2ScrollTarget = messagePhase2El || phase2;
		var totalWords = tokenizeWords(txt).length;
		var micUsed = totalWords > 0 && micWordsThisPhrase >= Math.max(1, Math.ceil(totalWords * 0.2));

		/* Avvia AJAX subito in parallelo con i messaggi. */
		var ajaxPromise = new Promise(function (resolve) {
			postCheck(2, txt, micUsed, function (json) {
				resolve(json);
			});
		});

		setMessagePhase2('', '');
		messagePhase2El && (messagePhase2El.innerHTML = '');
		messagePhase2El && messagePhase2El.classList.add('llm-phrase-game__message-phase2--success');

		smoothScrollIntoCenter(phase2ScrollTarget).then(function () {
			return appendMessagePhase2Typewriter(i18n.bravoCorrect || '');
		}).then(function () {
			return sleepMs(300);
		}).then(function () {
			return appendMessagePhase2Typewriter(i18n.phraseCompletePoints || '');
		}).then(function () {
			return sleepMs(300);
		}).then(function () {
			var micMsg = micUsed ? (i18n.micUsedPoint || '') : (i18n.micUsedNoPoint || '');
			return appendMessagePhase2Typewriter(micMsg);
		}).then(function () {
			return sleepMs(300);
		}).then(function () {
			return appendMessagePhase2Typewriter(i18n.storyContinue || '');
		}).then(function () {
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
					}
					if (!sentence) {
						advanceAfterPhrase2();
						return;
					}
				smoothScrollStoryToCenter().then(function () {
					var block = document.createElement('div');
					block.className = 'llm-phrase-game__story-line';
					if (d.display_interface) {
						block.dataset.translation = d.display_interface;
					}
					storyEl.appendChild(block);
					hydrateStoryLineTranslations();
						var sr = ++storyStreamRun;
						typewriterHtmlInto(block, sentence, function () {
							return storyStreamRun === sr;
						}, TYPE_TICK_MS).then(function () {
							if (storyStreamRun === sr) {
								advanceAfterPhrase2();
							}
						});
					});
				});
		});

	var startResume =
		parseInt(cfg.savedStep, 10) === 2 && cfg.resumeAnalysis;
	introReady.then(function () {
		if (pendingStoryIntroTypewriter && cardEl) {
			cardEl.hidden = false;
		}
		loadPhrase(!!startResume);
	});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.llm-phrase-game').forEach(function (el) {
			init(el);
		});
	});
})();
