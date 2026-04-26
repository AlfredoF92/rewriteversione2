<?php
/**
 * Shortcode: filtri storie (categoria + scope incluso «solo completate») in AJAX,
 * allineati allo stesso Query ID del Loop Elementor (es. Loop-storie-homepage).
 *
 * Shortcode: [llm_story_loop_filters query_id="Loop-storie-homepage" posts_per_page="12"]
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode filtri + elenco storie via AJAX.
 */
class LLM_Story_Loop_Filters_Shortcode {

	const SHORTCODE   = 'llm_story_loop_filters';
	const AJAX_ACTION = 'llm_story_loop_filters';
	const NONCE_ACTION = 'llm_story_loop_filters_nonce';

	/**
	 * Avvio.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_list' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_list' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 6 );
	}

	/**
	 * Registra CSS/JS (enqueue solo quando lo shortcode è in pagina).
	 */
	public static function register_assets() {
		wp_register_style(
			'llm-story-loop-filters-sc',
			LLM_TABELLE_URL . 'assets/llm-story-loop-filters-shortcode.css',
			array(),
			LLM_TABELLE_VERSION
		);
		wp_register_script(
			'llm-story-loop-filters-sc',
			LLM_TABELLE_URL . 'assets/llm-story-loop-filters-shortcode.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
		wp_localize_script(
			'llm-story-loop-filters-sc',
			'llmStoryLoopFilters',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'errMsg'  => __( 'Aggiornamento elenco non riuscito. Ricarica la pagina.', 'llm-con-tabelle' ),
			)
		);
	}

	/**
	 * @param array<string, string> $atts
	 * @return string
	 */
	public static function render_shortcode( $atts ) {
		wp_enqueue_style( 'llm-story-loop-filters-sc' );
		wp_enqueue_script( 'llm-story-loop-filters-sc' );

		$atts = shortcode_atts(
			array(
				'query_id'         => 'Loop-storie-homepage',
				'posts_per_page'   => '12',
				'sync_url'         => 'yes',
				'class'            => '',
				'show_scope'       => 'yes',
			),
			$atts,
			self::SHORTCODE
		);

		$qid = LLM_Elementor_Homepage_Stories_Loop::sanitize_query_id( $atts['query_id'] );
		if ( $qid === '' || ! LLM_Elementor_Homepage_Stories_Loop::widget_matches_query_id( $qid ) ) {
			if ( current_user_can( 'edit_posts' ) ) {
				return '<p class="llm-sl-msg llm-sl-msg--error">' . esc_html__( 'Query ID non riconosciuto: deve coincidere con il Loop Grid e con il filtro llm_elementor_homepage_stories_loop_query_ids.', 'llm-con-tabelle' ) . '</p>';
			}
			return '';
		}

		$ppp      = (int) $atts['posts_per_page'];
		$sync_url = ( 'yes' === strtolower( (string) $atts['sync_url'] ) );
		$show_scope = ( 'yes' === strtolower( (string) $atts['show_scope'] ) );

		$cat_init = isset( $_GET[ LLM_Elementor_Homepage_Stories_Loop::GET_CAT ] )
			? sanitize_title( wp_unslash( (string) $_GET[ LLM_Elementor_Homepage_Stories_Loop::GET_CAT ] ) )
			: '';
		$scope_init = isset( $_GET[ LLM_Elementor_Homepage_Stories_Loop::GET_SCOPE ] )
			? sanitize_key( wp_unslash( (string) $_GET[ LLM_Elementor_Homepage_Stories_Loop::GET_SCOPE ] ) )
			: 'smart';
		if ( '' === $scope_init ) {
			$scope_init = 'smart';
		}

		$ids       = LLM_Elementor_Homepage_Stories_Loop::get_filtered_story_ids_for_scope( $cat_init, $scope_init, null );
		$list      = self::build_list_html( $ids, $ppp );
		$user_lang = class_exists( 'LLM_Category_Translations' )
			? LLM_Category_Translations::current_user_lang()
			: 'it';
		$uid   = 'llm-sl-' . wp_generate_password( 8, false, false );
		$extra = trim( (string) $atts['class'] );

		ob_start();
		?>
		<div
			class="llm-story-loop-filters<?php echo $extra !== '' ? ' ' . esc_attr( $extra ) : ''; ?>"
			data-llm-sl-root="1"
			data-query-id="<?php echo esc_attr( $qid ); ?>"
			data-posts-per-page="<?php echo esc_attr( (string) $ppp ); ?>"
			data-sync-url="<?php echo $sync_url ? '1' : '0'; ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE_ACTION ) ); ?>"
		>
			<div class="llm-sl-toolbar">
				<div class="llm-sl-field">
					<label for="<?php echo esc_attr( $uid . '-cat' ); ?>" class="llm-sl-label"><?php esc_html_e( 'Categoria', 'llm-con-tabelle' ); ?></label>
					<select id="<?php echo esc_attr( $uid . '-cat' ); ?>" class="llm-sl-cat" aria-label="<?php esc_attr_e( 'Filtra per categoria', 'llm-con-tabelle' ); ?>">
						<option value=""<?php selected( $cat_init, '' ); ?>><?php esc_html_e( 'Tutte le categorie', 'llm-con-tabelle' ); ?></option>
						<?php foreach ( self::get_category_options() as $term ) : ?>
							<?php
							$term_label = class_exists( 'LLM_Category_Translations' )
								? LLM_Category_Translations::get_translated_name( $term, $user_lang )
								: $term->name;
							?>
							<option value="<?php echo esc_attr( $term->slug ); ?>"<?php selected( $cat_init, $term->slug ); ?>><?php echo esc_html( $term_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php if ( $show_scope ) : ?>
					<div class="llm-sl-field">
						<label for="<?php echo esc_attr( $uid . '-scope' ); ?>" class="llm-sl-label"><?php esc_html_e( 'Storie', 'llm-con-tabelle' ); ?></label>
						<select id="<?php echo esc_attr( $uid . '-scope' ); ?>" class="llm-sl-scope" aria-label="<?php esc_attr_e( 'Filtra per stato lettura', 'llm-con-tabelle' ); ?>">
							<option value="smart"<?php selected( $scope_init, 'smart' ); ?>><?php esc_html_e( 'Tutte (ordine consigliato)', 'llm-con-tabelle' ); ?></option>
							<option value="completed"<?php selected( $scope_init, 'completed' ); ?>><?php esc_html_e( 'Solo completate', 'llm-con-tabelle' ); ?></option>
							<option value="active"<?php selected( $scope_init, 'active' ); ?>><?php esc_html_e( 'Continua storie', 'llm-con-tabelle' ); ?></option>
							<option value="all"<?php selected( $scope_init, 'all' ); ?>><?php esc_html_e( 'Tutte per data', 'llm-con-tabelle' ); ?></option>
						</select>
					</div>
				<?php else : ?>
					<input type="hidden" class="llm-sl-scope" value="smart" />
				<?php endif; ?>
			</div>
			<div
				class="llm-sl-results"
				id="<?php echo esc_attr( $uid ); ?>"
				data-llm-sl-results="<?php echo esc_attr( $uid ); ?>"
				role="region"
				aria-live="polite"
				aria-relevant="additions removals"
				aria-label="<?php esc_attr_e( 'Elenco storie', 'llm-con-tabelle' ); ?>"
			>
				<?php echo wp_kses_post( $list ); ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @return array<int, WP_Term>
	 */
	private static function get_category_options() {
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

	/**
	 * @param array<int> $ids Ordine già applicato.
	 * @param int        $posts_per_page >0 limita; <=0 tutte.
	 * @return string
	 */
	public static function build_list_html( array $ids, $posts_per_page ) {
		$posts_per_page = (int) $posts_per_page;
		if ( $posts_per_page > 0 ) {
			$ids = array_slice( $ids, 0, $posts_per_page );
		}
		if ( empty( $ids ) ) {
			$html = '<p class="llm-sl-empty">' . esc_html__( 'Nessuna storia trovata.', 'llm-con-tabelle' ) . '</p>';
			return (string) apply_filters( 'llm_story_loop_filters_list_html', $html, array(), $posts_per_page );
		}

		ob_start();
		echo '<ul class="llm-sl-list">';
		foreach ( $ids as $pid ) {
			echo apply_filters( 'llm_story_loop_filters_item_html', self::default_item_html( (int) $pid ), (int) $pid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</ul>';
		$html = ob_get_clean();

		return (string) apply_filters( 'llm_story_loop_filters_list_html', $html, $ids, $posts_per_page );
	}

	/**
	 * @param int $post_id
	 * @return string
	 */
	private static function default_item_html( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return '';
		}
		$title    = get_the_title( $post_id );
		$url      = get_permalink( $post_id );
		$thumb_id = (int) get_post_thumbnail_id( $post_id );
		$alt      = '';
		if ( $thumb_id ) {
			$alt = (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
		}
		$thumb = get_the_post_thumbnail(
			$post_id,
			'medium',
			array(
				'class'   => 'llm-sl-list__thumb',
				'loading' => 'lazy',
				'alt'     => $alt,
			)
		);
		return '<li class="llm-sl-list__item"><a class="llm-sl-list__link" href="' . esc_url( $url ) . '">' . $thumb . '<span class="llm-sl-list__title">' . esc_html( $title ) . '</span></a></li>';
	}

	/**
	 * AJAX: restituisce HTML elenco.
	 */
	public static function ajax_list() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$raw_qid = isset( $_POST['query_id'] ) ? wp_unslash( (string) $_POST['query_id'] ) : '';
		$qid     = LLM_Elementor_Homepage_Stories_Loop::sanitize_query_id( $raw_qid );
		if ( $qid === '' || ! LLM_Elementor_Homepage_Stories_Loop::widget_matches_query_id( $qid ) ) {
			wp_send_json_error( array( 'message' => 'bad_query_id' ), 400 );
		}

		$cat   = isset( $_POST['cat'] ) ? sanitize_title( wp_unslash( (string) $_POST['cat'] ) ) : '';
		$scope = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( (string) $_POST['scope'] ) ) : 'smart';
		if ( '' === $scope ) {
			$scope = 'smart';
		}

		$ppp = isset( $_POST['posts_per_page'] ) ? (int) $_POST['posts_per_page'] : 12;
		if ( $ppp < -1 ) {
			$ppp = 12;
		}

		$ids = LLM_Elementor_Homepage_Stories_Loop::get_filtered_story_ids_for_scope( $cat, $scope, null );
		$html = self::build_list_html( $ids, $ppp );

		wp_send_json_success(
			array(
				'html'  => $html,
				'count' => count( $ids ),
			)
		);
	}
}
