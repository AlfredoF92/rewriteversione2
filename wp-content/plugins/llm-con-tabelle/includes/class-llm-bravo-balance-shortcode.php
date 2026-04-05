<?php
/**
 * Shortcode: bilancio Bravi inviati / ricevuti + suggerimento.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_Bravo_Balance_Shortcode
 */
class LLM_Bravo_Balance_Shortcode {

	const SHORTCODE = 'llm_bravo_balance';

	/**
	 * Avvio.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
	}

	/**
	 * @param string $path Path relativo.
	 * @return string
	 */
	private static function normalize_path( $path ) {
		$path = trim( (string) $path );
		if ( $path === '' ) {
			return '/';
		}
		if ( $path[0] !== '/' ) {
			return '/' . $path;
		}
		return $path;
	}

	/**
	 * @param array<string, string>|string $atts Attributi.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'login_path'  => '/login',
				'login_label' => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE
		);

		wp_enqueue_style( 'llm-ui' );
		wp_enqueue_style(
			'llm-bravo-balance',
			LLM_TABELLE_URL . 'assets/llm-bravo-balance.css',
			array( 'llm-ui' ),
			LLM_TABELLE_VERSION
		);

		$login_path = self::normalize_path( (string) $atts['login_path'] );
		$login_url  = esc_url( home_url( $login_path ) );
		$login_lbl  = trim( (string) $atts['login_label'] );
		if ( $login_lbl === '' ) {
			$login_lbl = LLM_Community_Feed_I18n::get( 'bravo_balance_login' );
		}

		if ( ! is_user_logged_in() ) {
			ob_start();
			?>
			<div class="llm-ui-scope llm-ui-scope--light llm-bravo-balance">
				<p class="llm-ui-notice llm-bravo-balance__guest">
					<?php echo esc_html( LLM_Community_Feed_I18n::get( 'bravo_balance_guest' ) ); ?>
					<span class="llm-bravo-balance__guest-sep"> </span>
					<a class="llm-ui-link llm-bravo-balance__guest-link" href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html( $login_lbl ); ?></a>
				</p>
			</div>
			<?php
			return (string) ob_get_clean();
		}

		$uid      = get_current_user_id();
		$sent     = LLM_Community::count_bravi_given( $uid );
		$received = LLM_Community::count_bravi_received( $uid );

		ob_start();
		?>
		<div class="llm-ui-scope llm-ui-scope--light llm-bravo-balance">
			<div class="llm-ui-card llm-bravo-balance__card">
				<div class="llm-ui-card__body llm-bravo-balance__body">
					<div class="llm-bravo-balance__row">
						<span class="llm-bravo-balance__label"><?php echo esc_html( LLM_Community_Feed_I18n::get( 'bravo_balance_sent' ) ); ?></span>
						<span class="llm-bravo-balance__value"><?php echo esc_html( (string) (int) $sent ); ?></span>
					</div>
					<div class="llm-bravo-balance__row">
						<span class="llm-bravo-balance__label"><?php echo esc_html( LLM_Community_Feed_I18n::get( 'bravo_balance_received' ) ); ?></span>
						<span class="llm-bravo-balance__value"><?php echo esc_html( (string) (int) $received ); ?></span>
					</div>
					<p class="llm-bravo-balance__tip llm-ui-text--small llm-ui-text--muted"><?php echo esc_html( LLM_Community_Feed_I18n::get( 'bravo_balance_tip' ) ); ?></p>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
