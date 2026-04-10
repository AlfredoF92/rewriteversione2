<?php
/**
 * Tassonomie: categoria (gerarchica) e tag.
 *
 * @package LLS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLS_Taxonomies {

	const CATEGORY = 'lls_story_category';
	const TAG      = 'lls_story_tag';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		$labels_cat = array(
			'name'              => _x( 'Categorie storia', 'taxonomy general name', 'language-learning-stories' ),
			'singular_name'     => _x( 'Categoria storia', 'taxonomy singular name', 'language-learning-stories' ),
			'search_items'      => __( 'Cerca categorie', 'language-learning-stories' ),
			'all_items'         => __( 'Tutte le categorie', 'language-learning-stories' ),
			'parent_item'       => __( 'Categoria genitore', 'language-learning-stories' ),
			'parent_item_colon' => __( 'Genitore:', 'language-learning-stories' ),
			'edit_item'         => __( 'Modifica categoria', 'language-learning-stories' ),
			'update_item'       => __( 'Aggiorna categoria', 'language-learning-stories' ),
			'add_new_item'      => __( 'Aggiungi categoria', 'language-learning-stories' ),
			'new_item_name'     => __( 'Nome nuova categoria', 'language-learning-stories' ),
			'menu_name'         => __( 'Categorie', 'language-learning-stories' ),
		);

		register_taxonomy(
			self::CATEGORY,
			array( LLS_CPT ),
			array(
				'hierarchical'      => true,
				'labels'            => $labels_cat,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => array( 'slug' => 'categoria-storia' ),
				'show_in_rest'      => true,
			)
		);

		$labels_tag = array(
			'name'                       => _x( 'Tag storia', 'taxonomy general name', 'language-learning-stories' ),
			'singular_name'              => _x( 'Tag storia', 'taxonomy singular name', 'language-learning-stories' ),
			'search_items'               => __( 'Cerca tag', 'language-learning-stories' ),
			'popular_items'              => __( 'Tag frequenti', 'language-learning-stories' ),
			'all_items'                  => __( 'Tutti i tag', 'language-learning-stories' ),
			'edit_item'                  => __( 'Modifica tag', 'language-learning-stories' ),
			'update_item'                => __( 'Aggiorna tag', 'language-learning-stories' ),
			'add_new_item'               => __( 'Aggiungi tag', 'language-learning-stories' ),
			'new_item_name'              => __( 'Nome nuovo tag', 'language-learning-stories' ),
			'separate_items_with_commas' => __( 'Separa i tag con la virgola', 'language-learning-stories' ),
			'add_or_remove_items'        => __( 'Aggiungi o rimuovi tag', 'language-learning-stories' ),
			'choose_from_most_used'      => __( 'Scegli tra i più usati', 'language-learning-stories' ),
			'menu_name'                  => __( 'Tag', 'language-learning-stories' ),
		);

		register_taxonomy(
			self::TAG,
			array( LLS_CPT ),
			array(
				'hierarchical'      => false,
				'labels'            => $labels_tag,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => array( 'slug' => 'tag-storia' ),
				'show_in_rest'      => true,
			)
		);
	}
}
