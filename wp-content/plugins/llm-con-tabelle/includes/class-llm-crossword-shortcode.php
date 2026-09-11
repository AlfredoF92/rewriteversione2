<?php
/**
 * Shortcode [llm_crossword id="123"]: griglia giocabile + definizioni.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Crossword_Shortcode {

	public static function init() {
		add_shortcode( 'llm_crossword', array( __CLASS__, 'render' ) );
		add_action( 'wp_ajax_llm_crossword_progress_save', array( __CLASS__, 'ajax_save_progress' ) );
	}

	public static function enqueue() {
		wp_enqueue_style( 'llm-ui' );
		wp_enqueue_style(
			'llm-crossword',
			LLM_TABELLE_URL . 'assets/llm-crossword.css',
			array( 'llm-ui' ),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_script(
			'llm-guest-browser-store',
			LLM_TABELLE_URL . 'assets/llm-guest-browser-store.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
		wp_enqueue_script(
			'llm-crossword',
			LLM_TABELLE_URL . 'assets/llm-crossword.js',
			array( 'llm-guest-browser-store' ),
			LLM_TABELLE_VERSION,
			true
		);
		static $localized = false;
		if ( ! $localized ) {
			$localized = true;
			wp_localize_script(
				'llm-crossword',
				'llmCrossword',
				array(
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'llm_crossword_progress' ),
					'loggedIn' => is_user_logged_in() ? 1 : 0,
				)
			);
		}
	}

	/**
	 * @param string $message Testo errore.
	 * @return string
	 */
	private static function error_box( $message ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		return '<p class="llm-crossword-error">' . esc_html( $message ) . '</p>';
	}

	/**
	 * @param array<string,mixed> $atts Attributi shortcode.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'       => 0,
				'story'    => 0,
				'progress' => 'yes',
			),
			$atts,
			'llm_crossword'
		);

		$post_id = absint( $atts['id'] );
		if ( ! $post_id ) {
			return self::error_box( __( 'Shortcode cruciverba: manca l’attributo id.', 'llm-con-tabelle' ) );
		}

		$data = LLM_Crossword::build( $post_id );
		if ( is_wp_error( $data ) ) {
			return self::error_box(
				sprintf(
					/* translators: 1: crossword ID, 2: error message */
					__( 'Cruciverba #%1$d: %2$s', 'llm-con-tabelle' ),
					$post_id,
					$data->get_error_message()
				)
			);
		}

		self::enqueue();

		$story_id = absint( $atts['story'] );
		$i18n     = LLM_Crossword_I18n::bundle();
		$known_code  = LLM_Crossword::get_known( $post_id );
		$target_code = LLM_Crossword::get_target( $post_id );
		$config      = array(
			'id'           => $post_id,
			'storyId'      => $story_id,
			'title'        => $data['title'],
			'grid'         => $data['grid'],
			'clues'        => $data['clues'],
			'knownFlag'    => class_exists( 'LLM_Languages' ) ? LLM_Languages::flag_emoji( $known_code ) : '',
			'targetFlag'   => class_exists( 'LLM_Languages' ) ? LLM_Languages::flag_emoji( $target_code ) : '',
			'i18n'         => $i18n,
			'saveProgress' => 'no' !== strtolower( (string) $atts['progress'] ),
			'loggedIn'     => is_user_logged_in() ? 1 : 0,
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'llm_crossword_progress' ),
			'savedCells'   => array(),
			'savedSolved'  => false,
		);

		$uid = get_current_user_id();
		if ( $uid && class_exists( 'LLM_User_Crossword_Progress' ) ) {
			$saved = LLM_User_Crossword_Progress::get( $uid, $post_id );
			if ( $saved ) {
				$config['savedCells']  = $saved['cells'];
				$config['savedSolved'] = ! empty( $saved['solved'] );
				if ( ! $story_id && ! empty( $saved['story_id'] ) ) {
					$config['storyId'] = (int) $saved['story_id'];
				}
			}
		}

		ob_start();
		?>
		<div class="llm-crossword llm-ui-scope llm-ui-scope--light" data-llm-crossword data-crossword-id="<?php echo esc_attr( (string) $post_id ); ?>">
			<script type="application/json" class="llm-crossword__config"><?php echo wp_json_encode( $config ); ?></script>
			<div class="cw-container">
				<div class="cw-board">
					<?php echo self::render_mobile_clue( $i18n, 'above' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<div class="cw-grid" data-cw-grid></div>
					<div class="cw-reveal-wrap">
						<button type="button" class="llm-ui-btn llm-ui-btn--primary cw-reveal" data-cw-reveal>
							<svg class="cw-reveal__icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
								<path fill="currentColor" d="M12 2a7 7 0 0 0-4 12.75V17a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-2.25A7 7 0 0 0 12 2zm2 14h-4v-.4a1 1 0 0 0 .4-.8V14h3.2v.8a1 1 0 0 0 .4.8V16zm.2-3.2H9.8A5 5 0 1 1 14.2 12.8zM10 20a1 1 0 0 0 1 1h2a1 1 0 1 0 0-2h-2a1 1 0 0 0-1 1z"/>
							</svg>
							<span><?php echo esc_html( $i18n['reveal_letter'] ); ?></span>
						</button>
					</div>
					<div class="cw-controls">
						<button type="button" class="llm-ui-btn llm-ui-btn--ghost cw-btn cw-btn--zoom" data-cw-zoom-out aria-label="<?php echo esc_attr( $i18n['zoom_out'] ); ?>">
							<span aria-hidden="true">🔍−</span>
						</button>
						<button type="button" class="llm-ui-btn llm-ui-btn--ghost cw-btn cw-btn--zoom" data-cw-zoom-in aria-label="<?php echo esc_attr( $i18n['zoom_in'] ); ?>">
							<span aria-hidden="true">🔍+</span>
						</button>
						<button type="button" class="llm-ui-btn llm-ui-btn--ghost cw-btn" data-cw-check><?php echo esc_html( $i18n['check'] ); ?></button>
						<button type="button" class="llm-ui-btn llm-ui-btn--ghost cw-btn" data-cw-restart><?php echo esc_html( $i18n['restart'] ); ?></button>
						<button
							type="button"
							class="llm-ui-btn llm-ui-btn--ghost llm-phrase-game__helper-acc cw-btn cw-keyboard__toggle"
							data-cw-keyboard-toggle
							aria-expanded="false"
							aria-controls="cw-keyboard-panel-<?php echo esc_attr( (string) $post_id ); ?>"
						>
							<span class="llm-phrase-game__helper-acc-emoji" aria-hidden="true">⌨️</span>
							<span class="llm-phrase-game__helper-acc-text"><?php echo esc_html( isset( $i18n['keyboard'] ) ? $i18n['keyboard'] : 'Tastiera' ); ?></span>
						</button>
					</div>
					<div class="cw-keyboard" data-cw-keyboard>
						<div
							class="cw-keyboard__panel llm-phrase-game__keyboard-panel"
							id="cw-keyboard-panel-<?php echo esc_attr( (string) $post_id ); ?>"
							data-cw-keyboard-panel
							hidden
						></div>
					</div>
					<p class="cw-status" data-cw-status role="status" aria-live="polite"></p>
					<?php echo self::render_mobile_clue( $i18n, 'below' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="cw-panel">
					<div class="cw-entrylist" data-cw-clues></div>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * AJAX: salva griglia (solo utenti loggati).
	 */
	public static function ajax_save_progress() {
		check_ajax_referer( 'llm_crossword_progress', 'nonce' );

		$uid = get_current_user_id();
		if ( ! $uid || ! class_exists( 'LLM_User_Crossword_Progress' ) ) {
			wp_send_json_error( array( 'message' => 'auth' ), 403 );
		}

		$crossword_id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$story_id     = isset( $_POST['story'] ) ? absint( wp_unslash( $_POST['story'] ) ) : 0;
		$filled       = isset( $_POST['filled'] ) ? absint( wp_unslash( $_POST['filled'] ) ) : 0;
		$total        = isset( $_POST['total'] ) ? absint( wp_unslash( $_POST['total'] ) ) : 0;
		$solved       = isset( $_POST['solved'] ) && '1' === (string) wp_unslash( $_POST['solved'] );
		$cells_raw    = isset( $_POST['cells'] ) ? wp_unslash( $_POST['cells'] ) : '[]';
		$cells        = LLM_User_Crossword_Progress::sanitize_cells( $cells_raw );

		if ( ! $crossword_id ) {
			wp_send_json_error( array( 'message' => 'id' ), 400 );
		}

		$post = get_post( $crossword_id );
		if ( ! $post || LLM_Crossword::CPT !== $post->post_type ) {
			wp_send_json_error( array( 'message' => 'crossword' ), 400 );
		}

		if ( $filled < 1 && ! $solved ) {
			$ok = LLM_User_Crossword_Progress::clear_grid( $uid, $crossword_id, $story_id, $cells, $total );
			if ( ! $ok ) {
				wp_send_json_error( array( 'message' => 'db' ), 500 );
			}
			wp_send_json_success( array( 'ok' => 1, 'cleared' => 1 ) );
		}

		$ok = LLM_User_Crossword_Progress::upsert( $uid, $crossword_id, $story_id, $cells, $filled, $total, $solved );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => 'db' ), 500 );
		}
		wp_send_json_success( array( 'ok' => 1 ) );
	}

	/**
	 * Riquadro definizione attiva + rivela lettera.
	 *
	 * @param array<string,string> $i18n Testi.
	 * @param string               $place above|below.
	 * @return string
	 */
	private static function render_mobile_clue( array $i18n, $place ) {
		$place = ( 'below' === $place ) ? 'below' : 'above';
		ob_start();
		?>
		<div class="cw-mobile-clue cw-mobile-clue--<?php echo esc_attr( $place ); ?>" data-cw-mobile-clue hidden>
			<div class="cw-mobile-clue__body">
				<span class="cw-mobile-clue__meta" data-cw-mobile-clue-meta></span>
				<div class="cw-mobile-clue__text" data-cw-mobile-clue-text></div>
			</div>
			<button
				type="button"
				class="cw-mobile-clue__hint"
				data-cw-reveal-mobile
				aria-label="<?php echo esc_attr( $i18n['reveal_letter'] ); ?>"
				title="<?php echo esc_attr( $i18n['reveal_letter'] ); ?>"
			>
				<span class="cw-mobile-clue__hint-emoji" aria-hidden="true">💡</span>
				<span class="cw-mobile-clue__hint-label"><?php echo esc_html( $i18n['reveal_letter'] ); ?></span>
			</button>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
