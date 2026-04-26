<?php
/**
 * Traduzioni manuali dei nomi di categoria per lingua interfaccia utente.
 *
 * - Aggiunge campi di traduzione nella pagina modifica-categoria di WordPress.
 * - Aggiunge una pagina admin riepilogativa (tabella tutte le categorie × lingue).
 * - Espone il metodo statico get_translated_name() usato dagli shortcode filtri.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Category_Translations {

	/** Prefisso chiave term meta. Es: _llm_cat_name_pl */
	const META_PREFIX = '_llm_cat_name_';

	/** Slug pagina admin. */
	const MENU_SLUG = 'llm-category-translations';

	public static function init() {
		// Campi nella pagina "Modifica categoria".
		add_action( 'category_edit_form_fields', array( __CLASS__, 'render_edit_fields' ), 10, 1 );
		add_action( 'edited_category', array( __CLASS__, 'save_edit_fields' ), 10, 1 );

		// Pagina admin riepilogativa.
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_page' ) );
	}

	/* -----------------------------------------------------------------------
	 * HELPER PUBBLICO: restituisce il nome tradotto (o il nome WordPress).
	 * ---------------------------------------------------------------------- */

	/**
	 * Restituisce il nome della categoria tradotto nella lingua $lang.
	 * Se la traduzione non è stata inserita, cade back sul nome WordPress nativo.
	 *
	 * @param WP_Term $term Oggetto termine categoria.
	 * @param string  $lang Codice lingua (it|en|pl|es).
	 * @return string
	 */
	public static function get_translated_name( WP_Term $term, $lang ) {
		$lang = sanitize_key( (string) $lang );
		if ( '' === $lang ) {
			return $term->name;
		}
		$val = (string) get_term_meta( $term->term_id, self::META_PREFIX . $lang, true );
		if ( '' !== trim( $val ) ) {
			return trim( $val );
		}
		return $term->name;
	}

	/**
	 * Restituisce la lingua interfaccia dell'utente corrente (default: 'it').
	 *
	 * @return string
	 */
	public static function current_user_lang() {
		if ( is_user_logged_in() ) {
			$lang = sanitize_key( (string) get_user_meta( get_current_user_id(), LLM_User_Meta::INTERFACE_LANG, true ) );
			if ( '' !== $lang && class_exists( 'LLM_Languages' ) && LLM_Languages::is_valid( $lang ) ) {
				return $lang;
			}
		}
		return 'it';
	}

	/**
	 * Etichette UI comuni tradotte per lingua interfaccia.
	 *
	 * @param string $lang Codice lingua.
	 * @return array<string,string>
	 */
	public static function ui_labels( $lang ) {
		$lang = sanitize_key( (string) $lang );
		$set  = array(
			'it' => array(
				'all'                 => 'Tutte',
				'all_stories'         => 'Tutte le storie',
				'in_progress'         => 'In corso',
				'to_continue'         => 'Da continuare',
				'completed'           => 'Completate',
				'filter_category'     => 'Filtra per categoria',
				'filter_reading'      => 'Filtra per stato lettura',
				'login_for_filter'    => 'Accedi per filtrare per stato lettura',
				'login_for_stories'   => 'Accedi per vedere le tue storie.',
				'no_started_stories'  => 'Non hai ancora iniziato nessuna storia.',
				'missing_learning'    => 'Nessuna lingua di studio impostata: vengono mostrate tutte le storie. Vai al tuo profilo per scegliere la lingua.',
			),
			'en' => array(
				'all'                 => 'All',
				'all_stories'         => 'All stories',
				'in_progress'         => 'In progress',
				'to_continue'         => 'Continue',
				'completed'           => 'Completed',
				'filter_category'     => 'Filter by category',
				'filter_reading'      => 'Filter by reading status',
				'login_for_filter'    => 'Log in to filter by reading status',
				'login_for_stories'   => 'Log in to see your stories.',
				'no_started_stories'  => 'You have not started any stories yet.',
				'missing_learning'    => 'No learning language is set: all stories are shown. Go to your profile to choose a language.',
			),
			'pl' => array(
				'all'                 => 'Wszystkie',
				'all_stories'         => 'Wszystkie historie',
				'in_progress'         => 'W trakcie',
				'to_continue'         => 'Do kontynuacji',
				'completed'           => 'Ukonczone',
				'filter_category'     => 'Filtruj wedlug kategorii',
				'filter_reading'      => 'Filtruj wedlug postepu',
				'login_for_filter'    => 'Zaloguj sie, aby filtrowac wedlug postepu',
				'login_for_stories'   => 'Zaloguj sie, aby zobaczyc swoje historie.',
				'no_started_stories'  => 'Nie rozpoczal(es/as) jeszcze zadnej historii.',
				'missing_learning'    => 'Nie ustawiono jezyka nauki: wyswietlamy wszystkie historie. Przejdz do profilu, aby wybrac jezyk.',
			),
			'es' => array(
				'all'                 => 'Todas',
				'all_stories'         => 'Todas las historias',
				'in_progress'         => 'En curso',
				'to_continue'         => 'Para continuar',
				'completed'           => 'Completadas',
				'filter_category'     => 'Filtrar por categoria',
				'filter_reading'      => 'Filtrar por estado de lectura',
				'login_for_filter'    => 'Inicia sesion para filtrar por estado de lectura',
				'login_for_stories'   => 'Inicia sesion para ver tus historias.',
				'no_started_stories'  => 'Aun no has empezado ninguna historia.',
				'missing_learning'    => 'No hay idioma de aprendizaje configurado: se muestran todas las historias. Ve a tu perfil para elegir un idioma.',
			),
		);

		if ( isset( $set[ $lang ] ) ) {
			return $set[ $lang ];
		}
		return $set['it'];
	}

	/* -----------------------------------------------------------------------
	 * CAMPI NELLA PAGINA "MODIFICA CATEGORIA"
	 * ---------------------------------------------------------------------- */

	/**
	 * Aggiunge i campi di traduzione alla pagina modifica categoria.
	 *
	 * @param WP_Term $term Termine in modifica.
	 */
	public static function render_edit_fields( WP_Term $term ) {
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		$langs = class_exists( 'LLM_Languages' ) ? LLM_Languages::get_codes() : array(
			'it' => 'Italian',
			'en' => 'English',
			'pl' => 'Polish',
			'es' => 'Spanish',
		);
		wp_nonce_field( 'llm_cat_trans_save_' . $term->term_id, 'llm_cat_trans_nonce' );
		?>
		<tr class="form-field">
			<td colspan="2">
				<h3 style="margin:1.2em 0 .4em; font-size:13px; color:#1d2327;">
					<?php esc_html_e( 'Traduzioni nome categoria (LLM)', 'llm-con-tabelle' ); ?>
				</h3>
			</td>
		</tr>
		<?php foreach ( $langs as $code => $label ) : ?>
			<?php
			$meta_key = self::META_PREFIX . $code;
			$value    = (string) get_term_meta( $term->term_id, $meta_key, true );
			?>
			<tr class="form-field">
				<th scope="row">
					<label for="<?php echo esc_attr( 'llm_cat_name_' . $code ); ?>">
						<?php echo esc_html( $label ); ?>
					</label>
				</th>
				<td>
					<input
						type="text"
						id="<?php echo esc_attr( 'llm_cat_name_' . $code ); ?>"
						name="<?php echo esc_attr( 'llm_cat_name[' . $code . ']' ); ?>"
						value="<?php echo esc_attr( $value ); ?>"
						class="regular-text"
						placeholder="<?php echo esc_attr( $term->name ); ?>"
					/>
					<p class="description">
						<?php
						printf(
							/* translators: %s: language name */
							esc_html__( 'Nome categoria in %s. Lascia vuoto per usare il nome WordPress.', 'llm-con-tabelle' ),
							esc_html( $label )
						);
						?>
					</p>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php
	}

	/**
	 * Salva i campi di traduzione quando si aggiorna una categoria.
	 *
	 * @param int $term_id ID del termine.
	 */
	public static function save_edit_fields( $term_id ) {
		$term_id = (int) $term_id;
		if ( ! $term_id ) {
			return;
		}
		if (
			! isset( $_POST['llm_cat_trans_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['llm_cat_trans_nonce'] ) ), 'llm_cat_trans_save_' . $term_id )
		) {
			return;
		}
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		$names = isset( $_POST['llm_cat_name'] ) && is_array( $_POST['llm_cat_name'] )
			? array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_POST['llm_cat_name'] ) )
			: array();

		$langs = class_exists( 'LLM_Languages' ) ? array_keys( LLM_Languages::get_codes() ) : array( 'it', 'en', 'pl', 'es' );
		foreach ( $langs as $code ) {
			$meta_key = self::META_PREFIX . sanitize_key( $code );
			$val      = isset( $names[ $code ] ) ? trim( $names[ $code ] ) : '';
			if ( '' !== $val ) {
				update_term_meta( $term_id, $meta_key, $val );
			} else {
				delete_term_meta( $term_id, $meta_key );
			}
		}
	}

	/* -----------------------------------------------------------------------
	 * PAGINA ADMIN RIEPILOGATIVA
	 * ---------------------------------------------------------------------- */

	public static function add_admin_page() {
		add_submenu_page(
			'edit.php?post_type=' . LLM_STORY_CPT,
			__( 'Traduzioni categorie', 'llm-con-tabelle' ),
			__( 'Traduzioni categorie', 'llm-con-tabelle' ),
			'manage_categories',
			self::MENU_SLUG,
			array( __CLASS__, 'render_admin_page' )
		);
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_die( esc_html__( 'Non hai i permessi necessari.', 'llm-con-tabelle' ) );
		}

		$langs = class_exists( 'LLM_Languages' ) ? LLM_Languages::get_codes() : array(
			'it' => 'Italian',
			'en' => 'English',
			'pl' => 'Polish',
			'es' => 'Spanish',
		);

		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			$terms = array();
		}

		// Gestione salvataggio.
		$saved = false;
		if (
			isset( $_POST['llm_cat_trans_bulk_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['llm_cat_trans_bulk_nonce'] ) ), 'llm_cat_trans_bulk' ) &&
			current_user_can( 'manage_categories' )
		) {
			$bulk = isset( $_POST['llm_cat_bulk'] ) && is_array( $_POST['llm_cat_bulk'] )
				? $_POST['llm_cat_bulk']
				: array();

			foreach ( $terms as $term ) {
				foreach ( array_keys( $langs ) as $code ) {
					$meta_key = self::META_PREFIX . sanitize_key( $code );
					$val      = isset( $bulk[ $term->term_id ][ $code ] )
						? trim( sanitize_text_field( wp_unslash( $bulk[ $term->term_id ][ $code ] ) ) )
						: '';
					if ( '' !== $val ) {
						update_term_meta( $term->term_id, $meta_key, $val );
					} else {
						delete_term_meta( $term->term_id, $meta_key );
					}
				}
			}
			$saved = true;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Traduzioni nomi categorie', 'llm-con-tabelle' ); ?></h1>
			<p><?php esc_html_e( 'Inserisci il nome di ogni categoria nella lingua desiderata. Lascia vuoto per usare il nome WordPress originale.', 'llm-con-tabelle' ); ?></p>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Traduzioni salvate.', 'llm-con-tabelle' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( empty( $terms ) ) : ?>
				<p><?php esc_html_e( 'Nessuna categoria trovata.', 'llm-con-tabelle' ); ?></p>
			<?php else : ?>
				<form method="post" action="">
					<?php wp_nonce_field( 'llm_cat_trans_bulk', 'llm_cat_trans_bulk_nonce' ); ?>
					<style>
						.llm-cat-trans-table { border-collapse: collapse; width: 100%; margin-top: 1em; }
						.llm-cat-trans-table th,
						.llm-cat-trans-table td { padding: 8px 12px; border: 1px solid #c3c4c7; vertical-align: middle; }
						.llm-cat-trans-table thead th { background: #f6f7f7; font-weight: 600; white-space: nowrap; }
						.llm-cat-trans-table tbody tr:nth-child(even) { background: #fafafa; }
						.llm-cat-trans-table td:first-child { font-weight: 500; white-space: nowrap; }
						.llm-cat-trans-table input[type="text"] { width: 100%; max-width: 220px; }
						.llm-cat-trans-table .llm-orig { font-size: 11px; color: #777; display: block; margin-top: 2px; }
					</style>
					<table class="llm-cat-trans-table widefat">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Categoria (nome WordPress)', 'llm-con-tabelle' ); ?></th>
								<?php foreach ( $langs as $code => $label ) : ?>
									<th><?php echo esc_html( $label ); ?> <code style="font-weight:400;"><?php echo esc_html( $code ); ?></code></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $terms as $term ) : ?>
								<tr>
									<td>
										<?php echo esc_html( $term->name ); ?>
										<a
											href="<?php echo esc_url( get_edit_term_link( $term->term_id, 'category' ) ); ?>"
											style="font-size:11px; margin-left:6px;"
										><?php esc_html_e( 'Modifica', 'llm-con-tabelle' ); ?></a>
									</td>
									<?php foreach ( $langs as $code => $label ) : ?>
										<?php
										$saved_val = (string) get_term_meta( $term->term_id, self::META_PREFIX . $code, true );
										?>
										<td>
											<input
												type="text"
												name="<?php echo esc_attr( "llm_cat_bulk[{$term->term_id}][{$code}]" ); ?>"
												value="<?php echo esc_attr( $saved_val ); ?>"
												placeholder="<?php echo esc_attr( $term->name ); ?>"
											/>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="submit">
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Salva tutte le traduzioni', 'llm-con-tabelle' ); ?>
						</button>
					</p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}
}
