/* llm-learning-modes.js — barra "Modalità apprendimento" e popup di scelta. */
(function () {
	'use strict';

	var cfg = (typeof window.llmLearningModes !== 'undefined') ? window.llmLearningModes : null;
	var lastFocused = null;

	function storageKey() {
		return (cfg && cfg.storageKey) ? cfg.storageKey : 'llm_learning_mode';
	}

	function optionsStorageKey() {
		return (cfg && cfg.optionsStorageKey) ? cfg.optionsStorageKey : 'llm_learning_options';
	}

	/** Le opzioni viaggiano come lista di id separati da virgola. */
	function sanitizeOptions(ids) {
		if (!cfg || !cfg.options) { return []; }
		var out = [];
		(ids || []).forEach(function (id) {
			for (var i = 0; i < cfg.options.length; i++) {
				if (cfg.options[i].id === id && out.indexOf(id) === -1) {
					out.push(id);
					return;
				}
			}
		});
		return out;
	}

	function readStoredOptions() {
		try {
			var raw = window.localStorage.getItem(optionsStorageKey()) || '';
			return sanitizeOptions(raw ? raw.split(',') : []);
		} catch (e) {
			return [];
		}
	}

	function writeStoredOptions(ids) {
		try {
			window.localStorage.setItem(optionsStorageKey(), ids.join(','));
		} catch (e) {
			/* Storage non disponibile: le opzioni restano spente. */
		}
	}

	/** Utenti loggati: vince il profilo. Ospiti: localStorage. */
	function resolveOptions() {
		if (!cfg) { return []; }
		if (cfg.isLoggedIn) { return sanitizeOptions(cfg.currentOptions || []); }
		return readStoredOptions();
	}

	function isValidMode(id) {
		if (!id || !cfg || !cfg.modes) { return false; }
		for (var i = 0; i < cfg.modes.length; i++) {
			if (cfg.modes[i].id === id) { return true; }
		}
		return false;
	}

	function readStoredMode() {
		try {
			return window.localStorage.getItem(storageKey()) || '';
		} catch (e) {
			return '';
		}
	}

	function writeStoredMode(id) {
		try {
			window.localStorage.setItem(storageKey(), id);
		} catch (e) {
			/* Storage non disponibile: la modalità resta quella di default. */
		}
	}

	/** Utenti loggati: vince il profilo. Ospiti: localStorage, poi default. */
	function resolveMode() {
		if (!cfg) { return ''; }
		if (cfg.isLoggedIn) { return cfg.current || cfg.defaultMode || ''; }
		var stored = readStoredMode();
		return isValidMode(stored) ? stored : (cfg.defaultMode || '');
	}

	function modeLabel(id) {
		if (!cfg || !cfg.modes) { return ''; }
		for (var i = 0; i < cfg.modes.length; i++) {
			if (cfg.modes[i].id === id) { return cfg.modes[i].label; }
		}
		return '';
	}

	function syncRootToMode(root, mode) {
		root.dataset.currentMode = mode;
		var valueEl = root.querySelector('.llm-learning-mode__value');
		if (valueEl) {
			var label = modeLabel(mode);
			if (label) { valueEl.textContent = label; }
		}
		root.querySelectorAll('.llm-learning-mode__radio').forEach(function (radio) {
			radio.checked = radio.value === mode;
			syncOptionActive(radio);
		});
	}

	function syncOptionActive(input) {
		var option = input.closest('.llm-learning-mode__option');
		if (option) {
			option.classList.toggle('llm-learning-mode__option--active', input.checked);
		}
	}

	function modeDisablesOptions(mode) {
		return !!(cfg && cfg.modeDisablesOptions && mode === cfg.modeDisablesOptions);
	}

	function syncRootToOptions(root, ids) {
		root.dataset.currentOptions = ids.join(',');
		root.querySelectorAll('.llm-learning-mode__check').forEach(function (check) {
			check.checked = ids.indexOf(check.value) !== -1;
			syncOptionActive(check);
		});
	}

	/** Default strumenti utili nel popup quando si sceglie una modalità “normale”. */
	function defaultPopupOptions() {
		return sanitizeOptions(['random_words', 'listen_replay_loop']);
	}

	/** In "Gioca al contrario" gli strumenti utili si azzerano e restano disabilitati. */
	function applyOptionsAvailability(root, mode) {
		var disabled = modeDisablesOptions(mode);
		var extras = root.querySelector('.llm-learning-mode__extras');
		if (extras) {
			extras.classList.toggle('llm-learning-mode__extras--disabled', disabled);
			extras.setAttribute('aria-disabled', disabled ? 'true' : 'false');
		}
		root.querySelectorAll('.llm-learning-mode__check').forEach(function (check) {
			check.disabled = disabled;
			if (disabled) {
				check.checked = false;
				syncOptionActive(check);
			}
		});
		if (disabled) {
			root.dataset.currentOptions = '';
		}
	}

	/**
	 * Solo UI del popup: invertito → niente strumenti;
	 * altre modalità → parole random + riascolto loop checkati di default.
	 */
	function applyPopupOptionsForMode(root, mode) {
		applyOptionsAvailability(root, mode);
		if (modeDisablesOptions(mode)) {
			return;
		}
		syncRootToOptions(root, defaultPopupOptions());
	}

	function selectedOptions(root) {
		if (modeDisablesOptions(selectedMode(root))) {
			return [];
		}
		var ids = [];
		root.querySelectorAll('.llm-learning-mode__check:checked').forEach(function (check) {
			ids.push(check.value);
		});
		return sanitizeOptions(ids);
	}

	function overlayOf(root) {
		return root ? root.querySelector('.llm-learning-mode__overlay') : null;
	}

	function openDialog(root) {
		var overlay = overlayOf(root);
		if (!overlay) { return; }
		lastFocused = document.activeElement;
		setMessage(root, '', false);
		var openMode = root.dataset.currentMode || resolveMode();
		syncRootToMode(root, openMode);
		syncRootToOptions(root, modeDisablesOptions(openMode) ? [] : resolveOptions());
		applyOptionsAvailability(root, openMode);
		overlay.hidden = false;
		var checked = overlay.querySelector('.llm-learning-mode__radio:checked');
		if (checked) { checked.focus(); }
	}

	function closeDialog(root) {
		var overlay = overlayOf(root);
		if (!overlay || overlay.hidden) { return; }
		overlay.hidden = true;
		if (lastFocused && typeof lastFocused.focus === 'function') {
			lastFocused.focus();
		}
		lastFocused = null;
	}

	function setMessage(root, text, isError) {
		var msgEl = root.querySelector('.llm-learning-mode__msg');
		if (!msgEl) { return; }
		msgEl.textContent = text || '';
		msgEl.classList.toggle('llm-learning-mode__msg--error', !!isError);
	}

	function setBusy(root, busy) {
		root.querySelectorAll('.llm-learning-mode__save, .llm-learning-mode__cancel').forEach(function (btn) {
			btn.disabled = !!busy;
		});
	}

	function selectedMode(root) {
		var checked = root.querySelector('.llm-learning-mode__radio:checked');
		return checked ? checked.value : '';
	}

	function saveMode(root) {
		var mode = selectedMode(root);
		if (!isValidMode(mode)) { return; }

		var options = selectedOptions(root);
		var sameMode = mode === (root.dataset.currentMode || '');
		var sameOptions = options.join(',') === resolveOptions().join(',');

		if (sameMode && sameOptions) {
			closeDialog(root);
			return;
		}

		setBusy(root, true);

		if (modeDisablesOptions(mode)) {
			options = [];
		}

		if (!cfg.isLoggedIn) {
			writeStoredMode(mode);
			writeStoredOptions(options);
			setMessage(root, cfg.savedMsg || '', false);
			setTimeout(function () { window.location.reload(); }, 700);
			return;
		}

		var body = new URLSearchParams();
		body.append('action', cfg.action);
		body.append('nonce', cfg.nonce);
		body.append('mode', mode);
		options.forEach(function (id) {
			body.append('options[]', id);
		});

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (data && data.success) {
					writeStoredMode(mode);
					writeStoredOptions(options);
					setMessage(root, cfg.savedMsg || '', false);
					setTimeout(function () { window.location.reload(); }, 700);
					return;
				}
				setBusy(root, false);
				setMessage(root, cfg.errorMsg || '', true);
			})
			.catch(function () {
				setBusy(root, false);
				setMessage(root, cfg.errorMsg || '', true);
			});
	}

	function init() {
		if (!cfg) { return; }

		var roots = document.querySelectorAll('.llm-learning-mode');
		if (!roots.length) { return; }

		var mode = resolveMode();
		var options = resolveOptions();
		roots.forEach(function (root) {
			syncRootToMode(root, mode);
			syncRootToOptions(root, options);
		});

		document.addEventListener('click', function (e) {
			var root = e.target.closest('.llm-learning-mode');
			if (!root) { return; }

			if (e.target.closest('.llm-learning-mode__change')) {
				openDialog(root);
			} else if (
				e.target.closest('.llm-learning-mode__cancel') ||
				e.target.closest('.llm-learning-mode__close')
			) {
				closeDialog(root);
			} else if (e.target.closest('.llm-learning-mode__save')) {
				saveMode(root);
			} else if (e.target.classList.contains('llm-learning-mode__overlay')) {
				closeDialog(root);
			}
		});

		document.addEventListener('change', function (e) {
			var check = e.target.closest('.llm-learning-mode__check');
			if (check) {
				if (check.disabled) {
					check.checked = false;
					return;
				}
				syncOptionActive(check);
				return;
			}
			var radio = e.target.closest('.llm-learning-mode__radio');
			if (!radio) { return; }
			var root = radio.closest('.llm-learning-mode');
			if (!root) { return; }
			root.querySelectorAll('.llm-learning-mode__radio').forEach(syncOptionActive);
			applyPopupOptionsForMode(root, radio.value);
		});

		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape') { return; }
			document.querySelectorAll('.llm-learning-mode').forEach(function (root) {
				closeDialog(root);
			});
		});
	}

	/** Modalità e opzioni attive, per gli altri script del gioco frasi. */
	window.llmGetLearningMode = resolveMode;
	window.llmGetLearningOptions = resolveOptions;

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
