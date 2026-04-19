<?php
/**
 * Filtro Loop Grid Elementor Pro: storie “personali” utente (tabelle LLM).
 *
 * In Elementor: Loop Grid → Query → Include By → “Storie dell'utente loggato” (solo sblocco tabella),
 * oppure Query ID = continuaStorie / AreaPersonale (sblocco + frasi + completate, vedi shortcode filtri).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_Elementor_Unlocked_Stories_Loop
 */
class LLM_Elementor_Unlocked_Stories_Loop {

	const SHORTCODE = 'llm_unlocked_stories_loop';

	/**
	 * Query ID extra registrati via shortcode / ensure_query_hook.
	 *
	 * @var array<string, true>
	 */
	private static $extra_unlock_query_ids = array();

	/**
	 * Avvio: hook Elementor + shortcode.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );

		if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			return;
		}

		if ( class_exists( 'LLM_Elementor_Group_Control_Related' ) ) {
			add_action( 'elementor/controls/register', array( __CLASS__, 'register_extended_related_query_group' ), 20 );
		}
		add_filter( 'elementor/query/query_args', array( __CLASS__, 'filter_elementor_query_args' ), 20, 2 );
		add_filter( 'elementor/query/query_args', array( __CLASS__, 'filter_elementor_query_args_by_query_id' ), 25, 2 );
	}

	/**
	 * Sostituisce il group control related-query con la versione che aggiunge Include By LLM.
	 *
	 * @param \Elementor\Controls_Manager $controls_manager Manager.
	 */
	public static function register_extended_related_query_group( $controls_manager ) {
		$controls_manager->add_group_control( 'related-query', new LLM_Elementor_Group_Control_Related() );
	}

	/**
	 * Include By: “Storie dell'utente loggato” → solo ID da tabella sblocchi (comportamento stretto).
	 *
	 * @param array                        $query_args Args WP_Query.
	 * @param \Elementor\Widget_Base|null $widget     Widget.
	 * @return array
	 */
	public static function filter_elementor_query_args( $query_args, $widget ) {
		if ( ! $widget instanceof \Elementor\Widget_Base || ! method_exists( $widget, 'get_query_name' ) ) {
			return $query_args;
		}
		$prefix = $widget->get_query_name() . '_';
		$settings = $widget->get_settings_for_display();
		$include = isset( $settings[ $prefix . 'include' ] ) ? $settings[ $prefix . 'include' ] : null;
		if ( ! self::settings_include_has_llm_unlocked( $include ) ) {
			return $query_args;
		}
		$post_type = isset( $settings[ $prefix . 'post_type' ] ) ? $settings[ $prefix . 'post_type' ] : '';
		if ( LLM_STORY_CPT !== $post_type ) {
			return $query_args;
		}

		$ids = self::get_strict_unlocked_published_story_ids_for_current_user();
		if ( isset( $query_args['post__in'] ) && is_array( $query_args['post__in'] ) && array() !== $query_args['post__in'] ) {
			$intersect = array_values( array_intersect( array_map( 'absint', $query_args['post__in'] ), $ids ) );
			$query_args['post__in'] = ! empty( $intersect ) ? $intersect : array( 0 );
		} else {
			$query_args['post__in'] = $ids;
		}

		return $query_args;
	}

	/**
	 * Query ID (continuaStorie, AreaPersonale, …): pool personale ampio + filtri GET per Area Personale.
	 * Eseguito dopo Include By (priorità 25).
	 *
	 * @param array                        $query_args Args.
	 * @param \Elementor\Widget_Base|null $widget     Widget.
	 * @return array
	 */
	public static function filter_elementor_query_args_by_query_id( $query_args, $widget ) {
		if ( ! $widget instanceof \Elementor\Widget_Base || ! method_exists( $widget, 'get_query_name' ) ) {
			return $query_args;
		}
		$prefix = $widget->get_query_name() . '_';
		$settings = $widget->get_settings_for_display();
		$post_type = isset( $settings[ $prefix . 'post_type' ] ) ? $settings[ $prefix . 'post_type' ] : '';
		if ( LLM_STORY_CPT !== $post_type ) {
			return $query_args;
		}
		$raw_qid = isset( $settings[ $prefix . 'query_id' ] ) ? trim( (string) $settings[ $prefix . 'query_id' ] ) : '';
		$widget_qid = self::sanitize_query_id( $raw_qid );
		if ( $widget_qid === '' ) {
			return $query_args;
		}
		if ( ! self::widget_query_id_matches_unlock_list( $widget_qid ) ) {
			return $query_args;
		}

		if ( self::widget_query_id_matches_area_personale_list( $widget_qid ) ) {
			return self::apply_area_personale_to_query_args( $query_args );
		}

		return self::apply_simple_personal_stories_to_query_args( $query_args );
	}

	/**
	 * @param mixed $include Valore controllo Include By (stringa o array per Select2 multiplo).
	 */
	private static function settings_include_has_llm_unlocked( $include ) {
		if ( ! class_exists( 'LLM_Elementor_Group_Control_Related' ) ) {
			return false;
		}
		$needle = LLM_Elementor_Group_Control_Related::INCLUDE_VALUE;
		if ( is_array( $include ) ) {
			return in_array( $needle, $include, true );
		}
		return (string) $include === $needle;
	}

	/**
	 * Solo tabella sblocchi (Include By).
	 *
	 * @return int[] Con 0 se vuoto / ospite.
	 */
	private static function get_strict_unlocked_published_story_ids_for_current_user() {
		if ( ! is_user_logged_in() ) {
			return array( 0 );
		}
		$uid = get_current_user_id();
		$ids = LLM_User_Stats::get_unlocked_story_ids( $uid );
		$published = self::filter_published_story_ids( $ids );
		return ! empty( $published ) ? $published : array( 0 );
	}

	/**
	 * Sbloccate + storie con frasi fatte + completate (Query ID loop).
	 *
	 * @param int $user_id ID utente.
	 * @return int[]
	 */
	private static function get_personal_story_candidate_ids( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}
		$candidates = array_merge(
			LLM_User_Stats::get_unlocked_story_ids( $user_id ),
			array_map( 'absint', array_keys( LLM_User_Stats::get_phrase_map( $user_id ) ) ),
			array_map( 'absint', array_keys( LLM_User_Stats::get_completed_stories_map( $user_id ) ) )
		);
		return array_values( array_unique( array_filter( array_map( 'absint', $candidates ) ) ) );
	}

	/**
	 * @param int $user_id ID utente.
	 * @return int[] Pubblicati llm_story; [0] se nessuno.
	 */
	private static function get_published_personal_story_ids_for_user( $user_id ) {
		$published = self::filter_published_story_ids( self::get_personal_story_candidate_ids( $user_id ) );
		return ! empty( $published ) ? $published : array( 0 );
	}

	/**
	 * @return int[]
	 */
	private static function get_published_personal_story_ids_for_current_user() {
		if ( ! is_user_logged_in() ) {
			return array( 0 );
		}
		return self::get_published_personal_story_ids_for_user( get_current_user_id() );
	}

	/**
	 * @param array<string, true> $list Lista canonica.
	 * @param string              $needle Valore dal widget (sanificato).
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
	 * @return array<string, true>
	 */
	private static function get_unlock_loop_query_id_map() {
		$defaults = apply_filters(
			'llm_elementor_unlocked_stories_query_ids',
			array( 'continuaStorie', 'AreaPersonale' )
		);
		$map = array();
		foreach ( is_array( $defaults ) ? $defaults : array() as $id ) {
			$id = self::sanitize_query_id( (string) $id );
			if ( $id !== '' ) {
				$map[ $id ] = true;
			}
		}
		foreach ( array_keys( self::$extra_unlock_query_ids ) as $id ) {
			$map[ $id ] = true;
		}
		return $map;
	}

	/**
	 * @return array<string, true>
	 */
	private static function get_area_personale_query_id_map() {
		$defaults = apply_filters( 'llm_elementor_area_personale_query_ids', array( 'AreaPersonale' ) );
		$map      = array();
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
	private static function widget_query_id_matches_unlock_list( $widget_qid ) {
		return self::id_in_list_ci( $widget_qid, self::get_unlock_loop_query_id_map() );
	}

	/**
	 * @param string $widget_qid Sanificato.
	 */
	private static function widget_query_id_matches_area_personale_list( $widget_qid ) {
		return self::id_in_list_ci( $widget_qid, self::get_area_personale_query_id_map() );
	}

	/**
	 * continuaStorie e simili: tutte le storie “personali”.
	 *
	 * @param array $query_args Args.
	 * @return array
	 */
	private static function apply_simple_personal_stories_to_query_args( array $query_args ) {
		$ids = self::get_published_personal_story_ids_for_current_user();
		$query_args['post__in']            = $ids;
		$query_args['post_status']        = 'publish';
		$query_args['ignore_sticky_posts'] = true;
		return $query_args;
	}

	/**
	 * Area Personale: pool personale + scope GET + lingua + titolo.
	 *
	 * @param array $query_args Args.
	 * @return array
	 */
	private static function apply_area_personale_to_query_args( array $query_args ) {
		if ( ! is_user_logged_in() ) {
			$query_args['post__in']    = array( 0 );
			$query_args['post_status'] = 'publish';
			return $query_args;
		}

		$uid      = get_current_user_id();
		$personal = self::get_published_personal_story_ids_for_user( $uid );
		if ( array( 0 ) === $personal ) {
			$query_args['post__in']    = array( 0 );
			$query_args['post_status'] = 'publish';
			return $query_args;
		}

		$completed_map = LLM_User_Stats::get_completed_stories_map( $uid );
		$completed_ids = array_values( array_intersect( $personal, array_map( 'absint', array_keys( $completed_map ) ) ) );

		$scope = isset( $_GET['llm_ap_scope'] ) ? sanitize_key( wp_unslash( (string) $_GET['llm_ap_scope'] ) ) : '';
		if ( 'completed' === $scope ) {
			$pool = $completed_ids;
		} elseif ( 'active' === $scope ) {
			$pool = array_values( array_diff( $personal, $completed_ids ) );
		} else {
			$pool = $personal;
		}

		if ( empty( $pool ) ) {
			$query_args['post__in']    = array( 0 );
			$query_args['post_status'] = 'publish';
			return $query_args;
		}

		$query_args['post__in']    = $pool;
		$query_args['post_status'] = 'publish';

		$lang = isset( $_GET['llm_ap_target_lang'] ) ? sanitize_key( wp_unslash( (string) $_GET['llm_ap_target_lang'] ) ) : '';
		if ( $lang !== '' && class_exists( 'LLM_Languages' ) && LLM_Languages::is_valid( $lang ) ) {
			$clause = array(
				'key'   => LLM_Story_Meta::TARGET_LANG,
				'value' => $lang,
			);
			if ( ! empty( $query_args['meta_query'] ) && is_array( $query_args['meta_query'] ) ) {
				$query_args['meta_query'] = array(
					'relation' => 'AND',
					$query_args['meta_query'],
					$clause,
				);
			} else {
				$query_args['meta_query'] = array( $clause );
			}
		}

		$search = isset( $_GET['llm_ap_s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['llm_ap_s'] ) ) : '';
		if ( $search !== '' ) {
			$query_args['s'] = $search;
		}

		return $query_args;
	}

	/**
	 * Sanifica e registra un Query ID aggiuntivo (shortcode).
	 *
	 * @param string $query_id Valore grezzo.
	 * @return string ID effettivo o stringa vuota se non valido.
	 */
	public static function ensure_query_hook( $query_id ) {
		$query_id = self::sanitize_query_id( (string) $query_id );
		if ( $query_id === '' ) {
			return '';
		}
		self::hook_query_id( $query_id );
		return $query_id;
	}

	/**
	 * Aggiunge l’ID alla lista usata da filter_elementor_query_args_by_query_id.
	 *
	 * @param string $query_id Identificativo.
	 */
	public static function hook_query_id( $query_id ) {
		$query_id = self::sanitize_query_id( $query_id );
		if ( $query_id === '' ) {
			return;
		}
		self::$extra_unlock_query_ids[ $query_id ] = true;
	}

	/**
	 * Solo lettere, numeri, trattino, underscore (mantiene il case).
	 *
	 * @param string $id Query ID.
	 * @return string
	 */
	private static function sanitize_query_id( $id ) {
		$id = trim( (string) $id );
		if ( $id === '' ) {
			return '';
		}
		return (string) preg_replace( '/[^a-zA-Z0-9_-]/', '', $id );
	}

	/**
	 * Shortcode: registra un Query ID aggiuntivo.
	 *
	 * @param array<string, string>|string $atts Attributi.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'query_id' => '',
			),
			$atts,
			self::SHORTCODE
		);
		self::ensure_query_hook( (string) $atts['query_id'] );
		return '';
	}

	/**
	 * @param int[] $ids ID post.
	 * @return int[]
	 */
	private static function filter_published_story_ids( array $ids ) {
		$out = array();
		foreach ( array_unique( array_map( 'absint', $ids ) ) as $sid ) {
			if ( ! $sid ) {
				continue;
			}
			if ( LLM_STORY_CPT !== get_post_type( $sid ) ) {
				continue;
			}
			if ( 'publish' !== get_post_status( $sid ) ) {
				continue;
			}
			$out[] = $sid;
		}
		return $out;
	}
}
