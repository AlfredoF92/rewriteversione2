<?php
/**
 * Bacheca: elenco utenti LLM e scheda dettaglio (dati in tabelle).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Admin_Users {

	const PAGE_SLUG = 'llm-utenti';

	/** Righe per pagina nelle tabelle attività Community (scheda utente). */
	private const COMMUNITY_ACTIVITY_PER_PAGE = 10;

	private const QUERY_OWN_PAGED = 'llm_own_paged';

	private const QUERY_GIVEN_PAGED = 'llm_given_paged';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_post' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue( $hook ) {
		if ( strpos( $hook, self::PAGE_SLUG ) === false && strpos( $hook, 'llm-community' ) === false ) {
			return;
		}
		wp_enqueue_style(
			'llm-admin-users',
			LLM_TABELLE_URL . 'assets/llm-admin-users.css',
			array(),
			LLM_TABELLE_VERSION
		);
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . LLM_STORY_CPT,
			__( 'Utenti LLM', 'llm-con-tabelle' ),
			__( 'Utenti LLM', 'llm-con-tabelle' ),
			'list_users',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Salva scheda utente.
	 */
	public static function handle_post() {
		if ( ! isset( $_POST['llm_user_profile_save'] ) || ! isset( $_POST['_wpnonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'llm_user_profile' ) ) {
			return;
		}
		$user_id = isset( $_POST['llm_edit_user_id'] ) ? absint( $_POST['llm_edit_user_id'] ) : 0;
		if ( ! $user_id || ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'Permesso negato.', 'llm-con-tabelle' ) );
		}

		$known = isset( $_POST['llm_interface_lang'] ) ? sanitize_key( wp_unslash( $_POST['llm_interface_lang'] ) ) : '';
		if ( $known !== '' && ! LLM_Languages::is_valid( $known ) ) {
			$known = '';
		}
		$learn = isset( $_POST['llm_learning_lang'] ) ? sanitize_key( wp_unslash( $_POST['llm_learning_lang'] ) ) : '';
		if ( $learn !== '' && ! LLM_Languages::is_valid( $learn ) ) {
			$learn = '';
		}

		update_user_meta( $user_id, LLM_User_Meta::INTERFACE_LANG, $known );
		update_user_meta( $user_id, LLM_User_Meta::LEARNING_LANG, $learn );

		if ( isset( $_POST['llm_coin_balance'] ) && current_user_can( 'manage_options' ) ) {
			$new = LLM_Story_Meta::sanitize_coin( wp_unslash( $_POST['llm_coin_balance'] ) );
			$note = isset( $_POST['llm_balance_note'] ) ? sanitize_text_field( wp_unslash( $_POST['llm_balance_note'] ) ) : '';
			LLM_User_Stats::set_balance_admin( $user_id, $new, $note );
		}

		$redirect = add_query_arg(
			array(
				'post_type' => LLM_STORY_CPT,
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
			wp_die( esc_html__( 'Permesso negato.', 'llm-con-tabelle' ) );
		}

		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		if ( $user_id && current_user_can( 'edit_user', $user_id ) ) {
			self::render_detail( $user_id );
			return;
		}

		self::render_list();
	}

	/**
	 * Elenco utenti con colonne LLM.
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

		echo '<div class="wrap llm-users-wrap">';
		echo '<h1>' . esc_html__( 'Utenti iscritti (LLM)', 'llm-con-tabelle' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Profilo lingue, progressi e coin collegati agli account WordPress.', 'llm-con-tabelle' ) . '</p>';

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Utente', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Email', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Lingue (nota → obiettivo)', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Frasi completate', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Storie completate', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Saldo coin', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Azioni', 'llm-con-tabelle' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $users ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'Nessun utente.', 'llm-con-tabelle' ) . '</td></tr>';
		} else {
			foreach ( $users as $u ) {
				$uid = (int) $u->ID;
				$k   = get_user_meta( $uid, LLM_User_Meta::INTERFACE_LANG, true );
				$t   = get_user_meta( $uid, LLM_User_Meta::LEARNING_LANG, true );
				$url = add_query_arg(
					array(
						'post_type' => LLM_STORY_CPT,
						'page'      => self::PAGE_SLUG,
						'user_id'   => $uid,
					),
					admin_url( 'edit.php' )
				);
				echo '<tr>';
				echo '<td><strong>' . esc_html( $u->display_name ) . '</strong><br /><span class="description">' . esc_html( $u->user_login ) . '</span></td>';
				echo '<td>' . esc_html( $u->user_email ) . '</td>';
				echo '<td>' . esc_html( ( $k ? $k : '—' ) . ' → ' . ( $t ? $t : '—' ) ) . '</td>';
				echo '<td>' . esc_html( (string) LLM_User_Stats::count_completed_phrases( $uid ) ) . '</td>';
				echo '<td>' . esc_html( (string) LLM_User_Stats::count_completed_stories( $uid ) ) . '</td>';
				echo '<td>' . esc_html( (string) LLM_User_Stats::get_balance( $uid ) ) . '</td>';
				echo '<td><a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html__( 'Scheda', 'llm-con-tabelle' ) . '</a></td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';

		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			$base = admin_url( 'edit.php?post_type=' . rawurlencode( LLM_STORY_CPT ) . '&page=' . rawurlencode( self::PAGE_SLUG ) . '&paged=%#%' );
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
			echo '<div class="wrap"><p>' . esc_html__( 'Utente non trovato.', 'llm-con-tabelle' ) . '</p></div>';
			return;
		}

		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Profilo salvato.', 'llm-con-tabelle' ) . '</p></div>';
		}

		$back = add_query_arg( array( 'post_type' => LLM_STORY_CPT, 'page' => self::PAGE_SLUG ), admin_url( 'edit.php' ) );

		$known  = get_user_meta( $user_id, LLM_User_Meta::INTERFACE_LANG, true );
		$learn  = get_user_meta( $user_id, LLM_User_Meta::LEARNING_LANG, true );
		$balance = LLM_User_Stats::get_balance( $user_id );
		$map    = LLM_User_Stats::get_phrase_map( $user_id );
		$unlocked = LLM_User_Stats::get_unlocked_story_ids( $user_id );
		$done_st = LLM_User_Stats::get_completed_stories_map( $user_id );
		$ledger  = LLM_User_Stats::get_ledger( $user_id );
		$econ    = LLM_User_Stats::sum_economy( $user_id );

		$lang_opts = LLM_Languages::get_codes();

		echo '<div class="wrap llm-users-wrap llm-user-detail">';
		echo '<p><a href="' . esc_url( $back ) . '">&larr; ' . esc_html__( 'Torna all\'elenco', 'llm-con-tabelle' ) . '</a></p>';
		echo '<h1>' . esc_html( sprintf( /* translators: %s display name */ __( 'Utente: %s', 'llm-con-tabelle' ), $user->display_name ) ) . '</h1>';
		echo '<p class="description">' . esc_html( $user->user_login ) . ' &mdash; ' . esc_html( $user->user_email ) . '</p>';

		echo '<form method="post" action="" class="llm-user-form">';
		wp_nonce_field( 'llm_user_profile', '_wpnonce' );
		echo '<input type="hidden" name="llm_user_profile_save" value="1" />';
		echo '<input type="hidden" name="llm_edit_user_id" value="' . esc_attr( (string) $user_id ) . '" />';

		echo '<h2>' . esc_html__( 'Profilo lingue', 'llm-con-tabelle' ) . '</h2>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="llm_interface_lang">' . esc_html__( 'Lingua interfaccia (nota)', 'llm-con-tabelle' ) . '</label></th><td>';
		echo '<select name="llm_interface_lang" id="llm_interface_lang">';
		echo '<option value="">' . esc_html__( '—', 'llm-con-tabelle' ) . '</option>';
		foreach ( $lang_opts as $code => $lab ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $code ),
				selected( $known, $code, false ),
				esc_html( $lab . ' (' . $code . ')' )
			);
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="llm_learning_lang">' . esc_html__( 'Lingua da imparare', 'llm-con-tabelle' ) . '</label></th><td>';
		echo '<select name="llm_learning_lang" id="llm_learning_lang">';
		echo '<option value="">' . esc_html__( '—', 'llm-con-tabelle' ) . '</option>';
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
			echo '<h2>' . esc_html__( 'Saldo coin', 'llm-con-tabelle' ) . '</h2>';
			echo '<table class="form-table"><tbody>';
			echo '<tr><th><label for="llm_coin_balance">' . esc_html__( 'Coin attuali', 'llm-con-tabelle' ) . '</label></th><td>';
			echo '<input type="number" min="0" step="1" name="llm_coin_balance" id="llm_coin_balance" value="' . esc_attr( (string) $balance ) . '" /> ';
			echo '<p class="description">' . esc_html__( 'Modifica manuale: verrà registrata una riga nel ledger.', 'llm-con-tabelle' ) . '</p>';
			echo '<input type="text" class="regular-text" name="llm_balance_note" placeholder="' . esc_attr__( 'Nota (opzionale)', 'llm-con-tabelle' ) . '" />';
			echo '</td></tr></tbody></table>';
		}

		submit_button( __( 'Salva profilo', 'llm-con-tabelle' ) );
		echo '</form>';

		echo '<div class="llm-user-cards">';
		self::card( __( 'Frasi completate (totale)', 'llm-con-tabelle' ), (string) LLM_User_Stats::count_completed_phrases( $user_id ) );
		self::card( __( 'Storie completate', 'llm-con-tabelle' ), (string) LLM_User_Stats::count_completed_stories( $user_id ) );
		self::card( __( 'Saldo coin', 'llm-con-tabelle' ), (string) $balance );
		self::card( __( 'Coin guadagnati (ledger)', 'llm-con-tabelle' ), (string) $econ['earned'] );
		self::card( __( 'Coin spesi (sblocchi)', 'llm-con-tabelle' ), (string) $econ['spent'] );
		self::card( __( 'Da frasi (+1 ciascuna)', 'llm-con-tabelle' ), (string) $econ['phrase_gain'] );
		self::card( __( 'Premi storia (somma)', 'llm-con-tabelle' ), (string) $econ['story_reward'] );
		self::card( __( 'Bravi ricevuti (sulle proprie attività)', 'llm-con-tabelle' ), (string) LLM_Community::count_bravi_received( $user_id ) );
		self::card( __( 'Bravi dati (like ad altri)', 'llm-con-tabelle' ), (string) LLM_Community::count_bravi_given( $user_id ) );
		echo '</div>';

		$detail_base = remove_query_arg(
			array( self::QUERY_OWN_PAGED, self::QUERY_GIVEN_PAGED ),
			add_query_arg(
				array(
					'post_type' => LLM_STORY_CPT,
					'page'      => self::PAGE_SLUG,
					'user_id'   => $user_id,
				),
				admin_url( 'edit.php' )
			)
		);

		$own_paged   = isset( $_GET[ self::QUERY_OWN_PAGED ] ) ? max( 1, absint( wp_unslash( $_GET[ self::QUERY_OWN_PAGED ] ) ) ) : 1;
		$given_paged = isset( $_GET[ self::QUERY_GIVEN_PAGED ] ) ? max( 1, absint( wp_unslash( $_GET[ self::QUERY_GIVEN_PAGED ] ) ) ) : 1;
		$per         = self::COMMUNITY_ACTIVITY_PER_PAGE;

		echo '<h2>' . esc_html__( 'Community — le tue attività e i Bravi', 'llm-con-tabelle' ) . '</h2>';
		echo '<h3>' . esc_html__( 'Attività pubblicate da questo utente', 'llm-con-tabelle' ) . '</h3>';
		$own_act = new WP_Query(
			array(
				'post_type'      => LLM_ACTIVITY_CPT,
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
					'post_type'      => LLM_ACTIVITY_CPT,
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
		echo '<th>' . esc_html__( 'Data attività', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Tipo', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Dettaglio', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Bravi ricevuti', 'llm-con-tabelle' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( ! $own_act->have_posts() ) {
			echo '<tr><td colspan="4">' . esc_html__( 'Nessuna attività in feed.', 'llm-con-tabelle' ) . '</td></tr>';
		} else {
			while ( $own_act->have_posts() ) {
				$own_act->the_post();
				$aid  = (int) get_the_ID();
				$type = (string) get_post_meta( $aid, LLM_Community::META_TYPE, true );
				$n    = count( LLM_Community::get_kudos_user_ids( $aid ) );
				echo '<tr>';
				echo '<td>' . esc_html( get_the_date( 'Y-m-d H:i' ) ) . '</td>';
				echo '<td>' . esc_html( LLM_Community::type_label( $type ) ) . '</td>';
				echo '<td>' . esc_html( LLM_Community::format_detail( $aid ) ) . '</td>';
				echo '<td>' . esc_html( (string) $n ) . '</td>';
				echo '</tr>';
			}
			wp_reset_postdata();
		}
		echo '</tbody></table>';
		self::render_community_table_pagination( $detail_base, self::QUERY_OWN_PAGED, $own_paged, $own_total_pages, $given_paged, self::QUERY_GIVEN_PAGED );

		echo '<h3>' . esc_html__( 'Bravo messo ad attività di altri utenti', 'llm-con-tabelle' ) . '</h3>';
		$given_rows     = LLM_Community::get_bravo_given_raw( $user_id );
		$given_reversed = array_reverse( $given_rows );
		$given_count    = count( $given_reversed );
		$given_total_pages = max( 1, (int) ceil( $given_count / $per ) );
		if ( $given_paged > $given_total_pages && $given_count > 0 ) {
			$given_paged = $given_total_pages;
		}
		$given_offset = ( $given_paged - 1 ) * $per;
		$given_slice  = array_slice( $given_reversed, $given_offset, $per );
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Data Bravo', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Autore attività', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Tipo', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Dettaglio', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Data attività', 'llm-con-tabelle' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( empty( $given_rows ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'Nessun Bravo dato.', 'llm-con-tabelle' ) . '</td></tr>';
		} else {
			foreach ( $given_slice as $row ) {
				$aid = isset( $row['activity_id'] ) ? absint( $row['activity_id'] ) : 0;
				$ap  = get_post( $aid );
				if ( ! $ap || LLM_ACTIVITY_CPT !== $ap->post_type ) {
					echo '<tr><td>' . esc_html( isset( $row['ts'] ) ? (string) $row['ts'] : '—' ) . '</td><td colspan="4">';
					echo esc_html( sprintf( /* translators: %d activity id */ __( 'Attività #%d non trovata.', 'llm-con-tabelle' ), $aid ) );
					echo '</td></tr>';
					continue;
				}
				$auth = (int) $ap->post_author;
				$au   = get_userdata( $auth );
				$type = (string) get_post_meta( $aid, LLM_Community::META_TYPE, true );
				echo '<tr>';
				echo '<td>' . esc_html( isset( $row['ts'] ) ? (string) $row['ts'] : '—' ) . '</td>';
				echo '<td>' . ( $au ? esc_html( $au->display_name ) : '—' ) . '</td>';
				echo '<td>' . esc_html( LLM_Community::type_label( $type ) ) . '</td>';
				echo '<td>' . esc_html( LLM_Community::format_detail( $aid ) ) . '</td>';
				echo '<td>' . esc_html( get_the_date( 'Y-m-d H:i', $ap ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		self::render_community_table_pagination( $detail_base, self::QUERY_GIVEN_PAGED, $given_paged, $given_total_pages, $own_paged, self::QUERY_OWN_PAGED );

		echo '<h2>' . esc_html__( 'Frasi completate (dettaglio)', 'llm-con-tabelle' ) . '</h2>';
		echo '<table class="widefat striped llm-phrases-detail"><thead><tr>';
		echo '<th>' . esc_html__( 'Storia', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Frase #', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Testo (lingua interfaccia)', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Testo (lingua obiettivo)', 'llm-con-tabelle' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( empty( $map ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'Nessun dato.', 'llm-con-tabelle' ) . '</td></tr>';
		} else {
			foreach ( $map as $sid => $indices ) {
				$story_id = (int) $sid;
				$title    = get_the_title( $story_id );
				if ( ! $title ) {
					$title = '#' . $sid;
				}
				$story_phrases = LLM_Story_Repository::get_phrases( $story_id );
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
					echo '<td class="llm-phrase-cell">' . esc_html( $if !== '' ? $if : '—' ) . '</td>';
					echo '<td class="llm-phrase-cell">' . esc_html( $tg !== '' ? $tg : '—' ) . '</td>';
					echo '</tr>';
				}
			}
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Storie sbloccate / acquistate', 'llm-con-tabelle' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Storia', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Costo impostato', 'llm-con-tabelle' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( empty( $unlocked ) ) {
			echo '<tr><td colspan="2">' . esc_html__( 'Nessuna.', 'llm-con-tabelle' ) . '</td></tr>';
		} else {
			foreach ( $unlocked as $sid ) {
				$cost = (int) get_post_meta( $sid, LLM_Story_Meta::COIN_COST, true );
				$title = get_the_title( $sid ) ? get_the_title( $sid ) : '#' . $sid;
				echo '<tr><td>' . esc_html( $title ) . '</td><td>' . esc_html( (string) $cost ) . '</td></tr>';
			}
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Storie completate (ultima frase)', 'llm-con-tabelle' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Storia', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Data/ora', 'llm-con-tabelle' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( empty( $done_st ) ) {
			echo '<tr><td colspan="2">' . esc_html__( 'Nessuna.', 'llm-con-tabelle' ) . '</td></tr>';
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

		echo '<h2>' . esc_html__( 'Attività economica (ledger)', 'llm-con-tabelle' ) . '</h2>';
		echo '<table class="widefat striped llm-ledger-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Data', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Tipo', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Importo', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Saldo dopo', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Storia', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Frase', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Nota', 'llm-con-tabelle' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $ledger ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'Nessun movimento.', 'llm-con-tabelle' ) . '</td></tr>';
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

		echo '<p class="description">' . esc_html__( 'Ogni frase completata +1 coin; allo sblocco si pagano i coin della storia; al completamento tutte le frasi si applica il premio della storia.', 'llm-con-tabelle' ) . '</p>';

		echo '</div>';
	}

	/**
	 * @param string $label Etichetta.
	 * @param string $value Valore.
	 */
	private static function card( $label, $value ) {
		echo '<div class="llm-stat-card"><span class="llm-stat-label">' . esc_html( $label ) . '</span><strong class="llm-stat-value">' . esc_html( $value ) . '</strong></div>';
	}

	/**
	 * Paginazione sotto una tabella Community nella scheda utente (mantiene la pagina dell’altro elenco).
	 *
	 * @param string $detail_base URL senza llm_own_paged / llm_given_paged.
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
		echo '<div class="tablenav llm-community-pagination"><div class="tablenav-pages">';
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
