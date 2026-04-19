<?php
/**
 * Variabili “esterne” della storia per template / Elementor (senza frasi da tabella).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Story_Template_Vars {

	/**
	 * Opzioni per controlli (etichetta => chiave interna).
	 *
	 * @return array<string, string>
	 */
	public static function get_text_field_options() {
		$opts = array(
			__( 'Trama', 'llm-con-tabelle' )                         => 'story_plot',
			__( 'Titolo in lingua obiettivo', 'llm-con-tabelle' )    => 'title_target',
			__( 'Lingua nota — codice', 'llm-con-tabelle' )          => 'known_lang_code',
			__( 'Lingua nota — etichetta', 'llm-con-tabelle' )       => 'known_lang_label',
			__( 'Lingua obiettivo — codice', 'llm-con-tabelle' )     => 'target_lang_code',
			__( 'Lingua obiettivo — etichetta', 'llm-con-tabelle' )  => 'target_lang_label',
			__( 'Costo sblocco (coin)', 'llm-con-tabelle' )          => 'coin_cost',
			__( 'Premio completamento (coin)', 'llm-con-tabelle' )   => 'coin_reward',
			__( 'Titolo (post)', 'llm-con-tabelle' )                => 'post_title',
			__( 'Estratto', 'llm-con-tabelle' )                     => 'post_excerpt',
			__( 'Contenuto (HTML)', 'llm-con-tabelle' )              => 'post_content',
			__( 'Autore — nome visualizzato', 'llm-con-tabelle' )    => 'author_name',
			__( 'Data pubblicazione', 'llm-con-tabelle' )           => 'post_date',
			__( 'Ultima modifica', 'llm-con-tabelle' )              => 'post_modified',
			__( 'ID post', 'llm-con-tabelle' )                      => 'post_id',
			__( 'Categorie (nome | nome | …)', 'llm-con-tabelle' )   => 'category_names_pipe',
			__( 'Categorie (percorso con sottocategorie)', 'llm-con-tabelle' ) => 'category_paths_pipe',
		);

		return apply_filters( 'llm_story_template_text_field_options', $opts );
	}

	/**
	 * Campi URL per widget Link / immagini.
	 *
	 * @return array<string, string>
	 */
	public static function get_url_field_options() {
		$opts = array(
			__( 'Permalink storia', 'llm-con-tabelle' )              => 'permalink',
			__( 'URL immagine in evidenza', 'llm-con-tabelle' )      => 'featured_image_url',
		);

		return apply_filters( 'llm_story_template_url_field_options', $opts );
	}

	/**
	 * @param int|null $post_id ID post o null = post corrente nel loop.
	 * @return WP_Post|null
	 */
	public static function get_story_post( $post_id = null ) {
		$post = $post_id ? get_post( (int) $post_id ) : get_post();
		if ( ! $post instanceof WP_Post || LLM_STORY_CPT !== $post->post_type ) {
			return null;
		}
		return $post;
	}

	/**
	 * Tutte le variabili testuali/numero come array (per debug o export).
	 *
	 * @param int|null $post_id ID post storia.
	 * @return array<string, string>|null
	 */
	public static function get_all_scalar_context( $post_id = null ) {
		$post = self::get_story_post( $post_id );
		if ( ! $post ) {
			return null;
		}
		$id = (int) $post->ID;
		$out = array();
		foreach ( self::get_text_field_options() as $label => $key ) {
			$out[ $key ] = self::get_text_value( $key, $post );
		}
		$out['permalink']           = (string) get_permalink( $post );
		$out['featured_image_url']  = self::get_featured_image_url( $id );

		return apply_filters( 'llm_story_template_context', $out, $post );
	}

	/**
	 * Valore testo per Elemento / shortcode.
	 *
	 * @param string   $field Chiave (es. story_plot).
	 * @param int|null $post_id ID post.
	 * @return string
	 */
	public static function get_text_value( $field, $post_id = null ) {
		$post = self::get_story_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		$field = sanitize_key( (string) $field );
		$id      = (int) $post->ID;

		switch ( $field ) {
			case 'story_plot':
				$v = get_post_meta( $id, LLM_Story_Meta::STORY_PLOT, true );
				return is_string( $v ) ? $v : '';
			case 'title_target':
				$v = get_post_meta( $id, LLM_Story_Meta::TITLE_TARGET, true );
				return is_string( $v ) ? $v : '';
			case 'known_lang_code':
				$v = get_post_meta( $id, LLM_Story_Meta::KNOWN_LANG, true );
				return is_string( $v ) ? $v : '';
			case 'known_lang_label':
				$code = get_post_meta( $id, LLM_Story_Meta::KNOWN_LANG, true );
				return LLM_Languages::label( is_string( $code ) ? $code : '' );
			case 'target_lang_code':
				$v = get_post_meta( $id, LLM_Story_Meta::TARGET_LANG, true );
				return is_string( $v ) ? $v : '';
			case 'target_lang_label':
				$code = get_post_meta( $id, LLM_Story_Meta::TARGET_LANG, true );
				return LLM_Languages::label( is_string( $code ) ? $code : '' );
			case 'coin_cost':
				return (string) (int) get_post_meta( $id, LLM_Story_Meta::COIN_COST, true );
			case 'coin_reward':
				return (string) (int) get_post_meta( $id, LLM_Story_Meta::COIN_REWARD, true );
			case 'post_title':
				return get_the_title( $post );
			case 'post_excerpt':
				return (string) $post->post_excerpt;
			case 'post_content':
				return (string) apply_filters( 'the_content', $post->post_content );
			case 'author_name':
				$u = get_userdata( (int) $post->post_author );
				return $u ? (string) $u->display_name : '';
			case 'post_date':
				return get_the_date( '', $post );
			case 'post_modified':
				return get_the_modified_date( '', $post );
			case 'post_id':
				return (string) $id;
			case 'category_names_pipe':
				return self::get_terms_flat_piped( $id );
			case 'category_paths_pipe':
				return self::get_terms_hierarchical_piped( $id );
			default:
				return (string) apply_filters( 'llm_story_template_custom_text_field', '', $field, $post );
		}
	}

	/**
	 * Tassonomia usata per categorie/sottocategorie (predefinito: category di WordPress).
	 *
	 * @return string
	 */
	public static function get_story_terms_taxonomy() {
		$tax = apply_filters( 'llm_story_terms_taxonomy', 'category' );
		$tax = sanitize_key( (string) $tax );
		return taxonomy_exists( $tax ) ? $tax : '';
	}

	/**
	 * Nomi termine separati da " | " (ordine alfabetico).
	 *
	 * @param int $post_id ID post storia.
	 * @return string
	 */
	public static function get_terms_flat_piped( $post_id ) {
		$taxonomy = self::get_story_terms_taxonomy();
		if ( '' === $taxonomy ) {
			return '';
		}
		$terms = wp_get_post_terms( (int) $post_id, $taxonomy, array( 'orderby' => 'name', 'order' => 'ASC' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}
		$names = array();
		foreach ( $terms as $t ) {
			if ( isset( $t->name ) && $t->name !== '' ) {
				$names[] = $t->name;
			}
		}
		$names = array_unique( $names );
		sort( $names, SORT_NATURAL | SORT_FLAG_CASE );
		return implode( ' | ', $names );
	}

	/**
	 * Per ogni termine assegnato: percorso dalla radice con " > ", più percorsi separati da " | ".
	 *
	 * @param int $post_id ID post storia.
	 * @return string
	 */
	public static function get_terms_hierarchical_piped( $post_id ) {
		$taxonomy = self::get_story_terms_taxonomy();
		if ( '' === $taxonomy ) {
			return '';
		}
		$terms = wp_get_post_terms( (int) $post_id, $taxonomy );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}
		$paths = array();
		foreach ( $terms as $term ) {
			if ( ! isset( $term->term_id ) ) {
				continue;
			}
			$names = array();
			$tid   = (int) $term->term_id;
			$guard = 0;
			while ( $tid > 0 && $guard < 50 ) {
				++$guard;
				$t = get_term( $tid, $taxonomy );
				if ( ! $t || is_wp_error( $t ) ) {
					break;
				}
				array_unshift( $names, $t->name );
				$tid = (int) $t->parent;
			}
			if ( ! empty( $names ) ) {
				$paths[] = implode( ' > ', $names );
			}
		}
		$paths = array_unique( $paths );
		sort( $paths, SORT_NATURAL | SORT_FLAG_CASE );
		return implode( ' | ', $paths );
	}

	/**
	 * @param string   $field permalink | featured_image_url
	 * @param int|null $post_id ID post.
	 * @return string
	 */
	public static function get_url_value( $field, $post_id = null ) {
		$post = self::get_story_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		$field = sanitize_key( (string) $field );
		$id      = (int) $post->ID;

		if ( 'permalink' === $field ) {
			return (string) get_permalink( $post );
		}
		if ( 'featured_image_url' === $field ) {
			return self::get_featured_image_url( $id );
		}

		return (string) apply_filters( 'llm_story_template_custom_url_field', '', $field, $post );
	}

	/**
	 * @param int $post_id ID post.
	 * @return string
	 */
	public static function get_featured_image_url( $post_id ) {
		$url = get_the_post_thumbnail_url( $post_id, 'full' );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Shortcode: [llm_story_field field="story_plot"]
	 *
	 * @param array<string, string> $atts Attributi.
	 * @return string
	 */
	public static function shortcode_field( $atts ) {
		$atts = shortcode_atts(
			array(
				'field'   => '',
				'post_id' => '',
			),
			$atts,
			'llm_story_field'
		);
		$field = sanitize_key( $atts['field'] );
		if ( '' === $field ) {
			return '';
		}
		$pid = $atts['post_id'] !== '' ? absint( $atts['post_id'] ) : null;

		$text_keys = array_values( self::get_text_field_options() );
		if ( in_array( $field, $text_keys, true ) ) {
			$val = self::get_text_value( $field, $pid );
			if ( 'post_content' === $field ) {
				return $val;
			}
			if ( 'story_plot' === $field ) {
				return nl2br( esc_html( $val ) );
			}
			return esc_html( $val );
		}

		$url_keys = array_values( self::get_url_field_options() );
		if ( in_array( $field, $url_keys, true ) ) {
			return esc_url( self::get_url_value( $field, $pid ) );
		}

		return '';
	}
}
