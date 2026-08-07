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
	}

	private static function enqueue() {
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

		$i18n   = LLM_Crossword_I18n::bundle();
		$config = array(
			'id'           => $post_id,
			'title'        => $data['title'],
			'grid'         => $data['grid'],
			'clues'        => $data['clues'],
			'i18n'         => $i18n,
			'saveProgress' => 'no' !== strtolower( (string) $atts['progress'] ),
		);

		ob_start();
		?>
		<div class="llm-crossword llm-ui-scope llm-ui-scope--light" data-llm-crossword data-crossword-id="<?php echo esc_attr( (string) $post_id ); ?>">
			<script type="application/json" class="llm-crossword__config"><?php echo wp_json_encode( $config ); ?></script>
			<div class="cw-container">
				<div class="cw-board">
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
						<button type="button" class="llm-ui-btn llm-ui-btn--ghost cw-btn" data-cw-check><?php echo esc_html( $i18n['check'] ); ?></button>
						<button type="button" class="llm-ui-btn llm-ui-btn--ghost cw-btn" data-cw-restart><?php echo esc_html( $i18n['restart'] ); ?></button>
					</div>
					<p class="cw-status" data-cw-status role="status" aria-live="polite"></p>
				</div>
				<div class="cw-panel">
					<div class="cw-entrylist" data-cw-clues></div>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
