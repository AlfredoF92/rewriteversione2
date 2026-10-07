<?php
/**
 * 404 e contenuti non apribili → home.
 * Gli admin restano in grado di aprire storie programmate/bozza.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Not_Found_Home_Redirect {

	public static function init() {
		add_filter( 'pre_handle_404', array( __CLASS__, 'admin_keep_unpublished_story' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 1 );
		add_filter( 'the_posts', array( __CLASS__, 'admin_inject_unpublished_story' ), 10, 2 );
	}

	/**
	 * Se la query principale è vuota ma esiste p=ID storia, gli admin la vedono comunque.
	 *
	 * @param WP_Post[] $posts  Post trovati.
	 * @param WP_Query  $query  Query.
	 * @return WP_Post[]
	 */
	public static function admin_inject_unpublished_story( $posts, $query ) {
		if ( ! ( $query instanceof WP_Query ) || ! $query->is_main_query() || is_admin() ) {
			return $posts;
		}
		if ( ! empty( $posts ) ) {
			return $posts;
		}
		if ( ! class_exists( 'LLM_Story_Phrase_Game' ) || ! LLM_Story_Phrase_Game::current_user_can_edit_notes( 0 ) ) {
			return $posts;
		}

		$p = absint( $query->get( 'p' ) );
		if ( ! $p ) {
			$name = (string) $query->get( 'name' );
			$pt   = $query->get( 'post_type' );
			if ( '' === $name || ( LLM_STORY_CPT !== $pt && ! ( is_array( $pt ) && in_array( LLM_STORY_CPT, $pt, true ) ) ) ) {
				return $posts;
			}
			$found = get_page_by_path( $name, OBJECT, LLM_STORY_CPT );
			if ( ! $found instanceof WP_Post ) {
				return $posts;
			}
			$p = (int) $found->ID;
		}

		$post = get_post( $p );
		if ( ! $post || LLM_STORY_CPT !== $post->post_type || 'trash' === $post->post_status ) {
			return $posts;
		}

		$query->is_404      = false;
		$query->is_single   = true;
		$query->is_singular = true;
		$query->found_posts = 1;
		$query->post_count  = 1;
		$query->posts       = array( $post );
		$query->post        = $post;

		return array( $post );
	}

	/**
	 * Evita il 404 WP sulle storie non pubblicate se l’utente è admin.
	 *
	 * @param bool|mixed $preempt  Se true, WP non gestisce il 404.
	 * @param WP_Query   $wp_query Query principale.
	 * @return bool|mixed
	 */
	public static function admin_keep_unpublished_story( $preempt, $wp_query ) {
		if ( true === $preempt || ! ( $wp_query instanceof WP_Query ) ) {
			return $preempt;
		}
		if ( ! class_exists( 'LLM_Story_Phrase_Game' ) || ! LLM_Story_Phrase_Game::current_user_can_edit_notes( 0 ) ) {
			return $preempt;
		}

		$post = null;
		if ( ! empty( $wp_query->posts[0] ) && $wp_query->posts[0] instanceof WP_Post ) {
			$post = $wp_query->posts[0];
		} else {
			$p = absint( $wp_query->get( 'p' ) );
			if ( $p ) {
				$post = get_post( $p );
			}
		}

		if ( ! $post || LLM_STORY_CPT !== $post->post_type || 'trash' === $post->post_status ) {
			return $preempt;
		}

		return true;
	}

	/**
	 * Redirect a home per 404 e storie non visibili al visitatore.
	 */
	public static function maybe_redirect() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return;
		}
		if ( is_customize_preview() || is_preview() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['elementor-preview'] ) || ! empty( $_GET['elementor_library'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['action'] ) && 'elementor' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
			return;
		}

		if ( is_singular( LLM_STORY_CPT ) ) {
			$post = get_queried_object();
			if (
				$post instanceof WP_Post
				&& class_exists( 'LLM_Story_Phrase_Game' )
				&& ! LLM_Story_Phrase_Game::user_can_view_story_front( $post )
			) {
				self::go_home();
			}
			return;
		}

		if ( is_404() ) {
			self::go_home();
		}
	}

	/**
	 * Redirect 302 alla home (niente loop se siamo già lì).
	 */
	private static function go_home() {
		if ( is_front_page() || is_home() ) {
			return;
		}

		$home = home_url( '/' );
		if ( self::is_current_url( $home ) ) {
			return;
		}

		wp_safe_redirect( $home, 302 );
		exit;
	}

	/**
	 * @param string $url URL assoluto.
	 * @return bool
	 */
	private static function is_current_url( $url ) {
		$url = (string) $url;
		if ( '' === $url ) {
			return false;
		}

		$current = ( is_ssl() ? 'https://' : 'http://' );
		if ( isset( $_SERVER['HTTP_HOST'] ) ) {
			$current .= wp_unslash( $_SERVER['HTTP_HOST'] );
		}
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$current .= wp_unslash( $_SERVER['REQUEST_URI'] );
		}

		return untrailingslashit( strtolower( $url ) ) === untrailingslashit( strtolower( strtok( $current, '?' ) ) );
	}
}
