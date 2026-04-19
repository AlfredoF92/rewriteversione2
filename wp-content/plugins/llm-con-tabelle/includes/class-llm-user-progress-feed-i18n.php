<?php
/**
 * Testi shortcode progressi utente (prima persona), lingua interfaccia come il gioco frasi.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_User_Progress_Feed_I18n
 */
class LLM_User_Progress_Feed_I18n {

	/**
	 * @return string it|en|pl|es
	 */
	public static function lang() {
		$code = LLM_Phrase_Game_I18n::lang();
		return (string) apply_filters( 'llm_user_progress_feed_ui_lang', $code );
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
	 * @param string   $key Chiave.
	 * @param mixed ...$args Argomenti sprintf.
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
				'progress_title'            => 'Frasi e storie completate',
				'progress_empty'          => 'Non hai ancora completato frasi o storie. Inizia una storia dalla tua area.',
				'progress_guest'            => 'Accedi per vedere le tue frasi completate e le storie sbloccate.',
				'progress_login'            => 'Vai al login',
				'progress_phrase_mid'       => 'Hai completato la frase %d nella storia ',
				'progress_story_done_mid'   => 'Hai completato la storia ',
				'progress_story_unlock_mid' => 'Hai sbloccato la storia ',
				'progress_generic_mid'      => "Hai un'attività sulla storia ",
			),
			'en' => array(
				'progress_title'            => 'Completed phrases and stories',
				'progress_empty'            => 'You have not completed any phrases or stories yet. Start a story from your area.',
				'progress_guest'            => 'Log in to see your completed phrases and unlocked stories.',
				'progress_login'            => 'Go to login',
				'progress_phrase_mid'       => 'You completed phrase %d in the story ',
				'progress_story_done_mid'   => 'You completed the story ',
				'progress_story_unlock_mid' => 'You unlocked the story ',
				'progress_generic_mid'      => 'You have activity on the story ',
			),
			'pl' => array(
				'progress_title'            => 'Ukończone frazy i opowieści',
				'progress_empty'            => 'Nie ukończyłeś jeszcze żadnych fraz ani opowieści. Zacznij opowieść w swojej strefie.',
				'progress_guest'            => 'Zaloguj się, aby zobaczyć ukończone frazy i odblokowane opowieści.',
				'progress_login'            => 'Przejdź do logowania',
				'progress_phrase_mid'       => 'Ukończyłeś frazę %d w opowieści ',
				'progress_story_done_mid'   => 'Ukończyłeś opowieść ',
				'progress_story_unlock_mid' => 'Odblokowałeś opowieść ',
				'progress_generic_mid'      => 'Masz aktywność przy opowieści ',
			),
			'es' => array(
				'progress_title'            => 'Frases e historias completadas',
				'progress_empty'            => 'Aún no has completado frases ni historias. Empieza una historia desde tu área.',
				'progress_guest'            => 'Inicia sesión para ver tus frases completadas e historias desbloqueadas.',
				'progress_login'            => 'Ir al inicio de sesión',
				'progress_phrase_mid'       => 'Completaste la frase %d en la historia ',
				'progress_story_done_mid'   => 'Completaste la historia ',
				'progress_story_unlock_mid' => 'Desbloqueaste la historia ',
				'progress_generic_mid'      => 'Tienes actividad en la historia ',
			),
		);

		return $cache;
	}
}
