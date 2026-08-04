<?php
/**
 * Colonne extra nella lista Articoli (post) in wp-admin: anteprima + riassunto.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Admin_Posts_List {

	public static function init() {
		add_filter( 'manage_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue( $hook ) {
		if ( 'edit.php' !== $hook ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style(
			'llm-admin-posts-list',
			LLM_TABELLE_URL . 'assets/llm-admin.css',
			array(),
			LLM_TABELLE_VERSION
		);
	}

	/**
	 * @param array<string, string> $columns Colonne esistenti.
	 * @return array<string, string>
	 */
	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'cb' === $key ) {
				$new['llm_list_thumb'] = __( 'Anteprima', 'llm-con-tabelle' );
			}
			if ( 'title' === $key ) {
				$new['llm_list_excerpt'] = __( 'Riassunto', 'llm-con-tabelle' );
			}
		}
		return $new;
	}

	/**
	 * @param string $column  Nome colonna.
	 * @param int    $post_id ID articolo.
	 */
	public static function column_content( $column, $post_id ) {
		if ( 'llm_list_thumb' === $column ) {
			self::render_thumbnail( $post_id );
			return;
		}
		if ( 'llm_list_excerpt' === $column ) {
			self::render_excerpt( $post_id, 'post' );
		}
	}

	/**
	 * @param int $post_id ID post.
	 */
	public static function render_thumbnail( $post_id ) {
		$thumb_id  = (int) get_post_thumbnail_id( $post_id );
		$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, array( 60, 60 ) ) : '';
		echo '<div class="llm-col-thumb">';
		if ( $thumb_url ) {
			echo '<img src="' . esc_url( $thumb_url ) . '" alt="" width="60" height="60" />';
		} else {
			echo '<span class="llm-no-thumb">—</span>';
		}
		echo '</div>';
	}

	/**
	 * @param int    $post_id ID post.
	 * @param string $context 'post' | 'story'.
	 */
	public static function render_excerpt( $post_id, $context = 'post' ) {
		$text = self::excerpt_text( $post_id, $context );
		if ( '' === $text ) {
			echo '<span class="llm-list-excerpt llm-list-excerpt--empty">—</span>';
			return;
		}
		echo '<div class="llm-list-excerpt">' . esc_html( $text ) . '</div>';
	}

	/**
	 * Testo riassunto: estratto, scheda storia o anteprima dal contenuto.
	 *
	 * @param int    $post_id ID post.
	 * @param string $context 'post' | 'story'.
	 * @return string
	 */
	public static function excerpt_text( $post_id, $context = 'post' ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return '';
		}

		if ( 'story' === $context ) {
			$card = get_post_meta( $post_id, LLM_Story_Meta::STORY_CARD_TEXT, true );
			if ( is_string( $card ) && '' !== trim( $card ) ) {
				return trim( $card );
			}
		}

		$excerpt = get_post_field( 'post_excerpt', $post_id );
		if ( is_string( $excerpt ) && '' !== trim( $excerpt ) ) {
			return trim( $excerpt );
		}

		if ( 'story' === $context ) {
			foreach ( array( LLM_Story_Meta::STORY_INTRO, LLM_Story_Meta::STORY_PLOT ) as $meta_key ) {
				$value = get_post_meta( $post_id, $meta_key, true );
				if ( is_string( $value ) && '' !== trim( $value ) ) {
					return wp_trim_words( trim( $value ), 24, '…' );
				}
			}
		}

		$content = get_post_field( 'post_content', $post_id );
		if ( is_string( $content ) && '' !== trim( $content ) ) {
			return wp_trim_words( wp_strip_all_tags( $content ), 24, '…' );
		}

		return '';
	}
}
