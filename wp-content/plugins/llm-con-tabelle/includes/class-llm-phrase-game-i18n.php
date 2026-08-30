<?php
/**
 * Testi UI del gioco frasi nella “lingua che conosce” (meta utente).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Phrase_Game_I18n {

	/**
	 * Codice lingua UI (it|en|pl|es).
	 */
	public static function lang() {
		$code = LLM_Visitor_Lang::known();

		if ( ! is_user_logged_in() ) {
			$code = (string) apply_filters( 'llm_phrase_game_guest_ui_lang', $code );
		}
		if ( ! LLM_Languages::is_valid( $code ) ) {
			$code = 'it';
		}

		return (string) apply_filters( 'llm_phrase_game_ui_lang', $code );
	}

	/**
	 * Nome della lingua di studio (target) formulato nella lingua UI.
	 *
	 * @param string $target_code Codice lingua obiettivo (es. en).
	 */
	public static function target_lang_label_for_ui( $target_code ) {
		$target_code = sanitize_key( (string) $target_code );
		$ui          = self::lang();
		$bundles     = self::bundles();
		if ( ! isset( $bundles[ $ui ]['lang_names'][ $target_code ] ) ) {
			return isset( $bundles['it']['lang_names'][ $target_code ] )
				? $bundles['it']['lang_names'][ $target_code ]
				: $target_code;
		}
		return $bundles[ $ui ]['lang_names'][ $target_code ];
	}

	/**
	 * @param string $key Chiave stringa.
	 * @return string
	 */
	public static function get( $key ) {
		$lang = self::lang();
		$all  = self::bundles();
		if ( isset( $all[ $lang ][ $key ] ) ) {
			return $all[ $lang ][ $key ];
		}
		return isset( $all['it'][ $key ] ) ? $all['it'][ $key ] : '';
	}

	/**
	 * @param string   $key Chiave con segnaposto sprintf.
	 * @param mixed ...$args Argomenti.
	 * @return string
	 */
	public static function format( $key, ...$args ) {
		return vsprintf( self::get( $key ), $args );
	}

	/**
	 * Messaggi creativi post-sessione microfono, per tier.
	 *
	 * @return array<string, array<int, string>>
	 */
	/**
	 * @return array{phase1: array<string, array<int, string>>, phase2: array<string, array<int, string>>}
	 */
	public static function get_mic_feedback() {
		$lang = self::lang();
		$all  = self::mic_feedback_bundles();
		if ( isset( $all[ $lang ] ) ) {
			return $all[ $lang ];
		}
		return $all['it'];
	}

	/**
	 * @return array<string, array{phase1: array<string, array<int, string>>, phase2: array<string, array<int, string>>}>
	 */
	private static function mic_feedback_bundles() {
		return array(
			'it' => array(
				'phase1' => array(
					'not_started' => array(
						'Il microfono non si è attivato. Riprova.',
						'Sessione non avviata. Clicca di nuovo sul microfono.',
					),
					'silent'      => array(
						'Non ho rilevato alcun testo. Prova a scandire le parole più lentamente.',
						'Nessuna voce registrata. Articola meglio, parola per parola.',
					),
					'heard'       => array(
						'Continua pure, aggiungi altre parole se vuoi.',
						'Bene, prova ancora o vai avanti quando sei pronto.',
						'Ottimo, continua a esercitarti con il microfono.',
						'Puoi aggiungere altre parole o cliccare Continua.',
						'Prova ancora a parlare, oppure vai avanti.',
					),
				),
				'phase2' => array(
					'not_started'  => array(
						'Il microfono non si è attivato. Riprova.',
						'Sessione non avviata. Clicca di nuovo sul microfono.',
					),
					'silent'       => array(
						'Non ho rilevato alcun testo. Prova a scandire le parole più lentamente.',
						'Nessuna voce registrata. Articola meglio, parola per parola.',
					),
					'unrecognized' => array(
						'Non corrisponde alla traduzione attesa.',
						'Non contiene parole della traduzione. Riprova parola per parola.',
						'Sembra un\'altra frase. Concentrati sulla traduzione richiesta.',
					),
					'one'          => array(
						'1 parola corretta. Continua così.',
						'Buon inizio: 1 parola riconosciuta.',
					),
					'two'          => array(
						'2 parole corrette. Stai andando bene.',
						'2 parole riconosciute. Prosegui.',
					),
					'some'         => array(
						'%1$d parole corrette. Manca poco.',
						'%1$d parole riconosciute. Sei sulla strada giusta.',
					),
					'all'          => array(
						'Tutte le parole riconosciute. Ottimo lavoro.',
						'Traduzione completa al microfono. Perfetto.',
					),
				),
			),
			'en' => array(
				'phase1' => array(
					'not_started' => array(
						'The microphone did not activate. Try again.',
						'Session not started. Click the microphone again.',
					),
					'silent'      => array(
						'No text detected. Try enunciating the words more slowly.',
						'No voice recorded. Articulate each word more clearly.',
					),
					'heard'       => array(
						'Keep going, add more words if you like.',
						'Good — try again or continue when you are ready.',
						'Keep practising with the microphone.',
						'You can add more words or click Continue.',
						'Try speaking again, or move on.',
					),
				),
				'phase2' => array(
					'not_started'  => array(
						'The microphone did not activate. Try again.',
						'Session not started. Click the microphone again.',
					),
					'silent'       => array(
						'No text detected. Try enunciating the words more slowly.',
						'No voice recorded. Articulate each word more clearly.',
					),
					'unrecognized' => array(
						'It does not match the expected translation.',
						'It does not contain words from the translation. Try again word by word.',
						'It seems to be a different sentence. Focus on the required translation.',
					),
					'one'          => array(
						'1 word correct. Keep going.',
						'Good start: 1 word recognized.',
					),
					'two'          => array(
						'2 words correct. You are doing well.',
						'2 words recognized. Continue.',
					),
					'some'         => array(
						'%1$d words correct. Almost there.',
						'%1$d words recognized. You are on the right track.',
					),
					'all'          => array(
						'All words recognized. Great work.',
						'Complete translation on the microphone. Perfect.',
					),
				),
			),
			'pl' => array(
				'phase1' => array(
					'not_started' => array(
						'Mikrofon nie wystartował. Spróbuj ponownie.',
						'Sesja nie rozpoczęta. Kliknij mikrofon ponownie.',
					),
					'silent'      => array(
						'Nie wykryto tekstu. Spróbuj wymawiać słowa wolniej i wyraźniej.',
						'Nie nagrano głosu. Artykułuj lepiej, słowo po słowie.',
					),
					'heard'       => array(
						'Śmiało, dodaj więcej słów jeśli chcesz.',
						'Dobrze — spróbuj jeszcze raz lub idź dalej, gdy będziesz gotowy.',
						'Kontynuuj ćwiczenie z mikrofonem.',
						'Możesz dodać więcej słów lub kliknąć Kontynuuj.',
						'Spróbuj mówić ponownie albo przejdź dalej.',
					),
				),
				'phase2' => array(
					'not_started'  => array(
						'Mikrofon nie wystartował. Spróbuj ponownie.',
						'Sesja nie rozpoczęta. Kliknij mikrofon ponownie.',
					),
					'silent'       => array(
						'Nie wykryto tekstu. Spróbuj wymawiać słowa wolniej i wyraźniej.',
						'Nie nagrano głosu. Artykułuj lepiej, słowo po słowie.',
					),
					'unrecognized' => array(
						'Nie pasuje do oczekiwanego tłumaczenia.',
						'Nie zawiera słów z tłumaczenia. Spróbuj słowo po słowie.',
						'To wygląda na inne zdanie. Skup się na wymaganym tłumaczeniu.',
					),
					'one'          => array(
						'1 słowo poprawne. Tak trzymaj.',
						'Dobry początek: 1 słowo rozpoznane.',
					),
					'two'          => array(
						'2 słowa poprawne. Idzie ci dobrze.',
						'2 słowa rozpoznane. Kontynuuj.',
					),
					'some'         => array(
						'%1$d słów poprawnych. Prawie gotowe.',
						'%1$d słów rozpoznanych. Jesteś na dobrej drodze.',
					),
					'all'          => array(
						'Wszystkie słowa rozpoznane. Świetna robota.',
						'Pełne tłumaczenie przy mikrofonie. Idealnie.',
					),
				),
			),
			'es' => array(
				'phase1' => array(
					'not_started' => array(
						'El micrófono no se activó. Inténtalo de nuevo.',
						'Sesión no iniciada. Haz clic de nuevo en el micrófono.',
					),
					'silent'      => array(
						'No se detectó texto. Intenta articular las palabras más despacio.',
						'No se registró voz. Pronuncia cada palabra con más claridad.',
					),
					'heard'       => array(
						'Sigue, añade más palabras si quieres.',
						'Bien — prueba otra vez o continúa cuando estés listo.',
						'Sigue practicando con el micrófono.',
						'Puedes añadir más palabras o pulsar Continuar.',
						'Prueba a hablar de nuevo, o sigue adelante.',
					),
				),
				'phase2' => array(
					'not_started'  => array(
						'El micrófono no se activó. Inténtalo de nuevo.',
						'Sesión no iniciada. Haz clic de nuevo en el micrófono.',
					),
					'silent'       => array(
						'No se detectó texto. Intenta articular las palabras más despacio.',
						'No se registró voz. Pronuncia cada palabra con más claridad.',
					),
					'unrecognized' => array(
						'No coincide con la traducción esperada.',
						'No contiene palabras de la traducción. Inténtalo palabra por palabra.',
						'Parece otra frase. Concéntrate en la traducción requerida.',
					),
					'one'          => array(
						'1 palabra correcta. Sigue así.',
						'Buen inicio: 1 palabra reconocida.',
					),
					'two'          => array(
						'2 palabras correctas. Lo estás haciendo bien.',
						'2 palabras reconocidas. Continúa.',
					),
					'some'         => array(
						'%1$d palabras correctas. Casi lo tienes.',
						'%1$d palabras reconocidas. Vas por buen camino.',
					),
					'all'          => array(
						'Todas las palabras reconocidas. Excelente trabajo.',
						'Traducción completa al micrófono. Perfecto.',
					),
				),
			),
		);
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	private static function bundles() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		$names_it = array(
			'en' => 'inglese',
			'it' => 'italiano',
			'pl' => 'polacco',
			'es' => 'spagnolo',
		);
		$names_en = array(
			'en' => 'English',
			'it' => 'Italian',
			'pl' => 'Polish',
			'es' => 'Spanish',
		);
		$names_pl = array(
			'en' => 'angielski',
			'it' => 'włoski',
			'pl' => 'polski',
			'es' => 'hiszpański',
		);
		$names_es = array(
			'en' => 'inglés',
			'it' => 'italiano',
			'pl' => 'polaco',
			'es' => 'español',
		);

		$cache = array(
			'it' => array(
				'lang_names'            => $names_it,
				'story_unavailable'     => 'Storia non disponibile.',
				'no_phrases'            => 'Nessuna frase impostata per questa storia.',
				'story_section_title'   => 'La tua storia (traduzioni completate)',
				'sr_your_translation'   => 'La tua traduzione',
				'continue'              => 'Continua',
				'continue_to_notes'     => 'Vai agli appunti della frase…',
				'bravo_intro'           => 'Bravo! Per questa frase ti consiglio:',
				'label_main'            => 'La traduzione principale consigliata:',
				'peek_target_label'     => 'Dai uno sguardo alla traduzione',
				'peek_target_aria'      => 'Mostra la traduzione principale per 10 secondi',
				'label_phrase_notes'    => 'Note sulla frase:',
				'label_notes'           => 'Note:',
				'notes_edit'            => 'Modifica',
				'notes_edit_notes'      => 'Modifica Note Frase (admin)',
				'notes_edit_grammar'    => 'Modifica Appunti della frase (admin)',
				'notes_edit_alt'        => 'Modifica Appunti frase alternativa (admin)',
				'notes_edit_title'      => 'Modifica appunti',
				'notes_edit_save'       => 'Salva',
				'notes_edit_cancel'     => 'Annulla',
				'notes_edit_saved'      => 'Salvato nel database',
				'notes_edit_error'      => 'Impossibile salvare. Riprova.',
				'label_alt'             => 'Note di approfondimento:',
				'alt_toggle_show'       => 'Mostra note di approfondimento',
				'alt_toggle_hide'       => 'Nascondi note di approfondimento',
				'sr_rewrite'            => 'Riscrivi la frase',
				'done_all'              => 'Hai completato tutte le frasi di questa storia.',
				'translate_prompt'      => 'Traduci la frase in %s',
				'rewrite_prompt'        => 'Step 2 - Ora traduci la frase nel modo corretto! (Usa la traduzione principale consigliata)',
				'input_placeholder_phase1' => 'Traduci la frase in %s',
				'input_placeholder_phase2' => 'Traduci la frase in %s',
				'phase1_fail'           => 'Per favore prova a scrivere qualche parola corretta per andare avanti... prova ad aiutarti con l\'ascolto',
				'phase2_fail'           => 'La frase deve coincidere con la traduzione principale (ignorando punteggiatura e simboli). Riprova.',
			'phase2_complete'       => 'Frase completata.',
			'phase2_story_continue' => 'Bravo! Traduzione corretta. Ottimo lavoro. 1 punto per te! Andiamo avanti con la storia...',
			'phase2_checking'       => 'Verifica in corso…',
			'bravo_correct'         => 'Bravo! Ottimo lavoro! La traduzione è corretta.',
			'phrase_complete_points' => 'Frase completata: +1 punto',
			'mic_used_point'        => 'Microfono utilizzato: +1 punto',
			'mic_used_no_point'     => 'Microfono utilizzato: +0 punti',
			'story_continue'        => 'Andiamo avanti con la storia…',
				'empty_input'           => 'Scrivi qualcosa nell’area di testo.',
				'progress'              => 'Frase %1$d di %2$d',
				'ajax_error'            => 'Errore di rete. Riprova.',
				'invalid_story'         => 'Storia non valida.',
				'phrase_not_found'      => 'Frase non trovata.',
				'bad_request'           => 'Richiesta non valida.',
				'your_phrase_label'     => 'La tua frase:',
				'mic_button'            => 'Attiva microfono',
				'sr_mic'                => 'Attiva il microfono per dettare nel campo di testo.',
				'listen_target_aria'    => 'Ascolta la traduzione in %s (lettura lenta)',
				'listen_target_label'   => 'Ascolta la traduzione',
				'listen_replay_after_mic' => 'Vuoi riascoltare la traduzione dopo ogni sessione microfono?',
				'listen_replay_yes'     => 'Sì',
				'listen_replay_no'      => 'No',
				'story_progress_restart' => 'Ricomincia storia',
				'story_progress_guest'  => 'Accedi per vedere i progressi e ricominciare la storia.',
				'story_progress_confirm' => 'Ricominciare dalla prima frase? Il gioco riparte da capo; le frasi già completate restano salvate (e i coin non cambiano).',
			'story_progress_sr'     => 'Progresso storia: %1$d frasi su %2$d completate',
			'intro_label'           => 'Introduzione:',
			'upcoming_phrases_hint' => 'Qui appariranno le %d frasi man mano che le completi! Buona fortuna.',
			'mic_hint'              => 'Clicca per parlare',
			'mic_pending'           => 'Avvio microfono... Fai un bel respiro :)',
			'mic_listening'         => 'Parla ora, ti ascolto…',
			'mic_grace'             => 'Finisco di ascoltare…',
			'mic_denied'            => 'Microfono non autorizzato. Abilita il microfono nelle impostazioni del browser.',
			'mic_unavailable'       => 'Microfono non disponibile sul dispositivo.',
			'mic_no_audio'          => 'Nessun audio rilevato. Riprova.',
			'clear_input'           => 'Ricomincia da capo',
			'loading_notes'         => 'Carico gli appunti per questa frase',
			'resolve_go_prompt'     => 'Traduci la frase in %s',
			'notes_toggle_show'     => 'Carica gli appunti per questa frase',
			'notes_toggle_hide'     => 'Nascondi gli appunti',
			'resolve_go_fail'       => 'Non è ancora la traduzione corretta. Apri gli appunti qui sotto per rivedere la frase e riprova.',
			'learning_mode_label'   => 'Modalità apprendimento:',
			'learning_mode_change'  => 'Cambia',
			'learning_mode_title'   => 'Scegli la modalità di apprendimento',
			'learning_mode_intro'   => 'Scegli come vuoi allenarti. Puoi cambiare quando vuoi: riprenderai dalla frase in corso.',
			'learning_mode_save'    => 'Conferma e Salva',
			'learning_mode_cancel'  => 'Annulla',
			'learning_mode_close'   => 'Chiudi',
			'learning_mode_saved'   => 'Modalità salvata. Aggiorno la pagina…',
			'learning_mode_error'   => 'Non è stato possibile salvare la modalità. Riprova.',
			'learning_mode_tools_title' => 'Strumenti utili:',
			'mode_loverewrite_label' => 'LoveRewrite',
			'mode_loverewrite_desc' => 'Due fasi per ogni frase: prima provi la tua traduzione, poi leggi gli appunti e riscrivi la frase corretta.',
			'mode_resolve_go_label' => 'Risolvi e vai',
			'mode_resolve_go_desc'  => 'Una sola fase: scrivi la traduzione corretta e passi subito alla frase successiva. Gli appunti li apri tu quando vuoi.',
			'mode_read_go_fast_label' => 'Read and go fast',
			'mode_read_go_fast_desc'  => 'Nessun controllo: leggi la frase, provi la traduzione se vuoi e vai avanti. Ogni frase vale sempre 1 punto.',
			'mode_play_inverted_label' => 'Gioca al contrario',
			'mode_play_inverted_desc'  => 'Vedi la traduzione principale e indovina la frase originale. Avanti sempre, senza microfono né strumenti utili.',
			'play_inverted_prompt'   => 'Traduci la frase in %s',
			'play_inverted_next'     => 'Vai alla prossima frase',
			'play_inverted_hint'     => 'Visualizza traduzione consigliata',
			'play_inverted_hint_hide'=> 'Nascondi traduzione consigliata',
			'play_inverted_target'   => 'La frase originale consigliata per questa traduzione è:',
			'play_inverted_exact'    => 'Bravo, risposta esatta.',
			'play_inverted_almost'   => 'Bravo, quasi. La frase originale consigliata era:',
			'play_inverted_complete' => '+1 punto, passiamo alla frase successiva…',
			'read_go_fast_prompt'   => 'Traduci la frase in %s',
			'read_go_fast_next'     => 'Vai alla prossima frase',
			'read_go_fast_target'   => 'La traduzione principale consigliata per questa frase è:',
			'read_go_fast_saved'    => 'Salvato nel database.',
			'read_go_fast_save_error' => 'Errore: la frase non è stata salvata. Riprova.',
			'read_go_fast_exact'    => 'Bravo, risposta esatta.',
			'read_go_fast_almost'   => 'Bravo, quasi. La traduzione consigliata era:',
			'read_go_fast_complete' => '+1 punto, passiamo alla frase successiva…',
			'option_random_words_label' => 'Aggiungi parole random di aiuto',
			'option_random_words_desc'  => 'Sotto il microfono compare un pulsante che mostra le parole della traduzione mescolate ad altre quasi identiche: clicca quelle giuste per scriverle nel campo di testo.',
			'option_extra_chars_label' => 'Caratteri aggiuntivi',
			'option_extra_chars_desc'  => 'Sotto il microfono compare un accordion con i caratteri speciali della lingua che stai studiando: clicca per inserirli nel testo.',
			'option_listen_replay_loop_label' => 'Riascolta la traduzione in loop',
			'option_listen_replay_loop_desc'  => 'Riascolta la traduzione ogni volta che usi il microfono. Utile per memorizzare la pronuncia.',
			'random_words_show'     => 'Visualizza parole random',
			'random_words_aria'     => 'Parole di aiuto per questa frase',
			'extra_chars_toggle'   => 'Caratteri aggiuntivi',
			'extra_chars_lower'    => 'Minuscole',
			'extra_chars_upper'    => 'Maiuscole',
			'extra_chars_symbols'  => 'Simboli',
			'layout_group'          => 'Disposizione pagina',
			'layout_two'            => 'Due colonne',
			'layout_one'            => 'Una colonna',
		),
		'en' => array(
				'lang_names'            => $names_en,
				'story_unavailable'     => 'Story unavailable.',
				'no_phrases'            => 'No phrases configured for this story.',
				'story_section_title'   => 'Your story (completed translations)',
				'sr_your_translation'   => 'Your translation',
				'continue'              => 'Continue',
				'continue_to_notes'     => 'Go to the sentence notes…',
				'bravo_intro'           => 'Well done! For this phrase we suggest:',
				'label_main'            => 'Recommended main translation:',
				'peek_target_label'     => 'Take a look at the translation',
				'peek_target_aria'      => 'Show the main translation for 10 seconds',
				'label_phrase_notes'    => 'Notes on this sentence:',
				'label_notes'           => 'Notes:',
				'notes_edit'            => 'Edit',
				'notes_edit_notes'      => 'Edit sentence notes (admin)',
				'notes_edit_grammar'    => 'Edit phrase notes (admin)',
				'notes_edit_alt'        => 'Edit alternative notes (admin)',
				'notes_edit_title'      => 'Edit notes',
				'notes_edit_save'       => 'Save',
				'notes_edit_cancel'     => 'Cancel',
				'notes_edit_saved'      => 'Saved to the database',
				'notes_edit_error'      => 'Could not save. Try again.',
				'label_alt'             => 'Further notes:',
				'alt_toggle_show'       => 'Show further notes',
				'alt_toggle_hide'       => 'Hide further notes',
				'sr_rewrite'            => 'Rewrite the sentence',
				'done_all'              => 'You have completed all phrases in this story.',
				'translate_prompt'      => 'Translate the sentence into %s',
				'rewrite_prompt'        => 'PHASE 2 - Now translate the sentence correctly! Compare your answer with the suggested translation in green: it must match word for word. Accents and punctuation are not required. Keep practising until you get it!',
				'input_placeholder_phase1' => 'Translate the sentence into %s',
				'input_placeholder_phase2' => 'Translate the sentence into %s',
				'phase1_fail'           => 'Please try to write at least a few correct words to continue... try listening to the audio for help',
				'phase2_fail'           => 'The sentence must match the main translation (ignoring punctuation and symbols). Try again.',
			'phase2_complete'       => 'Sentence completed.',
			'phase2_story_continue' => 'Great! Correct translation. Excellent work. 1 point for you! Let us continue the story...',
			'phase2_checking'       => 'Checking…',
			'bravo_correct'         => 'Well done! Great job! The translation is correct.',
			'phrase_complete_points' => 'Phrase completed: +1 point',
			'mic_used_point'        => 'Microphone used: +1 point',
			'mic_used_no_point'     => 'Microphone used: +0 points',
			'story_continue'        => 'Let\'s continue the story…',
				'empty_input'           => 'Type something in the text area.',
				'progress'              => 'Phrase %1$d of %2$d',
				'ajax_error'            => 'Network error. Please try again.',
				'invalid_story'         => 'Invalid story.',
				'phrase_not_found'      => 'Phrase not found.',
				'bad_request'           => 'Invalid request.',
				'your_phrase_label'     => 'Your sentence:',
				'mic_button'            => 'Activate microphone',
				'sr_mic'                => 'Activate the microphone to dictate into the text field.',
				'listen_target_aria'    => 'Listen to the translation in %s (slow)',
				'listen_target_label'   => 'Listen to the translation',
				'listen_replay_after_mic' => 'Replay the translation after each microphone session?',
				'listen_replay_yes'     => 'Yes',
				'listen_replay_no'      => 'No',
				'story_progress_restart' => 'Restart story',
				'story_progress_guest'  => 'Log in to see progress and restart the story.',
				'story_progress_confirm' => 'Start again from the first phrase? The game restarts from the beginning; completed phrases stay saved. Your coins will not change.',
			'story_progress_sr'     => 'Story progress: %1$d of %2$d phrases completed',
			'intro_label'           => 'Introduction:',
			'upcoming_phrases_hint' => 'The %d phrases will appear here as you complete them! Good luck.',
			'mic_hint'              => 'Click to speak',
			'mic_pending'           => 'Starting microphone…',
			'mic_listening'         => 'Listening…',
			'mic_grace'             => 'Finishing…',
			'mic_denied'            => 'Microphone not allowed. Enable it in your browser settings.',
			'mic_unavailable'       => 'Microphone not available on this device.',
			'mic_no_audio'          => 'No audio detected. Try again.',
			'clear_input'           => 'Start over',
			'loading_notes'         => 'Loading the notes for this phrase',
			'resolve_go_prompt'     => 'Translate the sentence into %s',
			'notes_toggle_show'     => 'Load the notes for this sentence',
			'notes_toggle_hide'     => 'Hide the notes',
			'resolve_go_fail'       => 'Not the correct translation yet. Open the notes below to review the sentence and try again.',
			'learning_mode_label'   => 'Learning mode:',
			'learning_mode_change'  => 'Change',
			'learning_mode_title'   => 'Choose your learning mode',
			'learning_mode_intro'   => 'Choose how you want to practise. You can change it anytime: you will resume from the current sentence.',
			'learning_mode_save'    => 'Confirm and save',
			'learning_mode_cancel'  => 'Cancel',
			'learning_mode_close'   => 'Close',
			'learning_mode_saved'   => 'Mode saved. Reloading the page…',
			'learning_mode_error'   => 'The mode could not be saved. Please try again.',
			'learning_mode_tools_title' => 'Useful tools:',
			'mode_loverewrite_label' => 'LoveRewrite',
			'mode_loverewrite_desc' => 'Two steps per sentence: first try your own translation, then read the notes and rewrite the correct sentence.',
			'mode_resolve_go_label' => 'Solve and go',
			'mode_resolve_go_desc'  => 'A single step: write the correct translation and move straight to the next sentence. You open the notes whenever you want.',
			'mode_read_go_fast_label' => 'Read and go fast',
			'mode_read_go_fast_desc'  => 'No checks: read the sentence, try the translation if you feel like it, and move on. Every sentence is always worth 1 point.',
			'mode_play_inverted_label' => 'Play inverted',
			'mode_play_inverted_desc'  => 'See the main translation and guess the original sentence. Always move on, with no microphone and no helper tools.',
			'play_inverted_prompt'   => 'Translate the sentence into %s',
			'play_inverted_next'     => 'Go to the next sentence',
			'play_inverted_hint'     => 'Show recommended translation',
			'play_inverted_hint_hide'=> 'Hide recommended translation',
			'play_inverted_target'   => 'The recommended original sentence for this translation is:',
			'play_inverted_exact'    => 'Well done, exact answer.',
			'play_inverted_almost'   => 'Well done, almost. The recommended original sentence was:',
			'play_inverted_complete' => '+1 point, on to the next sentence…',
			'read_go_fast_prompt'   => 'Translate the sentence into %s',
			'read_go_fast_next'     => 'Go to the next sentence',
			'read_go_fast_target'   => 'The recommended main translation for this sentence is:',
			'read_go_fast_saved'    => 'Saved to the database.',
			'read_go_fast_save_error' => 'Error: the sentence was not saved. Please try again.',
			'read_go_fast_exact'    => 'Well done, exact answer.',
			'read_go_fast_almost'   => 'Well done, almost. The recommended translation was:',
			'read_go_fast_complete' => '+1 point, on to the next sentence…',
			'option_random_words_label' => 'Add random helper words',
			'option_random_words_desc'  => 'A button appears under the microphone showing the words of the translation mixed with near-identical ones: click the right ones to write them in the text field.',
			'option_extra_chars_label' => 'Extra characters',
			'option_extra_chars_desc'  => 'An accordion under the microphone shows special characters for the language you are learning: click to insert them into the text.',
			'option_listen_replay_loop_label' => 'Replay the translation in a loop',
			'option_listen_replay_loop_desc'  => 'Replay the translation every time you use the microphone. Useful for memorising pronunciation.',
			'random_words_show'     => 'Show random words',
			'random_words_aria'     => 'Helper words for this sentence',
			'extra_chars_toggle'   => 'Extra characters',
			'extra_chars_lower'    => 'Lowercase',
			'extra_chars_upper'    => 'Uppercase',
			'extra_chars_symbols'  => 'Symbols',
			'layout_group'          => 'Page layout',
			'layout_two'            => 'Two columns',
			'layout_one'            => 'One column',
		),
		'pl' => array(
				'lang_names'            => $names_pl,
				'story_unavailable'     => 'Opowieść jest niedostępna.',
				'no_phrases'            => 'Brak zdań skonfigurowanych dla tej opowieści.',
				'story_section_title'   => 'Twoja historia (ukończone tłumaczenia)',
				'sr_your_translation'   => 'Twoje tłumaczenie',
				'continue'              => 'Dalej',
				'continue_to_notes'     => 'Przejdź do notatek do zdania…',
				'bravo_intro'           => 'Brawo! Dla tej frazy polecamy:',
				'label_main'            => 'Zalecane główne tłumaczenie:',
				'peek_target_label'     => 'Spójrz na tłumaczenie',
				'peek_target_aria'      => 'Pokaż główne tłumaczenie na 10 sekund',
				'label_phrase_notes'    => 'Notatki do zdania:',
				'label_notes'           => 'Notatki:',
				'notes_edit'            => 'Edytuj',
				'notes_edit_notes'      => 'Edytuj notatki zdania (admin)',
				'notes_edit_grammar'    => 'Edytuj notatki do zdania (admin)',
				'notes_edit_alt'        => 'Edytuj notatki alternatywne (admin)',
				'notes_edit_title'      => 'Edytuj notatki',
				'notes_edit_save'       => 'Zapisz',
				'notes_edit_cancel'     => 'Anuluj',
				'notes_edit_saved'      => 'Zapisano w bazie danych',
				'notes_edit_error'      => 'Nie udało się zapisać. Spróbuj ponownie.',
				'label_alt'             => 'Notatki uzupełniające:',
				'alt_toggle_show'       => 'Pokaż notatki uzupełniające',
				'alt_toggle_hide'       => 'Ukryj notatki uzupełniające',
				'sr_rewrite'            => 'Przepisz zdanie',
				'done_all'              => 'Ukończyłeś wszystkie zdania tej opowieści.',
				'translate_prompt'      => 'Przetłumacz zdanie na %s',
				'rewrite_prompt'        => 'FAZA 2 - Teraz przetłumacz zdanie poprawnie! Porównaj swoją odpowiedź z sugerowanym tłumaczeniem na zielono: musi się zgadzać słowo po słowie. Akcenty i interpunkcja nie są wymagane. Ćwicz, aż Ci się uda!',
				'input_placeholder_phase1' => 'Przetłumacz zdanie na %s',
				'input_placeholder_phase2' => 'Przetłumacz zdanie na %s',
				'phase1_fail'           => 'Spróbuj napisać kilka poprawnych słów, żeby przejść dalej... posłuchaj nagrania, żeby się podpowiedzieć',
				'phase2_fail'           => 'Zdanie musi być zgodne z głównym tłumaczeniem (ignorując interpunkcję i symbole). Spróbuj ponownie.',
			'phase2_complete'       => 'Zdanie ukończone.',
			'phase2_story_continue' => 'Brawo! Poprawne tlumaczenie. Swietna robota. 1 punkt dla Ciebie! Kontynuujmy historie...',
			'phase2_checking'       => 'Sprawdzanie…',
			'bravo_correct'         => 'Brawo! Swietna robota! Tlumaczenie jest poprawne.',
			'phrase_complete_points' => 'Zdanie ukonczone: +1 punkt',
			'mic_used_point'        => 'Mikrofon uzyty: +1 punkt',
			'mic_used_no_point'     => 'Mikrofon uzyty: +0 punktow',
			'story_continue'        => 'Kontynuujmy historie…',
				'empty_input'           => 'Wpisz coś w polu tekstowym.',
				'progress'              => 'Zdanie %1$d z %2$d',
				'ajax_error'            => 'Błąd sieci. Spróbuj ponownie.',
				'invalid_story'         => 'Nieprawidłowa opowieść.',
				'phrase_not_found'      => 'Nie znaleziono zdania.',
				'bad_request'           => 'Nieprawidłowe żądanie.',
				'your_phrase_label'     => 'Twoje zdanie:',
				'mic_button'            => 'Aktywuj mikrofon',
				'sr_mic'                => 'Aktywuj mikrofon, aby dyktować w polu tekstowym.',
				'listen_target_aria'    => 'Posłuchaj tłumaczenia po %s (wolno)',
				'listen_target_label'   => 'Posłuchaj tłumaczenia',
				'listen_replay_after_mic' => 'Odtworzyć tłumaczenie po każdej sesji mikrofonu?',
				'listen_replay_yes'     => 'Tak',
				'listen_replay_no'      => 'Nie',
				'story_progress_restart' => 'Zacznij od nowa',
				'story_progress_guest'  => 'Zaloguj się, aby zobaczyć postęp i zacząć opowieść od nowa.',
				'story_progress_confirm' => 'Zacząć od pierwszego zdania? Gra wraca na początek; ukończone zdania pozostają zapisane. Monety się nie zmienią.',
			'story_progress_sr'     => 'Postęp: ukończono %1$d z %2$d zdań',
			'intro_label'           => 'Wstęp:',
			'upcoming_phrases_hint' => 'Tutaj pojawią się %d zdania, w miarę jak je uzupełnisz! Powodzenia.',
			'mic_hint'              => 'Kliknij, aby mówić',
			'mic_pending'           => 'Uruchamiam mikrofon…',
			'mic_listening'         => 'Słucham…',
			'mic_grace'             => 'Kończę nagrywanie…',
			'mic_denied'            => 'Brak dostępu do mikrofonu. Włącz go w ustawieniach przeglądarki.',
			'mic_unavailable'       => 'Mikrofon niedostępny na tym urządzeniu.',
			'mic_no_audio'          => 'Nie wykryto dźwięku. Spróbuj ponownie.',
			'clear_input'           => 'Zacznij od nowa',
			'loading_notes'         => 'Ładuję notatki do tego zdania',
			'resolve_go_prompt'     => 'Przetłumacz zdanie na %s',
			'notes_toggle_show'     => 'Wczytaj notatki do tego zdania',
			'notes_toggle_hide'     => 'Ukryj notatki',
			'resolve_go_fail'       => 'To jeszcze nie jest poprawne tłumaczenie. Otwórz notatki poniżej, przejrzyj zdanie i spróbuj ponownie.',
			'learning_mode_label'   => 'Tryb nauki:',
			'learning_mode_change'  => 'Zmień',
			'learning_mode_title'   => 'Wybierz tryb nauki',
			'learning_mode_intro'   => 'Wybierz sposób ćwiczenia. Możesz to zmienić w każdej chwili: wrócisz do bieżącego zdania.',
			'learning_mode_save'    => 'Potwierdź i zapisz',
			'learning_mode_cancel'  => 'Anuluj',
			'learning_mode_close'   => 'Zamknij',
			'learning_mode_saved'   => 'Tryb zapisany. Odświeżam stronę…',
			'learning_mode_error'   => 'Nie udało się zapisać trybu. Spróbuj ponownie.',
			'learning_mode_tools_title' => 'Przydatne narzędzia:',
			'mode_loverewrite_label' => 'LoveRewrite',
			'mode_loverewrite_desc' => 'Dwa etapy na zdanie: najpierw próbujesz własnego tłumaczenia, potem czytasz notatki i zapisujesz poprawne zdanie.',
			'mode_resolve_go_label' => 'Rozwiąż i dalej',
			'mode_resolve_go_desc'  => 'Jeden etap: wpisujesz poprawne tłumaczenie i od razu przechodzisz do następnego zdania. Notatki otwierasz, kiedy chcesz.',
			'mode_read_go_fast_label' => 'Read and go fast',
			'mode_read_go_fast_desc'  => 'Bez sprawdzania: czytasz zdanie, próbujesz tłumaczenia, jeśli chcesz, i idziesz dalej. Każde zdanie zawsze daje 1 punkt.',
			'mode_play_inverted_label' => 'Graj odwrotnie',
			'mode_play_inverted_desc'  => 'Widzisz główne tłumaczenie i odgadujesz oryginalne zdanie. Zawsze idziesz dalej, bez mikrofonu i bez narzędzi pomocniczych.',
			'play_inverted_prompt'   => 'Przetłumacz zdanie na %s',
			'play_inverted_next'     => 'Przejdź do następnego zdania',
			'play_inverted_hint'     => 'Pokaż zalecane tłumaczenie',
			'play_inverted_hint_hide'=> 'Ukryj zalecane tłumaczenie',
			'play_inverted_target'   => 'Zalecane oryginalne zdanie dla tego tłumaczenia to:',
			'play_inverted_exact'    => 'Brawo, dokładna odpowiedź.',
			'play_inverted_almost'   => 'Brawo, prawie. Zalecane oryginalne zdanie brzmiało:',
			'play_inverted_complete' => '+1 punkt, przechodzimy do następnego zdania…',
			'read_go_fast_prompt'   => 'Przetłumacz zdanie na %s',
			'read_go_fast_next'     => 'Przejdź do następnego zdania',
			'read_go_fast_target'   => 'Zalecane główne tłumaczenie tego zdania to:',
			'read_go_fast_saved'    => 'Zapisano w bazie danych.',
			'read_go_fast_save_error' => 'Błąd: zdanie nie zostało zapisane. Spróbuj ponownie.',
			'read_go_fast_exact'    => 'Brawo, dokładna odpowiedź.',
			'read_go_fast_almost'   => 'Brawo, prawie. Zalecane tłumaczenie brzmiało:',
			'read_go_fast_complete' => '+1 punkt, przechodzimy do następnego zdania…',
			'option_random_words_label' => 'Dodaj losowe słowa pomocnicze',
			'option_random_words_desc'  => 'Pod mikrofonem pojawia się przycisk pokazujący słowa tłumaczenia wymieszane z niemal identycznymi: kliknij właściwe, aby wpisać je w pole tekstowe.',
			'option_extra_chars_label' => 'Dodatkowe znaki',
			'option_extra_chars_desc'  => 'Pod mikrofonem pojawia się accordion ze znakami specjalnymi języka, którego się uczysz: kliknij, aby wstawić je do tekstu.',
			'option_listen_replay_loop_label' => 'Odtwarzaj tłumaczenie w pętli',
			'option_listen_replay_loop_desc'  => 'Odtwarzaj tłumaczenie za każdym razem, gdy użyjesz mikrofonu. Przydatne do zapamiętania wymowy.',
			'random_words_show'     => 'Pokaż losowe słowa',
			'random_words_aria'     => 'Słowa pomocnicze do tego zdania',
			'extra_chars_toggle'   => 'Dodatkowe znaki',
			'extra_chars_lower'    => 'Minuskuły',
			'extra_chars_upper'    => 'Wersaliki',
			'extra_chars_symbols'  => 'Symbole',
			'layout_group'          => 'Układ strony',
			'layout_two'            => 'Dwie kolumny',
			'layout_one'            => 'Jedna kolumna',
		),
		'es' => array(
				'lang_names'            => $names_es,
				'story_unavailable'     => 'Historia no disponible.',
				'no_phrases'            => 'No hay frases configuradas para esta historia.',
				'story_section_title'   => 'Tu historia (traducciones completadas)',
				'sr_your_translation'   => 'Tu traducción',
				'continue'              => 'Continuar',
				'continue_to_notes'     => 'Ir a las notas de la frase…',
				'bravo_intro'           => '¡Bien hecho! Para esta frase te recomendamos:',
				'label_main'            => 'Traducción principal recomendada:',
				'peek_target_label'     => 'Echa un vistazo a la traducción',
				'peek_target_aria'      => 'Mostrar la traducción principal durante 10 segundos',
				'label_phrase_notes'    => 'Notas sobre la frase:',
				'label_notes'           => 'Notas:',
				'notes_edit'            => 'Editar',
				'notes_edit_notes'      => 'Editar notas de la frase (admin)',
				'notes_edit_grammar'    => 'Editar apuntes de la frase (admin)',
				'notes_edit_alt'        => 'Editar apuntes de la alternativa (admin)',
				'notes_edit_title'      => 'Editar notas',
				'notes_edit_save'       => 'Guardar',
				'notes_edit_cancel'     => 'Cancelar',
				'notes_edit_saved'      => 'Guardado en la base de datos',
				'notes_edit_error'      => 'No se pudo guardar. Inténtalo de nuevo.',
				'label_alt'             => 'Notas de profundización:',
				'alt_toggle_show'       => 'Mostrar notas de profundización',
				'alt_toggle_hide'       => 'Ocultar notas de profundización',
				'sr_rewrite'            => 'Reescribe la frase',
				'done_all'              => 'Has completado todas las frases de esta historia.',
				'translate_prompt'      => 'Traduce la frase al %s',
				'rewrite_prompt'        => 'FASE 2 - ¡Ahora traduce la frase correctamente! Compara tu respuesta con la traducción sugerida en verde: debe coincidir palabra por palabra. Los acentos y la puntuación no son obligatorios. ¡Sigue practicando hasta conseguirlo!',
				'input_placeholder_phase1' => 'Traduce la frase al %s',
				'input_placeholder_phase2' => 'Traduce la frase al %s',
				'phase1_fail'           => 'Por favor intenta escribir algunas palabras correctas para continuar... escucha el audio para ayudarte',
				'phase2_fail'           => 'La frase debe coincidir con la traducción principal (ignorando puntuación y símbolos). Inténtalo de nuevo.',
			'phase2_complete'       => 'Frase completada.',
			'phase2_story_continue' => '¡Bien hecho! Traduccion correcta. Excelente trabajo. ¡1 punto para ti! Continuemos la historia...',
			'phase2_checking'       => 'Verificando…',
			'bravo_correct'         => '¡Bien hecho! ¡Excelente trabajo! La traducción es correcta.',
			'phrase_complete_points' => 'Frase completada: +1 punto',
			'mic_used_point'        => 'Micrófono utilizado: +1 punto',
			'mic_used_no_point'     => 'Micrófono utilizado: +0 puntos',
			'story_continue'        => 'Continuemos la historia…',
				'empty_input'           => 'Escribe algo en el cuadro de texto.',
				'progress'              => 'Frase %1$d de %2$d',
				'ajax_error'            => 'Error de red. Vuelve a intentarlo.',
				'invalid_story'         => 'Historia no válida.',
				'phrase_not_found'      => 'Frase no encontrada.',
				'bad_request'           => 'Solicitud no válida.',
				'your_phrase_label'     => 'Tu frase:',
				'mic_button'            => 'Activar micrófono',
				'sr_mic'                => 'Activa el micrófono para dictar en el cuadro de texto.',
				'listen_target_aria'    => 'Escucha la traducción en %s (lento)',
				'listen_target_label'   => 'Escucha la traducción',
				'listen_replay_after_mic' => '¿Reescuchar la traducción tras cada sesión de micrófono?',
				'listen_replay_yes'     => 'Sí',
				'listen_replay_no'      => 'No',
				'story_progress_restart' => 'Reiniciar historia',
				'story_progress_guest'  => 'Inicia sesión para ver el progreso y reiniciar la historia.',
				'story_progress_confirm' => '¿Volver a la primera frase? El juego empieza de nuevo; las frases completadas siguen guardadas. Las monedas no cambian.',
			'story_progress_sr'     => 'Progreso: %1$d de %2$d frases completadas',
			'intro_label'           => 'Introducción:',
			'upcoming_phrases_hint' => '¡Aquí aparecerán las %d frases a medida que las completes! Buena suerte.',
			'mic_hint'              => 'Haz clic para hablar',
			'mic_pending'           => 'Iniciando micrófono…',
			'mic_listening'         => 'Escuchando…',
			'mic_grace'             => 'Terminando…',
			'mic_denied'            => 'Micrófono no autorizado. Actívalo en la configuración del navegador.',
			'mic_unavailable'       => 'Micrófono no disponible en este dispositivo.',
			'mic_no_audio'          => 'No se detectó audio. Inténtalo de nuevo.',
			'clear_input'           => 'Empezar de nuevo',
			'loading_notes'         => 'Cargando las notas de esta frase',
			'resolve_go_prompt'     => 'Traduce la frase al %s',
			'notes_toggle_show'     => 'Carga las notas de esta frase',
			'notes_toggle_hide'     => 'Ocultar las notas',
			'resolve_go_fail'       => 'Todavía no es la traducción correcta. Abre las notas de abajo para repasar la frase e inténtalo de nuevo.',
			'learning_mode_label'   => 'Modo de aprendizaje:',
			'learning_mode_change'  => 'Cambiar',
			'learning_mode_title'   => 'Elige tu modo de aprendizaje',
			'learning_mode_intro'   => 'Elige cómo quieres practicar. Puedes cambiarlo cuando quieras: retomarás desde la frase actual.',
			'learning_mode_save'    => 'Confirmar y guardar',
			'learning_mode_cancel'  => 'Cancelar',
			'learning_mode_close'   => 'Cerrar',
			'learning_mode_saved'   => 'Modo guardado. Actualizando la página…',
			'learning_mode_error'   => 'No se ha podido guardar el modo. Inténtalo de nuevo.',
			'learning_mode_tools_title' => 'Herramientas útiles:',
			'mode_loverewrite_label' => 'LoveRewrite',
			'mode_loverewrite_desc' => 'Dos fases por frase: primero pruebas tu traducción y luego lees las notas y reescribes la frase correcta.',
			'mode_resolve_go_label' => 'Resuelve y sigue',
			'mode_resolve_go_desc'  => 'Una sola fase: escribes la traducción correcta y pasas directamente a la siguiente frase. Las notas las abres cuando quieras.',
			'mode_read_go_fast_label' => 'Read and go fast',
			'mode_read_go_fast_desc'  => 'Sin comprobaciones: lees la frase, pruebas la traducción si quieres y sigues adelante. Cada frase vale siempre 1 punto.',
			'mode_play_inverted_label' => 'Jugar al revés',
			'mode_play_inverted_desc'  => 'Ves la traducción principal y adivinas la frase original. Avanzas siempre, sin micrófono ni herramientas útiles.',
			'play_inverted_prompt'   => 'Traduce la frase al %s',
			'play_inverted_next'     => 'Ir a la siguiente frase',
			'play_inverted_hint'     => 'Ver traducción recomendada',
			'play_inverted_hint_hide'=> 'Ocultar traducción recomendada',
			'play_inverted_target'   => 'La frase original recomendada para esta traducción es:',
			'play_inverted_exact'    => 'Bien hecho, respuesta exacta.',
			'play_inverted_almost'   => 'Bien hecho, casi. La frase original recomendada era:',
			'play_inverted_complete' => '+1 punto, pasamos a la siguiente frase…',
			'read_go_fast_prompt'   => 'Traduce la frase al %s',
			'read_go_fast_next'     => 'Ir a la siguiente frase',
			'read_go_fast_target'   => 'La traducción principal recomendada para esta frase es:',
			'read_go_fast_saved'    => 'Guardado en la base de datos.',
			'read_go_fast_save_error' => 'Error: la frase no se ha guardado. Inténtalo de nuevo.',
			'read_go_fast_exact'    => 'Bien hecho, respuesta exacta.',
			'read_go_fast_almost'   => 'Bien hecho, casi. La traducción recomendada era:',
			'read_go_fast_complete' => '+1 punto, pasamos a la siguiente frase…',
			'option_random_words_label' => 'Añadir palabras aleatorias de ayuda',
			'option_random_words_desc'  => 'Bajo el micrófono aparece un botón que muestra las palabras de la traducción mezcladas con otras casi idénticas: pulsa las correctas para escribirlas en el campo de texto.',
			'option_extra_chars_label' => 'Caracteres adicionales',
			'option_extra_chars_desc'  => 'Bajo el micrófono aparece un acordeón con los caracteres especiales del idioma que estás estudiando: pulsa para insertarlos en el texto.',
			'option_listen_replay_loop_label' => 'Reescuchar la traducción en bucle',
			'option_listen_replay_loop_desc'  => 'Reescucha la traducción cada vez que uses el micrófono. Útil para memorizar la pronunciación.',
			'random_words_show'     => 'Ver palabras aleatorias',
			'random_words_aria'     => 'Palabras de ayuda para esta frase',
			'extra_chars_toggle'   => 'Caracteres adicionales',
			'extra_chars_lower'    => 'Minúsculas',
			'extra_chars_upper'    => 'Mayúsculas',
			'extra_chars_symbols'  => 'Símbolos',
			'layout_group'          => 'Disposición de la página',
			'layout_two'            => 'Dos columnas',
			'layout_one'            => 'Una columna',
		),
	);

		return $cache;
	}
}
