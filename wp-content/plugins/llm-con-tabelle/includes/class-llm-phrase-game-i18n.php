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
		$code = '';
		if ( is_user_logged_in() ) {
			$code = (string) get_user_meta( get_current_user_id(), LLM_User_Meta::INTERFACE_LANG, true );
		}
		if ( ! LLM_Languages::is_valid( $code ) ) {
			$code = (string) apply_filters( 'llm_phrase_game_guest_ui_lang', 'it' );
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
				'bravo_intro'           => 'Bravo! Per questa frase ti consiglio:',
				'label_main'            => 'La traduzione principale consigliata corretta:',
				'label_alt'             => 'La traduzione alternativa:',
				'sr_rewrite'            => 'Riscrivi la frase',
				'done_all'              => 'Hai completato tutte le frasi di questa storia.',
				'translate_prompt'      => 'Traduci o pronuncia questa frase in %s:',
				'rewrite_prompt'        => 'Ora scrivi o pronuncia correttamente la frase:',
				'phase1_fail'           => 'Non bastano ancora parole corrette: prova a usare almeno il 20% delle parole della traduzione attesa (anche in ordine diverso).',
				'phase2_fail'           => 'La frase deve coincidere con la traduzione principale (ignorando punteggiatura e simboli). Riprova.',
				'phase2_complete'       => 'Frase completata.',
				'phase2_story_continue' => 'Bravo! Traduzione corretta. Ottimo lavoro. 1 punto per te! Andiamo avanti con la storia...',
				'phase2_checking'       => 'Verifica in corso…',
				'empty_input'           => 'Scrivi qualcosa nell’area di testo.',
				'progress'              => 'Frase %1$d di %2$d',
				'ajax_error'            => 'Errore di rete. Riprova.',
				'invalid_story'         => 'Storia non valida.',
				'phrase_not_found'      => 'Frase non trovata.',
				'bad_request'           => 'Richiesta non valida.',
				'your_phrase_label'     => 'La tua frase:',
				'mic_button'            => 'Pronuncia la frase in %s',
				'sr_mic'                => 'Tieni premuto per dettare nel campo di testo.',
				'listen_target_aria'    => 'Ascolta la traduzione in %s (lettura lenta)',
				'story_progress_restart' => 'Ricomincia storia',
				'story_progress_guest'  => 'Accedi per vedere i progressi e ricominciare la storia.',
				'story_progress_confirm' => 'Ricominciare dalla prima frase? Il gioco riparte da capo; le frasi già completate restano salvate (e i coin non cambiano).',
				'story_progress_sr'     => 'Progresso storia: %1$d frasi su %2$d completate',
			),
			'en' => array(
				'lang_names'            => $names_en,
				'story_unavailable'     => 'Story unavailable.',
				'no_phrases'            => 'No phrases configured for this story.',
				'story_section_title'   => 'Your story (completed translations)',
				'sr_your_translation'   => 'Your translation',
				'continue'              => 'Continue',
				'bravo_intro'           => 'Well done! For this phrase we suggest:',
				'label_main'            => 'Recommended correct translation:',
				'label_alt'             => 'Alternative translation:',
				'sr_rewrite'            => 'Rewrite the sentence',
				'done_all'              => 'You have completed all phrases in this story.',
				'translate_prompt'      => 'Translate or say this phrase in %s:',
				'rewrite_prompt'        => 'Now write or say the sentence correctly:',
				'phase1_fail'           => 'Not enough matching words yet: try to use at least 20% of the words from the expected translation (order can differ).',
				'phase2_fail'           => 'The sentence must match the main translation (ignoring punctuation and symbols). Try again.',
				'phase2_complete'       => 'Sentence completed.',
				'phase2_story_continue' => 'Great! Correct translation. Excellent work. 1 point for you! Let us continue the story...',
				'phase2_checking'       => 'Checking…',
				'empty_input'           => 'Type something in the text area.',
				'progress'              => 'Phrase %1$d of %2$d',
				'ajax_error'            => 'Network error. Please try again.',
				'invalid_story'         => 'Invalid story.',
				'phrase_not_found'      => 'Phrase not found.',
				'bad_request'           => 'Invalid request.',
				'your_phrase_label'     => 'Your sentence:',
				'mic_button'            => 'Say the sentence in %s',
				'sr_mic'                => 'Hold to dictate into the text field.',
				'listen_target_aria'    => 'Listen to the translation in %s (slow)',
				'story_progress_restart' => 'Restart story',
				'story_progress_guest'  => 'Log in to see progress and restart the story.',
				'story_progress_confirm' => 'Start again from the first phrase? The game restarts from the beginning; completed phrases stay saved. Your coins will not change.',
				'story_progress_sr'     => 'Story progress: %1$d of %2$d phrases completed',
			),
			'pl' => array(
				'lang_names'            => $names_pl,
				'story_unavailable'     => 'Opowieść jest niedostępna.',
				'no_phrases'            => 'Brak zdań skonfigurowanych dla tej opowieści.',
				'story_section_title'   => 'Twoja historia (ukończone tłumaczenia)',
				'sr_your_translation'   => 'Twoje tłumaczenie',
				'continue'              => 'Dalej',
				'bravo_intro'           => 'Brawo! Dla tej frazy polecamy:',
				'label_main'            => 'Zalecane poprawne tłumaczenie:',
				'label_alt'             => 'Tłumaczenie alternatywne:',
				'sr_rewrite'            => 'Przepisz zdanie',
				'done_all'              => 'Ukończyłeś wszystkie zdania tej opowieści.',
				'translate_prompt'      => 'Przetłumacz lub wypowiedz to zdanie po %s:',
				'rewrite_prompt'        => 'Teraz napisz lub wypowiedz zdanie poprawnie:',
				'phase1_fail'           => 'Za mało trafnych słów: spróbuj użyć co najmniej 20% słów z oczekiwanego tłumaczenia (kolejność może być inna).',
				'phase2_fail'           => 'Zdanie musi być zgodne z głównym tłumaczeniem (ignorując interpunkcję i symbole). Spróbuj ponownie.',
				'phase2_complete'       => 'Zdanie ukończone.',
				'phase2_story_continue' => 'Brawo! Poprawne tlumaczenie. Swietna robota. 1 punkt dla Ciebie! Kontynuujmy historie...',
				'phase2_checking'       => 'Sprawdzanie…',
				'empty_input'           => 'Wpisz coś w polu tekstowym.',
				'progress'              => 'Zdanie %1$d z %2$d',
				'ajax_error'            => 'Błąd sieci. Spróbuj ponownie.',
				'invalid_story'         => 'Nieprawidłowa opowieść.',
				'phrase_not_found'      => 'Nie znaleziono zdania.',
				'bad_request'           => 'Nieprawidłowe żądanie.',
				'your_phrase_label'     => 'Twoje zdanie:',
				'mic_button'            => 'Wypowiedz zdanie po %s',
				'sr_mic'                => 'Przytrzymaj, aby dyktować w polu tekstowym.',
				'listen_target_aria'    => 'Posłuchaj tłumaczenia po %s (wolno)',
				'story_progress_restart' => 'Zacznij od nowa',
				'story_progress_guest'  => 'Zaloguj się, aby zobaczyć postęp i zacząć opowieść od nowa.',
				'story_progress_confirm' => 'Zacząć od pierwszego zdania? Gra wraca na początek; ukończone zdania pozostają zapisane. Monety się nie zmienią.',
				'story_progress_sr'     => 'Postęp: ukończono %1$d z %2$d zdań',
			),
			'es' => array(
				'lang_names'            => $names_es,
				'story_unavailable'     => 'Historia no disponible.',
				'no_phrases'            => 'No hay frases configuradas para esta historia.',
				'story_section_title'   => 'Tu historia (traducciones completadas)',
				'sr_your_translation'   => 'Tu traducción',
				'continue'              => 'Continuar',
				'bravo_intro'           => '¡Bien hecho! Para esta frase te recomendamos:',
				'label_main'            => 'Traducción correcta recomendada:',
				'label_alt'             => 'Traducción alternativa:',
				'sr_rewrite'            => 'Reescribe la frase',
				'done_all'              => 'Has completado todas las frases de esta historia.',
				'translate_prompt'      => 'Traduce o di esta frase en %s:',
				'rewrite_prompt'        => 'Ahora escribe o di la frase correctamente:',
				'phase1_fail'           => 'Aún no hay suficientes palabras correctas: intenta usar al menos el 20% de las palabras de la traducción esperada (el orden puede variar).',
				'phase2_fail'           => 'La frase debe coincidir con la traducción principal (ignorando puntuación y símbolos). Inténtalo de nuevo.',
				'phase2_complete'       => 'Frase completada.',
				'phase2_story_continue' => '¡Bien hecho! Traduccion correcta. Excelente trabajo. ¡1 punto para ti! Continuemos la historia...',
				'phase2_checking'       => 'Verificando…',
				'empty_input'           => 'Escribe algo en el cuadro de texto.',
				'progress'              => 'Frase %1$d de %2$d',
				'ajax_error'            => 'Error de red. Vuelve a intentarlo.',
				'invalid_story'         => 'Historia no válida.',
				'phrase_not_found'      => 'Frase no encontrada.',
				'bad_request'           => 'Solicitud no válida.',
				'your_phrase_label'     => 'Tu frase:',
				'mic_button'            => 'Pronuncia la frase en %s',
				'sr_mic'                => 'Mantén pulsado para dictar en el cuadro de texto.',
				'listen_target_aria'    => 'Escucha la traducción en %s (lento)',
				'story_progress_restart' => 'Reiniciar historia',
				'story_progress_guest'  => 'Inicia sesión para ver el progreso y reiniciar la historia.',
				'story_progress_confirm' => '¿Volver a la primera frase? El juego empieza de nuevo; las frases completadas siguen guardadas. Las monedas no cambian.',
				'story_progress_sr'     => 'Progreso: %1$d de %2$d frases completadas',
			),
		);

		return $cache;
	}
}
