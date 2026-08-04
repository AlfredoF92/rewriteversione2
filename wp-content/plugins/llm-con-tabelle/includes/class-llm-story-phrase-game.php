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
			. '<button type="button" class="llm-phrase-game__tool-accordion-toggle llm-phrase-game__random-words-toggle" aria-expanded="false" aria-controls="' . esc_attr( $list_id ) . '">'
			. '<span class="llm-phrase-game__tool-accordion-toggle-text">' . esc_html( $label ) . '</span>'
			. '<span class="llm-phrase-game__tool-accordion-chevron" aria-hidden="true"></span>'
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
			. '<hr class="llm-phrase-game__divider llm-phrase-game__divider--extra-chars" role="presentation" aria-hidden="true" />'
			. '<button type="button" class="llm-phrase-game__tool-accordion-toggle llm-phrase-game__extra-chars-toggle" aria-expanded="false" aria-controls="' . esc_attr( $panel_id ) . '">'
			. '<span class="llm-phrase-game__tool-accordion-toggle-text">' . esc_html( $label ) . '</span>'
			. '<span class="llm-phrase-game__tool-accordion-chevron" aria-hidden="true"></span>'
			. '</button>'
			. '<div class="llm-phrase-game__extra-chars-panel" id="' . esc_attr( $panel_id ) . '" hidden></div>'
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

		$uid = 'llm-phrase-game-' . uniqid( '', false );

		$target_code_shortcode = (string) get_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, true );
		$mic_btn_text          = LLM_Phrase_Game_I18n::get( 'mic_button' );
		$listen_target_aria    = LLM_Phrase_Game_I18n::format(
			'listen_target_aria',
			LLM_Phrase_Game_I18n::target_lang_label_for_ui( $target_code_shortcode )
		);
		$listen_target_label   = LLM_Phrase_Game_I18n::get( 'listen_target_label' );

		ob_start();
		?>
		<div id="<?php echo esc_attr( $uid ); ?>" class="llm-phrase-game" data-story-id="<?php echo esc_attr( (string) $story_id ); ?>">
			<div class="llm-phrase-game__story-wrap">
				<div class="llm-phrase-game__story" aria-live="polite"></div>
			</div>
			<div class="llm-phrase-game__divider" role="presentation" aria-hidden="true"></div>
			<div class="llm-phrase-game__card">
				<div class="llm-phrase-game__progress"></div>
				<div class="llm-phrase-game__phase llm-phrase-game__phase--1">
					<div class="llm-phrase-game__interface-row">
						<span class="llm-phrase-game__lang-flag llm-phrase-game__lang-flag--source" aria-hidden="true"></span>
						<div class="llm-phrase-game__interface"></div>
						<button type="button" class="llm-phrase-game__listen-target llm-phrase-game__listen-target--force-hidden" hidden aria-label="<?php echo esc_attr( $listen_target_aria ); ?>" title="<?php echo esc_attr( $listen_target_aria ); ?>" aria-hidden="true">
							<span class="llm-phrase-game__listen-target-icon" aria-hidden="true">
								<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
							</span>
							<span class="llm-phrase-game__listen-target-text"><?php echo esc_html( $listen_target_label ); ?></span>
						</button>
					</div>
					<p class="llm-phrase-game__prompt llm-phrase-game__prompt--translate">
						<span class="llm-phrase-game__prompt-text"></span>
					</p>
					<label class="screen-reader-text" for="<?php echo esc_attr( $uid ); ?>-input1"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'sr_your_translation' ) ); ?></label>
					<div class="llm-phrase-game__compose llm-phrase-game__compose--phase1">
						<div class="llm-phrase-game__input-block">
							<div class="llm-phrase-game__input-shell">
								<span class="llm-phrase-game__lang-flag llm-phrase-game__lang-flag--write" aria-hidden="true"></span>
								<input type="text" id="<?php echo esc_attr( $uid ); ?>-input1" class="llm-phrase-game__input llm-phrase-game__input--1" autocomplete="off" />
							</div>
							<div class="llm-phrase-game__clear-wrap llm-phrase-game__clear-wrap--1 llm-phrase-game__action-fade" hidden>
								<?php echo self::render_clear_input_button( '1' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
							</div>
							<button type="button" class="llm-phrase-game__mic llm-phrase-game__mic--1" aria-label="<?php echo esc_attr( LLM_Phrase_Game_I18n::get( 'sr_mic' ) . ' ' . $mic_btn_text ); ?>">
								<span class="llm-phrase-game__mic-icon" aria-hidden="true">&#127908;</span>
								<span class="llm-phrase-game__mic-text"><?php echo esc_html( $mic_btn_text ); ?></span>
							</button>
							<button type="button" class="llm-phrase-game__tool-accordion-toggle llm-phrase-game__inverted-hint" hidden aria-expanded="false">
								<span class="llm-phrase-game__tool-accordion-toggle-text llm-phrase-game__inverted-hint-text-label"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'play_inverted_hint' ) ); ?></span>
								<span class="llm-phrase-game__tool-accordion-chevron" aria-hidden="true"></span>
							</button>
							<div class="llm-phrase-game__inverted-hint-panel" hidden></div>
							<?php echo self::render_random_words_block( $uid, '1' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
							<?php echo self::render_extra_chars_block( $uid, '1' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
						</div>
						<div class="llm-phrase-game__actions">
							<div class="llm-phrase-game__continue-block llm-phrase-game__continue-block--1">
								<hr class="llm-phrase-game__divider llm-phrase-game__divider--before-continue" role="presentation" aria-hidden="true" />
								<button type="button" class="llm-phrase-game__btn llm-phrase-game__btn--continue1 button"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'continue' ) ); ?></button>
							</div>
						</div>
					</div>
				</div>
		<div class="llm-phrase-game__message" role="alert"></div>
		<div class="llm-phrase-game__db-status" role="status" aria-live="polite" aria-atomic="true"></div>
		<div class="llm-phrase-game__message-phase2 llm-phrase-game__message-solo" role="status" aria-live="polite"></div>
			<div class="llm-phrase-game__notes" hidden>
				<button type="button" class="llm-phrase-game__listen-target llm-phrase-game__notes-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>-notes-panel">
					<span class="llm-phrase-game__listen-target-icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M18 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM8 6h8v2H8V6zm0 4h8v2H8v-2zm0 4h5v2H8v-2z"/></svg>
					</span>
					<span class="llm-phrase-game__listen-target-text llm-phrase-game__notes-toggle-text"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'notes_toggle_show' ) ); ?></span>
				</button>
				<div class="llm-phrase-game__notes-panel" id="<?php echo esc_attr( $uid ); ?>-notes-panel" hidden></div>
			</div>
			<div class="llm-phrase-game__phase1-feedback" hidden aria-live="polite"></div>
			<div class="llm-phrase-game__loading-notes" hidden aria-live="polite"></div>
			<div class="llm-phrase-game__analysis" hidden>
					<div class="llm-phrase-game__your-phrase-wrap" hidden>
						<p class="llm-phrase-game__your-phrase-label"><strong><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'your_phrase_label' ) ); ?></strong></p>
						<p class="llm-phrase-game__your-phrase-text"></p>
					</div>
					<p class="llm-phrase-game__bravo"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'bravo_intro' ) ); ?></p>
					<div class="llm-phrase-game__grammar"></div>
					<p class="llm-phrase-game__label-main"><strong><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'label_main' ) ); ?></strong></p>
					<div class="llm-phrase-game__target"></div>
					<button type="button" class="llm-phrase-game__listen-target llm-phrase-game__peek-target" hidden aria-label="<?php echo esc_attr( LLM_Phrase_Game_I18n::get( 'peek_target_aria' ) ); ?>" title="<?php echo esc_attr( LLM_Phrase_Game_I18n::get( 'peek_target_aria' ) ); ?>">
						<span class="llm-phrase-game__listen-target-icon" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
						</span>
						<span class="llm-phrase-game__listen-target-text"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'peek_target_label' ) ); ?></span>
					</button>
					<p class="llm-phrase-game__label-alt">
						<strong><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'label_alt' ) ); ?></strong>
						<button type="button" class="llm-phrase-game__alt-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>-alt-panel" aria-label="<?php echo esc_attr( LLM_Phrase_Game_I18n::get( 'alt_toggle_show' ) ); ?>" hidden>
							<span class="llm-phrase-game__alt-toggle-arrow" aria-hidden="true">&#9660;</span>
						</button>
					</p>
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
							<button type="button" class="llm-phrase-game__listen-target llm-phrase-game__listen-target--phase2" aria-label="<?php echo esc_attr( $listen_target_aria ); ?>" title="<?php echo esc_attr( $listen_target_aria ); ?>">
								<span class="llm-phrase-game__listen-target-icon" aria-hidden="true">
									<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
								</span>
								<span class="llm-phrase-game__listen-target-text"><?php echo esc_html( $listen_target_label ); ?></span>
							</button>
						</div>
						<p class="llm-phrase-game__prompt llm-phrase-game__prompt--rewrite">
							<span class="llm-phrase-game__prompt-text llm-phrase-game__prompt-text--rewrite"></span>
						</p>
						<p class="llm-phrase-game__phase2-recap__prompt"></p>
					</div>
					<div class="llm-phrase-game__input-block">
						<div class="llm-phrase-game__input-shell">
							<span class="llm-phrase-game__lang-flag llm-phrase-game__lang-flag--write" aria-hidden="true"></span>
							<input type="text" id="<?php echo esc_attr( $uid ); ?>-input2" class="llm-phrase-game__input llm-phrase-game__input--2" autocomplete="off" />
						</div>
						<div class="llm-phrase-game__clear-wrap llm-phrase-game__clear-wrap--2 llm-phrase-game__action-fade" hidden>
							<?php echo self::render_clear_input_button( '2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
						</div>
						<button type="button" class="llm-phrase-game__mic llm-phrase-game__mic--2" aria-label="<?php echo esc_attr( LLM_Phrase_Game_I18n::get( 'sr_mic' ) . ' ' . $mic_btn_text ); ?>">
							<span class="llm-phrase-game__mic-icon" aria-hidden="true">&#127908;</span>
							<span class="llm-phrase-game__mic-text"><?php echo esc_html( $mic_btn_text ); ?></span>
						</button>
						<?php echo self::render_random_words_block( $uid, '2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
						<?php echo self::render_extra_chars_block( $uid, '2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- metodo restituisce HTML escapato. ?>
					</div>
					<div class="llm-phrase-game__actions">
						<div class="llm-phrase-game__continue-block llm-phrase-game__continue-block--2">
							<hr class="llm-phrase-game__divider llm-phrase-game__divider--before-continue" role="presentation" aria-hidden="true" />
							<button type="button" class="llm-phrase-game__btn llm-phrase-game__btn--continue2 button"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'continue' ) ); ?></button>
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
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Barra "Modalità apprendimento" + popup di scelta.
	 *
	 * @param string $uid Prefisso ID univoco dell'istanza.
	 * @return string HTML già escapato.
	 */
	private static function render_learning_mode_ui( $uid ) {
		$modes        = LLM_Learning_Modes::all();
		$current      = LLM_Learning_Modes::current();
		$extras       = LLM_Learning_Modes::options();
		$extras_on    = LLM_Learning_Modes::current_options();
		$radio_name   = $uid . '-learning-mode';
		$dialog_id    = $uid . '-learning-mode-dialog';
		$title_id     = $uid . '-learning-mode-title';

		ob_start();
		?>
		<div class="llm-learning-mode" data-current-mode="<?php echo esc_attr( $current ); ?>">
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
			);
		}

		$target_code = (string) get_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, true );

		$n_phrases         = count( $phrases );
		$uid               = is_user_logged_in() ? get_current_user_id() : 0;
		$game_finished     = false;
		$saved_phrase_ix   = 0;
		$saved_step        = LLM_Story_Game_Progress::STEP_TRANSLATE;
		$resume_analysis   = null;
		$completed_targets = array();

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
						);
					}
				}
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
					);
				}
			}
		}

		wp_register_style(
			'llm-phrase-game-fonts',
			'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap',
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
			'llm-phrase-game',
			LLM_TABELLE_URL . 'assets/llm-story-phrase-game.js',
			array(),
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
		wp_enqueue_script( 'llm-phrase-game' );
		wp_enqueue_script( 'llm-learning-modes' );
		wp_localize_script( 'llm-learning-modes', 'llmLearningModes', LLM_Learning_Modes::script_data() );

		wp_localize_script(
			'llm-phrase-game',
			'llmPhraseGame',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'llm_phrase_game' ),
				'storyId'         => $story_id,
				'phrases'         => $boot,
				'targetLangLabel' => LLM_Phrase_Game_I18n::target_lang_label_for_ui( $target_code ),
				'targetLangCode'  => sanitize_key( $target_code ),
				'interfaceLangLabel' => LLM_Phrase_Game_I18n::target_lang_label_for_ui( LLM_Phrase_Game_I18n::lang() ),
				'interfaceLangCode'  => LLM_Phrase_Game_I18n::lang(),
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
				'progress'         => LLM_Phrase_Game_I18n::get( 'progress' ),
				'ajaxError'        => LLM_Phrase_Game_I18n::get( 'ajax_error' ),
				'restartConfirm'   => LLM_Phrase_Game_I18n::get( 'story_progress_confirm' ),
			'introLabel'       => LLM_Phrase_Game_I18n::get( 'intro_label' ),
			'micHint'          => LLM_Phrase_Game_I18n::get( 'mic_hint' ),
			'micPending'       => LLM_Phrase_Game_I18n::get( 'mic_pending' ),
			'micListening'     => LLM_Phrase_Game_I18n::get( 'mic_listening' ),
			'micGrace'         => LLM_Phrase_Game_I18n::get( 'mic_grace' ),
			'micDenied'        => LLM_Phrase_Game_I18n::get( 'mic_denied' ),
			'micUnavailable'   => LLM_Phrase_Game_I18n::get( 'mic_unavailable' ),
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
			'readGoFastPrompt'   => LLM_Phrase_Game_I18n::get( 'read_go_fast_prompt' ),
			'readGoFastNext'     => LLM_Phrase_Game_I18n::get( 'read_go_fast_next' ),
			'readGoFastTarget'   => LLM_Phrase_Game_I18n::get( 'read_go_fast_target' ),
			'readGoFastSaved'    => LLM_Phrase_Game_I18n::get( 'read_go_fast_saved' ),
			'readGoFastSaveError' => LLM_Phrase_Game_I18n::get( 'read_go_fast_save_error' ),
			'readGoFastExact'    => LLM_Phrase_Game_I18n::get( 'read_go_fast_exact' ),
			'readGoFastAlmost'   => LLM_Phrase_Game_I18n::get( 'read_go_fast_almost' ),
			'readGoFastComplete' => LLM_Phrase_Game_I18n::get( 'read_go_fast_complete' ),
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
			),
			'gameFinished'        => $game_finished,
			'savedPhraseIndex'    => $saved_phrase_ix,
			'savedPhrasesCount'   => $game_finished ? $n_phrases : $saved_phrase_ix,
				'savedStep'           => $saved_step,
				'resumeAnalysis'      => $resume_analysis,
				'completedStoryLines' => $completed_targets,
				'storyIntro'          => sanitize_textarea_field( (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_INTRO, true ) ),
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
			'learningOptionsStorageKey' => LLM_Learning_Modes::OPTIONS_STORAGE_KEY,
			'optionRandomWords'   => LLM_Learning_Modes::OPTION_RANDOM_WORDS,
			'optionExtraChars'    => LLM_Learning_Modes::OPTION_EXTRA_CHARS,
			'optionListenReplayLoop' => LLM_Learning_Modes::OPTION_LISTEN_REPLAY_LOOP,
			)
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

			wp_send_json_success(
				array(
					'phase'   => 1,
					'grammar' => isset( $row['grammar'] ) ? (string) $row['grammar'] : '',
					'target'  => $target,
					'alt'     => isset( $row['alt'] ) ? (string) $row['alt'] : '',
				)
			);
		}

	if ( 2 === $phase ) {
		$client_strict = isset( $_POST['strict_accents'] ) ? ( '1' === $_POST['strict_accents'] ) : null;
		if ( ! $skips_validation && ! self::phase2_passes( $user, $target, $client_strict ) ) {
			wp_send_json_error(
				array(
					'message' => LLM_Phrase_Game_I18n::get( 'phase2_fail' ),
				)
			);
		}

			$next       = null !== LLM_Story_Repository::get_phrase_at( $story_id, $index + 1 );
			$phrases    = LLM_Story_Repository::get_phrases( $story_id );
			$phr_total  = count( $phrases );
			$phrases_done = 0;

		if ( is_user_logged_in() ) {
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
