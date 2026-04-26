<?php
/**
 * Loop Grid Elementor: Query ID "continua-le-storie" (e alias) —
 * storie personali dell'utente ordinate per data modifica:
 *   1. In corso  (ordine data modifica DESC)
 *   2. Completate (ordine data modifica DESC)
 *
 * Filtra per lingua di studio (LLM_User_Meta::LEARNING_LANG).
 * Per ospiti non loggati restituisce 0 post.
 *
 * GET: llm_cs_scope = '' | active | completed
 *
 * Query ID predefiniti: continua-le-storie, continuaLeStorie, continua_le_storie
 * (estendibili con filtro llm_continua_storie_loop_query_ids).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_Continua_Storie_Loop
 */
class LLM_Continua_Storie_Loop {

	const GET_SCOPE = 'llm_cs_scope';

	/**
	 * Avvio.
	 */
	public static function init() {
		if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			return;
		}
		add_filter( 'elementor/query/query_args', array( __CLASS__, 'filter_query_args' ), 23, 2 );
	}

	/* ── Query ID ───────────────────────────────────────────── */

	/**
	 * @return array<string, true>
	 */
	private static function get_query_id_map() {
		$defaults = apply_filters(
			'llm_continua_storie_loop_query_ids',
			array( 'continua-le-storie', 'continuaLeStorie', 'continua_le_storie' )
		);
		$map = array();
		foreach ( is_array( $defaults ) ? $defaults : array() as $id ) {
			$clean = preg_replace( '/[^a-zA-Z0-9_-]/', '', trim( (string) $id ) );
			if ( $clean !== '' ) {
				$map[ $clean ] = true;
			}
		}
		return $map;
	}

	/**
	 * @param string $qid
	 */
	private static function matches( $qid ) {
		foreach ( array_keys( self::get_query_id_map() ) as $k ) {
			if ( strcasecmp( $qid, $k ) === 0 ) {
				return true;
			}
		}
		return false;
	}

	/* ── Filtro Elementor ───────────────────────────────────── */

	/**
	 * @param array                        $query_args
	 * @param \Elementor\Widget_Base|null $widget
	 * @return array
	 */
	public static function filter_query_args( $query_args, $widget ) {
		if ( ! $widget instanceof \Elementor\Widget_Base || ! method_exists( $widget, 'get_query_name' ) ) {
			return $query_args;
		}
		$prefix   = $widget->get_query_name() . '_';
		$settings = $widget->get_settings_for_display();
		if ( LLM_STORY_CPT !== ( isset( $settings[ $prefix . 'post_type' ] ) ? $settings[ $prefix . 'post_type' ] : '' ) ) {
			return $query_args;
		}
		$raw_qid = isset( $settings[ $prefix . 'query_id' ] ) ? trim( (string) $settings[ $prefix . 'query_id' ] ) : '';
		$clean   = preg_replace( '/[^a-zA-Z0-9_-]/', '', $raw_qid );
		if ( $clean === '' || ! self::matches( $clean ) ) {
			return $query_args;
		}

		$scope = isset( $_GET[ self::GET_SCOPE ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::GET_SCOPE ] ) ) : '';
		$ids   = self::get_continua_story_ids( get_current_user_id(), $scope );

		if ( empty( $ids ) ) {
			$query_args['post__in']           = array( 0 );
			$query_args['post_status']        = 'publish';
			$query_args['ignore_sticky_posts'] = true;
			return $query_args;
		}

		$query_args['post__in']           = $ids;
		$query_args['orderby']            = 'post__in';
		$query_args['order']              = 'ASC';
		$query_args['post_status']        = 'publish';
		$query_args['ignore_sticky_posts'] = true;
		$query_args['posts_per_page']     = isset( $query_args['posts_per_page'] ) ? (int) $query_args['posts_per_page'] : (int) get_option( 'posts_per_page', 10 );
		unset( $query_args['tax_query'], $query_args['meta_query'], $query_args['category_name'], $query_args['cat'] );

		return $query_args;
	}

	/* ── Pool personale ─────────────────────────────────────── */

	/**
	 * @param int    $user_id
	 * @param string $scope   '' | active | completed
	 * @return array<int>
	 */
	public static function get_continua_story_ids( $user_id, $scope = '' ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}

		/* Lingua di studio (target) */
		$lang = sanitize_key( (string) get_user_meta( $user_id, LLM_User_Meta::LEARNING_LANG, true ) );
		if ( class_exists( 'LLM_Languages' ) && ! LLM_Languages::is_valid( $lang ) ) {
			$lang = '';
		}

		/* Lingua nota (interfaccia utente) */
		$known_lang = sanitize_key( (string) get_user_meta( $user_id, LLM_User_Meta::INTERFACE_LANG, true ) );
		if ( class_exists( 'LLM_Languages' ) && ! LLM_Languages::is_valid( $known_lang ) ) {
			$known_lang = '';
		}

		$completed_map = LLM_User_Stats::get_completed_stories_map( $user_id );
		$completed_ids = array_map( 'absint', array_keys( $completed_map ) );

		$phrase_map    = LLM_User_Stats::get_phrase_map( $user_id );
		$in_prog_ids   = array_values(
			array_diff(
				array_map( 'absint', array_keys( $phrase_map ) ),
				$completed_ids
			)
		);

		/* Scope */
		$scope = sanitize_key( (string) $scope );
		if ( 'active' === $scope ) {
			$pool = $in_prog_ids;
		} elseif ( 'completed' === $scope ) {
			$pool = $completed_ids;
		} else {
			$pool = array_unique( array_merge( $in_prog_ids, $completed_ids ) );
		}
		$pool = array_values( array_filter( array_map( 'absint', $pool ) ) );
		if ( empty( $pool ) ) {
			return array();
		}

		/* Filtro WP: solo published + lingua */
		$base_q = array(
			'post_type'              => LLM_STORY_CPT,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'post__in'               => $pool,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
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
		$pub = is_array( $q->posts ) ? array_map( 'absint', $q->posts ) : array();
		if ( empty( $pub ) ) {
			return array();
		}
		$pub_set = array_flip( $pub );

		/* Ordine: in_progress per modified DESC → completed per modified DESC */
		$sort_by_modified_desc = function ( array $ids ) {
			usort( $ids, function ( $a, $b ) {
				$ta = strtotime( (string) get_post_field( 'post_modified', $a ) );
				$tb = strtotime( (string) get_post_field( 'post_modified', $b ) );
				return $tb <=> $ta;
			} );
			return $ids;
		};

		if ( 'active' === $scope ) {
			$valid = array_values( array_intersect( $in_prog_ids, array_keys( $pub_set ) ) );
			return $sort_by_modified_desc( $valid );
		}
		if ( 'completed' === $scope ) {
			$valid = array_values( array_intersect( $completed_ids, array_keys( $pub_set ) ) );
			return $sort_by_modified_desc( $valid );
		}

		$valid_in_prog  = $sort_by_modified_desc( array_values( array_intersect( $in_prog_ids, array_keys( $pub_set ) ) ) );
		$valid_completed = $sort_by_modified_desc( array_values( array_intersect( $completed_ids, array_keys( $pub_set ) ) ) );

		return array_values( array_unique( array_merge( $valid_in_prog, $valid_completed ) ) );
	}
}
