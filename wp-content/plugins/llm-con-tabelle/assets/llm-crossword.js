/*
 * llm-crossword.js — cruciverba giocabile: griglia, definizioni collegate,
 * rivela lettera, controllo e ripresa della partita dal browser.
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

	/** Categoria grammaticale + definizione inglese, con l'italiano a capo tra parentesi. */
	function formatDefHtml(def) {
		if (!def) {
			return '';
		}
		var en = def.en ? String(def.en) : '';
		var it = def.it ? String(def.it) : '';
		var pos = def.pos ? String(def.pos) : '';
		if (!en) {
			return escapeHtml(it);
		}
		var html = '';
		if (pos) {
			html += '<span class="cw-def-pos">' + escapeHtml(pos) + '</span> ';
		}
		html += escapeHtml(en);
		if (it) {
			html += '<br><em>(' + escapeHtml(it) + ')</em>';
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
		var revealBtn = root.querySelector('[data-cw-reveal]');
		var revealBtnMobile = root.querySelector('[data-cw-reveal-mobile]');
		var mobileClueEl = root.querySelector('[data-cw-mobile-clue]');
		var mobileClueMeta = root.querySelector('[data-cw-mobile-clue-meta]');
		var mobileClueText = root.querySelector('[data-cw-mobile-clue-text]');
		if (!gridEl || !clueListEl) {
			return;
		}

		var inputs = [];
		var entries = [];
		var cellGrid = [];
		var activeCell = null;
		var activeDirection = null;
		var saveTimer = null;

		function t(key) {
			return i18n[key] != null ? i18n[key] : '';
		}

		function setStatus(text) {
			if (statusEl) {
				statusEl.textContent = text;
			}
		}

		function isBlack(r, c) {
			if (r < 0 || r >= rows || c < 0 || c >= cols) {
				return true;
			}
			return gridRows[r].charAt(c) === '#';
		}

		function buildGrid() {
			gridEl.innerHTML = '';
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
			if (!mobileClueEl) {
				return;
			}
			mobileClueEl.hidden = false;
			if (!entry) {
				if (mobileClueMeta) {
					mobileClueMeta.textContent = '';
				}
				if (mobileClueText) {
					mobileClueText.textContent = t('mobile_clue_empty') || t('start_hint');
				}
				mobileClueEl.classList.remove('cw-mobile-clue--active');
				return;
			}
			var dirLabel = entry.direction === 'across' ? t('across') : t('down');
			if (mobileClueMeta) {
				mobileClueMeta.textContent = entry.number + ' · ' + dirLabel;
			}
			if (mobileClueText) {
				mobileClueText.innerHTML = clueText(entry);
			}
			mobileClueEl.classList.add('cw-mobile-clue--active');
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
			var clueEl = clueListEl.querySelector(
				'[data-number="' + entry.number + '"][data-direction="' + entry.direction + '"]'
			);
			if (clueEl) {
				clueEl.classList.add('cw-clue-active');
				/* Su mobile la definizione e' gia' sopra: non scrollare alla lista sotto. */
				var isMobile = window.matchMedia('(max-width: 782px)').matches;
				if (!opts.skipScroll && !isMobile) {
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
				// Riseleziona anche se la casella era gia' a fuoco: senza questo
				// un secondo click non farebbe ripartire l'evento 'focus'.
				window.setTimeout(function () {
					input.select();
				}, 0);
			});

			input.addEventListener('focus', function () {
				if (activeCell !== input) {
					selectCell(input, false);
				}
				window.setTimeout(function () {
					input.select();
				}, 0);
			});

			input.addEventListener('keydown', function (event) {
				if (event.key === 'Backspace' || event.key === 'Delete') {
					event.preventDefault();
					if (event.target.value) {
						event.target.value = '';
						event.target.classList.remove('cw-correct', 'cw-wrong');
						scheduleSave();
						return;
					}
					var entry = activeDirection === 'across' ? event.target._acrossEntry : event.target._downEntry;
					if (entry) {
						var prev = entry.cells[entry.cells.indexOf(event.target) - 1];
						if (prev) {
							prev.value = '';
							prev.classList.remove('cw-correct', 'cw-wrong');
							moveTo(prev);
							scheduleSave();
						}
					}
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
				var html = formatDefHtml(def);
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

		function persist() {
			var api = store();
			if (!cfg.saveProgress || !api || !api.setCrossword) {
				return;
			}
			api.setCrossword(cfg.id, {
				title: cfg.title || '',
				cells: snapshotLetters(),
				filled: countFilled(),
				total: inputs.length,
				solved: isSolved()
			});
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
			var api = store();
			if (!cfg.saveProgress || !api || !api.getCrossword) {
				return false;
			}
			var saved = api.getCrossword(cfg.id);
			if (!saved) {
				return false;
			}
			return applyLetters(saved.cells);
		}

		function syncCellSize() {
			var width = root.clientWidth || 0;
			if (!width) {
				return;
			}
			var isMobile = width < SIDE_BY_SIDE_WIDTH;
			var available = isMobile ? width : width - PANEL_RESERVE;
			var maxCell = isMobile ? 22 : MAX_CELL;
			var minCell = isMobile ? 15 : MIN_CELL;
			var size = Math.floor((available - (isMobile ? 4 : 8)) / cols);
			if (size > maxCell) {
				size = maxCell;
			}
			if (size < minCell) {
				size = minCell;
			}
			root.style.setProperty('--cw-cell-size', size + 'px');
		}

		function watchResize() {
			if (window.ResizeObserver) {
				var observer = new window.ResizeObserver(syncCellSize);
				observer.observe(root);
				return;
			}
			var timer = null;
			window.addEventListener('resize', function () {
				if (timer) {
					window.clearTimeout(timer);
				}
				timer = window.setTimeout(syncCellSize, 150);
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
				setStatus(format(t('solved'), [inputs.length, entries.length]));
			} else {
				setStatus(
					format(t('check_progress'), [correct, inputs.length, wrong, empty, wordsOk, entries.length])
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
			inputs.forEach(function (input) {
				input.value = '';
				input.classList.remove('cw-correct', 'cw-wrong');
			});
			clearHighlight();
			activeCell = null;
			activeDirection = null;
			setStatus(t('cleared'));
			var api = store();
			if (cfg.saveProgress && api && api.removeCrossword) {
				api.removeCrossword(cfg.id);
			}
		}

		buildGrid();
		renderClues();
		syncCellSize();
		watchResize();
		updateMobileClue(null);
		setStatus(restore() ? t('resumed') : t('start_hint'));

		if (checkBtn) {
			checkBtn.addEventListener('click', runCheck);
		}
		if (restartBtn) {
			restartBtn.addEventListener('click', restart);
		}
		if (revealBtn) {
			revealBtn.addEventListener('click', revealLetter);
		}
		if (revealBtnMobile) {
			revealBtnMobile.addEventListener('mousedown', function (e) {
				e.preventDefault();
			});
			revealBtnMobile.addEventListener('click', function (e) {
				e.preventDefault();
				revealLetter();
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
