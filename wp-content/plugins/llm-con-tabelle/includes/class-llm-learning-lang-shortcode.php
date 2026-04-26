<?php
/**
 * Shortcode: lingua che vuoi imparare (meta LEARNING_LANG) — vista + modifica AJAX.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_Learning_Lang_Shortcode
 *
 * Nota: [llm_user_learning_lang] è già usato per il chip nell’header.
 */
class LLM_Learning_Lang_Shortcode {

	const SHORTCODE = 'llm_learning_lang_settings';

	const AJAX_ACTION = 'llm_learning_lang_save';

	const NONCE_ACTION = 'llm_learning_lang_save';

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
		$ui_lang = LLM_User_Settings_I18n::lang();
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
			'llm-learning-lang',
			LLM_TABELLE_URL . 'assets/llm-learning-lang.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);

		wp_localize_script(
			'llm-learning-lang',
			'llmLearningLang',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'i18n'    => array(
					'saved'        => LLM_User_Settings_I18n::get( 'saved_learning_lang', $ui_lang ),
					'networkError' => LLM_User_Settings_I18n::get( 'network_error', $ui_lang ),
					'langInvalid'  => LLM_User_Settings_I18n::get( 'learning_lang_invalid', $ui_lang ),
					'genericError' => LLM_User_Settings_I18n::get( 'generic_error', $ui_lang ),
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
			return '<div class="llm-user-profile llm-user-profile--guest llm-learning-lang"><p class="llm-user-profile__guest-msg">' .
				esc_html( LLM_User_Settings_I18n::get( 'guest_set_learning', $ui_lang ) ) .
				'</p><p><a class="llm-user-profile__guest-link" href="' . $login_url . '">' .
				esc_html( LLM_User_Settings_I18n::get( 'go_login', $ui_lang ) ) . '</a></p></div>';
		}

		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return '';
		}

		$uid  = (int) $user->ID;
		$code = (string) get_user_meta( $uid, LLM_User_Meta::LEARNING_LANG, true );
		$code = sanitize_key( $code );

		if ( $code === '' || ! LLM_Languages::is_valid( $code ) ) {
			$view_label = LLM_User_Settings_I18n::get( 'not_set', $ui_lang );
			$sel_code   = '';
		} else {
			$view_label = LLM_User_Settings_I18n::language_label( $code, $ui_lang );
			$sel_code   = $code;
		}

		$lang_options = LLM_User_Settings_I18n::language_names( $ui_lang );
		$uid_attr     = 'llm-learn-lang-' . uniqid( '', false );

		ob_start();
		?>
		<div id="<?php echo esc_attr( $uid_attr ); ?>" class="llm-user-profile llm-learning-lang" data-llm-learning-lang>
			<div class="llm-user-profile__panel llm-user-profile__panel--view" data-llm-panel="view">
				<div class="llm-user-profile__list" role="list">
					<div class="llm-user-profile__row" role="listitem">
						<div class="llm-user-profile__dt"><?php echo esc_html( LLM_User_Settings_I18n::get( 'learning_lang_title', $ui_lang ) ); ?></div>
						<div class="llm-user-profile__dd"><span data-llm-field="learning_label"><?php echo esc_html( $view_label ); ?></span></div>
					</div>
				</div>
				<button type="button" class="llm-user-profile__btn llm-user-profile__btn--primary" data-llm-action="edit">
					<?php echo esc_html( LLM_User_Settings_I18n::get( 'edit', $ui_lang ) ); ?>
				</button>
			</div>

			<div class="llm-user-profile__panel llm-user-profile__panel--edit" data-llm-panel="edit" hidden>
				<form class="llm-user-profile__form" data-llm-learn-form novalidate>
					<div class="llm-user-profile__field">
						<label class="llm-user-profile__label" for="<?php echo esc_attr( $uid_attr ); ?>-learn"><?php echo esc_html( LLM_User_Settings_I18n::get( 'learning_lang_title', $ui_lang ) ); ?></label>
						<select class="llm-user-profile__input llm-user-profile__select" id="<?php echo esc_attr( $uid_attr ); ?>-learn" name="learning_lang" required>
							<option value="" disabled <?php echo $sel_code === '' ? 'selected' : ''; ?>><?php echo esc_html( LLM_User_Settings_I18n::get( 'choose_lang', $ui_lang ) ); ?></option>
							<?php foreach ( $lang_options as $opt_code => $lab ) : ?>
								<option value="<?php echo esc_attr( $opt_code ); ?>" <?php selected( $sel_code, $opt_code ); ?>><?php echo esc_html( $lab ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="llm-user-profile__actions">
						<button type="submit" class="llm-user-profile__btn llm-user-profile__btn--primary" data-llm-action="save">
							<?php echo esc_html( LLM_User_Settings_I18n::get( 'save', $ui_lang ) ); ?>
						</button>
						<button type="button" class="llm-user-profile__btn llm-user-profile__btn--ghost" data-llm-action="cancel">
							<?php echo esc_html( LLM_User_Settings_I18n::get( 'cancel', $ui_lang ) ); ?>
						</button>
					</div>
				</form>
			</div>

			<div class="llm-user-profile__message" data-llm-learn-message role="status" aria-live="polite"></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * AJAX salvataggio lingua di studio.
	 */
	public static function ajax_save() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => LLM_User_Settings_I18n::get( 'unauthorized' ) ), 403 );
		}

		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			wp_send_json_error( array( 'message' => LLM_User_Settings_I18n::get( 'invalid_user' ) ), 403 );
		}

		$uid = (int) $user->ID;
		$raw = isset( $_POST['learning_lang'] ) ? sanitize_key( wp_unslash( (string) $_POST['learning_lang'] ) ) : '';

		if ( ! LLM_Languages::is_valid( $raw ) ) {
			wp_send_json_error( array( 'code' => 'lang_invalid' ), 400 );
		}

		update_user_meta( $uid, LLM_User_Meta::LEARNING_LANG, $raw );

		wp_send_json_success(
			array(
				'code'  => $raw,
				'label' => LLM_User_Settings_I18n::language_label( $raw, LLM_User_Settings_I18n::lang_for_user( $uid ) ),
			)
		);
	}
}
