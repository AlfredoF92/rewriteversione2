<?php
/**
 * Area personale: shortcode impostazioni utente + salvataggio AJAX.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_User_Profile_Shortcode
 */
class LLM_User_Profile_Shortcode {

	const SHORTCODE = 'llm_user_profile';

	const AJAX_ACTION = 'llm_profile_save';

	const NONCE_ACTION = 'llm_profile_save';

	/**
	 * Avvio.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_save' ) );
	}

	/**
	 * @param array<string, string>|string $atts Attributi.
	 * @return string
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'login_path' => '/login',
			),
			$atts,
			self::SHORTCODE
		);

		wp_enqueue_style(
			'llm-user-profile-fonts',
			'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap',
			array(),
			null
		);
		wp_enqueue_style(
			'llm-user-profile',
			LLM_TABELLE_URL . 'assets/llm-user-profile.css',
			array( 'llm-user-profile-fonts' ),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_script(
			'llm-user-profile',
			LLM_TABELLE_URL . 'assets/llm-user-profile.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);

		wp_localize_script(
			'llm-user-profile',
			'llmUserProfile',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'i18n'    => array(
					'saved'           => __( 'Impostazioni salvate.', 'llm-con-tabelle' ),
					'networkError'    => __( 'Errore di rete. Riprova.', 'llm-con-tabelle' ),
					'invalidEmail'    => __( 'Indirizzo email non valido.', 'llm-con-tabelle' ),
					'emailInUse'      => __( 'Questa email è già usata da un altro account.', 'llm-con-tabelle' ),
					'passwordMismatch' => __( 'Le nuove password non coincidono.', 'llm-con-tabelle' ),
					'passwordShort'   => __( 'La nuova password è troppo corta (minimo 8 caratteri).', 'llm-con-tabelle' ),
					'passwordWrong'   => __( 'Password attuale non corretta.', 'llm-con-tabelle' ),
					'passwordNeedOld' => __( 'Per impostare una nuova password inserisci anche la password attuale.', 'llm-con-tabelle' ),
					'langInvalid'     => __( 'Lingua interfaccia non valida.', 'llm-con-tabelle' ),
					'genericError'    => __( 'Impossibile salvare. Riprova.', 'llm-con-tabelle' ),
				),
			)
		);

		if ( ! is_user_logged_in() ) {
			$path = trim( (string) $atts['login_path'] );
			if ( $path === '' ) {
				$path = '/login';
			}
			if ( isset( $path[0] ) && $path[0] !== '/' ) {
				$path = '/' . $path;
			}
			$login_url = esc_url( home_url( $path ) );
			return '<div class="llm-user-profile llm-user-profile--guest"><p class="llm-user-profile__guest-msg">' .
				esc_html( __( 'Accedi per gestire il tuo profilo.', 'llm-con-tabelle' ) ) .
				'</p><p><a class="llm-user-profile__guest-link" href="' . $login_url . '">' .
				esc_html( __( 'Vai al login', 'llm-con-tabelle' ) ) . '</a></p></div>';
		}

		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return '';
		}

		$uid            = (int) $user->ID;
		$username       = (string) $user->user_login;
		$email          = (string) $user->user_email;
		$interface_code = (string) get_user_meta( $uid, LLM_User_Meta::INTERFACE_LANG, true );
		$interface_code = sanitize_key( $interface_code );
		if ( $interface_code === '' || ! LLM_Languages::is_valid( $interface_code ) ) {
			$interface_code = 'it';
		}
		$interface_label = LLM_Languages::label( $interface_code );

		$lang_options = LLM_Languages::get_codes();
		$uid_attr     = 'llm-profile-' . uniqid( '', false );

		ob_start();
		?>
		<div id="<?php echo esc_attr( $uid_attr ); ?>" class="llm-user-profile" data-llm-profile>
			<div class="llm-user-profile__panel llm-user-profile__panel--view" data-panel="view">
				<div class="llm-user-profile__list" role="list">
					<div class="llm-user-profile__row" role="listitem">
						<div class="llm-user-profile__dt"><?php echo esc_html( __( 'Username', 'llm-con-tabelle' ) ); ?></div>
						<div class="llm-user-profile__dd"><span data-field="username"><?php echo esc_html( $username ); ?></span></div>
					</div>
					<div class="llm-user-profile__row" role="listitem">
						<div class="llm-user-profile__dt"><?php echo esc_html( __( 'Email', 'llm-con-tabelle' ) ); ?></div>
						<div class="llm-user-profile__dd"><span data-field="email"><?php echo esc_html( $email ); ?></span></div>
					</div>
					<div class="llm-user-profile__row" role="listitem">
						<div class="llm-user-profile__dt"><?php echo esc_html( __( 'Password', 'llm-con-tabelle' ) ); ?></div>
						<div class="llm-user-profile__dd"><span data-field="password-mask"><?php echo esc_html( __( '••••••••', 'llm-con-tabelle' ) ); ?></span></div>
					</div>
					<div class="llm-user-profile__row" role="listitem">
						<div class="llm-user-profile__dt"><?php echo esc_html( __( 'Lingua che conosci (interfaccia)', 'llm-con-tabelle' ) ); ?></div>
						<div class="llm-user-profile__dd"><span data-field="interface_label"><?php echo esc_html( $interface_label ); ?></span></div>
					</div>
				</div>
				<p class="llm-user-profile__hint"><?php echo esc_html( __( 'Lo username non è modificabile.', 'llm-con-tabelle' ) ); ?></p>
				<button type="button" class="llm-user-profile__btn llm-user-profile__btn--primary" data-action="edit">
					<?php echo esc_html( __( 'Modifica', 'llm-con-tabelle' ) ); ?>
				</button>
			</div>

			<div class="llm-user-profile__panel llm-user-profile__panel--edit" data-panel="edit" hidden>
				<form class="llm-user-profile__form" data-profile-form novalidate>
					<div class="llm-user-profile__field">
						<label class="llm-user-profile__label"><?php echo esc_html( __( 'Username', 'llm-con-tabelle' ) ); ?></label>
						<input type="text" class="llm-user-profile__input" value="<?php echo esc_attr( $username ); ?>" readonly autocomplete="username">
					</div>
					<div class="llm-user-profile__field">
						<label class="llm-user-profile__label" for="<?php echo esc_attr( $uid_attr ); ?>-email"><?php echo esc_html( __( 'Email', 'llm-con-tabelle' ) ); ?></label>
						<input type="email" class="llm-user-profile__input" id="<?php echo esc_attr( $uid_attr ); ?>-email" name="user_email" value="<?php echo esc_attr( $email ); ?>" required autocomplete="email">
					</div>
					<div class="llm-user-profile__field">
						<label class="llm-user-profile__label" for="<?php echo esc_attr( $uid_attr ); ?>-old-pass"><?php echo esc_html( __( 'Password attuale', 'llm-con-tabelle' ) ); ?></label>
						<input type="password" class="llm-user-profile__input" id="<?php echo esc_attr( $uid_attr ); ?>-old-pass" name="old_password" value="" autocomplete="current-password">
						<p class="llm-user-profile__field-hint"><?php echo esc_html( __( 'Obbligatoria solo se cambi password.', 'llm-con-tabelle' ) ); ?></p>
					</div>
					<div class="llm-user-profile__field">
						<label class="llm-user-profile__label" for="<?php echo esc_attr( $uid_attr ); ?>-new-pass"><?php echo esc_html( __( 'Nuova password', 'llm-con-tabelle' ) ); ?></label>
						<input type="password" class="llm-user-profile__input" id="<?php echo esc_attr( $uid_attr ); ?>-new-pass" name="new_password" value="" autocomplete="new-password" minlength="8">
					</div>
					<div class="llm-user-profile__field">
						<label class="llm-user-profile__label" for="<?php echo esc_attr( $uid_attr ); ?>-new-pass2"><?php echo esc_html( __( 'Ripeti nuova password', 'llm-con-tabelle' ) ); ?></label>
						<input type="password" class="llm-user-profile__input" id="<?php echo esc_attr( $uid_attr ); ?>-new-pass2" name="new_password_confirm" value="" autocomplete="new-password" minlength="8">
					</div>
					<div class="llm-user-profile__field">
						<label class="llm-user-profile__label" for="<?php echo esc_attr( $uid_attr ); ?>-iface"><?php echo esc_html( __( 'Lingua che conosci (interfaccia)', 'llm-con-tabelle' ) ); ?></label>
						<select class="llm-user-profile__input llm-user-profile__select" id="<?php echo esc_attr( $uid_attr ); ?>-iface" name="interface_lang">
							<?php foreach ( $lang_options as $code => $lab ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $interface_code, $code ); ?>><?php echo esc_html( $lab ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="llm-user-profile__actions">
						<button type="submit" class="llm-user-profile__btn llm-user-profile__btn--primary" data-action="save">
							<?php echo esc_html( __( 'Salva', 'llm-con-tabelle' ) ); ?>
						</button>
						<button type="button" class="llm-user-profile__btn llm-user-profile__btn--ghost" data-action="cancel">
							<?php echo esc_html( __( 'Annulla', 'llm-con-tabelle' ) ); ?>
						</button>
					</div>
				</form>
			</div>

			<div class="llm-user-profile__message" data-profile-message role="status" aria-live="polite"></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * AJAX salvataggio profilo.
	 */
	public static function ajax_save() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Non autorizzato.', 'llm-con-tabelle' ) ), 403 );
		}

		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			wp_send_json_error( array( 'message' => __( 'Utente non valido.', 'llm-con-tabelle' ) ), 403 );
		}

		$uid = (int) $user->ID;

		$email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['user_email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'code' => 'invalid_email' ), 400 );
		}

		$other = email_exists( $email );
		if ( $other && (int) $other !== $uid ) {
			wp_send_json_error( array( 'code' => 'email_in_use' ), 400 );
		}

		$interface = isset( $_POST['interface_lang'] ) ? sanitize_key( wp_unslash( (string) $_POST['interface_lang'] ) ) : '';
		if ( ! LLM_Languages::is_valid( $interface ) ) {
			wp_send_json_error( array( 'code' => 'lang_invalid' ), 400 );
		}

		$new_pass = isset( $_POST['new_password'] ) ? (string) wp_unslash( $_POST['new_password'] ) : '';
		$new_cfm  = isset( $_POST['new_password_confirm'] ) ? (string) wp_unslash( $_POST['new_password_confirm'] ) : '';
		$old_pass = isset( $_POST['old_password'] ) ? (string) wp_unslash( $_POST['old_password'] ) : '';

		$new_pass = trim( $new_pass );
		$new_cfm  = trim( $new_cfm );
		$old_pass = (string) $old_pass;

		if ( $new_pass !== '' || $new_cfm !== '' ) {
			if ( $new_pass !== $new_cfm ) {
				wp_send_json_error( array( 'code' => 'password_mismatch' ), 400 );
			}
			$min = (int) apply_filters( 'llm_profile_password_min_length', 8 );
			if ( strlen( $new_pass ) < $min ) {
				wp_send_json_error( array( 'code' => 'password_short' ), 400 );
			}
			if ( $old_pass === '' || ! wp_check_password( $old_pass, $user->user_pass, $uid ) ) {
				wp_send_json_error( array( 'code' => 'password_wrong' ), 400 );
			}
		}

		$update = array(
			'ID'         => $uid,
			'user_email' => $email,
		);
		if ( $new_pass !== '' ) {
			$update['user_pass'] = $new_pass;
		}

		$result = wp_update_user( $update );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'code'    => 'wp_error',
					'message' => $result->get_error_message(),
				),
				400
			);
		}

		update_user_meta( $uid, LLM_User_Meta::INTERFACE_LANG, $interface );

		wp_send_json_success(
			array(
				'email'           => $email,
				'interface_code'  => $interface,
				'interface_label' => LLM_Languages::label( $interface ),
				'passwordChanged' => ( $new_pass !== '' ),
			)
		);
	}
}
