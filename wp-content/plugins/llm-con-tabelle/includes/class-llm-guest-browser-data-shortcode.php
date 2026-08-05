<?php
/**
 * Shortcode [llm_guest_browser_data] — riepilogo dati guest salvati nel browser.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Guest_Browser_Data_Shortcode {

	const SHORTCODE = 'llm_guest_browser_data';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
	}

	/**
	 * @param array<string, string>|string $atts Attributi.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts( array(), $atts, self::SHORTCODE );

		wp_enqueue_style( 'llm-ui' );
		wp_enqueue_style(
			'llm-guest-browser-data',
			LLM_TABELLE_URL . 'assets/llm-guest-browser-data.css',
			array( 'llm-ui' ),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_style(
			'llm-user-profile',
			LLM_TABELLE_URL . 'assets/llm-user-profile.css',
			array(),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_style(
			'llm-lang-cards',
			LLM_TABELLE_URL . 'assets/llm-lang-cards.css',
			array(),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_script(
			'llm-guest-browser-store',
			LLM_TABELLE_URL . 'assets/llm-guest-browser-store.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
		wp_enqueue_script(
			'llm-guest-browser-data',
			LLM_TABELLE_URL . 'assets/llm-guest-browser-data.js',
			array( 'llm-guest-browser-store' ),
			LLM_TABELLE_VERSION,
			true
		);

		$lang = class_exists( 'LLM_Visitor_Lang' ) ? LLM_Visitor_Lang::known() : 'it';
		$i18n = self::i18n( $lang );
		$is_guest = ! is_user_logged_in();

		$display_name = '';
		if ( ! $is_guest ) {
			$user = wp_get_current_user();
			$display_name = trim( (string) $user->display_name );
			if ( '' === $display_name ) {
				$display_name = trim( (string) $user->user_login );
			}
		}

		wp_localize_script(
			'llm-guest-browser-data',
			'llmGuestBrowserData',
			array(
				'isGuest'     => $is_guest,
				'displayName' => $display_name,
				'i18n'        => $i18n,
			)
		);

		$login_html    = '';
		$continua_html = '';
		$logout_url    = '';
		if ( $is_guest && class_exists( 'LLM_Login_Form_Shortcode' ) ) {
			$login_html = (string) do_shortcode( '[' . LLM_Login_Form_Shortcode::SHORTCODE . ']' );
		} elseif ( ! $is_guest ) {
			if ( class_exists( 'LLM_Login_Form_Shortcode' ) ) {
				$continua_html = (string) do_shortcode( '[' . LLM_Login_Form_Shortcode::SHORTCODE . ']' );
			}
			$logout_url = wp_logout_url( home_url( '/' ) );
		}

		ob_start();
		?>
		<div class="llm-ui-scope llm-ui-scope--light llm-guest-browser-data" data-llm-guest-browser-data>
			<?php if ( ! $is_guest ) : ?>
				<div class="llm-guest-browser-data__account">
					<p class="llm-guest-browser-data__hello-line" data-llm-guest-hello>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: display name */
								$i18n['hello_name'],
								$display_name ? $display_name : $i18n['none']
							)
						);
						?>
					</p>
					<p class="llm-guest-browser-data__continue-label"><?php echo esc_html( $i18n['continue_label'] ); ?></p>
					<?php if ( '' !== $continua_html ) : ?>
						<div class="llm-guest-browser-data__continua">
							<?php echo $continua_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode già escapato. ?>
						</div>
					<?php endif; ?>
					<?php if ( '' !== $logout_url ) : ?>
						<p class="llm-guest-browser-data__logout-wrap">
							<a class="llm-user-profile__btn llm-user-profile__btn--ghost llm-guest-browser-data__logout" href="<?php echo esc_url( $logout_url ); ?>">
								<?php echo esc_html( $i18n['logout'] ); ?>
							</a>
						</p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<p class="llm-guest-browser-data__loading"><?php echo esc_html( $i18n['loading'] ); ?></p>
				<div class="llm-guest-browser-data__body" hidden></div>
				<?php if ( '' !== $login_html ) : ?>
					<div class="llm-guest-browser-data__login">
						<p class="llm-guest-browser-data__login-title"><?php echo esc_html( $i18n['login_title'] ); ?></p>
						<p class="llm-guest-browser-data__login-intro"><?php echo esc_html( $i18n['login_intro'] ); ?></p>
						<?php echo $login_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode già escapato. ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param string $lang Codice lingua UI.
	 * @return array<string, string>
	 */
	private static function i18n( $lang ) {
		$lang = sanitize_key( (string) $lang );
		$all  = array(
			'it' => array(
				'loading'          => 'Caricamento…',
				'logged_in_note'   => 'Disponibile solo da ospite.',
				'hello'            => 'Ciao',
				'hello_name'       => 'Ciao, %s',
				'name_placeholder' => 'Il tuo nome',
				'name_save'        => 'Salva',
				'name_saved'       => 'Salvato',
				'continue_label'   => 'Continua a giocare',
				'logout'           => 'Disconnetti',
				'stories_count'    => 'storie',
				'finished_count'   => 'finite',
				'phrases_done'     => 'frasi',
				'points'           => 'punti',
				'story'            => 'Storia',
				'phrase'           => 'Frase',
				'done'             => 'Done',
				'status'           => 'Stato',
				'finished'         => 'Finita',
				'in_progress'      => 'In corso',
				'crosswords_count' => 'cruciverba',
				'crossword'        => 'Cruciverba',
				'letters'          => 'Lettere',
				'solved'           => 'Risolto',
				'empty_stories'    => 'Nessuna storia ancora.',
				'none'             => '—',
				'bytes'            => 'B',
				'disclaimer'       => 'Questi dati restano solo nel tuo browser: se cancelli cache o cookie, li perdi. Lo spazio è limitato: hai usato circa %s. Nessun altro può vederli. Ogni browser ha il proprio archivio, quindi PC e telefono non sono sincronizzati. Per salvare i progressi in modo sicuro e su tutti i dispositivi, accedi con un account.',
				'login_title'      => 'Accedi al tuo account',
				'login_intro'      => 'Accedendo, i dati restano sincronizzati in un database personale su loveRewrite: così puoi continuare a giocare dal PC e dal telefono.',
			),
			'en' => array(
				'loading'          => 'Loading…',
				'logged_in_note'   => 'Guest only.',
				'hello'            => 'Hi',
				'hello_name'       => 'Hi, %s',
				'name_placeholder' => 'Your name',
				'name_save'        => 'Save',
				'name_saved'       => 'Saved',
				'continue_label'   => 'Keep playing',
				'logout'           => 'Sign out',
				'stories_count'    => 'stories',
				'finished_count'   => 'finished',
				'phrases_done'     => 'phrases',
				'points'           => 'points',
				'story'            => 'Story',
				'phrase'           => 'Phrase',
				'done'             => 'Done',
				'status'           => 'Status',
				'finished'         => 'Done',
				'in_progress'      => 'Open',
				'crosswords_count' => 'crosswords',
				'crossword'        => 'Crossword',
				'letters'          => 'Letters',
				'solved'           => 'Solved',
				'empty_stories'    => 'No stories yet.',
				'none'             => '—',
				'bytes'            => 'B',
				'disclaimer'       => 'This data stays only in your browser: if you clear cache or cookies, it is lost. Space is limited: you have used about %s. No one else can see it. Each browser has its own storage, so PC and phone are not synced. To keep your progress safe across devices, sign in with an account.',
				'login_title'      => 'Sign in to your account',
				'login_intro'      => 'When you sign in, your data stays synced in a personal loveRewrite database, so you can keep playing on PC and on mobile.',
			),
			'pl' => array(
				'loading'          => 'Ladowanie…',
				'logged_in_note'   => 'Tylko dla gosci.',
				'hello'            => 'Czesc',
				'hello_name'       => 'Czesc, %s',
				'name_placeholder' => 'Twoje imie',
				'name_save'        => 'Zapisz',
				'name_saved'       => 'Zapisano',
				'continue_label'   => 'Kontynuuj gre',
				'logout'           => 'Wyloguj',
				'stories_count'    => 'historie',
				'finished_count'   => 'ukonczone',
				'phrases_done'     => 'zdania',
				'points'           => 'punkty',
				'story'            => 'Historia',
				'phrase'           => 'Zdanie',
				'done'             => 'Done',
				'status'           => 'Status',
				'finished'         => 'Gotowe',
				'in_progress'      => 'W toku',
				'crosswords_count' => 'krzyzowki',
				'crossword'        => 'Krzyzowka',
				'letters'          => 'Litery',
				'solved'           => 'Rozwiazana',
				'empty_stories'    => 'Brak historii.',
				'none'             => '—',
				'bytes'            => 'B',
				'disclaimer'       => 'Te dane zostaja tylko w Twojej przegladarce: jesli wyczyscisz cache lub cookie, je stracisz. Miejsce jest ograniczone: zuzyles ok. %s. Nikt inny ich nie widzi. Kazda przegladarka ma wlasny magazyn, wiec komputer i telefon nie sa zsynchronizowane. Aby bezpiecznie zachowac postepy na wszystkich urzadzeniach, zaloguj sie na konto.',
				'login_title'      => 'Zaloguj sie na konto',
				'login_intro'      => 'Po zalogowaniu dane pozostaja zsynchronizowane w osobistej bazie loveRewrite, dzieki czemu mozesz grac dalej na komputerze i telefonie.',
			),
			'es' => array(
				'loading'          => 'Cargando…',
				'logged_in_note'   => 'Solo invitados.',
				'hello'            => 'Hola',
				'hello_name'       => 'Hola, %s',
				'name_placeholder' => 'Tu nombre',
				'name_save'        => 'Guardar',
				'name_saved'       => 'Guardado',
				'continue_label'   => 'Sigue jugando',
				'logout'           => 'Desconectar',
				'stories_count'    => 'historias',
				'finished_count'   => 'terminadas',
				'phrases_done'     => 'frases',
				'points'           => 'puntos',
				'story'            => 'Historia',
				'phrase'           => 'Frase',
				'done'             => 'Done',
				'status'           => 'Estado',
				'finished'         => 'Lista',
				'in_progress'      => 'En curso',
				'crosswords_count' => 'crucigramas',
				'crossword'        => 'Crucigrama',
				'letters'          => 'Letras',
				'solved'           => 'Resuelto',
				'empty_stories'    => 'Sin historias.',
				'none'             => '—',
				'bytes'            => 'B',
				'disclaimer'       => 'Estos datos solo estan en tu navegador: si borras cache o cookies, los pierdes. El espacio es limitado: has usado unos %s. Nadie mas puede verlos. Cada navegador tiene su propio archivo, asi que PC y movil no estan sincronizados. Para guardar el progreso de forma segura en todos tus dispositivos, inicia sesion con una cuenta.',
				'login_title'      => 'Accede a tu cuenta',
				'login_intro'      => 'Al iniciar sesion, tus datos quedan sincronizados en una base personal de loveRewrite: asi puedes seguir jugando desde el PC y el movil.',
			),
		);

		return isset( $all[ $lang ] ) ? $all[ $lang ] : $all['it'];
	}
}
