<?php
/**
 * Shortcode: chip-filter (categoria + stato lettura) per il Loop Grid Elementor.
 *
 * Uso:  [llm_storie_filtri]
 *       [llm_storie_filtri loop_data_id="abc123" show_scope="yes"]
 *
 * Attributi:
 * - loop_data_id   Valore data-id del widget Loop Grid in Elementor
 *                  (tasto dx sul loop in editor → Ispeziona → cerca data-id="…").
 *                  Se omesso, lo script trova il primo Loop Grid dopo i filtri nel DOM.
 * - show_scope     "yes" | "no"  — mostra chip "Tutte / In corso / Completate" (default: yes).
 * - show_cats      "yes" | "no"  — mostra chip categorie (default: yes).
 * - class          Classe CSS extra sul wrapper.
 *
 * Il filtro usa gli stessi parametri GET riconosciuti da LLM_Elementor_Homepage_Stories_Loop:
 *   llm_hs_cat   = slug categoria
 *   llm_hs_scope = smart | active | completed | all
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_Storie_Filtri_Shortcode
 */
class LLM_Storie_Filtri_Shortcode {

	const SHORTCODE = 'llm_storie_filtri';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style(
			'llm-storie-filtri',
			LLM_TABELLE_URL . 'assets/llm-storie-filtri.css',
			array(),
			LLM_TABELLE_VERSION
		);
		wp_register_script(
			'llm-storie-filtri',
			LLM_TABELLE_URL . 'assets/llm-storie-filtri.js',
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
				'show_scope'   => 'yes',
				'show_cats'    => 'yes',
				'class'        => '',
			),
			$atts,
			self::SHORTCODE
		);

		$loop_data_id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $atts['loop_data_id'] );
		$show_scope   = 'yes' === strtolower( (string) $atts['show_scope'] );
		$show_cats    = 'yes' === strtolower( (string) $atts['show_cats'] );
		$extra_class  = sanitize_html_class( (string) $atts['class'] );

		wp_enqueue_style( 'llm-storie-filtri' );
		wp_enqueue_script( 'llm-storie-filtri' );

		$current_cat   = isset( $_GET[ LLM_Elementor_Homepage_Stories_Loop::GET_CAT ] )
			? sanitize_title( wp_unslash( (string) $_GET[ LLM_Elementor_Homepage_Stories_Loop::GET_CAT ] ) )
			: '';
		$current_scope = isset( $_GET[ LLM_Elementor_Homepage_Stories_Loop::GET_SCOPE ] )
			? sanitize_key( wp_unslash( (string) $_GET[ LLM_Elementor_Homepage_Stories_Loop::GET_SCOPE ] ) )
			: 'smart';
		if ( '' === $current_scope ) {
			$current_scope = 'smart';
		}

		$cats      = $show_cats ? self::get_cats() : array();
		$user_lang = class_exists( 'LLM_Category_Translations' )
			? LLM_Category_Translations::current_user_lang()
			: 'it';
		$ui        = class_exists( 'LLM_Category_Translations' )
			? LLM_Category_Translations::ui_labels( $user_lang )
			: array();

		/* Messaggio se utente loggato non ha impostato la lingua */
		$lang_notice = '';
		if ( is_user_logged_in() ) {
			$ll = sanitize_key( (string) get_user_meta( get_current_user_id(), LLM_User_Meta::LEARNING_LANG, true ) );
			if ( $ll === '' || ( class_exists( 'LLM_Languages' ) && ! LLM_Languages::is_valid( $ll ) ) ) {
				$lang_notice = isset( $ui['missing_learning'] ) ? $ui['missing_learning'] : __( 'Nessuna lingua di studio impostata: vengono mostrate tutte le storie. Vai al tuo profilo per scegliere la lingua.', 'llm-con-tabelle' );
			}
		}

		ob_start();
		$wrap_class = 'llm-storie-filtri';
		if ( $extra_class !== '' ) {
			$wrap_class .= ' ' . $extra_class;
		}
		?>
		<div
			class="<?php echo esc_attr( $wrap_class ); ?>"
			data-llm-sf-root="1"
			<?php if ( $loop_data_id !== '' ) : ?>
				data-loop-data-id="<?php echo esc_attr( $loop_data_id ); ?>"
			<?php endif; ?>
		>
			<?php if ( $show_cats && ! empty( $cats ) ) : ?>
				<nav class="llm-sf-row llm-sf-row--cats" aria-label="<?php echo esc_attr( isset( $ui['filter_category'] ) ? $ui['filter_category'] : __( 'Filtra per categoria', 'llm-con-tabelle' ) ); ?>">
					<button
						class="llm-sf-chip<?php echo '' === $current_cat ? ' is-active' : ''; ?>"
						type="button"
						data-sf-cat=""
						aria-pressed="<?php echo '' === $current_cat ? 'true' : 'false'; ?>"
					><?php echo esc_html( isset( $ui['all'] ) ? $ui['all'] : __( 'Tutte', 'llm-con-tabelle' ) ); ?></button>
					<?php foreach ( $cats as $t ) : ?>
						<button
							class="llm-sf-chip<?php echo $current_cat === $t->slug ? ' is-active' : ''; ?>"
							type="button"
							data-sf-cat="<?php echo esc_attr( $t->slug ); ?>"
							aria-pressed="<?php echo $current_cat === $t->slug ? 'true' : 'false'; ?>"
						><?php
					$display_name = class_exists( 'LLM_Category_Translations' )
						? LLM_Category_Translations::get_translated_name( $t, $user_lang )
						: $t->name;
					echo esc_html( $display_name );
					?></button>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>

			<?php if ( $show_scope ) : ?>
				<nav class="llm-sf-row llm-sf-row--scope" aria-label="<?php echo esc_attr( isset( $ui['filter_reading'] ) ? $ui['filter_reading'] : __( 'Filtra per stato lettura', 'llm-con-tabelle' ) ); ?>">
					<?php
					$scope_items = array(
						'smart'     => isset( $ui['all'] ) ? $ui['all'] : __( 'Tutte', 'llm-con-tabelle' ),
						'active'    => isset( $ui['in_progress'] ) ? $ui['in_progress'] : __( 'In corso', 'llm-con-tabelle' ),
						'completed' => isset( $ui['completed'] ) ? $ui['completed'] : __( 'Completate', 'llm-con-tabelle' ),
					);
					if ( ! is_user_logged_in() ) {
						// Ospite: mostro solo "Tutte"
						$scope_items = array( 'smart' => isset( $ui['all'] ) ? $ui['all'] : __( 'Tutte', 'llm-con-tabelle' ) );
					}
					foreach ( $scope_items as $val => $label ) :
						$active = $current_scope === $val;
						?>
						<button
							class="llm-sf-chip<?php echo $active ? ' is-active' : ''; ?>"
							type="button"
							data-sf-scope="<?php echo esc_attr( $val ); ?>"
							aria-pressed="<?php echo $active ? 'true' : 'false'; ?>"
						><?php echo esc_html( $label ); ?></button>
					<?php endforeach; ?>
					<?php if ( ! is_user_logged_in() ) : ?>
						<span class="llm-sf-hint"><?php echo esc_html( isset( $ui['login_for_filter'] ) ? $ui['login_for_filter'] : __( 'Accedi per filtrare per stato lettura', 'llm-con-tabelle' ) ); ?></span>
					<?php endif; ?>
				</nav>
			<?php endif; ?>
			<?php if ( $lang_notice !== '' ) : ?>
				<p class="llm-sf-lang-notice"><?php echo esc_html( $lang_notice ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @return array<int, WP_Term>
	 */
	private static function get_cats() {
		if ( ! taxonomy_exists( 'category' ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}
		return $terms;
	}
}
