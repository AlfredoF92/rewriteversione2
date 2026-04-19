<?php
/**
 * Bacheca: elenco utenti LLS e scheda dettaglio.
 *
 * @package LLS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLS_Admin_Users {

	const PAGE_SLUG = 'lls-utenti';

	/** Righe per pagina nelle tabelle attività Community (scheda utente). */
	private const COMMUNITY_ACTIVITY_PER_PAGE = 10;

	private const QUERY_OWN_PAGED = 'lls_own_paged';

	private const QUERY_GIVEN_PAGED = 'lls_given_paged';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_post' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue( $hook ) {
		if ( strpos( $hook, self::PAGE_SLUG ) === false && strpos( $hook, 'lls-community' ) === false ) {
			return;
		}
		wp_enqueue_style(
			'lls-admin-users',
			LLS_PLUGIN_URL . 'assets/admin-users.css',
			array(),
			LLS_VERSION
		);
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . LLS_CPT,
			__( 'Utenti iscritti', 'language-learning-stories' ),
			__( 'Utenti iscritti', 'language-learning-stories' ),
			'list_users',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Salva scheda utente.
	 */
	public static function handle_post() {
		if ( ! isset( $_POST['lls_user_profile_save'] ) || ! isset( $_POST['_wpnonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'lls_user_profile' ) ) {
			return;
		}
		$user_id = isset( $_POST['lls_edit_user_id'] ) ? absint( $_POST['lls_edit_user_id'] ) : 0;
		if ( ! $user_id || ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'Permesso negato.', 'language-learning-stories' ) );
		}

		$known = isset( $_POST['lls_interface_lang'] ) ? sanitize_key( wp_unslash( $_POST['lls_interface_lang'] ) ) : '';
		if ( $known !== '' && ! LLS_Languages::is_valid( $known ) ) {
			$known = '';
		}
		$learn = isset( $_POST['lls_learning_lang'] ) ? sanitize_key( wp_unslash( $_POST['lls_learning_lang'] ) ) : '';
		if ( $learn !== '' && ! LLS_Languages::is_valid( $learn ) ) {
			$learn = '';
		}

		update_user_meta( $user_id, LLS_User_Meta::INTERFACE_LANG, $known );
		update_user_meta( $user_id, LLS_User_Meta::LEARNING_LANG, $learn );

		if ( isset( $_POST['lls_coin_balance'] ) && current_user_can( 'manage_options' ) ) {
			$new = LLS_Story_Meta::sanitize_coin_int( wp_unslash( $_POST['lls_coin_balance'] ) );
			$note = isset( $_POST['lls_balance_note'] ) ? sanitize_text_field( wp_unslash( $_POST['lls_balance_note'] ) ) : '';
			LLS_User_Stats::set_balance_admin( $user_id, $new, $note );
		}

		$redirect = add_query_arg(
			array(
				'post_type' => LLS_CPT,
				'page'      => self::PAGE_SLUG,
				'user_id'   => $user_id,
				'updated'   => '1',
			),
			admin_url( 'edit.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( 'list_users' ) ) {
			wp_die( esc_html__( 'Permesso negato.', 'language-learning-stories' ) );
		}

		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		if ( $user_id && current_user_can( 'edit_user', $user_id ) ) {
			self::render_detail( $user_id );
			return;
		}

		self::render_list();
	}

	/**
	 * Elenco utenti con colonne LLS.
	 */
	private static function render_list() {
		$paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per     = 25;
		$query   = new WP_User_Query(
			array(
				'number' => $per,
				'paged'  => $paged,
				'orderby' => 'registered',
				'order'   => 'DESC',
			)
		);
		$users   = $query->get_results();
		$total   = $query->get_total();
		$pages   = (int) ceil( $total / $per );

		echo '<div class="wrap lls-users-wrap">';
		echo '<h1>' . esc_html__( 'Utenti iscritti (LLS)', 'language-learning-stories' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Profilo lingue, progressi e coin collegati agli account WordPress.', 'language-learning-stories' ) . '</p>';

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Utente', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Email', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Lingue (nota → obiettivo)', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Frasi completate', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Storie completate', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Saldo coin', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Azioni', 'language-learning-stories' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $users ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'Nessun utente.', 'language-learning-stories' ) . '</td></tr>';
		} else {
			foreach ( $users as $u ) {
				$uid = (int) $u->ID;
				$k   = get_user_meta( $uid, LLS_User_Meta::INTERFACE_LANG, true );
				$t   = get_user_meta( $uid, LLS_User_Meta::LEARNING_LANG, true );
				$url = add_query_arg(
					array(
						'post_type' => LLS_CPT,
						'page'      => self::PAGE_SLUG,
						'user_id'   => $uid,
					),
					admin_url( 'edit.php' )
				);
				echo '<tr>';
				echo '<td><strong>' . esc_html( $u->display_name ) . '</strong><br /><span class="description">' . esc_html( $u->user_login ) . '</span></td>';
				echo '<td>' . esc_html( $u->user_email ) . '</td>';
				echo '<td>' . esc_html( ( $k ? $k : '—' ) . ' → ' . ( $t ? $t : '—' ) ) . '</td>';
				echo '<td>' . esc_html( (string) LLS_User_Stats::count_completed_phrases( $uid ) ) . '</td>';
				echo '<td>' . esc_html( (string) LLS_User_Stats::count_completed_stories( $uid ) ) . '</td>';
				echo '<td>' . esc_html( (string) LLS_User_Stats::get_balance( $uid ) ) . '</td>';
				echo '<td><a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html__( 'Scheda', 'language-learning-stories' ) . '</a></td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';

		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			$base = admin_url( 'edit.php?post_type=' . rawurlencode( LLS_CPT ) . '&page=' . rawurlencode( self::PAGE_SLUG ) . '&paged=%#%' );
			echo paginate_links(
				array(
					'base'      => $base,
					'format'    => '',
					'current'   => $paged,
					'total'     => $pages,
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
				)
			);
			echo '</div></div>';
		}

		echo '</div>';
	}

	/**
	 * Scheda singolo utente.
	 *
	 * @param int $user_id ID utente.
	 */
	private static function render_detail( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Utente non trovato.', 'language-learning-stories' ) . '</p></div>';
			return;
		}

		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Profilo salvato.', 'language-learning-stories' ) . '</p></div>';
		}

		$back = add_query_arg( array( 'post_type' => LLS_CPT, 'page' => self::PAGE_SLUG ), admin_url( 'edit.php' ) );

		$known  = get_user_meta( $user_id, LLS_User_Meta::INTERFACE_LANG, true );
		$learn  = get_user_meta( $user_id, LLS_User_Meta::LEARNING_LANG, true );
		$balance = LLS_User_Stats::get_balance( $user_id );
		$map    = LLS_User_Stats::get_phrase_map( $user_id );
		$unlocked = LLS_User_Stats::get_unlocked_story_ids( $user_id );
		$done_st = LLS_User_Stats::get_completed_stories_map( $user_id );
		$ledger  = LLS_User_Stats::get_ledger( $user_id );
		$econ    = LLS_User_Stats::sum_economy( $user_id );

		$lang_opts = LLS_Languages::get_codes();

		echo '<div class="wrap lls-users-wrap lls-user-detail">';
		echo '<p><a href="' . esc_url( $back ) . '">&larr; ' . esc_html__( 'Torna all\'elenco', 'language-learning-stories' ) . '</a></p>';
		echo '<h1>' . esc_html( sprintf( /* translators: %s display name */ __( 'Utente: %s', 'language-learning-stories' ), $user->display_name ) ) . '</h1>';
		echo '<p class="description">' . esc_html( $user->user_login ) . ' &mdash; ' . esc_html( $user->user_email ) . '</p>';

		echo '<form method="post" action="" class="lls-user-form">';
		wp_nonce_field( 'lls_user_profile', '_wpnonce' );
		echo '<input type="hidden" name="lls_user_profile_save" value="1" />';
		echo '<input type="hidden" name="lls_edit_user_id" value="' . esc_attr( (string) $user_id ) . '" />';

		echo '<h2>' . esc_html__( 'Profilo lingue', 'language-learning-stories' ) . '</h2>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="lls_interface_lang">' . esc_html__( 'Lingua interfaccia (nota)', 'language-learning-stories' ) . '</label></th><td>';
		echo '<select name="lls_interface_lang" id="lls_interface_lang">';
		echo '<option value="">' . esc_html__( '—', 'language-learning-stories' ) . '</option>';
		foreach ( $lang_opts as $code => $lab ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $code ),
				selected( $known, $code, false ),
				esc_html( $lab . ' (' . $code . ')' )
			);
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="lls_learning_lang">' . esc_html__( 'Lingua da imparare', 'language-learning-stories' ) . '</label></th><td>';
		echo '<select name="lls_learning_lang" id="lls_learning_lang">';
		echo '<option value="">' . esc_html__( '—', 'language-learning-stories' ) . '</option>';
		foreach ( $lang_opts as $code => $lab ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $code ),
				selected( $learn, $code, false ),
				esc_html( $lab . ' (' . $code . ')' )
			);
		}
		echo '</select></td></tr>';
		echo '</tbody></table>';

		if ( current_user_can( 'manage_options' ) ) {
			echo '<h2>' . esc_html__( 'Saldo coin', 'language-learning-stories' ) . '</h2>';
			echo '<table class="form-table"><tbody>';
			echo '<tr><th><label for="lls_coin_balance">' . esc_html__( 'Coin attuali', 'language-learning-stories' ) . '</label></th><td>';
			echo '<input type="number" min="0" step="1" name="lls_coin_balance" id="lls_coin_balance" value="' . esc_attr( (string) $balance ) . '" /> ';
			echo '<p class="description">' . esc_html__( 'Modifica manuale: verrà registrata una riga nel ledger.', 'language-learning-stories' ) . '</p>';
			echo '<input type="text" class="regular-text" name="lls_balance_note" placeholder="' . esc_attr__( 'Nota (opzionale)', 'language-learning-stories' ) . '" />';
			echo '</td></tr></tbody></table>';
		}

		submit_button( __( 'Salva profilo', 'language-learning-stories' ) );
		echo '</form>';

		echo '<div class="lls-user-cards">';
		self::card( __( 'Frasi completate (totale)', 'language-learning-stories' ), (string) LLS_User_Stats::count_completed_phrases( $user_id ) );
		self::card( __( 'Storie completate', 'language-learning-stories' ), (string) LLS_User_Stats::count_completed_stories( $user_id ) );
		self::card( __( 'Saldo coin', 'language-learning-stories' ), (string) $balance );
		self::card( __( 'Coin guadagnati (ledger)', 'language-learning-stories' ), (string) $econ['earned'] );
		self::card( __( 'Coin spesi (sblocchi)', 'language-learning-stories' ), (string) $econ['spent'] );
		self::card( __( 'Da frasi (+1 ciascuna)', 'language-learning-stories' ), (string) $econ['phrase_gain'] );
		self::card( __( 'Premi storia (somma)', 'language-learning-stories' ), (string) $econ['story_reward'] );
		self::card( __( 'Bravi ricevuti (sulle proprie attività)', 'language-learning-stories' ), (string) LLS_Community::count_bravi_received( $user_id ) );
		self::card( __( 'Bravi dati (like ad altri)', 'language-learning-stories' ), (string) LLS_Community::count_bravi_given( $user_id ) );
		echo '</div>';

		$detail_base = remove_query_arg(
			array( self::QUERY_OWN_PAGED, self::QUERY_GIVEN_PAGED ),
			add_query_arg(
				array(
					'post_type' => LLS_CPT,
					'page'      => self::PAGE_SLUG,
					'user_id'   => $user_id,
				),
				admin_url( 'edit.php' )
			)
		);

		$own_paged   = isset( $_GET[ self::QUERY_OWN_PAGED ] ) ? max( 1, absint( wp_unslash( $_GET[ self::QUERY_OWN_PAGED ] ) ) ) : 1;
		$given_paged = isset( $_GET[ self::QUERY_GIVEN_PAGED ] ) ? max( 1, absint( wp_unslash( $_GET[ self::QUERY_GIVEN_PAGED ] ) ) ) : 1;
		$per         = self::COMMUNITY_ACTIVITY_PER_PAGE;

		echo '<h2>' . esc_html__( 'Community — le tue attività e i Bravi', 'language-learning-stories' ) . '</h2>';
		echo '<h3>' . esc_html__( 'Attività pubblicate da questo utente', 'language-learning-stories' ) . '</h3>';
		$own_act = new WP_Query(
			array(
				'post_type'      => LLS_ACTIVITY_CPT,
				'post_status'    => 'publish',
				'author'         => $user_id,
				'posts_per_page' => $per,
				'paged'          => $own_paged,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		if ( (int) $own_act->max_num_pages > 0 && $own_paged > (int) $own_act->max_num_pages ) {
			$own_paged = (int) $own_act->max_num_pages;
			wp_reset_postdata();
			$own_act = new WP_Query(
				array(
					'post_type'      => LLS_ACTIVITY_CPT,
					'post_status'    => 'publish',
					'author'         => $user_id,
					'posts_per_page' => $per,
					'paged'          => $own_paged,
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);
		}
		$own_total_pages = max( 1, (int) $own_act->max_num_pages );
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Data attività', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Tipo', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Dettaglio', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Bravi ricevuti', 'language-learning-stories' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( ! $own_act->have_posts() ) {
			echo '<tr><td colspan="4">' . esc_html__( 'Nessuna attività in feed.', 'language-learning-stories' ) . '</td></tr>';
		} else {
			while ( $own_act->have_posts() ) {
				$own_act->the_post();
				$aid  = (int) get_the_ID();
				$type = (string) get_post_meta( $aid, LLS_Community::META_TYPE, true );
				$n    = count( LLS_Community::get_kudos_user_ids( $aid ) );
				echo '<tr>';
				echo '<td>' . esc_html( get_the_date( 'Y-m-d H:i' ) ) . '</td>';
				echo '<td>' . esc_html( LLS_Community::type_label( $type ) ) . '</td>';
				echo '<td>' . esc_html( LLS_Community::format_detail( $aid ) ) . '</td>';
				echo '<td>' . esc_html( (string) $n ) . '</td>';
				echo '</tr>';
			}
			wp_reset_postdata();
		}
		echo '</tbody></table>';
		self::render_community_table_pagination( $detail_base, self::QUERY_OWN_PAGED, $own_paged, $own_total_pages, $given_paged, self::QUERY_GIVEN_PAGED );

		echo '<h3>' . esc_html__( 'Bravo messo ad attività di altri utenti', 'language-learning-stories' ) . '</h3>';
		$given_rows     = LLS_Community::get_bravo_given_raw( $user_id );
		$given_reversed = array_reverse( $given_rows );
		$given_count    = count( $given_reversed );
		$given_total_pages = max( 1, (int) ceil( $given_count / $per ) );
		if ( $given_paged > $given_total_pages && $given_count > 0 ) {
			$given_paged = $given_total_pages;
		}
		$given_offset = ( $given_paged - 1 ) * $per;
		$given_slice  = array_slice( $given_reversed, $given_offset, $per );
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Data Bravo', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Autore attività', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Tipo', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Dettaglio', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Data attività', 'language-learning-stories' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( empty( $given_rows ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'Nessun Bravo dato.', 'language-learning-stories' ) . '</td></tr>';
		} else {
			foreach ( $given_slice as $row ) {
				$aid = isset( $row['activity_id'] ) ? absint( $row['activity_id'] ) : 0;
				$ap  = get_post( $aid );
				if ( ! $ap || LLS_ACTIVITY_CPT !== $ap->post_type ) {
					echo '<tr><td>' . esc_html( isset( $row['ts'] ) ? (string) $row['ts'] : '—' ) . '</td><td colspan="4">';
					echo esc_html( sprintf( /* translators: %d activity id */ __( 'Attività #%d non trovata.', 'language-learning-stories' ), $aid ) );
					echo '</td></tr>';
					continue;
				}
				$auth = (int) $ap->post_author;
				$au   = get_userdata( $auth );
				$type = (string) get_post_meta( $aid, LLS_Community::META_TYPE, true );
				echo '<tr>';
				echo '<td>' . esc_html( isset( $row['ts'] ) ? (string) $row['ts'] : '—' ) . '</td>';
				echo '<td>' . ( $au ? esc_html( $au->display_name ) : '—' ) . '</td>';
				echo '<td>' . esc_html( LLS_Community::type_label( $type ) ) . '</td>';
				echo '<td>' . esc_html( LLS_Community::format_detail( $aid ) ) . '</td>';
				echo '<td>' . esc_html( get_the_date( 'Y-m-d H:i', $ap ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		self::render_community_table_pagination( $detail_base, self::QUERY_GIVEN_PAGED, $given_paged, $given_total_pages, $own_paged, self::QUERY_OWN_PAGED );

		echo '<h2>' . esc_html__( 'Frasi completate (dettaglio)', 'language-learning-stories' ) . '</h2>';
		echo '<table class="widefat striped lls-phrases-detail"><thead><tr>';
		echo '<th>' . esc_html__( 'Storia', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Frase #', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Testo (lingua interfaccia)', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Testo (lingua obiettivo)', 'language-learning-stories' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( empty( $map ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'Nessun dato.', 'language-learning-stories' ) . '</td></tr>';
		} else {
			foreach ( $map as $sid => $indices ) {
				$story_id = (int) $sid;
				$title    = get_the_title( $story_id );
				if ( ! $title ) {
					$title = '#' . $sid;
				}
				$story_phrases = LLS_Story_Meta::get_phrases( $story_id );
				foreach ( $indices as $pi ) {
					$idx = (int) $pi;
					$if  = '';
					$tg  = '';
					if ( isset( $story_phrases[ $idx ] ) && is_array( $story_phrases[ $idx ] ) ) {
						$row = $story_phrases[ $idx ];
						$if  = isset( $row['interface'] ) ? (string) $row['interface'] : '';
						$tg  = isset( $row['target'] ) ? (string) $row['target'] : '';
					}
					echo '<tr>';
					echo '<td>' . esc_html( $title ) . '</td>';
					echo '<td>' . esc_html( (string) ( $idx + 1 ) ) . '</td>';
					echo '<td class="lls-phrase-cell">' . esc_html( $if !== '' ? $if : '—' ) . '</td>';
					echo '<td class="lls-phrase-cell">' . esc_html( $tg !== '' ? $tg : '—' ) . '</td>';
					echo '</tr>';
				}
			}
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Storie sbloccate / acquistate', 'language-learning-stories' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Storia', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Costo impostato', 'language-learning-stories' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( empty( $unlocked ) ) {
			echo '<tr><td colspan="2">' . esc_html__( 'Nessuna.', 'language-learning-stories' ) . '</td></tr>';
		} else {
			foreach ( $unlocked as $sid ) {
				$cost = (int) get_post_meta( $sid, LLS_Story_Meta::COIN_COST, true );
				$title = get_the_title( $sid ) ? get_the_title( $sid ) : '#' . $sid;
				echo '<tr><td>' . esc_html( $title ) . '</td><td>' . esc_html( (string) $cost ) . '</td></tr>';
			}
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Storie completate (ultima frase)', 'language-learning-stories' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Storia', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Data/ora', 'language-learning-stories' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( empty( $done_st ) ) {
			echo '<tr><td colspan="2">' . esc_html__( 'Nessuna.', 'language-learning-stories' ) . '</td></tr>';
		} else {
			foreach ( $done_st as $sid => $ts ) {
				$title = get_the_title( (int) $sid );
				if ( ! $title ) {
					$title = '#' . $sid;
				}
				echo '<tr><td>' . esc_html( $title ) . '</td><td>' . esc_html( (string) $ts ) . '</td></tr>';
			}
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Attività economica (ledger)', 'language-learning-stories' ) . '</h2>';
		echo '<table class="widefat striped lls-ledger-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Data', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Tipo', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Importo', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Saldo dopo', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Storia', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Frase', 'language-learning-stories' ) . '</th>';
		echo '<th>' . esc_html__( 'Nota', 'language-learning-stories' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $ledger ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'Nessun movimento.', 'language-learning-stories' ) . '</td></tr>';
		} else {
			$ledger = array_reverse( $ledger );
			foreach ( $ledger as $row ) {
				$sid = isset( $row['story_id'] ) ? (int) $row['story_id'] : 0;
				$stitle = $sid ? get_the_title( $sid ) : '—';
				if ( $sid && ! $stitle ) {
					$stitle = '#' . $sid;
				}
				$pi = isset( $row['phrase_index'] ) && null !== $row['phrase_index'] ? (string) ( (int) $row['phrase_index'] + 1 ) : '—';
				echo '<tr>';
				echo '<td>' . esc_html( isset( $row['ts'] ) ? $row['ts'] : '' ) . '</td>';
				echo '<td>' . esc_html( isset( $row['type'] ) ? $row['type'] : '' ) . '</td>';
				echo '<td>' . esc_html( isset( $row['amount'] ) ? (string) $row['amount'] : '0' ) . '</td>';
				echo '<td>' . esc_html( isset( $row['balance_after'] ) ? (string) $row['balance_after'] : '' ) . '</td>';
				echo '<td>' . esc_html( $stitle ) . '</td>';
				echo '<td>' . esc_html( $pi ) . '</td>';
				echo '<td>' . esc_html( isset( $row['label'] ) ? $row['label'] : '' ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		echo '<p class="description">' . esc_html__( 'Ogni frase completata +1 coin; allo sblocco si pagano i coin della storia; al completamento tutte le frasi si applica il premio della storia.', 'language-learning-stories' ) . '</p>';

		echo '</div>';
	}

	/**
	 * @param string $label Etichetta.
	 * @param string $value Valore.
	 */
	private static function card( $label, $value ) {
		echo '<div class="lls-stat-card"><span class="lls-stat-label">' . esc_html( $label ) . '</span><strong class="lls-stat-value">' . esc_html( $value ) . '</strong></div>';
	}

	/**
	 * Paginazione sotto una tabella Community nella scheda utente (mantiene la pagina dell’altro elenco).
	 *
	 * @param string $detail_base URL senza lls_own_paged / lls_given_paged.
	 * @param string $page_param  self::QUERY_OWN_PAGED o self::QUERY_GIVEN_PAGED.
	 * @param int    $current     Pagina corrente di questa sezione.
	 * @param int    $total_pages Numero pagine.
	 * @param int    $other_paged Pagina dell’altra sezione (≥2 viene aggiunta alla query).
	 * @param string $other_param Nome query arg dell’altra sezione.
	 */
	private static function render_community_table_pagination( $detail_base, $page_param, $current, $total_pages, $other_paged, $other_param ) {
		if ( $total_pages <= 1 ) {
			return;
		}
		$args = array( $page_param => 999999999 );
		if ( $other_paged > 1 ) {
			$args[ $other_param ] = $other_paged;
		}
		$base = esc_url( add_query_arg( $args, $detail_base ) );
		$base = str_replace( '999999999', '%#%', $base );
		echo '<div class="tablenav lls-community-pagination"><div class="tablenav-pages">';
		echo paginate_links(
			array(
				'base'      => $base,
				'format'    => '',
				'current'   => $current,
				'total'     => $total_pages,
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
				'type'      => 'plain',
			)
		);
		echo '</div></div>';
	}
}
