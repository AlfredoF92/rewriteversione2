<?php
/**
 * Shortcode [llm_lang_cards] — scelta lingua conosciuta + lingua da imparare.
 *
 * Funzionamento:
 * - Passo 1: chip/bandiere per la lingua che conosci (niente select).
 * - Passo 2: card per la lingua da imparare.
 * - Card con coppia configurata in llm_home_redirect_pairs → POST che:
 *     1. Salva _llm_interface_lang + _llm_learning_lang
 *     2. Salva cookie 30 giorni
 *     3. Redirect alla pagina della coppia
 * - Card senza coppia → badge "Coming Soon".
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Lang_Cards_Shortcode {

	const SHORTCODE         = 'llm_lang_cards';
	const NONCE_ACTION_LANG = 'llm_lang_cards_save';
	const NONCE_FIELD_LANG  = 'llm_lang_cards_nonce';
	const POST_FLAG_LANG    = 'llm_lang_cards_submit';

	const NONCE_ACTION_CARD = 'llm_lang_cards_card';
	const NONCE_FIELD_CARD  = 'llm_lang_cards_card_nonce';
	const POST_FLAG_CARD    = 'llm_lang_cards_card_submit';

	const COOKIE_KNOWN    = 'llm_interface_lang';
	const COOKIE_LEARNING = 'llm_learning_lang';
	const COOKIE_DAYS     = 30;

	/** ID sezione homepage per link anchor (es. [llm_login_form] "Imposta la tua lingua"). */
	const SECTION_ANCHOR = 'llm-lang-cards';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'init', array( __CLASS__, 'maybe_handle_form' ), 5 );
	}

	/* ------------------------------------------------------------------ */
	/* Form handler                                                         */
	/* ------------------------------------------------------------------ */

	public static function maybe_handle_form() {
		// Selettore lingua conosciuta.
		if ( ! empty( $_POST[ self::POST_FLAG_LANG ] ) ) {
			self::handle_known_lang_form();
			return;
		}

		// Bottone card (salva lingua conosciuta + lingua target → redirect alla pagina coppia).
		if ( ! empty( $_POST[ self::POST_FLAG_CARD ] ) ) {
			self::handle_card_form();
			return;
		}
	}

	/* ---- Handler: cambia lingua conosciuta ---- */
	private static function handle_known_lang_form() {
		if (
			! isset( $_POST[ self::NONCE_FIELD_LANG ] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD_LANG ] ) ),
				self::NONCE_ACTION_LANG
			)
		) {
			return;
		}

		$lang = sanitize_key( wp_unslash( $_POST['llm_known_lang'] ?? '' ) );
		if ( ! LLM_Languages::is_valid( $lang ) ) {
			return;
		}

		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), LLM_User_Meta::INTERFACE_LANG, $lang );
		}

		self::set_cookie( self::COOKIE_KNOWN, $lang );

		$redirect = isset( $_POST['_wp_http_referer'] )
			? wp_validate_redirect( wp_unslash( $_POST['_wp_http_referer'] ), home_url( '/' ) )
			: home_url( '/' );

		wp_safe_redirect( $redirect );
		exit;
	}

	/* ---- Handler: click bottone card (salva coppia + redirect) ---- */
	private static function handle_card_form() {
		if (
			! isset( $_POST[ self::NONCE_FIELD_CARD ] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD_CARD ] ) ),
				self::NONCE_ACTION_CARD
			)
		) {
			return;
		}

		$known    = sanitize_key( wp_unslash( $_POST['llm_known_lang'] ?? '' ) );
		$learning = sanitize_key( wp_unslash( $_POST['llm_learning_lang'] ?? '' ) );
		$dest     = wp_unslash( $_POST['llm_card_redirect'] ?? '' );

		if ( ! LLM_Languages::is_valid( $known ) || ! LLM_Languages::is_valid( $learning ) ) {
			return;
		}

		// Salva per utente loggato.
		if ( is_user_logged_in() ) {
			$uid = get_current_user_id();
			update_user_meta( $uid, LLM_User_Meta::INTERFACE_LANG, $known );
			update_user_meta( $uid, LLM_User_Meta::LEARNING_LANG, $learning );
		}

		// Salva cookie (loggati e ospiti).
		self::persist_pair_cookies( $known, $learning );

		// Redirect alla pagina della coppia.
		$redirect = ( '' !== $dest )
			? wp_validate_redirect( $dest, home_url( '/' ) )
			: home_url( '/' );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * URL homepage con anchor alla sezione card.
	 *
	 * @return string
	 */
	public static function section_url() {
		return home_url( '/' ) . '#' . self::SECTION_ANCHOR;
	}

	/* ------------------------------------------------------------------ */
	/* Render                                                               */
	/* ------------------------------------------------------------------ */

	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'subtitle' => '',
			),
			$atts,
			self::SHORTCODE
		);

		wp_enqueue_style(
			'llm-lang-cards',
			LLM_TABELLE_URL . 'assets/llm-lang-cards.css',
			array(),
			LLM_TABELLE_VERSION
		);

		$known_lang = self::get_current_lang();
		$all_langs  = LLM_Languages::get_codes();
		$pairs      = (array) get_option( LLM_Home_Redirect::OPT_PAIRS, array() );

		/* Attributo legacy: se passato, sostituisce il sottotitolo della sezione "imparare". */
		$learn_subtitle = trim( (string) $atts['subtitle'] );
		if ( '' === $learn_subtitle ) {
			$learn_subtitle = self::learn_subtitle( $known_lang );
		}

		ob_start();
		?>
		<div id="<?php echo esc_attr( self::SECTION_ANCHOR ); ?>" class="llm-lang-cards" data-known="<?php echo esc_attr( $known_lang ); ?>">

			<?php /* ---- Step 1: lingua che conosci ---- */ ?>
			<section class="llm-lang-cards__step llm-lang-cards__step--known" aria-labelledby="llm-lang-known-title">
				<div class="llm-lang-cards__header">
					<p class="llm-lang-cards__step-label"><?php echo esc_html( self::step_label( $known_lang, 1 ) ); ?></p>
					<h2 id="llm-lang-known-title" class="llm-lang-cards__title"><?php echo esc_html( self::known_title( $known_lang ) ); ?></h2>
					<p class="llm-lang-cards__subtitle"><?php echo esc_html( self::known_subtitle( $known_lang ) ); ?></p>
				</div>

				<div class="llm-lang-cards__pills" role="list">
					<?php foreach ( $all_langs as $code => $label ) : ?>
						<?php
						$is_active = ( $code === $known_lang );
						$pill_class = 'llm-lang-cards__pill';
						if ( $is_active ) {
							$pill_class .= ' llm-lang-cards__pill--active';
						}
						?>
						<div class="llm-lang-cards__pill-wrap" role="listitem">
							<?php if ( $is_active ) : ?>
								<span class="<?php echo esc_attr( $pill_class ); ?>" aria-current="true">
									<span class="llm-lang-cards__pill-flag" aria-hidden="true"><?php echo esc_html( self::flag_emoji( $code ) ); ?></span>
									<span class="llm-lang-cards__pill-name"><?php echo esc_html( self::lang_native_name( $code ) ); ?></span>
								</span>
							<?php else : ?>
								<form method="post" action="" class="llm-lang-cards__pill-form">
									<?php wp_nonce_field( self::NONCE_ACTION_LANG, self::NONCE_FIELD_LANG ); ?>
									<input type="hidden" name="<?php echo esc_attr( self::POST_FLAG_LANG ); ?>" value="1" />
									<input type="hidden" name="llm_known_lang" value="<?php echo esc_attr( $code ); ?>" />
									<?php echo wp_referer_field( false ); ?>
									<button type="submit" class="<?php echo esc_attr( $pill_class ); ?>">
										<span class="llm-lang-cards__pill-flag" aria-hidden="true"><?php echo esc_html( self::flag_emoji( $code ) ); ?></span>
										<span class="llm-lang-cards__pill-name"><?php echo esc_html( self::lang_native_name( $code ) ); ?></span>
									</button>
								</form>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</section>

			<?php /* ---- Step 2: lingua da imparare ---- */ ?>
			<section class="llm-lang-cards__step llm-lang-cards__step--learn" aria-labelledby="llm-lang-learn-title">
				<div class="llm-lang-cards__header">
					<p class="llm-lang-cards__step-label"><?php echo esc_html( self::step_label( $known_lang, 2 ) ); ?></p>
					<h2 id="llm-lang-learn-title" class="llm-lang-cards__title"><?php echo esc_html( self::learn_title( $known_lang ) ); ?></h2>
					<p class="llm-lang-cards__subtitle"><?php echo esc_html( $learn_subtitle ); ?></p>
				</div>

				<div class="llm-lang-cards__grid">
					<?php
					foreach ( $all_langs as $target_code => $target_label ) {
						if ( $target_code === $known_lang ) {
							continue;
						}

						$pair_key = $known_lang . '_' . $target_code;
						$page_id  = isset( $pairs[ $pair_key ] ) ? absint( $pairs[ $pair_key ] ) : 0;

						$pair_url = '';
						if ( $page_id > 0 ) {
							$page = get_post( $page_id );
							if ( $page && 'publish' === $page->post_status ) {
								$pair_url = (string) get_permalink( $page_id );
							}
						}

						$card_title = self::card_title( $known_lang, $target_code );
						$card_desc  = self::card_desc( $known_lang, $target_code );
						$btn_label  = self::card_btn( $known_lang, $target_code );
						$available  = '' !== $pair_url;
						?>
						<div class="llm-lang-cards__card<?php echo $available ? '' : ' llm-lang-cards__card--soon'; ?>">
							<div class="llm-lang-cards__card-flag">
								<?php echo self::lang_flag( $target_code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- emoji escapato. ?>
							</div>
							<h3 class="llm-lang-cards__card-title"><?php echo esc_html( $card_title ); ?></h3>
							<p class="llm-lang-cards__card-desc"><?php echo esc_html( $card_desc ); ?></p>
							<div class="llm-lang-cards__card-footer">
								<?php if ( $available ) : ?>
									<form method="post" action="">
										<?php wp_nonce_field( self::NONCE_ACTION_CARD, self::NONCE_FIELD_CARD ); ?>
										<input type="hidden" name="<?php echo esc_attr( self::POST_FLAG_CARD ); ?>" value="1" />
										<input type="hidden" name="llm_known_lang" value="<?php echo esc_attr( $known_lang ); ?>" />
										<input type="hidden" name="llm_learning_lang" value="<?php echo esc_attr( $target_code ); ?>" />
										<input type="hidden" name="llm_card_redirect" value="<?php echo esc_url( $pair_url ); ?>" />
										<button type="submit" class="llm-lang-cards__card-btn">
											<?php echo esc_html( $btn_label ); ?>
											<span class="llm-lang-cards__card-arrow" aria-hidden="true">→</span>
										</button>
									</form>
								<?php else : ?>
									<span class="llm-lang-cards__card-soon-badge">
										<?php echo esc_html( self::label_coming_soon( $known_lang ) ); ?>
									</span>
								<?php endif; ?>
							</div>
						</div>
						<?php
					}
					?>
				</div>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/* ------------------------------------------------------------------ */
	/* Utility                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Salva la coppia linguistica nei cookie (30 giorni).
	 * Usato anche da [llm_home_redirect] per ospiti e ritorno sul sito.
	 *
	 * @param string $known    Codice lingua conosciuta.
	 * @param string $learning Codice lingua da imparare.
	 */
	public static function persist_pair_cookies( $known, $learning ) {
		$known    = sanitize_key( (string) $known );
		$learning = sanitize_key( (string) $learning );
		if ( ! LLM_Languages::is_valid( $known ) || ! LLM_Languages::is_valid( $learning ) ) {
			return;
		}
		self::set_cookie( self::COOKIE_KNOWN, $known );
		self::set_cookie( self::COOKIE_LEARNING, $learning );
		$_COOKIE[ self::COOKIE_KNOWN ]    = $known;
		$_COOKIE[ self::COOKIE_LEARNING ] = $learning;
	}

	private static function set_cookie( $name, $value ) {
		if ( headers_sent() ) {
			return;
		}
		setcookie(
			$name,
			$value,
			time() + ( self::COOKIE_DAYS * DAY_IN_SECONDS ),
			COOKIEPATH ?: '/',
			COOKIE_DOMAIN ?: '',
			is_ssl(),
			true
		);
	}

	private static function get_current_lang() {
		if ( is_user_logged_in() ) {
			$lang = sanitize_key(
				(string) get_user_meta( get_current_user_id(), LLM_User_Meta::INTERFACE_LANG, true )
			);
			if ( LLM_Languages::is_valid( $lang ) ) {
				return $lang;
			}
		}

		$cookie = sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE_KNOWN ] ?? '' ) );
		if ( LLM_Languages::is_valid( $cookie ) ) {
			return $cookie;
		}

		return 'it';
	}

	/* ------------------------------------------------------------------ */
	/* Nomi nativi                                                          */
	/* ------------------------------------------------------------------ */

	private static function lang_native_name( $code ) {
		$names = array(
			'it' => 'Italiano',
			'en' => 'English',
			'pl' => 'Polski',
			'es' => 'Español',
		);
		return $names[ $code ] ?? $code;
	}

	/* ------------------------------------------------------------------ */
	/* Bandierine                                                           */
	/* ------------------------------------------------------------------ */

	private static function lang_flag( $code ) {
		$emoji = self::flag_emoji( $code );
		return '<span class="llm-lang-cards__flag" aria-hidden="true">' . esc_html( $emoji ) . '</span>';
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

	/* ------------------------------------------------------------------ */
	/* Traduzioni UI                                                        */
	/* ------------------------------------------------------------------ */

	private static function step_label( $lang, $n ) {
		$t = array(
			'it' => 'Passo %d',
			'en' => 'Step %d',
			'pl' => 'Krok %d',
			'es' => 'Paso %d',
		);
		$tpl = $t[ $lang ] ?? $t['it'];
		return sprintf( $tpl, (int) $n );
	}

	private static function known_title( $lang ) {
		$t = array(
			'it' => 'Lingua che conosci',
			'en' => 'Language you know',
			'pl' => 'Język, który znasz',
			'es' => 'Idioma que conoces',
		);
		return $t[ $lang ] ?? $t['it'];
	}

	private static function known_subtitle( $lang ) {
		$t = array(
			'it' => 'Tocca la lingua che parli già. L’interfaccia e le spiegazioni saranno in questa lingua.',
			'en' => 'Tap the language you already speak. The interface and explanations will use this language.',
			'pl' => 'Wybierz język, którym już mówisz. Interfejs i wyjaśnienia będą w tym języku.',
			'es' => 'Toca el idioma que ya hablas. La interfaz y las explicaciones estarán en este idioma.',
		);
		return $t[ $lang ] ?? $t['it'];
	}

	private static function learn_title( $lang ) {
		$t = array(
			'it' => 'Lingua che vuoi imparare',
			'en' => 'Language you want to learn',
			'pl' => 'Język, którego chcesz się uczyć',
			'es' => 'Idioma que quieres aprender',
		);
		return $t[ $lang ] ?? $t['it'];
	}

	private static function learn_subtitle( $lang ) {
		$t = array(
			'it' => 'Scegli cosa studiare: ti portiamo alle storie di quella lingua.',
			'en' => 'Choose what to study: we will take you to the stories for that language.',
			'pl' => 'Wybierz, czego chcesz się uczyć: przeniesiemy Cię do historii w tym języku.',
			'es' => 'Elige qué estudiar: te llevamos a las historias de ese idioma.',
		);
		return $t[ $lang ] ?? $t['it'];
	}

	private static function label_coming_soon( $lang ) {
		$t = array(
			'it' => 'Coming soon',
			'en' => 'Coming soon',
			'pl' => 'Wkrótce',
			'es' => 'Próximamente',
		);
		return $t[ $lang ] ?? $t['it'];
	}

	/* Alias legacy (filtri / riferimenti esterni). */
	private static function section_title( $known ) {
		return self::learn_title( $known );
	}

	private static function section_subtitle( $known ) {
		return self::learn_subtitle( $known );
	}

	private static function label_select( $lang ) {
		return self::known_title( $lang );
	}

	private static function label_confirm( $lang ) {
		unset( $lang );
		return '';
	}

	/* ------------------------------------------------------------------ */
	/* Testi card                                                           */
	/* ------------------------------------------------------------------ */

	private static function card_title( $known, $target ) {
		$t = array(
			'it' => array(
				'en' => 'Storie per imparare l\'inglese',
				'pl' => 'Storie per imparare il polacco',
				'es' => 'Storie per imparare lo spagnolo',
			),
			'en' => array(
				'it' => 'Stories to learn Italian',
				'pl' => 'Stories to learn Polish',
				'es' => 'Stories to learn Spanish',
			),
			'pl' => array(
				'it' => 'Historie, aby nauczyć się włoskiego',
				'en' => 'Historie, aby nauczyć się angielskiego',
				'es' => 'Historie, aby nauczyć się hiszpańskiego',
			),
			'es' => array(
				'it' => 'Historias para aprender italiano',
				'en' => 'Historias para aprender inglés',
				'pl' => 'Historias para aprender polaco',
			),
		);
		return $t[ $known ][ $target ] ?? self::lang_native_name( $target );
	}

	private static function card_desc( $known, $target ) {
		$t = array(
			'it' => array(
				'en' => 'Leggi storie brevi in inglese e allena la comprensione frase per frase.',
				'pl' => 'Leggi storie brevi in polacco e allena la comprensione frase per frase.',
				'es' => 'Leggi storie brevi in spagnolo e allena la comprensione frase per frase.',
			),
			'en' => array(
				'it' => 'Read short stories in Italian and train your comprehension phrase by phrase.',
				'pl' => 'Read short stories in Polish and train your comprehension phrase by phrase.',
				'es' => 'Read short stories in Spanish and train your comprehension phrase by phrase.',
			),
			'pl' => array(
				'it' => 'Czytaj krótkie historie po włosku i ćwicz rozumienie zdanie po zdaniu.',
				'en' => 'Czytaj krótkie historie po angielsku i ćwicz rozumienie zdanie po zdaniu.',
				'es' => 'Czytaj krótkie historie po hiszpańsku i ćwicz rozumienie zdanie po zdaniu.',
			),
			'es' => array(
				'it' => 'Lee historias cortas en italiano y entrena tu comprensión frase a frase.',
				'en' => 'Lee historias cortas en inglés y entrena tu comprensión frase a frase.',
				'pl' => 'Lee historias cortas en polaco y entrena tu comprensión frase a frase.',
			),
		);
		return $t[ $known ][ $target ] ?? '';
	}

	private static function card_btn( $known, $target ) {
		$t = array(
			'it' => array(
				'en' => 'Impara l\'inglese',
				'pl' => 'Impara il polacco',
				'es' => 'Impara lo spagnolo',
			),
			'en' => array(
				'it' => 'Learn Italian',
				'pl' => 'Learn Polish',
				'es' => 'Learn Spanish',
			),
			'pl' => array(
				'it' => 'Ucz się włoskiego',
				'en' => 'Ucz się angielskiego',
				'es' => 'Ucz się hiszpańskiego',
			),
			'es' => array(
				'it' => 'Aprende italiano',
				'en' => 'Aprende inglés',
				'pl' => 'Aprende polaco',
			),
		);
		return $t[ $known ][ $target ] ?? self::lang_native_name( $target );
	}
}
