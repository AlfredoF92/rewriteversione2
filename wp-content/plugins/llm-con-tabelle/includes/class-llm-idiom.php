<?php
/**
 * Modello espressioni: CPT banca modi di dire per coppia linguistica.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Idiom {

	const CPT = 'llm_idiom';

	const META_KNOWN = '_llm_idiom_known_lang';
	const META_TARGET = '_llm_idiom_target_lang';
	const META_ITEMS = '_llm_idiom_items';

	/**
	 * @return string[]
	 */
	public static function default_categories() {
		return array(
			'🍕 Espressioni legate al cibo',
			'🐺 Espressioni legate agli animali',
			'🌍 Vita quotidiana e reazioni',
		);
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		$labels = array(
			'name'               => _x( 'Espressioni', 'post type general name', 'llm-con-tabelle' ),
			'singular_name'      => _x( 'Banca espressioni', 'post type singular name', 'llm-con-tabelle' ),
			'menu_name'          => _x( 'Espressioni', 'admin menu', 'llm-con-tabelle' ),
			'add_new'            => _x( 'Aggiungi banca', 'idiom', 'llm-con-tabelle' ),
			'add_new_item'       => __( 'Aggiungi banca espressioni', 'llm-con-tabelle' ),
			'new_item'           => __( 'Nuova banca', 'llm-con-tabelle' ),
			'edit_item'          => __( 'Modifica espressioni', 'llm-con-tabelle' ),
			'view_item'          => __( 'Vedi espressioni', 'llm-con-tabelle' ),
			'all_items'          => __( 'Espressioni', 'llm-con-tabelle' ),
			'search_items'       => __( 'Cerca espressioni', 'llm-con-tabelle' ),
			'not_found'          => __( 'Nessuna banca trovata.', 'llm-con-tabelle' ),
			'not_found_in_trash' => __( 'Nessuna banca nel cestino.', 'llm-con-tabelle' ),
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
	 * @param int $post_id ID banca.
	 * @return string
	 */
	public static function get_known( $post_id ) {
		return sanitize_key( (string) get_post_meta( absint( $post_id ), self::META_KNOWN, true ) );
	}

	/**
	 * @param int $post_id ID banca.
	 * @return string
	 */
	public static function get_target( $post_id ) {
		return sanitize_key( (string) get_post_meta( absint( $post_id ), self::META_TARGET, true ) );
	}

	/**
	 * @param int $post_id ID banca.
	 * @return array<int,array{id:string,category:string,phrase:string,explanation:string}>
	 */
	public static function get_items( $post_id ) {
		return self::normalize_items( get_post_meta( absint( $post_id ), self::META_ITEMS, true ) );
	}

	/**
	 * @param int   $post_id ID banca.
	 * @param array $items   Items.
	 */
	public static function save_items( $post_id, $items ) {
		update_post_meta( absint( $post_id ), self::META_ITEMS, self::normalize_items( $items ) );
	}

	/**
	 * Unisce i campi legacy in un’unica spiegazione.
	 *
	 * @param array $row Riga grezza.
	 * @return string
	 */
	public static function compose_explanation( array $row ) {
		if ( isset( $row['explanation'] ) && '' !== trim( (string) $row['explanation'] ) ) {
			return sanitize_textarea_field( (string) $row['explanation'] );
		}
		$parts = array();
		if ( ! empty( $row['meaning'] ) ) {
			$parts[] = sanitize_textarea_field( (string) $row['meaning'] );
		}
		if ( ! empty( $row['equivalent'] ) ) {
			$parts[] = sanitize_textarea_field( (string) $row['equivalent'] );
		} elseif ( ! empty( $row['example'] ) ) {
			$parts[] = sanitize_textarea_field( (string) $row['example'] );
		}
		return implode( "\n\n", $parts );
	}

	/**
	 * @param mixed $raw Meta grezzo.
	 * @return array<int,array{id:string,category:string,phrase:string,explanation:string}>
	 */
	public static function normalize_items( $raw ) {
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$raw = $decoded;
			}
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$category    = isset( $row['category'] ) ? sanitize_text_field( (string) $row['category'] ) : '';
			$phrase      = isset( $row['phrase'] ) ? sanitize_text_field( (string) $row['phrase'] ) : '';
			$explanation = self::compose_explanation( $row );
			if ( '' === $phrase && '' === $explanation && '' === $category ) {
				continue;
			}
			$id = isset( $row['id'] ) ? sanitize_key( (string) $row['id'] ) : '';
			if ( '' === $id ) {
				$id = self::new_item_id();
			}
			$out[] = array(
				'id'          => $id,
				'category'    => $category,
				'phrase'      => $phrase,
				'explanation' => $explanation,
			);
		}
		return array_values( $out );
	}

	/**
	 * @return string
	 */
	public static function new_item_id() {
		return 'i_' . substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 12 );
	}

	/**
	 * @param array $items Items normalizzati.
	 * @return array<string,array<int,array>>
	 */
	public static function group_by_category( array $items ) {
		$groups = array();
		foreach ( $items as $i => $item ) {
			$cat = isset( $item['category'] ) ? (string) $item['category'] : '';
			if ( '' === $cat ) {
				$cat = __( 'Senza categoria', 'llm-con-tabelle' );
			}
			if ( ! isset( $groups[ $cat ] ) ) {
				$groups[ $cat ] = array();
			}
			$groups[ $cat ][ $i ] = $item;
		}
		uksort(
			$groups,
			static function ( $a, $b ) {
				$empty_a = __( 'Senza categoria', 'llm-con-tabelle' ) === $a;
				$empty_b = __( 'Senza categoria', 'llm-con-tabelle' ) === $b;
				if ( $empty_a !== $empty_b ) {
					return $empty_a ? 1 : -1;
				}
				return strcasecmp( (string) $a, (string) $b );
			}
		);
		return $groups;
	}

	/**
	 * @param string $known  Lingua nota.
	 * @param string $target Lingua obiettivo.
	 * @return int
	 */
	public static function find_for_pair( $known, $target ) {
		$known  = sanitize_key( (string) $known );
		$target = sanitize_key( (string) $target );
		if ( ! LLM_Languages::is_valid( $known ) || ! LLM_Languages::is_valid( $target ) || $known === $target ) {
			return 0;
		}

		$q = new WP_Query(
			array(
				'post_type'              => self::CPT,
				'post_status'            => array( 'publish', 'draft', 'private' ),
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
				),
				'orderby'                => 'date',
				'order'                  => 'DESC',
			)
		);

		return ! empty( $q->posts[0] ) ? (int) $q->posts[0] : 0;
	}

	/**
	 * @param string $known  Lingua nota.
	 * @param string $target Lingua obiettivo.
	 * @param string $item_id ID espressione.
	 * @return array{id:string,category:string,phrase:string,explanation:string}|null
	 */
	public static function find_item_for_pair( $known, $target, $item_id ) {
		$item_id = sanitize_key( (string) $item_id );
		if ( '' === $item_id ) {
			return null;
		}
		$bank = self::find_for_pair( $known, $target );
		if ( ! $bank ) {
			return null;
		}
		foreach ( self::get_items( $bank ) as $item ) {
			if ( $item['id'] === $item_id ) {
				return $item;
			}
		}
		return null;
	}
}
