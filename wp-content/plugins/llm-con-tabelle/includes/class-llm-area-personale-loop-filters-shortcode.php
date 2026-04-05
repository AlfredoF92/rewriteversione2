<?php
/**
 * Shortcode: form GET per filtrare il Loop Elementor con Query ID AreaPersonale (e ID personalizzabile).
 *
 * Attributi:
 * - query_id (default AreaPersonale) — stesso valore del campo Query ID nel Loop Grid.
 * - loop_data_id — opzionale: valore data-id del widget Loop Grid (ispeziona elemento). Se omesso, si usa il primo Loop Grid che segue i filtri nel DOM.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_Area_Personale_Loop_Filters_Shortcode
 */
class LLM_Area_Personale_Loop_Filters_Shortcode {

	const SHORTCODE = 'llm_area_personale_loop_filters';

	/**
	 * Avvio.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Script/CSS per aggiornamento AJAX del loop (registrati sempre; enqueue solo nello shortcode).
	 */
	public static function register_assets() {
		wp_register_style(
			'llm-ap-loop-filters',
			LLM_TABELLE_URL . 'assets/llm-area-personale-filters.css',
			array( 'llm-ui' ),
			LLM_TABELLE_VERSION
		);
		wp_register_script(
			'llm-ap-loop-filters',
			LLM_TABELLE_URL . 'assets/llm-area-personale-filters.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
	}

	/**
	 * @param array<string, string>|string $atts Attributi.
	 * @return string
	 */
	public static function render( $atts ) {
		if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'query_id'      => 'AreaPersonale',
				'loop_data_id'  => '',
			),
			$atts,
			self::SHORTCODE
		);
		$atts['loop_data_id'] = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $atts['loop_data_id'] );

		wp_enqueue_style( 'llm-ui' );
		wp_enqueue_style( 'llm-ap-loop-filters' );
		wp_enqueue_script( 'llm-ap-loop-filters' );

		$qid = LLM_Elementor_Unlocked_Stories_Loop::ensure_query_hook( (string) $atts['query_id'] );
		if ( $qid === '' ) {
			$qid = LLM_Elementor_Unlocked_Stories_Loop::ensure_query_hook( 'AreaPersonale' );
		}
		if ( $qid === '' ) {
			return '';
		}

		$scope = isset( $_GET['llm_ap_scope'] ) ? sanitize_key( wp_unslash( (string) $_GET['llm_ap_scope'] ) ) : '';
		$lang  = isset( $_GET['llm_ap_target_lang'] ) ? sanitize_key( wp_unslash( (string) $_GET['llm_ap_target_lang'] ) ) : '';
		$s     = isset( $_GET['llm_ap_s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['llm_ap_s'] ) ) : '';

		$clear_url = remove_query_arg(
			array( 'llm_ap_scope', 'llm_ap_target_lang', 'llm_ap_s' )
		);

		$lang_options = class_exists( 'LLM_Languages' ) ? LLM_Languages::get_codes() : array();

		ob_start();
		?>
		<div
			class="llm-ap-loop-filters llm-ui-scope"
			data-llm-ap-query-id="<?php echo esc_attr( $qid ); ?>"
			<?php if ( $atts['loop_data_id'] !== '' ) : ?>
				data-llm-ap-loop-data-id="<?php echo esc_attr( (string) $atts['loop_data_id'] ); ?>"
			<?php endif; ?>
		>
			<div class="llm-ui-form-bar">
				<?php if ( ! is_user_logged_in() ) : ?>
					<p class="llm-ui-notice llm-ap-loop-filters__notice"><?php esc_html_e( 'Accedi per usare i filtri sulle tue storie.', 'llm-con-tabelle' ); ?></p>
				<?php endif; ?>
				<form class="llm-ap-loop-filters__form llm-ui-form-bar__inner" method="get" action="">
					<div class="llm-ui-field llm-ui-field--fixed llm-ap-loop-filters__field">
						<label class="llm-ui-label" for="llm_ap_scope"><?php esc_html_e( 'Storie', 'llm-con-tabelle' ); ?></label>
						<select class="llm-ui-select" name="llm_ap_scope" id="llm_ap_scope">
							<option value="" <?php selected( $scope, '' ); ?>><?php esc_html_e( 'Tutte le sbloccate', 'llm-con-tabelle' ); ?></option>
							<option value="active" <?php selected( $scope, 'active' ); ?>><?php esc_html_e( 'Solo in corso (non completate)', 'llm-con-tabelle' ); ?></option>
							<option value="completed" <?php selected( $scope, 'completed' ); ?>><?php esc_html_e( 'Solo completate', 'llm-con-tabelle' ); ?></option>
						</select>
					</div>
					<?php if ( ! empty( $lang_options ) ) : ?>
						<div class="llm-ui-field llm-ui-field--fixed llm-ap-loop-filters__field">
							<label class="llm-ui-label" for="llm_ap_target_lang"><?php esc_html_e( 'Lingua', 'llm-con-tabelle' ); ?></label>
							<select class="llm-ui-select" name="llm_ap_target_lang" id="llm_ap_target_lang">
								<option value="" <?php selected( $lang, '' ); ?>><?php esc_html_e( 'Tutte', 'llm-con-tabelle' ); ?></option>
								<?php foreach ( $lang_options as $code => $label ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $lang, $code ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>
					<div class="llm-ui-field llm-ui-field--search llm-ap-loop-filters__field">
						<label class="llm-ui-label" for="llm_ap_s"><?php esc_html_e( 'Titolo', 'llm-con-tabelle' ); ?></label>
						<input class="llm-ui-input" type="search" name="llm_ap_s" id="llm_ap_s" value="<?php echo esc_attr( $s ); ?>" placeholder="<?php echo esc_attr__( 'Cerca…', 'llm-con-tabelle' ); ?>" autocomplete="off" />
					</div>
					<div class="llm-ui-form-actions llm-ap-loop-filters__actions">
						<button type="submit" class="llm-ui-btn llm-ui-btn--primary llm-ap-loop-filters__submit"><?php esc_html_e( 'Applica', 'llm-con-tabelle' ); ?></button>
						<a class="llm-ui-btn llm-ui-btn--ghost llm-ap-loop-filters__reset" href="<?php echo esc_url( $clear_url ); ?>"><?php esc_html_e( 'Azzera', 'llm-con-tabelle' ); ?></a>
					</div>
				</form>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
