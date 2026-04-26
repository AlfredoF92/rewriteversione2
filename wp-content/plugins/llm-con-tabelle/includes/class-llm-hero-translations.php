<?php
/**
 * Testi Hero Homepage multilingua.
 *
 * Gestisce i testi della sezione hero (sopratitolo, titolo, sottotitolo) in 4 lingue.
 * Supporta il segnaposto {lingua-selezionata} sostituito a runtime con la lingua
 * di apprendimento dell'utente nella sua lingua interfaccia.
 *
 * I testi sono modificabili dall'admin: Storie → Testi Hero Homepage.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Hero_Translations {

	const OPTION_KEY = 'llm_hero_texts';
	const MENU_SLUG  = 'llm-hero-translations';

	/** Segnaposto sostituito con il nome della lingua target. */
	const PLACEHOLDER = '{lingua-selezionata}';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_page' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend' ) );
	}

	/* -----------------------------------------------------------------------
	 * ENQUEUE FRONTEND (typewriter JS + CSS per il cursore)
	 * ---------------------------------------------------------------------- */

	public static function enqueue_frontend() {
		wp_enqueue_style(
			'llm-hero',
			LLM_TABELLE_URL . 'assets/llm-hero.css',
			array(),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_script(
			'llm-hero',
			LLM_TABELLE_URL . 'assets/llm-hero.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
	}

	/* -----------------------------------------------------------------------
	 * TESTI DEFAULT (fallback se admin non ha ancora salvato nulla)
	 * ---------------------------------------------------------------------- */

	/**
	 * @return array<string, array<string, string>>
	 */
	public static function get_defaults() {
		return array(
			'it' => array(
				'badge'    => 'Impara {lingua-selezionata} una frase alla volta',
				'title'    => 'Bentornato in MinePhrases',
				'subtitle' => 'Imparare {lingua-selezionata} utilizzando MinePhrases significa imparare una nuova lingua traducendo frasi, analizzando l\'analisi grammaticale e ripetendo la frase con la giusta pronuncia. Hai tutto ciò che ti serve per imparare una nuova lingua.',
			),
			'en' => array(
				'badge'    => 'Learn {lingua-selezionata} one phrase at a time',
				'title'    => 'Welcome back to MinePhrases',
				'subtitle' => 'Learning {lingua-selezionata} with MinePhrases means learning a new language by translating phrases, analysing grammar, and repeating each sentence with the right pronunciation. You have everything you need to learn a new language.',
			),
			'pl' => array(
				'badge'    => 'Ucz sie {lingua-selezionata} zdanie po zdaniu',
				'title'    => 'Witaj ponownie w MinePhrases',
				'subtitle' => 'Nauka {lingua-selezionata} z MinePhrases to nauka nowego jezyka przez tlumaczenie zdan, analize gramatyki i powtarzanie zdan z wlasciwa wymowa. Masz wszystko, czego potrzebujesz, aby nauczyc sie nowego jezyka.',
			),
			'es' => array(
				'badge'    => 'Aprende {lingua-selezionata} una frase a la vez',
				'title'    => 'Bienvenido de nuevo a MinePhrases',
				'subtitle' => 'Aprender {lingua-selezionata} con MinePhrases significa aprender un nuevo idioma traduciendo frases, analizando la gramatica y repitiendo cada frase con la pronunciacion correcta. Tienes todo lo que necesitas para aprender un nuevo idioma.',
			),
		);
	}

	/**
	 * Recupera tutti i testi salvati, con fallback ai default per i campi vuoti.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function get_all() {
		$saved    = get_option( self::OPTION_KEY, array() );
		$saved    = is_array( $saved ) ? $saved : array();
		$defaults = self::get_defaults();
		foreach ( $defaults as $lang => $fields ) {
			foreach ( $fields as $key => $default_val ) {
				if ( empty( $saved[ $lang ][ $key ] ) ) {
					$saved[ $lang ][ $key ] = $default_val;
				}
			}
		}
		return $saved;
	}

	/* -----------------------------------------------------------------------
	 * HELPER PUBBLICO: testo per la lingua corrente dell'utente
	 * ---------------------------------------------------------------------- */

	/**
	 * Restituisce il testo hero nella lingua interfaccia dell'utente corrente,
	 * con {lingua-selezionata} sostituito.
	 *
	 * @param string $key 'badge' | 'title' | 'subtitle'
	 * @return string
	 */
	public static function get_text( $key ) {
		$lang = class_exists( 'LLM_Category_Translations' )
			? LLM_Category_Translations::current_user_lang()
			: 'it';

		$all  = self::get_all();
		$text = isset( $all[ $lang ][ $key ] ) ? (string) $all[ $lang ][ $key ] : '';

		// Fallback italiano
		if ( '' === $text && isset( $all['it'][ $key ] ) ) {
			$text = (string) $all['it'][ $key ];
		}

		// Sostituisci {lingua-selezionata}
		if ( false !== strpos( $text, self::PLACEHOLDER ) ) {
			$text = str_replace( self::PLACEHOLDER, self::get_target_lang_name( $lang ), $text );
		}

		return $text;
	}

	/**
	 * Restituisce il nome della lingua target nella lingua interfaccia $ui_lang.
	 * Es. ui_lang=pl, target=it → "włoskiego"
	 *
	 * @param string $ui_lang Codice lingua UI.
	 * @return string
	 */
	private static function get_target_lang_name( $ui_lang ) {
		// Nomi generici per utenti non loggati o senza lingua impostata
		$generic = array(
			'it' => 'una nuova lingua',
			'en' => 'a new language',
			'pl' => 'nowego jezyka',
			'es' => 'un nuevo idioma',
		);

		if ( ! is_user_logged_in() ) {
			return isset( $generic[ $ui_lang ] ) ? $generic[ $ui_lang ] : $generic['it'];
		}

		$uid         = get_current_user_id();
		$target_code = (string) get_user_meta( $uid, LLM_User_Meta::LEARNING_LANG, true );

		if ( '' === $target_code || ! class_exists( 'LLM_Phrase_Game_I18n' ) ) {
			return isset( $generic[ $ui_lang ] ) ? $generic[ $ui_lang ] : $generic['it'];
		}

		// LLM_Phrase_Game_I18n::target_lang_label_for_ui legge la lingua UI dall'utente in automatico
		$label = LLM_Phrase_Game_I18n::target_lang_label_for_ui( $target_code );
		return '' !== $label ? $label : ( isset( $generic[ $ui_lang ] ) ? $generic[ $ui_lang ] : $generic['it'] );
	}

	/* -----------------------------------------------------------------------
	 * PAGINA ADMIN
	 * ---------------------------------------------------------------------- */

	public static function add_admin_page() {
		add_submenu_page(
			'edit.php?post_type=' . LLM_STORY_CPT,
			__( 'Testi Hero Homepage', 'llm-con-tabelle' ),
			__( 'Testi Hero Homepage', 'llm-con-tabelle' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_admin_page' )
		);
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non hai i permessi necessari.', 'llm-con-tabelle' ) );
		}

		$langs = class_exists( 'LLM_Languages' ) ? LLM_Languages::get_codes() : array(
			'it' => 'Italian',
			'en' => 'English',
			'pl' => 'Polish',
			'es' => 'Spanish',
		);

		$fields = array(
			'badge'    => array(
				'label' => __( 'Sopratitolo (badge)', 'llm-con-tabelle' ),
				'type'  => 'text',
				'hint'  => __( 'Riga piccola sopra il titolo principale.', 'llm-con-tabelle' ),
			),
			'title'    => array(
				'label' => __( 'Titolo principale', 'llm-con-tabelle' ),
				'type'  => 'text',
				'hint'  => __( 'Il titolo grande. Animazione typewriter automatica in Elementor.', 'llm-con-tabelle' ),
			),
			'subtitle' => array(
				'label' => __( 'Sottotitolo / Descrizione', 'llm-con-tabelle' ),
				'type'  => 'textarea',
				'hint'  => __( 'Testo descrittivo sotto il titolo.', 'llm-con-tabelle' ),
			),
		);

		$saved_msg = false;

		// Salvataggio
		if (
			isset( $_POST['llm_hero_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['llm_hero_nonce'] ) ), 'llm_hero_save' ) &&
			current_user_can( 'manage_options' )
		) {
			$new_data = array();
			foreach ( array_keys( $langs ) as $lang_code ) {
				foreach ( array_keys( $fields ) as $field_key ) {
					$raw = isset( $_POST['llm_hero'][ $lang_code ][ $field_key ] )
						? wp_unslash( $_POST['llm_hero'][ $lang_code ][ $field_key ] )
						: '';
					$new_data[ $lang_code ][ $field_key ] = 'textarea' === $fields[ $field_key ]['type']
						? sanitize_textarea_field( $raw )
						: sanitize_text_field( $raw );
				}
			}
			update_option( self::OPTION_KEY, $new_data );
			$saved_msg = true;
		}

		$all = self::get_all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Testi Hero Homepage', 'llm-con-tabelle' ); ?></h1>

			<p>
				<?php esc_html_e( 'Inserisci i testi della sezione hero nella lingua desiderata.', 'llm-con-tabelle' ); ?>
				<br>
				<?php
				printf(
					/* translators: %s: placeholder */
					wp_kses( __( 'Usa il segnaposto <code>{lingua-selezionata}</code> per inserire automaticamente il nome della lingua che l\'utente sta imparando (es. "italiano", "inglese", "włoskiego").', 'llm-con-tabelle' ), array( 'code' => array() ) )
				);
				?>
			</p>

			<?php if ( $saved_msg ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Testi salvati.', 'llm-con-tabelle' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'llm_hero_save', 'llm_hero_nonce' ); ?>
				<style>
					.llm-hero-admin { border-collapse: collapse; width: 100%; margin-top: 1.2em; }
					.llm-hero-admin th,
					.llm-hero-admin td { padding: 10px 14px; border: 1px solid #c3c4c7; vertical-align: top; }
					.llm-hero-admin thead th { background: #f6f7f7; font-weight: 600; white-space: nowrap; }
					.llm-hero-admin tbody tr:nth-child(even) { background: #fafafa; }
					.llm-hero-admin td:first-child { font-weight: 600; white-space: nowrap; min-width: 160px; }
					.llm-hero-admin input[type="text"] { width: 100%; }
					.llm-hero-admin textarea { width: 100%; min-height: 90px; resize: vertical; }
					.llm-hero-admin .llm-hint { font-size: 11px; color: #646970; margin-top: 4px; }
					.llm-hero-placeholder-note { background: #fff8e1; border-left: 4px solid #f0b90b; padding: 8px 14px; margin: .8em 0 1.4em; font-size: 13px; }
				</style>

				<div class="llm-hero-placeholder-note">
					<strong><?php esc_html_e( 'Segnaposto disponibile:', 'llm-con-tabelle' ); ?></strong>
					<code>{lingua-selezionata}</code> —
					<?php esc_html_e( 'Viene sostituito con il nome della lingua che l\'utente sta imparando (nella sua lingua interfaccia).', 'llm-con-tabelle' ); ?>
					<br>
					<?php esc_html_e( 'Esempi: se l\'utente apprende italiano con interfaccia polacca → "włoskiego". Con interfaccia italiana → "italiano".', 'llm-con-tabelle' ); ?>
				</div>

				<table class="llm-hero-admin widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Campo', 'llm-con-tabelle' ); ?></th>
							<?php foreach ( $langs as $code => $label ) : ?>
								<th><?php echo esc_html( $label ); ?> <code style="font-weight:400"><?php echo esc_html( $code ); ?></code></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $fields as $field_key => $field_cfg ) : ?>
							<tr>
								<td>
									<?php echo esc_html( $field_cfg['label'] ); ?>
									<p class="llm-hint"><?php echo esc_html( $field_cfg['hint'] ); ?></p>
								</td>
								<?php foreach ( $langs as $lang_code => $lang_label ) : ?>
									<?php $val = isset( $all[ $lang_code ][ $field_key ] ) ? $all[ $lang_code ][ $field_key ] : ''; ?>
									<td>
										<?php if ( 'textarea' === $field_cfg['type'] ) : ?>
											<textarea
												name="<?php echo esc_attr( "llm_hero[{$lang_code}][{$field_key}]" ); ?>"
											><?php echo esc_textarea( $val ); ?></textarea>
										<?php else : ?>
											<input
												type="text"
												name="<?php echo esc_attr( "llm_hero[{$lang_code}][{$field_key}]" ); ?>"
												value="<?php echo esc_attr( $val ); ?>"
											/>
										<?php endif; ?>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Salva testi hero', 'llm-con-tabelle' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}
}
