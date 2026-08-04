<?php
/**
 * Registro delle modalità di apprendimento del gioco frasi.
 *
 * Nuove modalità si aggiungono in self::registry() oppure via filtro `llm_learning_modes`.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Learning_Modes {

	const USER_META    = '_llm_learning_mode';
	const STORAGE_KEY  = 'llm_learning_mode';
	const AJAX_ACTION  = 'llm_learning_mode_save';
	const NONCE_ACTION = 'llm_learning_mode';

	/** Opzioni di aiuto, indipendenti dalla modalità e salvate insieme a essa. */
	const OPTIONS_USER_META   = '_llm_learning_options';
	const OPTIONS_STORAGE_KEY = 'llm_learning_options';

	/** Pulsante con le parole della soluzione mescolate a parole-esca. */
	const OPTION_RANDOM_WORDS = 'random_words';

	/** Dopo ogni sessione microfono, riascolta automaticamente la traduzione. */
	const OPTION_LISTEN_REPLAY_LOOP = 'listen_replay_loop';

	/** Pulsanti per inserire caratteri speciali della lingua target. */
	const OPTION_EXTRA_CHARS = 'extra_chars';

	/** Modalità storica a due fasi. */
	const MODE_LOVEREWRITE = 'loverewrite';

	/** Fase unica: traduzione corretta e avanti. */
	const MODE_RESOLVE_GO = 'resolve_go';

	/** Fase unica senza controlli: si avanza sempre. */
	const MODE_READ_GO_FAST = 'read_go_fast';

	/** Fase unica invertita: vedi la traduzione, indovina l'originale. */
	const MODE_PLAY_INVERTED = 'play_inverted';

	public static function init() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_save' ) );
	}

	/**
	 * Modalità disponibili, in ordine di presentazione.
	 *
	 * @return array<int, array{id: string, label: string, description: string}>
	 */
	public static function all() {
		$modes = array(
			array(
				'id'          => self::MODE_LOVEREWRITE,
				'label'       => LLM_Phrase_Game_I18n::get( 'mode_loverewrite_label' ),
				'description' => LLM_Phrase_Game_I18n::get( 'mode_loverewrite_desc' ),
			),
			array(
				'id'          => self::MODE_RESOLVE_GO,
				'label'       => LLM_Phrase_Game_I18n::get( 'mode_resolve_go_label' ),
				'description' => LLM_Phrase_Game_I18n::get( 'mode_resolve_go_desc' ),
			),
			array(
				'id'          => self::MODE_READ_GO_FAST,
				'label'       => LLM_Phrase_Game_I18n::get( 'mode_read_go_fast_label' ),
				'description' => LLM_Phrase_Game_I18n::get( 'mode_read_go_fast_desc' ),
			),
			array(
				'id'          => self::MODE_PLAY_INVERTED,
				'label'       => LLM_Phrase_Game_I18n::get( 'mode_play_inverted_label' ),
				'description' => LLM_Phrase_Game_I18n::get( 'mode_play_inverted_desc' ),
			),
		);

		/**
		 * Permette di registrare nuove modalità di apprendimento.
		 *
		 * @param array<int, array{id: string, label: string, description: string}> $modes
		 */
		$modes = (array) apply_filters( 'llm_learning_modes', $modes );

		$out = array();
		foreach ( $modes as $mode ) {
			if ( ! is_array( $mode ) || empty( $mode['id'] ) ) {
				continue;
			}
			$out[] = array(
				'id'          => sanitize_key( (string) $mode['id'] ),
				'label'       => isset( $mode['label'] ) ? (string) $mode['label'] : (string) $mode['id'],
				'description' => isset( $mode['description'] ) ? (string) $mode['description'] : '',
			);
		}

		return $out;
	}

	/**
	 * Opzioni di aiuto attivabili, valide per qualunque modalità.
	 *
	 * @return array<int, array{id: string, label: string, description: string}>
	 */
	public static function options() {
		$options = array(
			array(
				'id'          => self::OPTION_RANDOM_WORDS,
				'label'       => LLM_Phrase_Game_I18n::get( 'option_random_words_label' ),
				'description' => LLM_Phrase_Game_I18n::get( 'option_random_words_desc' ),
			),
			array(
				'id'          => self::OPTION_LISTEN_REPLAY_LOOP,
				'label'       => LLM_Phrase_Game_I18n::get( 'option_listen_replay_loop_label' ),
				'description' => LLM_Phrase_Game_I18n::get( 'option_listen_replay_loop_desc' ),
			),
			array(
				'id'          => self::OPTION_EXTRA_CHARS,
				'label'       => LLM_Phrase_Game_I18n::get( 'option_extra_chars_label' ),
				'description' => LLM_Phrase_Game_I18n::get( 'option_extra_chars_desc' ),
			),
		);

		/**
		 * Permette di registrare nuove opzioni di aiuto.
		 *
		 * @param array<int, array{id: string, label: string, description: string}> $options
		 */
		$options = (array) apply_filters( 'llm_learning_mode_options', $options );

		$out = array();
		foreach ( $options as $option ) {
			if ( ! is_array( $option ) || empty( $option['id'] ) ) {
				continue;
			}
			$out[] = array(
				'id'          => sanitize_key( (string) $option['id'] ),
				'label'       => isset( $option['label'] ) ? (string) $option['label'] : (string) $option['id'],
				'description' => isset( $option['description'] ) ? (string) $option['description'] : '',
			);
		}

		return $out;
	}

	/**
	 * Tiene solo gli identificatori di opzioni realmente registrate.
	 *
	 * @param mixed $ids
	 * @return array<int, string>
	 */
	public static function sanitize_options( $ids ) {
		if ( ! is_array( $ids ) ) {
			$ids = '' === $ids || null === $ids ? array() : explode( ',', (string) $ids );
		}

		$known = wp_list_pluck( self::options(), 'id' );
		$out   = array();
		foreach ( $ids as $id ) {
			if ( ! is_scalar( $id ) ) {
				continue;
			}
			$id = sanitize_key( (string) $id );
			if ( '' !== $id && in_array( $id, $known, true ) && ! in_array( $id, $out, true ) ) {
				$out[] = $id;
			}
		}

		return $out;
	}

	/**
	 * @param int $user_id
	 * @return array<int, string>
	 */
	public static function get_options_for_user( $user_id ) {
		return self::sanitize_options( get_user_meta( (int) $user_id, self::OPTIONS_USER_META, true ) );
	}

	/**
	 * @param int   $user_id
	 * @param mixed $ids
	 */
	public static function set_options_for_user( $user_id, $ids ) {
		update_user_meta( (int) $user_id, self::OPTIONS_USER_META, self::sanitize_options( $ids ) );
	}

	/**
	 * Opzioni attive: dal profilo se loggato, altrimenti nessuna (gli ospiti usano localStorage lato JS).
	 *
	 * @return array<int, string>
	 */
	public static function current_options() {
		if ( self::disables_options( self::current() ) ) {
			return array();
		}
		if ( is_user_logged_in() ) {
			return self::get_options_for_user( get_current_user_id() );
		}
		return array();
	}

	/**
	 * Identificatore della modalità predefinita.
	 *
	 * @return string
	 */
	public static function default_mode() {
		return (string) apply_filters( 'llm_learning_mode_default', self::MODE_LOVEREWRITE );
	}

	/**
	 * @param string $mode_id
	 * @return bool
	 */
	public static function is_valid( $mode_id ) {
		$mode_id = sanitize_key( (string) $mode_id );
		if ( '' === $mode_id ) {
			return false;
		}
		foreach ( self::all() as $mode ) {
			if ( $mode['id'] === $mode_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Modalità salvata per l'utente (default se assente o non più valida).
	 *
	 * @param int $user_id
	 * @return string
	 */
	public static function get_for_user( $user_id ) {
		$saved = (string) get_user_meta( (int) $user_id, self::USER_META, true );
		return self::is_valid( $saved ) ? sanitize_key( $saved ) : self::default_mode();
	}

	/**
	 * @param int    $user_id
	 * @param string $mode_id
	 * @return bool True se salvata.
	 */
	public static function set_for_user( $user_id, $mode_id ) {
		if ( ! self::is_valid( $mode_id ) ) {
			return false;
		}
		update_user_meta( (int) $user_id, self::USER_META, sanitize_key( (string) $mode_id ) );
		return true;
	}

	/**
	 * Modalità corrente: dal profilo se loggato, altrimenti default (gli ospiti usano localStorage lato JS).
	 *
	 * @return string
	 */
	public static function current() {
		if ( is_user_logged_in() ) {
			return self::get_for_user( get_current_user_id() );
		}
		return self::default_mode();
	}

	/**
	 * Modalità da applicare a una richiesta AJAX.
	 *
	 * Per gli utenti loggati vale sempre il profilo: è l'unico dato di cui il server
	 * si fida, visto che da qui dipendono punti e avanzamento. Gli ospiti tengono la
	 * scelta in localStorage, quindi la modalità arriva dal client e va solo validata.
	 *
	 * @param string $posted_mode Modalità dichiarata dal client.
	 * @return string
	 */
	public static function for_request( $posted_mode = '' ) {
		if ( is_user_logged_in() ) {
			return self::get_for_user( get_current_user_id() );
		}
		$posted_mode = sanitize_key( (string) $posted_mode );
		return self::is_valid( $posted_mode ) ? $posted_mode : self::default_mode();
	}

	/**
	 * Se la modalità avanza senza controllare il testo scritto dall'utente.
	 *
	 * @param string $mode_id
	 * @return bool
	 */
	public static function skips_validation( $mode_id ) {
		$mode_id = sanitize_key( (string) $mode_id );
		$skips   = in_array( $mode_id, array( self::MODE_READ_GO_FAST, self::MODE_PLAY_INVERTED ), true );

		/**
		 * Permette a modalità registrate da terzi di saltare la validazione.
		 *
		 * @param bool   $skips
		 * @param string $mode_id
		 */
		return (bool) apply_filters( 'llm_learning_mode_skips_validation', $skips, $mode_id );
	}

	/**
	 * Se la modalità disattiva tutti gli strumenti utili.
	 *
	 * @param string $mode_id
	 * @return bool
	 */
	public static function disables_options( $mode_id ) {
		$mode_id  = sanitize_key( (string) $mode_id );
		$disabled = self::MODE_PLAY_INVERTED === $mode_id;

		/**
		 * @param bool   $disabled
		 * @param string $mode_id
		 */
		return (bool) apply_filters( 'llm_learning_mode_disables_options', $disabled, $mode_id );
	}

	/**
	 * Etichetta leggibile di una modalità.
	 *
	 * @param string $mode_id
	 * @return string
	 */
	public static function label( $mode_id ) {
		$mode_id = sanitize_key( (string) $mode_id );
		foreach ( self::all() as $mode ) {
			if ( $mode['id'] === $mode_id ) {
				return $mode['label'];
			}
		}
		return '';
	}

	/**
	 * Dati per wp_localize_script.
	 *
	 * @return array<string, mixed>
	 */
	public static function script_data() {
		return array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( self::NONCE_ACTION ),
			'action'      => self::AJAX_ACTION,
			'isLoggedIn'  => is_user_logged_in(),
			'storageKey'  => self::STORAGE_KEY,
			'current'     => self::current(),
			'defaultMode' => self::default_mode(),
			'modes'       => self::all(),
			'options'          => self::options(),
			'currentOptions'   => self::current_options(),
			'optionsStorageKey' => self::OPTIONS_STORAGE_KEY,
			'modeDisablesOptions' => self::MODE_PLAY_INVERTED,
			'savedMsg'    => LLM_Phrase_Game_I18n::get( 'learning_mode_saved' ),
			'errorMsg'    => LLM_Phrase_Game_I18n::get( 'learning_mode_error' ),
		);
	}

	/**
	 * AJAX: salva la modalità scelta dall'utente loggato.
	 */
	public static function ajax_save() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'learning_mode_error' ) ), 403 );
		}

		$mode_id = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		if ( ! self::set_for_user( get_current_user_id(), $mode_id ) ) {
			wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'learning_mode_error' ) ), 400 );
		}

		$posted_options = isset( $_POST['options'] ) ? wp_unslash( $_POST['options'] ) : array();
		if ( self::disables_options( $mode_id ) ) {
			$posted_options = array();
		}
		self::set_options_for_user( get_current_user_id(), $posted_options );

		wp_send_json_success(
			array(
				'mode'    => $mode_id,
				'label'   => self::label( $mode_id ),
				'options' => self::get_options_for_user( get_current_user_id() ),
			)
		);
	}
}
