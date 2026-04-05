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
					'saved'        => __( 'Lingua di studio aggiornata.', 'llm-con-tabelle' ),
					'networkError' => __( 'Errore di rete. Riprova.', 'llm-con-tabelle' ),
					'langInvalid'  => __( 'Seleziona una lingua valida.', 'llm-con-tabelle' ),
					'genericError' => __( 'Impossibile salvare. Riprova.', 'llm-con-tabelle' ),
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
				esc_html( __( 'Accedi per impostare la lingua che vuoi imparare.', 'llm-con-tabelle' ) ) .
				'</p><p><a class="llm-user-profile__guest-link" href="' . $login_url . '">' .
				esc_html( __( 'Vai al login', 'llm-con-tabelle' ) ) . '</a></p></div>';
		}

		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return '';
		}

		$uid  = (int) $user->ID;
		$code = (string) get_user_meta( $uid, LLM_User_Meta::LEARNING_LANG, true );
		$code = sanitize_key( $code );

		if ( $code === '' || ! LLM_Languages::is_valid( $code ) ) {
			$view_label = __( 'Non impostata', 'llm-con-tabelle' );
			$sel_code   = '';
		} else {
			$view_label = LLM_Languages::label( $code );
			$sel_code   = $code;
		}

		$lang_options = LLM_Languages::get_codes();
		$uid_attr     = 'llm-learn-lang-' . uniqid( '', false );

		ob_start();
		?>
		<div id="<?php echo esc_attr( $uid_attr ); ?>" class="llm-user-profile llm-learning-lang" data-llm-learning-lang>
			<div class="llm-user-profile__panel llm-user-profile__panel--view" data-llm-panel="view">
				<div class="llm-user-profile__list" role="list">
					<div class="llm-user-profile__row" role="listitem">
						<div class="llm-user-profile__dt"><?php echo esc_html( __( 'Lingua che vuoi imparare', 'llm-con-tabelle' ) ); ?></div>
						<div class="llm-user-profile__dd"><span data-llm-field="learning_label"><?php echo esc_html( $view_label ); ?></span></div>
					</div>
				</div>
				<button type="button" class="llm-user-profile__btn llm-user-profile__btn--primary" data-llm-action="edit">
					<?php echo esc_html( __( 'Modifica', 'llm-con-tabelle' ) ); ?>
				</button>
			</div>

			<div class="llm-user-profile__panel llm-user-profile__panel--edit" data-llm-panel="edit" hidden>
				<form class="llm-user-profile__form" data-llm-learn-form novalidate>
					<div class="llm-user-profile__field">
						<label class="llm-user-profile__label" for="<?php echo esc_attr( $uid_attr ); ?>-learn"><?php echo esc_html( __( 'Lingua che vuoi imparare', 'llm-con-tabelle' ) ); ?></label>
						<select class="llm-user-profile__input llm-user-profile__select" id="<?php echo esc_attr( $uid_attr ); ?>-learn" name="learning_lang" required>
							<option value="" disabled <?php echo $sel_code === '' ? 'selected' : ''; ?>><?php echo esc_html( __( 'Scegli una lingua…', 'llm-con-tabelle' ) ); ?></option>
							<?php foreach ( $lang_options as $opt_code => $lab ) : ?>
								<option value="<?php echo esc_attr( $opt_code ); ?>" <?php selected( $sel_code, $opt_code ); ?>><?php echo esc_html( $lab ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="llm-user-profile__actions">
						<button type="submit" class="llm-user-profile__btn llm-user-profile__btn--primary" data-llm-action="save">
							<?php echo esc_html( __( 'Salva', 'llm-con-tabelle' ) ); ?>
						</button>
						<button type="button" class="llm-user-profile__btn llm-user-profile__btn--ghost" data-llm-action="cancel">
							<?php echo esc_html( __( 'Annulla', 'llm-con-tabelle' ) ); ?>
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
			wp_send_json_error( array( 'message' => __( 'Non autorizzato.', 'llm-con-tabelle' ) ), 403 );
		}

		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			wp_send_json_error( array( 'message' => __( 'Utente non valido.', 'llm-con-tabelle' ) ), 403 );
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
				'label' => LLM_Languages::label( $raw ),
			)
		);
	}
}
