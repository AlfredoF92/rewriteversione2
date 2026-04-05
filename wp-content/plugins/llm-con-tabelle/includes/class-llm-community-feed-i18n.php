<?php
/**
 * Testi del feed Community nella lingua interfaccia del visitatore (come il gioco frasi).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_Community_Feed_I18n
 */
class LLM_Community_Feed_I18n {

	/**
	 * Codice UI visitatore: it|en|pl|es (stessa logica di LLM_Phrase_Game_I18n).
	 *
	 * @return string
	 */
	public static function lang() {
		$code = LLM_Phrase_Game_I18n::lang();
		return (string) apply_filters( 'llm_community_feed_ui_lang', $code );
	}

	/**
	 * @param string $key Chiave.
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
	 * @param string   $key Chiave con segnaposto.
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

		$cache = array(
			'it' => array(
				'feed_title_community'     => 'Community',
				'feed_empty'               => 'Nessuna attività ancora. Completando frasi o sbloccando storie compariranno qui.',
				'feed_load_more'           => 'Carica altro',
				'feed_loading'             => 'Caricamento…',
				'feed_error'               => 'Operazione non riuscita. Riprova.',
				'feed_bravo'               => 'Bravo!',
				'feed_bravo_aria'          => 'Bravo! — %d ricevuti',
				'feed_login_bravo_hint'    => 'Accedi per lasciare un Bravo.',
				'feed_cannot_bravo'        => 'Non puoi mettere Bravo a questa attività (es. è tua).',
				'feed_bravo_received_aria' => 'Bravi ricevuti',
				'feed_ajax_login_bravo'    => 'Accedi per mettere o togliere un Bravo.',
				'feed_ajax_invalid'        => 'Richiesta non valida.',
				'feed_ajax_not_found'      => 'Attività non trovata.',
				'feed_user_num'            => 'Utente #%d',
				'feed_completed_phrase_mid' => 'ha completato la frase %d nella storia ',
				'feed_completed_story_mid'  => 'ha completato la storia ',
				'feed_unlocked_story_mid'   => 'ha sbloccato la storia ',
				'feed_generic_story_mid'    => "ha un'attività sulla storia ",
				'feed_lang_row'             => 'Lingue: %1$s → %2$s',
				'feed_lang_unknown'         => '—',
				'feed_motivate_caption'     => 'Motiva chi studia',
				'feed_motivate_own_caption' => 'I tuoi Bravi',
				'feed_motivate_guest_caption' => 'Motiva con un Bravo',
				'bravo_balance_sent'          => 'Bravi che hai inviato',
				'bravo_balance_received'      => 'Bravi che hai ricevuto',
				'bravo_balance_tip'           => 'Cerca di motivare anche gli altri: bilanciare i Bravi che dai con quelli che ricevi rende la community più equa e incoraggiante.',
				'bravo_balance_guest'         => 'Accedi per vedere il bilancio dei tuoi Bravi.',
				'bravo_balance_login'         => 'Accedi',
			),
			'en' => array(
				'feed_title_community'     => 'Community',
				'feed_empty'               => 'No activity yet. Complete phrases or unlock stories to see them here.',
				'feed_load_more'           => 'Load more',
				'feed_loading'             => 'Loading…',
				'feed_error'               => 'Something went wrong. Please try again.',
				'feed_bravo'               => 'Well done!',
				'feed_bravo_aria'          => 'Well done! — %d received',
				'feed_login_bravo_hint'    => 'Log in to leave a “well done”.',
				'feed_cannot_bravo'        => 'You cannot add a “well done” to this activity (e.g. it is yours).',
				'feed_bravo_received_aria' => '“Well done” received',
				'feed_ajax_login_bravo'    => 'Log in to add or remove a “well done”.',
				'feed_ajax_invalid'        => 'Invalid request.',
				'feed_ajax_not_found'      => 'Activity not found.',
				'feed_user_num'            => 'User #%d',
				'feed_completed_phrase_mid' => 'completed phrase %d in the story ',
				'feed_completed_story_mid'  => 'completed the story ',
				'feed_unlocked_story_mid'   => 'unlocked the story ',
				'feed_generic_story_mid'    => 'has activity on the story ',
				'feed_lang_row'             => 'Languages: %1$s → %2$s',
				'feed_lang_unknown'         => '—',
				'feed_motivate_caption'     => 'Encourage the learner',
				'feed_motivate_own_caption' => 'Your cheers',
				'feed_motivate_guest_caption' => 'Sign in to cheer them on',
				'bravo_balance_sent'          => 'Bravos you have given',
				'bravo_balance_received'      => 'Bravos you have received',
				'bravo_balance_tip'           => 'Cheer others on when you can—balancing bravos you give with those you receive keeps the community fair and encouraging for everyone.',
				'bravo_balance_guest'         => 'Log in to see your bravo balance.',
				'bravo_balance_login'         => 'Log in',
			),
			'pl' => array(
				'feed_title_community'     => 'Społeczność',
				'feed_empty'               => 'Brak aktywności. Ukończ frazy lub odblokuj opowieści, aby pojawiły się tutaj.',
				'feed_load_more'           => 'Wczytaj więcej',
				'feed_loading'             => 'Wczytywanie…',
				'feed_error'               => 'Operacja nie powiodła się. Spróbuj ponownie.',
				'feed_bravo'               => 'Brawo!',
				'feed_bravo_aria'          => 'Brawo! — %d otrzymanych',
				'feed_login_bravo_hint'    => 'Zaloguj się, aby zostawić „brawo”.',
				'feed_cannot_bravo'        => 'Nie możesz dodać „brawo” do tej aktywności (np. to Twoje).',
				'feed_bravo_received_aria' => 'Otrzymane „brawo”',
				'feed_ajax_login_bravo'    => 'Zaloguj się, aby dodać lub usunąć „brawo”.',
				'feed_ajax_invalid'        => 'Nieprawidłowe żądanie.',
				'feed_ajax_not_found'      => 'Nie znaleziono aktywności.',
				'feed_user_num'            => 'Użytkownik #%d',
				'feed_completed_phrase_mid' => 'ukończył frazę %d w opowieści ',
				'feed_completed_story_mid'  => 'ukończył opowieść ',
				'feed_unlocked_story_mid'   => 'odblokował opowieść ',
				'feed_generic_story_mid'    => 'ma aktywność przy opowieści ',
				'feed_lang_row'             => 'Języki: %1$s → %2$s',
				'feed_lang_unknown'         => '—',
				'feed_motivate_caption'     => 'Zmotywuj uczącego się',
				'feed_motivate_own_caption' => 'Twoje brawa',
				'feed_motivate_guest_caption' => 'Zaloguj się, by zostawić brawo',
				'bravo_balance_sent'          => 'Wysłane brawa',
				'bravo_balance_received'      => 'Otrzymane brawa',
				'bravo_balance_tip'           => 'Motywuj też innych: równowaga między brawami danymi a otrzymanymi sprawia, że społeczność jest życzliwsza dla wszystkich.',
				'bravo_balance_guest'         => 'Zaloguj się, aby zobaczyć bilans braw.',
				'bravo_balance_login'         => 'Zaloguj się',
			),
			'es' => array(
				'feed_title_community'     => 'Comunidad',
				'feed_empty'               => 'Aún no hay actividad. Al completar frases o desbloquear historias aparecerán aquí.',
				'feed_load_more'           => 'Cargar más',
				'feed_loading'             => 'Cargando…',
				'feed_error'               => 'No se pudo completar la acción. Inténtalo de nuevo.',
				'feed_bravo'               => '¡Bravo!',
				'feed_bravo_aria'          => '¡Bravo! — %d recibidos',
				'feed_login_bravo_hint'    => 'Inicia sesión para dejar un «bravo».',
				'feed_cannot_bravo'        => 'No puedes dar «bravo» a esta actividad (p. ej. es tuya).',
				'feed_bravo_received_aria' => '«Bravo» recibidos',
				'feed_ajax_login_bravo'    => 'Inicia sesión para dar o quitar un «bravo».',
				'feed_ajax_invalid'        => 'Solicitud no válida.',
				'feed_ajax_not_found'      => 'Actividad no encontrada.',
				'feed_user_num'            => 'Usuario n.º %d',
				'feed_completed_phrase_mid' => 'ha completado la frase %d en la historia ',
				'feed_completed_story_mid'  => 'ha completado la historia ',
				'feed_unlocked_story_mid'   => 'ha desbloqueado la historia ',
				'feed_generic_story_mid'    => 'tiene actividad en la historia ',
				'feed_lang_row'             => 'Idiomas: %1$s → %2$s',
				'feed_lang_unknown'         => '—',
				'feed_motivate_caption'     => 'Anima a quien estudia',
				'feed_motivate_own_caption' => 'Tus «bravos»',
				'feed_motivate_guest_caption' => 'Motiva con un «bravo»',
				'bravo_balance_sent'          => '«Bravos» que has dado',
				'bravo_balance_received'      => '«Bravos» que has recibido',
				'bravo_balance_tip'           => 'Anima también a los demás: equilibrar los «bravos» que das con los que recibes hace la comunidad más justa y positiva para todos.',
				'bravo_balance_guest'         => 'Inicia sesión para ver el balance de tus «bravos».',
				'bravo_balance_login'         => 'Iniciar sesión',
			),
		);

		return $cache;
	}
}
