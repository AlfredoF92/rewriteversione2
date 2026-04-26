<?php
/**
 * Shortcode: filtri chip per il Loop Grid "continua-le-storie".
 *
 * Uso: [llm_continua_filtri]
 *      [llm_continua_filtri loop_data_id="abc123"]
 *
 * Attributi:
 * - loop_data_id  data-id del widget Loop Grid Elementor (tasto dx → Ispeziona, data-id="…").
 *                 Se omesso lo script prende il primo loop-grid dopo il filtro nel DOM.
 * - class         Classe CSS aggiuntiva.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_Continua_Filtri_Shortcode
 */
class LLM_Continua_Filtri_Shortcode {

	const SHORTCODE = 'llm_continua_filtri';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style(
			'llm-continua-filtri',
			LLM_TABELLE_URL . 'assets/llm-continua-filtri.css',
			array(),
			LLM_TABELLE_VERSION
		);
		wp_register_script(
			'llm-continua-filtri',
			LLM_TABELLE_URL . 'assets/llm-continua-filtri.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
	}

	/**
	 * @param array<string, string>|string $atts
	 * @return string
	 */
	public static function render( $atts ) {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'loop_data_id' => '',
				'class'        => '',
			),
			$atts,
			self::SHORTCODE
		);

		$loop_data_id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $atts['loop_data_id'] );
		$extra_class  = sanitize_html_class( (string) $atts['class'] );

		wp_enqueue_style( 'llm-continua-filtri' );
		wp_enqueue_script( 'llm-continua-filtri' );

		$user_lang = class_exists( 'LLM_Category_Translations' )
			? LLM_Category_Translations::current_user_lang()
			: 'it';
		$ui        = class_exists( 'LLM_Category_Translations' )
			? LLM_Category_Translations::ui_labels( $user_lang )
			: array();

		if ( ! is_user_logged_in() ) {
			return '<p class="llm-cf-login-notice">' . esc_html( isset( $ui['login_for_stories'] ) ? $ui['login_for_stories'] : __( 'Accedi per vedere le tue storie.', 'llm-con-tabelle' ) ) . '</p>';
		}

		$uid = get_current_user_id();

		$current_scope = isset( $_GET[ LLM_Continua_Storie_Loop::GET_SCOPE ] )
			? sanitize_key( wp_unslash( (string) $_GET[ LLM_Continua_Storie_Loop::GET_SCOPE ] ) )
			: '';

		/* Conta in_progress e completed per mostrare i badge */
		$completed_map = LLM_User_Stats::get_completed_stories_map( $uid );
		$completed_ids = array_map( 'absint', array_keys( $completed_map ) );
		$phrase_map    = LLM_User_Stats::get_phrase_map( $uid );
		$in_prog_ids   = array_values(
			array_diff(
				array_map( 'absint', array_keys( $phrase_map ) ),
				$completed_ids
			)
		);
		$count_active    = count( $in_prog_ids );
		$count_completed = count( $completed_ids );

		$wrap_class = 'llm-continua-filtri';
		if ( $extra_class !== '' ) {
			$wrap_class .= ' ' . $extra_class;
		}

		ob_start();
		?>
		<div
			class="<?php echo esc_attr( $wrap_class ); ?>"
			data-llm-cf-root="1"
			<?php if ( $loop_data_id !== '' ) : ?>
				data-loop-data-id="<?php echo esc_attr( $loop_data_id ); ?>"
			<?php endif; ?>
		>
			<nav class="llm-cf-row" aria-label="<?php echo esc_attr( isset( $ui['filter_reading'] ) ? $ui['filter_reading'] : __( 'Filtra per stato lettura', 'llm-con-tabelle' ) ); ?>">
				<?php
				$tabs = array(
					''          => isset( $ui['all_stories'] ) ? $ui['all_stories'] : __( 'Tutte le storie', 'llm-con-tabelle' ),
					'active'    => isset( $ui['to_continue'] ) ? $ui['to_continue'] : __( 'Da continuare', 'llm-con-tabelle' ),
					'completed' => isset( $ui['completed'] ) ? $ui['completed'] : __( 'Completate', 'llm-con-tabelle' ),
				);
				$counts = array(
					''          => $count_active + $count_completed,
					'active'    => $count_active,
					'completed' => $count_completed,
				);
				foreach ( $tabs as $val => $label ) :
					$active = ( $current_scope === $val );
					$cnt    = $counts[ $val ];
					?>
					<button
						class="llm-cf-chip<?php echo $active ? ' is-active' : ''; ?>"
						type="button"
						data-cf-scope="<?php echo esc_attr( $val ); ?>"
						aria-pressed="<?php echo $active ? 'true' : 'false'; ?>"
					>
					<?php echo esc_html( $label ); ?>
					</button>
				<?php endforeach; ?>
			</nav>
			<?php if ( $count_active === 0 && $count_completed === 0 ) : ?>
				<p class="llm-cf-empty-notice"><?php echo esc_html( isset( $ui['no_started_stories'] ) ? $ui['no_started_stories'] : __( 'Non hai ancora iniziato nessuna storia.', 'llm-con-tabelle' ) ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
