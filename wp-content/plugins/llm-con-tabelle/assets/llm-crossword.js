/*
 * llm-crossword.js — cruciverba giocabile: griglia, definizioni collegate,
 * rivela lettera, controllo e ripresa (account se loggato, browser se ospite).
 */
(function (window, document) {
	'use strict';

	var MAX_CELL = 30;
	var MIN_CELL = 18;
	var PANEL_RESERVE = 260;
	var SIDE_BY_SIDE_WIDTH = 782;

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.textContent = String(str == null ? '' : str);
		return div.innerHTML;
	}

	/** Sostituisce %s, %d e %1$d nelle stringhe tradotte lato PHP. */
	function format(tpl, args) {
		var auto = 0;
		return String(tpl == null ? '' : tpl).replace(/%(?:(\d+)\$)?[sd]/g, function (match, pos) {
			var idx = pos ? parseInt(pos, 10) - 1 : auto++;
			return args && args[idx] != null ? String(args[idx]) : '';
		});
	}

	/** Toglie "(Soluzione: Frase n. XX)" dal testo e ne ricava il numero. */
	function splitSolutionHint(text) {
		var n = 0;
		var cleaned = String(text || '').replace(
			/\s*[\(\[]?\s*(?:Soluzione|Solution|Soluci[oó]n|Rozwi[aą]zanie)\s*:?\s*(?:Frase|Phrase|Zdanie)?\s*n\.?\s*(\d+)\s*[\)\]]?\s*/gi,
			function (_m, num) {
				var parsed = parseInt(num, 10);
				if (parsed) {
					n = parsed;
				}
				return ' ';
			}
		);
		cleaned = cleaned.replace(/\s+/g, ' ').replace(/^[\s.;,–—-]+|[\s.;,–—-]+$/g, '').trim();
		return { text: cleaned, n: n };
	}

	/** Due righe: bandiera + definizione nota in grassetto, poi bandiera + obiettivo. Hint frase una sola volta. */
	function formatDefHtml(def, flags, i18n) {
		if (!def) {
			return '';
		}
		var known = splitSolutionHint(def.en ? String(def.en) : '');
		var target = splitSolutionHint(def.it ? String(def.it) : '');
		var en = known.text;
		var it = target.text;
		var phraseN = known.n || target.n;
		var knownFlag = flags && flags.known ? String(flags.known) : '';
		var targetFlag = flags && flags.target ? String(flags.target) : '';
		if (!en && !it) {
			return '';
		}
		var html = '';
		if (en) {
			html += '<span class="cw-def-line cw-def-line--known">';
			if (knownFlag) {
				html += '<span class="cw-def-flag" aria-hidden="true">' + escapeHtml(knownFlag) + '</span>';
			}
			html += '<strong class="cw-def-known">' + escapeHtml(en) + '</strong></span>';
		}
		if (it) {
			html += '<span class="cw-def-line cw-def-line--target">';
			if (targetFlag) {
				html += '<span class="cw-def-flag" aria-hidden="true">' + escapeHtml(targetFlag) + '</span>';
			}
			html += '<span class="cw-def-target">' + escapeHtml(it) + '</span></span>';
		}
		if (phraseN) {
			var tpl = i18n && i18n.solution_phrase ? i18n.solution_phrase : 'Soluzione Frase n. %d';
			html += '<span class="cw-def-solution">' + escapeHtml(format(tpl, [phraseN])) + '</span>';
		}
		return html;
	}

	function readConfig(root) {
		var node = root.querySelector('.llm-crossword__config');
		if (!node) {
			return null;
		}
		try {
			return JSON.parse(node.textContent || node.innerHTML || '');
		} catch (e) {
			return null;
		}
	}

	function store() {
		return window.llmGuestBrowserStore || null;
	}

	function createGame(root) {
		var cfg = readConfig(root);
		if (!cfg || !cfg.grid || !cfg.grid.length) {
			return;
		}

		var i18n = cfg.i18n || {};
		var gridRows = cfg.grid;
		var rows = gridRows.length;
		var cols = String(gridRows[0]).length;
		var clues = cfg.clues || {};

		var gridEl = root.querySelector('[data-cw-grid]');
		var clueListEl = root.querySelector('[data-cw-clues]');
		var statusEl = root.querySelector('[data-cw-status]');
		var checkBtn = root.querySelector('[data-cw-check]');
		var restartBtn = root.querySelector('[data-cw-restart]');
		var zoomInBtn = root.querySelector('[data-cw-zoom-in]');
		var zoomOutBtn = root.querySelector('[data-cw-zoom-out]');
		var revealBtn = root.querySelector('[data-cw-reveal]');
		var revealBtnsMobile = root.querySelectorAll('[data-cw-reveal-mobile]');
		var mobileClueEls = root.querySelectorAll('[data-cw-mobile-clue]');
		var keyboardToggle = root.querySelector('[data-cw-keyboard-toggle]');
		var keyboardPanel = root.querySelector('[data-cw-keyboard-panel]');
		if (!gridEl || !clueListEl) {
			return;
		}

		var inputs = [];
		var entries = [];
		var cellGrid = [];
		var activeCell = null;
		var activeDirection = null;
		var saveTimer = null;
		var stickySolved = !!cfg.savedSolved;
		var ZOOM_MIN = 0.6;
		var ZOOM_MAX = 1.8;
		var ZOOM_STEP = 0.1;
		var zoom = 1;

		function t(key) {
			return i18n[key] != null ? i18n[key] : '';
		}

		function setStatus(text, kind) {
			if (statusEl) {
				statusEl.textContent = text;
				statusEl.classList.toggle('cw-status--success', kind === 'success');
			}
		}

		function isBlack(r, c) {
			if (r < 0 || r >= rows || c < 0 || c >= cols) {
				return true;
			}
			return gridRows[r].charAt(c) === '#';
		}

		function isTouchPlay() {
			if (window.matchMedia) {
				if (window.matchMedia('(pointer: coarse)').matches) {
					return true;
				}
				if (window.matchMedia('(max-width: 782px)').matches) {
					return true;
				}
			}
			return window.innerWidth <= SIDE_BY_SIDE_WIDTH;
		}

		function applyCellInputMode(input) {
			if (!input) {
				return;
			}
			if (isTouchPlay()) {
				input.readOnly = true;
				input.setAttribute('inputmode', 'none');
				input.setAttribute('virtualkeyboardpolicy', 'manual');
			} else {
				input.readOnly = false;
				input.setAttribute('inputmode', 'text');
				input.removeAttribute('virtualkeyboardpolicy');
			}
		}

		function syncTouchMode() {
			inputs.forEach(applyCellInputMode);
		}

		function openKeyboard() {
			if (!keyboardToggle || !keyboardPanel) {
				return;
			}
			paintCrosswordKeyboard();
			keyboardToggle.setAttribute('aria-expanded', 'true');
			keyboardPanel.hidden = false;
		}

		function buildGrid() {
			gridEl.innerHTML = '';
			root.style.setProperty('--cw-cols', String(cols));
			root.style.setProperty('--cw-rows', String(rows));
			gridEl.style.gridTemplateColumns = 'repeat(' + cols + ', var(--cw-cell-size))';
			gridEl.style.gridTemplateRows = 'repeat(' + rows + ', var(--cw-cell-size))';

			inputs = [];
			entries = [];
			cellGrid = [];

			var r;
			var c;
			for (r = 0; r < rows; r++) {
				var rowArr = [];
				for (c = 0; c < cols; c++) {
					if (isBlack(r, c)) {
						rowArr.push(null);
						continue;
					}
					var input = document.createElement('input');
					input.className = 'cw-input';
					input.type = 'text';
					input.maxLength = 1;
					input.autocomplete = 'off';
					input.autocapitalize = 'characters';
					input.spellcheck = false;
					input.setAttribute('autocorrect', 'off');
					input.dataset.answer = gridRows[r].charAt(c);
					input.dataset.row = String(r);
					input.dataset.col = String(c);
					applyCellInputMode(input);
					rowArr.push(input);
					inputs.push(input);
				}
				cellGrid.push(rowArr);
			}

			// Numerazione standard: una casella prende un numero se inizia una parola.
			var counter = 0;
			var numbering = {};
			for (r = 0; r < rows; r++) {
				for (c = 0; c < cols; c++) {
					if (isBlack(r, c)) {
						continue;
					}
					var startsAcross = isBlack(r, c - 1) && !isBlack(r, c + 1);
					var startsDown = isBlack(r - 1, c) && !isBlack(r + 1, c);
					if (!startsAcross && !startsDown) {
						continue;
					}
					counter++;
					numbering[r + '-' + c] = counter;

					if (startsAcross) {
						var acrossCells = [];
						for (var cc = c; !isBlack(r, cc); cc++) {
							acrossCells.push(cellGrid[r][cc]);
						}
						entries.push({ number: counter, direction: 'across', cells: acrossCells });
					}
					if (startsDown) {
						var downCells = [];
						for (var rr = r; !isBlack(rr, c); rr++) {
							downCells.push(cellGrid[rr][c]);
						}
						entries.push({ number: counter, direction: 'down', cells: downCells });
					}
				}
			}

			entries.forEach(function (entry) {
				entry.cells.forEach(function (cell) {
					if (entry.direction === 'across') {
						cell._acrossEntry = entry;
					} else {
						cell._downEntry = entry;
					}
				});
			});

			for (r = 0; r < rows; r++) {
				for (c = 0; c < cols; c++) {
					if (isBlack(r, c)) {
						var black = document.createElement('div');
						black.className = 'cw-black';
						gridEl.appendChild(black);
						continue;
					}
					var wrap = document.createElement('div');
					wrap.className = 'cw-cell-wrap';
					var num = numbering[r + '-' + c];
					if (num) {
						var label = document.createElement('span');
						label.className = 'cw-number';
						label.textContent = num;
						wrap.appendChild(label);
					}
					wrap.appendChild(cellGrid[r][c]);
					gridEl.appendChild(wrap);
				}
			}

			inputs.forEach(attachCellListeners);
		}

		function clearHighlight(keepMobileClue) {
			inputs.forEach(function (input) {
				input.classList.remove('cw-highlight', 'cw-active-cell');
			});
			clueListEl.querySelectorAll('.cw-clue-active').forEach(function (el) {
				el.classList.remove('cw-clue-active');
			});
			if (!keepMobileClue) {
				updateMobileClue(null);
			}
		}

		function clearCheckColors() {
			inputs.forEach(function (input) {
				input.classList.remove('cw-correct', 'cw-wrong');
			});
		}

		function updateMobileClue(entry) {
			if (!mobileClueEls.length) {
				return;
			}
			for (var i = 0; i < mobileClueEls.length; i++) {
				var mobileClueEl = mobileClueEls[i];
				var mobileClueMeta = mobileClueEl.querySelector('[data-cw-mobile-clue-meta]');
				var mobileClueText = mobileClueEl.querySelector('[data-cw-mobile-clue-text]');
				mobileClueEl.hidden = false;
				if (!entry) {
					if (mobileClueMeta) {
						mobileClueMeta.textContent = '';
					}
					if (mobileClueText) {
						mobileClueText.textContent = t('mobile_clue_empty') || t('start_hint');
					}
					mobileClueEl.classList.remove('cw-mobile-clue--active');
					continue;
				}
				var dirLabel = entry.direction === 'across' ? t('across') : t('down');
				if (mobileClueMeta) {
					var metaTpl = t('clue_meta') || '%1$d. %2$s - %3$d letters';
					mobileClueMeta.textContent = format(metaTpl, [
						entry.number,
						dirLabel,
						entry.cells.length,
					]);
				}
				if (mobileClueText) {
					mobileClueText.innerHTML = clueText(entry);
				}
				mobileClueEl.classList.add('cw-mobile-clue--active');
			}
		}

		function highlightWord(input, direction, opts) {
			opts = opts || {};
			clearHighlight(true);
			var entry = direction === 'across' ? input._acrossEntry : input._downEntry;
			if (entry) {
				entry.cells.forEach(function (cell) {
					cell.classList.add('cw-highlight');
				});
			}
			input.classList.add('cw-active-cell');

			if (!entry) {
				updateMobileClue(null);
				return;
			}
			updateMobileClue(entry);
			openKeyboard();
			var clueEl = clueListEl.querySelector(
				'[data-number="' + entry.number + '"][data-direction="' + entry.direction + '"]'
			);
			if (clueEl) {
				clueEl.classList.add('cw-clue-active');
				/* La definizione e' gia' sopra la griglia: non scrollare alla lista. */
				var inStory = !!(root.closest && root.closest('.llm-story-view--crossword'));
				if (!opts.skipScroll && !inStory) {
					clueEl.scrollIntoView({ block: 'nearest' });
				}
			}
		}

		function isEntryIncomplete(entry) {
			if (!entry) {
				return true;
			}
			return entry.cells.some(function (cell) {
				return !cell.value;
			});
		}

		function selectCell(input, forceToggle) {
			var direction;
			if (forceToggle && activeCell === input && input._acrossEntry && input._downEntry) {
				direction = activeDirection === 'across' ? 'down' : 'across';
			} else if (activeCell === input && activeDirection) {
				direction = activeDirection;
			} else if (input._acrossEntry && input._downEntry) {
				// Se una delle due parole e' gia' completa, preferiamo quella da finire.
				var acrossDone = !isEntryIncomplete(input._acrossEntry);
				var downDone = !isEntryIncomplete(input._downEntry);
				if (acrossDone && !downDone) {
					direction = 'down';
				} else if (downDone && !acrossDone) {
					direction = 'across';
				} else {
					direction = 'across';
				}
			} else {
				direction = input._acrossEntry ? 'across' : 'down';
			}
			activeCell = input;
			activeDirection = direction;
			highlightWord(input, direction);
		}

		function moveTo(cell, opts) {
			activeCell = cell;
			highlightWord(cell, activeDirection, opts);
			cell.focus({ preventScroll: true });
		}

		/**
		 * Scrive una lettera e avanza. Senza svuotare la casella: quel
		 * "lampeggio" a 110ms in digitazione veloce faceva accumulare
		 * timeout e cascate di focus su molte celle insieme.
		 */
		function deleteFromCell(cell) {
			if (!cell) {
				return;
			}
			if (cell.value) {
				cell.value = '';
				cell.classList.remove('cw-correct', 'cw-wrong');
				scheduleSave();
				return;
			}
			var entry = activeDirection === 'across' ? cell._acrossEntry : cell._downEntry;
			if (entry) {
				var prev = entry.cells[entry.cells.indexOf(cell) - 1];
				if (prev) {
					prev.value = '';
					prev.classList.remove('cw-correct', 'cw-wrong');
					moveTo(prev);
					scheduleSave();
				}
			}
		}

		var isWriting = false;
		function writeLetter(cell, letter, fromKeydown) {
			if (!cell || !letter || isWriting) {
				return;
			}
			isWriting = true;
			try {
				cell.value = letter;
				cell.classList.remove('cw-correct', 'cw-wrong');
				// Se la lettera arriva da keydown, ignora l'eventuale 'input'
				// nativo subito dopo (altrimenti si avanza di due caselle).
				if (fromKeydown) {
					cell._ignoreNextInput = true;
				}
				var entry = activeDirection === 'across' ? cell._acrossEntry : cell._downEntry;
				if (entry) {
					var next = entry.cells[entry.cells.indexOf(cell) + 1];
					if (next) {
						moveTo(next);
					}
				}
				scheduleSave();
			} finally {
				isWriting = false;
			}
		}

		function attachCellListeners(input) {
			input.addEventListener('mousedown', function () {
				var isSame = activeCell === input;
				clearCheckColors();
				selectCell(input, isSame);
				if (!isTouchPlay()) {
					window.setTimeout(function () {
						input.select();
					}, 0);
				}
			});

			input.addEventListener('focus', function () {
				if (activeCell !== input) {
					selectCell(input, false);
				}
				if (!isTouchPlay()) {
					window.setTimeout(function () {
						input.select();
					}, 0);
				}
			});

			input.addEventListener('beforeinput', function (event) {
				if (isTouchPlay()) {
					event.preventDefault();
				}
			});

			input.addEventListener('keydown', function (event) {
				if (event.key === 'Backspace' || event.key === 'Delete') {
					event.preventDefault();
					deleteFromCell(event.target);
					return;
				}

				// Tastiera fisica: gestiamo qui e blocchiamo l'input nativo,
				// cosi' non partono due avanzamenti (keydown + input).
				if (event.key && event.key.length === 1 && /[a-zA-Z]/.test(event.key)) {
					event.preventDefault();
					writeLetter(event.target, event.key.toUpperCase(), true);
				}
			});

			// Soft keyboard / mobile: spesso arriva solo l'evento input.
			input.addEventListener('input', function (event) {
				var cell = event.target;
				if (cell._ignoreNextInput) {
					cell._ignoreNextInput = false;
					cell.value = String(cell.value || '')
						.toUpperCase()
						.replace(/[^A-Z]/g, '')
						.slice(-1);
					return;
				}
				if (isWriting) {
					return;
				}
				var letter = String(cell.value || '')
					.toUpperCase()
					.replace(/[^A-Z]/g, '')
					.slice(-1);
				if (letter) {
					writeLetter(cell, letter, false);
				} else {
					cell.value = '';
					scheduleSave();
				}
			});
		}

		function clueText(entry) {
			var def = clues[entry.number + '-' + entry.direction];
			if (def) {
				var html = formatDefHtml(def, {
					known: cfg.knownFlag || '',
					target: cfg.targetFlag || '',
				}, i18n);
				if (html) {
					return html;
				}
			}
			return escapeHtml(format(t('letters_count'), [entry.cells.length]));
		}

		function renderClues() {
			function rowHtml(entry) {
				return (
					'<div class="cw-clue-row" data-number="' +
					entry.number +
					'" data-direction="' +
					entry.direction +
					'"><span class="cw-clue-num">' +
					entry.number +
					'</span> ' +
					clueText(entry) +
					'</div>'
				);
			}

			function byNumber(a, b) {
				return a.number - b.number;
			}

			var across = entries
				.filter(function (e) {
					return e.direction === 'across';
				})
				.sort(byNumber);
			var down = entries
				.filter(function (e) {
					return e.direction === 'down';
				})
				.sort(byNumber);

			var html = '';
			if (across.length) {
				html += '<h3>' + escapeHtml(t('across')) + '</h3>' + across.map(rowHtml).join('');
			}
			if (down.length) {
				html += '<h3>' + escapeHtml(t('down')) + '</h3>' + down.map(rowHtml).join('');
			}
			clueListEl.innerHTML = html;

			clueListEl.querySelectorAll('.cw-clue-row').forEach(function (row) {
				row.addEventListener('click', function () {
					var number = parseInt(row.dataset.number, 10);
					var direction = row.dataset.direction;
					var entry = entries.filter(function (e) {
						return e.number === number && e.direction === direction;
					})[0];
					if (!entry) {
						return;
					}
					activeDirection = direction;
					moveTo(entry.cells[0]);
				});
			});
		}

		function snapshotLetters() {
			var out = [];
			for (var r = 0; r < rows; r++) {
				var line = '';
				for (var c = 0; c < cols; c++) {
					var cell = cellGrid[r][c];
					line += cell ? cell.value || '.' : '#';
				}
				out.push(line);
			}
			return out;
		}

		function applyLetters(saved) {
			if (!saved || !saved.length) {
				return false;
			}
			var applied = false;
			for (var r = 0; r < rows && r < saved.length; r++) {
				var line = String(saved[r] || '');
				for (var c = 0; c < cols && c < line.length; c++) {
					var cell = cellGrid[r][c];
					var ch = line.charAt(c);
					if (cell && ch >= 'A' && ch <= 'Z') {
						cell.value = ch;
						applied = true;
					}
				}
			}
			return applied;
		}

		function countFilled() {
			var filled = 0;
			inputs.forEach(function (input) {
				if (input.value) {
					filled++;
				}
			});
			return filled;
		}

		function isSolved() {
			return inputs.every(function (input) {
				return input.value && input.value === input.dataset.answer;
			});
		}

		function isLoggedIn() {
			if (cfg.loggedIn != null) {
				return parseInt(cfg.loggedIn, 10) === 1;
			}
			var remote = window.llmCrossword || {};
			if (remote.loggedIn != null) {
				return parseInt(remote.loggedIn, 10) === 1;
			}
			return !!(document.body && document.body.classList && document.body.classList.contains('logged-in'));
		}

		function remoteCfg() {
			var remote = window.llmCrossword || {};
			return {
				ajaxUrl: cfg.ajaxUrl || remote.ajaxUrl || '',
				nonce: cfg.nonce || remote.nonce || ''
			};
		}

		function persistPayload() {
			if (isSolved()) {
				stickySolved = true;
			}
			return {
				title: cfg.title || '',
				cells: snapshotLetters(),
				filled: countFilled(),
				total: inputs.length,
				solved: stickySolved || isSolved()
			};
		}

		function persist() {
			var payload = persistPayload();
			if (!isLoggedIn()) {
				var api = store();
				if (cfg.saveProgress && api && api.setCrossword) {
					api.setCrossword(cfg.id, payload);
				}
				return;
			}
			persistServer(payload);
		}

		function persistServer(payload) {
			if (!cfg.saveProgress || !isLoggedIn()) {
				return;
			}
			var remote = remoteCfg();
			if (!remote.ajaxUrl || !remote.nonce) {
				return;
			}
			var body = new window.FormData();
			body.append('action', 'llm_crossword_progress_save');
			body.append('nonce', remote.nonce);
			body.append('id', String(cfg.id || 0));
			body.append('story', String(cfg.storyId || 0));
			body.append('cells', JSON.stringify(payload.cells || []));
			body.append('filled', String(payload.filled || 0));
			body.append('total', String(payload.total || 0));
			body.append('solved', payload.solved ? '1' : '0');
			window.fetch(remote.ajaxUrl, {
				method: 'POST',
				body: body,
				credentials: 'same-origin'
			}).catch(function () {});
		}

		function scheduleSave() {
			if (!cfg.saveProgress) {
				return;
			}
			if (saveTimer) {
				window.clearTimeout(saveTimer);
			}
			saveTimer = window.setTimeout(persist, 400);
		}

		function restore() {
			if (!cfg.saveProgress) {
				return { ok: false, source: '' };
			}
			if (cfg.savedSolved) {
				stickySolved = true;
			}
			if (cfg.savedCells && cfg.savedCells.length) {
				return { ok: applyLetters(cfg.savedCells), source: 'account' };
			}
			var api = store();
			if (!api || !api.getCrossword) {
				return { ok: false, source: '' };
			}
			var saved = api.getCrossword(cfg.id);
			if (!saved) {
				return { ok: false, source: '' };
			}
			if (saved.solved) {
				stickySolved = true;
			}
			var applied = applyLetters(saved.cells);
			if (!applied && !stickySolved) {
				return { ok: false, source: '' };
			}
			if (isLoggedIn()) {
				persistServer(persistPayload());
				return { ok: applied, source: 'account' };
			}
			return { ok: applied, source: 'browser' };
		}

		function zoomStorageKey() {
			return 'llm_cw_zoom_' + String(cfg.id || '0');
		}

		function readZoom() {
			try {
				var raw = window.localStorage.getItem(zoomStorageKey());
				var n = parseFloat(raw);
				if (n >= ZOOM_MIN && n <= ZOOM_MAX) {
					return Math.round(n * 10) / 10;
				}
			} catch (e) {
				/* privacy mode */
			}
			return 1;
		}

		function applyZoom(next) {
			zoom = Math.round(Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, next)) * 10) / 10;
			root.style.setProperty('--cw-zoom', String(zoom));
			root.classList.toggle('llm-crossword--zoomed-in', zoom > 1);
			if (zoomOutBtn) {
				zoomOutBtn.disabled = zoom <= ZOOM_MIN + 0.001;
			}
			if (zoomInBtn) {
				zoomInBtn.disabled = zoom >= ZOOM_MAX - 0.001;
			}
			try {
				window.localStorage.setItem(zoomStorageKey(), String(zoom));
			} catch (e) {
				/* privacy mode */
			}
			syncCellSize();
		}

		function syncCellSize() {
			var width = root.clientWidth || 0;
			if (!width) {
				return;
			}
			var inStory = !!(root.closest && root.closest('.llm-story-view--crossword'));
			var isMobile = width < SIDE_BY_SIDE_WIDTH;
			var available = isMobile || inStory ? width : width - PANEL_RESERVE;
			var maxCell = inStory ? 96 : isMobile ? 22 : MAX_CELL;
			var minCell = isMobile ? 15 : MIN_CELL;
			var size = Math.floor((available - (isMobile ? 4 : 8)) / cols);
			if (size > maxCell) {
				size = maxCell;
			}
			if (size < minCell) {
				size = minCell;
			}
			size = Math.max(12, Math.round(size * zoom));
			root.style.setProperty('--cw-cell-size', size + 'px');
		}

		function watchResize() {
			function onResize() {
				syncCellSize();
				syncTouchMode();
			}
			if (window.ResizeObserver) {
				var observer = new window.ResizeObserver(onResize);
				observer.observe(root);
			}
			window.addEventListener('resize', onResize);
		}

		function paintCrosswordKeyboard() {
			if (!keyboardPanel || keyboardPanel.dataset.cwReady === '1') {
				return;
			}
			keyboardPanel.dataset.cwReady = '1';
			keyboardPanel.innerHTML = '';
			var rowsKb = ['QWERTYUIOP', 'ASDFGHJKL', 'ZXCVBNM'];
			rowsKb.forEach(function (letters, idx) {
				var row = document.createElement('div');
				row.className = 'cw-keyboard__row llm-phrase-game__keyboard-row';
				letters.split('').forEach(function (ch) {
					var btn = document.createElement('button');
					btn.type = 'button';
					btn.className = 'cw-keyboard__key llm-phrase-game__kb-key';
					btn.textContent = ch;
					btn.setAttribute('data-cw-key', ch);
					row.appendChild(btn);
				});
				if (idx === rowsKb.length - 1) {
					var bs = document.createElement('button');
					bs.type = 'button';
					bs.className = 'cw-keyboard__key cw-keyboard__key--util llm-phrase-game__kb-key llm-phrase-game__kb-key--util';
					bs.setAttribute('data-cw-key', 'backspace');
					bs.setAttribute('aria-label', t('keyboard_backspace') || 'Backspace');
					bs.textContent = '⌫';
					row.appendChild(bs);
				}
				keyboardPanel.appendChild(row);
			});
		}

		function bindCrosswordKeyboard() {
			if (!keyboardToggle || !keyboardPanel) {
				return;
			}
			paintCrosswordKeyboard();
			keyboardToggle.addEventListener('mousedown', function (event) {
				event.preventDefault();
			});
			keyboardToggle.addEventListener('click', function (event) {
				event.preventDefault();
				var open = keyboardToggle.getAttribute('aria-expanded') === 'true';
				keyboardToggle.setAttribute('aria-expanded', open ? 'false' : 'true');
				keyboardPanel.hidden = open;
			});
			keyboardPanel.addEventListener('mousedown', function (event) {
				event.preventDefault();
			});
			keyboardPanel.addEventListener('pointerdown', function (event) {
				var btn = event.target.closest ? event.target.closest('[data-cw-key]') : null;
				if (!btn) {
					return;
				}
				if (event.pointerType && event.pointerType !== 'touch') {
					return;
				}
				try {
					if (navigator.vibrate) {
						navigator.vibrate(12);
					}
				} catch (err) {
					/* ignore */
				}
			});
			keyboardPanel.addEventListener('click', function (event) {
				var btn = event.target.closest ? event.target.closest('[data-cw-key]') : null;
				if (!btn) {
					return;
				}
				event.preventDefault();
				var key = btn.getAttribute('data-cw-key');
				var cell = activeCell;
				if (!cell && entries[0] && entries[0].cells[0]) {
					activeDirection = entries[0].direction;
					moveTo(entries[0].cells[0]);
					cell = activeCell;
				}
				if (!cell) {
					return;
				}
				if (key === 'backspace') {
					deleteFromCell(cell);
					return;
				}
				writeLetter(cell, key, true);
			});
		}

		function runCheck() {
			var correct = 0;
			var wrong = 0;
			var empty = 0;

			// Togliamo il giallo, altrimenti coprirebbe i colori del controllo.
			clearHighlight();

			inputs.forEach(function (input) {
				input.classList.remove('cw-correct', 'cw-wrong');
				if (!input.value) {
					empty++;
				} else if (input.value === input.dataset.answer) {
					correct++;
					input.classList.add('cw-correct');
				} else {
					wrong++;
					input.classList.add('cw-wrong');
				}
			});

			var wordsOk = 0;
			entries.forEach(function (entry) {
				var allRight = entry.cells.every(function (cell) {
					return cell.value && cell.value === cell.dataset.answer;
				});
				if (allRight) {
					wordsOk++;
				}
			});

			if (correct === inputs.length) {
				setStatus(format(t('solved'), [inputs.length, entries.length]), 'success');
			} else {
				setStatus(
					format(t('check_progress'), [correct, inputs.length, wrong, empty, wordsOk, entries.length]),
					'success'
				);
			}
			persist();
		}

		function revealLetter() {
			if (!activeCell) {
				setStatus(t('reveal_no_cell'));
				return;
			}
			var answer = activeCell.dataset.answer;
			if (!answer) {
				setStatus(t('reveal_no_answer'));
				return;
			}
			activeCell.value = answer;
			activeCell.classList.remove('cw-correct', 'cw-wrong');
			setStatus(format(t('revealed'), [answer]));

			var entry = activeDirection === 'across' ? activeCell._acrossEntry : activeCell._downEntry;
			if (entry) {
				var next = entry.cells[entry.cells.indexOf(activeCell) + 1];
				if (next) {
					moveTo(next, { skipScroll: true });
				}
			}
			persist();
		}

		function restart() {
			if (countFilled() && !window.confirm(t('restart_confirm'))) {
				return;
			}
			if (isSolved()) {
				stickySolved = true;
			}
			inputs.forEach(function (input) {
				input.value = '';
				input.classList.remove('cw-correct', 'cw-wrong');
			});
			clearHighlight();
			activeCell = null;
			activeDirection = null;
			setStatus(t('cleared'));
			var cleared = {
				title: cfg.title || '',
				cells: snapshotLetters(),
				filled: 0,
				total: inputs.length,
				solved: stickySolved
			};
			var api = store();
			if (cfg.saveProgress && api) {
				if (isLoggedIn() && api.removeCrossword) {
					api.removeCrossword(cfg.id);
				} else if (!isLoggedIn() && stickySolved && api.setCrossword) {
					api.setCrossword(cfg.id, cleared);
				} else if (!isLoggedIn() && api.removeCrossword) {
					api.removeCrossword(cfg.id);
				}
			}
			if (isLoggedIn()) {
				persistServer(cleared);
			}
		}

		buildGrid();
		renderClues();
		applyZoom(readZoom());
		syncTouchMode();
		bindCrosswordKeyboard();
		watchResize();
		updateMobileClue(null);
		var restored = restore();
		if (isSolved()) {
			runCheck();
		} else if (restored.ok) {
			setStatus(
				restored.source === 'account' ? t('resumed_account') : t('resumed'),
				'success'
			);
		} else {
			setStatus(t('start_hint'));
		}

		function bindZoomBtn(btn, delta) {
			if (!btn) {
				return;
			}
			btn.addEventListener('mousedown', function (event) {
				event.preventDefault();
			});
			btn.addEventListener('click', function (event) {
				event.preventDefault();
				applyZoom(zoom + delta);
			});
		}
		bindZoomBtn(zoomOutBtn, -ZOOM_STEP);
		bindZoomBtn(zoomInBtn, ZOOM_STEP);

		if (checkBtn) {
			checkBtn.addEventListener('click', runCheck);
		}
		if (restartBtn) {
			restartBtn.addEventListener('click', restart);
		}
		if (revealBtn) {
			revealBtn.addEventListener('click', revealLetter);
		}
		if (revealBtnsMobile.length) {
			Array.prototype.forEach.call(revealBtnsMobile, function (btn) {
				btn.addEventListener('mousedown', function (e) {
					e.preventDefault();
				});
				btn.addEventListener('click', function (e) {
					e.preventDefault();
					revealLetter();
				});
			});
		}
	}

	function init() {
		var roots = document.querySelectorAll('[data-llm-crossword]');
		Array.prototype.forEach.call(roots, function (root) {
			if (root.dataset.llmCrosswordReady === '1') {
				return;
			}
			root.dataset.llmCrosswordReady = '1';
			createGame(root);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})(window, document);
