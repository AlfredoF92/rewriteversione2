<?php
/**
 * Shortcode [llm_story_settings] — pannello impostazioni storia (ricomincia ecc.).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Story_Settings_Shortcode {

	const SHORTCODE    = 'llm_story_settings';
	const AJAX_ACTION  = 'llm_story_settings_restart';
	const NONCE_ACTION = 'llm_story_settings';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_restart' ) );
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
			$atts,
			self::SHORTCODE
		);

		$story_id = $atts['story_id'] !== '' ? absint( $atts['story_id'] ) : (int) get_the_ID();
		if ( ! $story_id ) {
			return '';
		}

		if ( ! is_user_logged_in() ) {
			return '';
		}

		$post = get_post( $story_id );
		if ( ! $post || LLM_STORY_CPT !== $post->post_type ) {
			return '';
		}

		$lang = LLM_User_Settings_I18n::lang();

		wp_enqueue_style(
			'llm-story-settings',
			LLM_TABELLE_URL . 'assets/llm-story-settings.css',
			array(),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_script(
			'llm-story-settings',
			LLM_TABELLE_URL . 'assets/llm-story-settings.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
		wp_localize_script(
			'llm-story-settings',
			'llmStorySettings',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( self::NONCE_ACTION ),
				'action'          => self::AJAX_ACTION,
				'restartConfirm'  => LLM_User_Settings_I18n::get( 'settings_restart_confirm', $lang ),
				'restartedMsg'    => LLM_User_Settings_I18n::get( 'settings_restarted', $lang ),
			)
		);

		$label_settings = LLM_User_Settings_I18n::get( 'settings_label', $lang );
		$label_restart  = LLM_User_Settings_I18n::get( 'settings_restart', $lang );

		ob_start();
		?>
		<div class="llm-story-settings" data-story-id="<?php echo esc_attr( (string) $story_id ); ?>">
			<button type="button" class="llm-story-settings__toggle" aria-expanded="false" aria-controls="llm-story-settings-panel-<?php echo esc_attr( (string) $story_id ); ?>">
				<?php echo esc_html( $label_settings ); ?>
				<span class="llm-story-settings__arrow" aria-hidden="true">&#9660;</span>
			</button>
			<div class="llm-story-settings__panel" id="llm-story-settings-panel-<?php echo esc_attr( (string) $story_id ); ?>" hidden>
				<button type="button" class="llm-story-settings__restart-btn" data-story-id="<?php echo esc_attr( (string) $story_id ); ?>">
					<?php echo esc_html( $label_restart ); ?>
				</button>
				<p class="llm-story-settings__msg" role="status" aria-live="polite"></p>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * AJAX: ricomincia storia (reset completo phrase_done + checkpoint).
	 */
	public static function ajax_restart() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => LLM_User_Settings_I18n::get( 'unauthorized' ) ), 403 );
		}

		$story_id = isset( $_POST['story_id'] ) ? absint( wp_unslash( $_POST['story_id'] ) ) : 0;
		if ( ! $story_id ) {
			wp_send_json_error( array( 'message' => LLM_User_Settings_I18n::get( 'generic_error' ) ), 400 );
		}

		$post = get_post( $story_id );
		if ( ! $post || LLM_STORY_CPT !== $post->post_type ) {
			wp_send_json_error( array( 'message' => LLM_User_Settings_I18n::get( 'generic_error' ) ), 400 );
		}

		LLM_User_Stats::reset_story_for_replay( get_current_user_id(), $story_id );

		wp_send_json_success();
	}
}
