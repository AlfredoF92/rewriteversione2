<?php
/**
 * Shortcode [scheda-utente]: profilo pubblico a due colonne.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Scheda_Utente {

	const SHORTCODE = 'scheda-utente';

	const AJAX_STORIES = 'llm_scheda_utente_stories';

	const AJAX_GUEST = 'llm_scheda_utente_guest';

	const AJAX_GUEST_PAIRS = 'llm_scheda_utente_guest_pairs';

	const AJAX_SAVE = 'llm_scheda_utente_save';

	const NONCE_STORIES = 'llm_scheda_utente_stories';

	const NONCE_SAVE = 'llm_scheda_utente_save';

	const OPT_REWRITE = 'llm_scheda_utente_rewrite';

	const OPT_PAGE_ID = 'llm_scheda_utente_page_id';

	const PAGE_SLUG = 'scheda-utente';

	const ACCOUNT_PAGE_SLUG = 'area-personale';

	const GUEST_SLUG = 'browser';

	const QVAR = 'llm_utente';

	public static function init() {
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'init', array( __CLASS__, 'ensure_page' ), 8 );
		add_action( 'init', array( __CLASS__, 'register_rewrite' ), 9 );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite' ), 99 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'skip_profile_canonical' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_author_archive' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_account_aliases' ), 1 );
		add_filter( 'document_title_parts', array( __CLASS__, 'document_title' ) );
		add_filter( 'get_avatar_data', array( __CLASS__, 'filter_avatar_data' ), 10, 2 );
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_shortcode( 'scheda_utente', array( __CLASS__, 'render' ) );
		add_shortcode( 'SCHEDA_UTENTE', array( __CLASS__, 'render' ) );
		add_action( 'wp_ajax_' . self::AJAX_STORIES, array( __CLASS__, 'ajax_stories' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_STORIES, array( __CLASS__, 'ajax_stories' ) );
		add_action( 'wp_ajax_' . self::AJAX_GUEST, array( __CLASS__, 'ajax_guest' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_GUEST, array( __CLASS__, 'ajax_guest' ) );
		add_action( 'wp_ajax_' . self::AJAX_GUEST_PAIRS, array( __CLASS__, 'ajax_guest_pairs' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_GUEST_PAIRS, array( __CLASS__, 'ajax_guest_pairs' ) );
		add_action( 'wp_ajax_' . self::AJAX_SAVE, array( __CLASS__, 'ajax_save' ) );
	}

	/**
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::QVAR;
		return $vars;
	}

	/**
	 * Pagina WordPress (modificabile con Elementor) che contiene lo shortcode.
	 *
	 * @return int
	 */
	public static function ensure_page() {
		$id = (int) get_option( self::OPT_PAGE_ID, 0 );
		if ( $id ) {
			$post = get_post( $id );
			if ( $post && 'page' === $post->post_type && 'trash' !== $post->post_status ) {
				return $id;
			}
		}
		$existing = get_page_by_path( self::PAGE_SLUG );
		if ( $existing instanceof WP_Post ) {
			update_option( self::OPT_PAGE_ID, (int) $existing->ID, false );
			return (int) $existing->ID;
		}
		$new_id = wp_insert_post(
			array(
				'post_title'   => __( 'Scheda utente', 'llm-con-tabelle' ),
				'post_name'    => self::PAGE_SLUG,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '[' . self::SHORTCODE . ']',
			),
			true
		);
		if ( is_wp_error( $new_id ) || ! $new_id ) {
			return 0;
		}
		update_option( self::OPT_PAGE_ID, (int) $new_id, false );
		return (int) $new_id;
	}

	public static function register_rewrite() {
		add_rewrite_rule(
			'^utente/([^/]+)/?$',
			'index.php?pagename=' . self::PAGE_SLUG . '&' . self::QVAR . '=$matches[1]',
			'top'
		);
	}

	public static function maybe_flush_rewrite() {
		$flag = (string) get_option( self::OPT_REWRITE, '' );
		if ( 'utente-page-1' === $flag ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( self::OPT_REWRITE, 'utente-page-1', false );
	}

	/**
	 * @param string|false $redirect  Redirect.
	 * @param string       $requested Requested.
	 * @return string|false
	 */
	public static function skip_profile_canonical( $redirect, $requested ) {
		unset( $requested );
		if ( get_query_var( self::QVAR ) ) {
			return false;
		}
		return $redirect;
	}

	/**
	 * Vecchio archivio autore WP → scheda profilo.
	 */
	public static function redirect_author_archive() {
		if ( is_admin() || ! is_author() ) {
			return;
		}
		if ( get_query_var( self::QVAR ) ) {
			return;
		}
		$user = get_queried_object();
		if ( ! $user instanceof WP_User ) {
			return;
		}
		$url = self::profile_url( (int) $user->ID );
		if ( $url ) {
			wp_safe_redirect( $url, 301 );
			exit;
		}
	}

	/**
	 * @param array<string,string> $parts Title parts.
	 * @return array<string,string>
	 */
	public static function document_title( $parts ) {
		$slug = sanitize_user( (string) get_query_var( self::QVAR ), true );
		if ( '' === $slug ) {
			if ( ! is_user_logged_in() && self::is_scheda_page() ) {
				$parts['title'] = self::t( 'guest_name' );
			}
			return $parts;
		}
		if ( self::is_reserved_guest_slug( $slug ) && ! self::wp_user_exists_for_slug( $slug ) ) {
			$parts['title'] = self::t( 'guest_name' );
			return $parts;
		}
		$u = get_user_by( 'slug', $slug );
		if ( ! $u ) {
			$u = get_user_by( 'login', $slug );
		}
		if ( $u instanceof WP_User ) {
			$name = $u->display_name ? $u->display_name : $u->user_login;
			$parts['title'] = $name;
		}
		return $parts;
	}

	/**
	 * URL pubblico della scheda.
	 *
	 * @param int $user_id ID utente.
	 * @return string
	 */
	public static function profile_url( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return '';
		}
		$slug = $user->user_nicename ? (string) $user->user_nicename : (string) $user->user_login;
		if ( '' === $slug ) {
			return '';
		}
		return home_url( user_trailingslashit( 'utente/' . $slug ) );
	}

	/**
	 * Scheda dell'utente browser (localStorage).
	 *
	 * @return string
	 */
	public static function guest_profile_url() {
		return home_url( user_trailingslashit( 'utente/' . self::GUEST_SLUG ) );
	}

	/**
	 * Area personale: scheda WP se loggato, scheda browser se ospite.
	 *
	 * @return string
	 */
	public static function account_url() {
		if ( is_user_logged_in() ) {
			$url = self::profile_url( get_current_user_id() );
			return $url ? $url : home_url( '/' );
		}
		return self::guest_profile_url();
	}

	/**
	 * /area-personale e /utente/browser/ (se loggato) → scheda corretta.
	 */
	public static function redirect_account_aliases() {
		if ( is_admin() ) {
			return;
		}

		$slug = sanitize_user( (string) get_query_var( self::QVAR ), true );
		if ( is_user_logged_in() && self::is_reserved_guest_slug( $slug ) && ! self::wp_user_exists_for_slug( $slug ) ) {
			$url = self::profile_url( get_current_user_id() );
			if ( $url ) {
				wp_safe_redirect( $url, 302 );
				exit;
			}
		}

		if ( ! is_singular( 'page' ) ) {
			return;
		}
		$page = get_queried_object();
		if ( ! $page instanceof WP_Post || self::ACCOUNT_PAGE_SLUG !== $page->post_name ) {
			return;
		}
		wp_safe_redirect( self::account_url(), 302 );
		exit;
	}

	/**
	 * @param string $slug Slug.
	 * @return bool
	 */
	public static function is_reserved_guest_slug( $slug ) {
		$slug = sanitize_user( (string) $slug, true );
		return in_array( $slug, array( self::GUEST_SLUG, 'ospite', 'guest' ), true );
	}

	/**
	 * @param string $slug Slug.
	 * @return bool
	 */
	private static function wp_user_exists_for_slug( $slug ) {
		$slug = sanitize_user( (string) $slug, true );
		if ( '' === $slug ) {
			return false;
		}
		return (bool) ( get_user_by( 'slug', $slug ) || get_user_by( 'login', $slug ) );
	}

	/**
	 * @return bool
	 */
	private static function is_scheda_page() {
		if ( ! is_singular( 'page' ) ) {
			return false;
		}
		$page = get_queried_object();
		return $page instanceof WP_Post && self::PAGE_SLUG === $page->post_name;
	}

	/**
	 * @param string $atts_user Attributo shortcode.
	 * @return bool
	 */
	private static function wants_guest_profile( $atts_user ) {
		if ( is_user_logged_in() ) {
			return false;
		}
		$slug = sanitize_user( (string) $atts_user, true );
		if ( '' === $slug ) {
			$slug = sanitize_user( (string) get_query_var( self::QVAR ), true );
		}
		if ( self::is_reserved_guest_slug( $slug ) && ! self::wp_user_exists_for_slug( $slug ) ) {
			return true;
		}
		if ( '' === $slug ) {
			return ! is_author() && self::is_scheda_page();
		}
		return false;
	}

	/**
	 * @param array<string,mixed> $args        Avatar args.
	 * @param mixed               $id_or_email ID o email.
	 * @return array<string,mixed>
	 */
	public static function filter_avatar_data( $args, $id_or_email ) {
		$user_id = 0;
		if ( is_numeric( $id_or_email ) ) {
			$user_id = (int) $id_or_email;
		} elseif ( $id_or_email instanceof WP_User ) {
			$user_id = (int) $id_or_email->ID;
		} elseif ( $id_or_email instanceof WP_Post && ! empty( $id_or_email->post_author ) ) {
			$user_id = (int) $id_or_email->post_author;
		} elseif ( is_object( $id_or_email ) && ! empty( $id_or_email->user_id ) ) {
			$user_id = (int) $id_or_email->user_id;
		} elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
			$u = get_user_by( 'email', $id_or_email );
			$user_id = $u ? (int) $u->ID : 0;
		}
		if ( ! $user_id ) {
			return $args;
		}
		$url = self::photo_url( $user_id, 'medium' );
		if ( $url ) {
			$args['url'] = $url;
			$args['found_avatar'] = true;
		}
		return $args;
	}

	/**
	 * @param int    $user_id ID.
	 * @param string $size    Size.
	 * @return string
	 */
	public static function photo_url( $user_id, $size = 'medium' ) {
		if ( class_exists( 'LLM_User_Avatars' ) ) {
			$url = LLM_User_Avatars::user_url( (int) $user_id );
			if ( $url ) {
				return $url;
			}
		}
		$att = (int) get_user_meta( (int) $user_id, LLM_User_Meta::PROFILE_PHOTO, true );
		if ( ! $att ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $att, $size );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * @param array<string,string>|string $atts Attributi.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'user' => '',
			),
			$atts,
			self::SHORTCODE
		);

		if ( self::wants_guest_profile( (string) $atts['user'] ) ) {
			return self::render_guest();
		}

		$user = self::resolve_user( (string) $atts['user'] );
		if ( ! $user instanceof WP_User ) {
			return '<div class="llm-scheda-utente llm-scheda-utente--empty"><p>' . esc_html( self::t( 'no_user' ) ) . '</p></div>';
		}

		self::enqueue();

		$uid       = (int) $user->ID;
		$is_owner  = is_user_logged_in() && get_current_user_id() === $uid;
		$ui        = class_exists( 'LLM_Visitor_Lang' ) ? LLM_Visitor_Lang::known() : 'it';
		$roles     = class_exists( 'LLM_Story_Cast' ) ? LLM_Story_Cast::get_roles_for_user( $uid ) : array();
		$role_line = self::role_line( $roles );
		$stats     = self::stats_for_user( $uid );
		$modes     = self::build_modes( $uid );
		$default   = self::pick_default_pair( $modes['user']['pairs'] );
		$catalog   = self::stories_catalog_html( $uid, 'user', '', $default['known'], $default['target'] );

		$photo = self::photo_url( $uid, 'medium' );
		$avatar_file = class_exists( 'LLM_User_Avatars' ) ? LLM_User_Avatars::user_file( $uid ) : '';
		$avatar_html = $photo
			? '<img class="llm-scheda-utente__photo-img" src="' . esc_url( $photo ) . '" alt="" data-llm-scheda-photo data-llm-scheda-avatar="' . esc_attr( $avatar_file ) . '" />'
			: get_avatar( $uid, 280, '', $user->display_name, array( 'class' => 'llm-scheda-utente__photo-img' ) );

		$display = trim( (string) $user->display_name );
		$bio     = trim( (string) $user->description );
		$link    = trim( (string) $user->user_url );
		$dom_id  = 'llm-scheda-' . uniqid( '', false );

		wp_localize_script(
			'llm-scheda-utente',
			'llmSchedaUtente',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'storiesAct' => self::AJAX_STORIES,
				'guestAct'   => self::AJAX_GUEST,
				'guestPairsAct' => self::AJAX_GUEST_PAIRS,
				'saveAct'    => self::AJAX_SAVE,
				'storiesNonce' => wp_create_nonce( self::NONCE_STORIES ),
				'saveNonce'  => wp_create_nonce( self::NONCE_SAVE ),
				'userId'     => $uid,
				'isOwner'    => $is_owner ? 1 : 0,
				'isGuest'    => 0,
				'modes'      => $modes,
				'i18n'       => array(
					'empty'        => self::t( 'empty_pair' ),
					'saved'        => self::t( 'saved' ),
					'error'        => self::t( 'error' ),
					'started'      => self::t( 'started' ),
					'completed'    => self::t( 'completed' ),
					'loading'      => self::t( 'loading' ),
					'noPairs'      => self::t( 'no_pairs' ),
					'changeAvatar' => self::t( 'change_avatar' ),
					'playTimeMin'  => self::t( 'play_time_min' ),
				),
			)
		);

		ob_start();
		?>
		<div
			id="<?php echo esc_attr( $dom_id ); ?>"
			class="llm-scheda-utente llm-story-layout llm-story-layout--two"
			data-llm-scheda
			data-user-id="<?php echo esc_attr( (string) $uid ); ?>"
		>
			<div class="llm-scheda-utente__body llm-story-layout__body">
				<aside class="llm-scheda-utente__id llm-story-layout__read">
					<div class="llm-scheda-utente__id-inner">
						<div class="llm-scheda-utente__photo"><?php echo $avatar_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						<?php if ( $is_owner ) : ?>
							<?php echo self::avatar_change_buttons_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>
						<?php if ( $role_line ) : ?>
							<p class="llm-scheda-utente__role"><?php echo esc_html( $role_line ); ?></p>
						<?php endif; ?>
						<p class="llm-scheda-utente__login"><?php echo esc_html( (string) $user->user_login ); ?></p>
						<?php if ( $display ) : ?>
							<h1 class="llm-scheda-utente__name" data-field="display_name"><?php echo esc_html( $display ); ?></h1>
						<?php endif; ?>
						<?php if ( $bio ) : ?>
							<p class="llm-scheda-utente__bio" data-field="description"><?php echo esc_html( $bio ); ?></p>
						<?php else : ?>
							<p class="llm-scheda-utente__bio" data-field="description" hidden></p>
						<?php endif; ?>
						<p class="llm-scheda-utente__link-wrap" <?php echo $link ? '' : 'hidden'; ?>>
							<a class="llm-scheda-utente__link" data-field="user_url" href="<?php echo esc_url( $link ? $link : '#' ); ?>" rel="noopener noreferrer" target="_blank"><?php echo esc_html( $link ); ?></a>
						</p>

						<div class="llm-scheda-utente__info">
							<p class="llm-scheda-utente__info-label"><?php echo esc_html( self::t( 'info' ) ); ?></p>
							<ul class="llm-scheda-utente__stats">
								<li><span><?php echo esc_html( self::t( 'phrases' ) ); ?></span><strong data-llm-scheda-stat="phrases"><?php echo esc_html( (string) $stats['phrases'] ); ?></strong></li>
								<li><span><?php echo esc_html( self::t( 'crosswords' ) ); ?></span><strong data-llm-scheda-stat="crosswords"><?php echo esc_html( (string) $stats['crosswords'] ); ?></strong></li>
								<li><span><?php echo esc_html( self::t( 'play_time' ) ); ?></span><strong data-llm-scheda-stat="play_time"><?php echo esc_html( $stats['play_time'] ); ?></strong></li>
								<li><span><?php echo esc_html( self::t( 'points' ) ); ?></span><strong data-llm-scheda-stat="points"><?php echo esc_html( (string) $stats['points'] ); ?></strong></li>
								<li><span><?php echo esc_html( self::t( 'stories_done' ) ); ?></span><strong data-llm-scheda-stat="completed"><?php echo esc_html( (string) $stats['completed'] ); ?></strong></li>
								<li><span><?php echo esc_html( self::t( 'stories_started' ) ); ?></span><strong data-llm-scheda-stat="started"><?php echo esc_html( (string) $stats['started'] ); ?></strong></li>
								<li><span><?php echo esc_html( self::t( 'bravi' ) ); ?></span><strong data-llm-scheda-stat="bravi"><?php echo esc_html( (string) $stats['bravi'] ); ?></strong></li>
							</ul>
						</div>

						<?php if ( $is_owner ) : ?>
							<button type="button" class="llm-scheda-utente__edit-btn" data-llm-scheda-edit>
								<?php echo esc_html( self::t( 'edit_info' ) ); ?>
							</button>
							<?php echo self::auth_actions_html( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>

						<?php if ( $roles ) : ?>
							<details class="llm-scheda-utente__acc">
								<summary><?php echo esc_html( self::t( 'cast_staff' ) ); ?></summary>
								<div class="llm-scheda-utente__acc-body">
									<?php foreach ( $roles as $role ) : ?>
										<button
											type="button"
											class="llm-scheda-utente__role-btn"
											data-llm-scheda-mode="cast"
											data-llm-scheda-role="<?php echo esc_attr( $role['role_key'] ); ?>"
										>
											<?php echo esc_html( sprintf( self::t( 'view_as_role' ), $role['label'] ) ); ?>
										</button>
									<?php endforeach; ?>
								</div>
							</details>
						<?php endif; ?>

						<details class="llm-scheda-utente__acc" open>
							<summary><?php echo esc_html( self::t( 'as_user' ) ); ?></summary>
							<div class="llm-scheda-utente__acc-body">
								<button type="button" class="llm-scheda-utente__role-btn is-active" data-llm-scheda-mode="user" data-llm-scheda-role="">
									<?php echo esc_html( self::t( 'view_user_stories' ) ); ?>
								</button>
							</div>
						</details>
					</div>
				</aside>

				<div class="llm-scheda-utente__main llm-story-layout__learn">
					<?php if ( $is_owner ) : ?>
						<section class="llm-scheda-utente__edit" data-llm-scheda-edit-panel hidden>
							<h2 class="llm-scheda-utente__panel-title"><?php echo esc_html( self::t( 'edit_info' ) ); ?></h2>
							<form class="llm-scheda-utente__form" data-llm-scheda-form>
								<input type="hidden" name="avatar" value="<?php echo esc_attr( $avatar_file ); ?>" data-llm-scheda-avatar-input />
								<label class="llm-scheda-utente__field">
									<span><?php echo esc_html( self::t( 'full_name' ) ); ?></span>
									<input type="text" name="display_name" value="<?php echo esc_attr( $display ); ?>" maxlength="190" />
								</label>
								<label class="llm-scheda-utente__field">
									<span><?php echo esc_html( self::t( 'bio' ) ); ?></span>
									<textarea name="description" rows="6"><?php echo esc_textarea( $bio ); ?></textarea>
								</label>
								<label class="llm-scheda-utente__field">
									<span><?php echo esc_html( self::t( 'link' ) ); ?></span>
									<input type="url" name="user_url" value="<?php echo esc_attr( $link ); ?>" placeholder="https://" />
								</label>
								<p class="llm-scheda-utente__form-msg" data-llm-scheda-form-msg hidden></p>
								<div class="llm-scheda-utente__form-actions">
									<button type="submit" class="llm-scheda-utente__edit-btn"><?php echo esc_html( self::t( 'save' ) ); ?></button>
									<button type="button" class="llm-scheda-utente__btn-ghost" data-llm-scheda-cancel><?php echo esc_html( self::t( 'cancel' ) ); ?></button>
								</div>
							</form>
						</section>
					<?php endif; ?>

					<section class="llm-scheda-utente__stories" data-llm-scheda-stories>
						<div class="llm-scheda-utente__pairs" data-llm-scheda-pairs role="tablist">
							<?php echo self::render_pair_buttons( $modes['user']['pairs'], $default ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
						<div class="llm-scheda-utente__catalog llm-ie-stories" data-llm-ie-catalog data-llm-scheda-catalog>
							<div class="llm-ie-stories__backdrop" hidden></div>
							<div data-llm-scheda-board>
								<?php echo $catalog; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>
					</section>
				</div>
			</div>
		</div>
		<?php
		unset( $ui );
		return (string) ob_get_clean();
	}

	/**
	 * Stessa scheda, dati da localStorage del browser.
	 *
	 * @return string
	 */
	private static function render_guest() {
		self::enqueue();

		$guest_name = self::t( 'guest_name' );
		$dom_id     = 'llm-scheda-' . uniqid( '', false );
		$blank      = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
		$avatar     = '<img class="llm-scheda-utente__photo-img" src="' . esc_attr( $blank ) . '" alt="" data-llm-scheda-photo data-llm-guest-avatar />';

		wp_localize_script(
			'llm-scheda-utente',
			'llmSchedaUtente',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'storiesAct'    => self::AJAX_STORIES,
				'guestAct'      => self::AJAX_GUEST,
				'guestPairsAct' => self::AJAX_GUEST_PAIRS,
				'saveAct'       => self::AJAX_SAVE,
				'storiesNonce'  => wp_create_nonce( self::NONCE_STORIES ),
				'saveNonce'     => wp_create_nonce( self::NONCE_SAVE ),
				'userId'        => 0,
				'isOwner'       => 1,
				'isGuest'       => 1,
				'modes'         => array(
					'user' => array(
						'pairs'     => array(),
						'started'   => array(),
						'completed' => array(),
					),
					'cast' => array(),
				),
				'i18n'          => array(
					'empty'     => self::t( 'empty_pair' ),
					'saved'     => self::t( 'saved' ),
					'error'     => self::t( 'error' ),
					'started'   => self::t( 'started' ),
					'completed' => self::t( 'completed' ),
					'loading'   => self::t( 'loading' ),
					'noPairs'       => self::t( 'no_pairs' ),
					'guestName'     => $guest_name,
					'changeAvatar'  => self::t( 'change_avatar' ),
					'playTimeMin'   => self::t( 'play_time_min' ),
				),
			)
		);

		ob_start();
		?>
		<div
			id="<?php echo esc_attr( $dom_id ); ?>"
			class="llm-scheda-utente llm-story-layout llm-story-layout--two llm-scheda-utente--guest"
			data-llm-scheda
			data-llm-scheda-guest="1"
			data-user-id="0"
		>
			<div class="llm-scheda-utente__body llm-story-layout__body">
				<aside class="llm-scheda-utente__id llm-story-layout__read">
					<div class="llm-scheda-utente__id-inner">
						<div class="llm-scheda-utente__photo"><?php echo $avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						<?php echo self::avatar_change_buttons_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<p class="llm-scheda-utente__role"><?php echo esc_html( $guest_name ); ?></p>
						<p class="llm-scheda-utente__login"><?php echo esc_html( $guest_name ); ?></p>
						<h1 class="llm-scheda-utente__name" data-field="display_name"><?php echo esc_html( $guest_name ); ?></h1>
						<p class="llm-scheda-utente__bio" data-field="description" hidden></p>
						<p class="llm-scheda-utente__link-wrap" hidden>
							<a class="llm-scheda-utente__link" data-field="user_url" href="#" rel="noopener noreferrer" target="_blank"></a>
						</p>

						<div class="llm-scheda-utente__info">
							<p class="llm-scheda-utente__info-label"><?php echo esc_html( self::t( 'info' ) ); ?></p>
							<ul class="llm-scheda-utente__stats">
								<li><span><?php echo esc_html( self::t( 'phrases' ) ); ?></span><strong data-llm-scheda-stat="phrases">0</strong></li>
								<li><span><?php echo esc_html( self::t( 'crosswords' ) ); ?></span><strong data-llm-scheda-stat="crosswords">0</strong></li>
								<li><span><?php echo esc_html( self::t( 'play_time' ) ); ?></span><strong data-llm-scheda-stat="play_time">0 min</strong></li>
								<li><span><?php echo esc_html( self::t( 'points' ) ); ?></span><strong data-llm-scheda-stat="points">0</strong></li>
								<li><span><?php echo esc_html( self::t( 'stories_done' ) ); ?></span><strong data-llm-scheda-stat="completed">0</strong></li>
								<li><span><?php echo esc_html( self::t( 'stories_started' ) ); ?></span><strong data-llm-scheda-stat="started">0</strong></li>
								<li><span><?php echo esc_html( self::t( 'bravi' ) ); ?></span><strong data-llm-scheda-stat="bravi">0</strong></li>
							</ul>
						</div>

						<button type="button" class="llm-scheda-utente__edit-btn" data-llm-scheda-edit>
							<?php echo esc_html( self::t( 'edit_info' ) ); ?>
						</button>
						<?php echo self::auth_actions_html( true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

						<details class="llm-scheda-utente__acc" open>
							<summary><?php echo esc_html( self::t( 'as_user' ) ); ?></summary>
							<div class="llm-scheda-utente__acc-body">
								<button type="button" class="llm-scheda-utente__role-btn is-active" data-llm-scheda-mode="user" data-llm-scheda-role="">
									<?php echo esc_html( self::t( 'view_user_stories' ) ); ?>
								</button>
							</div>
						</details>
					</div>
				</aside>

				<div class="llm-scheda-utente__main llm-story-layout__learn">
					<section class="llm-scheda-utente__edit" data-llm-scheda-edit-panel hidden>
						<h2 class="llm-scheda-utente__panel-title"><?php echo esc_html( self::t( 'edit_info' ) ); ?></h2>
						<form class="llm-scheda-utente__form" data-llm-scheda-form data-llm-scheda-guest-form>
							<input type="hidden" name="avatar" value="" data-llm-scheda-avatar-input />
							<label class="llm-scheda-utente__field">
								<span><?php echo esc_html( self::t( 'full_name' ) ); ?></span>
								<input type="text" name="display_name" value="" maxlength="60" autocomplete="nickname" />
							</label>
							<p class="llm-scheda-utente__form-msg" data-llm-scheda-form-msg hidden></p>
							<div class="llm-scheda-utente__form-actions">
								<button type="submit" class="llm-scheda-utente__edit-btn"><?php echo esc_html( self::t( 'save' ) ); ?></button>
								<button type="button" class="llm-scheda-utente__btn-ghost" data-llm-scheda-cancel><?php echo esc_html( self::t( 'cancel' ) ); ?></button>
							</div>
						</form>
					</section>

					<section class="llm-scheda-utente__stories" data-llm-scheda-stories>
						<div class="llm-scheda-utente__pairs" data-llm-scheda-pairs role="tablist">
							<p class="llm-scheda-utente__pairs-empty"><?php echo esc_html( self::t( 'no_pairs' ) ); ?></p>
						</div>
						<div class="llm-scheda-utente__catalog llm-ie-stories" data-llm-ie-catalog data-llm-scheda-catalog>
							<div class="llm-ie-stories__backdrop" hidden></div>
							<div data-llm-scheda-board>
								<p class="llm-scheda-utente__empty"><?php echo esc_html( self::t( 'loading' ) ); ?></p>
							</div>
						</div>
					</section>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function enqueue() {
		if ( class_exists( 'LLM_Home_Page_Uscite_Shortcode' ) ) {
			add_action( 'wp_head', array( 'LLM_Home_Page_Uscite_Shortcode', 'print_font_preconnect' ), 2 );
			LLM_Home_Page_Uscite_Shortcode::print_font_preconnect();
		}
		wp_enqueue_style(
			'llm-uscite-fonts',
			'https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Bebas+Neue&family=Elms+Sans:ital,wght@0,100..900;1,100..900&family=Lora:ital,wght@0,400..700;1,400..700&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Roboto+Slab:wght@100..900&display=swap',
			array(),
			null
		);
		wp_enqueue_style(
			'llm-italian-english-stories',
			LLM_TABELLE_URL . 'assets/llm-italian-english-stories.css',
			array( 'llm-uscite-fonts' ),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_script(
			'llm-italian-english-stories',
			LLM_TABELLE_URL . 'assets/llm-italian-english-stories.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
		wp_enqueue_style(
			'llm-scheda-utente',
			LLM_TABELLE_URL . 'assets/llm-scheda-utente.css',
			array( 'llm-italian-english-stories' ),
			LLM_TABELLE_VERSION
		);
		if ( class_exists( 'LLM_User_Avatars' ) ) {
			LLM_User_Avatars::enqueue();
		}
		wp_enqueue_script(
			'llm-guest-browser-store',
			LLM_TABELLE_URL . 'assets/llm-guest-browser-store.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
		wp_enqueue_script(
			'llm-scheda-utente',
			LLM_TABELLE_URL . 'assets/llm-scheda-utente.js',
			array( 'llm-guest-browser-store', 'llm-user-avatars', 'llm-italian-english-stories' ),
			LLM_TABELLE_VERSION,
			true
		);
	}

	/**
	 * Pulsanti Cambia Avatar / Salva / Annulla (niente galleria).
	 *
	 * @return string
	 */
	private static function avatar_change_buttons_html() {
		ob_start();
		?>
		<div class="llm-scheda-utente__avatar-actions">
			<button type="button" class="llm-scheda-utente__edit-btn" data-llm-scheda-avatar-next>
				<?php echo esc_html( self::t( 'change_avatar' ) ); ?>
			</button>
			<span class="llm-scheda-utente__avatar-pending" data-llm-scheda-avatar-pending hidden>
				<button type="button" class="llm-scheda-utente__edit-btn" data-llm-scheda-avatar-save>
					<?php echo esc_html( self::t( 'save' ) ); ?>
				</button>
				<button type="button" class="llm-scheda-utente__btn-ghost" data-llm-scheda-avatar-cancel>
					<?php echo esc_html( self::t( 'cancel' ) ); ?>
				</button>
			</span>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Accedi (ospite) o Logout (utente connesso) in area personale.
	 *
	 * @param bool $is_guest Scheda browser.
	 * @return string
	 */
	private static function auth_actions_html( $is_guest ) {
		$login_url  = class_exists( 'LLM_Frontend_Auth' ) ? LLM_Frontend_Auth::login_url() : home_url( '/login' );
		$logout_to  = class_exists( 'LLM_Frontend_Auth' ) ? LLM_Frontend_Auth::after_logout_url() : home_url( '/' );
		$logout_url = wp_logout_url( $logout_to );

		ob_start();
		?>
		<div class="llm-scheda-utente__auth">
			<?php if ( $is_guest ) : ?>
				<a class="llm-scheda-utente__edit-btn" href="<?php echo esc_url( $login_url ); ?>">
					<?php echo esc_html( self::t( 'login' ) ); ?>
				</a>
			<?php else : ?>
				<a class="llm-scheda-utente__edit-btn llm-scheda-utente__logout" href="<?php echo esc_url( $logout_url ); ?>">
					<?php echo esc_html( self::t( 'logout' ) ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param string $login_or_slug Login.
	 * @return WP_User|null
	 */
	private static function resolve_user( $login_or_slug ) {
		$from_url = sanitize_user( (string) get_query_var( self::QVAR ), true );
		if ( '' === $login_or_slug && '' !== $from_url ) {
			$login_or_slug = $from_url;
		}
		$login_or_slug = sanitize_user( (string) $login_or_slug, true );
		if ( '' !== $login_or_slug ) {
			$u = get_user_by( 'slug', $login_or_slug );
			if ( ! $u ) {
				$u = get_user_by( 'login', $login_or_slug );
			}
			if ( $u instanceof WP_User ) {
				return $u;
			}
		}
		if ( is_author() ) {
			$obj = get_queried_object();
			if ( $obj instanceof WP_User ) {
				return $obj;
			}
			$aid = (int) get_query_var( 'author' );
			if ( $aid ) {
				$u = get_userdata( $aid );
				if ( $u instanceof WP_User ) {
					return $u;
				}
			}
		}
		if ( is_user_logged_in() ) {
			return wp_get_current_user();
		}
		return null;
	}

	/**
	 * @param array<int, array{role_key:string,label:string}> $roles Ruoli.
	 * @return string
	 */
	private static function role_line( $roles ) {
		$labels = array();
		foreach ( $roles as $role ) {
			if ( ! empty( $role['label'] ) ) {
				$labels[] = (string) $role['label'];
			}
		}
		return implode( ' · ', $labels );
	}

	/**
	 * @param int $user_id ID.
	 * @return array{phrases:int,crosswords:int,play_time:string,points:int,completed:int,started:int,bravi:int}
	 */
	private static function stats_for_user( $user_id ) {
		$phrases    = class_exists( 'LLM_User_Stats' ) ? LLM_User_Stats::count_completed_phrases( $user_id ) : 0;
		$crosswords = class_exists( 'LLM_User_Stats' ) ? LLM_User_Stats::count_completed_crosswords( $user_id ) : 0;
		$points     = class_exists( 'LLM_User_Stats' ) ? LLM_User_Stats::get_balance( $user_id ) : 0;
		$completed  = class_exists( 'LLM_User_Stats' ) ? LLM_User_Stats::count_completed_stories( $user_id ) : 0;
		$started    = count( self::started_ids( $user_id ) );
		$bravi      = class_exists( 'LLM_Community' ) ? LLM_Community::count_bravi_received( $user_id ) : 0;
		$seconds    = class_exists( 'LLM_User_Story_Play_Time' ) ? LLM_User_Story_Play_Time::sum_seconds( $user_id ) : 0;
		$minutes    = class_exists( 'LLM_User_Story_Play_Time' ) ? LLM_User_Story_Play_Time::seconds_to_minutes( $seconds ) : 0;
		return array(
			'phrases'    => $phrases,
			'crosswords' => $crosswords,
			'play_time'  => sprintf( self::t( 'play_time_min' ), $minutes ),
			'points'     => $points,
			'completed'  => $completed,
			'started'    => $started,
			'bravi'      => $bravi,
		);
	}

	/**
	 * @param int $user_id ID.
	 * @return array<string, mixed>
	 */
	private static function build_modes( $user_id ) {
		$started   = self::started_ids( $user_id );
		$completed = array_map( 'absint', array_keys( LLM_User_Stats::get_completed_stories_map( $user_id ) ) );
		$user_ids  = array_values( array_unique( array_merge( $started, $completed ) ) );
		$modes     = array(
			'user' => array(
				'pairs'     => self::pairs_for_story_ids( $user_ids ),
				'started'   => $started,
				'completed' => $completed,
			),
			'cast' => array(),
		);
		if ( class_exists( 'LLM_Story_Cast' ) ) {
			$by_role = array();
			foreach ( LLM_Story_Cast::get_assignments_for_user( $user_id ) as $row ) {
				$key = $row['role_key'];
				if ( ! isset( $by_role[ $key ] ) ) {
					$by_role[ $key ] = array();
				}
				$by_role[ $key ][] = (int) $row['story_id'];
			}
			foreach ( $by_role as $key => $ids ) {
				$ids = array_values( array_unique( array_filter( $ids ) ) );
				$modes['cast'][ $key ] = array(
					'pairs'     => self::pairs_for_story_ids( $ids ),
					'story_ids' => $ids,
				);
			}
		}
		return $modes;
	}

	/**
	 * @param int[] $story_ids ID.
	 * @return array<int, array{known:string,target:string,count:int,flags:string,label:string}>
	 */
	private static function pairs_for_story_ids( array $story_ids ) {
		$counts = array();
		foreach ( $story_ids as $sid ) {
			$pair = self::story_pair( (int) $sid );
			if ( ! $pair ) {
				continue;
			}
			$key = $pair[0] . '_' . $pair[1];
			if ( ! isset( $counts[ $key ] ) ) {
				$counts[ $key ] = array(
					'known'  => $pair[0],
					'target' => $pair[1],
					'count'  => 0,
				);
			}
			++$counts[ $key ]['count'];
		}
		$out = array_values( $counts );
		usort(
			$out,
			static function ( $a, $b ) {
				return (int) $b['count'] - (int) $a['count'];
			}
		);
		foreach ( $out as $i => $row ) {
			$fk = class_exists( 'LLM_Languages' ) ? LLM_Languages::flag_emoji( $row['known'] ) : '';
			$ft = class_exists( 'LLM_Languages' ) ? LLM_Languages::flag_emoji( $row['target'] ) : '';
			$out[ $i ]['flags'] = trim( $fk . ' → ' . $ft );
			$out[ $i ]['label'] = strtoupper( $row['known'] ) . ' → ' . strtoupper( $row['target'] );
		}
		return $out;
	}

	/**
	 * @param array<int, array<string,mixed>> $pairs Pairs.
	 * @return array{known:string,target:string}
	 */
	private static function pick_default_pair( array $pairs ) {
		if ( empty( $pairs ) ) {
			return array(
				'known'  => '',
				'target' => '',
			);
		}
		return array(
			'known'  => (string) $pairs[0]['known'],
			'target' => (string) $pairs[0]['target'],
		);
	}

	/**
	 * @param array<int, array<string,mixed>> $pairs   Pairs.
	 * @param array{known:string,target:string} $active Active.
	 * @return string
	 */
	private static function render_pair_buttons( array $pairs, array $active ) {
		if ( ! $pairs ) {
			return '<p class="llm-scheda-utente__pairs-empty">' . esc_html( self::t( 'no_pairs' ) ) . '</p>';
		}
		$html = '';
		foreach ( $pairs as $pair ) {
			$is = ( $pair['known'] === $active['known'] && $pair['target'] === $active['target'] );
			$html .= '<button type="button" class="llm-scheda-utente__pair' . ( $is ? ' is-active' : '' ) . '" data-known="' . esc_attr( $pair['known'] ) . '" data-target="' . esc_attr( $pair['target'] ) . '" role="tab" aria-selected="' . ( $is ? 'true' : 'false' ) . '">';
			$html .= '<span class="llm-scheda-utente__pair-flags">' . esc_html( $pair['flags'] ) . '</span>';
			$html .= '<span class="llm-scheda-utente__pair-count">' . esc_html( (string) $pair['count'] ) . '</span>';
			$html .= '</button>';
		}
		return $html;
	}

	/**
	 * @param int    $user_id  ID.
	 * @param string $mode     user|cast.
	 * @param string $role_key Ruolo.
	 * @param string $known    Known.
	 * @param string $target   Target.
	 * @return string
	 */
	private static function stories_catalog_html( $user_id, $mode, $role_key, $known, $target ) {
		$known  = sanitize_key( $known );
		$target = sanitize_key( $target );
		if ( '' === $known || '' === $target ) {
			return '<p class="llm-scheda-utente__empty">' . esc_html( self::t( 'no_pairs' ) ) . '</p>';
		}

		if ( 'cast' === $mode ) {
			$ids = array();
			if ( class_exists( 'LLM_Story_Cast' ) ) {
				foreach ( LLM_Story_Cast::get_assignments_for_user( $user_id ) as $row ) {
					if ( $row['role_key'] === $role_key ) {
						$ids[] = (int) $row['story_id'];
					}
				}
			}
			$ids = self::filter_ids_by_pair( $ids, $known, $target );
			if ( ! $ids ) {
				return '<p class="llm-scheda-utente__empty">' . esc_html( self::t( 'empty_pair' ) ) . '</p>';
			}
			return self::render_section( self::t( 'cast_stories' ), $ids, '' );
		}

		$started   = self::filter_ids_by_pair( self::started_ids( $user_id ), $known, $target );
		$completed = self::filter_ids_by_pair(
			array_map( 'absint', array_keys( LLM_User_Stats::get_completed_stories_map( $user_id ) ) ),
			$known,
			$target
		);
		return self::stories_catalog_from_lists( $started, $completed, $known, $target, false );
	}

	/**
	 * @param int[]  $started   ID iniziate.
	 * @param int[]  $completed ID completate.
	 * @param string $known     Known.
	 * @param string $target    Target.
	 * @param bool   $filter    Se filtrare ancora per coppia.
	 * @return string
	 */
	private static function stories_catalog_from_lists( array $started, array $completed, $known, $target, $filter = true ) {
		$known  = sanitize_key( $known );
		$target = sanitize_key( $target );
		if ( '' === $known || '' === $target ) {
			return '<p class="llm-scheda-utente__empty">' . esc_html( self::t( 'no_pairs' ) ) . '</p>';
		}
		if ( $filter ) {
			$started   = self::filter_ids_by_pair( $started, $known, $target );
			$completed = self::filter_ids_by_pair( $completed, $known, $target );
		}
		$html = '';
		if ( $started ) {
			$html .= self::render_section( self::t( 'started' ), $started, self::t( 'started' ) );
		}
		if ( $completed ) {
			$html .= self::render_section( self::t( 'completed' ), $completed, self::t( 'completed' ) );
		}
		if ( '' === $html ) {
			return '<p class="llm-scheda-utente__empty">' . esc_html( self::t( 'empty_pair' ) ) . '</p>';
		}
		return $html;
	}

	/**
	 * @param string $title  Titolo.
	 * @param int[]  $ids    ID storie.
	 * @param string $kicker Badge.
	 * @return string
	 */
	private static function render_section( $title, array $ids, $kicker ) {
		$html  = '<section class="llm-scheda-utente__block">';
		$html .= '<h2 class="llm-scheda-utente__panel-title">' . esc_html( $title ) . '</h2>';
		$html .= '<div class="llm-ie-stories__grid llm-scheda-utente__grid">';
		foreach ( $ids as $sid ) {
			$html .= self::render_card( (int) $sid, $kicker );
		}
		$html .= '</div></section>';
		return $html;
	}

	/**
	 * @param int    $story_id ID.
	 * @param string $kicker   Badge.
	 * @return string
	 */
	private static function render_card( $story_id, $kicker ) {
		$post = get_post( $story_id );
		if ( ! $post || LLM_STORY_CPT !== $post->post_type || 'publish' !== $post->post_status ) {
			return '';
		}
		$title_it = get_the_title( $story_id );
		$title_t  = trim( (string) get_post_meta( $story_id, LLM_Story_Meta::TITLE_TARGET, true ) );
		$title    = $title_t !== '' ? $title_t : $title_it;
		$subtitle = ( $title_t !== '' && $title_it !== '' && strcasecmp( $title_t, $title_it ) !== 0 ) ? $title_it : '';
		$thumb_id = get_post_thumbnail_id( $story_id );
		$cover    = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
		$plot     = trim( (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_PLOT, true ) );
		$cefr_raw = trim( (string) get_post_meta( $story_id, LLM_Story_Meta::STORY_CEFR_LEVEL, true ) );
		$cefr     = '';
		if ( $cefr_raw && preg_match( '/\b([ABC][12])\b/i', $cefr_raw, $m ) ) {
			$cefr = strtoupper( $m[1] );
		}
		$url    = (string) get_permalink( $story_id );
		$pop_id = 'llm-scheda-pop-' . $story_id . '-' . wp_unique_id();
		$cta    = self::t( 'open_story' );

		ob_start();
		?>
		<article class="llm-ie-stories__card llm-ie-stories__card--openable llm-scheda-utente__card" data-llm-ie-card data-llm-ie-card-id="<?php echo esc_attr( $pop_id ); ?>">
			<div class="llm-ie-stories__trigger" role="button" tabindex="0" aria-expanded="false" aria-controls="<?php echo esc_attr( $pop_id ); ?>">
				<div
					class="llm-ie-stories__cover<?php echo $cover ? '' : ' llm-ie-stories__cover--empty'; ?>"
					<?php if ( $cover ) : ?>
						style="background-image:url('<?php echo esc_url( $cover ); ?>');"
					<?php endif; ?>
					role="img"
					aria-label="<?php echo esc_attr( $title ); ?>"
				>
					<?php if ( $kicker ) : ?>
						<span class="llm-scheda-utente__badge"><?php echo esc_html( $kicker ); ?></span>
					<?php endif; ?>
					<?php if ( $cefr ) : ?>
						<span class="llm-scheda-utente__cefr"><?php echo esc_html( $cefr ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<div id="<?php echo esc_attr( $pop_id ); ?>" class="llm-ie-stories__popup" role="dialog" aria-modal="true" hidden data-llm-ie-for="<?php echo esc_attr( $pop_id ); ?>">
				<button type="button" class="llm-ie-stories__popup-close" aria-label="<?php echo esc_attr( self::t( 'close' ) ); ?>"><span aria-hidden="true">&times;</span></button>
				<div class="llm-ie-stories__popup-cover<?php echo $cover ? '' : ' llm-ie-stories__cover--empty'; ?>" <?php if ( $cover ) : ?>style="background-image:url('<?php echo esc_url( $cover ); ?>');"<?php endif; ?> aria-hidden="true"></div>
				<div class="llm-ie-stories__popup-body">
					<h4 class="llm-ie-stories__popup-title"><?php echo esc_html( $title ); ?></h4>
					<?php if ( $subtitle ) : ?>
						<p class="llm-ie-stories__popup-sub"><?php echo esc_html( $subtitle ); ?></p>
					<?php endif; ?>
					<?php if ( $plot ) : ?>
						<p class="llm-ie-stories__plot"><?php echo esc_html( $plot ); ?></p>
					<?php endif; ?>
					<a class="llm-ie-stories__cta" href="<?php echo esc_url( $url ); ?>"><span><?php echo esc_html( $cta ); ?></span></a>
				</div>
			</div>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	public static function ajax_stories() {
		check_ajax_referer( self::NONCE_STORIES, 'nonce' );
		$user_id  = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$mode     = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'user';
		$role_key = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
		$known    = isset( $_POST['known'] ) ? sanitize_key( wp_unslash( $_POST['known'] ) ) : '';
		$target   = isset( $_POST['target'] ) ? sanitize_key( wp_unslash( $_POST['target'] ) ) : '';
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			wp_send_json_error( array( 'message' => self::t( 'error' ) ), 400 );
		}
		if ( 'cast' !== $mode ) {
			$mode     = 'user';
			$role_key = '';
		}
		$html = self::stories_catalog_html( $user_id, $mode, $role_key, $known, $target );
		wp_send_json_success( array( 'html' => $html ) );
	}

	public static function ajax_guest_pairs() {
		check_ajax_referer( self::NONCE_STORIES, 'nonce' );
		$ids = self::posted_id_list( 'ids' );
		wp_send_json_success( array( 'pairs' => self::pairs_for_story_ids( $ids ) ) );
	}

	public static function ajax_guest() {
		check_ajax_referer( self::NONCE_STORIES, 'nonce' );
		$known     = isset( $_POST['known'] ) ? sanitize_key( wp_unslash( $_POST['known'] ) ) : '';
		$target    = isset( $_POST['target'] ) ? sanitize_key( wp_unslash( $_POST['target'] ) ) : '';
		$started   = self::posted_id_list( 'started' );
		$completed = self::posted_id_list( 'completed' );
		if ( $completed ) {
			$done    = array_fill_keys( $completed, true );
			$started = array_values(
				array_filter(
					$started,
					static function ( $id ) use ( $done ) {
						return empty( $done[ $id ] );
					}
				)
			);
		}
		$html = self::stories_catalog_from_lists( $started, $completed, $known, $target, true );
		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * @param string $key POST key.
	 * @return int[]
	 */
	private static function posted_id_list( $key ) {
		if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return array();
		}
		$raw = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( is_array( $raw ) ) {
			$parts = $raw;
		} else {
			$parts = preg_split( '/[,\s]+/', (string) $raw );
		}
		$ids = array();
		foreach ( (array) $parts as $part ) {
			$n = absint( $part );
			if ( $n ) {
				$ids[] = $n;
			}
		}
		$ids = array_values( array_unique( $ids ) );
		return array_slice( $ids, 0, 200 );
	}

	public static function ajax_save() {
		check_ajax_referer( self::NONCE_SAVE, 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => self::t( 'error' ) ), 403 );
		}
		$uid = get_current_user_id();
		$user = get_userdata( $uid );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => self::t( 'error' ) ), 400 );
		}

		$display = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
		$bio     = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$url     = isset( $_POST['user_url'] ) ? esc_url_raw( wp_unslash( $_POST['user_url'] ) ) : '';
		if ( '' === $display ) {
			$display = (string) $user->user_login;
		}

		$payload = array(
			'ID'           => $uid,
			'display_name' => $display,
			'description'  => $bio,
			'user_url'     => $url,
		);
		$has_profile_fields = isset( $_POST['display_name'] ) || isset( $_POST['description'] ) || isset( $_POST['user_url'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $has_profile_fields ) {
			$updated = wp_update_user( $payload );
			if ( is_wp_error( $updated ) ) {
				wp_send_json_error( array( 'message' => $updated->get_error_message() ), 400 );
			}
		}

		$avatar_file = '';
		if ( isset( $_POST['avatar'] ) && class_exists( 'LLM_User_Avatars' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$avatar_file = LLM_User_Avatars::set_user( $uid, sanitize_text_field( wp_unslash( $_POST['avatar'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( '' === $avatar_file ) {
				wp_send_json_error( array( 'message' => self::t( 'error' ) ), 400 );
			}
		}

		$photo_url = self::photo_url( $uid, 'medium' );
		if ( ! empty( $_FILES['photo'] ) && is_array( $_FILES['photo'] ) && ! empty( $_FILES['photo']['name'] ) ) {
			$saved = self::handle_photo_upload( $uid );
			if ( is_wp_error( $saved ) ) {
				wp_send_json_error( array( 'message' => $saved->get_error_message() ), 400 );
			}
			$photo_url = self::photo_url( $uid, 'medium' );
		}

		wp_send_json_success(
			array(
				'display_name' => $display,
				'description'  => $bio,
				'user_url'     => $url,
				'photo'        => $photo_url,
				'avatar'       => $avatar_file ? $avatar_file : ( class_exists( 'LLM_User_Avatars' ) ? LLM_User_Avatars::user_file( $uid ) : '' ),
				'message'      => self::t( 'saved' ),
			)
		);
	}

	/**
	 * @param int $user_id ID.
	 * @return true|WP_Error
	 */
	private static function handle_photo_upload( $user_id ) {
		if ( empty( $_FILES['photo'] ) || ! is_array( $_FILES['photo'] ) ) {
			return true;
		}
		$file = $_FILES['photo'];
		if ( ! empty( $file['error'] ) && UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
			return true;
		}
		if ( (int) $file['size'] > 3 * 1024 * 1024 ) {
			return new WP_Error( 'photo_size', self::t( 'photo_big' ) );
		}
		$check = wp_check_filetype_and_ext(
			isset( $file['tmp_name'] ) ? $file['tmp_name'] : '',
			isset( $file['name'] ) ? $file['name'] : ''
		);
		$ok_ext = array( 'jpg', 'jpeg', 'png', 'webp' );
		$ext    = isset( $check['ext'] ) ? strtolower( (string) $check['ext'] ) : '';
		if ( ! in_array( $ext, $ok_ext, true ) ) {
			return new WP_Error( 'photo_type', self::t( 'photo_type' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$overrides = array(
			'test_form' => false,
			'mimes'     => array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
				'webp'         => 'image/webp',
			),
		);
		$moved = wp_handle_upload( $file, $overrides );
		if ( isset( $moved['error'] ) ) {
			return new WP_Error( 'photo_up', (string) $moved['error'] );
		}
		$filename = isset( $moved['file'] ) ? $moved['file'] : '';
		if ( '' === $filename ) {
			return new WP_Error( 'photo_up', self::t( 'error' ) );
		}
		$att_id = wp_insert_attachment(
			array(
				'post_mime_type' => isset( $moved['type'] ) ? $moved['type'] : 'image/jpeg',
				'post_title'     => sanitize_file_name( wp_basename( $filename ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_author'    => $user_id,
			),
			$filename
		);
		if ( is_wp_error( $att_id ) || ! $att_id ) {
			return is_wp_error( $att_id ) ? $att_id : new WP_Error( 'photo_up', self::t( 'error' ) );
		}
		$meta = wp_generate_attachment_metadata( $att_id, $filename );
		if ( is_array( $meta ) ) {
			wp_update_attachment_metadata( $att_id, $meta );
		}
		$old = (int) get_user_meta( $user_id, LLM_User_Meta::PROFILE_PHOTO, true );
		update_user_meta( $user_id, LLM_User_Meta::PROFILE_PHOTO, (int) $att_id );
		if ( $old && $old !== (int) $att_id ) {
			wp_delete_attachment( $old, true );
		}
		return true;
	}

	/**
	 * @param int $user_id ID.
	 * @return int[]
	 */
	private static function started_ids( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}
		$ids = array();
		$p   = LLM_Tabelle_Database::table( 'llm_user_story_game_progress' );
		$d   = LLM_Tabelle_Database::table( 'llm_user_phrase_done' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT story_id FROM {$p} WHERE user_id = %d", $user_id ) );
		if ( is_array( $col ) ) {
			$ids = array_merge( $ids, array_map( 'absint', $col ) );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT story_id FROM {$d} WHERE user_id = %d", $user_id ) );
		if ( is_array( $col ) ) {
			$ids = array_merge( $ids, array_map( 'absint', $col ) );
		}
		$completed = array();
		if ( class_exists( 'LLM_User_Stats' ) ) {
			$completed = array_map( 'absint', array_keys( LLM_User_Stats::get_completed_stories_map( $user_id ) ) );
		}
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		if ( $completed ) {
			$ids = array_values( array_diff( $ids, $completed ) );
		}
		return $ids;
	}

	/**
	 * @param int[]  $ids    ID.
	 * @param string $known  Known.
	 * @param string $target Target.
	 * @return int[]
	 */
	private static function filter_ids_by_pair( array $ids, $known, $target ) {
		$out = array();
		foreach ( $ids as $sid ) {
			$pair = self::story_pair( (int) $sid );
			if ( $pair && $pair[0] === $known && $pair[1] === $target ) {
				$out[] = (int) $sid;
			}
		}
		return $out;
	}

	/**
	 * @param int $story_id ID.
	 * @return array{0:string,1:string}|null
	 */
	private static function story_pair( $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return null;
		}
		$post = get_post( $story_id );
		if ( ! $post || LLM_STORY_CPT !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}
		$known  = sanitize_key( (string) get_post_meta( $story_id, LLM_Story_Meta::KNOWN_LANG, true ) );
		$target = sanitize_key( (string) get_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, true ) );
		if ( ! LLM_Languages::is_valid( $known ) || ! LLM_Languages::is_valid( $target ) || $known === $target ) {
			return null;
		}
		return array( $known, $target );
	}

	/**
	 * @param string $key Chiave.
	 * @return string
	 */
	private static function t( $key ) {
		$ui  = class_exists( 'LLM_Visitor_Lang' ) ? LLM_Visitor_Lang::known() : 'it';
		$ui  = sanitize_key( (string) $ui );
		$set = array(
			'no_user'            => array(
				'it' => 'Utente non trovato.',
				'en' => 'User not found.',
				'pl' => 'Nie znaleziono użytkownika.',
				'es' => 'Usuario no encontrado.',
			),
			'guest_name'         => array(
				'it' => 'Utente browser',
				'en' => 'Browser user',
				'pl' => 'Użytkownik przeglądarki',
				'es' => 'Usuario del navegador',
			),
			'info'               => array(
				'it' => 'Info',
				'en' => 'Info',
				'pl' => 'Info',
				'es' => 'Info',
			),
			'phrases'            => array(
				'it' => 'Frasi completate',
				'en' => 'Phrases completed',
				'pl' => 'Ukończone zdania',
				'es' => 'Frases completadas',
			),
			'crosswords'         => array(
				'it' => 'Cruciverba completati',
				'en' => 'Crosswords completed',
				'pl' => 'Ukończone krzyżówki',
				'es' => 'Crucigramas completados',
			),
			'play_time'          => array(
				'it' => 'Tempo di gioco',
				'en' => 'Play time',
				'pl' => 'Czas gry',
				'es' => 'Tiempo de juego',
			),
			'play_time_min'      => array(
				'it' => '%d min',
				'en' => '%d min',
				'pl' => '%d min',
				'es' => '%d min',
			),
			'points'             => array(
				'it' => 'Punti',
				'en' => 'Points',
				'pl' => 'Punkty',
				'es' => 'Puntos',
			),
			'stories_done'       => array(
				'it' => 'Storie completate',
				'en' => 'Stories completed',
				'pl' => 'Ukończone historie',
				'es' => 'Historias completadas',
			),
			'stories_started'    => array(
				'it' => 'Storie iniziate',
				'en' => 'Stories started',
				'pl' => 'Rozpoczęte historie',
				'es' => 'Historias empezadas',
			),
			'bravi'              => array(
				'it' => 'Bravi ricevuti',
				'en' => 'Bravos received',
				'pl' => 'Otrzymane brawa',
				'es' => 'Bravos recibidos',
			),
			'edit_info'          => array(
				'it' => 'Modifica le tue info',
				'en' => 'Edit your info',
				'pl' => 'Edytuj swoje dane',
				'es' => 'Edita tus datos',
			),
			'login'              => array(
				'it' => 'Accedi',
				'en' => 'Log in',
				'pl' => 'Zaloguj się',
				'es' => 'Iniciar sesión',
			),
			'logout'             => array(
				'it' => 'Logout',
				'en' => 'Log out',
				'pl' => 'Wyloguj',
				'es' => 'Cerrar sesión',
			),
			'cast_staff'         => array(
				'it' => 'Cast Staff Lovrite',
				'en' => 'Lovrite Cast Staff',
				'pl' => 'Cast Staff Lovrite',
				'es' => 'Cast Staff Lovrite',
			),
			'as_user'            => array(
				'it' => 'Da utente',
				'en' => 'As learner',
				'pl' => 'Jako użytkownik',
				'es' => 'Como usuario',
			),
			'view_as_role'       => array(
				'it' => 'Visualizza storie da %s',
				'en' => 'View stories as %s',
				'pl' => 'Pokaż historie jako %s',
				'es' => 'Ver historias como %s',
			),
			'view_user_stories'  => array(
				'it' => 'Visualizza le storie da utente',
				'en' => 'View stories as learner',
				'pl' => 'Pokaż historie użytkownika',
				'es' => 'Ver historias como usuario',
			),
			'started'            => array(
				'it' => 'Iniziate',
				'en' => 'Started',
				'pl' => 'Rozpoczęte',
				'es' => 'Empezadas',
			),
			'completed'          => array(
				'it' => 'Completate',
				'en' => 'Completed',
				'pl' => 'Ukończone',
				'es' => 'Completadas',
			),
			'cast_stories'       => array(
				'it' => 'Storie del Cast',
				'en' => 'Cast stories',
				'pl' => 'Historie Cast',
				'es' => 'Historias del Cast',
			),
			'empty_pair'         => array(
				'it' => 'Nessuna storia in questa coppia.',
				'en' => 'No stories in this pair.',
				'pl' => 'Brak historii w tej parze.',
				'es' => 'No hay historias en esta pareja.',
			),
			'no_pairs'           => array(
				'it' => 'Ancora nessuna storia da mostrare.',
				'en' => 'No stories to show yet.',
				'pl' => 'Brak historii do wyświetlenia.',
				'es' => 'Aún no hay historias.',
			),
			'open_story'         => array(
				'it' => 'Vai alla storia',
				'en' => 'Go to story',
				'pl' => 'Przejdź do historii',
				'es' => 'Ir a la historia',
			),
			'close'              => array(
				'it' => 'Chiudi',
				'en' => 'Close',
				'pl' => 'Zamknij',
				'es' => 'Cerrar',
			),
			'photo'              => array(
				'it' => 'Foto',
				'en' => 'Photo',
				'pl' => 'Zdjęcie',
				'es' => 'Foto',
			),
			'change_avatar'      => array(
				'it' => 'Cambia Avatar',
				'en' => 'Change avatar',
				'pl' => 'Zmień awatar',
				'es' => 'Cambiar avatar',
			),
			'full_name'          => array(
				'it' => 'Nome e cognome',
				'en' => 'Full name',
				'pl' => 'Imię i nazwisko',
				'es' => 'Nombre y apellido',
			),
			'bio'                => array(
				'it' => 'Descrizione',
				'en' => 'Description',
				'pl' => 'Opis',
				'es' => 'Descripción',
			),
			'link'               => array(
				'it' => 'Link',
				'en' => 'Link',
				'pl' => 'Link',
				'es' => 'Enlace',
			),
			'save'               => array(
				'it' => 'Salva',
				'en' => 'Save',
				'pl' => 'Zapisz',
				'es' => 'Guardar',
			),
			'cancel'             => array(
				'it' => 'Annulla',
				'en' => 'Cancel',
				'pl' => 'Anuluj',
				'es' => 'Cancelar',
			),
			'saved'              => array(
				'it' => 'Info salvate.',
				'en' => 'Info saved.',
				'pl' => 'Zapisano dane.',
				'es' => 'Datos guardados.',
			),
			'error'              => array(
				'it' => 'Operazione non riuscita.',
				'en' => 'Something went wrong.',
				'pl' => 'Operacja nie powiodła się.',
				'es' => 'No se pudo completar.',
			),
			'loading'            => array(
				'it' => 'Caricamento…',
				'en' => 'Loading…',
				'pl' => 'Ładowanie…',
				'es' => 'Cargando…',
			),
			'photo_big'          => array(
				'it' => 'Foto troppo grande (max 3 MB).',
				'en' => 'Photo too large (max 3 MB).',
				'pl' => 'Zdjęcie za duże (maks. 3 MB).',
				'es' => 'Foto demasiado grande (máx. 3 MB).',
			),
			'photo_type'         => array(
				'it' => 'Usa un file JPG, PNG o WebP.',
				'en' => 'Use a JPG, PNG or WebP file.',
				'pl' => 'Użyj pliku JPG, PNG lub WebP.',
				'es' => 'Usa un archivo JPG, PNG o WebP.',
			),
		);
		if ( ! isset( $set[ $key ] ) ) {
			return $key;
		}
		return isset( $set[ $key ][ $ui ] ) ? $set[ $key ][ $ui ] : $set[ $key ]['it'];
	}
}
