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
		$mic_btn_text          = LLM_Phrase_Game_I18n::format(
			'mic_button',
			LLM_Phrase_Game_I18n::target_lang_label_for_ui( $target_code_shortcode )
		);
		$listen_target_aria    = LLM_Phrase_Game_I18n::format(
			'listen_target_aria',
			LLM_Phrase_Game_I18n::target_lang_label_for_ui( $target_code_shortcode )
		);

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
						<p class="llm-phrase-game__interface"></p>
						<button type="button" class="llm-phrase-game__listen-target" hidden aria-label="<?php echo esc_attr( $listen_target_aria ); ?>" title="<?php echo esc_attr( $listen_target_aria ); ?>">
							<span class="llm-phrase-game__listen-target-icon" aria-hidden="true">
								<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
							</span>
						</button>
					</div>
					<p class="llm-phrase-game__prompt llm-phrase-game__prompt--translate"></p>
					<label class="screen-reader-text" for="<?php echo esc_attr( $uid ); ?>-input1"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'sr_your_translation' ) ); ?></label>
					<div class="llm-phrase-game__compose llm-phrase-game__compose--phase1">
						<div class="llm-phrase-game__input-block">
							<div class="llm-phrase-game__input-shell">
								<textarea id="<?php echo esc_attr( $uid ); ?>-input1" class="llm-phrase-game__input llm-phrase-game__input--1" rows="3"></textarea>
							</div>
							<button type="button" class="llm-phrase-game__mic llm-phrase-game__mic--1" aria-label="<?php echo esc_attr( LLM_Phrase_Game_I18n::get( 'sr_mic' ) . ' ' . $mic_btn_text ); ?>">
								<span class="llm-phrase-game__mic-icon" aria-hidden="true">&#127908;</span>
								<span class="llm-phrase-game__mic-text"><?php echo esc_html( $mic_btn_text ); ?></span>
							</button>
						</div>
						<button type="button" class="llm-phrase-game__btn llm-phrase-game__btn--continue1 button"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'continue' ) ); ?></button>
					</div>
				</div>
				<div class="llm-phrase-game__message" role="alert"></div>
				<div class="llm-phrase-game__analysis" hidden>
					<div class="llm-phrase-game__your-phrase-wrap" hidden>
						<p class="llm-phrase-game__your-phrase-label"><strong><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'your_phrase_label' ) ); ?></strong></p>
						<p class="llm-phrase-game__your-phrase-text"></p>
					</div>
					<p class="llm-phrase-game__bravo"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'bravo_intro' ) ); ?></p>
					<div class="llm-phrase-game__grammar"></div>
					<p class="llm-phrase-game__label-main"><strong><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'label_main' ) ); ?></strong></p>
					<p class="llm-phrase-game__target"></p>
					<p class="llm-phrase-game__label-alt"><strong><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'label_alt' ) ); ?></strong></p>
					<p class="llm-phrase-game__alt"></p>
				</div>
				<div class="llm-phrase-game__phase llm-phrase-game__phase--2" hidden>
					<p class="llm-phrase-game__prompt llm-phrase-game__prompt--rewrite"></p>
					<label class="screen-reader-text" for="<?php echo esc_attr( $uid ); ?>-input2"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'sr_rewrite' ) ); ?></label>
					<div class="llm-phrase-game__compose llm-phrase-game__compose--phase2">
						<div class="llm-phrase-game__input-block">
							<div class="llm-phrase-game__input-shell">
								<textarea id="<?php echo esc_attr( $uid ); ?>-input2" class="llm-phrase-game__input llm-phrase-game__input--2" rows="3"></textarea>
							</div>
							<button type="button" class="llm-phrase-game__mic llm-phrase-game__mic--2" aria-label="<?php echo esc_attr( LLM_Phrase_Game_I18n::get( 'sr_mic' ) . ' ' . $mic_btn_text ); ?>">
								<span class="llm-phrase-game__mic-icon" aria-hidden="true">&#127908;</span>
								<span class="llm-phrase-game__mic-text"><?php echo esc_html( $mic_btn_text ); ?></span>
							</button>
						</div>
						<button type="button" class="llm-phrase-game__btn llm-phrase-game__btn--continue2 button"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'continue' ) ); ?></button>
					</div>
					<div class="llm-phrase-game__message-phase2" role="status" aria-live="polite"></div>
				</div>
			</div>
		<div class="llm-phrase-game__done" hidden>
			<p class="llm-phrase-game__done-text"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'done_all' ) ); ?></p>
			<?php if ( is_user_logged_in() ) : ?>
			<button type="button" class="llm-phrase-game__restart-btn button"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'story_progress_restart' ) ); ?></button>
			<?php endif; ?>
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
				$prog_row = LLM_Story_Game_Progress::get_row( $uid, $story_id );
				$run      = ( is_array( $prog_row ) && isset( $prog_row['run_completions'] ) ) ? (int) $prog_row['run_completions'] : 0;
				if ( $run > 0 ) {
					$show = min( $run, $n_phrases );
					for ( $ix = 0; $ix < $show; $ix++ ) {
						if ( isset( $phrases[ $ix ]['target'] ) ) {
							$completed_targets[] = array(
								'target'    => (string) $phrases[ $ix ]['target'],
								'interface' => isset( $phrases[ $ix ]['interface'] ) ? (string) $phrases[ $ix ]['interface'] : '',
							);
						}
					}
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

		wp_enqueue_style( 'llm-phrase-game' );
		wp_enqueue_script( 'llm-phrase-game' );

		wp_localize_script(
			'llm-phrase-game',
			'llmPhraseGame',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'llm_phrase_game' ),
				'storyId'         => $story_id,
				'phrases'         => $boot,
				'targetLangLabel' => LLM_Phrase_Game_I18n::target_lang_label_for_ui( $target_code ),
				'i18n'                => array(
				'translatePrompt'  => LLM_Phrase_Game_I18n::get( 'translate_prompt' ),
				'rewritePrompt'    => LLM_Phrase_Game_I18n::get( 'rewrite_prompt' ),
				'phase1Fail'       => LLM_Phrase_Game_I18n::get( 'phase1_fail' ),
			'phase2Fail'       => LLM_Phrase_Game_I18n::get( 'phase2_fail' ),
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
			),
				'gameFinished'        => $game_finished,
				'savedPhraseIndex'    => $saved_phrase_ix,
				'savedStep'           => $saved_step,
				'resumeAnalysis'      => $resume_analysis,
				'completedStoryLines' => $completed_targets,
				'speechLang'          => self::speech_locale( $target_code ),
				'validation'          => array(
					'phase1MinRatio'     => self::PHASE1_MIN_RATIO,
					'phase2MinSimilar'   => self::PHASE2_MIN_SIMILAR,
					'phase2MinWordRatio' => self::PHASE2_MIN_WORD_RATIO,
				),
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

		if ( '' === trim( $user ) ) {
			wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'empty_input' ) ) );
		}

		if ( 1 === $phase ) {
			$ratio = self::reference_words_found_ratio( $user, $target );
			if ( $ratio < self::PHASE1_MIN_RATIO ) {
				wp_send_json_error(
					array(
						'message' => LLM_Phrase_Game_I18n::get( 'phase1_fail' ),
					)
				);
			}

			if ( is_user_logged_in() ) {
				LLM_Story_Game_Progress::upsert(
					get_current_user_id(),
					$story_id,
					$index,
					LLM_Story_Game_Progress::STEP_REWRITE
				);
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
			if ( ! self::phase2_passes( $user, $target ) ) {
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
	public static function phase2_passes( $user_text, $reference_text ) {
		$u = self::normalize_sentence( $user_text );
		$r = self::normalize_sentence( $reference_text );
		if ( '' === $r ) {
			return true;
		}
		if ( '' === $u ) {
			return false;
		}
		return $u === $r;
	}

	/**
	 * @param string $s Testo.
	 * @return string
	 */
	public static function normalize_sentence( $s ) {
		$s = wp_strip_all_tags( (string) $s );
		$s = strtolower( $s );
		$s = preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $s );
		$s = preg_replace( '/\s+/u', ' ', $s );
		return trim( $s );
	}

	/**
	 * AJAX: ricomincia la storia dalla prima frase (solo utenti loggati).
	 */
	public static function ajax_restart() {
		check_ajax_referer( 'llm_phrase_game', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not logged in.' ), 403 );
		}

		$story_id = isset( $_POST['story_id'] ) ? absint( wp_unslash( $_POST['story_id'] ) ) : 0;
		if ( ! $story_id ) {
			wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'invalid_story' ) ), 400 );
		}

		$post = get_post( $story_id );
		if ( ! $post || LLM_STORY_CPT !== $post->post_type ) {
			wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'invalid_story' ) ), 400 );
		}

		LLM_User_Stats::reset_story_progress_for_user( get_current_user_id(), $story_id );
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
