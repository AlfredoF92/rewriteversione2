<?php
/**
 * Pagina impostazioni admin: Redirect Homepage.
 *
 * Permette di associare una pagina WordPress a ciascuna combinazione
 * di lingua conosciuta → lingua da imparare.
 *
 * Opzione salvata: llm_home_redirect_pairs
 * Formato: array( 'it_en' => page_id, 'en_it' => page_id, ... )
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Admin_Home_Redirect {

	const PAGE_SLUG  = 'llm-home-redirect-settings';
	const NONCE_KEY  = 'llm_hr_settings_save';
	const ACTION_KEY = 'llm_hr_save';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_' . self::ACTION_KEY, array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . LLM_STORY_CPT,
			__( 'Redirect Homepage', 'llm-con-tabelle' ),
			__( 'Redirect Homepage', 'llm-con-tabelle' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	public static function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'llm-ui' );
	}

	// -------------------------------------------------------------------------
	// Salvataggio
	// -------------------------------------------------------------------------

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permesso negato.', 'llm-con-tabelle' ) );
		}

		check_admin_referer( self::NONCE_KEY );

		$raw   = isset( $_POST['llm_hr_pairs'] ) && is_array( $_POST['llm_hr_pairs'] )
			? $_POST['llm_hr_pairs']
			: array();
		$pairs = array();

		$valid_codes = array_keys( LLM_Languages::get_codes() );

		foreach ( $raw as $key => $val ) {
			$key     = sanitize_key( (string) $key );
			$page_id = absint( $val );

			// Chiave valida: due codici separati da underscore
			$parts = explode( '_', $key, 2 );
			if (
				count( $parts ) !== 2 ||
				! in_array( $parts[0], $valid_codes, true ) ||
				! in_array( $parts[1], $valid_codes, true ) ||
				$parts[0] === $parts[1]
			) {
				continue;
			}

			if ( $page_id > 0 ) {
				$pairs[ $key ] = $page_id;
			}
		}

		update_option( LLM_Home_Redirect::OPT_PAIRS, $pairs, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => self::PAGE_SLUG,
					'saved' => '1',
				),
				admin_url( 'edit.php?post_type=' . LLM_STORY_CPT )
			)
		);
		exit;
	}

	// -------------------------------------------------------------------------
	// Render pagina
	// -------------------------------------------------------------------------

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permesso negato.', 'llm-con-tabelle' ) );
		}

		$saved = (array) get_option( LLM_Home_Redirect::OPT_PAIRS, array() );
		$codes = LLM_Languages::get_codes();

		// Tutte le pagine pubblicate, ordinate per titolo
		$all_pages = get_pages(
			array(
				'post_status' => 'publish',
				'sort_column' => 'post_title',
				'sort_order'  => 'ASC',
			)
		);
		if ( ! is_array( $all_pages ) ) {
			$all_pages = array();
		}

		// Etichette lingua in italiano (lingua dell'interfaccia admin)
		$lang_it = array(
			'en' => 'Inglese',
			'it' => 'Italiano',
			'pl' => 'Polacco',
			'es' => 'Spagnolo',
		);

		$action_url = admin_url( 'admin-post.php' );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Redirect Homepage — Pagine per coppia linguistica', 'llm-con-tabelle' ) . '</h1>';

		// Notice successo
		if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html__( 'Impostazioni salvate.', 'llm-con-tabelle' );
			echo '</p></div>';
		}

		echo '<p class="description" style="margin:12px 0 20px;">';
		echo esc_html__( 'Per ogni combinazione di lingue, seleziona la pagina WordPress a cui lo shortcode [llm_home_redirect] deve reindirizzare l\'utente. Lascia "— Non configurata —" per disabilitare quella coppia.', 'llm-con-tabelle' );
		echo '</p>';

		echo '<form method="post" action="' . esc_url( $action_url ) . '">';
		wp_nonce_field( self::NONCE_KEY );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_KEY ) . '" />';

		// Raggruppa per lingua nota (una sezione per lingua)
		foreach ( $codes as $known_code => $unused_known_label ) {
			$known_label = isset( $lang_it[ $known_code ] ) ? $lang_it[ $known_code ] : $known_code;

			echo '<h2 style="margin-top:24px;margin-bottom:8px;font-size:1rem;font-weight:600;">';
			printf(
				/* translators: %s = nome lingua (es. "Italiano") */
				esc_html__( 'Conosce: %s', 'llm-con-tabelle' ),
				esc_html( $known_label )
			);
			echo '</h2>';

			echo '<table class="widefat fixed" style="margin-bottom:8px;">';
			echo '<thead><tr>';
			echo '<th style="width:30%;">' . esc_html__( 'Vuole imparare', 'llm-con-tabelle' ) . '</th>';
			echo '<th>' . esc_html__( 'Pagina di destinazione', 'llm-con-tabelle' ) . '</th>';
			echo '<th style="width:22%;">' . esc_html__( 'Anteprima URL', 'llm-con-tabelle' ) . '</th>';
			echo '</tr></thead>';
			echo '<tbody>';

			foreach ( $codes as $learning_code => $unused_learning_label ) {
				if ( $known_code === $learning_code ) {
					continue;
				}

				$learning_label = isset( $lang_it[ $learning_code ] ) ? $lang_it[ $learning_code ] : $learning_code;
				$opt_key        = $known_code . '_' . $learning_code;
				$selected_id    = isset( $saved[ $opt_key ] ) ? absint( $saved[ $opt_key ] ) : 0;
				$field_name     = 'llm_hr_pairs[' . esc_attr( $opt_key ) . ']';
				$field_id       = 'llm-hr-' . esc_attr( $opt_key );

				// URL pagina selezionata (per anteprima)
				$preview_url = '';
				if ( $selected_id > 0 ) {
					$permalink   = get_permalink( $selected_id );
					$preview_url = $permalink ? (string) $permalink : '';
				}

				echo '<tr>';

				// Colonna: lingua da imparare
				echo '<td><strong>' . esc_html( $learning_label ) . '</strong></td>';

				// Colonna: select pagina
				echo '<td>';
				echo '<select name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_id ) . '" class="llm-hr-page-select" style="width:100%;max-width:420px;" data-preview-target="' . esc_attr( $field_id . '-preview' ) . '">';
				echo '<option value="0">' . esc_html__( '— Non configurata —', 'llm-con-tabelle' ) . '</option>';

				foreach ( $all_pages as $page ) {
					$pid   = (int) $page->ID;
					$label = esc_html( $page->post_title );
					$url   = (string) get_permalink( $pid );
					echo '<option value="' . esc_attr( $pid ) . '"'
						. selected( $selected_id, $pid, false )
						. ' data-url="' . esc_attr( $url ) . '"'
						. '>' . $label . '</option>';
				}

				echo '</select>';
				echo '</td>';

				// Colonna: anteprima URL
				echo '<td>';
				echo '<span id="' . esc_attr( $field_id . '-preview' ) . '" style="font-size:0.8rem;color:#646970;word-break:break-all;">';
				if ( '' !== $preview_url ) {
					echo '<a href="' . esc_url( $preview_url ) . '" target="_blank" rel="noopener">' . esc_html( $preview_url ) . '</a>';
				} else {
					echo '—';
				}
				echo '</span>';
				echo '</td>';

				echo '</tr>';
			}

			echo '</tbody></table>';
		}

		echo '<p style="margin-top:24px;">';
		echo '<button type="submit" class="button button-primary">';
		echo esc_html__( 'Salva impostazioni', 'llm-con-tabelle' );
		echo '</button>';
		echo '</p>';

		echo '</form>';

		// JS inline per aggiornare l'anteprima URL al cambio select
		?>
		<script>
		(function () {
			document.querySelectorAll('.llm-hr-page-select').forEach(function (sel) {
				sel.addEventListener('change', function () {
					var previewId = sel.dataset.previewTarget;
					var previewEl = previewId ? document.getElementById(previewId) : null;
					if (!previewEl) return;
					var opt = sel.options[sel.selectedIndex];
					var url = opt ? opt.dataset.url || '' : '';
					if (url) {
						previewEl.innerHTML = '<a href="' + url + '" target="_blank" rel="noopener">' + url + '</a>';
					} else {
						previewEl.textContent = '—';
					}
				});
			});
		}());
		</script>
		<?php

		echo '</div>'; // .wrap
	}
}
