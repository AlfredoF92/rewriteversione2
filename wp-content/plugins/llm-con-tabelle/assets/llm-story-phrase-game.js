(function () {
	'use strict';

	function qs(root, sel) {
		return root.querySelector(sel);
	}

	function qsa(root, sel) {
		return Array.prototype.slice.call(root.querySelectorAll(sel));
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

	function formatSymbolToken(tok) {
		return (tok.lead || '') + tok.word + (tok.trail || '');
	}

	/**
	 * Prefisso utente che coincide in ordine con la traduzione.
	 * null se non c'è nulla da tenere (tutto sbagliato) o nulla da togliere.
	 */
	function exactPrefixRewind(userText, targetText) {
		var userTokens = splitSymbolTokens(userText);
		var refTokens = splitSymbolTokens(plainSpeechText(targetText));
		if (!userTokens.length || !refTokens.length) {
			return null;
		}
		var n = 0;
		while (n < userTokens.length && n < refTokens.length) {
			if (symbolWordKey(userTokens[n].word) !== symbolWordKey(refTokens[n].word)) {
				break;
			}
			n += 1;
		}
		var drop = userTokens.length - n;
		if (n < 1 || drop < 1) {
			return null;
		}
		var kept = [];
		var i;
		for (i = 0; i < n; i++) {
			kept.push(formatSymbolToken(userTokens[i]));
		}
		return { drop: drop, text: kept.join(' ') };
	}

	function rewindArrowsLabel(count) {
		var parts = [];
		var i;
		for (i = 0; i < count; i++) {
			parts.push('<--');
		}
		return parts.join(' ');
	}

	function symbolWordKey(word) {
		return removeAccents(String(word || '')).toLowerCase().replace(/['’\-]/g, '');
	}

	/**
	 * Spezza una frase in parole con punteggiatura attaccata (davanti/dietro).
	 */
	function splitSymbolTokens(text) {
		var s = String(text || '');
		var tokens = [];
		var i = 0;
		var reLead = /^[\p{P}\p{S}]+/u;
		var reWord = /^[\p{L}\p{N}]+(?:['’\-][\p{L}\p{N}]+)*/u;
		var reTrail = /^[\p{P}\p{S}]+/u;
		while (i < s.length) {
			if (/\s/.test(s.charAt(i))) {
				i += 1;
				continue;
			}
			var rest = s.slice(i);
			var lead = '';
			var mLead = rest.match(reLead);
			if (mLead) {
				lead = mLead[0];
				rest = rest.slice(lead.length);
				i += lead.length;
			}
			var mWord = rest.match(reWord);
			if (!mWord) {
				if (!lead) {
					i += 1;
				}
				continue;
			}
			var word = mWord[0];
			rest = rest.slice(word.length);
			i += word.length;
			var trail = '';
			var mTrail = rest.match(reTrail);
			if (mTrail) {
				trail = mTrail[0];
				i += trail.length;
			}
			tokens.push({ lead: lead, word: word, trail: trail });
		}
		return tokens;
	}

	function controllaConcatKeys(tokens, start, end) {
		var key = '';
		var i;
		for (i = start; i < end; i++) {
			key += symbolWordKey(tokens[i].word);
		}
		return key;
	}

	function controllaTakeForm(formsByKey, usedCount, key) {
		var forms = key ? formsByKey[key] : null;
		if (!forms || !forms.length) {
			return '';
		}
		var n = usedCount[key] || 0;
		usedCount[key] = n + 1;
		return forms[Math.min(n, forms.length - 1)];
	}

	function controllaSplitUserWord(userKey, refs) {
		if (!userKey) {
			return '';
		}
		var j;
		var k;
		var acc;
		var parts;
		for (j = 0; j < refs.length; j++) {
			acc = '';
			parts = [];
			for (k = j; k < refs.length; k++) {
				acc += symbolWordKey(refs[k].word);
				parts.push((refs[k].lead || '') + refs[k].word + (refs[k].trail || ''));
				if (acc === userKey && parts.length >= 2) {
					return parts.join(' ');
				}
				if (acc.length >= userKey.length) {
					break;
				}
			}
		}
		return '';
	}

	/**
	 * Allinea maiuscole/minuscole e simboli delle parole dette a quelli della traduzione corretta.
	 * Unisce "Every Day" → "Everyday" e stacca "Everyday" → "Every day" se la traduzione lo richiede.
	 */
	function applyControllaSimboli(userText, targetText) {
		var refs = splitSymbolTokens(plainSpeechText(targetText));
		var users = splitSymbolTokens(userText);
		if (!refs.length || !users.length) {
			return String(userText || '');
		}
		var formsByKey = {};
		var i;
		for (i = 0; i < refs.length; i++) {
			var refKey = symbolWordKey(refs[i].word);
			if (!refKey) {
				continue;
			}
			if (!formsByKey[refKey]) {
				formsByKey[refKey] = [];
			}
			formsByKey[refKey].push(refs[i].lead + refs[i].word + refs[i].trail);
		}
		var usedCount = {};
		var out = [];
		i = 0;
		while (i < users.length) {
			var taken = '';
			var span;
			for (span = Math.min(4, users.length - i); span >= 2; span--) {
				taken = controllaTakeForm(formsByKey, usedCount, controllaConcatKeys(users, i, i + span));
				if (taken) {
					out.push(taken);
					i += span;
					break;
				}
			}
			if (taken) {
				continue;
			}
			var key = symbolWordKey(users[i].word);
			taken = controllaTakeForm(formsByKey, usedCount, key);
			if (taken) {
				out.push(taken);
				i += 1;
				continue;
			}
			var split = controllaSplitUserWord(key, refs);
			if (split) {
				out.push(split);
				i += 1;
				continue;
			}
			out.push((users[i].lead || '') + users[i].word + (users[i].trail || ''));
			i += 1;
		}
		return out.join(' ');
	}

	function applyControllaSimboliTwice(userText, targetText) {
		return applyControllaSimboli(applyControllaSimboli(userText, targetText), targetText);
	}

	function controllaKeysMatch(tokens, start, phraseKeys) {
		var n = phraseKeys.length;
		var k;
		for (k = 0; k < n; k++) {
			if (symbolWordKey(tokens[start + k].word) !== phraseKeys[k]) {
				return false;
			}
		}
		return true;
	}

	function controllaMaxPhraseCopies(tokens, phraseKeys) {
		var n = phraseKeys.length;
		var max = 0;
		var j = 0;
		while (j + n <= tokens.length) {
			if (!controllaKeysMatch(tokens, j, phraseKeys)) {
				j += 1;
				continue;
			}
			var copies = 0;
			var p = j;
			while (p + n <= tokens.length && controllaKeysMatch(tokens, p, phraseKeys)) {
				copies += 1;
				p += n;
			}
			if (copies > max) {
				max = copies;
			}
			j = p;
		}
		return max;
	}

	function countSymbolKeys(tokens) {
		var counts = {};
		var i;
		for (i = 0; i < tokens.length; i++) {
			var key = symbolWordKey(tokens[i].word);
			if (!key) {
				continue;
			}
			counts[key] = (counts[key] || 0) + 1;
		}
		return counts;
	}

	function flagSurplusWordCopies(tokens, refs) {
		var flags = [];
		var allowed = countSymbolKeys(refs);
		var seen = {};
		var i;
		for (i = 0; i < tokens.length; i++) {
			var key = symbolWordKey(tokens[i].word);
			if (!key) {
				flags.push(false);
				continue;
			}
			seen[key] = (seen[key] || 0) + 1;
			flags.push(seen[key] > (allowed[key] || 0));
		}
		return flags;
	}

	function flagSurplusRepeatedControllaPhrases(tokens, refs) {
		var flags = [];
		var i;
		for (i = 0; i < tokens.length; i++) {
			flags.push(false);
		}
		if (!tokens || tokens.length < 2) {
			return flags;
		}
		i = 0;
		while (i < tokens.length) {
			var did = false;
			var maxN = Math.min(8, Math.floor((tokens.length - i) / 2));
			var n;
			for (n = maxN; n >= 1; n--) {
				var phraseKeys = [];
				var k;
				for (k = 0; k < n; k++) {
					phraseKeys.push(symbolWordKey(tokens[i + k].word));
				}
				if (!phraseKeys.join('')) {
					continue;
				}
				var copies = 0;
				var p = i;
				while (p + n <= tokens.length && controllaKeysMatch(tokens, p, phraseKeys)) {
					copies += 1;
					p += n;
				}
				if (copies < 2) {
					continue;
				}
				var keep = Math.max(1, controllaMaxPhraseCopies(refs, phraseKeys));
				if (keep > copies) {
					keep = copies;
				}
				var t;
				for (t = i + keep * n; t < i + copies * n; t++) {
					flags[t] = true;
				}
				i += copies * n;
				did = true;
				break;
			}
			if (!did) {
				i += 1;
			}
		}
		return flags;
	}

	function escapeControllaHtml(s) {
		return String(s || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function stripControllaStrike(text) {
		return String(text || '').replace(/\u0336/g, '');
	}

	function targetSymbolKeySet(targetText) {
		var refs = splitSymbolTokens(plainSpeechText(targetText));
		var known = {};
		var i;
		for (i = 0; i < refs.length; i++) {
			var key = symbolWordKey(refs[i].word);
			if (key) {
				known[key] = true;
			}
		}
		return known;
	}

	function mapUnknownControllaWords(userText, targetText, mode) {
		var known = targetSymbolKeySet(targetText);
		var refs = splitSymbolTokens(plainSpeechText(targetText));
		var users = splitSymbolTokens(stripControllaStrike(userText));
		var surplusPhrase = flagSurplusRepeatedControllaPhrases(users, refs);
		var surplusWord = flagSurplusWordCopies(users, refs);
		var out = [];
		var html = [];
		var hasExtras = false;
		var i;
		for (i = 0; i < users.length; i++) {
			var key = symbolWordKey(users[i].word);
			var extra = !!(key && !known[key]) || !!surplusPhrase[i] || !!surplusWord[i];
			var piece = (users[i].lead || '') + users[i].word + (users[i].trail || '');
			if (extra) {
				hasExtras = true;
				if (mode === 'drop') {
					continue;
				}
				out.push(piece);
				html.push(
					escapeControllaHtml(users[i].lead || '') +
					'<strong class="llm-phrase-game__voice-extra">' +
					escapeControllaHtml(users[i].word) +
					'</strong>' +
					escapeControllaHtml(users[i].trail || '')
				);
			} else {
				out.push(piece);
				html.push(escapeControllaHtml(piece));
			}
		}
		return {
			text: normalizeControllaSentence(out.join(' ')),
			html: html.join(' '),
			hasExtras: hasExtras
		};
	}

	function tokenToControllaPiece(tok) {
		return String((tok.lead || '') + (tok.word || '') + (tok.trail || '')).replace(/\s+/g, ' ').trim();
	}

	function normalizeControllaSentence(text) {
		var tokens = splitSymbolTokens(stripControllaStrike(text));
		var pieces = [];
		var i;
		for (i = 0; i < tokens.length; i++) {
			var piece = tokenToControllaPiece(tokens[i]);
			if (piece) {
				pieces.push(piece);
			}
		}
		return pieces.join(' ');
	}

	/** Come tokenizeWords ma rimuove sempre gli accenti, indipendentemente da strictAccents. */
	function tokenizeWordsNoAccents(s) {
		var n = removeAccents(s.toLowerCase());
		n = n.replace(/[^\p{L}\p{N}\s]+/gu, ' ').replace(/\s+/g, ' ').trim();
		if (!n) { return []; }
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
		var refWords = tokenizeWordsNoAccents(referenceText);
		var userWords = tokenizeWordsNoAccents(userText);
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

	function countReferenceWordHits(userText, referenceText) {
		var refWords = tokenizeWordsNoAccents(referenceText);
		var userWords = tokenizeWordsNoAccents(userText);
		if (!refWords.length) {
			return { hits: 0, total: 0, heard: userWords.length };
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
		return { hits: hits, total: refWords.length, heard: userWords.length };
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

	/* ── Parole random di aiuto ──────────────────────────────────────────
	 * Le esche sostituiscono lettere simili restando nella stessa classe
	 * (vocale con vocale, consonante con consonante), così la parola sbagliata
	 * si distingue solo leggendo con attenzione.
	 */

	var VOWEL_SWAPS = {
		a: 'eo', e: 'ai', i: 'ey', o: 'au', u: 'oy', y: 'iu'
	};

	var CONSONANT_SWAPS = {
		b: 'dp', c: 'gs', d: 'bp', f: 'lt', g: 'cq', h: 'kb', j: 'gz',
		k: 'hc', l: 'ft', m: 'nw', n: 'mr', p: 'bq', q: 'gp', r: 'ns',
		s: 'zc', t: 'fl', v: 'wb', w: 'mv', x: 'sk', z: 'sx'
	};

	/** Lettere che NFD non scompone perché il segno fa parte del glifo. */
	var STANDALONE_LETTERS = { 'ł': 'l', 'ø': 'o', 'đ': 'd', 'ß': 's', 'æ': 'a', 'œ': 'o' };

	var WORD_EDGE_RE = /^[^0-9A-Za-zÀ-ÖØ-öø-ÿĀ-ſ]+|[^0-9A-Za-zÀ-ÖØ-öø-ÿĀ-ſ]+$/g;

	function baseLetter(ch) {
		if (STANDALONE_LETTERS[ch]) {
			return STANDALONE_LETTERS[ch];
		}
		if (typeof ''.normalize !== 'function') {
			return ch;
		}
		return ch.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
	}

	/** Accento, tilde, ogonek, ł, ø…: queste lettere restano intatte anche nell'esca. */
	function hasDiacritic(ch) {
		var lower = ch.toLowerCase();
		if (STANDALONE_LETTERS[lower]) {
			return true;
		}
		if (typeof ''.normalize !== 'function') {
			return lower !== baseLetter(lower);
		}
		return lower.normalize('NFD').length > 1;
	}

	function shuffled(list) {
		var out = list.slice();
		for (var i = out.length - 1; i > 0; i--) {
			var j = Math.floor(Math.random() * (i + 1));
			var tmp = out[i];
			out[i] = out[j];
			out[j] = tmp;
		}
		return out;
	}

	/** Lettera simile della stessa classe, o null se non sostituibile. */
	function swapLetter(ch) {
		var lower = ch.toLowerCase();
		if (hasDiacritic(ch)) {
			return null;
		}
		var base = baseLetter(lower);
		var pool = VOWEL_SWAPS[base] || CONSONANT_SWAPS[base] || '';
		if (!pool) {
			return null;
		}
		var next = pool.charAt(Math.floor(Math.random() * pool.length));
		var isUpper = ch !== lower && ch === ch.toUpperCase();
		return isUpper ? next.toUpperCase() : next;
	}

	/**
	 * Variante alterata di una parola: cambia una sola lettera, mai una
	 * con accento o diacritico. Tocca le lettere interne; nelle parole di
	 * una o due lettere agisce sull'ultima.
	 *
	 * @param {string} word
	 * @returns {string|null} null se nessuna lettera è sostituibile.
	 */
	function mutateWord(word) {
		var chars = word.split('');
		var positions = [];
		if (chars.length <= 2) {
			positions.push(chars.length - 1);
		} else {
			for (var i = 1; i < chars.length - 1; i++) {
				positions.push(i);
			}
		}
		positions = shuffled(positions);

		for (var p = 0; p < positions.length; p++) {
			var ix = positions[p];
			var next = swapLetter(chars[ix]);
			if (next && next !== chars[ix]) {
				chars[ix] = next;
				return chars.join('');
			}
		}
		return null;
	}

	function stripMarkup(html) {
		var holder = document.createElement('div');
		holder.innerHTML = String(html || '');
		return holder.textContent || '';
	}

	function splitHelperWords(text) {
		return String(text || '')
			.split(/\s+/)
			.map(function (word) {
				return word.replace(WORD_EDGE_RE, '');
			})
			.filter(function (word) {
				return word.length > 0;
			});
	}

	/**
	 * Parole della soluzione più una esca ciascuna, tutte mescolate.
	 *
	 * Il confronto dei doppioni ignora le maiuscole: un'esca che coincide con
	 * una parola corretta a meno del caso verrebbe accettata dalla validazione,
	 * quindi non sarebbe più un'esca.
	 */
	function buildRandomWords(target) {
		var words = splitHelperWords(stripMarkup(target));
		var out = words.slice();
		var seen = words.map(function (word) {
			return word.toLowerCase();
		});

		words.forEach(function (word) {
			for (var tries = 0; tries < 6; tries++) {
				var decoy = mutateWord(word);
				if (decoy && seen.indexOf(decoy.toLowerCase()) === -1) {
					out.push(decoy);
					seen.push(decoy.toLowerCase());
					break;
				}
			}
		});

		return shuffled(out);
	}

	function init(root) {
		if (!root || !window.llmPhraseGame) {
			return;
		}
		if (root.getAttribute('data-llm-pg-ready') === '1') {
			return;
		}
		root.setAttribute('data-llm-pg-ready', '1');

		var cfg = window.llmPhraseGame;
		if (window.llmGuestBrowserStore && typeof window.llmGuestBrowserStore.hydratePhraseGameCfg === 'function') {
			window.llmGuestBrowserStore.hydratePhraseGameCfg(cfg);
		}
		var phrases = cfg.phrases || [];
		if (!phrases.length) {
			return;
		}

		var storyId = cfg.storyId;
		var storyTitle = cfg.storyTitle || '';

		function persistGuestStoryProgress(partial) {
			if (cfg.learningModeIsSaved || !window.llmGuestBrowserStore) {
				return;
			}
			var payload = partial || {};
			payload.title = payload.title != null ? payload.title : storyTitle;
			payload.phrasesTotal = payload.phrasesTotal != null ? payload.phrasesTotal : phrases.length;
			window.llmGuestBrowserStore.persistPhraseProgress(storyId, payload);
		}
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
		var promptTransText = qs(root, '.llm-phrase-game__prompt-text:not(.llm-phrase-game__prompt-text--rewrite)');
		var promptRewrite = qs(root, '.llm-phrase-game__prompt--rewrite');
		var promptRewriteText = qs(root, '.llm-phrase-game__prompt-text--rewrite');
		var input1 = qs(root, '.llm-phrase-game__input--1');
		var input2 = qs(root, '.llm-phrase-game__input--2');
		var inputVoice = qs(root, '.llm-phrase-game__input--voice');
		var voiceFieldEl = qs(root, '.llm-phrase-game__voice-field');
		var btn1 = qs(root, '.llm-phrase-game__btn--continue1');
		var btn2 = qs(root, '.llm-phrase-game__btn--continue2');
		function setContinueLabel(btn, text) {
			if (!btn || !text) {
				return;
			}
			var label = btn.querySelector('.llm-phrase-game__btn-label');
			if (label) {
				label.textContent = text;
			} else {
				btn.textContent = text;
			}
		}
		var messageEl = qs(root, '.llm-phrase-game__message');
		var messageSoloEl = qs(root, '.llm-phrase-game__message-solo');
		/* Non usare il primo .message-phase2: in DOM arriva prima .message-solo (nascosto in LoveRewrite). */
		var messagePhase2El =
			qs(root, '.llm-phrase-game__phase--2 .llm-phrase-game__message-phase2') ||
			qs(root, '.llm-phrase-game__message-phase2:not(.llm-phrase-game__message-solo)');
		var analysisEl = qs(root, '.llm-phrase-game__analysis');
		var phraseNotesWrap = qs(root, '.llm-phrase-game__phrase-notes-wrap');
		var phraseNotesEl = qs(root, '.llm-phrase-game__phrase-notes');
		var grammarEl = qs(root, '.llm-phrase-game__grammar');
		var pronunciationEl = qs(root, '.llm-phrase-game__pronunciation');
		var labelPronunciationEl = qs(root, '.llm-phrase-game__label-pronunciation');
		var ipaEl = qs(root, '.llm-phrase-game__ipa');
		var labelIpaEl = qs(root, '.llm-phrase-game__label-ipa');
		var approxEl = qs(root, '.llm-phrase-game__approx');
		var labelApproxEl = qs(root, '.llm-phrase-game__label-approx');
		var targetShow = qs(root, '.llm-phrase-game__target');
		var targetPeekBtn = qs(root, '.llm-phrase-game__peek-target');
		var altShow = qs(root, '.llm-phrase-game__alt');
		var bravoEl = qs(root, '.llm-phrase-game__bravo');
		var labelMainEl = qs(root, '.llm-phrase-game__label-main');
		var labelNotesEl = qs(root, '.llm-phrase-game__label-notes');
		var notesEditBtns = root.querySelectorAll('[data-llm-edit-field]');
		var adminEditsEl = qs(root, '.llm-phrase-game__admin-edits');
		var notesModal = qs(root, '#llm-fe-notes-modal');
		var notesEditorTa = qs(root, '#llm-fe-notes-editor');
		var notesModalTitle = qs(root, '#llm-fe-notes-modal-title');
		var notesModalStatus = qs(root, '.llm-fe-notes-modal__status');
		var notesModalSave = qs(root, '.llm-fe-notes-modal__save');
		var notesEditorReady = false;
		var notesEditField = 'grammar';
		var labelAltEl = qs(root, '.llm-phrase-game__label-alt');
		var doneEl = qs(root, '.llm-phrase-game__done');
		var cardEl = qs(root, '.llm-phrase-game__card');
		var yourPhraseWrap = qs(root, '.llm-phrase-game__your-phrase-wrap');
		var yourPhraseText = qs(root, '.llm-phrase-game__your-phrase-text');
		var mic1 = qs(root, '.llm-phrase-game__mic--1');
		var mic2 = qs(root, '.llm-phrase-game__mic--2');
		var clear1 = qs(root, '.llm-phrase-game__clear-input--1');
		var clear2 = qs(root, '.llm-phrase-game__clear-input--2');
		var clearVoice = qs(root, '.llm-phrase-game__clear-input--voice');
		var rewind2 = qs(root, '.llm-phrase-game__rewind-input--2');
		var rewindVoice = qs(root, '.llm-phrase-game__rewind-input--voice');
		var phase2RecapCounter   = qs(root, '.llm-phrase-game__phase2-recap__counter');
	var phase2RecapIface     = qs(root, '.llm-phrase-game__phase2-recap__interface');
	var phase2RecapPrompt    = qs(root, '.llm-phrase-game__phase2-recap__prompt');
	var listenTargetBtn      = qs(root, '.llm-phrase-game__listen-target:not(.llm-phrase-game__listen-target--phase2):not(.llm-phrase-game__peek-target):not(.llm-phrase-game__random-words-toggle)');
		var listenTargetBtnPhase2 = qs(root, '.llm-phrase-game__listen-target--phase2');
		var composePhase1 = qs(root, '.llm-phrase-game__compose--phase1');
		var composePhase2 = qs(root, '.llm-phrase-game__compose--phase2');
		var phase1Tools = qs(root, '.llm-phrase-game__phase1-tools');
	var feedbackEl      = qs(root, '.llm-phrase-game__phase1-feedback');
	var loadingNotesEl  = qs(root, '.llm-phrase-game__loading-notes');
	var dbStatusEl      = qs(root, '.llm-phrase-game__db-status');
	var notesWrap       = qs(root, '.llm-phrase-game__notes');
	var notesToggleBtn  = qs(root, '.llm-phrase-game__notes-toggle');
	var notesToggleText = qs(root, '.llm-phrase-game__notes-toggle-text');
	var notesPanel      = qs(root, '.llm-phrase-game__notes-panel');
	var storyNotesToggle = qs(root, '.llm-phrase-game__story-notes-toggle');
	var storyNotesPanel  = qs(root, '.llm-phrase-game__story-notes-panel');
	var storyNotesWrap = qs(root, '.llm-phrase-game__story-notes');
	var storyNotesPhraseEl = qs(root, '.llm-phrase-game__story-notes-phrase');
	var storyNotesTextEl = qs(root, '.llm-phrase-game__story-notes-text');
	var pronTipsToggle = qs(root, '.llm-phrase-game__pron-tips-toggle');
	var pronTipsPanel = qs(root, '.llm-phrase-game__pron-tips-panel');
	var pronTipsWrap = qs(root, '.llm-phrase-game__pron-tips');
	var pronTipsText = qs(root, '.llm-phrase-game__pron-tips-text');
	var showFieldPronBtn = qs(root, '.llm-phrase-game__show-field-pron');
	var fieldPronWrap = qs(root, '.llm-phrase-game__field-pron');
	var fieldPronToggle = qs(root, '.llm-phrase-game__field-pron-toggle');
	var fieldPronPanel = qs(root, '.llm-phrase-game__field-pron-panel');
	var fieldPronIpa = qs(root, '.llm-phrase-game__field-pron-ipa');
	var fieldPronApprox = qs(root, '.llm-phrase-game__field-pron-approx');
	var showFieldTransBtn = qs(root, '.llm-phrase-game__show-field-trans');
	var fieldTransWrap = qs(root, '.llm-phrase-game__field-trans');
	var fieldTransToggle = qs(root, '.llm-phrase-game__field-trans-toggle');
	var fieldTransPanel = qs(root, '.llm-phrase-game__field-trans-panel');
	var fieldTransText = qs(root, '.llm-phrase-game__field-trans-text');
	var fieldTransTitleEl = fieldTransToggle ? qs(fieldTransToggle, '.llm-phrase-game__notes-acc-text') : null;
	var fieldPronTitleEl = fieldPronToggle ? qs(fieldPronToggle, '.llm-phrase-game__notes-acc-text') : null;
	var fieldTransTitleText = fieldTransTitleEl ? String(fieldTransTitleEl.textContent || '').trim() : '';
	var fieldPronTitleText = fieldPronTitleEl ? String(fieldPronTitleEl.textContent || '').trim() : '';
	var fieldTransIntroPlayed = false;
	var fieldPronIntroPlayed = false;
	var fieldHelperIntroRun = 0;
	var fieldHelperIntroKind = '';
	var fieldAccFading = false;
	var invertedHintBtn = qs(root, '.llm-phrase-game__inverted-hint');
	var invertedHintLabel = qs(root, '.llm-phrase-game__inverted-hint-text-label');
	var invertedHintPanel = qs(root, '.llm-phrase-game__inverted-hint-panel');

	function randomWordsBlock(suffix, inputEl) {
		var wrap = qs(root, '.llm-phrase-game__random-words--' + suffix);
		return {
			wrap: wrap,
			input: inputEl,
			toggle: wrap ? qs(wrap, '.llm-phrase-game__random-words-toggle') : null,
			list: wrap ? qs(wrap, '.llm-phrase-game__random-words-list') : null
		};
	}

	var randomWordsBlocks = [randomWordsBlock('1', input1), randomWordsBlock('2', input2)]
		.filter(function (block) {
			return block.wrap && block.toggle && block.list;
		});

	function extraCharsBlock(suffix, inputEl) {
		var wrap = qs(root, '.llm-phrase-game__extra-chars--' + suffix);
		return {
			wrap: wrap,
			input: inputEl,
			toggle: wrap ? qs(wrap, '.llm-phrase-game__extra-chars-toggle') : null,
			panel: wrap ? qs(wrap, '.llm-phrase-game__extra-chars-panel') : null
		};
	}

	var extraCharsBlocks = [extraCharsBlock('1', input1), extraCharsBlock('2', input2)]
		.filter(function (block) {
			return block.wrap && block.toggle && block.panel;
		});

	function keyboardBlock(suffix, inputEl) {
		var wrap = qs(root, '.llm-phrase-game__keyboard--' + suffix);
		return {
			wrap: wrap,
			input: inputEl,
			toggle: wrap ? qs(wrap, '.llm-phrase-game__keyboard-toggle') : null,
			panel: wrap ? qs(wrap, '.llm-phrase-game__keyboard-panel') : null,
			shift: false
		};
	}

	var keyboardBlocks = [keyboardBlock('1', input1), keyboardBlock('2', input2)]
		.filter(function (block) {
			return block.wrap && block.toggle && block.panel;
		});

		var MODE_LOVEREWRITE     = 'loverewrite';
		var MODE_RESOLVE_GO      = 'resolve_go';
		var MODE_WRITE_TRANSLATE = 'write_translate';
		var MODE_READ_GO_FAST    = 'read_go_fast';
		var MODE_PLAY_INVERTED   = 'play_inverted';

		/* Utente loggato: vince il profilo. Ospite: localStorage, poi default. */
		function resolveLearningMode() {
			var fallback = cfg.learningModeDefault || cfg.learningMode || MODE_RESOLVE_GO;
			if (cfg.learningModeIsSaved) {
				return cfg.learningMode || fallback;
			}
			var key = cfg.learningModeStorageKey || 'llm_learning_mode';
			try {
				var stored = window.localStorage.getItem(key);
				if (stored) {
					return stored;
				}
				window.localStorage.setItem(key, fallback);
			} catch (e) {
				/* Storage non disponibile: resta il default. */
			}
			return fallback;
		}

		var learningMode = String(resolveLearningMode() || '').replace(/[^a-z0-9_-]/gi, '');
		if (!learningMode) {
			learningMode = MODE_RESOLVE_GO;
		}
		var isResolveGo      = learningMode === MODE_RESOLVE_GO;
		var isWriteTranslate = learningMode === MODE_WRITE_TRANSLATE;
		var isReadGoFast     = learningMode === MODE_READ_GO_FAST;
		var isPlayInverted   = learningMode === MODE_PLAY_INVERTED;
		/* Modalità con la sola fase 1: stesso layout, cambia solo cosa succede al click. */
		var isSinglePhase = isResolveGo || isWriteTranslate || isReadGoFast || isPlayInverted;
		var writeTargetInput = input1;
		root.classList.add('llm-phrase-game--mode-' + learningMode.replace(/_/g, '-'));
		if (isSinglePhase) {
			root.classList.add('llm-phrase-game--single-phase');
		}
		if (isWriteTranslate && voiceFieldEl) {
			voiceFieldEl.hidden = false;
		}

		/* Dove finiscono i messaggi di completamento frase. */
		var completionMsgEl = (isSinglePhase && messageSoloEl) ? messageSoloEl : messagePhase2El;

		var interfaceLang = cfg.interfaceLangLabel || '';
		var introPromptKey = isPlayInverted
			? 'playInvertedPrompt'
			: (isReadGoFast
				? 'readGoFastPrompt'
				: ((isResolveGo || isWriteTranslate) ? 'resolveGoPrompt' : 'translatePrompt'));

		function flagEmoji(code) {
			var map = cfg.langFlags || {};
			return map[String(code || '').toLowerCase()] || '';
		}

		function setFlagEls(selector, emoji) {
			qsa(root, selector).forEach(function (el) {
				el.textContent = emoji;
			});
		}

		function syncLangFlags() {
			var sourceCode = isPlayInverted
				? (cfg.targetLangCode || '')
				: (cfg.interfaceLangCode || '');
			var writeCode = isPlayInverted
				? (cfg.interfaceLangCode || '')
				: (cfg.targetLangCode || '');
			setFlagEls('.llm-phrase-game__lang-flag--source', flagEmoji(sourceCode));
			setFlagEls('.llm-phrase-game__lang-flag--write', flagEmoji(writeCode));
		}

		function setTranslatePromptText(text) {
			var value = text || '';
			if (promptTransText) {
				promptTransText.textContent = value;
			} else if (promptTrans) {
				promptTrans.textContent = value;
			}
			if (promptTrans) {
				promptTrans.classList.toggle('llm-phrase-game__prompt--has-text', value !== '');
			}
		}

		function setRewritePromptText(text) {
			if (promptRewriteText) {
				promptRewriteText.textContent = text || '';
				return;
			}
			if (promptRewrite) {
				promptRewrite.textContent = text || '';
			}
		}

		syncLangFlags();

		/* Opzioni di aiuto: stessa regola della modalità, profilo o localStorage. */
		function resolveLearningOptions() {
			if (isPlayInverted) {
				return [];
			}
			if (cfg.learningModeIsSaved) {
				return Array.isArray(cfg.learningOptions) ? cfg.learningOptions : [];
			}
			var defaults = Array.isArray(cfg.learningOptionsDefault) && cfg.learningOptionsDefault.length
				? cfg.learningOptionsDefault.slice()
				: ['random_words', 'listen_replay_loop'];
			try {
				var key = cfg.learningOptionsStorageKey || 'llm_learning_options';
				var raw = window.localStorage.getItem(key);
				if (raw === null) {
					try {
						window.localStorage.setItem(key, defaults.join(','));
					} catch (writeErr) {
						/* Storage non disponibile. */
					}
					return defaults;
				}
				return raw ? raw.split(',') : [];
			} catch (e) {
				return defaults;
			}
		}

		var randomWordsOn =
			resolveLearningOptions().indexOf(cfg.optionRandomWords || 'random_words') !== -1;
		var extraCharsOn =
			resolveLearningOptions().indexOf(cfg.optionExtraChars || 'extra_chars') !== -1;
		var listenReplayAfterMic =
			resolveLearningOptions().indexOf(cfg.optionListenReplayLoop || 'listen_replay_loop') !== -1;

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
			if (show && !listenTargetBtn._llmListenWrapReady) {
				ensureListenTargetWrap(listenTargetBtn);
			}
			listenTargetBtn.hidden = !show;
			listenTargetBtn.classList.toggle(
				'llm-phrase-game__listen-target--force-hidden',
				!show
			);
			if (listenTargetBtn._llmListenTargetWrap) {
				listenTargetBtn._llmListenTargetWrap.hidden = !show;
			}
			if (show) {
				listenTargetBtn.removeAttribute('aria-hidden');
			} else {
				listenTargetBtn.setAttribute('aria-hidden', 'true');
				hideListenCountdown(listenTargetBtn);
				var listenHelpBtnHide = qs(root, '.llm-phrase-game__listen-help-btn');
				var listenHelpBubbleHide = qs(root, '.llm-phrase-game__listen-help-bubble');
				if (listenHelpBtnHide) {
					listenHelpBtnHide.setAttribute('aria-expanded', 'false');
				}
				if (listenHelpBubbleHide) {
					listenHelpBubbleHide.hidden = true;
				}
			}
		}

		if (pendingStoryIntroTypewriter) {
			root.classList.add('llm-phrase-game--story-intro-active');
			setListenTargetVisible(false);
			if (cardEl) {
				cardEl.hidden = true;
			}
		}

		function showPhase2RewritePrompt() {
			setRewritePromptText('');
		}

		function setComposePhaseVisible(phaseNum, visible) {
			var el = phaseNum === 1 ? composePhase1 : composePhase2;
			if (!el) {
				return;
			}
			el.classList.toggle('llm-phrase-game__compose--visible', !!visible);
			if (phaseNum === 1 && phase1Tools) {
				phase1Tools.classList.toggle('llm-phrase-game__phase1-tools--visible', !!visible);
			}
			if (phaseNum === 2 && visible) {
				showPhase2RewritePrompt();
			}
		}

		var phraseIx = 0;
		var savedPhraseIndexOnLoad =
			cfg.savedPhraseIndex !== undefined && cfg.savedPhraseIndex !== null
				? parseInt(cfg.savedPhraseIndex, 10)
				: 0;
		if (isNaN(savedPhraseIndexOnLoad)) {
			savedPhraseIndexOnLoad = 0;
		}

	function canEditNotes() {
		return !!cfg.canEditNotes && notesEditBtns.length > 0 && !!notesModal && !!notesEditorTa;
	}

	function notesFieldTitle(field) {
		if (field === 'notes') {
			return i18n.notesEditNotes || 'Modifica Note Frase (admin)';
		}
		if (field === 'alt') {
			return i18n.notesEditAlt || 'Modifica Appunti frase alternativa (admin)';
		}
		if (field === 'pronunciation') {
			return i18n.notesEditPronunciation || 'Modifica note di pronuncia';
		}
		if (field === 'ipa') {
			return i18n.notesEditIpa || 'Modifica trascrizione fonetica IPA';
		}
		if (field === 'approx') {
			return i18n.notesEditApprox || 'Modifica pronuncia approssimata';
		}
		return i18n.notesEditGrammar || 'Modifica Appunti della frase (admin)';
	}

	function notesFieldValue(field) {
		var p = phrases[phraseIx] || {};
		if (field === 'notes') {
			return p.notes || '';
		}
		if (field === 'alt') {
			return p.alt || '';
		}
		if (field === 'pronunciation') {
			return p.pronunciation || '';
		}
		if (field === 'ipa') {
			return p.ipa || '';
		}
		if (field === 'approx') {
			return p.approx || '';
		}
		return p.grammar || '';
	}

	function getWpEditorApi() {
		if (window.wp && wp.oldEditor && typeof wp.oldEditor.initialize === 'function') {
			return wp.oldEditor;
		}
		if (window.wp && wp.editor && typeof wp.editor.initialize === 'function') {
			return wp.editor;
		}
		return null;
	}

	function grammarToEditorHtml(raw) {
		var s = String(raw || '');
		if (!s) {
			return '';
		}
		if (/<[a-z][\s\S]*>/i.test(s)) {
			return s;
		}
		return s.split(/\n\n+/).map(function (block) {
			return '<p>' + String(block).replace(/\n/g, '<br />') + '</p>';
		}).join('');
	}

	function setNotesModalStatus(text, isError) {
		if (!notesModalStatus) {
			return;
		}
		if (!text) {
			notesModalStatus.hidden = true;
			notesModalStatus.textContent = '';
			notesModalStatus.classList.remove('is-error', 'is-ok');
			return;
		}
		notesModalStatus.hidden = false;
		notesModalStatus.textContent = text;
		notesModalStatus.classList.toggle('is-error', !!isError);
		notesModalStatus.classList.toggle('is-ok', !isError);
	}

	function getNotesEditorContent() {
		if (window.tinymce) {
			var ed = tinymce.get('llm-fe-notes-editor');
			if (ed) {
				return ed.getContent();
			}
		}
		return notesEditorTa ? notesEditorTa.value : '';
	}

	function setNotesEditorContent(html) {
		if (notesEditorTa) {
			notesEditorTa.value = html || '';
		}
		if (window.tinymce) {
			var ed = tinymce.get('llm-fe-notes-editor');
			if (ed) {
				ed.setContent(html || '');
			}
		}
	}

	function ensureNotesEditor() {
		if (notesEditorReady) {
			return true;
		}
		var api = getWpEditorApi();
		if (!api) {
			return false;
		}
		api.initialize('llm-fe-notes-editor', {
			tinymce: {
				wpautop: true,
				plugins: 'lists,paste,tabfocus,textcolor,colorpicker,wordpress,wpautoresize,wplink,wptextpattern',
				toolbar1: 'formatselect,bold,italic,underline,strikethrough,|,bullist,numlist,|,forecolor,backcolor,|,removeformat,|,undo,redo',
				toolbar2: '',
				menubar: false,
				branding: false,
				height: 380,
				relative_urls: false,
				convert_urls: false,
				body_class: 'llm-fe-notes-tinymce'
			},
			quicktags: true,
			mediaButtons: false
		});
		notesEditorReady = true;
		return true;
	}

	function closeNotesModal() {
		if (!notesModal) {
			return;
		}
		notesModal.hidden = true;
		document.body.classList.remove('llm-fe-notes-modal-open');
		setNotesModalStatus('', false);
		if (notesModalSave) {
			notesModalSave.disabled = false;
		}
	}

	function openNotesModal(field) {
		if (!canEditNotes()) {
			return;
		}
		notesEditField = (field === 'notes' || field === 'alt' || field === 'pronunciation' || field === 'ipa' || field === 'approx') ? field : 'grammar';
		if (notesModalTitle) {
			notesModalTitle.textContent = notesFieldTitle(notesEditField);
		}
		notesModal.hidden = false;
		document.body.classList.add('llm-fe-notes-modal-open');
		setNotesModalStatus('', false);
		ensureNotesEditor();
		setTimeout(function () {
			setNotesEditorContent(grammarToEditorHtml(notesFieldValue(notesEditField)));
			if (window.tinymce) {
				var ed = tinymce.get('llm-fe-notes-editor');
				if (ed) {
					ed.focus();
				}
			} else if (notesEditorTa) {
				notesEditorTa.focus();
			}
		}, 80);
	}

	function saveNotesFromModal() {
		if (!canEditNotes() || !notesModalSave) {
			return;
		}
		notesModalSave.disabled = true;
		setNotesModalStatus('', false);
		var body = new URLSearchParams();
		body.set('action', 'llm_fe_save_phrase_notes');
		body.set('nonce', String(cfg.editNotesNonce || ''));
		body.set('story_id', String(storyId));
		body.set('phrase_index', String(phraseIx));
		body.set('field', notesEditField);
		body.set('grammar', getNotesEditorContent());
		fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		})
			.then(function (res) { return res.json(); })
			.then(function (json) {
				if (!json || !json.success) {
					var err = (json && json.data && json.data.message) || (i18n.notesEditError || '');
					setNotesModalStatus(err, true);
					notesModalSave.disabled = false;
					return;
				}
				setNotesModalStatus(i18n.notesEditSaved || 'Salvato nel database', false);
				setTimeout(function () {
					window.location.reload();
				}, 700);
			})
			.catch(function () {
				setNotesModalStatus(i18n.notesEditError || i18n.ajaxError || '', true);
				notesModalSave.disabled = false;
			});
	}

	var fillSolutionBtn = qs(root, '.llm-phrase-game__admin-fill-solution');
	if (fillSolutionBtn) {
		fillSolutionBtn.addEventListener('click', function (e) {
			e.preventDefault();
			var solution = plainSpeechText(currentPhraseTargetText());
			if (!solution) {
				return;
			}
			if (input1) {
				input1.value = solution;
				if (typeof input1._llmSyncClearBtn === 'function') {
					input1._llmSyncClearBtn();
				}
			}
			if (inputVoice) {
				clearControllaStrikeTimer();
				hideVoiceExtrasMark();
				inputVoice.value = typeof withTrailingMicSpace === 'function'
					? withTrailingMicSpace(solution)
					: solution;
				if (typeof inputVoice._llmSyncClearBtn === 'function') {
					inputVoice._llmSyncClearBtn();
				}
			}
			if (typeof syncWriteTranslatePeekBlur === 'function') {
				syncWriteTranslatePeekBlur();
			}
		});
	}

	if (canEditNotes()) {
		notesEditBtns.forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				openNotesModal(btn.getAttribute('data-llm-edit-field') || 'grammar');
			});
		});
		notesModal.addEventListener('click', function (e) {
			if (e.target && e.target.getAttribute && e.target.getAttribute('data-llm-notes-close') === '1') {
				closeNotesModal();
			}
		});
		if (notesModalSave) {
			notesModalSave.addEventListener('click', function (e) {
				e.preventDefault();
				saveNotesFromModal();
			});
		}
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && notesModal && !notesModal.hidden) {
				closeNotesModal();
			}
		});
	}

	var speechRec = null;
	var speechBase = '';
	var speechInsertSuffix = '';
	var speechSessionStartValue = '';
	var speechSegmentTranscript = '';
	var voiceCaret = { start: null, end: null };
	var controllaStrikeTimer = null;
	var activeMicTa = null;
	var activeMicBtn = null;
	var micWordsThisPhrase = 0;
	var micState = 'idle'; // 'idle' | 'pending' | 'listening'
	var micPermissionGranted = false;
	var MIC_PENDING_MS = 2000;
	var MIC_SESSION_MS = 6000;
	var MIC_BAR_FADE_MS = 180;
	var MIC_RESTART_GAP_MS = 400;
	var micSessionActive = false;
	var peekBlurVoiceHold = false;
	var peekBlurLockedOn = inputVoice || input1;
	var micSessionTimer = null;
	var micPendingTimer = null;
	var micRestartTimer = null;
	var micPendingPhaseDone = false;
	var micRecognitionStarted = false;
	var lastCommittedSpeechKey = '';
	var micFeedbackTimer = null;
	var LISTEN_REPLAY_DELAY_MS = 0;
	var MIC_FEEDBACK_DISPLAY_MS = 8000;
	var listenReplayTimer = null;
	var targetPeekTimer = null;
	var TARGET_PEEK_MS = 10000;
	var TARGET_PEEK_FADE_MS = 400;

	function normalizeSpeechSpace(text) {
		return String(text || '').replace(/\s+/g, ' ').trim();
	}

	function speechNormKey(text) {
		return normalizeSpeechSpace(text).toLowerCase();
	}

	function isMobileSpeechEngine() {
		var ua = String(navigator.userAgent || '');
		if (/Android|iPhone|iPad|iPod/i.test(ua)) {
			return true;
		}
		try {
			return !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches);
		} catch (e) {
			return false;
		}
	}

	/**
	 * Chrome Android/iOS spesso allunga "tak" in "tak tak tak…" dentro un unico risultato.
	 * Collassa solo ripetizioni consecutive da 3 in su, così "very very" resta valido.
	 */
	function collapseStutterTokens(text) {
		var raw = normalizeSpeechSpace(text);
		if (!raw) {
			return '';
		}
		var words = raw.split(' ');
		if (words.length < 3) {
			return raw;
		}
		var out = [];
		var i = 0;
		while (i < words.length) {
			var key = words[i].toLowerCase();
			var j = i + 1;
			while (j < words.length && words[j].toLowerCase() === key) {
				j++;
			}
			if (j - i >= 3) {
				out.push(words[i]);
			} else {
				var k;
				for (k = i; k < j; k++) {
					out.push(words[k]);
				}
			}
			i = j;
		}
		return out.join(' ');
	}

	function isStutterGrowth(shortText, longText) {
		var s = speechNormKey(shortText);
		var l = speechNormKey(longText);
		if (!s || !l || l === s || l.indexOf(s) !== 0) {
			return false;
		}
		var rest = l.slice(s.length).replace(/^\s+/, '');
		if (!rest) {
			return true;
		}
		while (rest) {
			if (rest.indexOf(s) !== 0) {
				return false;
			}
			rest = rest.slice(s.length).replace(/^\s+/, '');
		}
		return true;
	}

	function isDuplicateSpeechChunk(base, chunk) {
		var b = speechNormKey(base);
		var c = speechNormKey(chunk);
		if (!c) {
			return true;
		}
		if (!b) {
			return false;
		}
		if (b === c) {
			return true;
		}
		if (b.length >= c.length && b.slice(b.length - c.length) === c) {
			var before = b.slice(0, b.length - c.length);
			return !before || before.slice(-1) === ' ';
		}
		return false;
	}

	/**
	 * Unisce i segmenti del motore senza duplicare frasi cumulative (comune su mobile).
	 * Se un segmento estende il precedente, sostituisce — non concatena.
	 */
	function combineEngineSegments(parts) {
		var out = '';
		var p;
		for (p = 0; p < parts.length; p++) {
			var tr = String(parts[p].text || '');
			if (!tr) {
				continue;
			}
			if (!out) {
				out = tr;
				continue;
			}
			var o = speechNormKey(out);
			var t = speechNormKey(tr);
			if (t === o) {
				continue;
			}
			if (isStutterGrowth(out, tr)) {
				continue;
			}
			if (t.indexOf(o) === 0) {
				out = tr;
				continue;
			}
			if (o.indexOf(t) === 0 || isStutterGrowth(tr, out)) {
				continue;
			}
			out = out + (/\s$/.test(out) ? '' : ' ') + tr;
		}
		return collapseStutterTokens(out);
	}

	function getEngineTranscriptFromResults(results) {
		if (!results || !results.length) {
			return '';
		}
		var parts = [];
		var i;
		for (i = 0; i < results.length; i++) {
			if (!results[i] || !results[i][0]) {
				continue;
			}
			parts.push({
				text: results[i][0].transcript,
				final: !!results[i].isFinal
			});
		}
		return combineEngineSegments(parts);
	}

	function padJoinSpeech(left, right) {
		left = String(left || '');
		right = String(right || '');
		if (left && right && !/\s$/.test(left) && !/^\s/.test(right)) {
			return left + ' ' + right;
		}
		return left + right;
	}

	function writeMicTextarea(ta) {
		if (!ta) {
			return;
		}
		var mid = collapseStutterTokens(padJoinSpeech(speechBase, speechSegmentTranscript));
		if (mid && speechInsertSuffix && !/\s$/.test(mid) && !/^\s/.test(speechInsertSuffix)) {
			mid += ' ';
		}
		ta.value = mid + String(speechInsertSuffix || '');
		if (typeof ta.setSelectionRange === 'function') {
			try {
				ta.setSelectionRange(mid.length, mid.length);
			} catch (e) {
				/* ignore */
			}
		}
		if (ta === inputVoice) {
			voiceCaret.start = mid.length;
			voiceCaret.end = mid.length;
		}
		if (typeof ta._llmSyncClearBtn === 'function') {
			ta._llmSyncClearBtn();
		}
		syncWriteTranslatePeekBlur();
	}

	function commitMicSpeechToBase(ta) {
		var chunk = collapseStutterTokens(String(speechSegmentTranscript || ''));
		speechSegmentTranscript = '';
		if (!chunk) {
			return;
		}
		var key = speechNormKey(chunk);
		if (key === lastCommittedSpeechKey || isDuplicateSpeechChunk(speechBase, chunk)) {
			return;
		}
		speechBase += chunk;
		if (speechBase.length && !/\s$/.test(speechBase)) {
			speechBase += ' ';
		}
		lastCommittedSpeechKey = key;
		writeMicTextarea(ta);
	}

	function countNewWords(oldText, newText) {
		var oldLen = tokenizeWords(oldText).length;
		var newLen = tokenizeWords(newText).length;
		return Math.max(0, newLen - oldLen);
	}

		var TTS_SLOW_RATE = 0.78;
		var TTS_SLOWER_RATE = 0.52;

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

		function typewriterInto(el, text, isAlive, tickMs) {
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
				function nextDelay() {
					if (typeof tickMs === 'function') {
						return tickMs(s.charAt(i - 1) || '', i, s);
					}
					return tickMs == null ? TYPE_TICK_MS : tickMs;
				}
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
					setTimeout(tick, nextDelay());
				}
				tick();
			});
		}

	function resetPhraseNotes() {
		if (phraseNotesEl) {
			phraseNotesEl.innerHTML = '';
		}
		if (phraseNotesWrap) {
			phraseNotesWrap.hidden = true;
			phraseNotesWrap.style.opacity = '';
			phraseNotesWrap.style.transition = '';
		}
	}

	function hideAdminEdits() {
		/* I pulsanti admin restano sopra Note della storia. */
	}

	function prepareAnalysisStreamLayout() {
		resetPhraseNotes();
		hideAdminEdits();
		if (bravoEl) {
			bravoEl.textContent = '';
		}
		if (grammarEl) {
			grammarEl.innerHTML = '';
		}
		if (pronunciationEl) {
			pronunciationEl.innerHTML = '';
		}
		if (ipaEl) {
			ipaEl.innerHTML = '';
		}
		if (approxEl) {
			approxEl.innerHTML = '';
		}
		if (targetShow) {
			targetShow.innerHTML = '';
		}
		resetTargetPeek();
		resetAltNotes();
		if (labelMainEl) {
			labelMainEl.style.opacity = '0';
		}
		if (labelNotesEl) {
			labelNotesEl.hidden = true;
			labelNotesEl.style.opacity = '0';
		}
		if (labelPronunciationEl) {
			labelPronunciationEl.hidden = true;
			labelPronunciationEl.style.opacity = '0';
		}
		if (labelIpaEl) {
			labelIpaEl.hidden = true;
			labelIpaEl.style.opacity = '0';
		}
		if (labelApproxEl) {
			labelApproxEl.hidden = true;
			labelApproxEl.style.opacity = '0';
		}
		if (labelAltEl) {
			labelAltEl.hidden = true;
			labelAltEl.style.opacity = '0';
		}
		if (promptRewrite) {
			setRewritePromptText('');
			promptRewrite.style.opacity = '0';
		}
	}

	function clearTargetPeekTimer() {
		if (targetPeekTimer) {
			clearTimeout(targetPeekTimer);
			targetPeekTimer = null;
		}
	}

	function fadeElementOpacity(el, opacity, dur) {
		return new Promise(function (resolve) {
			if (!el) {
				resolve();
				return;
			}
			var d = dur || TARGET_PEEK_FADE_MS;
			el.style.transition = 'opacity ' + d + 'ms ease';
			requestAnimationFrame(function () {
				el.style.opacity = String(opacity);
				setTimeout(resolve, d);
			});
		});
	}

	/** Il pannello impostazioni può cambiare gli accenti dopo il boot: rileggiamo dal DOM. */
	function syncStrictAccentsFromDom() {
		var accentsToggleEl = document.querySelector('.llm-story-settings__accents-input');
		if (accentsToggleEl && window.llmPhraseGame) {
			window.llmPhraseGame.strictAccents = accentsToggleEl.checked;
		}
	}

	/**
	 * Appunti a richiesta ("Risolvi e vai"): riusa il blocco analisi esistente
	 * spostandolo sotto il pulsante, così non si duplica il markup.
	 */
	var notesLoaded = false;
	var phraseNotesOpened = false;

	function setStoryNotesOpen(open) {
		if (!storyNotesToggle || !storyNotesPanel) {
			return;
		}
		var isOpen = !!open;
		storyNotesToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		storyNotesPanel.hidden = !isOpen;
		if (isOpen) {
			setNotesOpen(false);
			setPronTipsOpen(false);
		}
	}

	function hideShowFieldTransBtn() {
		if (!showFieldTransBtn) {
			return;
		}
		showFieldTransBtn.hidden = true;
		showFieldTransBtn.style.opacity = '';
		showFieldTransBtn.style.transition = '';
	}

	function revealShowFieldTransBtn() {
		if (!showFieldTransBtn) {
			return Promise.resolve();
		}
		showFieldTransBtn.hidden = false;
		showFieldTransBtn.style.opacity = '0';
		return fadeElementOpacity(showFieldTransBtn, 1, 400);
	}

	function setNotesOpen(open) {
		if (!notesToggleBtn || !notesPanel) {
			return;
		}
		var isOpen = !!open;
		notesPanel.hidden = !isOpen;
		notesToggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		if (notesToggleText) {
			notesToggleText.textContent = isOpen
				? (i18n.notesToggleHide || 'Nascondi i consigli sulla traduzione')
				: (i18n.notesToggleShow || 'Consigli sulla traduzione');
		}
		if (isOpen) {
			setStoryNotesOpen(false);
			setPronTipsOpen(false);
		}
		if (!isOpen || notesLoaded) {
			return;
		}
		notesLoaded = true;
		var p = phrases[phraseIx];
		analysisEl.hidden = false;
		hideShowFieldTransBtn();
		runAnalysisTypestream({
			notesOnly: true,
			skipYourPhrase: true,
			skipBravo: true,
			notes: (p && p.notes) || '',
			grammar: (p && p.grammar) || '',
			target: '',
			alt: (p && p.alt) || '',
			pronunciation: (p && p.pronunciation) || '',
			ipa: (p && p.ipa) || '',
			approx: (p && p.approx) || ''
		});
		if (labelMainEl) {
			labelMainEl.hidden = true;
		}
		if (targetShow) {
			targetShow.hidden = true;
			targetShow.innerHTML = '';
		}
		if (targetPeekBtn) {
			targetPeekBtn.hidden = true;
		}
		if (labelNotesEl) {
			labelNotesEl.hidden = true;
		}
	}

	function getPhase1WriteInput() {
		if (!isWriteTranslate) {
			return input1;
		}
		var active = document.activeElement;
		if (active === inputVoice || active === input1) {
			return active;
		}
		if (writeTargetInput === inputVoice || writeTargetInput === input1) {
			return writeTargetInput;
		}
		return input1;
	}

	function rememberWriteTarget(el) {
		if (el === input1 || el === inputVoice) {
			writeTargetInput = el;
		}
	}

	function destForPhase1Helper(blockInput) {
		if (isWriteTranslate && blockInput === input1) {
			return getPhase1WriteInput();
		}
		return blockInput;
	}

	function readInputCaret(inputEl) {
		var val = inputEl.value || '';
		var start = val.length;
		var end = val.length;
		if (document.activeElement === inputEl && typeof inputEl.selectionStart === 'number') {
			start = inputEl.selectionStart;
			end = typeof inputEl.selectionEnd === 'number' ? inputEl.selectionEnd : start;
		} else if (inputEl === inputVoice && voiceCaret.start !== null && voiceCaret.start !== undefined) {
			start = voiceCaret.start;
			end = voiceCaret.end !== null && voiceCaret.end !== undefined ? voiceCaret.end : start;
		} else if (inputEl._llmCaret && inputEl._llmCaret.start !== null && inputEl._llmCaret.start !== undefined) {
			start = inputEl._llmCaret.start;
			end = inputEl._llmCaret.end !== null && inputEl._llmCaret.end !== undefined ? inputEl._llmCaret.end : start;
		} else if (typeof inputEl.selectionStart === 'number') {
			start = inputEl.selectionStart;
			end = typeof inputEl.selectionEnd === 'number' ? inputEl.selectionEnd : start;
		}
		start = Math.max(0, Math.min(Number(start) || 0, val.length));
		end = Math.max(start, Math.min(Number(end) || start, val.length));
		return { start: start, end: end };
	}

	function storeInputCaret(inputEl, start, end) {
		if (!inputEl) {
			return;
		}
		if (!inputEl._llmCaret) {
			inputEl._llmCaret = {};
		}
		inputEl._llmCaret.start = start;
		inputEl._llmCaret.end = end;
		if (inputEl === inputVoice) {
			voiceCaret.start = start;
			voiceCaret.end = end;
		}
	}

	function appendWordToInput(inputEl, word) {
		if (!inputEl || inputEl.readOnly || inputEl.disabled) {
			return;
		}
		var val = inputEl.value || '';
		var caret = readInputCaret(inputEl);
		var before = val.slice(0, caret.start);
		var after = val.slice(caret.end);
		var prefix = (before && !/\s$/.test(before)) ? ' ' : '';
		var suffix = (after && !/^\s/.test(after)) ? ' ' : '';
		var insert = prefix + word + suffix;
		inputEl.value = before + insert + after;
		var pos = caret.start + insert.length;
		if (suffix) {
			pos -= suffix.length;
		}
		inputEl.dispatchEvent(new Event('input', { bubbles: true }));
		try {
			inputEl.setSelectionRange(pos, pos);
		} catch (e) {
			/* ignore */
		}
		storeInputCaret(inputEl, pos, pos);
		/* Niente focus: su mobile aprirebbe la tastiera. */
	}

	function insertKbChar(inputEl, ch) {
		if (!inputEl || inputEl.readOnly || inputEl.disabled) {
			return;
		}
		var val = inputEl.value || '';
		var caret = readInputCaret(inputEl);
		inputEl.value = val.slice(0, caret.start) + ch + val.slice(caret.end);
		var pos = caret.start + String(ch).length;
		inputEl.dispatchEvent(new Event('input', { bubbles: true }));
		try {
			inputEl.setSelectionRange(pos, pos);
		} catch (e) {
			/* ignore */
		}
		storeInputCaret(inputEl, pos, pos);
	}

	function deleteKbChar(inputEl) {
		if (!inputEl || inputEl.readOnly || inputEl.disabled) {
			return;
		}
		var val = inputEl.value || '';
		var caret = readInputCaret(inputEl);
		var start = caret.start;
		var end = caret.end;
		if (start === end) {
			if (start <= 0) {
				return;
			}
			start = start - 1;
		}
		inputEl.value = val.slice(0, start) + val.slice(end);
		inputEl.dispatchEvent(new Event('input', { bubbles: true }));
		try {
			inputEl.setSelectionRange(start, start);
		} catch (e) {
			/* ignore */
		}
		storeInputCaret(inputEl, start, start);
	}

	function appendCharToInput(inputEl, ch) {
		if (!inputEl || inputEl.readOnly || inputEl.disabled) {
			return;
		}
		var val = inputEl.value || '';
		var start = typeof inputEl.selectionStart === 'number' ? inputEl.selectionStart : val.length;
		var end = typeof inputEl.selectionEnd === 'number' ? inputEl.selectionEnd : val.length;
		inputEl.value = val.slice(0, start) + ch + val.slice(end);
		inputEl.dispatchEvent(new Event('input', { bubbles: true }));
		inputEl.focus();
		var pos = start + String(ch).length;
		try {
			inputEl.setSelectionRange(pos, pos);
		} catch (e) {
			/* ignore */
		}
	}

	/* Set caratteri per lingua target (etichette native della lingua studiata). */
	var EXTRA_CHARS_BY_LANG = {
		pl: {
			lower: [
				{ c: 'ą', n: 'ogonek' }, { c: 'ć', n: 'kreska' }, { c: 'ę', n: 'ogonek' },
				{ c: 'ł', n: 'kreska ukośna' }, { c: 'ń', n: 'kreska' }, { c: 'ó', n: 'kreska' },
				{ c: 'ś', n: 'kreska' }, { c: 'ź', n: 'kreska' }, { c: 'ż', n: 'kropka' }
			],
			upper: [
				{ c: 'Ą', n: 'ogonek' }, { c: 'Ć', n: 'kreska' }, { c: 'Ę', n: 'ogonek' },
				{ c: 'Ł', n: 'kreska ukośna' }, { c: 'Ń', n: 'kreska' }, { c: 'Ó', n: 'kreska' },
				{ c: 'Ś', n: 'kreska' }, { c: 'Ź', n: 'kreska' }, { c: 'Ż', n: 'kropka' }
			],
			symbols: [
				{ c: '„', n: 'cudzysłów dolny' }, { c: '”', n: 'cudzysłów górny' },
				{ c: '»', n: 'cudzysłów ostrokątny' }, { c: '«', n: 'cudzysłów ostrokątny' },
				{ c: '—', n: 'myślnik' }, { c: '…', n: 'wielokropek' }
			]
		},
		it: {
			lower: [
				{ c: 'à', n: 'accento grave' }, { c: 'è', n: 'accento grave' }, { c: 'é', n: 'accento acuto' },
				{ c: 'ì', n: 'accento grave' }, { c: 'ò', n: 'accento grave' }, { c: 'ù', n: 'accento grave' }
			],
			upper: [
				{ c: 'À', n: 'accento grave' }, { c: 'È', n: 'accento grave' }, { c: 'É', n: 'accento acuto' },
				{ c: 'Ì', n: 'accento grave' }, { c: 'Ò', n: 'accento grave' }, { c: 'Ù', n: 'accento grave' }
			],
			symbols: [
				{ c: '«', n: 'virgolette basse apertura' }, { c: '»', n: 'virgolette basse chiusura' },
				{ c: '\u201C', n: 'virgolette alte apertura' }, { c: '\u201D', n: 'virgolette alte chiusura' },
				{ c: '\u2019', n: 'apostrofo tipografico' },
				{ c: '—', n: 'lineetta' }, { c: '…', n: 'puntini di sospensione' }
			]
		},
		es: {
			lower: [
				{ c: 'á', n: 'acento' }, { c: 'é', n: 'acento' }, { c: 'í', n: 'acento' },
				{ c: 'ó', n: 'acento' }, { c: 'ú', n: 'acento' }, { c: 'ü', n: 'diéresis' }, { c: 'ñ', n: 'eñe' }
			],
			upper: [
				{ c: 'Á', n: 'acento' }, { c: 'É', n: 'acento' }, { c: 'Í', n: 'acento' },
				{ c: 'Ó', n: 'acento' }, { c: 'Ú', n: 'acento' }, { c: 'Ü', n: 'diéresis' }, { c: 'Ñ', n: 'eñe' }
			],
			symbols: [
				{ c: '¿', n: 'apertura interrogación' }, { c: '¡', n: 'apertura exclamación' },
				{ c: '«', n: 'comillas angulares' }, { c: '»', n: 'comillas angulares' },
				{ c: '—', n: 'raya' }, { c: '…', n: 'puntos suspensivos' }
			]
		}
	};

	function getWriteLangCode() {
		var code = (typeof isPlayInverted !== 'undefined' && isPlayInverted)
			? (cfg.interfaceLangCode || cfg.targetLangCode || '')
			: (cfg.targetLangCode || '');
		return String(code || '').toLowerCase();
	}

	function getExtraCharsSet() {
		var code = getWriteLangCode();
		return EXTRA_CHARS_BY_LANG[code] || null;
	}

	function renderExtraCharsPanel(block) {
		if (!block.panel) {
			return;
		}
		var set = getExtraCharsSet();
		block.panel.innerHTML = '';
		if (!set) {
			return;
		}
		var sections = [
			{ key: 'lower', label: i18n.extraCharsLower || 'Minuscole', items: set.lower },
			{ key: 'upper', label: i18n.extraCharsUpper || 'Maiuscole', items: set.upper },
			{ key: 'symbols', label: i18n.extraCharsSymbols || 'Simboli', items: set.symbols }
		];
		sections.forEach(function (sec) {
			if (!sec.items || !sec.items.length) {
				return;
			}
			var row = document.createElement('div');
			row.className = 'llm-phrase-game__extra-chars-row';
			var lab = document.createElement('p');
			lab.className = 'llm-phrase-game__extra-chars-row-label';
			lab.textContent = sec.label;
			var btns = document.createElement('div');
			btns.className = 'llm-phrase-game__extra-chars-btns';
			sec.items.forEach(function (item) {
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'llm-phrase-game__extra-char';
				btn.setAttribute('aria-label', item.c + ' ' + (item.n || ''));
				var glyph = document.createElement('span');
				glyph.className = 'llm-phrase-game__extra-char-glyph';
				glyph.textContent = item.c;
				var name = document.createElement('span');
				name.className = 'llm-phrase-game__extra-char-name';
				name.textContent = item.n || '';
				btn.appendChild(glyph);
				btn.appendChild(name);
				btn.addEventListener('mousedown', function (e) {
					e.preventDefault();
				});
				btn.addEventListener('click', function () {
					appendCharToInput(destForPhase1Helper(block.input), item.c);
				});
				btns.appendChild(btn);
			});
			row.appendChild(lab);
			row.appendChild(btns);
			block.panel.appendChild(row);
		});
	}

	function setExtraCharsOpen(block, open) {
		if (!block.toggle || !block.panel) {
			return;
		}
		block.toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		block.panel.hidden = !open;
		if (open && !block.panel.childNodes.length) {
			renderExtraCharsPanel(block);
		}
	}

	function resetExtraChars() {
		extraCharsBlocks.forEach(function (block) {
			setExtraCharsOpen(block, false);
			if (block.panel) {
				block.panel.innerHTML = '';
			}
		});
	}

	var QWERTY_ROWS = [
		['q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p'],
		['a', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l'],
		['z', 'x', 'c', 'v', 'b', 'n', 'm']
	];

	var KB_DIGITS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'];
	var KB_PUNCT = [',', '.', ':', '?', '!', "'", '"', '”', '„'];

	function bindKbPress(el, fn) {
		el.addEventListener('mousedown', function (e) {
			e.preventDefault();
			e.stopPropagation();
		});
		el.addEventListener('click', function (e) {
			e.stopPropagation();
			fn(e);
		});
	}

	function makeKbKey(label, extraClass, aria) {
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'llm-phrase-game__kb-key' + (extraClass ? ' ' + extraClass : '');
		btn.textContent = label;
		if (aria) {
			btn.setAttribute('aria-label', aria);
		}
		return btn;
	}

	function kbGlyph(btn, shifted) {
		var hi = btn.getAttribute('data-kb-hi');
		var lo = btn.getAttribute('data-kb-lo');
		if (shifted && hi) {
			return hi;
		}
		return lo || btn.textContent || '';
	}

	function syncKeyboardShift(block) {
		var shifted = !!block.shift;
		if (!block.panel) {
			return;
		}
		qsa(block.panel, '[data-kb-lo]').forEach(function (btn) {
			btn.textContent = kbGlyph(btn, shifted);
		});
		qsa(block.panel, '.llm-phrase-game__kb-key--shift').forEach(function (btn) {
			btn.classList.toggle('is-active', shifted);
			btn.setAttribute('aria-pressed', shifted ? 'true' : 'false');
		});
		block.panel.classList.toggle('is-shifted', shifted);
	}

	function paintKeyboard(block) {
		if (!block.panel) {
			return;
		}
		block.panel.innerHTML = '';
		block.panel.classList.remove('is-shifted');
		block.shift = false;
		var set = getExtraCharsSet();

		function addRow(keys, rowClass) {
			var row = document.createElement('div');
			row.className = 'llm-phrase-game__keyboard-row' + (rowClass ? ' ' + rowClass : '');
			keys.forEach(function (item) {
				row.appendChild(item);
			});
			block.panel.appendChild(row);
		}

		function pairKey(lo, hi, extraClass) {
			var btn = makeKbKey(lo, extraClass || '');
			btn.setAttribute('data-kb-lo', lo);
			if (hi) {
				btn.setAttribute('data-kb-hi', hi);
			}
			bindKbPress(btn, function () {
				insertKbChar(destForPhase1Helper(block.input), kbGlyph(btn, !!block.shift));
			});
			return btn;
		}

		addRow(KB_DIGITS.map(function (d, ix) {
			var hi = KB_PUNCT[ix] || '';
			var btn = pairKey(d, hi, 'llm-phrase-game__kb-key--digit');
			if (!hi) {
				btn.classList.add('llm-phrase-game__kb-key--shift-hide');
			}
			return btn;
		}), 'llm-phrase-game__keyboard-row--digits');

		if (set && set.lower && set.lower.length) {
			addRow(set.lower.map(function (item, ix) {
				var hi = (set.upper && set.upper[ix] && set.upper[ix].c) ? set.upper[ix].c : item.c;
				var btn = pairKey(item.c, hi, 'llm-phrase-game__kb-key--extra');
				btn.setAttribute('aria-label', item.c + ' ' + (item.n || ''));
				return btn;
			}), 'llm-phrase-game__keyboard-row--extra');
		}

		QWERTY_ROWS.forEach(function (letters, ix) {
			var keys = [];
			if (ix === 2) {
				var shiftBtn = makeKbKey(i18n.keyboardShift || 'Maiusc', 'llm-phrase-game__kb-key--util llm-phrase-game__kb-key--shift', i18n.keyboardShift || 'Maiusc');
				shiftBtn.setAttribute('aria-pressed', 'false');
				bindKbPress(shiftBtn, function () {
					block.shift = !block.shift;
					syncKeyboardShift(block);
				});
				keys.push(shiftBtn);
			}
			letters.forEach(function (letter) {
				keys.push(pairKey(letter, letter.toUpperCase()));
			});
			if (ix === 2) {
				var bs = makeKbKey('⌫', 'llm-phrase-game__kb-key--util', i18n.keyboardBackspace || 'Cancella');
				bindKbPress(bs, function () {
					deleteKbChar(destForPhase1Helper(block.input));
				});
				keys.push(bs);
			}
			addRow(keys);
		});

		var space = makeKbKey(i18n.keyboardSpace || 'Spazio', 'llm-phrase-game__kb-key--space', i18n.keyboardSpace || 'Spazio');
		bindKbPress(space, function () {
			insertKbChar(destForPhase1Helper(block.input), ' ');
		});
		addRow([space], 'llm-phrase-game__keyboard-row--space');
	}

	function setKeyboardOpen(block, open) {
		if (!block.toggle || !block.panel) {
			return;
		}
		block.toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		block.panel.hidden = !open;
		if (open) {
			block.shift = false;
			paintKeyboard(block);
		}
	}

	function resetKeyboard() {
		keyboardBlocks.forEach(function (block) {
			setKeyboardOpen(block, false);
			if (block.panel) {
				block.panel.innerHTML = '';
			}
		});
	}

	function renderRandomWords(block) {
		if (!block.list) {
			return;
		}
		var phrase = phrases[phraseIx];
		block.list.innerHTML = '';
		buildRandomWords((phrase && phrase.target) || '').forEach(function (word) {
			var chip = document.createElement('button');
			chip.type = 'button';
			chip.className = 'llm-phrase-game__random-word';
			chip.textContent = word;
			chip.addEventListener('mousedown', function (e) {
				e.preventDefault();
			});
			chip.addEventListener('click', function () {
				appendWordToInput(destForPhase1Helper(block.input), word);
			});
			block.list.appendChild(chip);
		});
	}

	/* Accordion: apre/chiude il pannello parole. */
	function setRandomWordsOpen(block, open) {
		if (!block.toggle || !block.list) {
			return;
		}
		block.toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		if (open) {
			if (!block.list.childNodes.length) {
				renderRandomWords(block);
			}
			block.list.hidden = false;
		} else {
			block.list.hidden = true;
		}
	}

	function resetRandomWords() {
		randomWordsBlocks.forEach(function (block) {
			if (block.list) {
				block.list.innerHTML = '';
				block.list.hidden = true;
			}
			if (block.toggle) {
				block.toggle.setAttribute('aria-expanded', 'false');
				block.toggle.hidden = false;
			}
		});
	}

	function resetNotesPanel() {
		notesLoaded = false;
		phraseNotesOpened = false;
		hideShowFieldTransBtn();
		if (notesToggleBtn && notesPanel) {
			setNotesOpen(false);
		}
		if (labelMainEl) {
			labelMainEl.hidden = false;
		}
		resetPronunciationHelpers();
	}

	function setPronTipsOpen(open) {
		if (!pronTipsToggle || !pronTipsPanel) {
			return;
		}
		var isOpen = !!open;
		pronTipsToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		pronTipsPanel.hidden = !isOpen;
		if (isOpen) {
			setStoryNotesOpen(false);
			setNotesOpen(false);
		}
	}

	function isInside(el, node) {
		return !!(el && node && el.contains(node));
	}

	function isInsideHelperBlock(block, node) {
		return isInside(block.toggle, node) || isInside(block.panel, node) || isInside(block.list, node) || isInside(block.wrap, node);
	}

	function closeHelperAccsExcept(keep) {
		randomWordsBlocks.forEach(function (block) {
			if (keep !== 'random') {
				setRandomWordsOpen(block, false);
			}
		});
		extraCharsBlocks.forEach(function (block) {
			if (keep !== 'extra') {
				setExtraCharsOpen(block, false);
			}
		});
		keyboardBlocks.forEach(function (block) {
			if (keep !== 'keyboard') {
				setKeyboardOpen(block, false);
			}
		});
	}

	function closeAccordionsOnOutsideClick(e) {
		var t = e.target;
		if (!isInside(fieldTransWrap, t) && !isInside(showFieldTransBtn, t)) {
			if (fieldHelperIntroKind === 'trans') {
				stopFieldHelperIntro();
				fieldTransIntroPlayed = true;
			}
			setFieldTransOpen(false);
		}
		if (!isInside(fieldPronWrap, t) && !isInside(showFieldPronBtn, t)) {
			if (fieldHelperIntroKind === 'pron') {
				stopFieldHelperIntro();
				fieldPronIntroPlayed = true;
			}
			setFieldPronOpen(false);
		}
		if (!isInside(storyNotesWrap, t)) {
			setStoryNotesOpen(false);
		}
		if (!isInside(notesToggleBtn, t) && (!isInside(notesPanel, t) || isInside(showFieldTransBtn, t))) {
			setNotesOpen(false);
		}
		if (!isInside(pronTipsWrap, t) || isInside(showFieldPronBtn, t)) {
			setPronTipsOpen(false);
		}
		if (!isInside(invertedHintBtn, t) && !isInside(invertedHintPanel, t)) {
			setInvertedHintOpen(false);
		}
		randomWordsBlocks.forEach(function (block) {
			if (!isInsideHelperBlock(block, t)) {
				setRandomWordsOpen(block, false);
			}
		});
		extraCharsBlocks.forEach(function (block) {
			if (!isInsideHelperBlock(block, t)) {
				setExtraCharsOpen(block, false);
			}
		});
		keyboardBlocks.forEach(function (block) {
			if (!isInsideHelperBlock(block, t)) {
				setKeyboardOpen(block, false);
			}
		});
	}

	function restoreFieldAccTitles() {
		if (fieldTransTitleEl && fieldTransTitleText) {
			fieldTransTitleEl.textContent = fieldTransTitleText;
		}
		if (fieldPronTitleEl && fieldPronTitleText) {
			fieldPronTitleEl.textContent = fieldPronTitleText;
		}
	}

	function clearFieldAccPanelFade(panel) {
		if (!panel) {
			return;
		}
		panel.style.opacity = '';
		panel.style.transition = '';
	}

	function stopFieldHelperIntro() {
		fieldHelperIntroRun += 1;
		fieldHelperIntroKind = '';
		fieldAccFading = false;
		restoreFieldAccTitles();
		clearFieldAccPanelFade(fieldTransPanel);
		clearFieldAccPanelFade(fieldPronPanel);
	}

	function setFieldPronOpen(open) {
		if (!fieldPronToggle || !fieldPronPanel) {
			return;
		}
		var isOpen = !!open;
		fieldPronToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		fieldPronPanel.hidden = !isOpen;
		if (isOpen) {
			setFieldTransOpen(false);
			if (!fieldAccFading) {
				clearFieldAccPanelFade(fieldPronPanel);
			}
		} else {
			clearFieldAccPanelFade(fieldPronPanel);
		}
	}

	function fillPronunciationHelpers() {
		var p = phrases[phraseIx] || {};
		var pron = String(p.pronunciation || '').trim();
		var ipa = String(p.ipa || '').trim();
		var approx = String(p.approx || '').trim();
		if (pronTipsText) {
			pronTipsText.innerHTML = '';
			if (pron) {
				splitGrammarBlocks(pron).forEach(function (blockHtml) {
					var el = document.createElement('p');
					el.className = 'llm-phrase-game__pron-tips-block';
					if (blockHtml.indexOf('<') === -1) {
						blockHtml = String(blockHtml).replace(/\n/g, '<br />');
					}
					try {
						el.innerHTML = blockHtml;
					} catch (e) {
						el.textContent = blockHtml;
					}
					pronTipsText.appendChild(el);
				});
			}
		}
		if (fieldPronIpa) {
			fieldPronIpa.textContent = ipa;
		}
		if (fieldPronApprox) {
			fieldPronApprox.textContent = approx;
		}
		if (fieldTransText) {
			fieldTransText.textContent = String(p.target || '').trim();
		}
	}

	function setFieldTransOpen(open) {
		if (!fieldTransToggle || !fieldTransPanel) {
			return;
		}
		var isOpen = !!open;
		fieldTransToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		fieldTransPanel.hidden = !isOpen;
		if (isOpen) {
			setFieldPronOpen(false);
			if (!fieldAccFading) {
				clearFieldAccPanelFade(fieldTransPanel);
			}
		} else {
			clearFieldAccPanelFade(fieldTransPanel);
		}
	}

	function resetPronunciationHelpers() {
		stopFieldHelperIntro();
		fieldTransIntroPlayed = false;
		fieldPronIntroPlayed = false;
		setPronTipsOpen(false);
		setFieldPronOpen(false);
		setFieldTransOpen(false);
		if (fieldPronWrap) {
			fieldPronWrap.hidden = true;
		}
		if (fieldTransWrap) {
			fieldTransWrap.hidden = true;
		}
	}

	function playFieldAccIntro(kind) {
		var isTrans = kind === 'trans';
		var wrap = isTrans ? fieldTransWrap : fieldPronWrap;
		var titleEl = isTrans ? fieldTransTitleEl : fieldPronTitleEl;
		var titleText = isTrans ? fieldTransTitleText : fieldPronTitleText;
		var panel = isTrans ? fieldTransPanel : fieldPronPanel;
		var run = ++fieldHelperIntroRun;
		fieldHelperIntroKind = kind;
		fieldAccFading = false;
		function alive() {
			return fieldHelperIntroRun === run;
		}

		fillPronunciationHelpers();
		restoreFieldAccTitles();
		if (titleEl) {
			titleEl.textContent = '';
		}
		if (wrap) {
			wrap.hidden = false;
		}
		if (isTrans) {
			setFieldTransOpen(false);
		} else {
			setFieldPronOpen(false);
		}
		if (wrap) {
			smoothScrollIntoCenter(wrap);
		}

		function titleTickMs(ch) {
			var jitter = 48 + Math.floor(Math.random() * 36);
			if (ch === ' ' || ch === '\u00a0') {
				return jitter + 70;
			}
			return jitter;
		}

		function pauseMs(ms) {
			return new Promise(function (resolve) {
				window.setTimeout(resolve, ms);
			});
		}

		return typewriterInto(titleEl, titleText, alive, titleTickMs).then(function () {
			if (!alive()) {
				return;
			}
			if (titleEl && titleText) {
				titleEl.textContent = titleText;
			}
			return pauseMs(240);
		}).then(function () {
			if (!alive()) {
				return;
			}
			fieldAccFading = true;
			if (panel) {
				panel.style.opacity = '0';
				panel.style.transition = 'none';
			}
			if (isTrans) {
				setFieldTransOpen(true);
			} else {
				setFieldPronOpen(true);
			}
			if (!panel) {
				fieldAccFading = false;
				return;
			}
			void panel.offsetWidth;
			return fadeElementOpacity(panel, 1, 720);
		}).then(function () {
			if (!alive()) {
				return;
			}
			fieldAccFading = false;
			clearFieldAccPanelFade(panel);
			if (isTrans) {
				fieldTransIntroPlayed = true;
			} else {
				fieldPronIntroPlayed = true;
			}
			if (fieldHelperIntroKind === kind) {
				fieldHelperIntroKind = '';
			}
		});
	}

	function revealFieldPronunciation() {
		if (fieldPronIntroPlayed) {
			fillPronunciationHelpers();
			if (fieldPronWrap) {
				fieldPronWrap.hidden = false;
			}
			restoreFieldAccTitles();
			setFieldPronOpen(true);
			if (fieldPronWrap) {
				smoothScrollIntoCenter(fieldPronWrap);
			}
			return;
		}
		if (fieldHelperIntroKind === 'pron') {
			if (fieldPronWrap) {
				smoothScrollIntoCenter(fieldPronWrap);
			}
			return;
		}
		stopFieldHelperIntro();
		playFieldAccIntro('pron');
	}

	function revealFieldTranslation() {
		if (fieldTransIntroPlayed) {
			fillPronunciationHelpers();
			if (fieldTransWrap) {
				fieldTransWrap.hidden = false;
			}
			restoreFieldAccTitles();
			setFieldTransOpen(true);
			if (fieldTransWrap) {
				smoothScrollIntoCenter(fieldTransWrap);
			}
			return;
		}
		if (fieldHelperIntroKind === 'trans') {
			if (fieldTransWrap) {
				smoothScrollIntoCenter(fieldTransWrap);
			}
			return;
		}
		stopFieldHelperIntro();
		playFieldAccIntro('trans');
	}

	function setInvertedHintOpen(open) {
		if (!invertedHintBtn || !invertedHintPanel) {
			return;
		}
		var isOpen = !!open;
		invertedHintBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		invertedHintPanel.hidden = !isOpen;
		if (invertedHintLabel) {
			invertedHintLabel.textContent = isOpen
				? (i18n.playInvertedHintHide || '')
				: (i18n.playInvertedHint || '');
		}
		if (isOpen) {
			var p = phrases[phraseIx];
			invertedHintPanel.innerHTML = '';
			var line = document.createElement('div');
			line.className = 'llm-phrase-game__inverted-hint-answer';
			try {
				line.innerHTML = (p && p.interface) ? String(p.interface) : '';
			} catch (e) {
				line.textContent = (p && p.interface) ? String(p.interface) : '';
			}
			invertedHintPanel.appendChild(line);
		} else {
			invertedHintPanel.innerHTML = '';
		}
	}

	function resetTargetPeek() {
		clearTargetPeekTimer();
		if (targetShow) {
			targetShow.hidden = false;
			targetShow.style.opacity = '';
			targetShow.style.transition = '';
			targetShow.style.cursor = '';
		}
		if (targetPeekBtn) {
			targetPeekBtn.hidden = true;
		}
	}

	function hideTargetShowPeekButton() {
		clearTargetPeekTimer();
		if (!targetShow || !targetPeekBtn) {
			return Promise.resolve();
		}
		return fadeElementOpacity(targetShow, 0, TARGET_PEEK_FADE_MS).then(function () {
			targetShow.hidden = true;
			targetPeekBtn.hidden = false;
		});
	}

	function revealTargetFromPeekButton() {
		if (!targetShow || !targetPeekBtn) {
			return Promise.resolve();
		}
		targetPeekBtn.hidden = true;
		targetShow.hidden = false;
		targetShow.style.opacity = '0';
		return fadeElementOpacity(targetShow, 1, TARGET_PEEK_FADE_MS);
	}

	function scheduleTargetAutoHide() {
		clearTargetPeekTimer();
		targetPeekTimer = setTimeout(function () {
			targetPeekTimer = null;
			hideTargetShowPeekButton();
		}, TARGET_PEEK_MS);
	}

	function startTargetPeekCycle() {
		clearTargetPeekTimer();
		if (targetPeekBtn) {
			targetPeekBtn.hidden = true;
		}
		if (targetShow) {
			targetShow.hidden = false;
			targetShow.style.opacity = '1';
			targetShow.style.transition = '';
			targetShow.style.cursor = 'pointer';
		}
		scheduleTargetAutoHide();
	}

	function onTargetPeekButtonClick() {
		if (!targetShow || !targetPeekBtn) {
			return;
		}
		if (!targetPeekBtn.hidden) {
			revealTargetFromPeekButton().then(scheduleTargetAutoHide);
		}
	}

	function onTargetPeekAreaClick() {
		if (!targetShow || !targetPeekBtn) {
			return;
		}
		if (targetShow.hidden || targetPeekBtn.hidden === false) {
			return;
		}
		scheduleTargetAutoHide();
	}

	function resetAltNotes() {
		if (altShow) {
			altShow.innerHTML = '';
			altShow.hidden = true;
			altShow.style.opacity = '';
			altShow.style.transition = '';
		}
		if (labelAltEl) {
			labelAltEl.hidden = true;
			labelAltEl.style.opacity = '';
			labelAltEl.style.transition = '';
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
		var notes     = opts.notes     != null ? String(opts.notes)     : '';
		var grammar   = opts.grammar   != null ? String(opts.grammar)   : '';
		var target    = opts.target    != null ? String(opts.target)    : '';
		var alt       = opts.alt       != null ? String(opts.alt)       : '';
		var pronunciation = opts.pronunciation != null ? String(opts.pronunciation) : '';
		var ipa = opts.ipa != null ? String(opts.ipa) : '';
		var approx = opts.approx != null ? String(opts.approx) : '';
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

		/* Note sulla frase: non più negli appunti (il contesto sta in Note della storia). */

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

		/* ── Frase corretta → typewriter (sempre in alto negli appunti) ─ */
		if (target) {
			addStep(function () {
				if (!alive()) { return; }
				if (labelMainEl) { labelMainEl.style.opacity = '1'; }
				return typewriterHtmlInto(targetShow, target, alive, TYPE_TICK_MS).then(function () {
					if (!alive()) { return; }
					startTargetPeekCycle();
				});
			});
		} else if (labelMainEl && !isPlayInverted) {
			labelMainEl.style.opacity = '1';
		}

		/* ── Grammatica → titolo Note: + fade lento per paragrafo ─────── */
		if (grammar) {
			if (!opts.notesOnly) {
			addStep(function () {
				if (!alive() || !labelNotesEl) { return; }
				labelNotesEl.hidden = false;
				labelNotesEl.style.opacity = '0';
				labelNotesEl.style.transition = 'opacity 400ms ease';
				return fadeReveal(labelNotesEl, 400);
			}, FADE_GAP);
			}
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

		/* ── Pronuncia → stessa cadenza della grammatica (non negli appunti a richiesta) ─ */
		if (!opts.notesOnly && pronunciation && pronunciationEl) {
			addStep(function () {
				if (!alive() || !labelPronunciationEl) { return; }
				labelPronunciationEl.hidden = false;
				labelPronunciationEl.style.opacity = '0';
				labelPronunciationEl.style.transition = 'opacity 400ms ease';
				return fadeReveal(labelPronunciationEl, 400);
			}, FADE_GAP);
			var pronBlocks = splitGrammarBlocks(pronunciation);
			pronBlocks.forEach(function (blockHtml) {
				addStep((function (bHtml) {
					return function () {
						if (!alive() || !pronunciationEl) { return; }
						var p = document.createElement('p');
						p.className = 'llm-phrase-game__pronunciation-block';
						if (bHtml.indexOf('<') === -1) {
							bHtml = String(bHtml).replace(/\n/g, '<br />');
						}
						try { p.innerHTML = bHtml; } catch (e) { p.textContent = bHtml; }
						p.style.margin = '0 0 0.45em';
						pronunciationEl.appendChild(p);
						return fadeReveal(p);
					};
				})(blockHtml), FADE_GAP);
			});
		}

		function addLabeledPlainLine(labelEl, boxEl, text, lineClass) {
			if (!text || !boxEl) {
				return;
			}
			addStep(function () {
				if (!alive() || !labelEl) { return; }
				labelEl.hidden = false;
				labelEl.style.opacity = '0';
				labelEl.style.transition = 'opacity 400ms ease';
				return fadeReveal(labelEl, 400);
			}, FADE_GAP);
			addStep(function () {
				if (!alive() || !boxEl) { return; }
				var p = document.createElement('p');
				p.className = lineClass;
				p.textContent = text;
				p.style.margin = '0 0 0.45em';
				boxEl.appendChild(p);
				return fadeReveal(p);
			}, FADE_GAP);
		}

		if (!opts.notesOnly) {
			addLabeledPlainLine(labelIpaEl, ipaEl, ipa, 'llm-phrase-game__ipa-line');
			addLabeledPlainLine(labelApproxEl, approxEl, approx, 'llm-phrase-game__approx-line');
		}

		/* ── Alternativa → sempre visibile, tipografia ridotta ───────── */
		if (alt) {
			addStep(function () {
				if (!alive()) { return; }
				try { altShow.innerHTML = alt; } catch (e) { altShow.textContent = alt; }
				altShow.hidden = false;
				altShow.style.opacity = '0';
				if (labelAltEl) {
					labelAltEl.hidden = false;
					labelAltEl.style.opacity = '0';
					labelAltEl.style.transition = 'opacity 400ms ease';
				}
				return Promise.all([
					labelAltEl ? fadeReveal(labelAltEl, 400) : Promise.resolve(),
					fadeReveal(altShow, FADE_DUR)
				]);
			});
		}

	return chain.then(function () {
		if (!alive()) { return; }
		/* Appunti a richiesta: nessun passaggio alla fase 2. */
		if (opts.notesOnly) {
			return revealShowFieldTransBtn();
		}
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
			phase2RecapPrompt.textContent = '';
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

	function htmlToChipText(html) {
		var s = String(html || '');
		s = s.replace(/<\s*br\s*\/?>/gi, '\n');
		s = s.replace(/<\/\s*p\s*>/gi, '\n');
		s = s.replace(/<[^>]*>/g, '');
		s = s.replace(/\u00a0/g, ' ');
		s = s.replace(/[ \t]+\n/g, '\n').replace(/\n[ \t]+/g, '\n');
		s = s.replace(/\n{3,}/g, '\n\n').trim();
		return s;
	}

	function attachStoryTranslationChip(lineEl, translationText, notesHtml, phraseIndex) {
		if (!lineEl || !translationText) {
			return null;
		}
		var oldChip = qs(lineEl, '.llm-phrase-game__story-chip');
		if (oldChip) {
			oldChip.remove();
		}
		var chip = document.createElement('span');
		chip.className = 'llm-phrase-game__story-chip';
		var trans = document.createElement('span');
		trans.className = 'llm-phrase-game__story-chip-translation';
		trans.innerHTML = String(translationText);
		chip.appendChild(trans);
		var notesText = htmlToChipText(notesHtml || '');
		if (notesText) {
			var notesEl = document.createElement('span');
			notesEl.className = 'llm-phrase-game__story-chip-notes';
			notesEl.textContent = notesText;
			chip.appendChild(notesEl);
		}
		var parsedChipIx = parseInt(phraseIndex, 10);
		if (isNaN(parsedChipIx) && lineEl.dataset && lineEl.dataset.phraseIndex != null) {
			parsedChipIx = parseInt(lineEl.dataset.phraseIndex, 10);
		}
		if (!isNaN(parsedChipIx) && parsedChipIx >= 0) {
			var goBtn = document.createElement('button');
			goBtn.type = 'button';
			goBtn.className = 'llm-phrase-game__story-chip-go button';
			goBtn.textContent = i18n.goToPhrase || 'Vai a questa frase';
			goBtn.addEventListener('click', function (ev) {
				ev.preventDefault();
				ev.stopPropagation();
				setDisplayPhrase(parsedChipIx, goBtn);
			});
			chip.appendChild(goBtn);
		}
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
			var text = plainSpeechText(line.textContent || '');
			var phrase = findPhraseForStoryLine(text);
			if (!phrase) {
				return;
			}
			if (!line.dataset.translation && phrase.interface) {
				line.dataset.translation = phrase.interface;
			}
			if (phrase.notes) {
				line.dataset.storyNotes = phrase.notes;
			}
		});
	}

	function getStoryMediaBlocks() {
		return Array.isArray(cfg.mediaBlocks) ? cfg.mediaBlocks : [];
	}

	function createStoryPhotoEl(item) {
		var fig = document.createElement('figure');
		fig.className = 'llm-phrase-game__story-photo';
		var img = document.createElement('img');
		img.className = 'llm-phrase-game__story-photo-img';
		img.src = item.url;
		img.alt = item.alt || '';
		img.loading = 'lazy';
		img.decoding = 'async';
		fig.appendChild(img);
		return fig;
	}

	function appendStoryPhotosAfter(afterIndex) {
		if (!storyEl) {
			return;
		}
		var wanted = parseInt(afterIndex, 10);
		if (isNaN(wanted)) {
			return;
		}
		getStoryMediaBlocks().forEach(function (item) {
			if (!item || !item.url) {
				return;
			}
			if (parseInt(item.afterPhraseIndex, 10) !== wanted) {
				return;
			}
			storyEl.appendChild(createStoryPhotoEl(item));
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
			/* Prima visita: niente typewriter, solo fade (transition su opacity) */
			introWrap.classList.add('llm-phrase-game__story-intro--visible');
			introText.textContent = String(cfg.storyIntro);
			introComplete = true;
			introReady = Promise.resolve();
			// Rimuove lo stato "intro attiva" in modo da non disturbare eventuali animazioni UI.
			requestAnimationFrame(function () {
				root.classList.remove('llm-phrase-game--story-intro-active');
			});
		}
	}

	appendStoryPhotosAfter(-1);

	if (cfg.completedStoryLines && cfg.completedStoryLines.length) {
		cfg.completedStoryLines.forEach(function (line, lineIx) {
			var block = document.createElement('div');
			block.className = 'llm-phrase-game__story-line';
			var target = typeof line === 'object' ? (line.target || '') : String(line);
			var iface = typeof line === 'object' ? (line.interface || '') : '';
			var phraseIndex = lineIx;
			if (typeof line === 'object' && line.index != null && line.index !== '') {
				var parsedIx = parseInt(line.index, 10);
				if (!isNaN(parsedIx)) {
					phraseIndex = parsedIx;
				}
			}
			block.innerHTML = String(target);
			block.dataset.phraseIndex = String(phraseIndex);
			if (iface) {
				block.dataset.translation = iface;
			}
			storyEl.appendChild(block);
			appendStoryPhotosAfter(phraseIndex);
		});
	}

	function upcomingBlurSample() {
		var first = phrases[0] || {};
		var html = String(first.target || '');
		var tmp = document.createElement('div');
		tmp.innerHTML = html;
		return String(tmp.textContent || tmp.innerText || '').replace(/\s+/g, ' ').trim();
	}

	function hideUpcomingHint() {
		if (!storyEl) {
			return;
		}
		var hint = storyEl.querySelector('.llm-phrase-game__upcoming');
		if (hint && hint.parentNode) {
			hint.parentNode.removeChild(hint);
		}
	}

	function mountUpcomingHint() {
		if (!storyEl || !phrases.length) {
			return;
		}
		if (cfg.gameFinished) {
			return;
		}
		if (cfg.completedStoryLines && cfg.completedStoryLines.length) {
			return;
		}
		if (storyEl.querySelector('.llm-phrase-game__upcoming')) {
			return;
		}
		var wrap = document.createElement('div');
		wrap.className = 'llm-phrase-game__upcoming';
		var blurWrap = document.createElement('div');
		blurWrap.className = 'llm-phrase-game__upcoming-blur-wrap';
		var blur = document.createElement('p');
		blur.className = 'llm-phrase-game__upcoming-blur';
		blur.setAttribute('aria-hidden', 'true');
		blur.textContent = upcomingBlurSample();
		if (!blur.textContent) {
			return;
		}
		blurWrap.appendChild(blur);
		var label = document.createElement('p');
		label.className = 'llm-phrase-game__upcoming-label';
		label.textContent = i18n.upcomingHint || '';
		if (!label.textContent) {
			return;
		}
		wrap.appendChild(blurWrap);
		wrap.appendChild(label);
		storyEl.appendChild(wrap);
	}

	mountUpcomingHint();
	hydrateStoryLineTranslations();

		var restartBtnEl = doneEl ? qs(doneEl, '.llm-phrase-game__restart-btn') : null;

	/* ── Click su riga storia: TTS + etichetta traduzione ─── */
	function findPhraseForStoryLine(targetText) {
		var wanted = normalizeSentence(targetText || '');
		if (!wanted || !cfg.phrases || !cfg.phrases.length) {
			return null;
		}
		var i;
		for (i = 0; i < cfg.phrases.length; i++) {
			var p = cfg.phrases[i] || {};
			if (normalizeSentence(p.target || '') === wanted) {
				return p;
			}
		}
		return null;
	}

	function findInterfaceForStoryLine(targetText) {
		var p = findPhraseForStoryLine(targetText);
		return p ? (p.interface || '') : '';
	}
	storyEl && storyEl.addEventListener('click', function (e) {
		if (e.target && e.target.closest && (
			e.target.closest('.llm-phrase-game__story-photo') ||
			e.target.closest('.llm-phrase-game__story-chip-go')
		)) {
			return;
		}
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
		var phrase = findPhraseForStoryLine(text);
		var translation = line.dataset.translation || (phrase && phrase.interface) || '';
		if (!translation) {
			return;
		}
		line.dataset.translation = translation;
		var notes = line.dataset.storyNotes || (phrase && phrase.notes) || '';
		if (notes) {
			line.dataset.storyNotes = notes;
		}
		/* Mostra/nascondi etichetta traduzione al click. */
		var existingChip = qs(line, '.llm-phrase-game__story-chip');
		if (existingChip) {
			closeOpenStoryChip();
			return;
		}
		closeOpenStoryChip();
		var chipIx = line.dataset.phraseIndex;
		if ((chipIx == null || chipIx === '') && phrase && phrase.index != null) {
			chipIx = phrase.index;
		}
		openStoryChip = attachStoryTranslationChip(line, translation, notes, chipIx);
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
		function setDisplayPhrase(phraseIndex, btn) {
			if (btn) {
				btn.disabled = true;
			}
			var body = new URLSearchParams();
			body.set('action', 'llm_phrase_game_set_display');
			body.set('nonce', nonce);
			body.set('story_id', String(storyId));
			if (phraseIndex === null || phraseIndex === undefined) {
				body.set('clear', '1');
			} else {
				body.set('phrase_index', String(phraseIndex));
			}
			fetch(ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString(),
			})
				.then(function (res) {
					if (!res.ok) {
						throw new Error('display');
					}
					window.location.reload();
				})
				.catch(function () {
					if (btn) {
						btn.disabled = false;
					}
				});
		}

		qsa(root, '.llm-phrase-game__jump-return-btn').forEach(function (returnBtn) {
			if (!cfg.isPhraseJump || !i18n.returnToCheckpointBtn) {
				return;
			}
			var wrap = returnBtn.closest ? returnBtn.closest('.llm-phrase-game__jump-return') : returnBtn.parentNode;
			returnBtn.textContent = i18n.returnToCheckpointBtn;
			if (wrap) {
				wrap.hidden = false;
			}
			returnBtn.addEventListener('click', function (ev) {
				ev.preventDefault();
				ev.stopPropagation();
				setDisplayPhrase(null, returnBtn);
			});
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
						if (window.llmGuestBrowserStore && typeof window.llmGuestBrowserStore.removeStory === 'function') {
							window.llmGuestBrowserStore.removeStory(storyId);
						}
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

	function showMicCountdownIdle(btn) {
		var wrap = btn && btn._llmMicCountdownWrap;
		var bar = btn && btn._llmMicCountdownBar;
		if (!wrap || !bar) {
			return;
		}
		wrap.hidden = false;
		wrap.classList.remove('llm-phrase-game__mic-countdown--active');
		bar.style.animation = 'none';
		bar.style.transform = 'scaleX(1)';
	}

	function startMicCountdownAnimation(btn) {
		var wrap = btn && btn._llmMicCountdownWrap;
		var bar = btn && btn._llmMicCountdownBar;
		if (!wrap || !bar) {
			return;
		}
		wrap.hidden = false;
		bar.style.animation = 'none';
		bar.style.transform = 'scaleX(1)';
		wrap.classList.remove('llm-phrase-game__mic-countdown--active');
		void bar.offsetWidth;
		wrap.classList.add('llm-phrase-game__mic-countdown--active');
		void bar.offsetWidth;
		bar.style.animation =
			'llm-mic-countdown ' +
			(MIC_SESSION_MS / 1000) + 's linear ' +
			(MIC_BAR_FADE_MS / 1000) + 's forwards';
	}

	function hideMicCountdown(btn) {
		if (!btn || !btn._llmMicCountdownWrap || !btn._llmMicCountdownBar) {
			return;
		}
		btn._llmMicCountdownWrap.hidden = true;
		btn._llmMicCountdownWrap.classList.remove('llm-phrase-game__mic-countdown--active');
		btn._llmMicCountdownBar.style.animation = 'none';
		btn._llmMicCountdownBar.style.transform = '';
	}

	function ensureListenTargetWrap(btn) {
		if (!btn || btn._llmListenWrapReady) {
			return;
		}
		var wrap = btn.closest('.llm-phrase-game__listen-target-wrap');
		if (!wrap) {
			var parent = btn.parentNode;
			wrap = document.createElement('div');
			wrap.className = 'llm-phrase-game__listen-target-wrap';
			if (parent) {
				parent.insertBefore(wrap, btn);
				wrap.appendChild(btn);
			}
		}
		btn._llmListenTargetWrap = wrap;

		var countdownWrap = document.createElement('div');
		countdownWrap.className = 'llm-phrase-game__listen-countdown';
		countdownWrap.hidden = true;
		countdownWrap.innerHTML = '<div class="llm-phrase-game__listen-countdown__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="100"></div>';
		wrap.appendChild(countdownWrap);
		btn._llmListenCountdownWrap = countdownWrap;
		btn._llmListenCountdownBar = countdownWrap.querySelector('.llm-phrase-game__listen-countdown__bar');
		btn._llmListenWrapReady = true;
	}

	function getListenBtnForPhase(phaseNum) {
		return phaseNum === 2 ? listenTargetBtnPhase2 : listenTargetBtn;
	}

	function clearListenReplayTimer() {
		if (listenReplayTimer !== null) {
			clearTimeout(listenReplayTimer);
			listenReplayTimer = null;
		}
	}

	function scheduleListenReplayAfterMic(micPhase) {
		if (!listenReplayAfterMic) {
			return;
		}
		clearListenReplayTimer();
		listenReplayTimer = setTimeout(function () {
			listenReplayTimer = null;
			var listenBtn = getListenBtnForPhase(micPhase);
			var p = phrases[phraseIx];
			if (!listenBtn || !p) {
				return;
			}
			speakTargetTranslation(p.target || '', listenBtn);
		}, LISTEN_REPLAY_DELAY_MS);
	}

	function hideListenCountdown(btn) {
		if (!btn || !btn._llmListenCountdownWrap || !btn._llmListenCountdownBar) {
			return;
		}
		btn._llmListenCountdownWrap.hidden = true;
		btn._llmListenCountdownWrap.classList.remove('llm-phrase-game__listen-countdown--active');
		btn._llmListenCountdownBar.style.animation = 'none';
		btn._llmListenCountdownBar.style.transform = '';
	}

	function estimateTtsDurationMs(text, rate) {
		text = String(text || '').trim();
		rate = rate || TTS_SLOW_RATE;
		if (!text) {
			return 2000;
		}
		var words = tokenizeWords(text).length || 1;
		var ms = words * (420 / rate);
		ms = Math.max(ms, 1800);
		return Math.min(Math.round(ms * 1.15), 60000);
	}

	function startListenCountdownAnimation(btn, durationMs) {
		var wrap = btn && btn._llmListenCountdownWrap;
		var bar = btn && btn._llmListenCountdownBar;
		if (!wrap || !bar) {
			return;
		}
		durationMs = Math.max(Number(durationMs) || 0, 1500);
		wrap.hidden = false;
		bar.style.animation = 'none';
		bar.style.transform = 'scaleX(1)';
		wrap.classList.remove('llm-phrase-game__listen-countdown--active');
		void bar.offsetWidth;
		wrap.classList.add('llm-phrase-game__listen-countdown--active');
		void bar.offsetWidth;
		bar.style.animation =
			'llm-mic-countdown ' +
			(durationMs / 1000) + 's linear ' +
			(MIC_BAR_FADE_MS / 1000) + 's forwards';
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
			'llm-phrase-game__mic-status--listening',
			'llm-phrase-game__mic-status--error',
			'llm-phrase-game__mic-status--feedback'
		);
		if (btnEl._llmMicStatusErrorLine) {
			btnEl._llmMicStatusErrorLine.textContent = '';
		}
		if (btnEl._llmMicFeedbackLine) {
			btnEl._llmMicFeedbackLine.textContent = '';
		}
		if (state === 'idle') {
			return;
		}
		if (state === 'pending') {
			el.classList.add('llm-phrase-game__mic-status--pending');
		} else if (state === 'listening') {
			el.classList.add('llm-phrase-game__mic-status--listening');
		}
		requestAnimationFrame(function () {
			el.classList.add('llm-phrase-game__mic-status--visible');
		});
	}

	function hideMicSessionFeedback(btn) {
		if (micFeedbackTimer !== null) {
			clearTimeout(micFeedbackTimer);
			micFeedbackTimer = null;
		}
		if (!btn || !btn._llmMicStatusEl) {
			return;
		}
		btn._llmMicStatusEl.classList.remove(
			'llm-phrase-game__mic-status--visible',
			'llm-phrase-game__mic-status--feedback'
		);
		if (btn._llmMicFeedbackLine) {
			btn._llmMicFeedbackLine.textContent = '';
		}
	}

	function getMicSessionSpokenText() {
		var ta = activeMicTa;
		if (ta) {
			var start = String(speechSessionStartValue || '');
			var full = String(ta.value || '');
			if (start && full.indexOf(start) === 0) {
				return full.slice(start.length).trim();
			}
		}
		return String(speechSegmentTranscript || '').trim();
	}

	function shortenHeardText(text, maxLen) {
		maxLen = maxLen || 48;
		var heard = String(text || '').trim();
		if (!heard) {
			return '';
		}
		return heard.length > maxLen ? heard.slice(0, maxLen - 1) + '…' : heard;
	}

	function formatMicFeedbackDisplay(heardText, msg) {
		if (!msg) {
			return '';
		}
		var short = shortenHeardText(heardText) || '???';
		return '«' + short + '» — ' + msg;
	}

	function getMicFeedbackPool(phaseNum, tier) {
		var fb = cfg.micFeedback || {};
		var phaseKey = phaseNum === 2 ? 'phase2' : 'phase1';
		var phaseFb = fb[phaseKey] || fb;
		return phaseFb[tier] || [];
	}

	function pickMicFeedbackMessage(phaseNum, tier, hits, heardText) {
		var pool = getMicFeedbackPool(phaseNum, tier);
		if (!pool.length) {
			return '';
		}
		var msg = pickRandom(pool);
		if (!msg) {
			return '';
		}
		if (msg.indexOf('%1$d') !== -1) {
			msg = msg.replace('%1$d', String(hits));
		}
		if (msg.indexOf('%s') !== -1) {
			if (!heardText) {
				return pickMicFeedbackMessage(phaseNum, 'silent', hits, heardText);
			}
			var short = heardText.length > 36 ? heardText.slice(0, 33) + '…' : heardText;
			msg = msg.replace('%s', short);
		}
		return formatMicFeedbackDisplay(heardText, msg);
	}

	function resolveMicFeedbackTierPhase1(transcript, recognitionStarted) {
		var heardText = String(transcript || '').trim();
		var heardCount = tokenizeWords(heardText).length;
		if (!recognitionStarted && !heardText) {
			return 'not_started';
		}
		if (!heardText || heardCount === 0) {
			return 'silent';
		}
		return 'heard';
	}

	function resolveMicFeedbackTierPhase2(transcript, recognitionStarted, targetText) {
		var heardText = String(transcript || '').trim();
		var heardCount = tokenizeWords(heardText).length;
		if (!recognitionStarted && !heardText) {
			return 'not_started';
		}
		if (!heardText || heardCount === 0) {
			return 'silent';
		}
		var counts = countReferenceWordHits(heardText, targetText);
		if (counts.hits === 0) {
			return 'unrecognized';
		}
		if (counts.total > 0 && counts.hits >= counts.total - 1) {
			return 'all';
		}
		if (counts.hits === 1) {
			return 'one';
		}
		if (counts.hits === 2) {
			return 'two';
		}
		return 'some';
	}

	function showMicSessionFeedback(btn, transcript, recognitionStarted, phaseNum) {
		return;
		var status = btn && btn._llmMicStatusEl;
		var feedbackLine = btn && btn._llmMicFeedbackLine;
		if (!status || !feedbackLine) {
			return;
		}
		hideMicSessionFeedback(btn);
		var p = phrases[phraseIx];
		var targetText = p && p.target != null ? String(p.target) : '';
		var heardText = String(transcript || '').trim();
		var tier;
		var hits = 0;
		if (phaseNum === 2) {
			tier = resolveMicFeedbackTierPhase2(transcript, recognitionStarted, targetText);
			hits = countReferenceWordHits(transcript, targetText).hits;
		} else {
			tier = resolveMicFeedbackTierPhase1(transcript, recognitionStarted);
		}
		var msg = pickMicFeedbackMessage(phaseNum, tier, hits, heardText);
		if (!msg) {
			return;
		}
		feedbackLine.textContent = msg;
		status.classList.remove(
			'llm-phrase-game__mic-status--pending',
			'llm-phrase-game__mic-status--listening',
			'llm-phrase-game__mic-status--error'
		);
		status.classList.add('llm-phrase-game__mic-status--feedback');
		requestAnimationFrame(function () {
			status.classList.add('llm-phrase-game__mic-status--visible');
		});
		micFeedbackTimer = setTimeout(function () {
			hideMicSessionFeedback(btn);
		}, MIC_FEEDBACK_DISPLAY_MS);
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
			syncWriteTranslatePeekBlur();
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
		syncWriteTranslatePeekBlur();
	}

	function clearMicPendingTimer() {
		if (micPendingTimer !== null) {
			clearTimeout(micPendingTimer);
			micPendingTimer = null;
		}
	}

	function beginMicCountdownPhase() {
		if (!micSessionActive || !activeMicBtn) {
			return;
		}
		startMicCountdownAnimation(activeMicBtn);
		if (micSessionTimer !== null) {
			clearTimeout(micSessionTimer);
		}
		micSessionTimer = setTimeout(function () {
			finishMicSession();
		}, MIC_SESSION_MS);
	}

	function tryEnterMicListeningState() {
		if (!micSessionActive || micState !== 'pending' || !micPendingPhaseDone || !micRecognitionStarted) {
			return;
		}
		micState = 'listening';
		applyMicStateClasses();
	}

	function stopSpeech() {
		peekBlurVoiceHold = false;
		micSessionActive = false;
		clearMicPendingTimer();
		micPendingPhaseDone = false;
		micRecognitionStarted = false;
		if (micSessionTimer !== null) {
			clearTimeout(micSessionTimer);
			micSessionTimer = null;
		}
		if (micRestartTimer !== null) {
			clearTimeout(micRestartTimer);
			micRestartTimer = null;
		}
		micState = 'idle';
		speechSegmentTranscript = '';
		if (speechRec) {
			try {
				speechRec.onend = null;
				speechRec.onresult = null;
				speechRec.onerror = null;
				speechRec.onstart = null;
				speechRec.stop();
			} catch (e) { /* ignore */ }
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

	function withTrailingMicSpace(text) {
		text = String(text || '');
		if (text.length && !/\s$/.test(text)) {
			return text + ' ';
		}
		return text;
	}

	function ensureTrailingMicSpace(ta) {
		if (!ta) {
			return;
		}
		var next = withTrailingMicSpace(ta.value || '');
		if (next === ta.value) {
			return;
		}
		ta.value = next;
		if (typeof ta._llmSyncClearBtn === 'function') {
			ta._llmSyncClearBtn();
		}
		storeInputCaret(ta, next.length, next.length);
		try {
			ta.setSelectionRange(next.length, next.length);
		} catch (e) {
			/* ignore */
		}
	}

	function getVoiceMarkEl() {
		if (!inputVoice) {
			return null;
		}
		if (inputVoice._llmMarkEl) {
			return inputVoice._llmMarkEl;
		}
		var el = inputVoice.parentNode
			? inputVoice.parentNode.querySelector('.llm-phrase-game__voice-mark')
			: null;
		inputVoice._llmMarkEl = el || null;
		return inputVoice._llmMarkEl;
	}

	function showVoiceExtrasMark(html) {
		var el = getVoiceMarkEl();
		if (!el) {
			return;
		}
		el.innerHTML = '<span class="llm-phrase-game__voice-mark-text">' + html + '</span>';
		el.hidden = false;
		inputVoice.classList.add('llm-phrase-game__input--voice-marking');
	}

	function hideVoiceExtrasMark() {
		var el = getVoiceMarkEl();
		if (el) {
			el.innerHTML = '';
			el.hidden = true;
		}
		if (inputVoice) {
			inputVoice.classList.remove('llm-phrase-game__input--voice-marking');
		}
	}

	function setVoiceFieldControllaText(next, html) {
		next = withTrailingMicSpace(normalizeControllaSentence(next));
		inputVoice.value = next;
		if (typeof inputVoice._llmSyncClearBtn === 'function') {
			inputVoice._llmSyncClearBtn();
		}
		storeInputCaret(inputVoice, next.length, next.length);
		try {
			inputVoice.setSelectionRange(next.length, next.length);
		} catch (e) {
			/* ignore */
		}
		if (html) {
			showVoiceExtrasMark(withTrailingMicSpace(html));
		} else {
			hideVoiceExtrasMark();
		}
		syncWriteTranslatePeekBlur();
	}

	function clearControllaStrikeTimer() {
		if (controllaStrikeTimer !== null) {
			clearTimeout(controllaStrikeTimer);
			controllaStrikeTimer = null;
		}
	}

	function controllaSimboliVoiceField() {
		if (!isWriteTranslate || !inputVoice || micSessionActive) {
			return;
		}
		runControllaSimboliRound(0);
	}

	function runControllaSimboliRound(round) {
		if (!isWriteTranslate || !inputVoice || micSessionActive) {
			return;
		}
		clearControllaStrikeTimer();
		hideVoiceExtrasMark();
		var raw = stripControllaStrike(inputVoice.value || '');
		if (!raw.trim()) {
			hideRewindExactButton(inputVoice._llmRewindBtn);
			return;
		}
		var p = phrases[phraseIx] || {};
		var target = stripTagsHtml(p.target || '');
		if (!target) {
			hideRewindExactButton(inputVoice._llmRewindBtn);
			return;
		}
		var next = applyControllaSimboliTwice(raw, target);
		var marked = mapUnknownControllaWords(next, target, 'mark');
		var nextText = withTrailingMicSpace(marked.text);
		if (nextText === inputVoice.value && !marked.hasExtras) {
			revealRewindAfterControlla(inputVoice);
			return;
		}
		setVoiceFieldControllaText(marked.text, marked.hasExtras ? marked.html : '');
		if (!marked.hasExtras) {
			revealRewindAfterControlla(inputVoice);
			return;
		}
		hideRewindExactButton(inputVoice._llmRewindBtn);
		controllaStrikeTimer = setTimeout(function () {
			controllaStrikeTimer = null;
			if (!inputVoice) {
				return;
			}
			var pNow = phrases[phraseIx] || {};
			var targetNow = stripTagsHtml(pNow.target || '');
			var cleaned = mapUnknownControllaWords(inputVoice.value || '', targetNow, 'drop');
			var spaced = normalizeControllaSentence(cleaned.text);
			var rechecked = applyControllaSimboliTwice(spaced, targetNow);
			setVoiceFieldControllaText(rechecked, '');
			if (round < 3) {
				runControllaSimboliRound(round + 1);
				return;
			}
			revealRewindAfterControlla(inputVoice);
		}, 3000);
	}

	function finishMicSession(opts) {
		opts = opts || {};
		var btn = activeMicBtn;
		var ta = activeMicTa;
		var micPhase = ta === input2 ? 2 : 1;
		var sessionText = getMicSessionSpokenText();
		var recognitionStarted = micRecognitionStarted;
		stopSpeech();
		if (isWriteTranslate && ta === inputVoice) {
			controllaSimboliVoiceField();
			try {
				inputVoice.focus({ preventScroll: true });
			} catch (e) {
				inputVoice.focus();
			}
		}
		ensureTrailingMicSpace(ta);
		if (opts.feedback !== false && btn) {
			showMicSessionFeedback(btn, sessionText, recognitionStarted, micPhase);
			scheduleListenReplayAfterMic(micPhase);
		}
	}

	function showMicError(btn, msg) {
		if (!btn || !msg) { return; }
		hideMicSessionFeedback(btn);
		var status = btn._llmMicStatusEl;
		var errorLine = btn._llmMicStatusErrorLine;
		if (!status || !errorLine) { return; }
		errorLine.textContent = msg;
		status.classList.remove(
			'llm-phrase-game__mic-status--pending',
			'llm-phrase-game__mic-status--listening'
		);
		status.classList.add(
			'llm-phrase-game__mic-status--visible',
			'llm-phrase-game__mic-status--error'
		);
		setTimeout(function () {
			errorLine.textContent = '';
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
			clearListenReplayTimer();
			try {
				window.speechSynthesis.cancel();
			} catch (e) {
				/* ignore */
			}
			if (listenTargetBtn) {
				listenTargetBtn.classList.remove('llm-phrase-game__listen-target--playing');
				hideListenCountdown(listenTargetBtn);
			}
			if (listenTargetBtnPhase2) {
				listenTargetBtnPhase2.classList.remove('llm-phrase-game__listen-target--playing');
				hideListenCountdown(listenTargetBtnPhase2);
			}
		}

		function normalizeLangTag(l) {
			return String(l || '')
				.replace(/_/g, '-')
				.toLowerCase();
		}

		function listVoicesForLang(lang) {
			var synth = window.speechSynthesis;
			if (!synth || typeof synth.getVoices !== 'function') {
				return [];
			}
			var voices = synth.getVoices();
			if (!voices || !voices.length) {
				return [];
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
			function score(v) {
				var n = (v.name || '').toLowerCase();
				var i;
				for (i = 0; i < prefs.length; i++) {
					if (n.indexOf(prefs[i]) !== -1) {
						return i;
					}
				}
				return prefs.length;
			}
			candidates = candidates.slice().sort(function (a, b) {
				return score(a) - score(b);
			});
			var seen = {};
			var out = [];
			var i;
			var key;
			for (i = 0; i < candidates.length; i++) {
				key = String(candidates[i].name || '') + '|' + String(candidates[i].lang || '');
				if (seen[key]) {
					continue;
				}
				seen[key] = true;
				out.push(candidates[i]);
			}
			return out;
		}

		function pickVoiceForLang(lang) {
			var list = listVoicesForLang(lang);
			return list.length ? list[0] : null;
		}

		function speakTargetTranslation(text, triggerBtn, opts) {
			if (!window.speechSynthesis) {
				return;
			}
			var btnEl = triggerBtn || listenTargetBtn;
			if (!btnEl) {
				return;
			}
			opts = opts || {};
			ensureListenTargetWrap(btnEl);
			var trimmed = plainSpeechText(text);
			if (!trimmed) {
				return;
			}
			var rate = typeof opts.rate === 'number' ? opts.rate : TTS_SLOW_RATE;
			cancelTts();
			var durationMs = estimateTtsDurationMs(trimmed, rate);
			var ut = new SpeechSynthesisUtterance(trimmed);
			ut.lang = speechLang;
			ut.rate = rate;
			ut.pitch = 1;
			var v = opts.voice || pickVoiceForLang(speechLang);
			if (v) {
				ut.voice = v;
			}
			ut.onend = function () {
				if (btnEl) {
					btnEl.classList.remove('llm-phrase-game__listen-target--playing');
					hideListenCountdown(btnEl);
				}
			};
			ut.onerror = function () {
				if (btnEl) {
					btnEl.classList.remove('llm-phrase-game__listen-target--playing');
					hideListenCountdown(btnEl);
				}
			};
			btnEl.classList.add('llm-phrase-game__listen-target--playing');
			startListenCountdownAnimation(btnEl, durationMs);
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
		clearListenReplayTimer();
		hideMicSessionFeedback(micBtn);

		micSessionActive = true;
		activeMicTa = textarea;
		activeMicBtn = micBtn;
		speechSessionStartValue = textarea.value;
		speechInsertSuffix = '';
		if (isWriteTranslate && textarea === inputVoice) {
			var val = String(textarea.value || '');
			var caretStart;
			var caretEnd;
			if (document.activeElement === textarea && typeof textarea.selectionStart === 'number') {
				caretStart = textarea.selectionStart;
				caretEnd = typeof textarea.selectionEnd === 'number' ? textarea.selectionEnd : caretStart;
			} else if (voiceCaret.start !== null && voiceCaret.start !== undefined) {
				caretStart = voiceCaret.start;
				caretEnd = voiceCaret.end !== null && voiceCaret.end !== undefined ? voiceCaret.end : caretStart;
			} else {
				caretStart = val.length;
				caretEnd = val.length;
			}
			caretStart = Math.max(0, Math.min(caretStart, val.length));
			caretEnd = Math.max(caretStart, Math.min(caretEnd, val.length));
			speechInsertSuffix = val.slice(caretEnd);
			speechBase = val.slice(0, caretStart);
		} else {
			speechBase = textarea.value;
		}
		if (speechBase.length && !/\s$/.test(speechBase)) { speechBase += ' '; }
		speechSegmentTranscript = '';
		lastCommittedSpeechKey = '';
		micState = 'pending';
		micPendingPhaseDone = false;
		micRecognitionStarted = false;
		clearMicPendingTimer();

		micBtn.disabled = true;
		if (micBtn !== mic1 && mic1) { mic1.disabled = true; }
		if (micBtn !== mic2 && mic2) { mic2.disabled = true; }
		showMicCountdownIdle(micBtn);
		applyMicStateClasses();

		micPendingTimer = setTimeout(function () {
			micPendingTimer = null;
			if (!micSessionActive) {
				return;
			}
			micPendingPhaseDone = true;
			beginMicCountdownPhase();
			tryEnterMicListeningState();
		}, MIC_PENDING_MS);

		function startRecognitionEngine() {
			var Rec = window.SpeechRecognition || window.webkitSpeechRecognition;
			if (!Rec || !micSessionActive) {
				return;
			}
			if (speechRec) {
				try {
					speechRec.onend = null;
					speechRec.onresult = null;
					speechRec.onerror = null;
					speechRec.stop();
				} catch (e) { /* ignore */ }
				speechRec = null;
			}
			speechSegmentTranscript = '';
			speechRec = new Rec();
			speechRec.lang = speechLang;
			speechRec.maxAlternatives = 1;
			// Su mobile continuous:true spesso NON resta acceso: ogni parola fa onend
			// e il riavvio immediato ricommette lo stesso risultato in loop.
			speechRec.continuous = !isMobileSpeechEngine();
			speechRec.interimResults = true;

			speechRec.onstart = function () {
				if (!micSessionActive) { return; }
				micRecognitionStarted = true;
				tryEnterMicListeningState();
			};

			speechRec.onresult = function (ev) {
				if (!micSessionActive) { return; }
				var prevSegment = speechSegmentTranscript;
				var next = getEngineTranscriptFromResults(ev.results);
				if (prevSegment && isStutterGrowth(prevSegment, next)) {
					next = prevSegment;
				}
				speechSegmentTranscript = collapseStutterTokens(next);
				micWordsThisPhrase += countNewWords(prevSegment, speechSegmentTranscript);
				writeMicTextarea(textarea);
			};

			speechRec.onerror = function (ev) {
				var code = ev && ev.error;
				if (code === 'not-allowed' || code === 'service-not-allowed') {
					micPermissionGranted = false;
					finishMicSession({ feedback: false });
					showMicError(micBtn, i18n.micDenied || '');
					return;
				}
				// no-speech / aborted / network: lasciare onend gestire l'eventuale riavvio.
			};

			speechRec.onend = function () {
				if (speechRec) {
					speechRec.onend = null;
					speechRec.onresult = null;
					speechRec.onerror = null;
				}
				speechRec = null;
				if (!micSessionActive) {
					return;
				}
				commitMicSpeechToBase(textarea);
				if (micRestartTimer !== null) {
					clearTimeout(micRestartTimer);
				}
				micRestartTimer = setTimeout(function () {
					micRestartTimer = null;
					if (!micSessionActive) {
						return;
					}
					startRecognitionEngine();
				}, isMobileSpeechEngine() ? MIC_RESTART_GAP_MS : 180);
			};

			try {
				speechRec.start();
			} catch (e) {
				finishMicSession({ feedback: false });
			}
		}

		function doStart() {
			if (!micSessionActive) { return; }
			startRecognitionEngine();
		}

		if (!micPermissionGranted && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
			navigator.mediaDevices.getUserMedia({ audio: true })
				.then(function (stream) {
					micPermissionGranted = true;
					stream.getTracks().forEach(function (t) { t.stop(); });
					doStart();
				})
				.catch(function () {
					finishMicSession({ feedback: false });
					showMicError(micBtn, i18n.micDenied || '');
				});
		} else {
			doStart();
		}
	}

	function bindMic(micBtn, textareaOrFn) {
		if (!micBtn || !textareaOrFn) { return; }
		if (!window.SpeechRecognition && !window.webkitSpeechRecognition) {
			micBtn.hidden = true;
			return;
		}

		var origTextEl = micBtn.querySelector('.llm-phrase-game__mic-text');
		micBtn._llmMicOrigText = origTextEl ? origTextEl.textContent : '';

		var statusEl = document.createElement('div');
		statusEl.className = 'llm-phrase-game__mic-status';
		statusEl.setAttribute('aria-live', 'polite');
		statusEl.setAttribute('aria-atomic', 'true');

		var pendingLine = document.createElement('span');
		pendingLine.className = 'llm-phrase-game__mic-status-line llm-phrase-game__mic-status-line--pending';
		pendingLine.textContent = i18n.micPending || '…';

		var listeningLine = document.createElement('span');
		listeningLine.className = 'llm-phrase-game__mic-status-line llm-phrase-game__mic-status-line--listening';
		listeningLine.textContent = i18n.micListening || '…';

		var errorLine = document.createElement('span');
		errorLine.className = 'llm-phrase-game__mic-status-line llm-phrase-game__mic-status-line--error';

		var feedbackLine = document.createElement('span');
		feedbackLine.className = 'llm-phrase-game__mic-status-line llm-phrase-game__mic-status-line--feedback';

		statusEl.appendChild(pendingLine);
		statusEl.appendChild(listeningLine);
		statusEl.appendChild(errorLine);
		statusEl.appendChild(feedbackLine);
		var micRow = micBtn.closest('.llm-phrase-game__mic-row');
		var micCluster = micBtn.closest('.llm-phrase-game__mic-cluster');
		var micHost = micRow || micBtn.parentNode;
		var micBefore = micCluster || micBtn;
		micHost.insertBefore(statusEl, micBefore);
		micBtn._llmMicStatusEl = statusEl;
		micBtn._llmMicStatusErrorLine = errorLine;
		micBtn._llmMicFeedbackLine = feedbackLine;

		var countdownWrap = document.createElement('div');
		countdownWrap.className = 'llm-phrase-game__mic-countdown';
		countdownWrap.hidden = true;
		countdownWrap.innerHTML = '<div class="llm-phrase-game__mic-countdown__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="100"></div>';
		micHost.insertBefore(countdownWrap, micBefore);
		micBtn._llmMicCountdownWrap = countdownWrap;
		micBtn._llmMicCountdownBar = countdownWrap.querySelector('.llm-phrase-game__mic-countdown__bar');

		micBtn.addEventListener('pointerdown', function () {
			if (!isWriteTranslate || micBtn !== mic1 || micSessionActive || micBtn.disabled) {
				return;
			}
			peekBlurVoiceHold = true;
			syncWriteTranslatePeekBlur();
		});
		function releasePeekBlurVoiceHoldIfIdle() {
			if (peekBlurVoiceHold && !micSessionActive) {
				peekBlurVoiceHold = false;
				syncWriteTranslatePeekBlur();
			}
		}
		micBtn.addEventListener('pointerup', function () {
			setTimeout(releasePeekBlurVoiceHoldIfIdle, 0);
		});
		micBtn.addEventListener('pointercancel', releasePeekBlurVoiceHoldIfIdle);
		micBtn.addEventListener('click', function () {
			if (micSessionActive || micBtn.disabled) {
				return;
			}
			var ta = typeof textareaOrFn === 'function' ? textareaOrFn() : textareaOrFn;
			if (!ta) {
				return;
			}
			startMicSession(ta, micBtn);
		});
	}

		bindMic(mic1, function () {
			return isWriteTranslate ? inputVoice : input1;
		});
		bindMic(mic2, input2);

		(function bindMicHelp() {
			var helpWrap = qs(root, '.llm-phrase-game__mic-help');
			var helpBtn = qs(root, '.llm-phrase-game__mic-help-btn');
			var helpBubble = qs(root, '.llm-phrase-game__mic-help-bubble');
			if (!helpWrap || !helpBtn || !helpBubble) {
				return;
			}
			if (mic1 && mic1.hidden) {
				helpWrap.hidden = true;
				return;
			}
			function setHelpOpen(open) {
				helpBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
				helpBubble.hidden = !open;
			}
			helpBtn.addEventListener('mousedown', function (e) {
				e.preventDefault();
			});
			helpBtn.addEventListener('click', function (e) {
				e.stopPropagation();
				setHelpOpen(helpBtn.getAttribute('aria-expanded') !== 'true');
			});
			document.addEventListener('click', function (e) {
				if (helpWrap.contains(e.target)) {
					return;
				}
				setHelpOpen(false);
			});
		})();

		if (listenTargetBtn) {
			ensureListenTargetWrap(listenTargetBtn);
		}
		if (listenTargetBtnPhase2) {
			ensureListenTargetWrap(listenTargetBtnPhase2);
		}

	/* Flag: feedback 0% già mostrato — secondo click bypassa alla fase 2 */
	var feedbackWarnActive = false;

	/* ── Feedback Fase 1 ─────────────────────────────────────────────────── */

	function pctToTier(pct) {
		if (pct >= 100) { return '100'; }
		if (pct > 60)   { return 'gt60lt90'; }
		if (pct > 50)   { return 'gt50'; }
		if (pct > 0)    { return 'gt0'; }
		return '0';
	}

	function pickRandom(arr) {
		if (!arr || !arr.length) { return ''; }
		return arr[Math.floor(Math.random() * arr.length)] || '';
	}

	function getFeedbackTexts(pctOrTier) {
		var feedbackCfg = cfg.feedback || {};
		/* Se è una stringa non numerica è già un tier key (es. 'double_click') */
		var tier = (typeof pctOrTier === 'string' && isNaN(Number(pctOrTier)))
			? pctOrTier
			: pctToTier(Number(pctOrTier));
		var tierData = feedbackCfg[tier] || {};
		return {
			p1: pickRandom(tierData.p1 || []),
			p2: pickRandom(tierData.p2 || []),
		};
	}

	function showPhase1Feedback(pct) {
		var texts   = getFeedbackTexts(pct);
		var full    = [texts.p1, texts.p2].filter(Boolean).join(' ');
		// Percentuale visibile solo in console (debug)
		console.log('[LLM Phase1] %c' + pct + '%', 'font-weight:bold', '| tier:', pctToTier(pct), '| p1:', texts.p1, '| p2:', texts.p2);
		if (!feedbackEl || !full) { return Promise.resolve(); }
		feedbackEl.textContent = '';
		feedbackEl.hidden = false;
		return typewriterInto(feedbackEl, full, function () { return true; });
	}

	function hidePhase1Feedback() {
		if (!feedbackEl) { return; }
		feedbackEl.hidden = true;
		feedbackEl.textContent = '';
		feedbackWarnActive = false;
	}

	function showLoadingNotes() {
		if (!loadingNotesEl) { return Promise.resolve(); }
		loadingNotesEl.hidden = false;
		loadingNotesEl.innerHTML = '';
		var textSpan = document.createElement('span');
		var dotsSpan = document.createElement('span');
		dotsSpan.className = 'llm-phrase-game__loading-dots';
		dotsSpan.innerHTML = '<span>.</span><span>.</span><span>.</span>';
		dotsSpan.style.opacity = '0';
		loadingNotesEl.appendChild(textSpan);
		loadingNotesEl.appendChild(dotsSpan);
		return typewriterInto(textSpan, i18n.loadingNotes || 'Carico gli appunti per questa frase', function () { return true; }).then(function () {
			dotsSpan.style.transition = 'opacity 0.3s ease';
			dotsSpan.style.opacity = '1';
		});
	}

	function hideLoadingNotes() {
		if (!loadingNotesEl) { return; }
		loadingNotesEl.hidden = true;
		loadingNotesEl.innerHTML = '';
	}

	var ACTION_FADE_MS = 250;

	function setActionFadeVisible(el, show) {
		if (!el) {
			return;
		}
		if (show) {
			if (el._llmFadeTimer) {
				clearTimeout(el._llmFadeTimer);
				el._llmFadeTimer = null;
			}
			el.hidden = false;
			requestAnimationFrame(function () {
				el.classList.add('llm-phrase-game__action-fade--visible');
			});
			return;
		}
		el.classList.remove('llm-phrase-game__action-fade--visible');
		if (el._llmFadeTimer) {
			clearTimeout(el._llmFadeTimer);
		}
		el._llmFadeTimer = setTimeout(function () {
			el._llmFadeTimer = null;
			el.hidden = true;
		}, ACTION_FADE_MS);
	}

	function currentPhraseTargetText() {
		var p = phrases[phraseIx] || {};
		return stripTagsHtml(p.target || '');
	}

	function hideRewindExactButton(rewindBtn) {
		if (!rewindBtn) {
			return;
		}
		rewindBtn.hidden = true;
		var arrowsEl = qs(rewindBtn, '.llm-phrase-game__rewind-input-arrows');
		if (arrowsEl) {
			arrowsEl.textContent = '';
		}
	}

	function syncRewindExactButton(textarea, rewindBtn) {
		if (!rewindBtn) {
			return;
		}
		var info = textarea
			? exactPrefixRewind(textarea.value || '', currentPhraseTargetText())
			: null;
		var arrowsEl = qs(rewindBtn, '.llm-phrase-game__rewind-input-arrows');
		if (!info) {
			hideRewindExactButton(rewindBtn);
			return;
		}
		if (arrowsEl) {
			arrowsEl.textContent = rewindArrowsLabel(info.drop);
		}
		rewindBtn.hidden = false;
	}

	function revealRewindAfterControlla(textarea) {
		if (!textarea) {
			return;
		}
		syncRewindExactButton(textarea, textarea._llmRewindBtn);
	}

	function syncClearInputVisibility(textarea, clearBtn) {
		if (!textarea || !clearBtn) {
			return;
		}
		var wrap = clearBtn.closest('.llm-phrase-game__clear-wrap');
		setActionFadeVisible(wrap, !!(textarea.value || '').trim());
		if (textarea === inputVoice) {
			hideRewindExactButton(textarea._llmRewindBtn);
		} else {
			syncRewindExactButton(textarea, textarea._llmRewindBtn);
		}
	}

		function bindClearInput(clearBtn, rewindBtn, textarea, onClear) {
			if (!clearBtn || !textarea) {
				return;
			}
			textarea._llmRewindBtn = rewindBtn || null;
			function sync() {
				syncClearInputVisibility(textarea, clearBtn);
			}
			textarea._llmSyncClearBtn = sync;
			textarea.addEventListener('input', sync);
			sync();
			clearBtn.addEventListener('click', function () {
				stopSpeech();
				textarea.value = '';
				if (textarea === inputVoice) {
					clearControllaStrikeTimer();
					hideVoiceExtrasMark();
				}
				sync();
				if (typeof onClear === 'function') {
					onClear();
				}
				textarea.focus();
			});
			if (rewindBtn) {
				rewindBtn.addEventListener('click', function () {
					var info = exactPrefixRewind(textarea.value || '', currentPhraseTargetText());
					if (!info) {
						return;
					}
					stopSpeech();
					if (textarea === inputVoice) {
						clearControllaStrikeTimer();
						hideVoiceExtrasMark();
					}
					var next = info.text;
					if (textarea === inputVoice && typeof withTrailingMicSpace === 'function') {
						next = withTrailingMicSpace(next);
					}
					textarea.value = next;
					sync();
					if (typeof onClear === 'function') {
						onClear();
					}
					textarea.focus();
					try {
						textarea.setSelectionRange(textarea.value.length, textarea.value.length);
					} catch (e) {
						/* ignore */
					}
				});
			}
		}

	bindClearInput(clear1, null, input1, function () {
		setMessage('');
		hidePhase1Feedback();
		syncWriteTranslatePeekBlur();
	});
		bindClearInput(clear2, rewind2, input2, function () {
			if (messagePhase2El) {
				setMessagePhase2('', '');
			}
		});
		bindClearInput(clearVoice, rewindVoice, inputVoice, function () {
			setMessage('');
			setMessagePhase2('', '');
			syncWriteTranslatePeekBlur();
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

		function bindListenKeepCaret(btn) {
			if (!btn) {
				return;
			}
			btn.addEventListener('mousedown', function (e) {
				e.preventDefault();
			});
			btn.addEventListener('click', function () {
				if (btn.classList.contains('llm-phrase-game__listen-target--playing')) {
					cancelTts();
					return;
				}
				var p = phrases[phraseIx];
				speakTargetTranslation(p ? p.target : '', btn);
			});
		}
		bindListenKeepCaret(listenTargetBtn);
		bindListenKeepCaret(listenTargetBtnPhase2);

		(function bindListenHelp() {
			var helpWrap = qs(root, '.llm-phrase-game__listen-help');
			var helpBtn = qs(root, '.llm-phrase-game__listen-help-btn');
			var helpBubble = qs(root, '.llm-phrase-game__listen-help-bubble');
			if (!helpWrap || !helpBtn || !helpBubble) {
				return;
			}
			if (!window.speechSynthesis) {
				helpWrap.hidden = true;
				return;
			}
			function setListenHelpOpen(open) {
				helpBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
				helpBubble.hidden = !open;
				if (open) {
					syncListenHelpVoices();
				}
			}
			function syncListenHelpVoices() {
				var voices = listVoicesForLang(speechLang);
				var row1 = helpBubble.querySelector('[data-listen-variant="voice-1"]');
				var row2 = helpBubble.querySelector('[data-listen-variant="voice-2"]');
				if (row1) {
					row1.hidden = voices.length < 2;
				}
				if (row2) {
					row2.hidden = voices.length < 3;
				}
			}
			function playListenVariant(kind) {
				var p = phrases[phraseIx];
				var text = p ? p.target : '';
				var voices = listVoicesForLang(speechLang);
				var opts = {};
				if (kind === 'slow') {
					opts.rate = TTS_SLOWER_RATE;
					opts.voice = voices[0] || null;
				} else if (kind === 'voice-1') {
					if (!voices[1]) {
						return;
					}
					opts.voice = voices[1];
				} else if (kind === 'voice-2') {
					if (!voices[2]) {
						return;
					}
					opts.voice = voices[2];
				}
				speakTargetTranslation(text, listenTargetBtn, opts);
			}
			helpBtn.addEventListener('mousedown', function (e) {
				e.preventDefault();
			});
			helpBtn.addEventListener('click', function (e) {
				e.stopPropagation();
				setListenHelpOpen(helpBtn.getAttribute('aria-expanded') !== 'true');
			});
			helpBubble.addEventListener('mousedown', function (e) {
				e.preventDefault();
			});
			helpBubble.addEventListener('click', function (e) {
				e.stopPropagation();
				var playBtn = e.target.closest('.llm-phrase-game__listen-help-play');
				if (!playBtn) {
					return;
				}
				playListenVariant(playBtn.getAttribute('data-listen-variant') || '');
			});
			document.addEventListener('click', function (e) {
				if (helpWrap.contains(e.target)) {
					return;
				}
				setListenHelpOpen(false);
			});
			if (window.speechSynthesis) {
				window.speechSynthesis.addEventListener('voiceschanged', syncListenHelpVoices);
			}
			syncListenHelpVoices();
		})();

		function t(key, a, b) {
			var s = i18n[key] || '';
			if (a !== undefined && b !== undefined) {
				return s
					.replace('%1$d', String(a)).replace('%2$d', String(b))
					.replace('%1$s', String(a)).replace('%2$s', String(b));
			}
			if (a !== undefined) {
				return s.replace('%s', String(a));
			}
			return s;
		}

		function fadeRevealEl(el, dur) {
			var d = dur || 350;
			return new Promise(function (resolve) {
				if (!el) {
					resolve();
					return;
				}
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

		function runPhraseIntroTypewriter(ifaceText, promptText, introRunId) {
			if (!ifaceEl) {
				return Promise.resolve();
			}
			ifaceEl.innerHTML = '';
			setTranslatePromptText('');
			function aliveIntro() {
				return phraseIntroRun === introRunId;
			}
			return typewriterHtmlInto(ifaceEl, ifaceText, aliveIntro, TYPE_TICK_MS);
		}

		function fieldHasWrittenText(el) {
			return !!(el && String(el.value || '').trim());
		}

		function syncWriteTranslatePeekBlur() {
			if (!isWriteTranslate || !input1 || !inputVoice) {
				return;
			}
			var active = document.activeElement;
			var voiceMicOn = peekBlurVoiceHold || (micSessionActive && activeMicTa === inputVoice) ||
				inputVoice.classList.contains('llm-phrase-game__input--listening');
			if (voiceMicOn) {
				peekBlurLockedOn = input1;
			} else if (active === input1 && peekBlurLockedOn === input1) {
				peekBlurLockedOn = inputVoice;
			} else if (active === inputVoice && peekBlurLockedOn === inputVoice) {
				peekBlurLockedOn = input1;
			}
			if (peekBlurLockedOn !== input1 && peekBlurLockedOn !== inputVoice) {
				peekBlurLockedOn = inputVoice;
			}
			var blurFirst = peekBlurLockedOn === input1 && fieldHasWrittenText(input1) && active !== input1;
			var blurVoice = peekBlurLockedOn === inputVoice && fieldHasWrittenText(inputVoice) &&
				active !== inputVoice && !voiceMicOn;
			input1.classList.toggle('llm-phrase-game__input--peek-blur', blurFirst);
			inputVoice.classList.toggle('llm-phrase-game__input--peek-blur', blurVoice);
		}

		function syncStoryNotesForPhrase(p) {
			p = p || {};
			var phraseText = stripTagsHtml(
				isPlayInverted ? (p.target || p.interface || '') : (p.interface || '')
			).trim();
			var notesText = htmlToChipText(p.notes || '');
			if (!notesText && storyNotesPanel) {
				notesText = String(storyNotesPanel.getAttribute('data-story-intro') || '').trim();
			}
			if (storyNotesPhraseEl) {
				storyNotesPhraseEl.textContent = phraseText;
				storyNotesPhraseEl.hidden = !phraseText;
			}
			if (storyNotesTextEl) {
				storyNotesTextEl.textContent = notesText;
				storyNotesTextEl.hidden = !notesText;
			}
		}

		function syncInputPlaceholders() {
			var writeLang = isPlayInverted ? (interfaceLang || targetLang) : targetLang;
			if (input1) {
				input1.placeholder = isWriteTranslate
					? t('writeTranslatePlaceholderWrite', writeLang)
					: t('inputPlaceholderPhase1', writeLang);
			}
			if (input2) {
				input2.placeholder = t('inputPlaceholderPhase2', writeLang);
			}
			if (inputVoice) {
				inputVoice.placeholder = t('writeTranslatePlaceholderSpeak', writeLang);
			}
		}

		function setMessage(text, isError) {
			if (!messageEl) {
				return;
			}
			messageEl.textContent = text || '';
			messageEl.classList.toggle('llm-phrase-game__message--error', !!isError);
		}

		/** Messaggi di esito frase (fase 2 o fase unica): variant 'error' | 'success' | 'pending' | ''. */
		function setMessagePhase2(text, variant) {
			if (!completionMsgEl) {
				return;
			}
			cancelPhase2MessageStream();
			completionMsgEl.textContent = text || '';
			completionMsgEl.classList.toggle('llm-phrase-game__message-phase2--error', variant === 'error');
			completionMsgEl.classList.toggle('llm-phrase-game__message-phase2--success', variant === 'success');
			completionMsgEl.classList.toggle('llm-phrase-game__message-phase2--pending', variant === 'pending');
		}

		function setMessagePhase2Typewriter(text, variant) {
			if (!completionMsgEl) {
				return Promise.resolve();
			}
			cancelPhase2MessageStream();
			var run = phase2MessageRun;
			completionMsgEl.classList.toggle('llm-phrase-game__message-phase2--error', variant === 'error');
			completionMsgEl.classList.toggle('llm-phrase-game__message-phase2--success', variant === 'success');
			completionMsgEl.classList.toggle('llm-phrase-game__message-phase2--pending', false);
			return typewriterInto(completionMsgEl, text || '', function () {
				return phase2MessageRun === run;
			});
		}

		function appendMessagePhase2Typewriter(text, asHtml) {
			if (!completionMsgEl) {
				return Promise.resolve();
			}
			phase2MessageRun++;
			var run = phase2MessageRun;
			var line = document.createElement('p');
			line.className = 'llm-phrase-game__message-phase2-line';
			completionMsgEl.appendChild(line);
			var alive = function () {
				return phase2MessageRun === run;
			};
			return asHtml
				? typewriterHtmlInto(line, text || '', alive, TYPE_TICK_MS)
				: typewriterInto(line, text || '', alive);
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
		resetPhraseNotes();
		hideAdminEdits();
		if (bravoEl) {
			bravoEl.textContent = '';
		}
		if (grammarEl) {
			grammarEl.innerHTML = '';
		}
		if (pronunciationEl) {
			pronunciationEl.innerHTML = '';
		}
		if (ipaEl) {
			ipaEl.innerHTML = '';
		}
		if (approxEl) {
			approxEl.innerHTML = '';
		}
		if (targetShow) {
			targetShow.innerHTML = '';
		}
		resetTargetPeek();
		resetAltNotes();
		if (labelMainEl) {
			labelMainEl.style.opacity = '';
			labelMainEl.style.transition = '';
		}
		if (labelNotesEl) {
			labelNotesEl.hidden = true;
			labelNotesEl.style.opacity = '';
			labelNotesEl.style.transition = '';
		}
		if (labelPronunciationEl) {
			labelPronunciationEl.hidden = true;
			labelPronunciationEl.style.opacity = '';
			labelPronunciationEl.style.transition = '';
		}
		if (labelIpaEl) {
			labelIpaEl.hidden = true;
			labelIpaEl.style.opacity = '';
			labelIpaEl.style.transition = '';
		}
		if (labelApproxEl) {
			labelApproxEl.hidden = true;
			labelApproxEl.style.opacity = '';
			labelApproxEl.style.transition = '';
		}
		if (labelAltEl) {
			labelAltEl.hidden = true;
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
		hidePhase1Feedback();
		hideLoadingNotes();
		resetNotesPanel();
		resetRandomWords();
		resetExtraChars();
		resetKeyboard();
		setInvertedHintOpen(false);
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
			syncInputPlaceholders();
			var p = phrases[phraseIx];
			syncStoryNotesForPhrase(p);
			fillPronunciationHelpers();
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
				if (inputVoice) { inputVoice.value = ''; }
				if (input1 && input1._llmSyncClearBtn) { input1._llmSyncClearBtn(); }
				if (input2 && input2._llmSyncClearBtn) { input2._llmSyncClearBtn(); }
				if (inputVoice && inputVoice._llmSyncClearBtn) { inputVoice._llmSyncClearBtn(); }
				ifaceEl.innerHTML = String(p.interface || '');
				setTranslatePromptText('');
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
					notes: cfg.resumeAnalysis.notes || '',
					grammar: cfg.resumeAnalysis.grammar || '',
					target: cfg.resumeAnalysis.target || '',
					alt: cfg.resumeAnalysis.alt || '',
					pronunciation: cfg.resumeAnalysis.pronunciation || '',
					ipa: cfg.resumeAnalysis.ipa || '',
					approx: cfg.resumeAnalysis.approx || '',
				});
				return;
			}

			resetAnalysis();
			input1.value = '';
			input2.value = '';
			if (inputVoice) { inputVoice.value = ''; }
			writeTargetInput = input1;
			peekBlurLockedOn = inputVoice || input1;
			voiceCaret.start = null;
			voiceCaret.end = null;
			clearControllaStrikeTimer();
			hideVoiceExtrasMark();
			if (input1) { input1._llmCaret = null; }
			if (input2) { input2._llmCaret = null; }
			if (inputVoice) { inputVoice._llmCaret = null; }
			if (input1 && input1._llmSyncClearBtn) { input1._llmSyncClearBtn(); }
			if (input2 && input2._llmSyncClearBtn) { input2._llmSyncClearBtn(); }
			if (inputVoice && inputVoice._llmSyncClearBtn) { inputVoice._llmSyncClearBtn(); }
			syncWriteTranslatePeekBlur();
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
			if (inputVoice) {
				inputVoice.readOnly = true;
			}
			if (promptRewrite) {
				setRewritePromptText('');
			}
			renderProgress();
			var promptText = isPlayInverted
				? t('playInvertedPrompt', interfaceLang || targetLang)
				: t(introPromptKey, targetLang);
			var promptPhrase = isPlayInverted ? (p.target || '') : (p.interface || '');
			runPhraseIntroTypewriter(
				promptPhrase,
				promptText,
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
				if (inputVoice) {
					inputVoice.readOnly = false;
				}
				syncWriteTranslatePeekBlur();
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
		body.set('mode', learningMode);
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
					if (json.success && json.data) {
						var d = json.data;
						var phaseNum = parseInt(d.phase, 10);
						if (1 === phaseNum) {
							persistGuestStoryProgress({
								phraseIndex: phrases[phraseIx] ? phrases[phraseIx].index : phraseIx,
								step: 2,
								phrasesDone: phraseIx,
								phrasesTotal: phrases.length,
								points: phraseIx,
								finished: false
							});
						} else if (2 === phaseNum) {
							var done = parseInt(d.phrases_done, 10);
							if (isNaN(done)) {
								done = phraseIx + 1;
							}
							var nextIx = phrases.length;
							if (d.has_more && d.next_index !== null && d.next_index !== undefined) {
								nextIx = parseInt(d.next_index, 10);
								if (isNaN(nextIx)) {
									nextIx = phrases.length;
								}
							}
							var totalBar = parseInt(d.phrases_total, 10);
							if (isNaN(totalBar)) {
								totalBar = phrases.length;
							}
							persistGuestStoryProgress({
								phraseIndex: nextIx,
								step: 1,
								phrasesDone: done,
								phrasesTotal: totalBar,
								points: done,
								finished: !d.has_more
							});
						}
					}
					cb(json);
				})
				.catch(function () {
					cb({ success: false, data: { message: i18n.ajaxError || '' } });
				});
		}

	input1.addEventListener('input', function () {
		setMessage('');
		hidePhase1Feedback();
		syncWriteTranslatePeekBlur();
	});
	if (inputVoice) {
		inputVoice.addEventListener('input', function () {
			setMessage('');
			setMessagePhase2('', '');
			syncWriteTranslatePeekBlur();
		});
		function saveVoiceCaret() {
			if (!inputVoice || typeof inputVoice.selectionStart !== 'number') {
				return;
			}
			voiceCaret.start = inputVoice.selectionStart;
			voiceCaret.end = typeof inputVoice.selectionEnd === 'number' ? inputVoice.selectionEnd : voiceCaret.start;
		}
		inputVoice.addEventListener('pointerdown', function () {
			rememberWriteTarget(inputVoice);
		});
		inputVoice.addEventListener('focus', function () {
			rememberWriteTarget(inputVoice);
			saveVoiceCaret();
			syncWriteTranslatePeekBlur();
		});
		inputVoice.addEventListener('blur', function () {
			saveVoiceCaret();
			syncWriteTranslatePeekBlur();
		});
		inputVoice.addEventListener('keyup', saveVoiceCaret);
		inputVoice.addEventListener('mouseup', saveVoiceCaret);
		document.addEventListener('selectionchange', function () {
			if (document.activeElement === inputVoice) {
				saveVoiceCaret();
			}
		});
		inputVoice.addEventListener('click', function () {
			controllaSimboliVoiceField();
		});
	}
	if (input1) {
		function saveWriteCaret() {
			if (!input1 || typeof input1.selectionStart !== 'number') {
				return;
			}
			storeInputCaret(
				input1,
				input1.selectionStart,
				typeof input1.selectionEnd === 'number' ? input1.selectionEnd : input1.selectionStart
			);
		}
		input1.addEventListener('pointerdown', function () {
			rememberWriteTarget(input1);
		});
		input1.addEventListener('focus', function () {
			rememberWriteTarget(input1);
			saveWriteCaret();
			syncWriteTranslatePeekBlur();
		});
		input1.addEventListener('blur', function () {
			saveWriteCaret();
			syncWriteTranslatePeekBlur();
		});
		input1.addEventListener('keyup', saveWriteCaret);
		input1.addEventListener('mouseup', saveWriteCaret);
		document.addEventListener('selectionchange', function () {
			if (document.activeElement === input1) {
				saveWriteCaret();
			}
		});
	}

	function bindVoiceFieldNoLetters(el) {
		if (!el) {
			return;
		}
		function hasLetter(str) {
			return !!str && /\p{L}/u.test(String(str));
		}
		el.addEventListener('keydown', function (ev) {
			if (ev.ctrlKey || ev.metaKey || ev.altKey) {
				return;
			}
			if (ev.key && ev.key.length === 1 && hasLetter(ev.key)) {
				ev.preventDefault();
			}
		});
		el.addEventListener('beforeinput', function (ev) {
			if (ev.inputType && ev.inputType.indexOf('insertFromPaste') === 0) {
				return;
			}
			if (ev.inputType === 'insertFromDrop' || ev.inputType === 'insertReplacementText') {
				return;
			}
			if (ev.inputType === 'insertText' && hasLetter(ev.data)) {
				ev.preventDefault();
			}
		});
	}
	bindVoiceFieldNoLetters(inputVoice);

	function bindTextFieldEnter(inputEl) {
		if (!inputEl) {
			return;
		}
		inputEl.addEventListener('keydown', function (ev) {
			if (ev.key === 'Enter') {
				ev.preventDefault();
			}
		});
		inputEl.addEventListener('input', function () {
			if (inputEl.value && /[\r\n]/.test(inputEl.value)) {
				inputEl.value = inputEl.value.replace(/[\r\n]+/g, ' ');
			}
		});
	}
	bindTextFieldEnter(input1);
	bindTextFieldEnter(input2);
	bindTextFieldEnter(inputVoice);

	if (targetPeekBtn) {
		targetPeekBtn.addEventListener('click', onTargetPeekButtonClick);
	}

	if (targetShow) {
		targetShow.addEventListener('click', onTargetPeekAreaClick);
	}

	if (notesToggleBtn) {
		notesToggleBtn.addEventListener('click', function () {
			phraseNotesOpened = true;
			setNotesOpen(notesToggleBtn.getAttribute('aria-expanded') !== 'true');
		});
	}
	if (storyNotesToggle && storyNotesPanel) {
		storyNotesToggle.addEventListener('click', function () {
			setStoryNotesOpen(storyNotesToggle.getAttribute('aria-expanded') !== 'true');
		});
	}
	if (pronTipsToggle && pronTipsPanel) {
		pronTipsToggle.addEventListener('click', function () {
			setPronTipsOpen(pronTipsToggle.getAttribute('aria-expanded') !== 'true');
		});
	}
	if (fieldPronToggle && fieldPronPanel) {
		fieldPronToggle.addEventListener('click', function () {
			if (fieldHelperIntroKind === 'pron') {
				stopFieldHelperIntro();
				fieldPronIntroPlayed = true;
			}
			setFieldPronOpen(fieldPronToggle.getAttribute('aria-expanded') !== 'true');
		});
	}
	if (showFieldPronBtn) {
		showFieldPronBtn.addEventListener('click', function () {
			revealFieldPronunciation();
		});
	}
	if (fieldTransToggle && fieldTransPanel) {
		fieldTransToggle.addEventListener('click', function () {
			if (fieldHelperIntroKind === 'trans') {
				stopFieldHelperIntro();
				fieldTransIntroPlayed = true;
			}
			setFieldTransOpen(fieldTransToggle.getAttribute('aria-expanded') !== 'true');
		});
	}
	document.addEventListener('click', closeAccordionsOnOutsideClick);
	[input1, input2, inputVoice].forEach(function (field) {
		if (!field) {
			return;
		}
		field.addEventListener('focus', function () {
			closeHelperAccsExcept(null);
		});
	});

	if (showFieldTransBtn) {
		showFieldTransBtn.addEventListener('click', function () {
			revealFieldTranslation();
		});
	}

	btn1.addEventListener('click', function () {
		stopSpeech();
		cancelTts();

		if (isReadGoFast) {
			handleReadGoFastSubmit();
			return;
		}
		if (isPlayInverted) {
			handlePlayInvertedSubmit();
			return;
		}

		if (isResolveGo) {
			handleResolveGoSubmit();
			return;
		}
		if (isWriteTranslate) {
			handleWriteTranslateSubmit();
			return;
		}

		var txt = (input1.value || '').trim();

		/* ── Caso 1: campo vuoto ─────────────────────────────────────── */
		if (!txt) {
			hidePhase1Feedback();
			feedbackWarnActive = false;
			btn1.disabled = true;
			var emptyTexts = getFeedbackTexts('empty_input');
			var emptyFull  = [emptyTexts.p1, emptyTexts.p2].filter(Boolean).join(' ');
			console.log('[LLM Phase1] campo vuoto | p1:', emptyTexts.p1, '| p2:', emptyTexts.p2);
			if (feedbackEl && emptyFull) {
				feedbackEl.textContent = '';
				feedbackEl.hidden = false;
				typewriterInto(feedbackEl, emptyFull, function () { return true; }).then(function () {
					btn1.disabled = false;
				});
			} else {
				setMessage(i18n.empty || '', true);
				btn1.disabled = false;
			}
			return;
		}

		var p         = phrases[phraseIx];
		var targetRef = p && p.target != null ? String(p.target) : '';
		var ratio     = referenceWordsFoundRatio(txt, targetRef);
		var pct       = Math.round(ratio * 100);

		console.log('[LLM Phase1] %c' + pct + '%', 'font-weight:bold', '| tier:', pctToTier(pct) + ' | feedbackWarnActive:', feedbackWarnActive);

		/* ── Caso 2: 0% e primo click ────────────────────────────────── */
		if (pct === 0 && !feedbackWarnActive) {
			hidePhase1Feedback();
			btn1.disabled = true;
			showPhase1Feedback(pct).then(function () {
				btn1.disabled = false;
				feedbackWarnActive = true;
			});
			return;
		}

	/* ── Caso 3: 0% e secondo click / Caso 4: >0% ───────────────────── */
	/* bypassPhase1 = true sempre: la soglia è ora gestita lato JS con il
	   feedback typewriter — il server non deve più bloccare o mostrare errori. */
	var bypassPhase1 = true;

	setMessage('');
	setMessagePhase2('', '');
	btn1.disabled = true;
	if (btn2) { btn2.disabled = true; }
	if (input2) { input2.readOnly = true; input2.value = ''; }
	if (input2 && input2._llmSyncClearBtn) { input2._llmSyncClearBtn(); }

	/* Avvia controllo server in parallelo (solo per registrare avanzamento) */
	postCheck(1, txt, false, function (json) {
		btn1.disabled = false;
		/* Mostra solo errori di rete reali, non validazioni fase 1 */
		if (!json) {
			setMessage(i18n.ajaxError || '', true);
		}
	}, bypassPhase1);

		prepareAnalysisStreamLayout();
		setComposePhaseVisible(2, false);
		showPhase(2);

		/* Scegli tier feedback:
		   - secondo click a 0% (feedbackWarnActive era true) → double_click
		   - tutti gli altri casi (>0%) → tier normale */
		var isDoubleClick = (pct === 0 && feedbackWarnActive);
		var feedbackPromise;
		if (isDoubleClick) {
			var dcTexts = getFeedbackTexts('double_click');
			var dcFull  = [dcTexts.p1, dcTexts.p2].filter(Boolean).join(' ');
			console.log('[LLM Phase1] double_click | p1:', dcTexts.p1, '| p2:', dcTexts.p2);
			hidePhase1Feedback();
			if (feedbackEl && dcFull) {
				feedbackEl.textContent = '';
				feedbackEl.hidden = false;
				feedbackPromise = typewriterInto(feedbackEl, dcFull, function () { return true; });
			} else {
				feedbackPromise = Promise.resolve();
			}
		} else {
			feedbackPromise = showPhase1Feedback(pct);
		}

		feedbackWarnActive = false;

		feedbackPromise.then(function () {
			return showLoadingNotes();
		}).then(function () {
			return sleepMs(3000);
		}).then(function () {
			analysisEl.hidden = false;
			requestAnimationFrame(function () {
				requestAnimationFrame(function () {
					smoothScrollIntoCenter(analysisEl).then(function () {
						runAnalysisTypestream({
							yourText: txt,
							notes: (p && p.notes) || '',
							grammar: (p && p.grammar) || '',
							target: targetRef,
							alt: (p && p.alt) || '',
							pronunciation: (p && p.pronunciation) || '',
							ipa: (p && p.ipa) || '',
							approx: (p && p.approx) || '',
						});
					});
				});
			});
		});
	});

		/**
		 * Sequenza di completamento frase condivisa da tutte le modalità:
		 * messaggi di esito → riga di storia → frase successiva.
		 *
		 * @param {string}   txt      Testo validato dell'utente.
		 * @param {boolean}  micUsed  Se assegnare il punto microfono.
		 * @param {{scrollTarget?: Element, showMicMessage?: boolean, messages?: string[], holdMs?: number, onFail?: function(string):void}} opts
		 */
		function runPhraseCompletionFlow(txt, micUsed, opts) {
			opts = opts || {};
			var showMicMessage = opts.showMicMessage !== false;
			var onFail = typeof opts.onFail === 'function' ? opts.onFail : function () {};
			var scrollTarget = opts.scrollTarget || completionMsgEl || phase2;
			/* Pausa di lettura dei messaggi prima di scrivere la riga di storia. */
			var holdMs = typeof opts.holdMs === 'number' ? opts.holdMs : 3000;

			/* Modalità che non validano il testo usano messaggi propri. */
			var messages = Array.isArray(opts.messages)
				? opts.messages
				: [
					i18n.bravoCorrect || '',
					i18n.phraseCompletePoints || '',
					showMicMessage
						? (micUsed ? (i18n.micUsedPoint || '') : (i18n.micUsedNoPoint || ''))
						: '',
					i18n.storyContinue || ''
				];

			/* Avvia AJAX subito in parallelo con i messaggi. */
			var ajaxPromise = new Promise(function (resolve) {
				postCheck(2, txt, micUsed, function (json) {
					resolve(json);
				});
			});

			setMessagePhase2('', '');
			if (completionMsgEl) {
				completionMsgEl.innerHTML = '';
				completionMsgEl.classList.add('llm-phrase-game__message-phase2--success');
			}

			/* Ogni voce è una stringa, oppure {text, html} per righe che contengono markup. */
			var typed = messages.reduce(function (chain, msg, ix) {
				var isObj = !!msg && 'object' === typeof msg;
				var text = isObj ? (msg.text || '') : (msg || '');
				var asHtml = isObj && !!msg.html;
				return chain.then(function () {
					return ix > 0 ? sleepMs(300) : null;
				}).then(function () {
					return text ? appendMessagePhase2Typewriter(text, asHtml) : null;
				});
			}, smoothScrollIntoCenter(scrollTarget));

			return typed.then(function () {
				return Promise.all([ajaxPromise, sleepMs(holdMs)]);
			})
				.then(function (pair) {
					var json = pair && pair[0];
					if (!json || !json.success) {
						var msg =
							(json && json.data && json.data.message) || i18n.phase2Fail || '';
						onFail(msg);
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
					function advanceAfterPhrase() {
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
						advanceAfterPhrase();
						return;
					}
					smoothScrollStoryToCenter().then(function () {
						hideUpcomingHint();
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
								appendStoryPhotosAfter(phraseIx);
								advanceAfterPhrase();
							}
						});
					});
				});
		}

		function lockWriteFields(locked) {
			if (input1) {
				input1.readOnly = !!locked;
			}
			if (inputVoice) {
				inputVoice.readOnly = !!locked;
			}
		}

		/** Come Risolvi e vai, ma entrambi i campi devono essere la stessa traduzione corretta. */
		function handleWriteTranslateSubmit() {
			syncStrictAccentsFromDom();

			var txtWrite = (input1 && input1.value ? input1.value : '').trim();
			var txtVoice = (inputVoice && inputVoice.value ? inputVoice.value : '').trim();
			if (!txtWrite || !txtVoice) {
				setMessagePhase2Typewriter(i18n.empty || '', 'error');
				return;
			}
			var p = phrases[phraseIx];
			var targetRef = p && p.target != null ? String(p.target) : '';
			var writeOk = phase2PassesLocal(txtWrite, targetRef, PHASE2_SIM, PHASE2_WR);
			var voiceOk = phase2PassesLocal(txtVoice, targetRef, PHASE2_SIM, PHASE2_WR);
			if (!writeOk || !voiceOk) {
				var failMsg = i18n.writeTranslateFail || i18n.resolveGoFail || i18n.phase2Fail || '';
				if (!writeOk && voiceOk) {
					failMsg = i18n.writeTranslateFailWrite || failMsg;
				} else if (writeOk && !voiceOk) {
					failMsg = i18n.writeTranslateFailSpeak || failMsg;
				}
				setMessagePhase2Typewriter(failMsg, 'error');
				return;
			}
			if (!phraseNotesOpened) {
				setMessagePhase2Typewriter(i18n.writeTranslatePeekNotes || '', 'success');
				return;
			}

			setMessagePhase2('', '');
			clearDbStatus();
			btn1.disabled = true;
			lockWriteFields(true);
			setNotesOpen(false);

			postCheck(2, txtWrite, false, function (json) {
				runFeedbackAfterSave(json, [
					i18n.bravoCorrect || '',
					i18n.phraseCompletePoints || '',
					i18n.storyContinue || ''
				], {
					scrollTarget: completionMsgEl || phase1,
					onFail: function () {
						btn1.disabled = false;
						lockWriteFields(false);
					}
				});
			});
		}

		/** Modalità "Risolvi e vai": validazione locale, poi AJAX-first, status DB, poi feedback. */
		function handleResolveGoSubmit() {
			syncStrictAccentsFromDom();

			var txt = (input1.value || '').trim();
			if (!txt) {
				setMessagePhase2Typewriter(i18n.empty || '', 'error');
				return;
			}
			var p = phrases[phraseIx];
			var targetRef = p && p.target != null ? String(p.target) : '';
			if (!phase2PassesLocal(txt, targetRef, PHASE2_SIM, PHASE2_WR)) {
				setMessagePhase2Typewriter(i18n.resolveGoFail || i18n.phase2Fail || '', 'error');
				return;
			}

			setMessagePhase2('', '');
			clearDbStatus();
			btn1.disabled = true;
			input1.readOnly = true;
			setNotesOpen(false);

			postCheck(2, txt, false, function (json) {
				runFeedbackAfterSave(json, [
					i18n.bravoCorrect || '',
					i18n.phraseCompletePoints || '',
					i18n.storyContinue || ''
				], {
					scrollTarget: completionMsgEl || phase1,
					onFail: function () {
						btn1.disabled = false;
						input1.readOnly = false;
					}
				});
			});
		}

		/** Mostra il messaggio di stato DB con fade-in per `showMs` ms, poi fade-out. Restituisce una Promise. */
		function showDbStatus(text, isError, showMs) {
			if (!dbStatusEl) {
				return sleepMs(showMs || 1200);
			}
			dbStatusEl.textContent = text || '';
			dbStatusEl.classList.toggle('llm-phrase-game__db-status--ok', !isError);
			dbStatusEl.classList.toggle('llm-phrase-game__db-status--error', !!isError);
			/* force reflow so transition fires */
			void dbStatusEl.offsetWidth;
			dbStatusEl.classList.add('llm-phrase-game__db-status--visible');
			return sleepMs(showMs || 1200).then(function () {
				dbStatusEl.classList.remove('llm-phrase-game__db-status--visible');
				/* aspetta la transizione di uscita (350 ms) */
				return sleepMs(400);
			});
		}

		function clearDbStatus() {
			if (!dbStatusEl) { return; }
			dbStatusEl.classList.remove('llm-phrase-game__db-status--visible');
			dbStatusEl.classList.remove('llm-phrase-game__db-status--ok');
			dbStatusEl.classList.remove('llm-phrase-game__db-status--error');
			dbStatusEl.textContent = '';
		}

		/**
		 * Helper condiviso per i flussi AJAX-first (Risolvi e vai, Read and go fast).
		 * Mostra il messaggio DB in fade, poi la sequenza di messaggi, poi gestisce story line e avanzamento.
		 *
		 * @param {object} json      Risposta postCheck
		 * @param {Array}  messages  Array di stringhe o {text, html}
		 * @param {object} opts      { holdMs, scrollTarget, onFail }
		 */
		function runFeedbackAfterSave(json, messages, opts) {
			opts = opts || {};
			var onFail = typeof opts.onFail === 'function' ? opts.onFail : function () {};
			var scrollTarget = opts.scrollTarget || completionMsgEl || phase2;
			var holdMs = typeof opts.holdMs === 'number' ? opts.holdMs : 3000;

			if (!json || !json.success) {
				var errMsg = (json && json.data && json.data.message)
					|| i18n.readGoFastSaveError
					|| i18n.ajaxError
					|| '';
				showDbStatus(errMsg, true, 2500).then(function () {
					onFail(errMsg);
				});
				return;
			}

			var d = json.data || {};

			/* Aggiorna progress bar */
			if (typeof window.llmUpdateStoryProgressBar === 'function' && d.phrases_total != null) {
				var doneBar = parseInt(d.phrases_done, 10);
				if (isNaN(doneBar)) { doneBar = 0; }
				var totalBar = parseInt(d.phrases_total, 10);
				if (isNaN(totalBar)) { totalBar = phrases.length; }
				window.llmUpdateStoryProgressBar(String(storyId), doneBar, totalBar);
			}

			var savedMsg = i18n.readGoFastSaved || 'Salvato nel database.';
			showDbStatus(savedMsg, false, 1400).then(function () {
				setMessagePhase2('', '');
				if (completionMsgEl) {
					completionMsgEl.innerHTML = '';
					completionMsgEl.classList.add('llm-phrase-game__message-phase2--success');
				}

				var typed = messages.reduce(function (chain, msg, ix) {
					var isObj = !!msg && 'object' === typeof msg;
					var text = isObj ? (msg.text || '') : (msg || '');
					var asHtml = isObj && !!msg.html;
					return chain.then(function () {
						return ix > 0 ? sleepMs(300) : null;
					}).then(function () {
						return text ? appendMessagePhase2Typewriter(text, asHtml) : null;
					});
				}, smoothScrollIntoCenter(scrollTarget));

				typed.then(function () {
					return sleepMs(holdMs);
				}).then(function () {
					var sentence = d.display_sentence || '';
					function advanceAfterPhrase() {
						resetAnalysis();
						if (d.has_more && d.next_index !== null && d.next_index !== undefined) {
							phraseIx = parseInt(d.next_index, 10);
							if (isNaN(phraseIx)) { phraseIx = phrases.length; }
							loadPhrase(false);
						} else {
							phraseIx = phrases.length;
							loadPhrase(false);
						}
					}
					if (!sentence) {
						advanceAfterPhrase();
						return;
					}
					smoothScrollStoryToCenter().then(function () {
						hideUpcomingHint();
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
								appendStoryPhotosAfter(phraseIx);
								advanceAfterPhrase();
							}
						});
					});
				});
			});
		}

		/** Modalità "Read and go fast": AJAX prima, status DB, poi feedback. */
		function handleReadGoFastSubmit() {
			syncStrictAccentsFromDom();
			setMessage('');
			setMessagePhase2('', '');
			clearDbStatus();
			btn1.disabled = true;
			input1.readOnly = true;
			setNotesOpen(false);

			var txt = (input1.value || '').trim();
			var p = phrases[phraseIx];
			var targetRef = p && p.target != null ? String(p.target) : '';

			var opening = '';
			if (!txt) {
				opening = i18n.readGoFastTarget || '';
			} else if (phase2PassesLocal(txt, targetRef, PHASE2_SIM, PHASE2_WR)) {
				opening = i18n.readGoFastExact || i18n.bravoCorrect || '';
			} else {
				opening = i18n.readGoFastAlmost || i18n.readGoFastTarget || '';
			}

			postCheck(2, txt, false, function (json) {
				runFeedbackAfterSave(json, [
					opening,
					{ text: targetRef, html: true },
					i18n.readGoFastComplete || i18n.phraseCompletePoints || ''
				], {
					holdMs: 1200,
					scrollTarget: completionMsgEl || phase1,
					onFail: function () {
						btn1.disabled = false;
						input1.readOnly = false;
					}
				});
			}, true /* bypass */);
		}

		/** Modalità "Gioca al contrario": vedi target, indovina originale (interface). */
		function handlePlayInvertedSubmit() {
			syncStrictAccentsFromDom();
			setMessage('');
			setMessagePhase2('', '');
			clearDbStatus();
			setInvertedHintOpen(false);
			btn1.disabled = true;
			input1.readOnly = true;
			setNotesOpen(false);

			var txt = (input1.value || '').trim();
			var p = phrases[phraseIx];
			var originalRef = p && p.interface != null ? String(p.interface) : '';

			var opening = '';
			if (!txt) {
				opening = i18n.playInvertedTarget || '';
			} else if (phase2PassesLocal(txt, originalRef, PHASE2_SIM, PHASE2_WR)) {
				opening = i18n.playInvertedExact || i18n.bravoCorrect || '';
			} else {
				opening = i18n.playInvertedAlmost || i18n.playInvertedTarget || '';
			}

			postCheck(2, txt, false, function (json) {
				runFeedbackAfterSave(json, [
					opening,
					{ text: originalRef, html: true },
					i18n.playInvertedComplete || i18n.phraseCompletePoints || ''
				], {
					holdMs: 1200,
					scrollTarget: completionMsgEl || phase1,
					onFail: function () {
						btn1.disabled = false;
						input1.readOnly = false;
					}
				});
			}, true /* bypass */);
		}

		btn2.addEventListener('click', function () {
			stopSpeech();
			cancelTts();
			syncStrictAccentsFromDom();
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
			var totalWords = tokenizeWords(txt).length;
			var micUsed = totalWords > 0 && micWordsThisPhrase >= Math.max(1, Math.ceil(totalWords * 0.2));

			runPhraseCompletionFlow(txt, micUsed, {
				scrollTarget: messagePhase2El || phase2,
				onFail: function () {
					btn2.disabled = false;
					if (input2) {
						input2.readOnly = false;
					}
				}
			});
		});

	if (isSinglePhase && notesWrap && notesPanel && analysisEl) {
		notesWrap.hidden = false;
		if (showFieldTransBtn && showFieldTransBtn.parentNode === notesPanel) {
			notesPanel.insertBefore(analysisEl, showFieldTransBtn);
		} else {
			notesPanel.appendChild(analysisEl);
			if (showFieldTransBtn) {
				notesPanel.appendChild(showFieldTransBtn);
			}
		}
		var continueBlock1 = qs(root, '.llm-phrase-game__continue-block--1');
		if (continueBlock1) {
			continueBlock1.insertBefore(notesWrap, continueBlock1.firstChild);
		}
	}

	if (!isSinglePhase && btn1 && i18n.continueToNotes) {
		setContinueLabel(btn1, i18n.continueToNotes);
	}

	if (isReadGoFast && btn1 && i18n.readGoFastNext) {
		setContinueLabel(btn1, i18n.readGoFastNext);
	}

	if (isPlayInverted) {
		if (btn1 && i18n.playInvertedNext) {
			setContinueLabel(btn1, i18n.playInvertedNext);
		}
		if (invertedHintBtn) {
			invertedHintBtn.hidden = false;
			invertedHintBtn.addEventListener('click', function () {
				var open = invertedHintBtn.getAttribute('aria-expanded') === 'true';
				setInvertedHintOpen(!open);
			});
		}
	}

	if (randomWordsOn) {
		randomWordsBlocks.forEach(function (block) {
			block.wrap.hidden = false;
			block.toggle.addEventListener('mousedown', function (e) {
				e.preventDefault();
				e.stopPropagation();
			});
			block.toggle.addEventListener('click', function (e) {
				e.stopPropagation();
				var willOpen = block.toggle.getAttribute('aria-expanded') !== 'true';
				if (willOpen) {
					closeHelperAccsExcept('random');
				}
				setRandomWordsOpen(block, willOpen);
			});
		});
	}

	if (extraCharsOn && getExtraCharsSet()) {
		extraCharsBlocks.forEach(function (block) {
			block.wrap.hidden = false;
			if (randomWordsOn) {
				block.wrap.classList.add('llm-phrase-game__extra-chars--after-random');
			}
			if (block.panel) {
				block.panel.addEventListener('mousedown', function (e) {
					e.preventDefault();
					e.stopPropagation();
				});
				block.panel.addEventListener('click', function (e) {
					e.stopPropagation();
				});
			}
			block.toggle.addEventListener('mousedown', function (e) {
				e.preventDefault();
				e.stopPropagation();
			});
			block.toggle.addEventListener('click', function (e) {
				e.stopPropagation();
				var willOpen = block.toggle.getAttribute('aria-expanded') !== 'true';
				if (willOpen) {
					closeHelperAccsExcept('extra');
				}
				setExtraCharsOpen(block, willOpen);
			});
		});
	}

	keyboardBlocks.forEach(function (block) {
		if (block.panel) {
			block.panel.addEventListener('mousedown', function (e) {
				e.preventDefault();
				e.stopPropagation();
			});
			block.panel.addEventListener('click', function (e) {
				e.stopPropagation();
			});
		}
		block.toggle.addEventListener('mousedown', function (e) {
			e.preventDefault();
			e.stopPropagation();
		});
		block.toggle.addEventListener('click', function (e) {
			e.stopPropagation();
			var willOpen = block.toggle.getAttribute('aria-expanded') !== 'true';
			if (willOpen) {
				closeHelperAccsExcept('keyboard');
			}
			setKeyboardOpen(block, willOpen);
		});
	});

	(function initMobileStickyTranslate() {
		var stickyEl = qs(root, '.llm-phrase-game__sticky-translate');
		var phaseEl = qs(root, '.llm-phrase-game__phase--1');
		if (!stickyEl || !phaseEl || !stickyEl.parentNode) {
			return;
		}

		var placeholder = document.createElement('div');
		placeholder.className = 'llm-phrase-game__sticky-placeholder';
		placeholder.setAttribute('aria-hidden', 'true');
		stickyEl.parentNode.insertBefore(placeholder, stickyEl);

		var pinned = false;
		var raf = 0;

		function isMobile() {
			return window.matchMedia('(max-width: 782px)').matches;
		}

		function headerOffset() {
			var offset = 0;
			if (document.body.classList.contains('admin-bar')) {
				offset = window.innerWidth <= 782 ? 46 : 32;
			}
			var nodes = document.querySelectorAll(
				'.elementor-location-header, .elementor-sticky--active, header[data-sticky], .site-header, #masthead'
			);
			for (var i = 0; i < nodes.length; i++) {
				var el = nodes[i];
				var style = window.getComputedStyle(el);
				var pos = style.position;
				var active = el.classList.contains('elementor-sticky--active') || pos === 'fixed' || pos === 'sticky';
				if (!active) {
					continue;
				}
				var rect = el.getBoundingClientRect();
				if (rect.height < 8 || rect.bottom <= 0 || rect.top > 160) {
					continue;
				}
				offset = Math.max(offset, Math.round(rect.bottom));
			}
			return offset;
		}

		function unpin() {
			if (!pinned) {
				return;
			}
			pinned = false;
			stickyEl.classList.remove('is-pinned');
			stickyEl.style.top = '';
			stickyEl.style.left = '';
			stickyEl.style.width = '';
			placeholder.classList.remove('is-active');
			placeholder.style.height = '';
		}

		function pin(topOff, width, left) {
			if (!pinned) {
				placeholder.style.height = stickyEl.offsetHeight + 'px';
				placeholder.classList.add('is-active');
				stickyEl.classList.add('is-pinned');
				pinned = true;
			} else {
				placeholder.style.height = stickyEl.offsetHeight + 'px';
			}
			stickyEl.style.top = topOff + 'px';
			stickyEl.style.width = Math.max(0, width) + 'px';
			stickyEl.style.left = left + 'px';
		}

		function update() {
			raf = 0;
			if (!isMobile() || root.classList.contains('llm-phrase-game--phase2-active') || phaseEl.hidden) {
				unpin();
				return;
			}

			var topOff = headerOffset();
			var ref = pinned ? placeholder : stickyEl;
			var refRect = ref.getBoundingClientRect();
			var phaseRect = phaseEl.getBoundingClientRect();
			var stickyH = stickyEl.offsetHeight;
			var width = refRect.width;
			var left = refRect.left;

			if (phaseRect.bottom <= topOff + stickyH + 12) {
				if (phaseRect.bottom <= topOff + 24) {
					unpin();
					return;
				}
				pin(Math.max(topOff, phaseRect.bottom - stickyH), width, left);
				return;
			}

			if (refRect.top <= topOff + 1) {
				pin(topOff, width, left);
			} else {
				unpin();
			}
		}

		function schedule() {
			if (raf) {
				return;
			}
			raf = window.requestAnimationFrame(update);
		}

		window.addEventListener('scroll', schedule, { passive: true });
		window.addEventListener('resize', schedule);
		window.addEventListener('orientationchange', schedule);
		if (window.visualViewport) {
			window.visualViewport.addEventListener('resize', schedule);
			window.visualViewport.addEventListener('scroll', schedule);
		}
		schedule();
	})();

	var startResume =
		!isSinglePhase &&
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
