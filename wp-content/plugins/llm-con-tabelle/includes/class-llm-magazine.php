<?php
/**
 * Modello rivista: CPT, coppie linguistiche, meta rubriche.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Magazine {

	const CPT = 'llm_magazine';

	const META_KNOWN      = '_llm_magazine_known_lang';
	const META_TARGET     = '_llm_magazine_target_lang';
	const META_DATE       = '_llm_magazine_date';
	const META_HOMEPAGE   = '_llm_magazine_homepage';
	const META_CROSSWORD  = '_llm_magazine_crossword_id';
	const META_STORY_IDS  = '_llm_magazine_story_ids';
	const META_MUSIC_IDS  = '_llm_magazine_music_ids';

	/** Slug categoria WordPress per i brani (oltre alla categoria di coppia). */
	const MUSIC_CATEGORY_SLUG = 'brani-musicali';

	/** Slug inglese usato negli shortcode (en → english). */
	const SLUG_NAMES = array(
		'en' => 'english',
		'it' => 'italian',
		'pl' => 'polish',
		'es' => 'spanish',
	);

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		$labels = array(
			'name'               => _x( 'Riviste', 'post type general name', 'llm-con-tabelle' ),
			'singular_name'      => _x( 'Rivista', 'post type singular name', 'llm-con-tabelle' ),
			'menu_name'          => _x( 'Riviste', 'admin menu', 'llm-con-tabelle' ),
			'add_new'            => _x( 'Aggiungi nuova', 'rivista', 'llm-con-tabelle' ),
			'add_new_item'       => __( 'Aggiungi nuova rivista', 'llm-con-tabelle' ),
			'new_item'           => __( 'Nuova rivista', 'llm-con-tabelle' ),
			'edit_item'          => __( 'Modifica rivista', 'llm-con-tabelle' ),
			'view_item'          => __( 'Vedi rivista', 'llm-con-tabelle' ),
			'all_items'          => __( 'Riviste', 'llm-con-tabelle' ),
			'search_items'       => __( 'Cerca riviste', 'llm-con-tabelle' ),
			'not_found'          => __( 'Nessuna rivista trovata.', 'llm-con-tabelle' ),
			'not_found_in_trash' => __( 'Nessuna rivista nel cestino.', 'llm-con-tabelle' ),
		);

		register_post_type(
			self::CPT,
			array(
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => 'edit.php?post_type=' . LLM_STORY_CPT,
				'query_var'          => false,
				'rewrite'            => false,
				'capability_type'    => 'post',
				'has_archive'        => false,
				'hierarchical'       => false,
				'supports'           => array( 'title' ),
				'show_in_rest'       => false,
			)
		);
	}

	/**
	 * @param string $code Codice lingua (en|it|…).
	 * @return string
	 */
	public static function lang_slug( $code ) {
		$code = sanitize_key( (string) $code );
		return isset( self::SLUG_NAMES[ $code ] ) ? self::SLUG_NAMES[ $code ] : $code;
	}

	/**
	 * @param string $slug english|italian|…
	 * @return string Codice lingua oppure ''.
	 */
	public static function slug_to_code( $slug ) {
		$slug  = strtolower( sanitize_title( (string) $slug ) );
		$flipped = array_flip( self::SLUG_NAMES );
		return isset( $flipped[ $slug ] ) ? $flipped[ $slug ] : '';
	}

	/**
	 * Chiave interna coppia: en-it.
	 *
	 * @param string $known  Lingua nota.
	 * @param string $target Lingua di studio.
	 * @return string
	 */
	public static function pair_key( $known, $target ) {
		return sanitize_key( $known ) . '-' . sanitize_key( $target );
	}

	/**
	 * Nome shortcode: rivista-primapagina-english-italian.
	 *
	 * @param string $known  Lingua nota.
	 * @param string $target Lingua di studio.
	 * @return string
	 */
	public static function homepage_shortcode_name( $known, $target ) {
		return 'rivista-primapagina-' . self::lang_slug( $known ) . '-' . self::lang_slug( $target );
	}

	/**
	 * Tutte le coppie possibili (nota ≠ obiettivo).
	 *
	 * @return array<int,array{known:string,target:string,key:string,label:string,shortcode:string}>
	 */
	public static function all_pairs() {
		$codes = array_keys( LLM_Languages::get_codes() );
		$out   = array();
		foreach ( $codes as $known ) {
			foreach ( $codes as $target ) {
				if ( $known === $target ) {
					continue;
				}
				$out[] = array(
					'known'     => $known,
					'target'    => $target,
					'key'       => self::pair_key( $known, $target ),
					'label'     => LLM_Languages::label( $known ) . ' → ' . LLM_Languages::label( $target ),
					'shortcode' => self::homepage_shortcode_name( $known, $target ),
				);
			}
		}
		return $out;
	}

	/**
	 * @param int $post_id ID rivista.
	 * @return string
	 */
	public static function get_known( $post_id ) {
		return sanitize_key( (string) get_post_meta( absint( $post_id ), self::META_KNOWN, true ) );
	}

	/**
	 * @param int $post_id ID rivista.
	 * @return string
	 */
	public static function get_target( $post_id ) {
		return sanitize_key( (string) get_post_meta( absint( $post_id ), self::META_TARGET, true ) );
	}

	/**
	 * Data rivista Y-m-d; fallback alla data del post.
	 *
	 * @param int $post_id ID rivista.
	 * @return string
	 */
	public static function get_date( $post_id ) {
		$post_id = absint( $post_id );
		$date    = (string) get_post_meta( $post_id, self::META_DATE, true );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return $date;
		}
		$post = get_post( $post_id );
		if ( $post ) {
			return mysql2date( 'Y-m-d', $post->post_date, false );
		}
		return gmdate( 'Y-m-d' );
	}

	/**
	 * @param int $post_id ID rivista.
	 * @return bool
	 */
	public static function is_homepage( $post_id ) {
		return '1' === (string) get_post_meta( absint( $post_id ), self::META_HOMEPAGE, true );
	}

	/**
	 * @param int $post_id ID rivista.
	 * @return int
	 */
	public static function get_crossword_id( $post_id ) {
		return absint( get_post_meta( absint( $post_id ), self::META_CROSSWORD, true ) );
	}

	/**
	 * @param int $post_id ID rivista.
	 * @return int[]
	 */
	public static function get_story_ids( $post_id ) {
		return self::normalize_id_list( get_post_meta( absint( $post_id ), self::META_STORY_IDS, true ) );
	}

	/**
	 * ID storie/brani musicali selezionati nella rivista.
	 *
	 * @param int $post_id ID rivista.
	 * @return int[]
	 */
	public static function get_music_ids( $post_id ) {
		return self::normalize_id_list( get_post_meta( absint( $post_id ), self::META_MUSIC_IDS, true ) );
	}

	/**
	 * @param mixed $raw Meta grezzo.
	 * @return int[]
	 */
	private static function normalize_id_list( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$ids = array();
		foreach ( $raw as $id ) {
			$id = absint( $id );
			if ( $id ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Termine categoria «Brani musicali» (se esiste).
	 *
	 * @return WP_Term|null
	 */
	public static function music_category() {
		if ( ! taxonomy_exists( 'category' ) ) {
			return null;
		}
		$slugs = array( self::MUSIC_CATEGORY_SLUG, 'brani_musicali' );
		/**
		 * Filtra gli slug candidati della categoria brani musicali.
		 *
		 * @param string[] $slugs Slug.
		 */
		$slugs = apply_filters( 'llm_magazine_music_category_slugs', $slugs );
		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', sanitize_title( (string) $slug ), 'category' );
			if ( $term && ! is_wp_error( $term ) ) {
				return $term;
			}
		}
		return null;
	}

	/**
	 * @return int 0 se assente.
	 */
	public static function music_category_term_id() {
		$term = self::music_category();
		return $term ? (int) $term->term_id : 0;
	}

	/**
	 * Storia con categoria Brani musicali (diretta o discendente).
	 *
	 * @param int $story_id ID storia.
	 * @return bool
	 */
	public static function is_music_story( $story_id ) {
		$music_id = self::music_category_term_id();
		if ( ! $music_id ) {
			return false;
		}
		$cats = wp_get_post_terms( absint( $story_id ), 'category', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $cats ) || empty( $cats ) ) {
			return false;
		}
		$cats = array_map( 'intval', $cats );
		if ( in_array( $music_id, $cats, true ) ) {
			return true;
		}
		foreach ( $cats as $cid ) {
			$ancestors = get_ancestors( $cid, 'category' );
			if ( is_array( $ancestors ) && in_array( $music_id, array_map( 'intval', $ancestors ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Rivista di prima pagina per una coppia (pubblicata).
	 *
	 * @param string $known  Lingua nota.
	 * @param string $target Lingua di studio.
	 * @return int ID rivista oppure 0.
	 */
	public static function find_homepage_id( $known, $target ) {
		$known  = sanitize_key( (string) $known );
		$target = sanitize_key( (string) $target );
		if ( ! LLM_Languages::is_valid( $known ) || ! LLM_Languages::is_valid( $target ) || $known === $target ) {
			return 0;
		}

		$q = new WP_Query(
			array(
				'post_type'              => self::CPT,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					'relation' => 'AND',
					array(
						'key'   => self::META_KNOWN,
						'value' => $known,
					),
					array(
						'key'   => self::META_TARGET,
						'value' => $target,
					),
					array(
						'key'   => self::META_HOMEPAGE,
						'value' => '1',
					),
				),
				'orderby'                => 'date',
				'order'                  => 'DESC',
			)
		);

		return ! empty( $q->posts[0] ) ? (int) $q->posts[0] : 0;
	}

	/**
	 * Toglie il flag prima pagina alle altre riviste della stessa coppia.
	 *
	 * @param int    $keep_id ID da lasciare.
	 * @param string $known   Lingua nota.
	 * @param string $target  Lingua di studio.
	 */
	public static function clear_other_homepages( $keep_id, $known, $target ) {
		$keep_id = absint( $keep_id );
		$known   = sanitize_key( (string) $known );
		$target  = sanitize_key( (string) $target );

		$q = new WP_Query(
			array(
				'post_type'              => self::CPT,
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					'relation' => 'AND',
					array(
						'key'   => self::META_KNOWN,
						'value' => $known,
					),
					array(
						'key'   => self::META_TARGET,
						'value' => $target,
					),
					array(
						'key'   => self::META_HOMEPAGE,
						'value' => '1',
					),
				),
			)
		);

		foreach ( $q->posts as $id ) {
			$id = (int) $id;
			if ( $id !== $keep_id ) {
				delete_post_meta( $id, self::META_HOMEPAGE );
			}
		}
	}

	/**
	 * Slug candidati della categoria root dedicata alla coppia.
	 * Es. it→en → it-english; pl→it → pl-wloski.
	 *
	 * @param string $known  Lingua nota.
	 * @param string $target Lingua di studio.
	 * @return string[]
	 */
	public static function pair_category_slugs( $known, $target ) {
		$known  = sanitize_key( (string) $known );
		$target = sanitize_key( (string) $target );
		$slugs  = array(
			$known . '-' . self::lang_slug( $target ),
			$known . '-' . $target,
		);

		/* Eccezioni già presenti sul sito. */
		$special = array(
			'pl-it' => array( 'pl-wloski', 'llm-seed-polish-to-italian' ),
		);
		$key = self::pair_key( $known, $target );
		if ( isset( $special[ $key ] ) ) {
			$slugs = array_merge( $special[ $key ], $slugs );
		}

		$slugs = array_values( array_unique( array_filter( $slugs ) ) );

		/**
		 * Filtra gli slug categoria usati per una coppia linguistica.
		 *
		 * @param string[] $slugs  Candidate slug.
		 * @param string   $known  Lingua nota.
		 * @param string   $target Lingua di studio.
		 */
		return apply_filters( 'llm_magazine_pair_category_slugs', $slugs, $known, $target );
	}

	/**
	 * Categoria root della coppia (prima candidata esistente).
	 *
	 * @param string $known  Lingua nota.
	 * @param string $target Lingua di studio.
	 * @return WP_Term|null
	 */
	public static function pair_root_category( $known, $target ) {
		if ( ! taxonomy_exists( 'category' ) ) {
			return null;
		}
		foreach ( self::pair_category_slugs( $known, $target ) as $slug ) {
			$term = get_term_by( 'slug', $slug, 'category' );
			if ( $term && ! is_wp_error( $term ) ) {
				return $term;
			}
		}
		return null;
	}

	/**
	 * ID della categoria root + tutte le sottocategorie.
	 *
	 * @param string $known  Lingua nota.
	 * @param string $target Lingua di studio.
	 * @return int[]
	 */
	public static function pair_category_term_ids( $known, $target ) {
		$root = self::pair_root_category( $known, $target );
		if ( ! $root ) {
			return array();
		}
		$ids      = array( (int) $root->term_id );
		$children = get_term_children( (int) $root->term_id, 'category' );
		if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
			foreach ( $children as $cid ) {
				$ids[] = (int) $cid;
			}
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Storie della coppia, dalla più recente.
	 * Preferisce la categoria dedicata alla coppia (root + figli);
	 * se la categoria non esiste, fallback sui meta lingua.
	 *
	 * @param string $known  Lingua nota.
	 * @param string $target Lingua di studio.
	 * @return WP_Post[]
	 */
	public static function stories_for_pair( $known, $target ) {
		$known  = sanitize_key( (string) $known );
		$target = sanitize_key( (string) $target );
		if ( ! LLM_Languages::is_valid( $known ) || ! LLM_Languages::is_valid( $target ) ) {
			return array();
		}

		$args = array(
			'post_type'              => LLM_STORY_CPT,
			'post_status'            => 'publish',
			'posts_per_page'         => 200,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		);

		$term_ids = self::pair_category_term_ids( $known, $target );
		if ( ! empty( $term_ids ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy'         => 'category',
					'field'            => 'term_id',
					'terms'            => $term_ids,
					'include_children' => false,
					'operator'         => 'IN',
				),
			);
		} else {
			$args['meta_query'] = array(
				'relation' => 'AND',
				array(
					'key'   => LLM_Story_Meta::KNOWN_LANG,
					'value' => $known,
				),
				array(
					'key'   => LLM_Story_Meta::TARGET_LANG,
					'value' => $target,
				),
			);
		}

		$q = new WP_Query( $args );
		return $q->posts;
	}

	/**
	 * Elenco cruciverba per select admin.
	 *
	 * @return array<int,string> ID → titolo.
	 */
	public static function crossword_choices() {
		$q = new WP_Query(
			array(
				'post_type'              => LLM_Crossword::CPT,
				'post_status'            => array( 'publish', 'draft', 'private' ),
				'posts_per_page'         => 200,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$out = array();
		foreach ( $q->posts as $post ) {
			$out[ (int) $post->ID ] = $post->post_title ? $post->post_title : ( '#' . $post->ID );
		}
		return $out;
	}
}
