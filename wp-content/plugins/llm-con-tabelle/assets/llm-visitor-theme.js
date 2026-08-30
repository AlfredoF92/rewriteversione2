/*
 * llm-visitor-theme.js — tema colore (light | dark) e layout storia (one | two).
 *
 * Il cookie/profilo è la fonte di verità (il server lo legge già al primo render).
 * localStorage è la riserva: se il cookie scade, lo rimette e ricarica una volta.
 * I pulsanti [data-llm-game-theme] e [data-llm-story-layout] salvano via query string.
 */
(function () {
	'use strict';

	var cfg = window.llmVisitorTheme;
	if (!cfg) {
		return;
	}

	function isValid(theme) {
		return theme === 'light' || theme === 'dark';
	}

	function isValidLayout(layout) {
		return layout === 'one' || layout === 'two';
	}

	function readStored(name) {
		try {
			return window.localStorage.getItem(name) || '';
		} catch (e) {
			return '';
		}
	}

	function writeStored(name, value) {
		try {
			window.localStorage.setItem(name, value);
		} catch (e) {
			/* Storage non disponibile: resta valido il solo cookie. */
		}
	}

	function writeCookie(name, value) {
		document.cookie =
			name + '=' + encodeURIComponent(value) +
			'; path=' + (cfg.cookiePath || '/') +
			'; max-age=' + (cfg.cookieMaxAge || 0) +
			'; SameSite=Lax' +
			(cfg.secure ? '; Secure' : '');
	}

	function cookieHas(name, value) {
		return document.cookie.indexOf(name + '=' + encodeURIComponent(value)) !== -1;
	}

	function saveAndReload(queryVar, value) {
		var url = new URL(window.location.href);
		url.searchParams.set(queryVar, value);
		window.location.href = url.toString();
	}

	var themeName = cfg.cookieName || 'llm_game_theme';
	var layoutName = cfg.cookieNameLayout || 'llm_story_layout';
	var storedTheme = readStored(themeName);
	var storedLayout = readStored(layoutName);

	if (cfg.themeStored && cfg.theme !== storedTheme) {
		writeStored(themeName, cfg.theme);
	}
	if (cfg.layoutStored && cfg.layout !== storedLayout) {
		writeStored(layoutName, cfg.layout);
	}

	document.addEventListener('click', function (e) {
		var themeBtn = e.target.closest('[data-llm-game-theme]');
		if (themeBtn) {
			var theme = themeBtn.getAttribute('data-llm-game-theme');
			if (!isValid(theme) || themeBtn.classList.contains('is-active')) {
				return;
			}
			e.preventDefault();
			writeStored(themeName, theme);
			saveAndReload(cfg.queryVar || 'llm_game_theme', theme);
			return;
		}

		var layoutBtn = e.target.closest('[data-llm-story-layout]');
		if (!layoutBtn) {
			return;
		}
		var layout = layoutBtn.getAttribute('data-llm-story-layout');
		if (!isValidLayout(layout) || layoutBtn.classList.contains('is-active')) {
			return;
		}
		e.preventDefault();
		writeStored(layoutName, layout);
		saveAndReload(cfg.queryVarLayout || 'llm_story_layout', layout);
	});

	if (cfg.isLoggedIn) {
		return;
	}

	var needReload = false;

	if (!cfg.themeStored && isValid(storedTheme)) {
		var themeFlag = cfg.restoreFlag || 'llm_theme_restored';
		var themeOk = false;
		try {
			if (!window.sessionStorage.getItem(themeFlag)) {
				window.sessionStorage.setItem(themeFlag, '1');
				themeOk = true;
			}
		} catch (e) {
			themeOk = false;
		}
		if (themeOk) {
			writeCookie(themeName, storedTheme);
			if (cookieHas(themeName, storedTheme)) {
				needReload = true;
			}
		}
	}

	if (!cfg.layoutStored && isValidLayout(storedLayout)) {
		var layoutFlag = cfg.restoreFlagLayout || 'llm_layout_restored';
		var layoutOk = false;
		try {
			if (!window.sessionStorage.getItem(layoutFlag)) {
				window.sessionStorage.setItem(layoutFlag, '1');
				layoutOk = true;
			}
		} catch (e2) {
			layoutOk = false;
		}
		if (layoutOk) {
			writeCookie(layoutName, storedLayout);
			if (cookieHas(layoutName, storedLayout)) {
				needReload = true;
			}
		}
	}

	if (needReload) {
		window.location.reload();
	}
})();
