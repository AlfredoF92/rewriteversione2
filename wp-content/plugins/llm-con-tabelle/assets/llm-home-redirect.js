/**
 * LLM Home Redirect — selettore coppia linguistica.
 *
 * - Filtra le opzioni della select "lingua appresa" escludendo la stessa lingua di "lingua nota".
 * - Utenti loggati: salva la coppia via AJAX (user meta), poi esegue il redirect.
 * - Ospiti: esegue il redirect diretto senza salvataggio.
 */
(function () {
	'use strict';

	var cfg      = window.llmHomeRedirect || {};
	var urlMap   = cfg.urlMap   || {};
	var i18n     = cfg.i18n     || {};
	var loggedIn = !! cfg.loggedIn;
	var ajaxUrl  = cfg.ajaxUrl  || '';
	var action   = cfg.action   || '';
	var nonce    = cfg.nonce    || '';

	/**
	 * Restituisce l'URL per la coppia, o '' se non esiste.
	 *
	 * @param {string} known
	 * @param {string} learning
	 * @returns {string}
	 */
	function getUrl( known, learning ) {
		return ( urlMap[ known ] && urlMap[ known ][ learning ] )
			? urlMap[ known ][ learning ]
			: '';
	}

	/**
	 * Inizializza un singolo widget [data-llm-home-redirect].
	 *
	 * @param {HTMLElement} root
	 */
	function initWidget( root ) {
		var form      = root.querySelector( '[data-llm-hr-form]' );
		var knownSel  = root.querySelector( '[data-llm-hr-known]' );
		var learnSel  = root.querySelector( '[data-llm-hr-learning]' );
		var btn       = root.querySelector( '[data-llm-hr-btn]' );
		var errEl     = root.querySelector( '[data-llm-hr-error]' );

		if ( ! form || ! knownSel || ! learnSel ) {
			return;
		}

		// Disabilita nella select "lingua appresa" l'opzione uguale a "lingua nota"
		function syncLearningOptions() {
			var known = knownSel.value;
			Array.prototype.forEach.call( learnSel.options, function ( opt ) {
				if ( opt.value === '' ) {
					return;
				}
				opt.disabled = opt.value === known;
				if ( opt.disabled && learnSel.value === opt.value ) {
					learnSel.value = '';
				}
			} );
		}

		knownSel.addEventListener( 'change', syncLearningOptions );
		syncLearningOptions();

		function showError( msg ) {
			if ( ! errEl ) {
				return;
			}
			errEl.textContent = msg;
			errEl.hidden = false;
		}

		function hideError() {
			if ( ! errEl ) {
				return;
			}
			errEl.hidden = true;
			errEl.textContent = '';
		}

		function doRedirect( url ) {
			if ( url ) {
				window.location.href = url;
			} else {
				showError( i18n.noPage || 'Questa combinazione non è ancora disponibile.' );
				if ( btn ) {
					btn.disabled = false;
				}
			}
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			hideError();

			var known    = knownSel.value;
			var learning = learnSel.value;

			if ( ! known || ! learning || known === learning ) {
				return;
			}

			var targetUrl = getUrl( known, learning );

			if ( btn ) {
				btn.disabled = true;
			}

			if ( loggedIn && ajaxUrl && nonce ) {
				// Utente loggato: salva user meta → poi redirect
				var fd = new FormData();
				fd.append( 'action',   action );
				fd.append( 'nonce',    nonce );
				fd.append( 'known',    known );
				fd.append( 'learning', learning );

				fetch( ajaxUrl, {
					method:      'POST',
					body:        fd,
					credentials: 'same-origin',
				} )
					.then( function ( response ) {
						return response.json();
					} )
					.then( function ( data ) {
						if ( data.success ) {
							var redirectUrl = ( data.data && data.data.url ) ? data.data.url : targetUrl;
							doRedirect( redirectUrl );
						} else {
							showError( i18n.error || 'Errore. Riprova.' );
							if ( btn ) {
								btn.disabled = false;
							}
						}
					} )
					.catch( function () {
						showError( i18n.error || 'Errore. Riprova.' );
						if ( btn ) {
							btn.disabled = false;
						}
					} );
			} else {
				// Ospite: redirect diretto
				doRedirect( targetUrl );
			}
		} );
	}

	// Inizializza tutti i widget presenti nella pagina
	document.querySelectorAll( '[data-llm-home-redirect]' ).forEach( initWidget );
}() );
