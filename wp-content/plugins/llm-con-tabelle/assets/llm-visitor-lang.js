/*
 * llm-visitor-lang.js — copia in localStorage la coppia linguistica del visitatore.
 *
 * Il cookie resta la fonte di verità (il server lo legge già al primo render).
 * localStorage è la riserva: se il cookie scade o viene pulito, lo rimette e ricarica una volta.
 */
(function () {
	'use strict';

	var cfg = window.llmVisitorLang;
	if (!cfg) {
		return;
	}

	var codes = cfg.codes || [];

	function isValid(code) {
		return !!code && codes.indexOf(code) !== -1;
	}

	function readStored(key) {
		try {
			return window.localStorage.getItem(key) || '';
		} catch (e) {
			return '';
		}
	}

	function writeStored(key, value) {
		try {
			window.localStorage.setItem(key, value);
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

	var storedKnown = readStored(cfg.knownKey);
	var storedLearning = readStored(cfg.learningKey);

	/* Il server ha una scelta salvata: localStorage si allinea a quella. */
	if (cfg.knownStored && cfg.known !== storedKnown) {
		writeStored(cfg.knownKey, cfg.known);
	}
	if (cfg.learningStored && cfg.learning !== storedLearning) {
		writeStored(cfg.learningKey, cfg.learning);
	}

	/* Per gli utenti loggati vince sempre il profilo: nessun ripristino dal browser. */
	if (cfg.isLoggedIn) {
		return;
	}

	var restoreKnown = !cfg.knownStored && isValid(storedKnown) ? storedKnown : '';
	var restoreLearning = !cfg.learningStored && isValid(storedLearning) ? storedLearning : '';
	if (!restoreKnown && !restoreLearning) {
		return;
	}

	/* Un solo tentativo per sessione: se i cookie sono bloccati non si entra in ciclo. */
	var flag = cfg.restoreFlag || 'llm_lang_restored';
	try {
		if (window.sessionStorage.getItem(flag)) {
			return;
		}
		window.sessionStorage.setItem(flag, '1');
	} catch (e) {
		return;
	}

	var restored = false;
	if (restoreKnown) {
		writeCookie(cfg.knownKey, restoreKnown);
		restored = cookieHas(cfg.knownKey, restoreKnown) || restored;
	}
	if (restoreLearning) {
		writeCookie(cfg.learningKey, restoreLearning);
		restored = cookieHas(cfg.learningKey, restoreLearning) || restored;
	}

	/* Ricarica solo se il cookie è stato accettato: altrimenti sarebbe un giro a vuoto. */
	if (restored) {
		window.location.reload();
	}
})();
