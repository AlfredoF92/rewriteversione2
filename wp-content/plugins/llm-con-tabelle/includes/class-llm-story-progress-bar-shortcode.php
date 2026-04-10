<?php
/**
 * Shortcode: barra progresso frasi completate + “Ricomincia la storia”.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_Story_Progress_Bar_Shortcode
 */
class LLM_Story_Progress_Bar_Shortcode {

	const SHORTCODE = 'llm_story_progress_bar';

	const NONCE_ACTION = 'llm_story_progress_bar';

	/**
	 * Avvio.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'wp_ajax_llm_story_progress_reset', array( __CLASS__, 'ajax_reset' ) );
	}

	/**
	 * Script e stile (una volta per richiesta).
	 */
	private static function enqueue_assets() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		wp_enqueue_style(
			'llm-story-progress-bar',
			LLM_TABELLE_URL . 'assets/llm-story-progress-bar.css',
			array( 'llm-font-manrope' ),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_script(
			'llm-story-progress-bar',
			LLM_TABELLE_URL . 'assets/llm-story-progress-bar.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
		wp_localize_script(
			'llm-story-progress-bar',
			'llmStoryProgressBar',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'confirm'   => LLM_Phrase_Game_I18n::get( 'story_progress_confirm' ),
					'ajaxError' => LLM_Phrase_Game_I18n::get( 'ajax_error' ),
				),
			)
		);
	}

	/**
	 * @param array<string, string>|string $atts Attributi.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'story_id' => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE
		);

		$story_id = absint( $atts['story_id'] );
		if ( ! $story_id ) {
			$story_id = (int) get_queried_object_id();
		}

		if ( ! $story_id || LLM_STORY_CPT !== get_post_type( $story_id ) || 'publish' !== get_post_status( $story_id ) ) {
			return '';
		}

		$phrases = LLM_Story_Repository::get_phrases( $story_id );
		$total   = count( $phrases );
		if ( $total < 1 ) {
			return '';
		}

		self::enqueue_assets();

		$done = 0;
		if ( is_user_logged_in() ) {
			$map = LLM_User_Stats::get_phrase_map( get_current_user_id() );
			$key = (string) $story_id;
			if ( isset( $map[ $key ] ) && is_array( $map[ $key ] ) ) {
				$done = count( $map[ $key ] );
			}
		}

		$pct = $total > 0 ? (int) min( 100, round( ( 100 * $done ) / $total ) ) : 0;

		$logged_in = is_user_logged_in();
		$nonce     = $logged_in ? wp_create_nonce( self::NONCE_ACTION ) : '';
		$sr        = LLM_Phrase_Game_I18n::format( 'story_progress_sr', $done, $total );

		ob_start();
		?>
		<div
			class="llm-story-progress-bar"
			data-story-id="<?php echo esc_attr( (string) $story_id ); ?>"
			<?php if ( $logged_in ) : ?>
				data-nonce="<?php echo esc_attr( $nonce ); ?>"
			<?php endif; ?>
		>
			<div class="llm-story-progress-bar__row">
				<div
					class="llm-story-progress-bar__track"
					role="progressbar"
					aria-valuemin="0"
					aria-valuenow="<?php echo esc_attr( (string) $done ); ?>"
					aria-valuemax="<?php echo esc_attr( (string) $total ); ?>"
					aria-label="<?php echo esc_attr( $sr ); ?>"
				>
					<div class="llm-story-progress-bar__fill" style="width: <?php echo esc_attr( (string) $pct ); ?>%;"></div>
				</div>
				<span class="llm-story-progress-bar__count"><?php echo esc_html( (string) $done . ' / ' . (string) $total ); ?></span>
				<?php if ( $logged_in ) : ?>
					<button type="button" class="llm-story-progress-bar__restart">
						<?php echo esc_html( LLM_Phrase_Game_I18n::get( 'story_progress_restart' ) ); ?>
					</button>
				<?php endif; ?>
			</div>
			<?php if ( ! $logged_in ) : ?>
				<p class="llm-story-progress-bar__guest-hint"><?php echo esc_html( LLM_Phrase_Game_I18n::get( 'story_progress_guest' ) ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * AJAX: azzera progresso storia per l’utente corrente.
	 */
	public static function ajax_reset() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'story_progress_guest' ) ), 401 );
		}

		$story_id = isset( $_POST['story_id'] ) ? absint( wp_unslash( $_POST['story_id'] ) ) : 0;
		if ( ! $story_id || LLM_STORY_CPT !== get_post_type( $story_id ) || 'publish' !== get_post_status( $story_id ) ) {
			wp_send_json_error( array( 'message' => LLM_Phrase_Game_I18n::get( 'invalid_story' ) ), 400 );
		}

		LLM_User_Stats::reset_story_progress_for_user( get_current_user_id(), $story_id );

		$total = count( LLM_Story_Repository::get_phrases( $story_id ) );
		wp_send_json_success(
			array(
				'done'  => 0,
				'total' => $total,
			)
		);
	}
}
