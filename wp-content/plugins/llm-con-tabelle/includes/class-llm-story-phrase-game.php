<?php
/**
 * Shortcode gioco frasi: traduci → feedback → riscrivi; storia con sole traduzioni completate.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Story_Phrase_Game {

	const SHORTCODE = 'llm_story_phrase_game';

	/** Soglia fase 1: % parole della referenza trovate nell’input utente. */
	const PHASE1_MIN_RATIO = 0.2;

	/** Soglia fase 2: similar_text (0–100) su stringa normalizzata. */
	const PHASE2_MIN_SIMILAR = 68;

	/** Soglia alternativa fase 2: rapporto parole. */
	const PHASE2_MIN_WORD_RATIO = 0.82;

	/**
	 * Locale BCP-47 per Web Speech API (lingua che si studia = target storia).
	 *
	 * @param string $code it|en|pl|es.
	 * @return string
	 */
	public static function speech_locale( $code ) {
		$map = array(
			'it' => 'it-IT',
			'en' => 'en-US',
			'pl' => 'pl-PL',
			'es' => 'es-ES',
		);
		$c = sanitize_key( (string) $code );
		return isset( $map[ $c ] ) ? $map[ $c ] : 'en-US';
	}

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_singular' ) );
		add_action( 'wp_ajax_llm_phrase_game_check', array( __CLASS__, 'ajax_check' ) );
		add_action( 'wp_ajax_nopriv_llm_phrase_game_check', array( __CLASS__, 'ajax_check' ) );
		add_action( 'wp_ajax_llm_phrase_game_restart', array( __CLASS__, 'ajax_restart' ) );
		add_action( 'wp_ajax_nopriv_llm_phrase_game_restart', array( __CLASS__, 'ajax_restart' ) );
		add_action( 'wp_ajax_llm_phrase_game_set_display', array( __CLASS__, 'ajax_set_display' ) );
		add_action( 'wp_ajax_nopriv_llm_phrase_game_set_display', array( __CLASS__, 'ajax_set_display' ) );
		add_action( 'wp_ajax_llm_fe_save_phrase_notes', array( __CLASS__, 'ajax_save_phrase_notes' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_sync_visitor_langs' ), 6 );
	}

	/**
	 * Allinea lingua conosciuta e da imparare alla coppia della storia.
	 */
	public static function maybe_sync_visitor_langs() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( is_customize_preview() || is_preview() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['elementor-preview'] ) ) {
			return;
		}
		if ( ! is_singular( LLM_STORY_CPT ) ) {
			return;
		}
		if ( ! class_exists( 'LLM_Visitor_Lang' ) || ! class_exists( 'LLM_Languages' ) ) {
			return;
		}

		$story_id = get_queried_object_id();
		if ( ! $story_id ) {
			return;
		}

		$known  = sanitize_key( (string) get_post_meta( $story_id, LLM_Story_Meta::KNOWN_LANG, true ) );
		$target = sanitize_key( (string) get_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, true ) );
		if ( ! LLM_Languages::is_valid( $known ) || ! LLM_Languages::is_valid( $target ) || $known === $target ) {
			return;
		}

		if ( LLM_Visitor_Lang::stored_known() === $known && LLM_Visitor_Lang::stored_learning() === $target ) {
			return;
		}

		LLM_Visitor_Lang::set_pair( $known, $target );
	}

	/**
	 * Canzone / brano musicale.
	 *
	 * @param int $story_id ID storia.
	 * @return bool
	 */
	private static function is_song_story( $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return false;
		}
		if ( class_exists( 'LLM_Magazine' ) && LLM_Magazine::is_music_story( $story_id ) ) {
			return true;
		}
		$cefr = (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_CEFR_LEVEL, true );
		return (bool) preg_match( '/canzone|song|brano/i', $cefr );
	}

	/**
	 * Carica asset sulla singola storia se ci sono frasi (lo shortcode può stare nel template Elementor).
	 * Lo script non fa nulla se in pagina non c’è .llm-phrase-game.
	 */
	public static function maybe_enqueue_singular() {
		if ( ! is_singular( LLM_STORY_CPT ) ) {
			return;
		}
		global $post;
		if ( ! $post || LLM_STORY_CPT !== $post->post_type ) {
			return;
		}
		$sid = (int) $post->ID;
		if ( empty( LLM_Story_Repository::get_phrases( $sid ) ) ) {
			return;
		}
		if ( ! apply_filters( 'llm_story_phrase_game_enqueue_assets', true, $sid ) ) {
			return;
		}
		self::enqueue_assets( $sid );
	}

	/**
	 * Tema del gioco (header sempre scuro). Fonte unica: LLM_Visitor_Theme.
	 *
	 * @return string
	 */
	private static function game_theme() {
		if ( class_exists( 'LLM_Visitor_Theme' ) ) {
			$stored = LLM_Visitor_Theme::stored();
			if ( '' !== $stored ) {
				return LLM_Visitor_Theme::get();
			}
		}
		return 'dark';
	}

	/**
	 * Pulsanti DARK / LIGHT: al click salvano il cookie e ricaricano.
	 *
	 * @param string $current dark|light.
	 * @return string
	 */
	private static function render_game_theme_switcher( $current ) {
		$current = ( 'light' === $current ) ? 'light' : 'dark';
		ob_start();
		?>
		<div class="llm-game-theme" role="group" aria-label="<?php echo esc_attr( 'Versione colore gioco' ); ?>">
			<button type="button" class="llm-game-theme__btn llm-game-theme__btn--dark<?php echo 'dark' === $current ? ' is-active' : ''; ?>" data-llm-game-theme="dark" aria-pressed="<?php echo 'dark' === $current ? 'true' : 'false'; ?>">versione DARK</button>
			<button type="button" class="llm-game-theme__btn llm-game-theme__btn--light<?php echo 'light' === $current ? ' is-active' : ''; ?>" data-llm-game-theme="light" aria-pressed="<?php echo 'light' === $current ? 'true' : 'false'; ?>">versione LIGHT</button>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Pulsanti Due colonne / Una colonna: al click salvano cookie/profilo e ricaricano.
	 *
	 * @param string $current one|two.
	 * @return string
	 */
	private static function render_story_layout_switcher( $current ) {
		$current = ( 'two' === $current ) ? 'two' : 'one';
		ob_start();
		?>
		<div class="llm-story-layout-switch" role="group" aria-label="<?php echo esc_attr( LLM_Phrase_Game_I18n::get( 'layout_group' ) ); ?>">
			<button type="button" class="llm-game-theme__btn llm-story-layout-switch__btn<?php echo 'two' === $current ? ' is-active' : ''; ?>" data-llm-story-layout="two" aria-pressed="<?php echo 'two' === $current ? 'true' : 'false'; ?>"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'layout_two' ) ); ?></button>
			<button type="button" class="llm-game-theme__btn llm-story-layout-switch__btn<?php echo 'one' === $current ? ' is-active' : ''; ?>" data-llm-story-layout="one" aria-pressed="<?php echo 'one' === $current ? 'true' : 'false'; ?>"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'layout_one' ) ); ?></button>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Pulsante Continua / prossima frase, con freccia.
	 *
	 * @param string $suffix continue1|continue2.
	 * @return string
	 */
	private static function render_continue_button( $suffix ) {
		$label = LLM_Phrase_Game_I18n::get( 'continue' );
		return '<button type="button" class="llm-phrase-game__btn llm-phrase-game__btn--' . esc_attr( $suffix ) . ' button">'
			. '<span class="llm-phrase-game__btn-label">' . esc_html( $label ) . '</span>'
			. '<span class="llm-phrase-game__btn-arrow" aria-hidden="true">'
			. '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>'
			. '</span>'
			. '</button>';
	}

	/**
	 * Pulsante per tornare al checkpoint dopo un salto.
	 *
	 * @return string
	 */
	private static function render_jump_return_button() {
		return '<p class="llm-learning-mode__bar llm-phrase-game__jump-return" hidden>'
			. '<span class="llm-learning-mode__label">' . esc_html( LLM_Phrase_Game_I18n::get( 'return_to_checkpoint_label' ) ) . '</span>'
			. '<button type="button" class="llm-learning-mode__change llm-phrase-game__jump-return-btn"></button>'
			. '</p>';
	}

	/**
	 * Pulsante per svuotare la textarea (fase 1 o 2).
	 *
	 * @param string $suffix Suffisso classe (--1 / --2).
	 * @return string
	 */
	private static function render_clear_input_button( $suffix ) {
		$label = LLM_Phrase_Game_I18n::get( 'clear_input' );
		return '<button type="button" class="llm-phrase-game__clear-input llm-phrase-game__clear-input--' . esc_attr( $suffix ) . ' button" aria-label="' . esc_attr( $label ) . '">'
			. '<span class="llm-phrase-game__clear-input-icon" aria-hidden="true">'
			. '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M17.65 6.35A7.958 7.958 0 0 0 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0 1 12 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>'
			. '</span>'
			. '<span class="llm-phrase-game__clear-input-text">' . esc_html( $label ) . '</span>'
			. '</button>';
	}

	/**
	 * Pulsante "Riprendi dalle parole esatte" (frecce, una per parola da togliere).
	 *
	 * @param string $suffix Suffisso classe (--1 / --2 / --voice).
	 * @return string
	 */
	private static function render_rewind_exact_button( $suffix ) {
		$label = LLM_Phrase_Game_I18n::get( 'rewind_exact' );
		return '<button type="button" class="llm-phrase-game__clear-input llm-phrase-game__rewind-input llm-phrase-game__rewind-input--' . esc_attr( $suffix ) . ' button" hidden aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '">'
			. '<span class="llm-phrase-game__rewind-input-arrows" aria-hidden="true"></span>'
			. '</button>';
	}

	/**
	 * Riga sotto textarea: svuota + riprendi dalle parole esatte.
	 *
	 * @param string $suffix Suffisso classe (--1 / --2 / --voice).
	 * @return string
	 */
	private static function render_clear_input_row( $suffix ) {
		return self::render_clear_input_button( $suffix ) . self::render_rewind_exact_button( $suffix );
	}

	private static function render_mic_button( $mod_class, $engine, $label ) {
		$label = (string) $label;
		return '<button type="button" class="llm-phrase-game__mic ' . esc_attr( $mod_class ) . '" data-llm-stt="' . esc_attr( $engine ) . '" aria-label="' . esc_attr( $label ) . '">'
			. '<span class="llm-phrase-game__mic-icon" aria-hidden="true">&#127908;</span>'
			. '<span class="llm-phrase-game__mic-text">' . esc_html( $label ) . '</span>'
			. '</button>';
	}

	private static function render_mic_cluster( $uid, $phase, $deepgram_ready, $azure_ready, $with_help ) {
		$phase = ( '2' === (string) $phase ) ? '2' : '1';
		$html  = '<div class="llm-phrase-game__mic-cluster">';
		$html .= '<div class="llm-phrase-game__mic-stage" data-llm-mic-stage>';
		if ( $deepgram_ready ) {
			$html .= self::render_mic_button(
				'llm-phrase-game__mic--deepgram llm-phrase-game__mic--deepgram-' . $phase,
				'deepgram',
				LLM_Phrase_Game_I18n::get( 'mic_button_deepgram' )
			);
		}
		if ( $azure_ready ) {
			$html .= self::render_mic_button(
				'llm-phrase-game__mic--azure llm-phrase-game__mic--azure-' . $phase,
				'azure',
				LLM_Phrase_Game_I18n::get( 'mic_button_azure' )
			);
		}
		$browser_mod = ( '2' === $phase ) ? 'llm-phrase-game__mic--2' : 'llm-phrase-game__mic--1';
		$html       .= self::render_mic_button( $browser_mod, 'browser', LLM_Phrase_Game_I18n::get( 'mic_button_browser' ) );
		$html       .= '</div>';
		$html       .= '<button type="button" class="llm-phrase-game__mic-switch" data-llm-mic-switch aria-label="' . esc_attr( LLM_Phrase_Game_I18n::get( 'mic_switch_aria' ) ) . '">';
		$html       .= '<span class="llm-phrase-game__mic-switch-pad" aria-hidden="true"><span class="llm-phrase-game__mic-switch-arrow"></span></span>';
		$html       .= '</button>';
		if ( $with_help ) {
			$html .= '<span class="llm-phrase-game__mic-help">';
			$html .= '<button type="button" class="llm-phrase-game__help-dot llm-phrase-game__mic-help-btn" aria-expanded="false" aria-controls="' . esc_attr( $uid ) . '-mic-help" aria-label="' . esc_attr( LLM_Phrase_Game_I18n::get( 'mic_help_aria' ) ) . '"><span class="llm-phrase-game__help-dot-mark" aria-hidden="true">?</span></button>';
			$html .= '<span class="llm-phrase-game__mic-help-bubble" id="' . esc_attr( $uid ) . '-mic-help" role="tooltip" hidden>' . esc_html( LLM_Phrase_Game_I18n::get( 'mic_help_text' ) ) . '</span>';
			$html .= '</span>';
		}
		$html .= '</div>';
		return $html;
	}

	private static function listen_speaker_icon() {
		return '<span class="llm-phrase-game__listen-target-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg></span>';
	}

	private static function render_listen_target_button( $classes, $label, $aria, $hidden = false ) {
		$hidden_attr = $hidden ? ' hidden aria-hidden="true"' : '';
		return '<button type="button" class="llm-phrase-game__listen-target ' . esc_attr( trim( $classes ) ) . '"'
			. $hidden_attr
			. ' aria-label="' . esc_attr( $aria ) . '" title="' . esc_attr( $aria ) . '">'
			. self::listen_speaker_icon()
			. '<span class="llm-phrase-game__listen-target-text">' . esc_html( $label ) . '</span>'
			. '</button>';
	}

	private static function render_listen_voices( $phase_class, $label, $aria, $hidden_primary = false ) {
		$label_2 = LLM_Phrase_Game_I18n::get( 'listen_label_female' );
		$cls_1   = trim( $phase_class . ' llm-phrase-game__listen-target--voice-1' . ( $hidden_primary ? ' llm-phrase-game__listen-target--force-hidden' : '' ) );
		$cls_2   = trim( $phase_class . ' llm-phrase-game__listen-target--voice-2 llm-phrase-game__listen-target--force-hidden' );
		return '<div class="llm-phrase-game__listen-voices">'
			. self::render_listen_target_button( $cls_1, $label, $aria, $hidden_primary )
			. self::render_listen_target_button( $cls_2, $label_2, $label_2, true )
			. '</div>';
	}

	private static function render_listen_switch() {
		return '<button type="button" class="llm-phrase-game__mic-switch llm-phrase-game__listen-switch" data-llm-listen-switch hidden aria-label="' . esc_attr( LLM_Phrase_Game_I18n::get( 'listen_switch_aria' ) ) . '">'
			. '<span class="llm-phrase-game__mic-switch-pad" aria-hidden="true"><span class="llm-phrase-game__mic-switch-arrow"></span></span>'
			. '</button>';
	}

	private static function render_listen_slow() {
		$icon = '<span class="llm-phrase-game__listen-slow-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" focusable="false"><circle cx="12" cy="6" r="2.15"/><circle cx="5.15" cy="10.15" r="1.7"/><circle cx="18.85" cy="10.15" r="1.7"/><circle cx="6.1" cy="16.85" r="1.7"/><circle cx="17.9" cy="16.85" r="1.7"/><circle cx="12" cy="19.35" r="1.15"/><ellipse cx="12" cy="13.05" rx="6.4" ry="4.85"/></svg></span>';
		return '<button type="button" class="llm-phrase-game__mic-switch llm-phrase-game__listen-switch llm-phrase-game__listen-slow" data-llm-listen-slow aria-label="' . esc_attr( LLM_Phrase_Game_I18n::get( 'listen_slow_aria' ) ) . '" title="' . esc_attr( LLM_Phrase_Game_I18n::get( 'listen_slow_aria' ) ) . '">'
			. '<span class="llm-phrase-game__mic-switch-pad" aria-hidden="true">' . $icon . '</span>'
			. '</button>';
	}

	/**
	 * Pulsante "Visualizza parole random" e contenitore delle parole (riempito via JS).
	 *
	 * Resta nascosto se l'opzione di aiuto non è attiva.
	 *
	 * @param string $uid    Prefisso ID univoco dell'istanza.
	 * @param string $suffix Fase di appartenenza (1 / 2).
	 * @return string
	 */
	private static function render_random_words_block( $uid, $suffix ) {
		$label   = LLM_Phrase_Game_I18n::get( 'random_words_show' );
		$aria    = LLM_Phrase_Game_I18n::get( 'random_words_aria' );
		$list_id = $uid . '-random-words-' . $suffix;

		return '<div class="llm-phrase-game__random-words llm-phrase-game__random-words--' . esc_attr( $suffix ) . '" hidden>'
			. '<button type="button" class="llm-game-theme__btn llm-story-layout-switch__btn llm-phrase-game__helper-acc llm-phrase-game__random-words-toggle" aria-expanded="false" aria-controls="' . esc_attr( $list_id ) . '">'
			. '<span class="llm-phrase-game__helper-acc-emoji" aria-hidden="true">🎲</span>'
			. '<span class="llm-phrase-game__helper-acc-text">' . esc_html( $label ) . '</span>'
			. '</button>'
			. '<div class="llm-phrase-game__random-words-list" id="' . esc_attr( $list_id ) . '" role="group" aria-label="' . esc_attr( $aria ) . '" hidden></div>'
			. '</div>';
	}

	/**
	 * Accordion caratteri speciali della lingua target (sotto parole random / microfono).
	 *
	 * @param string $uid    Prefisso id univoco shortcode.
	 * @param string $suffix '1' o '2'.
	 * @return string
	 */
	private static function render_extra_chars_block( $uid, $suffix ) {
		$label    = LLM_Phrase_Game_I18n::get( 'extra_chars_toggle' );
		$panel_id = $uid . '-extra-chars-' . $suffix;

		return '<div class="llm-phrase-game__extra-chars llm-phrase-game__extra-chars--' . esc_attr( $suffix ) . '" hidden>'
			. '<button type="button" class="llm-game-theme__btn llm-story-layout-switch__btn llm-phrase-game__helper-acc llm-phrase-game__extra-chars-toggle" aria-expanded="false" aria-controls="' . esc_attr( $panel_id ) . '">'
			. '<span class="llm-phrase-game__helper-acc-emoji" aria-hidden="true">⌨️</span>'
			. '<span class="llm-phrase-game__helper-acc-text">' . esc_html( $label ) . '</span>'
			. '</button>'
			. '<div class="llm-phrase-game__extra-chars-panel" id="' . esc_attr( $panel_id ) . '" hidden></div>'
			. '</div>';
	}

	/**
	 * Accordion tastiera QWERTY a schermo (caratteri della lingua + lettere).
	 *
	 * @param string $uid    Prefisso id univoco shortcode.
	 * @param string $suffix '1' o '2'.
	 * @return string
	 */
	private static function render_keyboard_block( $uid, $suffix ) {
		$label    = LLM_Phrase_Game_I18n::get( 'keyboard_toggle' );
		$panel_id = $uid . '-keyboard-' . $suffix;

		return '<div class="llm-phrase-game__keyboard llm-phrase-game__keyboard--' . esc_attr( $suffix ) . '">'
			. '<button type="button" class="llm-game-theme__btn llm-story-layout-switch__btn llm-phrase-game__helper-acc llm-phrase-game__keyboard-toggle" aria-expanded="false" aria-controls="' . esc_attr( $panel_id ) . '">'
			. '<span class="llm-phrase-game__helper-acc-emoji" aria-hidden="true">⌨️</span>'
			. '<span class="llm-phrase-game__helper-acc-text">' . esc_html( $label ) . '</span>'
			. '</button>'
			. '<div class="llm-phrase-game__keyboard-panel" id="' . esc_attr( $panel_id ) . '" hidden></div>'
			. '</div>';
	}

	/**
	 * @param array<string, string> $atts Attributi shortcode.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'story_id' => '',
			),
			$atts,
			self::SHORTCODE
		);

		$story_id = $atts['story_id'] !== '' ? absint( $atts['story_id'] ) : get_the_ID();
		if ( ! $story_id ) {
			return '';
		}

		$post = get_post( $story_id );
		if ( ! $post || LLM_STORY_CPT !== $post->post_type || 'publish' !== $post->post_status ) {
			return '<p class="llm-phrase-game__error">' . esc_html( LLM_Phrase_Game_I18n::get( 'story_unavailable' ) ) . '</p>';
		}

		self::enqueue_assets( $story_id );

		$phrases = LLM_Story_Repository::get_phrases( $story_id );
		if ( empty( $phrases ) ) {
			return '<p class="llm-phrase-game__error">' . esc_html( LLM_Phrase_Game_I18n::get( 'no_phrases' ) ) . '</p>';
		}

		$uid          = 'llm-phrase-game-' . uniqid( '', false );
		$is_wp_admin  = self::current_user_can_edit_notes( $story_id );

		$target_code_shortcode = (string) get_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, true );
		$deepgram_ready        = class_exists( 'LLM_STT' ) && LLM_STT::deepgram_ready();
		$azure_ready           = class_exists( 'LLM_STT' ) && LLM_STT::azure_ready();
		$listen_target_aria    = LLM_Phrase_Game_I18n::format(
			'listen_target_aria',
			LLM_Phrase_Game_I18n::target_lang_label_for_ui( $target_code_shortcode )
		);
		$listen_target_label   = LLM_Phrase_Game_I18n::get( 'listen_target_label' );
		$listen_help_play_icon = '<span class="llm-phrase-game__listen-help-play-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg></span>';
		$known_ui_label        = LLM_Phrase_Game_I18n::target_lang_label_for_ui( LLM_Phrase_Game_I18n::lang() );
		$label_approx          = LLM_Phrase_Game_I18n::format( 'label_approx', $known_ui_label );
		$story_intro  = sanitize_textarea_field( (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_INTRO, true ) );
		$game_theme   = self::game_theme();
		$story_layout = class_exists( 'LLM_Visitor_Theme' ) ? LLM_Visitor_Theme::get_layout() : 'one';
		$story_layout = in_array( $story_layout, array( 'one', 'two' ), true ) ? $story_layout : 'one';
		$cw_id        = class_exists( 'LLM_Story_Crossword' ) ? LLM_Story_Crossword::get_crossword_id( $story_id ) : 0;
		$cw_html      = '';
		if ( $cw_id && class_exists( 'LLM_Crossword_Shortcode' ) ) {
			$cw_html = LLM_Crossword_Shortcode::render(
				array(
					'id'    => $cw_id,
					'story' => $story_id,
				)
			);
		}
		$has_crossword = ( '' !== trim( (string) $cw_html ) );
		ob_start();
		?>
		<div id="<?php echo esc_attr( $uid ); ?>" class="llm-phrase-game llm-phrase-game--theme-<?php echo esc_attr( $game_theme ); ?> llm-story-layout llm-story-layout--<?php echo esc_attr( $story_layout ); ?>" data-story-id="<?php echo esc_attr( (string) $story_id ); ?>" data-game-theme="<?php echo esc_attr( $game_theme ); ?>" data-story-view="phrases">
			<div class="llm-story-layout__body">
			<div class="llm-story-layout__read">
				<?php echo self::render_story_hero( $story_id, count( $phrases ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
			</div>
			<div class="llm-story-layout__learn">
			<?php echo self::render_story_view_tabs( $story_id, $has_crossword ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
			<div class="llm-story-view llm-story-view--phrases" id="llm-story-view-phrases-<?php echo esc_attr( (string) (int) $story_id ); ?>" role="tabpanel" aria-labelledby="llm-story-tab-phrases-<?php echo esc_attr( (string) (int) $story_id ); ?>">
			<div class="llm-phrase-game__story-wrap">
				<div class="llm-phrase-game__story" aria-live="polite"></div>
			</div>
			<div class="llm-phrase-game__divider" role="presentation" aria-hidden="true"></div>
			<div class="llm-phrase-game__card">
				<div class="llm-phrase-game__progress"></div>
				<div class="llm-phrase-game__phase llm-phrase-game__phase--1">
					<div class="llm-phrase-game__sticky-translate">
						<div class="llm-phrase-game__interface-row">
							<span class="llm-phrase-game__lang-flag llm-phrase-game__lang-flag--source" aria-hidden="true"></span>
							<div class="llm-phrase-game__interface"></div>
						</div>
						<p class="llm-phrase-game__prompt llm-phrase-game__prompt--translate">
							<span class="llm-phrase-game__prompt-text"></span>
						</p>
						<label class="screen-reader-text" for="<?php echo esc_attr( $uid ); ?>-input1"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'sr_your_translation' ) ); ?></label>
						<div class="llm-phrase-game__compose llm-phrase-game__compose--phase1">
							<div class="llm-phrase-game__input-block llm-phrase-game__input-block--sticky">
								<div class="llm-phrase-game__input-shell">
									<span class="llm-phrase-game__lang-flag llm-phrase-game__lang-flag--write" aria-hidden="true"></span>
									<textarea rows="1" wrap="off" id="<?php echo esc_attr( $uid ); ?>-input1" class="llm-phrase-game__input llm-phrase-game__input--1" name="llm_phrase_answer_1_<?php echo esc_attr( $uid ); ?>" autocomplete="off" autocorrect="off" autocapitalize="none" spellcheck="false" inputmode="none" enterkeyhint="done" virtualkeyboardpolicy="manual" readonly data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other"></textarea>
								</div>
								<div class="llm-phrase-game__clear-wrap llm-phrase-game__clear-wrap--1 llm-phrase-game__action-fade" hidden>
									<?php echo self::render_clear_input_button( '1' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
								</div>
							</div>
							<div class="llm-phrase-game__voice-field" hidden>
								<label class="screen-reader-text" for="<?php echo esc_attr( $uid ); ?>-input-voice"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'sr_your_spoken_translation' ) ); ?></label>
								<div class="llm-phrase-game__input-block llm-phrase-game__input-block--sticky llm-phrase-game__input-block--voice">
									<div class="llm-phrase-game__input-shell">
										<span class="llm-phrase-game__lang-flag llm-phrase-game__lang-flag--voice" aria-hidden="true"></span>
										<span class="llm-phrase-game__input-grow">
										<textarea rows="1" wrap="off" id="<?php echo esc_attr( $uid ); ?>-input-voice" class="llm-phrase-game__input llm-phrase-game__input--voice" name="llm_phrase_answer_voice_<?php echo esc_attr( $uid ); ?>" autocomplete="off" autocorrect="off" autocapitalize="none" spellcheck="false" inputmode="none" enterkeyhint="done" virtualkeyboardpolicy="manual" readonly data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other"></textarea>
										<span class="llm-phrase-game__voice-mark" hidden aria-hidden="true"></span>
										</span>
									</div>
									<div class="llm-phrase-game__clear-wrap llm-phrase-game__clear-wrap--voice llm-phrase-game__action-fade" hidden>
										<?php echo self::render_clear_input_row( 'voice' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="llm-phrase-game__helper-accs">
					<?php echo self::render_random_words_block( $uid, '1' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
					<?php echo self::render_extra_chars_block( $uid, '1' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
					<?php echo self::render_keyboard_block( $uid, '1' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
					</div>
					<div class="llm-phrase-game__field-trans" hidden>
						<button type="button" class="llm-phrase-game__notes-acc llm-phrase-game__field-trans-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>-field-trans">
							<span class="llm-phrase-game__notes-toggle-emoji" aria-hidden="true">❤️</span>
							<span class="llm-phrase-game__notes-acc-text"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'view_translation' ) ); ?></span>
							<span class="llm-phrase-game__tool-accordion-chevron" aria-hidden="true"></span>
						</button>
						<div class="llm-phrase-game__field-trans-panel" id="<?php echo esc_attr( $uid ); ?>-field-trans" hidden>
							<p class="llm-phrase-game__field-trans-text"></p>
						</div>
					</div>
					<div class="llm-phrase-game__field-pron" hidden>
						<button type="button" class="llm-phrase-game__notes-acc llm-phrase-game__field-pron-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>-field-pron">
							<span class="llm-phrase-game__notes-toggle-emoji" aria-hidden="true">🗣️</span>
							<span class="llm-phrase-game__notes-acc-text"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'view_pronunciation' ) ); ?></span>
							<span class="llm-phrase-game__tool-accordion-chevron" aria-hidden="true"></span>
						</button>
						<div class="llm-phrase-game__field-pron-panel" id="<?php echo esc_attr( $uid ); ?>-field-pron" hidden>
							<p class="llm-phrase-game__field-pron-label"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'label_ipa' ) ); ?></p>
							<p class="llm-phrase-game__field-pron-ipa"></p>
							<p class="llm-phrase-game__field-pron-label"><?php echo esc_html( $label_approx ); ?></p>
							<p class="llm-phrase-game__field-pron-approx"></p>
						</div>
					</div>
					<div class="llm-phrase-game__phase1-tools">
						<div class="llm-phrase-game__input-block llm-phrase-game__input-block--tools">
							<div class="llm-phrase-game__mic-row">
							<?php echo self::render_mic_cluster( $uid, '1', $deepgram_ready, $azure_ready, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapato. ?>
							</div>
							<div class="llm-phrase-game__listen-target-wrap llm-phrase-game__listen-target-wrap--above-mic" hidden>
								<div class="llm-phrase-game__listen-cluster">
								<?php echo self::render_listen_voices( '', $listen_target_label, $listen_target_aria, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapato. ?>
								<?php echo self::render_listen_switch(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapato. ?>
								<?php echo self::render_listen_slow(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapato. ?>
								<span class="llm-phrase-game__listen-help">
									<button type="button" class="llm-phrase-game__help-dot llm-phrase-game__listen-help-btn" aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>-listen-help" aria-label="<?php echo esc_attr( LLM_Phrase_Game_I18n::get( 'listen_help_aria' ) ); ?>"><span class="llm-phrase-game__help-dot-mark" aria-hidden="true">?</span></button>
									<span class="llm-phrase-game__listen-help-bubble" id="<?php echo esc_attr( $uid ); ?>-listen-help" role="dialog" hidden>
										<span class="llm-phrase-game__listen-help-row" data-listen-variant="slow">
											<span class="llm-phrase-game__listen-help-label"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'listen_help_slow' ) ); ?></span>
											<button type="button" class="llm-phrase-game__listen-help-play" data-listen-variant="slow"><?php echo $listen_help_play_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statico. ?><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'listen_help_play' ) ); ?></button>
										</span>
										<span class="llm-phrase-game__listen-help-row" data-listen-variant="voice-1">
											<span class="llm-phrase-game__listen-help-label"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'listen_help_variant_1' ) ); ?></span>
											<button type="button" class="llm-phrase-game__listen-help-play" data-listen-variant="voice-1"><?php echo $listen_help_play_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statico. ?><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'listen_help_play' ) ); ?></button>
										</span>
										<span class="llm-phrase-game__listen-help-row" data-listen-variant="voice-2">
											<span class="llm-phrase-game__listen-help-label"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'listen_help_variant_2' ) ); ?></span>
											<button type="button" class="llm-phrase-game__listen-help-play" data-listen-variant="voice-2"><?php echo $listen_help_play_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statico. ?><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'listen_help_play' ) ); ?></button>
										</span>
									</span>
								</span>
								</div>
							</div>
							<button type="button" class="llm-phrase-game__tool-accordion-toggle llm-phrase-game__inverted-hint" hidden aria-expanded="false">
								<span class="llm-phrase-game__tool-accordion-toggle-text llm-phrase-game__inverted-hint-text-label"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'play_inverted_hint' ) ); ?></span>
								<span class="llm-phrase-game__tool-accordion-chevron" aria-hidden="true"></span>
							</button>
							<div class="llm-phrase-game__inverted-hint-panel" hidden></div>
						</div>
						<div class="llm-phrase-game__actions">
							<div class="llm-phrase-game__continue-block llm-phrase-game__continue-block--1">
								<hr class="llm-phrase-game__divider llm-phrase-game__divider--before-continue" role="presentation" aria-hidden="true" />
								<?php echo self::render_continue_button( 'continue1' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
							</div>
						</div>
					</div>
				</div>
		<div class="llm-phrase-game__message" role="alert"></div>
		<div class="llm-phrase-game__db-status" role="status" aria-live="polite" aria-atomic="true"></div>
		<div class="llm-phrase-game__message-phase2 llm-phrase-game__message-solo" role="status" aria-live="polite"></div>
			<div class="llm-phrase-game__notes" hidden>
				<hr class="llm-phrase-game__divider llm-phrase-game__divider--before-notes" role="presentation" aria-hidden="true" />
				<div class="llm-phrase-game__notes-stack">
				<div class="llm-phrase-game__story-notes">
					<button type="button" class="llm-phrase-game__notes-acc llm-phrase-game__story-notes-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>-story-notes">
						<span class="llm-phrase-game__notes-toggle-emoji" aria-hidden="true">📓</span>
						<span class="llm-phrase-game__notes-acc-text"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'story_notes_toggle' ) ); ?></span>
						<span class="llm-phrase-game__tool-accordion-chevron" aria-hidden="true"></span>
					</button>
					<div class="llm-phrase-game__story-notes-panel" id="<?php echo esc_attr( $uid ); ?>-story-notes" data-story-intro="<?php echo esc_attr( $story_intro ); ?>" hidden>
						<p class="llm-phrase-game__story-notes-phrase" hidden></p>
						<p class="llm-phrase-game__story-notes-text"><?php echo esc_html( $story_intro ); ?></p>
					</div>
				</div>
				<div class="llm-phrase-game__notes-item">
				<button type="button" class="llm-phrase-game__notes-acc llm-phrase-game__notes-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>-notes-panel">
					<span class="llm-phrase-game__notes-toggle-emoji" aria-hidden="true">❤️</span>
					<span class="llm-phrase-game__notes-toggle-text"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'notes_toggle_show' ) ); ?></span>
					<span class="llm-phrase-game__tool-accordion-chevron" aria-hidden="true"></span>
				</button>
				<div class="llm-phrase-game__notes-panel" id="<?php echo esc_attr( $uid ); ?>-notes-panel" hidden>
					<button type="button" class="llm-learning-mode__change llm-phrase-game__show-field-trans" hidden>
						<span class="llm-phrase-game__show-field-pron-heart" aria-hidden="true">❤️</span>
						<?php echo esc_html( LLM_Phrase_Game_I18n::get( 'show_translation_under_field' ) ); ?>
					</button>
				</div>
				</div>
				<div class="llm-phrase-game__pron-tips">
					<button type="button" class="llm-phrase-game__notes-acc llm-phrase-game__pron-tips-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>-pron-tips">
						<span class="llm-phrase-game__notes-toggle-emoji" aria-hidden="true">🗣️</span>
						<span class="llm-phrase-game__notes-acc-text"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'pronunciation_tips_toggle' ) ); ?></span>
						<span class="llm-phrase-game__tool-accordion-chevron" aria-hidden="true"></span>
					</button>
					<div class="llm-phrase-game__pron-tips-panel" id="<?php echo esc_attr( $uid ); ?>-pron-tips" hidden>
						<div class="llm-phrase-game__pron-tips-text"></div>
						<button type="button" class="llm-learning-mode__change llm-phrase-game__show-field-pron">
							<span class="llm-phrase-game__show-field-pron-heart" aria-hidden="true">❤️</span>
							<?php echo esc_html( LLM_Phrase_Game_I18n::get( 'show_pronunciation_under_field' ) ); ?>
						</button>
					</div>
				</div>
				</div>
			</div>
			<div class="llm-phrase-game__phase1-feedback" hidden aria-live="polite"></div>
			<div class="llm-phrase-game__loading-notes" hidden aria-live="polite"></div>
			<div class="llm-phrase-game__analysis" hidden>
					<div class="llm-phrase-game__phrase-notes-wrap" hidden>
						<p class="llm-phrase-game__label-phrase-notes"><strong><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'label_phrase_notes' ) ); ?></strong></p>
						<div class="llm-phrase-game__phrase-notes"></div>
					</div>
					<div class="llm-phrase-game__your-phrase-wrap" hidden>
						<p class="llm-phrase-game__your-phrase-label"><strong><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'your_phrase_label' ) ); ?></strong></p>
						<p class="llm-phrase-game__your-phrase-text"></p>
					</div>
					<p class="llm-phrase-game__bravo"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'bravo_intro' ) ); ?></p>
					<p class="llm-phrase-game__label-main"><strong><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'label_main' ) ); ?></strong></p>
					<div class="llm-phrase-game__target"></div>
					<button type="button" class="llm-phrase-game__listen-target llm-phrase-game__peek-target" hidden aria-label="<?php echo esc_attr( LLM_Phrase_Game_I18n::get( 'peek_target_aria' ) ); ?>" title="<?php echo esc_attr( LLM_Phrase_Game_I18n::get( 'peek_target_aria' ) ); ?>">
						<span class="llm-phrase-game__listen-target-icon" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
						</span>
						<span class="llm-phrase-game__listen-target-text"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'peek_target_label' ) ); ?></span>
					</button>
					<p class="llm-phrase-game__label-notes" hidden><strong><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'label_notes' ) ); ?></strong></p>
					<div class="llm-phrase-game__grammar"></div>
					<p class="llm-phrase-game__label-pronunciation" hidden><strong><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'label_pronunciation' ) ); ?></strong></p>
					<div class="llm-phrase-game__pronunciation"></div>
					<p class="llm-phrase-game__label-ipa" hidden><strong><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'label_ipa' ) ); ?></strong></p>
					<div class="llm-phrase-game__ipa"></div>
					<p class="llm-phrase-game__label-approx" hidden><strong><?php echo esc_html( $label_approx ); ?></strong></p>
					<div class="llm-phrase-game__approx"></div>
					<p class="llm-phrase-game__label-alt" hidden><em><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'label_alt' ) ); ?></em></p>
					<div class="llm-phrase-game__alt" id="<?php echo esc_attr( $uid ); ?>-alt-panel" hidden></div>
				</div>
				<div class="llm-phrase-game__phase llm-phrase-game__phase--2" hidden>
					<label class="screen-reader-text" for="<?php echo esc_attr( $uid ); ?>-input2"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'sr_rewrite' ) ); ?></label>
					<div class="llm-phrase-game__compose llm-phrase-game__compose--phase2">
						<hr class="llm-phrase-game__divider llm-phrase-game__divider--phase2-before" role="presentation" aria-hidden="true" />
					<div class="llm-phrase-game__phase2-recap" aria-hidden="true">
						<p class="llm-phrase-game__phase2-recap__counter"></p>
						<div class="llm-phrase-game__interface-row llm-phrase-game__interface-row--phase2">
							<span class="llm-phrase-game__lang-flag llm-phrase-game__lang-flag--source" aria-hidden="true"></span>
							<p class="llm-phrase-game__phase2-recap__interface"></p>
							<div class="llm-phrase-game__listen-target-wrap llm-phrase-game__listen-target-wrap--phase2">
								<div class="llm-phrase-game__listen-cluster">
							<?php echo self::render_listen_voices( 'llm-phrase-game__listen-target--phase2', $listen_target_label, $listen_target_aria, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapato. ?>
							<?php echo self::render_listen_switch(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapato. ?>
							<?php echo self::render_listen_slow(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapato. ?>
								</div>
							</div>
						</div>
						<p class="llm-phrase-game__prompt llm-phrase-game__prompt--rewrite">
							<span class="llm-phrase-game__prompt-text llm-phrase-game__prompt-text--rewrite"></span>
						</p>
						<p class="llm-phrase-game__phase2-recap__prompt"></p>
					</div>
					<div class="llm-phrase-game__input-block">
						<div class="llm-phrase-game__input-shell">
							<span class="llm-phrase-game__lang-flag llm-phrase-game__lang-flag--write" aria-hidden="true"></span>
							<textarea rows="1" wrap="off" id="<?php echo esc_attr( $uid ); ?>-input2" class="llm-phrase-game__input llm-phrase-game__input--2" name="llm_phrase_answer_2_<?php echo esc_attr( $uid ); ?>" autocomplete="off" autocorrect="off" autocapitalize="none" spellcheck="false" inputmode="none" enterkeyhint="done" virtualkeyboardpolicy="manual" readonly data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other"></textarea>
						</div>
						<div class="llm-phrase-game__clear-wrap llm-phrase-game__clear-wrap--2 llm-phrase-game__action-fade" hidden>
							<?php echo self::render_clear_input_row( '2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
						</div>
						<div class="llm-phrase-game__mic-row">
							<?php echo self::render_mic_cluster( $uid, '2', $deepgram_ready, $azure_ready, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapato. ?>
						</div>
						<?php echo self::render_random_words_block( $uid, '2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
						<?php echo self::render_extra_chars_block( $uid, '2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
						<?php echo self::render_keyboard_block( $uid, '2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
					</div>
					<div class="llm-phrase-game__actions">
						<div class="llm-phrase-game__continue-block llm-phrase-game__continue-block--2">
							<hr class="llm-phrase-game__divider llm-phrase-game__divider--before-continue" role="presentation" aria-hidden="true" />
							<?php echo self::render_continue_button( 'continue2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
						</div>
					</div>
					</div>
					<div class="llm-phrase-game__message-phase2" role="status" aria-live="polite"></div>
				</div>
			</div>
		<div class="llm-phrase-game__done" hidden>
			<p class="llm-phrase-game__done-text"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'done_all' ) ); ?></p>
			<button type="button" class="llm-phrase-game__restart-btn button"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'story_progress_restart' ) ); ?></button>
		</div>
		<?php echo self::render_learning_mode_ui( $uid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
			</div>
			<?php if ( $has_crossword ) : ?>
			<div class="llm-story-view llm-story-view--crossword" id="llm-story-view-crossword-<?php echo esc_attr( (string) (int) $story_id ); ?>" role="tabpanel" aria-labelledby="llm-story-tab-crossword-<?php echo esc_attr( (string) (int) $story_id ); ?>" hidden>
				<?php echo $cw_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML dello shortcode cruciverba. ?>
			</div>
			<?php endif; ?>
			</div>
			</div>
			<div class="llm-story-layout__prefs">
				<?php if ( $is_wp_admin ) : ?>
				<div class="llm-phrase-game__admin-edits">
					<?php
					self::render_admin_edit_link( 'notes', LLM_Phrase_Game_I18n::get( 'notes_edit_notes' ) );
					self::render_admin_edit_link( 'grammar', LLM_Phrase_Game_I18n::get( 'notes_edit_grammar' ) );
					self::render_admin_edit_link( 'alt', LLM_Phrase_Game_I18n::get( 'notes_edit_alt' ) );
					self::render_admin_edit_link( 'pronunciation', LLM_Phrase_Game_I18n::get( 'notes_edit_pronunciation' ) );
					self::render_admin_edit_link( 'ipa', LLM_Phrase_Game_I18n::get( 'notes_edit_ipa' ) );
					self::render_admin_edit_link( 'approx', LLM_Phrase_Game_I18n::get( 'notes_edit_approx' ) );
					?>
					<button type="button" class="llm-game-theme__btn llm-story-layout-switch__btn llm-phrase-game__admin-edit llm-phrase-game__admin-fill-solution"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'admin_fill_solution' ) ); ?></button>
				</div>
				<?php endif; ?>
		<?php echo self::render_game_theme_switcher( $game_theme ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
		<?php echo self::render_story_layout_switcher( $story_layout ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
			</div>
		<?php
		if ( self::current_user_can_edit_notes( $story_id ) ) {
			self::render_notes_edit_modal();
		}
		?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param int $story_id ID storia.
	 * @return bool
	 */
	public static function current_user_can_edit_notes( $story_id = 0 ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$user = wp_get_current_user();
		if ( ! $user instanceof WP_User ) {
			return false;
		}
		return in_array( 'administrator', (array) $user->roles, true );
	}

	/**
	 * Link discreto per aprire l'editor (solo markup admin).
	 *
	 * @param string $field notes|grammar|alt|pronunciation.
	 * @param string $label Testo pulsante.
	 */
	private static function render_admin_edit_link( $field, $label ) {
		$field = sanitize_key( $field );
		if ( ! in_array( $field, array( 'notes', 'grammar', 'alt', 'pronunciation', 'ipa', 'approx' ), true ) ) {
			return;
		}
		echo '<button type="button" class="llm-game-theme__btn llm-story-layout-switch__btn llm-phrase-game__admin-edit" data-llm-edit-field="' . esc_attr( $field ) . '">' . esc_html( $label ) . '</button>';
	}

	/**
	 * Popup editor appunti (solo chi può modificare la storia).
	 */
	private static function render_notes_edit_modal() {
		?>
		<div id="llm-fe-notes-modal" class="llm-fe-notes-modal" hidden>
			<div class="llm-fe-notes-modal__backdrop" data-llm-notes-close="1"></div>
			<div class="llm-fe-notes-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="llm-fe-notes-modal-title">
				<div class="llm-fe-notes-modal__header">
					<h2 id="llm-fe-notes-modal-title" class="llm-fe-notes-modal__title"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'notes_edit_title' ) ); ?></h2>
					<button type="button" class="llm-fe-notes-modal__close" data-llm-notes-close="1" aria-label="<?php echo esc_attr( LLM_Phrase_Game_I18n::get( 'notes_edit_cancel' ) ); ?>">&times;</button>
				</div>
				<div class="llm-fe-notes-modal__body">
					<textarea id="llm-fe-notes-editor" class="llm-fe-notes-editor" rows="18"></textarea>
				</div>
				<div class="llm-fe-notes-modal__footer">
					<p class="llm-fe-notes-modal__status" hidden></p>
					<button type="button" class="llm-fe-notes-modal__cancel button" data-llm-notes-close="1"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'notes_edit_cancel' ) ); ?></button>
					<button type="button" class="llm-fe-notes-modal__save button button-primary"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'notes_edit_save' ) ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Hero editoriale sopra il gioco (HTML+CSS; non modifica .llm-phrase-game).
	 *
	 * @param int $story_id     ID storia.
	 * @param int $phrase_count Numero frasi.
	 * @return string
	 */
	private static function render_story_hero( $story_id, $phrase_count ) {
		$story_id     = absint( $story_id );
		$phrase_count = max( 0, (int) $phrase_count );
		if ( ! $story_id ) {
			return '';
		}

		$known  = sanitize_key( (string) get_post_meta( $story_id, LLM_Story_Meta::KNOWN_LANG, true ) );
		$target = sanitize_key( (string) get_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, true ) );
		$ui     = $known ? $known : ( class_exists( 'LLM_Phrase_Game_I18n' ) ? LLM_Phrase_Game_I18n::lang() : 'it' );

		$title_known  = get_the_title( $story_id );
		$title_target = trim( (string) get_post_meta( $story_id, LLM_Story_Meta::TITLE_TARGET, true ) );

		// Titolo inglese in evidenza; traduzione sotto. Altre coppie: titolo nella lingua da imparare.
		if ( 'en' === $target ) {
			$main_title = $title_target !== '' ? $title_target : $title_known;
			$subtitle   = ( $title_target !== '' && $title_known !== '' && strcasecmp( $title_target, $title_known ) !== 0 )
				? $title_known
				: '';
		} elseif ( 'en' === $known ) {
			$main_title = $title_known;
			$subtitle   = ( $title_target !== '' && strcasecmp( $title_target, $title_known ) !== 0 )
				? $title_target
				: '';
		} else {
			$main_title = $title_target !== '' ? $title_target : $title_known;
			$subtitle   = ( $title_target !== '' && $title_known !== '' && strcasecmp( $title_target, $title_known ) !== 0 )
				? $title_known
				: '';
		}

		$plot = trim( (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_PLOT, true ) );
		if ( '' === $plot ) {
			$plot = trim( (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_CARD_TEXT, true ) );
		}

		$cefr      = (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_CEFR_LEVEL, true );
		$cefr_code = '';
		if ( $cefr && preg_match( '/\b([ABC][12])\b/i', $cefr, $m ) ) {
			$cefr_code = strtoupper( $m[1] );
		}

		$is_song = self::is_song_story( $story_id );
		$unit    = $is_song ? self::hero_verse_word( $ui, $phrase_count ) : self::hero_phrase_word( $ui, $phrase_count );

		$cat_names = array();
		$raw_cats  = get_the_terms( $story_id, 'category' );
		if ( ! empty( $raw_cats ) && ! is_wp_error( $raw_cats ) ) {
			$skip_slugs = array( 'uncategorized', 'senza-categoria' );
			foreach ( $raw_cats as $cat ) {
				if ( in_array( $cat->slug, $skip_slugs, true ) ) {
					continue;
				}
				if ( preg_match( '/^[a-z]{2,3}-[a-z]{2,10}$/', $cat->slug ) ) {
					continue;
				}
				$name = $cat->name;
				if ( class_exists( 'LLM_Category_Translations' ) ) {
					$name = LLM_Category_Translations::get_translated_name( $cat, $ui );
				}
				if ( '' !== trim( $name ) ) {
					$cat_names[] = $name;
				}
			}
		}

		$thumb_id      = get_post_thumbnail_id( $story_id );
		$thumb_url     = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'large' ) : '';
		$target_name   = self::hero_target_lang_name( $ui, $target );
		$known_name    = self::hero_target_lang_name( $ui, $known );
		$headline_lang = ( 'en' === $target || 'en' === $known ) ? 'en' : $target;
		$headline_flag = class_exists( 'LLM_Languages' ) ? LLM_Languages::flag_emoji( $headline_lang ) : '';
		$target_flag   = class_exists( 'LLM_Languages' ) ? LLM_Languages::flag_emoji( $target ) : '';
		$about_label   = self::hero_ui_string( $ui, 'about' );
		$plot_label    = self::hero_ui_string( $ui, 'plot' );
		$level_label   = self::hero_ui_string( $ui, 'level' );
		$lang_label    = self::hero_ui_string( $ui, 'language' );
		$phrases_label = $is_song ? self::hero_ui_string( $ui, 'duration' ) : self::hero_ui_string( $ui, 'phrases' );
		$phrases_notes = $is_song
			? sprintf( '%d %s', $phrase_count, $unit )
			: self::hero_ui_string( $ui, 'phrases_notes' );
		$known_for     = self::hero_known_for_line( $ui, $known_name );

		ob_start();
		?>
		<div class="llm-story-pagehead">
		<header class="llm-story-hero">
			<div class="llm-story-hero__panel">
				<div class="llm-story-hero__copy">
					<?php if ( $main_title ) : ?>
						<div class="llm-story-hero__title-block">
							<?php if ( $headline_flag ) : ?>
								<span class="llm-story-hero__learn-flag" aria-hidden="true"><?php echo esc_html( $headline_flag ); ?></span>
							<?php endif; ?>
							<h1 class="llm-story-hero__title"><?php echo esc_html( $main_title ); ?></h1>
						</div>
					<?php endif; ?>
					<?php if ( $subtitle ) : ?>
						<p class="llm-story-hero__subtitle"><?php echo esc_html( $subtitle ); ?></p>
					<?php endif; ?>
					<p class="llm-story-hero__facts">
						<?php if ( $cefr_code ) : ?>
							<span class="llm-story-hero__fact llm-story-hero__fact--level"><?php echo esc_html( self::hero_ui_string( $ui, 'story_level' ) . ' ' . $cefr_code ); ?></span>
							<span class="llm-story-hero__fact-dot" aria-hidden="true">·</span>
						<?php endif; ?>
						<?php if ( $phrase_count > 0 ) : ?>
							<span class="llm-story-hero__fact"><?php echo esc_html( $phrase_count . ' ' . $unit ); ?></span>
							<span class="llm-story-hero__fact-dot" aria-hidden="true">·</span>
						<?php endif; ?>
						<span class="llm-story-hero__fact llm-story-hero__fact--id">id <strong><?php echo esc_html( (string) (int) $story_id ); ?></strong></span>
					</p>
				</div>
			</div>
			<div
				class="llm-story-hero__media<?php echo $thumb_url ? '' : ' llm-story-hero__media--empty'; ?>"
				<?php if ( $thumb_url ) : ?>
					style="background-image:url('<?php echo esc_url( $thumb_url ); ?>');"
					role="img"
					aria-label="<?php echo esc_attr( $main_title ); ?>"
				<?php else : ?>
					aria-hidden="true"
				<?php endif; ?>
			></div>
		</header>
		<?php if ( $plot ) : ?>
			<div class="llm-story-hero__plot-block">
				<p class="llm-story-hero__plot-label"><?php echo esc_html( $plot_label ); ?></p>
				<p class="llm-story-hero__plot"><?php echo esc_html( $plot ); ?></p>
			</div>
		<?php endif; ?>
		<?php LLM_Story_Cast::render_frontend( (int) $story_id, self::hero_ui_string( $ui, 'cast' ) ); ?>
		<section class="llm-story-about" aria-label="<?php echo esc_attr( $about_label ); ?>">
			<div class="llm-story-about__stats">
				<div class="llm-story-about__stat">
					<span class="llm-story-about__stat-label"><?php echo esc_html( $level_label ); ?></span>
					<div class="llm-story-about__stat-main">
						<span class="llm-story-about__stat-icon llm-story-about__stat-icon--level" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.2H22l-6 4.4 2.3 7.2L12 16.8 5.7 20.8 8 13.6 2 9.2h7.6z"/></svg>
						</span>
						<span class="llm-story-about__stat-value"><?php echo esc_html( $cefr_code ? $cefr_code : '—' ); ?></span>
					</div>
					<?php if ( $target_name ) : ?>
						<span class="llm-story-about__stat-sub"><?php echo esc_html( $target_name ); ?></span>
					<?php endif; ?>
				</div>
				<div class="llm-story-about__stat">
					<span class="llm-story-about__stat-label"><?php echo esc_html( $phrases_label ); ?></span>
					<div class="llm-story-about__stat-main">
						<span class="llm-story-about__stat-icon <?php echo $is_song ? 'llm-story-about__stat-icon--duration' : 'llm-story-about__stat-icon--phrases'; ?>" aria-hidden="true">
							<?php if ( $is_song ) : ?>
							<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3v10.55A4 4 0 1 0 14 17V7h4V3h-6z"/></svg>
							<?php else : ?>
							<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M4 4h16v12H7l-3 3V4zm4 5v2h8V9H8zm0 4v2h5v-2H8z"/></svg>
							<?php endif; ?>
						</span>
						<span class="llm-story-about__stat-value"><?php echo esc_html( (string) $phrase_count ); ?></span>
					</div>
					<span class="llm-story-about__stat-sub"><?php echo esc_html( $phrases_notes ); ?></span>
				</div>
				<div class="llm-story-about__stat">
					<span class="llm-story-about__stat-label"><?php echo esc_html( $lang_label ); ?></span>
					<div class="llm-story-about__stat-main">
						<?php if ( $target_flag ) : ?>
							<span class="llm-story-about__stat-flag" aria-hidden="true"><?php echo esc_html( $target_flag ); ?></span>
						<?php endif; ?>
						<span class="llm-story-about__stat-value llm-story-about__stat-value--lang"><?php echo esc_html( $target_name ? $target_name : '—' ); ?></span>
					</div>
					<?php if ( $known_for ) : ?>
						<span class="llm-story-about__stat-sub"><?php echo esc_html( $known_for ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( $cat_names ) : ?>
			<div class="llm-story-about__tags">
				<?php foreach ( $cat_names as $cat_name ) : ?>
					<span class="llm-story-about__tag"><?php echo esc_html( $cat_name ); ?></span>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Pulsanti compatti Frasi / Cruciverba, in cima alla colonna di studio.
	 *
	 * @param int  $story_id      ID storia.
	 * @param bool $has_crossword Se c'è il cruciverba.
	 * @return string
	 */
	private static function render_story_view_tabs( $story_id, $has_crossword ) {
		if ( ! $has_crossword ) {
			return '';
		}
		$story_id = absint( $story_id );
		$known    = sanitize_key( (string) get_post_meta( $story_id, LLM_Story_Meta::KNOWN_LANG, true ) );
		$ui       = $known ? $known : ( class_exists( 'LLM_Phrase_Game_I18n' ) ? LLM_Phrase_Game_I18n::lang() : 'it' );
		$sid      = (string) $story_id;
		ob_start();
		?>
		<nav class="llm-story-view-tabs" role="tablist" aria-label="<?php echo esc_attr( self::hero_ui_string( $ui, 'tab_phrases' ) . ' / ' . self::hero_ui_string( $ui, 'tab_crossword' ) ); ?>">
			<button type="button" class="llm-game-theme__btn llm-story-layout-switch__btn llm-phrase-game__helper-acc llm-story-view-tabs__btn is-active" role="tab" id="llm-story-tab-phrases-<?php echo esc_attr( $sid ); ?>" aria-selected="true" aria-controls="llm-story-view-phrases-<?php echo esc_attr( $sid ); ?>" data-llm-story-view="phrases">
				<span class="llm-phrase-game__helper-acc-emoji" aria-hidden="true">💬</span>
				<span class="llm-phrase-game__helper-acc-text"><?php echo esc_html( self::hero_ui_string( $ui, 'tab_phrases' ) ); ?></span>
			</button>
			<button type="button" class="llm-game-theme__btn llm-story-layout-switch__btn llm-phrase-game__helper-acc llm-story-view-tabs__btn" role="tab" id="llm-story-tab-crossword-<?php echo esc_attr( $sid ); ?>" aria-selected="false" aria-controls="llm-story-view-crossword-<?php echo esc_attr( $sid ); ?>" data-llm-story-view="crossword">
				<span class="llm-phrase-game__helper-acc-emoji" aria-hidden="true">🧩</span>
				<span class="llm-phrase-game__helper-acc-text"><?php echo esc_html( self::hero_ui_string( $ui, 'tab_crossword' ) ); ?></span>
			</button>
		</nav>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Posizione 1-based della storia nella rivista della coppia, 0 se assente.
	 *
	 * @param int $story_id ID storia.
	 * @return int
	 */
	private static function story_ordinal_in_magazine( $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id || ! class_exists( 'LLM_Magazine' ) ) {
			return 0;
		}

		$known  = sanitize_key( (string) get_post_meta( $story_id, LLM_Story_Meta::KNOWN_LANG, true ) );
		$target = sanitize_key( (string) get_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, true ) );
		if ( ! $known || ! $target ) {
			return 0;
		}

		$candidates = array();
		$home       = LLM_Magazine::find_homepage_id( $known, $target );
		if ( $home ) {
			$candidates[] = $home;
		}

		$q = new WP_Query(
			array(
				'post_type'              => LLM_Magazine::CPT,
				'post_status'            => array( 'publish', 'draft', 'private' ),
				'posts_per_page'         => 12,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => LLM_Magazine::META_KNOWN,
						'value' => $known,
					),
					array(
						'key'   => LLM_Magazine::META_TARGET,
						'value' => $target,
					),
				),
			)
		);
		foreach ( $q->posts as $mid ) {
			$candidates[] = (int) $mid;
		}
		$candidates = array_values( array_unique( array_filter( $candidates ) ) );

		foreach ( $candidates as $mid ) {
			foreach ( array( LLM_Magazine::get_story_ids( $mid ), LLM_Magazine::get_music_ids( $mid ) ) as $list ) {
				foreach ( $list as $i => $sid ) {
					if ( (int) $sid === $story_id ) {
						return (int) $i + 1;
					}
				}
			}
		}

		return 0;
	}

	/**
	 * @param string $ui    Lingua UI.
	 * @param int    $count Numero frasi.
	 * @return string
	 */
	private static function hero_phrase_word( $ui, $count ) {
		$count = (int) $count;
		$map   = array(
			'it' => $count === 1 ? 'frase' : 'frasi',
			'en' => $count === 1 ? 'phrase' : 'phrases',
			'pl' => $count === 1 ? 'zdanie' : 'zdań',
			'es' => $count === 1 ? 'frase' : 'frases',
		);
		$ui    = sanitize_key( (string) $ui );
		return isset( $map[ $ui ] ) ? $map[ $ui ] : $map['it'];
	}

	/**
	 * @param string $ui    Lingua UI.
	 * @param int    $count Numero versi.
	 * @return string
	 */
	private static function hero_verse_word( $ui, $count ) {
		$count = (int) $count;
		$map   = array(
			'it' => $count === 1 ? 'verso' : 'versi',
			'en' => $count === 1 ? 'verse' : 'verses',
			'pl' => $count === 1 ? 'wers' : 'wersów',
			'es' => $count === 1 ? 'verso' : 'versos',
		);
		$ui    = sanitize_key( (string) $ui );
		return isset( $map[ $ui ] ) ? $map[ $ui ] : $map['it'];
	}

	/**
	 * Etichetta livello: "livello B1 polacco".
	 *
	 * @param string $ui        Lingua UI.
	 * @param string $cefr_code A1–C2 oppure ''.
	 * @param string $target    Lingua da imparare.
	 * @return string
	 */
	private static function hero_level_label( $ui, $cefr_code, $target ) {
		$ui     = sanitize_key( (string) $ui );
		$target = sanitize_key( (string) $target );
		$word   = self::hero_ui_string( $ui, 'level' );
		$lang   = self::hero_target_lang_name( $ui, $target );
		if ( '' !== $lang ) {
			$lang = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $lang, 'UTF-8' ) : strtoupper( $lang );
		}
		$cefr   = strtoupper( sanitize_key( (string) $cefr_code ) );

		$parts = array_filter(
			array(
				$word,
				$cefr,
				$lang,
			)
		);
		return implode( ' ', $parts );
	}

	/**
	 * Nome della lingua target nella lingua UI (minuscolo in italiano).
	 *
	 * @param string $ui     Lingua UI.
	 * @param string $target Codice target.
	 * @return string
	 */
	private static function hero_target_lang_name( $ui, $target ) {
		$names = array(
			'it' => array(
				'en' => 'inglese',
				'it' => 'italiano',
				'pl' => 'polacco',
				'es' => 'spagnolo',
			),
			'en' => array(
				'en' => 'English',
				'it' => 'Italian',
				'pl' => 'Polish',
				'es' => 'Spanish',
			),
			'pl' => array(
				'en' => 'angielski',
				'it' => 'włoski',
				'pl' => 'polski',
				'es' => 'hiszpański',
			),
			'es' => array(
				'en' => 'inglés',
				'it' => 'italiano',
				'pl' => 'polaco',
				'es' => 'español',
			),
		);
		$ui     = sanitize_key( (string) $ui );
		$target = sanitize_key( (string) $target );
		if ( isset( $names[ $ui ][ $target ] ) ) {
			return $names[ $ui ][ $target ];
		}
		if ( isset( $names['it'][ $target ] ) ) {
			return $names['it'][ $target ];
		}
		return '';
	}

	/**
	 * @param string $ui  Lingua UI.
	 * @param string $key level.
	 * @return string
	 */
	private static function hero_ui_string( $ui, $key ) {
		$ui  = sanitize_key( (string) $ui );
		$set = array(
			'level'    => array(
				'it' => 'livello',
				'en' => 'level',
				'pl' => 'poziom',
				'es' => 'nivel',
			),
			'story_level' => array(
				'it' => 'livello storia',
				'en' => 'story level',
				'pl' => 'poziom historii',
				'es' => 'nivel de la historia',
			),
			'duration' => array(
				'it' => 'durata',
				'en' => 'duration',
				'pl' => 'czas',
				'es' => 'duración',
			),
			'about'    => array(
				'it' => 'Riguardo',
				'en' => 'About',
				'pl' => 'Informacje',
				'es' => 'Acerca de',
			),
			'plot'     => array(
				'it' => 'Trama',
				'en' => 'Plot',
				'pl' => 'Fabuła',
				'es' => 'Trama',
			),
			'cast'     => array(
				'it' => 'Cast',
				'en' => 'Cast',
				'pl' => 'Obsada',
				'es' => 'Reparto',
			),
			'phrases'  => array(
				'it' => 'frasi',
				'en' => 'phrases',
				'pl' => 'zdania',
				'es' => 'frases',
			),
			'tab_phrases' => array(
				'it' => 'Frasi',
				'en' => 'Phrases',
				'pl' => 'Zdania',
				'es' => 'Frases',
			),
			'tab_crossword' => array(
				'it' => 'Cruciverba',
				'en' => 'Crossword',
				'pl' => 'Krzyżówka',
				'es' => 'Crucigrama',
			),
			'language' => array(
				'it' => 'lingua',
				'en' => 'language',
				'pl' => 'język',
				'es' => 'idioma',
			),
			'pair'     => array(
				'it' => 'lingue',
				'en' => 'languages',
				'pl' => 'języki',
				'es' => 'idiomas',
			),
			'category' => array(
				'it' => 'categoria',
				'en' => 'category',
				'pl' => 'kategoria',
				'es' => 'categoría',
			),
			'grammar'  => array(
				'it' => 'grammatica',
				'en' => 'grammar',
				'pl' => 'gramatyka',
				'es' => 'gramática',
			),
			'phrases_notes' => array(
				'it' => 'frasi con appunti di approfondimento',
				'en' => 'phrases with extra notes',
				'pl' => 'zdania z notatkami',
				'es' => 'frases con apuntes',
			),
		);
		if ( ! isset( $set[ $key ] ) ) {
			return '';
		}
		return isset( $set[ $key ][ $ui ] ) ? $set[ $key ][ $ui ] : $set[ $key ]['it'];
	}

	/**
	 * Sottotitolo riquadro lingua: "per chi conosce l'italiano".
	 *
	 * @param string $ui         Lingua UI.
	 * @param string $known_name Nome della lingua nota.
	 * @return string
	 */
	private static function hero_known_for_line( $ui, $known_name ) {
		$known_name = trim( (string) $known_name );
		if ( '' === $known_name ) {
			return '';
		}
		$ui = sanitize_key( (string) $ui );
		if ( 'it' === $ui ) {
			$article = preg_match( '/^[aeiouàèéìòù]/iu', $known_name ) ? "l'" : 'il ';
			return 'per chi conosce ' . $article . $known_name;
		}
		$map = array(
			'en' => 'for speakers of %s',
			'pl' => 'dla osób znających język %s',
			'es' => 'para quien conoce el %s',
		);
		$tpl = isset( $map[ $ui ] ) ? $map[ $ui ] : $map['en'];
		return sprintf( $tpl, $known_name );
	}

	/**
	 * Temi grammaticali della storia, già spezzati.
	 *
	 * @param string $raw Meta grezza.
	 * @return string[]
	 */
	private static function hero_grammar_topics( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return array();
		}
		$lines = preg_split( '/[\r\n;]+/u', $raw );
		$out   = array();
		if ( ! is_array( $lines ) ) {
			return array();
		}
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			// Virgole solo se la riga non ha parentesi (es. "broke, stole" dentro un topic).
			if ( false !== strpos( $line, ',' ) && false === strpos( $line, '(' ) ) {
				$bits = preg_split( '/\s*,\s*/u', $line );
				if ( is_array( $bits ) ) {
					foreach ( $bits as $bit ) {
						$bit = trim( (string) $bit );
						if ( '' !== $bit ) {
							$out[] = $bit;
						}
					}
				}
				continue;
			}
			$out[] = $line;
		}
		return $out;
	}

	/**
	 * Barra "Modalità apprendimento" + popup di scelta.
	 *
	 * @param string $uid Prefisso ID univoco dell'istanza.
	 * @return string HTML già escapato.
	 */
	private static function render_learning_mode_ui( $uid ) {
		$modes        = LLM_Learning_Modes::visible();
		$current      = LLM_Learning_Modes::current();
		$extras       = LLM_Learning_Modes::options();
		$extras_on    = LLM_Learning_Modes::current_options();
		$radio_name   = $uid . '-learning-mode';
		$dialog_id    = $uid . '-learning-mode-dialog';
		$title_id     = $uid . '-learning-mode-title';

		ob_start();
		?>
		<div class="llm-learning-mode" data-current-mode="<?php echo esc_attr( $current ); ?>">
			<?php echo self::render_jump_return_button(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
			<p class="llm-learning-mode__bar">
				<span class="llm-learning-mode__label"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'learning_mode_label' ) ); ?></span>
				<span class="llm-learning-mode__value"><?php echo esc_html( LLM_Learning_Modes::label( $current ) ); ?></span>
				<button type="button" class="llm-learning-mode__change" aria-haspopup="dialog" aria-controls="<?php echo esc_attr( $dialog_id ); ?>"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'learning_mode_change' ) ); ?></button>
			</p>
			<div class="llm-learning-mode__overlay" id="<?php echo esc_attr( $dialog_id ); ?>" hidden>
				<div class="llm-learning-mode__dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
					<button type="button" class="llm-learning-mode__close" aria-label="<?php echo esc_attr( LLM_Phrase_Game_I18n::get( 'learning_mode_close' ) ); ?>">&times;</button>
					<h3 class="llm-learning-mode__title" id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'learning_mode_title' ) ); ?></h3>
					<p class="llm-learning-mode__intro"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'learning_mode_intro' ) ); ?></p>
					<div class="llm-learning-mode__list">
						<?php foreach ( $modes as $mode ) : ?>
						<label class="llm-learning-mode__option<?php echo $mode['id'] === $current ? ' llm-learning-mode__option--active' : ''; ?>">
							<input type="radio" class="llm-learning-mode__radio" name="<?php echo esc_attr( $radio_name ); ?>" value="<?php echo esc_attr( $mode['id'] ); ?>" <?php checked( $mode['id'], $current ); ?> />
							<span class="llm-learning-mode__option-body">
								<span class="llm-learning-mode__option-name"><?php echo esc_html( $mode['label'] ); ?></span>
								<span class="llm-learning-mode__option-desc"><?php echo esc_html( $mode['description'] ); ?></span>
							</span>
						</label>
						<?php endforeach; ?>
					</div>
					<?php if ( ! empty( $extras ) ) : ?>
					<div class="llm-learning-mode__extras">
						<hr class="llm-learning-mode__extras-divider" role="presentation" aria-hidden="true" />
						<p class="llm-learning-mode__extras-title"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'learning_mode_tools_title' ) ); ?></p>
						<div class="llm-learning-mode__extras-list">
							<?php foreach ( $extras as $extra ) : ?>
							<label class="llm-learning-mode__option llm-learning-mode__option--check<?php echo in_array( $extra['id'], $extras_on, true ) ? ' llm-learning-mode__option--active' : ''; ?>">
								<input type="checkbox" class="llm-learning-mode__check" value="<?php echo esc_attr( $extra['id'] ); ?>" <?php checked( in_array( $extra['id'], $extras_on, true ) ); ?> />
								<span class="llm-learning-mode__option-body">
									<span class="llm-learning-mode__option-name"><?php echo esc_html( $extra['label'] ); ?></span>
									<span class="llm-learning-mode__option-desc"><?php echo esc_html( $extra['description'] ); ?></span>
								</span>
							</label>
							<?php endforeach; ?>
						</div>
					</div>
					<?php endif; ?>
					<p class="llm-learning-mode__msg" role="status" aria-live="polite"></p>
					<div class="llm-learning-mode__actions">
						<button type="button" class="llm-learning-mode__cancel"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'learning_mode_cancel' ) ); ?></button>
						<button type="button" class="llm-learning-mode__save"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'learning_mode_save' ) ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Preconnect Google Fonts (Roboto + Barlow per i titoli).
	 */
	public static function print_font_preconnect() {
		echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
		echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
	}

	/**
	 * Immagini del flusso per lo script frontend.
	 *
	 * @param int $story_id ID storia.
	 * @return array<int, array{url:string, alt:string, afterPhraseIndex:int}>
	 */
	private static function media_blocks_for_script( $story_id ) {
		$blocks = LLM_Story_Repository::get_media_blocks( $story_id );
		$out    = array();
		foreach ( $blocks as $b ) {
			$aid = isset( $b['attachment_id'] ) ? (int) $b['attachment_id'] : 0;
			$url = $aid ? wp_get_attachment_image_url( $aid, 'medium' ) : '';
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}
			$alt = $aid ? (string) get_post_meta( $aid, '_wp_attachment_image_alt', true ) : '';
			$out[] = array(
				'url'               => $url,
				'alt'               => $alt,
				'afterPhraseIndex'  => isset( $b['after_phrase_index'] ) ? (int) $b['after_phrase_index'] : -1,
			);
		}
		return $out;
	}

	/**
	 * @param int $story_id ID storia.
	 */
	public static function enqueue_assets( $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return;
		}

		$phrases = LLM_Story_Repository::get_phrases( $story_id );
		$boot      = array();
		foreach ( $phrases as $i => $row ) {
			$boot[] = array(
				'index'     => $i,
				'interface' => isset( $row['interface'] ) ? $row['interface'] : '',
				'target'    => isset( $row['target'] ) ? $row['target'] : '',
				'grammar'   => isset( $row['grammar'] ) ? $row['grammar'] : '',
				'alt'       => isset( $row['alt'] ) ? $row['alt'] : '',
				'notes'     => isset( $row['notes'] ) ? $row['notes'] : '',
				'pronunciation' => isset( $row['pronunciation'] ) ? $row['pronunciation'] : '',
				'ipa'           => isset( $row['ipa'] ) ? $row['ipa'] : '',
				'approx'        => isset( $row['approx'] ) ? $row['approx'] : '',
				'audioMale'     => isset( $row['audio_male_url'] ) ? (string) $row['audio_male_url'] : '',
				'audioFemale'   => isset( $row['audio_female_url'] ) ? (string) $row['audio_female_url'] : '',
			);
		}

		$target_code = (string) get_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, true );
		// In questa UI:
		// - interfaceLang* = lingua “fonte” (quella della traduzione che vedi e in cui scrivi nella fase 1)
		// - targetLang*    = lingua “obiettivo” (quella della frase attesa)
		// Le bandiere DEVONO quindi venire da `KNOWN_LANG/TARGET_LANG` della storia, non dalla lingua corrente dell’utente.
		$interface_code = (string) get_post_meta( $story_id, LLM_Story_Meta::KNOWN_LANG, true );
		if ( '' === $interface_code ) {
			$interface_code = LLM_Phrase_Game_I18n::lang();
		}

		$media_blocks    = self::media_blocks_for_script( $story_id );
		$can_edit_notes  = self::current_user_can_edit_notes( $story_id );

		$n_phrases         = count( $phrases );
		$uid               = is_user_logged_in() ? get_current_user_id() : 0;
		$game_finished     = false;
		$saved_phrase_ix   = 0;
		$saved_step        = LLM_Story_Game_Progress::STEP_TRANSLATE;
		$resume_analysis   = null;
		$completed_targets = array();
		$is_phrase_jump    = false;
		$return_phrase_n   = 0;
		$bar_phrases_count = 0;
		$resolved          = null;

		if ( $uid > 0 && $n_phrases > 0 ) {
			$resolved = LLM_Story_Game_Progress::resolve_for_user( $uid, $story_id, $n_phrases );
			if ( $resolved && ! empty( $resolved['finished'] ) ) {
				$game_finished   = true;
				$saved_phrase_ix = $n_phrases;
			} elseif ( $resolved ) {
				$saved_phrase_ix = (int) $resolved['phrase_index'];
				$saved_step      = (int) $resolved['step'];
				// A ogni caricamento pagina si riparte sempre dalla fase 1 (traduzione), anche se il checkpoint era in fase 2.
				if ( LLM_Story_Game_Progress::STEP_REWRITE === $saved_step && $saved_phrase_ix >= 0 && $saved_phrase_ix < $n_phrases ) {
					LLM_Story_Game_Progress::upsert( $uid, $story_id, $saved_phrase_ix, LLM_Story_Game_Progress::STEP_TRANSLATE );
					$saved_step = LLM_Story_Game_Progress::STEP_TRANSLATE;
				}
			}

			if ( $game_finished ) {
				$map = LLM_User_Stats::get_phrase_map( $uid );
				$key = (string) $story_id;
				if ( isset( $map[ $key ] ) && is_array( $map[ $key ] ) ) {
					$indices = array_map( 'intval', $map[ $key ] );
					sort( $indices );
					foreach ( $indices as $pi ) {
						if ( isset( $phrases[ $pi ]['target'] ) ) {
							$completed_targets[] = array(
								'target'    => (string) $phrases[ $pi ]['target'],
								'interface' => isset( $phrases[ $pi ]['interface'] ) ? (string) $phrases[ $pi ]['interface'] : '',
								'index'     => (int) $pi,
							);
						}
					}
				}
			} else {
				// Mostra tutte le frasi dall'indice 0 al checkpoint: phrase_done è la fonte di verità.
				$show = min( $saved_phrase_ix, $n_phrases );
				for ( $ix = 0; $ix < $show; $ix++ ) {
					if ( isset( $phrases[ $ix ]['target'] ) ) {
						$completed_targets[] = array(
							'target'    => (string) $phrases[ $ix ]['target'],
							'interface' => isset( $phrases[ $ix ]['interface'] ) ? (string) $phrases[ $ix ]['interface'] : '',
							'index'     => (int) $ix,
						);
					}
				}
			}
			$bar_phrases_count = $game_finished ? $n_phrases : $saved_phrase_ix;
			$jump = self::apply_display_jump( $resolved, $n_phrases, $saved_phrase_ix, $saved_step, $game_finished, $completed_targets );
			$is_phrase_jump  = ! empty( $jump['is_jump'] );
			$return_phrase_n = isset( $jump['return_n'] ) ? (int) $jump['return_n'] : 0;
			if ( ! empty( $jump['stale'] ) ) {
				self::persist_display_phrase_index( $story_id, -1, $saved_phrase_ix );
			}
		} elseif ( 0 === $uid && $n_phrases > 0 ) {
			/* Ospite: cookie anonimo + tabella guest progress. */
			$guest_id = LLM_Guest_Story_Progress::get_or_create_id();
			$resolved = LLM_Guest_Story_Progress::resolve( $guest_id, $story_id, $n_phrases );
			if ( $resolved && ! empty( $resolved['finished'] ) ) {
				$game_finished   = true;
				$saved_phrase_ix = $n_phrases;
			} elseif ( $resolved ) {
				$saved_phrase_ix = (int) $resolved['phrase_index'];
				$saved_step      = (int) $resolved['step'];
				if ( LLM_Story_Game_Progress::STEP_REWRITE === $saved_step && $saved_phrase_ix >= 0 && $saved_phrase_ix < $n_phrases ) {
					LLM_Guest_Story_Progress::upsert( $guest_id, $story_id, $saved_phrase_ix, LLM_Story_Game_Progress::STEP_TRANSLATE );
					$saved_step = LLM_Story_Game_Progress::STEP_TRANSLATE;
				}
			}

			$show = min( $saved_phrase_ix, $n_phrases );
			for ( $ix = 0; $ix < $show; $ix++ ) {
				if ( isset( $phrases[ $ix ]['target'] ) ) {
					$completed_targets[] = array(
						'target'    => (string) $phrases[ $ix ]['target'],
						'interface' => isset( $phrases[ $ix ]['interface'] ) ? (string) $phrases[ $ix ]['interface'] : '',
						'index'     => (int) $ix,
					);
				}
			}
			$bar_phrases_count = $game_finished ? $n_phrases : $saved_phrase_ix;
			$jump = self::apply_display_jump( $resolved, $n_phrases, $saved_phrase_ix, $saved_step, $game_finished, $completed_targets );
			$is_phrase_jump  = ! empty( $jump['is_jump'] );
			$return_phrase_n = isset( $jump['return_n'] ) ? (int) $jump['return_n'] : 0;
			if ( ! empty( $jump['stale'] ) ) {
				self::persist_display_phrase_index( $story_id, -1, $saved_phrase_ix );
			}
		}

		if ( ! has_action( 'wp_head', array( __CLASS__, 'print_font_preconnect' ) ) ) {
			add_action( 'wp_head', array( __CLASS__, 'print_font_preconnect' ), 2 );
		}
		wp_register_style(
			'llm-phrase-game-fonts',
			'https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;700;800;900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap',
			array(),
			null
		);
		wp_register_style(
			'llm-phrase-game',
			LLM_TABELLE_URL . 'assets/llm-story-phrase-game.css',
			array( 'llm-phrase-game-fonts' ),
			LLM_TABELLE_VERSION
		);
		wp_register_script(
			'llm-guest-browser-store',
			LLM_TABELLE_URL . 'assets/llm-guest-browser-store.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
		wp_register_script(
			'llm-stt-azure',
			LLM_TABELLE_URL . 'assets/llm-stt-azure.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
		wp_register_script(
			'llm-stt-deepgram',
			LLM_TABELLE_URL . 'assets/llm-stt-deepgram.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
		wp_register_script(
			'llm-phrase-game',
			LLM_TABELLE_URL . 'assets/llm-story-phrase-game.js',
			array( 'llm-guest-browser-store', 'llm-stt-azure', 'llm-stt-deepgram' ),
			LLM_TABELLE_VERSION,
			true
		);

		wp_register_script(
			'llm-learning-modes',
			LLM_TABELLE_URL . 'assets/llm-learning-modes.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);

		wp_enqueue_style( 'llm-phrase-game' );
		wp_enqueue_style(
			'llm-story-hero-fonts',
			'https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;700;800;900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap',
			array(),
			null
		);
		wp_enqueue_style(
			'llm-story-hero',
			LLM_TABELLE_URL . 'assets/llm-story-hero.css',
			array( 'llm-story-hero-fonts' ),
			LLM_TABELLE_VERSION
		);
		if ( $can_edit_notes ) {
			wp_enqueue_editor();
			wp_enqueue_style( 'editor-buttons' );
			wp_enqueue_style( 'dashicons' );
		}

		wp_enqueue_script( 'llm-phrase-game' );
		wp_enqueue_script( 'llm-learning-modes' );
		wp_localize_script( 'llm-learning-modes', 'llmLearningModes', LLM_Learning_Modes::script_data() );

		if ( class_exists( 'LLM_Story_Crossword' ) && class_exists( 'LLM_Crossword_Shortcode' ) && LLM_Story_Crossword::get_crossword_id( $story_id ) ) {
			LLM_Crossword_Shortcode::enqueue();
		}

		$cefr_raw  = (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_CEFR_LEVEL, true );
		$cefr_code = '';
		if ( $cefr_raw && preg_match( '/\b([ABC][12])\b/i', $cefr_raw, $cefr_m ) ) {
			$cefr_code = strtoupper( $cefr_m[1] );
		}

		wp_localize_script(
			'llm-phrase-game',
			'llmPhraseGame',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'llm_phrase_game' ),
				'stt'             => class_exists( 'LLM_STT' ) ? LLM_STT::frontend_config() : array( 'provider' => 'browser' ),
				'storyId'         => $story_id,
				'storyTitle'      => get_the_title( $story_id ),
				'phrases'         => $boot,
				'mediaBlocks'     => $media_blocks,
				'targetLangLabel' => LLM_Phrase_Game_I18n::target_lang_label_for_ui( $target_code ),
				'targetLangCode'  => sanitize_key( $target_code ),
				'interfaceLangLabel' => LLM_Phrase_Game_I18n::target_lang_label_for_ui( $interface_code ),
				'interfaceLangCode'  => sanitize_key( $interface_code ),
				'langFlags'          => class_exists( 'LLM_Languages' ) ? LLM_Languages::flag_map() : array(),
				'i18n'                => array(
				'translatePrompt'  => LLM_Phrase_Game_I18n::get( 'translate_prompt' ),
				'rewritePrompt'    => LLM_Phrase_Game_I18n::get( 'rewrite_prompt' ),
				'inputPlaceholderPhase1' => LLM_Phrase_Game_I18n::get( 'input_placeholder_phase1' ),
				'inputPlaceholderPhase2' => LLM_Phrase_Game_I18n::get( 'input_placeholder_phase2' ),
				'phase1Fail'       => LLM_Phrase_Game_I18n::get( 'phase1_fail' ),
			'phase2Fail'       => ( class_exists( 'LLM_Admin_Phrase_Feedback' ) && '' !== LLM_Admin_Phrase_Feedback::get_fixed_string( 'phase2_fail', LLM_Phrase_Game_I18n::lang() ) )
					? LLM_Admin_Phrase_Feedback::get_fixed_string( 'phase2_fail', LLM_Phrase_Game_I18n::lang() )
					: LLM_Phrase_Game_I18n::get( 'phase2_fail' ),
			'phase2Complete'   => LLM_Phrase_Game_I18n::get( 'phase2_complete' ),
			'phase2StoryContinue' => LLM_Phrase_Game_I18n::get( 'phase2_story_continue' ),
			'phase2Checking'   => LLM_Phrase_Game_I18n::get( 'phase2_checking' ),
			'bravoCorrect'     => LLM_Phrase_Game_I18n::get( 'bravo_correct' ),
			'phraseCompletePoints' => LLM_Phrase_Game_I18n::get( 'phrase_complete_points' ),
			'micUsedPoint'     => LLM_Phrase_Game_I18n::get( 'mic_used_point' ),
			'micUsedNoPoint'   => LLM_Phrase_Game_I18n::get( 'mic_used_no_point' ),
			'storyContinue'    => LLM_Phrase_Game_I18n::get( 'story_continue' ),
				'empty'            => LLM_Phrase_Game_I18n::get( 'empty_input' ),
				'rewindExact'      => LLM_Phrase_Game_I18n::get( 'rewind_exact' ),
				'progress'         => LLM_Phrase_Game_I18n::get( 'progress' ),
				'ajaxError'        => LLM_Phrase_Game_I18n::get( 'ajax_error' ),
				'restartConfirm'   => LLM_Phrase_Game_I18n::get( 'story_progress_confirm' ),
			'introLabel'       => LLM_Phrase_Game_I18n::get( 'intro_label' ),
			'grammarTopicsTitle' => LLM_Phrase_Game_I18n::get( 'grammar_topics_title' ),
			'grammarTopicsBody'  => LLM_Phrase_Game_I18n::get( 'grammar_topics_body' ),
			'upcomingHint'     => LLM_Phrase_Game_I18n::format( 'upcoming_phrases_hint', $n_phrases ),
			'micHint'          => LLM_Phrase_Game_I18n::get( 'mic_hint' ),
			'micPending'       => LLM_Phrase_Game_I18n::get( 'mic_pending' ),
			'micListening'     => LLM_Phrase_Game_I18n::get( 'mic_listening' ),
			'micGrace'         => LLM_Phrase_Game_I18n::get( 'mic_grace' ),
			'micProcessing'    => LLM_Phrase_Game_I18n::get( 'mic_processing' ),
			'micDenied'        => LLM_Phrase_Game_I18n::get( 'mic_denied' ),
			'micUnavailable'   => LLM_Phrase_Game_I18n::get( 'mic_unavailable' ),
			'micSttError'      => LLM_Phrase_Game_I18n::get( 'mic_stt_error' ),
			'micSwitchAria'    => LLM_Phrase_Game_I18n::get( 'mic_switch_aria' ),
			'listenSwitchAria' => LLM_Phrase_Game_I18n::get( 'listen_switch_aria' ),
			'listenLabelMale'  => LLM_Phrase_Game_I18n::get( 'listen_label_male' ),
			'listenLabelFemale'=> LLM_Phrase_Game_I18n::get( 'listen_label_female' ),
			'listenTargetLabel'=> LLM_Phrase_Game_I18n::get( 'listen_target_label' ),
			'micNoAudio'       => LLM_Phrase_Game_I18n::get( 'mic_no_audio' ),
			'loadingNotes'     => LLM_Phrase_Game_I18n::get( 'loading_notes' ),
			'altToggleShow'    => LLM_Phrase_Game_I18n::get( 'alt_toggle_show' ),
			'altToggleHide'    => LLM_Phrase_Game_I18n::get( 'alt_toggle_hide' ),
			'peekTargetLabel'  => LLM_Phrase_Game_I18n::get( 'peek_target_label' ),
			'peekTargetAria'   => LLM_Phrase_Game_I18n::get( 'peek_target_aria' ),
			'resolveGoPrompt'  => LLM_Phrase_Game_I18n::get( 'resolve_go_prompt' ),
			'notesToggleShow'  => LLM_Phrase_Game_I18n::get( 'notes_toggle_show' ),
			'notesToggleHide'  => LLM_Phrase_Game_I18n::get( 'notes_toggle_hide' ),
			'continueToNotes'  => LLM_Phrase_Game_I18n::get( 'continue_to_notes' ),
			'resolveGoFail'    => LLM_Phrase_Game_I18n::get( 'resolve_go_fail' ),
			'writeTranslateFail' => LLM_Phrase_Game_I18n::get( 'write_translate_fail' ),
			'writeTranslateFailWrite' => LLM_Phrase_Game_I18n::get( 'write_translate_fail_write' ),
			'writeTranslateFailSpeak' => LLM_Phrase_Game_I18n::get( 'write_translate_fail_speak' ),
			'writeTranslatePeekNotes' => LLM_Phrase_Game_I18n::get( 'write_translate_peek_notes' ),
			'writeTranslatePlaceholderWrite' => LLM_Phrase_Game_I18n::get( 'write_translate_placeholder_write' ),
			'writeTranslatePlaceholderSpeak' => LLM_Phrase_Game_I18n::get( 'write_translate_placeholder_speak' ),
			'readGoFastPrompt'   => LLM_Phrase_Game_I18n::get( 'read_go_fast_prompt' ),
			'readGoFastNext'     => LLM_Phrase_Game_I18n::get( 'read_go_fast_next' ),
			'readGoFastTarget'   => LLM_Phrase_Game_I18n::get( 'read_go_fast_target' ),
			'readGoFastSaved'    => LLM_Phrase_Game_I18n::get( 'read_go_fast_saved' ),
			'readGoFastSaveError' => LLM_Phrase_Game_I18n::get( 'read_go_fast_save_error' ),
			'readGoFastExact'    => LLM_Phrase_Game_I18n::get( 'read_go_fast_exact' ),
			'readGoFastAlmost'   => LLM_Phrase_Game_I18n::get( 'read_go_fast_almost' ),
			'readGoFastComplete' => LLM_Phrase_Game_I18n::get( 'read_go_fast_complete' ),
			'playInvertedFail'   => LLM_Phrase_Game_I18n::get( 'play_inverted_fail' ),
			'playInvertedFailWrite' => LLM_Phrase_Game_I18n::get( 'play_inverted_fail_write' ),
			'playInvertedFailSpeak' => LLM_Phrase_Game_I18n::get( 'play_inverted_fail_speak' ),
			'playInvertedPlaceholderSpeak' => LLM_Phrase_Game_I18n::get( 'play_inverted_placeholder_speak' ),
			'playInvertedPrompt' => LLM_Phrase_Game_I18n::get( 'play_inverted_prompt' ),
			'playInvertedNext'   => LLM_Phrase_Game_I18n::get( 'play_inverted_next' ),
			'playInvertedHint'   => LLM_Phrase_Game_I18n::get( 'play_inverted_hint' ),
			'playInvertedHintHide' => LLM_Phrase_Game_I18n::get( 'play_inverted_hint_hide' ),
			'playInvertedTarget' => LLM_Phrase_Game_I18n::get( 'play_inverted_target' ),
			'playInvertedExact'  => LLM_Phrase_Game_I18n::get( 'play_inverted_exact' ),
			'playInvertedAlmost' => LLM_Phrase_Game_I18n::get( 'play_inverted_almost' ),
			'playInvertedComplete' => LLM_Phrase_Game_I18n::get( 'play_inverted_complete' ),
			'extraCharsLower'    => LLM_Phrase_Game_I18n::get( 'extra_chars_lower' ),
			'extraCharsUpper'    => LLM_Phrase_Game_I18n::get( 'extra_chars_upper' ),
			'extraCharsSymbols'  => LLM_Phrase_Game_I18n::get( 'extra_chars_symbols' ),
			'keyboardToggle'     => LLM_Phrase_Game_I18n::get( 'keyboard_toggle' ),
			'keyboardShift'      => LLM_Phrase_Game_I18n::get( 'keyboard_shift' ),
			'keyboardBackspace'  => LLM_Phrase_Game_I18n::get( 'keyboard_backspace' ),
			'keyboardSpace'      => LLM_Phrase_Game_I18n::get( 'keyboard_space' ),
			'notesEdit'          => LLM_Phrase_Game_I18n::get( 'notes_edit' ),
			'notesEditNotes'     => LLM_Phrase_Game_I18n::get( 'notes_edit_notes' ),
			'notesEditGrammar'   => LLM_Phrase_Game_I18n::get( 'notes_edit_grammar' ),
			'notesEditAlt'       => LLM_Phrase_Game_I18n::get( 'notes_edit_alt' ),
			'notesEditPronunciation' => LLM_Phrase_Game_I18n::get( 'notes_edit_pronunciation' ),
			'notesEditIpa'       => LLM_Phrase_Game_I18n::get( 'notes_edit_ipa' ),
			'notesEditApprox'    => LLM_Phrase_Game_I18n::get( 'notes_edit_approx' ),
			'notesEditTitle'     => LLM_Phrase_Game_I18n::get( 'notes_edit_title' ),
			'notesEditSave'      => LLM_Phrase_Game_I18n::get( 'notes_edit_save' ),
			'notesEditCancel'    => LLM_Phrase_Game_I18n::get( 'notes_edit_cancel' ),
			'notesEditSaved'     => LLM_Phrase_Game_I18n::get( 'notes_edit_saved' ),
			'notesEditError'     => LLM_Phrase_Game_I18n::get( 'notes_edit_error' ),
			'goToPhrase'         => LLM_Phrase_Game_I18n::get( 'go_to_phrase' ),
			'returnToCheckpointBtn' => $return_phrase_n > 0
				? LLM_Phrase_Game_I18n::format( 'return_to_checkpoint_btn', $return_phrase_n )
				: '',
			),
			'canEditNotes'        => $can_edit_notes,
			'editNotesNonce'      => $can_edit_notes ? wp_create_nonce( 'llm_fe_edit_notes' ) : '',
			'gameFinished'        => $game_finished,
			'isPhraseJump'        => $is_phrase_jump,
			'savedPhraseIndex'    => $saved_phrase_ix,
			'savedPhrasesCount'   => $bar_phrases_count,
				'savedStep'           => $saved_step,
				'resumeAnalysis'      => $resume_analysis,
				'completedStoryLines' => $completed_targets,
				'storyIntro'          => sanitize_textarea_field( (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_INTRO, true ) ),
				'cefrLevel'           => $cefr_code,
				'grammarTopics'       => self::hero_grammar_topics( (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_GRAMMAR_TOPICS, true ) ),
			'storyFinale'         => sanitize_textarea_field( (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_FINALE, true ) ),
				'speechLang'          => self::speech_locale( $target_code ),
			'strictAccents'       => is_user_logged_in() ? LLM_User_Meta::get_strict_accents( get_current_user_id() ) : true,
			'validation'          => array(
				'phase1MinRatio'     => self::PHASE1_MIN_RATIO,
				'phase2MinSimilar'   => self::PHASE2_MIN_SIMILAR,
				'phase2MinWordRatio' => self::PHASE2_MIN_WORD_RATIO,
			),
			'feedback'            => class_exists( 'LLM_Admin_Phrase_Feedback' )
				? LLM_Admin_Phrase_Feedback::get_for_lang( LLM_Phrase_Game_I18n::lang() )
				: array(),
			'micFeedback'         => LLM_Phrase_Game_I18n::get_mic_feedback(),
			'learningMode'        => LLM_Learning_Modes::current(),
			'learningModeDefault' => LLM_Learning_Modes::default_mode(),
			'learningModeStorageKey' => LLM_Learning_Modes::STORAGE_KEY,
			'learningModeIsSaved' => is_user_logged_in(),
			'learningOptions'     => LLM_Learning_Modes::current_options(),
			'learningOptionsDefault' => LLM_Learning_Modes::default_options(),
			'learningOptionsStorageKey' => LLM_Learning_Modes::OPTIONS_STORAGE_KEY,
			'optionRandomWords'   => LLM_Learning_Modes::OPTION_RANDOM_WORDS,
			'optionExtraChars'    => LLM_Learning_Modes::OPTION_EXTRA_CHARS,
			'optionListenReplayLoop' => LLM_Learning_Modes::OPTION_LISTEN_REPLAY_LOOP,
			)
		);
	}

	/**
	 * Applica display_phrase_index al boot: nasconde le frasi dopo il salto.
	 *
	 * @param array<string,mixed>|null $resolved           Resolve checkpoint.
	 * @param int                      $n_phrases          Totale frasi.
	 * @param int                      $saved_phrase_ix    Checkpoint (by ref: diventa display se salto).
	 * @param int                      $saved_step         Step (by ref).
	 * @param bool                     $game_finished      Finita (by ref).
	 * @param array<int, array<string,mixed>> $completed_targets Linee storia (by ref).
	 * @return array{is_jump:bool,return_n:int,stale:bool}
	 */
	private static function apply_display_jump( $resolved, $n_phrases, &$saved_phrase_ix, &$saved_step, &$game_finished, &$completed_targets ) {
		$n_phrases   = max( 0, (int) $n_phrases );
		$checkpoint  = (int) $saved_phrase_ix;
		$was_finished = (bool) $game_finished;
		$return_n    = $was_finished ? $n_phrases : min( $checkpoint + 1, $n_phrases );
		$display     = is_array( $resolved ) && isset( $resolved['display_phrase_index'] )
			? (int) $resolved['display_phrase_index']
			: -1;
		if ( $display < 0 || $display >= $n_phrases ) {
			$display = -1;
		}
		$can_jump = $display >= 0 && ( $was_finished || $display < $checkpoint );
		if ( ! $can_jump ) {
			return array(
				'is_jump'  => false,
				'return_n' => $return_n,
				'stale'    => $display >= 0 && ! $was_finished && $display >= $checkpoint,
			);
		}

		$filtered = array();
		foreach ( $completed_targets as $line ) {
			$ix = isset( $line['index'] ) ? (int) $line['index'] : -1;
			if ( $ix >= 0 && $ix < $display ) {
				$filtered[] = $line;
			}
		}
		$completed_targets = $filtered;
		$saved_phrase_ix   = $display;
		$game_finished     = false;
		$saved_step        = LLM_Story_Game_Progress::STEP_TRANSLATE;

		return array(
			'is_jump'  => true,
			'return_n' => $return_n,
			'stale'    => false,
		);
	}

	/**
	 * @param int $story_id              ID storia.
	 * @param int $display_ix            Indice da mostrare, o -1.
	 * @param int $fallback_phrase_index Checkpoint se manca la riga.
	 */
	private static function persist_display_phrase_index( $story_id, $display_ix, $fallback_phrase_index = 0 ) {
		$story_id              = absint( $story_id );
		$display_ix            = (int) $display_ix;
		$fallback_phrase_index = (int) $fallback_phrase_index;
		if ( ! $story_id ) {
			return;
		}
		if ( is_user_logged_in() ) {
			LLM_Story_Game_Progress::set_display_phrase_index(
				get_current_user_id(),
				$story_id,
				$display_ix,
				$fallback_phrase_index
			);
			return;
		}
		$guest_id = LLM_Guest_Story_Progress::get_or_create_id();
		if ( '' !== $guest_id ) {
			LLM_Guest_Story_Progress::set_display_phrase_index(
				$guest_id,
				$story_id,
				$display_ix,
				$fallback_phrase_index
			);
		}
	}

	/**
	 * @param int $story_id  ID storia.
	 * @param int $n_phrases Totale frasi.
	 * @return array{jumping:bool,checkpoint:int,display:int}
	 */
	private static function current_display_jump( $story_id, $n_phrases ) {
		$story_id  = absint( $story_id );
		$n_phrases = max( 0, (int) $n_phrases );
		$resolved  = null;
		if ( is_user_logged_in() ) {
			$resolved = LLM_Story_Game_Progress::resolve_for_user( get_current_user_id(), $story_id, $n_phrases );
		} else {
			$guest_id = LLM_Guest_Story_Progress::get_or_create_id();
			if ( '' !== $guest_id ) {
				$resolved = LLM_Guest_Story_Progress::resolve( $guest_id, $story_id, $n_phrases );
			}
		}
		if ( ! is_array( $resolved ) ) {
			return array(
				'jumping'    => false,
				'checkpoint' => 0,
				'display'    => -1,
			);
		}
		$display    = isset( $resolved['display_phrase_index'] ) ? (int) $resolved['display_phrase_index'] : -1;
		$checkpoint = ! empty( $resolved['finished'] ) ? $n_phrases : (int) $resolved['phrase_index'];
		$jumping    = $display >= 0 && $display < $n_phrases && $display < $checkpoint;
		return array(
			'jumping'    => $jumping,
			'checkpoint' => $checkpoint,
			'display'    => $display,
		);
	}

	/**
	 * AJAX: validazione fase 1 o 2.
	 */
	public static function ajax_check() {
		check_ajax_referer( 'llm_phrase_game', 'nonce' );

		$story_id = isset( $_POST['story_id'] ) ? absint( wp_unslash( $_POST['story_id'] ) ) : 0;
		$index    = isset( $_POST['phrase_index'] ) ? absint( wp_unslash( $_POST['phrase_index'] ) ) : 0;
		$phase    = isset( $_POST['phase'] ) ? absint( wp_unslash( $_POST['phase'] ) ) : 0;
		$user_raw = isset( $_POST['user_text'] ) ? wp_unslash( $_POST['user_text'] ) : '';
		$user     = sanitize_textarea_field( $user_raw );
		$mic_used = isset( $_POST['mic_used'] ) && '1' === $_POST['mic_used'];

		$post = get_post( $story_id );
		if ( ! $post || LLM_STORY_CPT !== $post->post_type || 'publish' !== $post->post_status ) {
			wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'invalid_story' ) ), 400 );
		}

		$row = LLM_Story_Repository::get_phrase_at( $story_id, $index );
		if ( null === $row ) {
			wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'phrase_not_found' ) ), 400 );
		}

		$target = isset( $row['target'] ) ? (string) $row['target'] : '';

		$posted_mode      = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		$mode             = LLM_Learning_Modes::for_request( $posted_mode );
		$skips_validation = LLM_Learning_Modes::skips_validation( $mode );

		if ( ! $skips_validation && '' === trim( $user ) ) {
			wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'empty_input' ) ) );
		}

		if ( 1 === $phase ) {
			$bypass_phase1 = isset( $_POST['phase1_bypass'] ) && '1' === $_POST['phase1_bypass'];
			if ( ! $bypass_phase1 && ! $skips_validation ) {
				$ratio = self::reference_words_found_ratio( $user, $target );
				if ( $ratio < self::PHASE1_MIN_RATIO ) {
					wp_send_json_error(
						array(
							'message' => LLM_Phrase_Game_I18n::get( 'phase1_fail' ),
						)
					);
				}
			}

			$phrases_for_jump = LLM_Story_Repository::get_phrases( $story_id );
			$jump_state       = self::current_display_jump( $story_id, count( $phrases_for_jump ) );
			if ( empty( $jump_state['jumping'] ) ) {
				if ( is_user_logged_in() ) {
					LLM_Story_Game_Progress::upsert(
						get_current_user_id(),
						$story_id,
						$index,
						LLM_Story_Game_Progress::STEP_REWRITE
					);
				} else {
					$guest_id = LLM_Guest_Story_Progress::get_or_create_id();
					if ( '' !== $guest_id ) {
						LLM_Guest_Story_Progress::upsert(
							$guest_id,
							$story_id,
							$index,
							LLM_Story_Game_Progress::STEP_REWRITE
						);
					}
				}
			}

			wp_send_json_success(
				array(
					'phase'   => 1,
					'grammar' => isset( $row['grammar'] ) ? (string) $row['grammar'] : '',
					'target'  => $target,
					'alt'     => isset( $row['alt'] ) ? (string) $row['alt'] : '',
					'notes'   => isset( $row['notes'] ) ? (string) $row['notes'] : '',
					'pronunciation' => isset( $row['pronunciation'] ) ? (string) $row['pronunciation'] : '',
					'ipa'           => isset( $row['ipa'] ) ? (string) $row['ipa'] : '',
					'approx'        => isset( $row['approx'] ) ? (string) $row['approx'] : '',
				)
			);
		}

	if ( 2 === $phase ) {
		$client_strict = isset( $_POST['strict_accents'] ) ? ( '1' === $_POST['strict_accents'] ) : null;
		$reference     = $target;
		if ( LLM_Learning_Modes::MODE_PLAY_INVERTED === $mode ) {
			$reference = isset( $row['interface'] ) ? (string) $row['interface'] : '';
		}
		if ( ! $skips_validation && ! self::phase2_passes( $user, $reference, $client_strict ) ) {
			wp_send_json_error(
				array(
					'message' => LLM_Phrase_Game_I18n::get( LLM_Learning_Modes::MODE_PLAY_INVERTED === $mode ? 'play_inverted_fail' : 'phase2_fail' ),
				)
			);
		}

			$next       = null !== LLM_Story_Repository::get_phrase_at( $story_id, $index + 1 );
			$phrases    = LLM_Story_Repository::get_phrases( $story_id );
			$phr_total  = count( $phrases );
			$phrases_done = 0;
			$jump_state = self::current_display_jump( $story_id, $phr_total );

		if ( ! empty( $jump_state['jumping'] ) ) {
			$next_display = $index + 1;
			if ( $next_display >= (int) $jump_state['checkpoint'] ) {
				self::persist_display_phrase_index( $story_id, -1, (int) $jump_state['checkpoint'] );
			} else {
				self::persist_display_phrase_index( $story_id, $next_display, (int) $jump_state['checkpoint'] );
			}
			if ( is_user_logged_in() ) {
				$phrases_done = LLM_Story_Game_Progress::bar_completed_count( get_current_user_id(), $story_id, $phr_total );
			} else {
				$guest_id = LLM_Guest_Story_Progress::get_or_create_id();
				if ( '' !== $guest_id ) {
					$phrases_done = LLM_Guest_Story_Progress::bar_completed_count( $guest_id, $story_id, $phr_total );
				}
			}
		} elseif ( is_user_logged_in() ) {
			$uid    = get_current_user_id();
			$amount = $mic_used ? 2 : 1;
			LLM_User_Stats::record_phrase_completion( $uid, $story_id, $index, $amount );
				if ( $next ) {
					LLM_Story_Game_Progress::upsert(
						$uid,
						$story_id,
						$index + 1,
						LLM_Story_Game_Progress::STEP_TRANSLATE
					);
				} else {
					LLM_Story_Game_Progress::delete( $uid, $story_id );
				}
				$phrases_done = LLM_Story_Game_Progress::bar_completed_count( $uid, $story_id, $phr_total );
			} else {
				$guest_id = LLM_Guest_Story_Progress::get_or_create_id();
				if ( '' !== $guest_id ) {
					$phrases_done = LLM_Guest_Story_Progress::record_phrase_completion(
						$guest_id,
						$story_id,
						$index,
						$phr_total
					);
				}
			}

			wp_send_json_success(
				array(
					'phase'             => 2,
					'display_sentence'  => $target,
					'display_interface' => isset( $row['interface'] ) ? (string) $row['interface'] : '',
					'has_more'          => $next,
					'next_index'        => $next ? $index + 1 : null,
					'phrases_done'      => $phrases_done,
					'phrases_total'     => $phr_total,
				)
			);
		}

		wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'bad_request' ) ), 400 );
	}

	/**
	 * Quante parole uniche della referenza compaiono nell’input (normalizzate).
	 *
	 * @param string $user_text    Testo utente.
	 * @param string $reference_text Traduzione di riferimento.
	 * @return float 0–1
	 */
	public static function reference_words_found_ratio( $user_text, $reference_text ) {
		$ref_words = self::tokenize_words( $reference_text );
		$user_words = self::tokenize_words( $user_text );
		if ( empty( $ref_words ) ) {
			return 1.0;
		}
		$user_set = array_flip( $user_words );
		$hits     = 0;
		foreach ( $ref_words as $w ) {
			if ( isset( $user_set[ $w ] ) ) {
				++$hits;
			}
		}
		return $hits / count( $ref_words );
	}

	/**
	 * @param string $user_text    Testo utente.
	 * @param string $reference_text Modello.
	 */
	public static function phase2_passes( $user_text, $reference_text, $strict_accents = null ) {
		if ( null === $strict_accents ) {
			$strict_accents = is_user_logged_in()
				? LLM_User_Meta::get_strict_accents( get_current_user_id() )
				: true;
		}

		$u = self::normalize_sentence( $user_text, $strict_accents );
		$r = self::normalize_sentence( $reference_text, $strict_accents );
		if ( '' === $r ) {
			return true;
		}
		if ( '' === $u ) {
			return false;
		}
		return $u === $r;
	}

	/**
	 * Rimuove i diacritici (accenti, ogonek, cedille ecc.) da una stringa UTF-8.
	 * Usa Normalizer (NFD) se disponibile, altrimenti una mappa manuale.
	 *
	 * @param string $s
	 * @return string
	 */
	private static function strip_diacritics( $s ) {
		if ( class_exists( 'Normalizer' ) ) {
			$nfd = \Normalizer::normalize( $s, \Normalizer::FORM_D );
			if ( false !== $nfd ) {
				return preg_replace( '/\p{M}/u', '', $nfd );
			}
		}
		/* Mappa manuale: copre IT, FR, ES, PL, DE, più comuni */
		$map = array(
			'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
			'ą' => 'a', 'ā' => 'a', 'ă' => 'a',
			'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ę' => 'e', 'ě' => 'e',
			'ē' => 'e', 'ĕ' => 'e',
			'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'ĭ' => 'i',
			'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
			'ő' => 'o', 'ō' => 'o',
			'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ű' => 'u', 'ū' => 'u',
			'ý' => 'y', 'ÿ' => 'y',
			'ç' => 'c', 'ć' => 'c', 'č' => 'c',
			'ñ' => 'n', 'ń' => 'n', 'ň' => 'n',
			'ł' => 'l', 'ľ' => 'l',
			'ś' => 's', 'š' => 's', 'ş' => 's',
			'ź' => 'z', 'ż' => 'z', 'ž' => 'z',
			'ď' => 'd', 'ð' => 'd',
			'ř' => 'r',
			'ť' => 't', 'þ' => 'th',
			'ß' => 'ss',
			'æ' => 'ae', 'œ' => 'oe',
		);
		$upper = array();
		foreach ( $map as $from => $to ) {
			$upper[ mb_strtoupper( $from, 'UTF-8' ) ] = mb_strtoupper( $to, 'UTF-8' );
		}
		return strtr( $s, array_merge( $map, $upper ) );
	}

	/**
	 * @param string    $s             Testo.
	 * @param bool      $keep_accents  Se false, rimuove i diacritici.
	 * @return string
	 */
	public static function normalize_sentence( $s, $keep_accents = true ) {
		$s = wp_strip_all_tags( (string) $s );
		$s = mb_strtolower( $s, 'UTF-8' );
		if ( ! $keep_accents ) {
			$s = self::strip_diacritics( $s );
		}
		$s = preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $s );
		$s = preg_replace( '/\s+/u', ' ', $s );
		return trim( $s );
	}

	/**
	 * AJAX: ricomincia la storia dalla prima frase (loggati e ospiti).
	 */
	public static function ajax_restart() {
		check_ajax_referer( 'llm_phrase_game', 'nonce' );

		$story_id = isset( $_POST['story_id'] ) ? absint( wp_unslash( $_POST['story_id'] ) ) : 0;
		if ( ! $story_id ) {
			wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'invalid_story' ) ), 400 );
		}

		$post = get_post( $story_id );
		if ( ! $post || LLM_STORY_CPT !== $post->post_type ) {
			wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'invalid_story' ) ), 400 );
		}

		if ( is_user_logged_in() ) {
			LLM_User_Stats::reset_story_progress_for_user( get_current_user_id(), $story_id );
		} else {
			$guest_id = LLM_Guest_Story_Progress::get_or_create_id();
			if ( '' !== $guest_id ) {
				LLM_Guest_Story_Progress::reset_story( $guest_id, $story_id );
			}
		}
		wp_send_json_success();
	}

	/**
	 * AJAX: salta a una frase già fatta, o torna al checkpoint.
	 */
	public static function ajax_set_display() {
		check_ajax_referer( 'llm_phrase_game', 'nonce' );

		$story_id = isset( $_POST['story_id'] ) ? absint( wp_unslash( $_POST['story_id'] ) ) : 0;
		$clear    = isset( $_POST['clear'] ) && '1' === (string) wp_unslash( $_POST['clear'] );
		$index    = isset( $_POST['phrase_index'] ) ? absint( wp_unslash( $_POST['phrase_index'] ) ) : 0;

		if ( ! $story_id ) {
			wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'invalid_story' ) ), 400 );
		}

		$post = get_post( $story_id );
		if ( ! $post || LLM_STORY_CPT !== $post->post_type ) {
			wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'invalid_story' ) ), 400 );
		}

		$phrases   = LLM_Story_Repository::get_phrases( $story_id );
		$n_phrases = count( $phrases );
		if ( $n_phrases < 1 ) {
			wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'phrase_not_found' ) ), 400 );
		}

		$resolved = null;
		if ( is_user_logged_in() ) {
			$resolved = LLM_Story_Game_Progress::resolve_for_user( get_current_user_id(), $story_id, $n_phrases );
		} else {
			$guest_id = LLM_Guest_Story_Progress::get_or_create_id();
			if ( '' !== $guest_id ) {
				$resolved = LLM_Guest_Story_Progress::resolve( $guest_id, $story_id, $n_phrases );
			}
		}

		$finished   = is_array( $resolved ) && ! empty( $resolved['finished'] );
		$checkpoint = $finished
			? $n_phrases
			: ( is_array( $resolved ) ? (int) $resolved['phrase_index'] : 0 );

		if ( $clear ) {
			self::persist_display_phrase_index( $story_id, -1, $checkpoint );
			wp_send_json_success();
		}

		if ( $index >= $n_phrases ) {
			wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'phrase_not_found' ) ), 400 );
		}
		if ( ! $finished && $index >= $checkpoint ) {
			wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'bad_request' ) ), 400 );
		}

		self::persist_display_phrase_index( $story_id, $index, $checkpoint );
		wp_send_json_success();
	}

	/**
	 * AJAX: salva analisi grammaticale dal frontend (solo chi può modificare la storia).
	 */
	public static function ajax_save_phrase_notes() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'notes_edit_error' ) ), 403 );
		}
		check_ajax_referer( 'llm_fe_edit_notes', 'nonce' );

		$story_id = isset( $_POST['story_id'] ) ? absint( wp_unslash( $_POST['story_id'] ) ) : 0;
		$index    = isset( $_POST['phrase_index'] ) ? absint( wp_unslash( $_POST['phrase_index'] ) ) : 0;
		$field    = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : 'grammar';
		$value    = isset( $_POST['grammar'] ) ? wp_unslash( $_POST['grammar'] ) : '';

		if ( ! $story_id || ! self::current_user_can_edit_notes( $story_id ) ) {
			wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'notes_edit_error' ) ), 403 );
		}

		$post = get_post( $story_id );
		if ( ! $post || LLM_STORY_CPT !== $post->post_type ) {
			wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'invalid_story' ) ), 400 );
		}

		$ok = LLM_Story_Repository::update_phrase_rich_field( $story_id, $index, $field, $value );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'phrase_not_found' ) ), 400 );
		}

		wp_send_json_success( array( 'message' => LLM_Phrase_Game_I18n::get( 'notes_edit_saved' ) ) );
	}

	/**
	 * Token parole uniche ordine preservato per ref count.
	 *
	 * @param string $s Testo.
	 * @return string[]
	 */
	public static function tokenize_words( $s ) {
		$s = self::normalize_sentence( $s );
		if ( '' === $s ) {
			return array();
		}
		$parts = preg_split( '/\s+/u', $s, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $parts ) ? array_values( $parts ) : array();
	}
}
