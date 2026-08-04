<?php
/**
 * Shortcode [llm_change_lang] — “Stai imparando …” + bandiera + link a /language-to-learn/.
 *
 * Uso: [llm_change_lang]
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Change_Lang_Shortcode {

	const SHORTCODE = 'llm_change_lang';

	const DEFAULT_PATH = '/language-to-learn/';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
	}

	/**
	 * @param array<string, string>|string $atts Attributi.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'path' => self::DEFAULT_PATH,
			),
			$atts,
			self::SHORTCODE
		);

		wp_enqueue_style(
			'llm-change-lang',
			LLM_TABELLE_URL . 'assets/llm-change-lang.css',
			array(),
			LLM_TABELLE_VERSION
		);

		$path = trim( (string) $atts['path'] );
		if ( '' === $path ) {
			$path = self::DEFAULT_PATH;
		}
		if ( '/' !== $path[0] ) {
			$path = '/' . $path;
		}
		$url = home_url( $path );

		$learning = self::current_learning_lang();
		$ui_lang  = self::ui_lang();

		$flag  = self::flag_emoji( $learning );
		$label = self::message( $learning, $ui_lang );
		$btn   = self::button_label( $ui_lang );

		ob_start();
		?>
		<div class="llm-change-lang">
			<span class="llm-change-lang__text">
				<?php if ( $flag ) : ?>
					<span class="llm-change-lang__flag" aria-hidden="true"><?php echo esc_html( $flag ); ?></span>
				<?php endif; ?>
				<span class="llm-change-lang__msg"><?php echo esc_html( $label ); ?></span>
			</span>
			<a class="llm-change-lang__btn" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $btn ); ?></a>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @return string Codice lingua target o ''.
	 */
	private static function current_learning_lang() {
		if ( is_user_logged_in() ) {
			$code = sanitize_key( (string) get_user_meta( get_current_user_id(), LLM_User_Meta::LEARNING_LANG, true ) );
			if ( LLM_Languages::is_valid( $code ) ) {
				return $code;
			}
		}

		$code = sanitize_key( wp_unslash( $_COOKIE['llm_learning_lang'] ?? '' ) );
		return LLM_Languages::is_valid( $code ) ? $code : '';
	}

	/**
	 * @return string
	 */
	private static function ui_lang() {
		if ( class_exists( 'LLM_Category_Translations' ) ) {
			$lang = LLM_Category_Translations::current_user_lang();
			if ( is_string( $lang ) && '' !== $lang ) {
				return $lang;
			}
		}
		if ( is_user_logged_in() ) {
			$known = sanitize_key( (string) get_user_meta( get_current_user_id(), LLM_User_Meta::INTERFACE_LANG, true ) );
			if ( LLM_Languages::is_valid( $known ) ) {
				return $known;
			}
		}
		$cookie = sanitize_key( wp_unslash( $_COOKIE['llm_interface_lang'] ?? '' ) );
		return LLM_Languages::is_valid( $cookie ) ? $cookie : 'it';
	}

	/**
	 * @param string $code Codice lingua.
	 * @return string
	 */
	private static function flag_emoji( $code ) {
		$flags = array(
			'it' => '🇮🇹',
			'en' => '🇬🇧',
			'pl' => '🇵🇱',
			'es' => '🇪🇸',
		);
		return $flags[ $code ] ?? '';
	}

	/**
	 * Nome lingua con articolo (nella UI corrente).
	 *
	 * @param string $code    Lingua target.
	 * @param string $ui_lang Lingua interfaccia.
	 * @return string
	 */
	private static function learning_name( $code, $ui_lang ) {
		$names = array(
			'it' => array(
				'en' => "l'inglese",
				'it' => "l'italiano",
				'pl' => 'il polacco',
				'es' => 'lo spagnolo',
			),
			'en' => array(
				'en' => 'English',
				'it' => 'Italian',
				'pl' => 'Polish',
				'es' => 'Spanish',
			),
			'pl' => array(
				'en' => 'angielski',
				'it' => 'włoski',
				'pl' => 'polski',
				'es' => 'hiszpański',
			),
			'es' => array(
				'en' => 'inglés',
				'it' => 'italiano',
				'pl' => 'polaco',
				'es' => 'español',
			),
		);
		$ui = isset( $names[ $ui_lang ] ) ? $ui_lang : 'it';
		return $names[ $ui ][ $code ] ?? ( class_exists( 'LLM_Languages' ) ? LLM_Languages::label( $code ) : $code );
	}

	/**
	 * @param string $learning Codice o ''.
	 * @param string $ui_lang  UI.
	 * @return string
	 */
	private static function message( $learning, $ui_lang ) {
		if ( '' === $learning ) {
			$empty = array(
				'it' => 'Nessuna lingua da imparare impostata',
				'en' => 'No learning language set',
				'pl' => 'Nie ustawiono języka do nauki',
				'es' => 'No hay idioma de aprendizaje configurado',
			);
			return $empty[ $ui_lang ] ?? $empty['it'];
		}

		$name = self::learning_name( $learning, $ui_lang );
		$tpl  = array(
			'it' => 'Storie per imparare %s',
			'en' => 'Stories to learn %s',
			'pl' => 'Historie, aby uczyć się: %s',
			'es' => 'Historias para aprender %s',
		);
		$template = $tpl[ $ui_lang ] ?? $tpl['it'];
		return sprintf( $template, $name );
	}

	/**
	 * @param string $ui_lang UI.
	 * @return string
	 */
	private static function button_label( $ui_lang ) {
		$labels = array(
			'it' => 'cambia lingua',
			'en' => 'change language',
			'pl' => 'zmień język',
			'es' => 'cambiar idioma',
		);
		return $labels[ $ui_lang ] ?? $labels['it'];
	}
}
