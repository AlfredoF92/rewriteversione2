<?php
/**
 * Loop Grid Elementor: Query ID “homepage” — tutte le storie LLM, filtri GET e ordinamento per progresso.
 *
 * Parametri URL (condividono prefisso con il widget filtri) o stessi valori via POST AJAX (azione
 * {@see LLM_Elementor_Homepage_Stories_Loop::AJAX_ACTION}):
 * - llm_hs_cat   = slug categoria WordPress (taxonomy category)
 * - llm_hs_scope = smart | active | completed | all
 *   · smart (default se assente): in corso → completate → nuove (solo utente loggato)
 *   · active: solo storie in corso
 *   · completed: solo completate
 *   · all: tutte, ordine data decrescente
 *
 * Query ID predefinito: Loop-storie-homepage (filtro llm_elementor_homepage_stories_loop_query_ids).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_Elementor_Homepage_Stories_Loop
 */
class LLM_Elementor_Homepage_Stories_Loop {

	const GET_SCOPE = 'llm_hs_scope';
	const GET_CAT   = 'llm_hs_cat';

	const AJAX_ACTION       = 'llm_hs_loop_fragment';
	const AJAX_NONCE_ACTION = 'llm_hs_loop';

	/**
	 * Contesto filtri durante richiesta AJAX (non usa $_GET).
	 *
	 * @var array{cat:string,scope:string}|null
	 */
	private static $ajax_filter_context = null;

	/**
	 * Avvio.
	 */
	public static function init() {
		if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			return;
		}
		add_filter( 'elementor/query/query_args', array( __CLASS__, 'filter_query_args' ), 22, 2 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_loop_fragment' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_loop_fragment' ) );
	}

	/**
	 * Slug categoria dai parametri effettivi (GET o contesto AJAX).
	 *
	 * @return string
	 */
	public static function get_effective_filter_cat() {
		if ( is_array( self::$ajax_filter_context ) ) {
			return self::$ajax_filter_context['cat'];
		}
		return isset( $_GET[ self::GET_CAT ] ) ? sanitize_title( wp_unslash( (string) $_GET[ self::GET_CAT ] ) ) : '';
	}

	/**
	 * Scope dai parametri effettivi (GET o contesto AJAX).
	 *
	 * @return string
	 */
	public static function get_effective_filter_scope() {
		if ( is_array( self::$ajax_filter_context ) ) {
			return self::$ajax_filter_context['scope'];
		}
		return isset( $_GET[ self::GET_SCOPE ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::GET_SCOPE ] ) ) : '';
	}

	/**
	 * AJAX: render pagina Elementor e restituisce innerHTML del nodo #id (solo selettore #id).
	 */
	public static function ajax_loop_fragment() {
		check_ajax_referer( self::AJAX_NONCE_ACTION, 'nonce' );

		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			wp_send_json_error( array( 'message' => 'no_elementor' ), 400 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$post_id = (int) apply_filters( 'llm_hs_loop_fragment_post_id', $post_id );
		$selector = isset( $_POST['selector'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['selector'] ) ) : '';
		$cat      = isset( $_POST['cat'] ) ? sanitize_title( wp_unslash( (string) $_POST['cat'] ) ) : '';
		$scope   = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( (string) $_POST['scope'] ) ) : '';

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => 'bad_post' ), 400 );
		}
		if ( 'publish' !== $post->post_status && ! current_user_can( 'read_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		self::$ajax_filter_context = array(
			'cat'   => $cat,
			'scope' => $scope,
		);

		try {
			$html = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $post_id, true );
		} catch ( \Throwable $e ) {
			self::$ajax_filter_context = null;
			wp_send_json_error( array( 'message' => 'render' ), 500 );
		}

		self::$ajax_filter_context = null;

		if ( ! is_string( $html ) || $html === '' ) {
			wp_send_json_error( array( 'message' => 'empty' ), 422 );
		}

		$fragment = self::extract_inner_html_by_css_id( $html, $selector );
		if ( is_wp_error( $fragment ) ) {
			wp_send_json_error(
				array(
					'message' => $fragment->get_error_message(),
					'code'    => $fragment->get_error_code(),
				),
				422
			);
		}

		wp_send_json_success( array( 'html' => $fragment ) );
	}

	/**
	 * Estrae innerHTML del primo elemento con id da selettore `#mio-id`.
	 *
	 * @param string $html     HTML completo (documento Elementor).
	 * @param string $selector Solo `#id` (caratteri sicuri).
	 * @return string|\WP_Error
	 */
	public static function extract_inner_html_by_css_id( $html, $selector ) {
		$selector = trim( (string) $selector );
		if ( ! preg_match( '/^#([A-Za-z0-9_-]+)$/', $selector, $m ) ) {
			return new \WP_Error(
				'llm_hs_bad_selector',
				__( "Per l'AJAX usa un selettore del tipo #id (es. #llm-stories-loop-home).", 'llm-con-tabelle' )
			);
		}
		$id = $m[1];

		$prev = libxml_use_internal_errors( true );
		$dom  = new \DOMDocument();
		$wrap = '<!DOCTYPE html><html><head><meta charset="UTF-8"/></head><body><div id="__llm_hs_parse_wrap">' . $html . '</div></body></html>';
		$dom->loadHTML( $wrap, LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		$el = $dom->getElementById( $id );
		if ( ! $el instanceof \DOMElement ) {
			return new \WP_Error(
				'llm_hs_no_node',
				__( "Nel markup della pagina non compare un elemento con quell'ID. Imposta lo stesso ID sul contenitore del loop in Elementor.", 'llm-con-tabelle' )
			);
		}

		$inner = '';
		foreach ( $el->childNodes as $child ) {
			$inner .= $dom->saveHTML( $child );
		}

		return $inner;
	}

	/**
	 * @return array<string, true>
	 */
	public static function get_homepage_query_id_map() {
		$defaults = apply_filters(
			'llm_elementor_homepage_stories_loop_query_ids',
			array( 'Loop-storie-homepage' )
		);
		$map = array();
		foreach ( is_array( $defaults ) ? $defaults : array() as $id ) {
			$id = self::sanitize_query_id( (string) $id );
			if ( $id !== '' ) {
				$map[ $id ] = true;
			}
		}
		return $map;
	}

	/**
	 * @param string $widget_qid Sanificato.
	 */
	public static function widget_matches_query_id( $widget_qid ) {
		return self::id_in_list_ci( $widget_qid, self::get_homepage_query_id_map() );
	}

	/**
	 * @param array<string, true> $list
	 * @param string                $needle
	 */
	private static function id_in_list_ci( $needle, array $list ) {
		foreach ( array_keys( $list ) as $reg ) {
			if ( strcasecmp( $needle, (string) $reg ) === 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $id Query ID.
	 * @return string
	 */
	public static function sanitize_query_id( $id ) {
		$id = trim( (string) $id );
		if ( $id === '' ) {
			return '';
		}
		return (string) preg_replace( '/[^a-zA-Z0-9_-]/', '', $id );
	}

	/**
	 * Elenco ordinato di ID storie LLM per categoria + scope (stessa logica del Loop Elementor).
	 * Filtra per lingua di studio (_llm_target_lang) E lingua nota (_llm_known_lang) dell'utente.
	 * Ospiti: nessun filtro lingua (mostrate tutte).
	 *
	 * @param string   $cat_slug    Slug taxonomy category (vuoto = tutte).
	 * @param string   $scope       smart | active | completed | all (vuoto trattato come smart).
	 * @param int|null $user_id     null = utente corrente.
	 * @param string   $target_lang Forza una lingua target specifica (vuoto = auto da user meta).
	 * @return array<int>
	 */
	public static function get_filtered_story_ids_for_scope( $cat_slug, $scope, $user_id = null, $target_lang = '' ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}
		$uid      = absint( $user_id );
		$cat_slug = sanitize_title( (string) $cat_slug );
		$scope    = sanitize_key( (string) $scope );

		/* ── Lingua di studio (target) ───────────────────────── */
		$lang = sanitize_key( (string) $target_lang );
		if ( $lang === '' && $uid > 0 ) {
			$lang = sanitize_key( (string) get_user_meta( $uid, LLM_User_Meta::LEARNING_LANG, true ) );
		}
		$lang = ( class_exists( 'LLM_Languages' ) && LLM_Languages::is_valid( $lang ) ) ? $lang : '';

		/* ── Lingua nota (interfaccia utente) ────────────────── */
		$known_lang = '';
		if ( $uid > 0 ) {
			$known_lang = sanitize_key( (string) get_user_meta( $uid, LLM_User_Meta::INTERFACE_LANG, true ) );
			$known_lang = ( class_exists( 'LLM_Languages' ) && LLM_Languages::is_valid( $known_lang ) ) ? $known_lang : '';
		}

		/* ── Costruzione WP_Query base ──────────────────────── */
		$tax_query = array();
		if ( $cat_slug !== '' && taxonomy_exists( 'category' ) ) {
			$term = get_term_by( 'slug', $cat_slug, 'category' );
			if ( $term && ! is_wp_error( $term ) ) {
				$tax_query[] = array(
					'taxonomy' => 'category',
					'field'    => 'slug',
					'terms'    => array( $cat_slug ),
				);
			}
		}

		$base_q = array(
			'post_type'              => LLM_STORY_CPT,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		if ( ! empty( $tax_query ) ) {
			$base_q['tax_query'] = $tax_query;
		}
		$meta_query = array();
		if ( $lang !== '' ) {
			$meta_query[] = array(
				'key'     => LLM_Story_Meta::TARGET_LANG,
				'value'   => $lang,
				'compare' => '=',
			);
		}
		if ( $known_lang !== '' ) {
			$meta_query[] = array(
				'key'     => LLM_Story_Meta::KNOWN_LANG,
				'value'   => $known_lang,
				'compare' => '=',
			);
		}
		if ( ! empty( $meta_query ) ) {
			$meta_query['relation'] = 'AND';
			$base_q['meta_query']   = $meta_query;
		}

		$q   = new WP_Query( $base_q );
		$ids = is_array( $q->posts ) ? array_map( 'absint', $q->posts ) : array();
		$ids = array_values( array_filter( array_unique( $ids ) ) );

		if ( empty( $ids ) ) {
			return array();
		}

		$buckets = array();
		foreach ( $ids as $sid ) {
			$buckets[ $sid ] = self::story_progress_bucket( $uid, $sid );
		}

		if ( 'active' === $scope ) {
			$ids = array_values( array_filter( $ids, fn( $sid ) => 'in_progress' === $buckets[ $sid ] ) );
		} elseif ( 'completed' === $scope ) {
			$ids = array_values( array_filter( $ids, fn( $sid ) => 'completed' === $buckets[ $sid ] ) );
		} elseif ( 'all' === $scope ) {
			usort(
				$ids,
				function ( $a, $b ) {
					$ta = strtotime( (string) get_post_field( 'post_date', $a ) );
					$tb = strtotime( (string) get_post_field( 'post_date', $b ) );
					return $tb <=> $ta;
				}
			);
		} else {
			if ( $uid ) {
				$in_p = array_values( array_filter( $ids, fn( $sid ) => 'in_progress' === $buckets[ $sid ] ) );
				$done = array_values( array_filter( $ids, fn( $sid ) => 'completed' === $buckets[ $sid ] ) );
				$new  = array_values( array_filter( $ids, fn( $sid ) => 'new' === $buckets[ $sid ] ) );
				$sort_by_date = function ( $a, $b ) {
					$ta = strtotime( (string) get_post_field( 'post_date', $a ) );
					$tb = strtotime( (string) get_post_field( 'post_date', $b ) );
					return $tb <=> $ta;
				};
				usort( $in_p, $sort_by_date );
				usort( $done, $sort_by_date );
				usort( $new, $sort_by_date );
				$ids = array_merge( $in_p, $done, $new );
			} else {
				usort(
					$ids,
					function ( $a, $b ) {
						$ta = strtotime( (string) get_post_field( 'post_date', $a ) );
						$tb = strtotime( (string) get_post_field( 'post_date', $b ) );
						return $tb <=> $ta;
					}
				);
			}
		}

		return array_values( array_filter( array_map( 'absint', $ids ) ) );
	}

	/**
	 * @param int $user_id 0 = ospite.
	 * @param int $story_id
	 * @return string completed|in_progress|new
	 */
	public static function story_progress_bucket( $user_id, $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return 'new';
		}
		$total = count( LLM_Story_Repository::get_phrases( $story_id ) );
		if ( $total < 1 ) {
			return 'new';
		}
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 'new';
		}
		$completed = LLM_User_Stats::get_completed_stories_map( $user_id );
		if ( isset( $completed[ (string) $story_id ] ) ) {
			return 'completed';
		}
		$map  = LLM_User_Stats::get_phrase_map( $user_id );
		$done = isset( $map[ (string) $story_id ] ) && is_array( $map[ (string) $story_id ] )
			? count( $map[ (string) $story_id ] )
			: 0;
		if ( $done >= $total ) {
			return 'completed';
		}
		if ( $done > 0 ) {
			return 'in_progress';
		}
		return 'new';
	}

	/**
	 * @param array                        $query_args
	 * @param \Elementor\Widget_Base|null $widget
	 * @return array
	 */
	public static function filter_query_args( $query_args, $widget ) {
		if ( ! $widget instanceof \Elementor\Widget_Base || ! method_exists( $widget, 'get_query_name' ) ) {
			return $query_args;
		}
		$prefix    = $widget->get_query_name() . '_';
		$settings  = $widget->get_settings_for_display();
		$post_type = isset( $settings[ $prefix . 'post_type' ] ) ? $settings[ $prefix . 'post_type' ] : '';
		if ( LLM_STORY_CPT !== $post_type ) {
			return $query_args;
		}
		$raw_qid    = isset( $settings[ $prefix . 'query_id' ] ) ? trim( (string) $settings[ $prefix . 'query_id' ] ) : '';
		$widget_qid = self::sanitize_query_id( $raw_qid );
		if ( $widget_qid === '' || ! self::widget_matches_query_id( $widget_qid ) ) {
			return $query_args;
		}

		$cat_slug = self::get_effective_filter_cat();
		$scope    = self::get_effective_filter_scope();

		$ids = self::get_filtered_story_ids_for_scope( $cat_slug, $scope, get_current_user_id() );

		if ( empty( $ids ) ) {
			$query_args['post__in']            = array( 0 );
			$query_args['post_status']        = 'publish';
			$query_args['ignore_sticky_posts'] = true;
			return $query_args;
		}

		$query_args['post__in']             = $ids;
		$query_args['orderby']             = 'post__in';
		$query_args['order']               = 'ASC';
		$query_args['post_status']         = 'publish';
		$query_args['ignore_sticky_posts']  = true;
		$query_args['posts_per_page']      = isset( $query_args['posts_per_page'] ) ? (int) $query_args['posts_per_page'] : get_option( 'posts_per_page', 10 );

		unset( $query_args['tax_query'], $query_args['category_name'], $query_args['cat'] );

		return $query_args;
	}
}
