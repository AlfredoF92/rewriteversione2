<?php
/**
 * Shortcode form login: username + password.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Login_Form_Shortcode {

	const SHORTCODE    = 'llm_login_form';
	const NONCE_ACTION = 'llm_login_form';
	const NONCE_NAME   = 'llm_login_nonce';
	const POST_FLAG    = 'llm_login_submit';

	/** @var string|null */
	private static $last_error = null;

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'init', array( __CLASS__, 'maybe_process_login' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_logged_in_visitor' ), 5 );
	}

	/**
	 * Utente loggato su una pagina con [llm_login_form] → home per coppia linguistica.
	 */
	public static function maybe_redirect_logged_in_visitor() {
		if ( ! LLM_Redirects::enabled() ) {
			return;
		}

		if ( ! is_user_logged_in() || is_admin() ) {
			return;
		}
		if ( ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( ! self::page_has_login_shortcode( $post->ID ) ) {
			return;
		}
		wp_safe_redirect( self::redirect_url_for_current_user() );
		exit;
	}

	/**
	 * @param int $post_id ID pagina.
	 * @return bool
	 */
	private static function page_has_login_shortcode( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}
		if ( has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			return true;
		}
		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( is_string( $elementor_data ) && false !== strpos( $elementor_data, self::SHORTCODE ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Gestisce POST del form (prima dell'output).
	 */
	public static function maybe_process_login() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		if ( empty( $_POST[ self::POST_FLAG ] ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			self::$last_error = LLM_User_Settings_I18n::get( 'login_error_generic' );
			return;
		}
		if ( is_user_logged_in() ) {
			if ( ! LLM_Redirects::enabled() ) {
				return;
			}
			wp_safe_redirect( self::redirect_url_for_current_user() );
			exit;
		}

		$login = isset( $_POST['log'] ) ? sanitize_user( wp_unslash( $_POST['log'] ) ) : '';
		$pass  = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '';

		if ( '' === $login || '' === $pass ) {
			self::$last_error = LLM_User_Settings_I18n::get( 'login_error_empty' );
			return;
		}

		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $pass,
				'remember'      => ! empty( $_POST['rememberme'] ),
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			self::$last_error = $user->get_error_message();
			return;
		}

		$requested = isset( $_POST['redirect_to'] ) ? (string) wp_unslash( $_POST['redirect_to'] ) : '';
		$default   = self::redirect_url_for_user( $user );
		$dest      = class_exists( 'LLM_Frontend_Auth' )
			? LLM_Frontend_Auth::login_redirect( $default, $requested, $user )
			: $default;
		$dest = wp_validate_redirect( $dest, $default );

		wp_safe_redirect( $dest );
		exit;
	}

	/**
	 * @param array<string, string>|string $atts Attributi.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'redirect_path' => '',
			),
			$atts,
			self::SHORTCODE
		);

		wp_enqueue_style(
			'llm-user-profile',
			LLM_TABELLE_URL . 'assets/llm-user-profile.css',
			array(),
			LLM_TABELLE_VERSION
		);

		$lang = LLM_User_Settings_I18n::lang();

		if ( is_user_logged_in() ) {
			return self::render_continua_btn();
		}

		$error = self::$last_error;
		if ( null === $error && isset( $_GET['login'] ) && 'failed' === sanitize_key( wp_unslash( $_GET['login'] ) ) ) {
			$error = LLM_User_Settings_I18n::get( 'login_error_generic', $lang );
		}

		$redirect_hidden = '';
		$custom_path     = trim( (string) $atts['redirect_path'] );
		if ( '' !== $custom_path && class_exists( 'LLM_Frontend_Auth' ) ) {
			if ( '/' !== $custom_path[0] ) {
				$custom_path = '/' . $custom_path;
			}
			$redirect_hidden = '<input type="hidden" name="redirect_to" value="' . esc_attr( home_url( $custom_path ) ) . '" />';
		} elseif ( isset( $_GET['redirect_to'] ) ) {
			$from_query = (string) wp_unslash( $_GET['redirect_to'] );
			if ( '' !== $from_query && class_exists( 'LLM_Frontend_Auth' ) ) {
				$validated = wp_validate_redirect( $from_query, false );
				if ( $validated ) {
					$redirect_hidden = '<input type="hidden" name="redirect_to" value="' . esc_attr( $validated ) . '" />';
				}
			}
		}

		ob_start();
		?>
		<div class="llm-login-form llm-user-profile">
			<?php if ( $error ) : ?>
				<p class="llm-user-profile__message llm-user-profile__message--error" role="alert"><?php echo esc_html( $error ); ?></p>
			<?php endif; ?>
			<form class="llm-user-profile__form" method="post" action="">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<input type="hidden" name="<?php echo esc_attr( self::POST_FLAG ); ?>" value="1" />
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- costante markup.
				echo $redirect_hidden;
				?>
				<div class="llm-user-profile__field">
					<label class="llm-user-profile__label" for="llm-login-log"><?php echo esc_html( LLM_User_Settings_I18n::get( 'username', $lang ) ); ?></label>
					<input class="llm-user-profile__input" type="text" name="log" id="llm-login-log" autocomplete="username" required />
				</div>
				<div class="llm-user-profile__field">
					<label class="llm-user-profile__label" for="llm-login-pwd"><?php echo esc_html( LLM_User_Settings_I18n::get( 'password', $lang ) ); ?></label>
					<input class="llm-user-profile__input" type="password" name="pwd" id="llm-login-pwd" autocomplete="current-password" required />
				</div>
				<div class="llm-user-profile__field">
					<label class="llm-user-profile__label">
						<input type="checkbox" name="rememberme" value="forever" />
						<?php echo esc_html( LLM_User_Settings_I18n::get( 'login_remember', $lang ) ); ?>
					</label>
				</div>
				<div class="llm-user-profile__actions">
					<button type="submit" class="llm-user-profile__btn llm-user-profile__btn--primary"><?php echo esc_html( LLM_User_Settings_I18n::get( 'login_submit', $lang ) ); ?></button>
				</div>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Pulsante "Continua con le storie in IT → Polacco" per utente loggato.
	 *
	 * @return string
	 */
	private static function render_continua_btn() {
		wp_enqueue_style(
			'llm-lang-cards',
			LLM_TABELLE_URL . 'assets/llm-lang-cards.css',
			array(),
			LLM_TABELLE_VERSION
		);

		$uid       = get_current_user_id();
		$known     = sanitize_key( (string) get_user_meta( $uid, LLM_User_Meta::INTERFACE_LANG, true ) );
		$learning  = sanitize_key( (string) get_user_meta( $uid, LLM_User_Meta::LEARNING_LANG, true ) );

		$known_ok    = LLM_Languages::is_valid( $known );
		$learning_ok = LLM_Languages::is_valid( $learning );

		// Coppia configurata: pulsante cliccabile.
		if ( $known_ok && $learning_ok && $known !== $learning ) {
			$pair_url = class_exists( 'LLM_Home_Redirect' ) ? LLM_Home_Redirect::pair_url( $known, $learning ) : '';

			if ( '' !== $pair_url ) {
				$label = self::continua_label( $known, $learning );
				return self::render_continua_button_markup( $pair_url, $label );
			}
		}

		// Coppia non configurata: "Imposta la tua lingua" → sezione card in homepage.
		$settings_url = class_exists( 'LLM_Lang_Cards_Shortcode' )
			? LLM_Lang_Cards_Shortcode::section_url()
			: home_url( '/#llm-lang-cards' );
		$label        = self::imposta_label( $known );

		return self::render_continua_button_markup( $settings_url, $label );
	}

	/**
	 * Markup pulsante Continua (button, non link).
	 *
	 * @param string $url   Destinazione.
	 * @param string $label Testo pulsante.
	 * @return string
	 */
	private static function render_continua_button_markup( $url, $label ) {
		return sprintf(
			'<form class="llm-login-form llm-login-form--continua" action="%1$s" method="get">'
			. '<button type="submit" class="llm-lang-cards__card-btn">%2$s</button>'
			. '</form>',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	/**
	 * Testo del pulsante Continua in base alla coppia linguistica.
	 *
	 * @param string $known    Codice lingua conosciuta.
	 * @param string $learning Codice lingua da imparare.
	 * @return string
	 */
	private static function continua_label( $known, $learning ) {
		$known_upper    = strtoupper( $known );
		$learning_names = array(
			'it' => array( 'en' => 'Inglese', 'pl' => 'Polacco', 'es' => 'Spagnolo' ),
			'en' => array( 'it' => 'Italian', 'pl' => 'Polish',  'es' => 'Spanish'  ),
			'pl' => array( 'it' => 'Włoskiego', 'en' => 'Angielskiego', 'es' => 'Hiszpańskiego' ),
			'es' => array( 'it' => 'Italiano', 'en' => 'Inglés', 'pl' => 'Polaco'   ),
		);
		$templates = array(
			'it' => 'Continua con le storie in %s → %s',
			'en' => 'Continue with stories in %s → %s',
			'pl' => 'Kontynuuj z historiami w %s → %s',
			'es' => 'Continúa con las historias en %s → %s',
		);
		$learning_name = $learning_names[ $known ][ $learning ] ?? strtoupper( $learning );
		$template      = $templates[ $known ] ?? $templates['it'];

		return sprintf( $template, $known_upper, $learning_name );
	}

	/**
	 * Testo "Imposta la tua lingua" in base alla lingua conosciuta.
	 *
	 * @param string $known Codice lingua conosciuta.
	 * @return string
	 */
	private static function imposta_label( $known ) {
		$labels = array(
			'it' => 'Imposta la tua lingua →',
			'en' => 'Set your language →',
			'pl' => 'Ustaw swój język →',
			'es' => 'Configura tu idioma →',
		);
		return $labels[ $known ] ?? $labels['it'];
	}

	/**
	 * @param WP_User $user Utente autenticato.
	 * @return string URL.
	 */
	private static function redirect_url_for_user( $user ) {
		if ( class_exists( 'LLM_Frontend_Auth' ) ) {
			return LLM_Frontend_Auth::get_language_home_url( (int) $user->ID );
		}
		return home_url( '/' );
	}

	/**
	 * @return string URL.
	 */
	private static function redirect_url_for_current_user() {
		$user = wp_get_current_user();
		if ( $user && $user->exists() ) {
			return self::redirect_url_for_user( $user );
		}
		return home_url( '/' );
	}
}
