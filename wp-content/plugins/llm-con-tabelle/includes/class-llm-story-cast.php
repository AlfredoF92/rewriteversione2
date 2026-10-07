<?php
/**
 * Cast delle storie: catalogo ruoli + assegnazione utenti WP per storia.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Story_Cast {

	const PAGE_SLUG = 'llm-cast-roles';

	const NONCE_ROLES = 'llm_cast_roles';

	const OPT_DIRECTOR_BACKFILL = 'llm_cast_default_director_backfill';

	const DEFAULT_DIRECTOR_LOGIN = 'admin';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_roles_post' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'deleted_user', array( __CLASS__, 'on_deleted_user' ) );
		add_action( 'init', array( __CLASS__, 'maybe_backfill_default_director' ), 30 );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . LLM_STORY_CPT,
			__( 'Cast / Ruoli', 'llm-con-tabelle' ),
			__( 'Cast / Ruoli', 'llm-con-tabelle' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_roles_page' )
		);
	}

	/**
	 * @return array<int, array{role_key:string,label:string,sort_order:int}>
	 */
	public static function get_roles() {
		global $wpdb;

		$table = LLM_Tabelle_Database::table( 'llm_cast_roles' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT role_key, label, sort_order FROM {$table} ORDER BY sort_order ASC, label ASC", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $row ) {
			$key = sanitize_key( (string) $row['role_key'] );
			if ( '' === $key ) {
				continue;
			}
			$out[] = array(
				'role_key'   => $key,
				'label'      => (string) $row['label'],
				'sort_order' => (int) $row['sort_order'],
			);
		}
		return $out;
	}

	/**
	 * Ruoli con almeno un utente ancora esistente, per il front.
	 *
	 * @param int $story_id ID storia.
	 * @return array<int, array{role_key:string,label:string,users:array<int, array{id:int,login:string}>}>
	 */
	public static function get_grouped_for_story( $story_id ) {
		global $wpdb;

		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return array();
		}

		$roles = self::get_roles();
		if ( ! $roles ) {
			return array();
		}

		$cast_t = LLM_Tabelle_Database::table( 'llm_story_cast' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role_key, user_id, sort_order FROM {$cast_t} WHERE story_id = %d ORDER BY sort_order ASC, id ASC",
				$story_id
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$by_role = array();
		foreach ( $rows as $row ) {
			$key = sanitize_key( (string) $row['role_key'] );
			$uid = absint( $row['user_id'] );
			if ( '' === $key || ! $uid ) {
				continue;
			}
			if ( ! isset( $by_role[ $key ] ) ) {
				$by_role[ $key ] = array();
			}
			$by_role[ $key ][] = $uid;
		}

		$out = array();
		foreach ( $roles as $role ) {
			$key = $role['role_key'];
			if ( empty( $by_role[ $key ] ) ) {
				continue;
			}
			$people = array();
			$seen   = array();
			foreach ( $by_role[ $key ] as $uid ) {
				if ( isset( $seen[ $uid ] ) ) {
					continue;
				}
				$seen[ $uid ] = true;
				$user         = get_userdata( $uid );
				if ( ! $user || ! $user->user_login ) {
					continue;
				}
				$people[] = array(
					'id'    => $uid,
					'login' => (string) $user->user_login,
				);
			}
			if ( ! $people ) {
				continue;
			}
			$out[] = array(
				'role_key' => $key,
				'label'    => $role['label'],
				'users'    => $people,
			);
		}
		return $out;
	}

	/**
	 * User ID assegnati, raggruppati per role_key.
	 *
	 * @param int $story_id ID storia.
	 * @return array<string, int[]>
	 */
	public static function get_user_ids_by_role( $story_id ) {
		global $wpdb;

		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return array();
		}

		$cast_t = LLM_Tabelle_Database::table( 'llm_story_cast' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role_key, user_id FROM {$cast_t} WHERE story_id = %d ORDER BY sort_order ASC, id ASC",
				$story_id
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			$key = sanitize_key( (string) $row['role_key'] );
			$uid = absint( $row['user_id'] );
			if ( '' === $key || ! $uid ) {
				continue;
			}
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = array();
			}
			$out[ $key ][] = $uid;
		}
		return $out;
	}

	/**
	 * Assegnazioni Cast di un utente.
	 *
	 * @param int $user_id ID utente.
	 * @return array<int, array{story_id:int,role_key:string}>
	 */
	public static function get_assignments_for_user( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}
		$cast_t = LLM_Tabelle_Database::table( 'llm_story_cast' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT story_id, role_key FROM {$cast_t} WHERE user_id = %d ORDER BY id ASC",
				$user_id
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $row ) {
			$sid = absint( $row['story_id'] );
			$key = sanitize_key( (string) $row['role_key'] );
			if ( ! $sid || '' === $key ) {
				continue;
			}
			$out[] = array(
				'story_id' => $sid,
				'role_key' => $key,
			);
		}
		return $out;
	}

	/**
	 * Ruoli Cast distinti in cui compare l'utente.
	 *
	 * @param int $user_id ID utente.
	 * @return array<int, array{role_key:string,label:string}>
	 */
	public static function get_roles_for_user( $user_id ) {
		$assigned = self::get_assignments_for_user( $user_id );
		if ( ! $assigned ) {
			return array();
		}
		$have = array();
		foreach ( $assigned as $row ) {
			$have[ $row['role_key'] ] = true;
		}
		$out = array();
		foreach ( self::get_roles() as $role ) {
			if ( isset( $have[ $role['role_key'] ] ) ) {
				$out[] = array(
					'role_key' => $role['role_key'],
					'label'    => $role['label'],
				);
			}
		}
		return $out;
	}

	/**
	 * Sostituisce il cast di una storia.
	 *
	 * @param int                          $story_id ID storia.
	 * @param array<string, int[]>         $by_role  role_key => user_id[].
	 */
	public static function replace_for_story( $story_id, $by_role ) {
		global $wpdb;

		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return;
		}

		$valid_keys = array();
		foreach ( self::get_roles() as $role ) {
			$valid_keys[ $role['role_key'] ] = true;
		}

		$table = LLM_Tabelle_Database::table( 'llm_story_cast' );
		$wpdb->delete( $table, array( 'story_id' => $story_id ), array( '%d' ) );

		if ( ! is_array( $by_role ) ) {
			return;
		}

		foreach ( $by_role as $role_key => $user_ids ) {
			$role_key = sanitize_key( (string) $role_key );
			if ( '' === $role_key || ! isset( $valid_keys[ $role_key ] ) ) {
				continue;
			}
			if ( ! is_array( $user_ids ) ) {
				continue;
			}
			$order = 0;
			$seen  = array();
			foreach ( $user_ids as $uid ) {
				$uid = absint( $uid );
				if ( ! $uid || isset( $seen[ $uid ] ) ) {
					continue;
				}
				if ( ! get_userdata( $uid ) ) {
					continue;
				}
				$seen[ $uid ] = true;
				$wpdb->insert(
					$table,
					array(
						'story_id'   => $story_id,
						'role_key'   => $role_key,
						'user_id'    => $uid,
						'sort_order' => $order,
					),
					array( '%d', '%s', '%d', '%d' )
				);
				++$order;
			}
		}
	}

	/**
	 * Salva il metabox dal POST dell'editor storia.
	 *
	 * @param int $story_id ID storia.
	 */
	public static function save_from_post( $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return;
		}

		$posted = isset( $_POST['llm_cast'] ) && is_array( $_POST['llm_cast'] )
			? wp_unslash( $_POST['llm_cast'] )
			: array();
		$add    = isset( $_POST['llm_cast_add'] ) && is_array( $_POST['llm_cast_add'] )
			? wp_unslash( $_POST['llm_cast_add'] )
			: array();

		$by_role = array();
		foreach ( self::get_roles() as $role ) {
			$key = $role['role_key'];
			$ids = array();
			if ( isset( $posted[ $key ] ) && is_array( $posted[ $key ] ) ) {
				foreach ( $posted[ $key ] as $uid ) {
					$ids[] = absint( $uid );
				}
			}
			if ( isset( $add[ $key ] ) ) {
				$ids = array_merge( $ids, self::user_ids_from_login_list( (string) $add[ $key ] ) );
			}
			$by_role[ $key ] = $ids;
		}

		self::replace_for_story( $story_id, $by_role );

		$after = self::get_user_ids_by_role( $story_id );
		if ( empty( $after['director'] ) ) {
			self::assign_user_role( $story_id, 'director', self::default_director_user_id() );
		}
	}

	/**
	 * Utente Director di default (login admin, altrimenti primo amministratore).
	 *
	 * @return int
	 */
	public static function default_director_user_id() {
		$u = get_user_by( 'login', self::DEFAULT_DIRECTOR_LOGIN );
		if ( $u instanceof WP_User ) {
			return (int) $u->ID;
		}
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
			)
		);
		if ( ! empty( $admins ) && $admins[0] instanceof WP_User ) {
			return (int) $admins[0]->ID;
		}
		return 1;
	}

	/**
	 * Aggiunge un utente a un ruolo senza togliere gli altri.
	 *
	 * @param int    $story_id ID storia.
	 * @param string $role_key Ruolo.
	 * @param int    $user_id  ID utente.
	 */
	public static function assign_user_role( $story_id, $role_key, $user_id ) {
		global $wpdb;

		$story_id = absint( $story_id );
		$user_id  = absint( $user_id );
		$role_key = sanitize_key( $role_key );
		if ( ! $story_id || ! $user_id || '' === $role_key || ! get_userdata( $user_id ) ) {
			return;
		}
		$table = LLM_Tabelle_Database::table( 'llm_story_cast' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table} (story_id, role_key, user_id, sort_order) VALUES (%d, %s, %d, 0)",
				$story_id,
				$role_key,
				$user_id
			)
		);
	}

	/**
	 * Tutte le storie: Director = admin, se manca.
	 */
	public static function maybe_backfill_default_director() {
		if ( '1' === (string) get_option( self::OPT_DIRECTOR_BACKFILL, '' ) ) {
			return;
		}
		$admin_id = self::default_director_user_id();
		if ( ! $admin_id || ! get_userdata( $admin_id ) ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'      => LLM_STORY_CPT,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		if ( is_array( $ids ) ) {
			foreach ( $ids as $id ) {
				self::assign_user_role( (int) $id, 'director', $admin_id );
			}
		}
		update_option( self::OPT_DIRECTOR_BACKFILL, '1', false );
	}

	/**
	 * @param string $raw Username separati da virgola o spazio.
	 * @return int[]
	 */
	private static function user_ids_from_login_list( $raw ) {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return array();
		}
		$parts = preg_split( '/[\s,;]+/', $raw );
		if ( ! is_array( $parts ) ) {
			return array();
		}
		$ids = array();
		foreach ( $parts as $login ) {
			$login = sanitize_user( $login, true );
			if ( '' === $login ) {
				continue;
			}
			$user = get_user_by( 'login', $login );
			if ( $user ) {
				$ids[] = (int) $user->ID;
			}
		}
		return $ids;
	}

	/**
	 * @param int $user_id ID utente eliminato.
	 */
	public static function on_deleted_user( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return;
		}
		$table = LLM_Tabelle_Database::table( 'llm_story_cast' );
		$wpdb->delete( $table, array( 'user_id' => $user_id ), array( '%d' ) );
	}

	/**
	 * @param string $role_key Chiave ruolo.
	 * @return int
	 */
	public static function count_assignments( $role_key ) {
		global $wpdb;

		$role_key = sanitize_key( $role_key );
		if ( '' === $role_key ) {
			return 0;
		}
		$table = LLM_Tabelle_Database::table( 'llm_story_cast' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE role_key = %s", $role_key )
		);
	}

	public static function handle_roles_post() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_POST['llm_cast_roles_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['llm_cast_roles_nonce'] ) ), self::NONCE_ROLES ) ) {
			return;
		}

		$redirect = admin_url( 'edit.php?post_type=' . LLM_STORY_CPT . '&page=' . self::PAGE_SLUG );

		if ( isset( $_POST['llm_cast_role_add'] ) ) {
			$key   = isset( $_POST['llm_new_role_key'] ) ? sanitize_key( wp_unslash( $_POST['llm_new_role_key'] ) ) : '';
			$label = isset( $_POST['llm_new_role_label'] ) ? sanitize_text_field( wp_unslash( $_POST['llm_new_role_label'] ) ) : '';
			$sort  = isset( $_POST['llm_new_role_sort'] ) ? (int) $_POST['llm_new_role_sort'] : 0;
			$result = self::insert_role( $key, $label, $sort );
			$flag   = is_wp_error( $result ) ? 'err=' . rawurlencode( $result->get_error_message() ) : 'added=1';
			wp_safe_redirect( $redirect . '&' . $flag );
			exit;
		}

		if ( isset( $_POST['llm_cast_role_delete'] ) ) {
			$key    = sanitize_key( wp_unslash( $_POST['llm_cast_role_delete'] ) );
			$result = self::delete_role( $key );
			$flag   = is_wp_error( $result ) ? 'err=' . rawurlencode( $result->get_error_message() ) : 'deleted=1';
			wp_safe_redirect( $redirect . '&' . $flag );
			exit;
		}

		if ( isset( $_POST['llm_cast_roles_save'] ) ) {
			$raw = isset( $_POST['llm_roles'] ) && is_array( $_POST['llm_roles'] )
				? wp_unslash( $_POST['llm_roles'] )
				: array();
			self::update_roles( $raw );
			wp_safe_redirect( $redirect . '&updated=1' );
			exit;
		}
	}

	/**
	 * @param string $key   Chiave.
	 * @param string $label Etichetta.
	 * @param int    $sort  Ordine.
	 * @return true|WP_Error
	 */
	private static function insert_role( $key, $label, $sort ) {
		global $wpdb;

		$key   = sanitize_key( $key );
		$label = trim( $label );
		if ( '' === $key ) {
			return new WP_Error( 'cast_key', __( 'La chiave del ruolo è obbligatoria (solo lettere, numeri, trattino).', 'llm-con-tabelle' ) );
		}
		if ( strlen( $key ) > 64 ) {
			return new WP_Error( 'cast_key', __( 'Chiave troppo lunga.', 'llm-con-tabelle' ) );
		}
		if ( '' === $label ) {
			return new WP_Error( 'cast_label', __( 'Il nome del ruolo è obbligatorio.', 'llm-con-tabelle' ) );
		}

		$table = LLM_Tabelle_Database::table( 'llm_cast_roles' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT role_key FROM {$table} WHERE role_key = %s", $key ) );
		if ( $exists ) {
			return new WP_Error( 'cast_dup', __( 'Esiste già un ruolo con questa chiave.', 'llm-con-tabelle' ) );
		}

		$ok = $wpdb->insert(
			$table,
			array(
				'role_key'   => $key,
				'label'      => $label,
				'sort_order' => (int) $sort,
			),
			array( '%s', '%s', '%d' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'cast_db', __( 'Salvataggio non riuscito.', 'llm-con-tabelle' ) );
		}
		return true;
	}

	/**
	 * @param array<string, array<string, mixed>> $raw POST llm_roles.
	 */
	private static function update_roles( $raw ) {
		global $wpdb;

		$table = LLM_Tabelle_Database::table( 'llm_cast_roles' );
		foreach ( self::get_roles() as $role ) {
			$key = $role['role_key'];
			if ( ! isset( $raw[ $key ] ) || ! is_array( $raw[ $key ] ) ) {
				continue;
			}
			$label = isset( $raw[ $key ]['label'] ) ? sanitize_text_field( (string) $raw[ $key ]['label'] ) : '';
			$sort  = isset( $raw[ $key ]['sort'] ) ? (int) $raw[ $key ]['sort'] : 0;
			if ( '' === $label ) {
				$label = $role['label'];
			}
			$wpdb->update(
				$table,
				array(
					'label'      => $label,
					'sort_order' => $sort,
				),
				array( 'role_key' => $key ),
				array( '%s', '%d' ),
				array( '%s' )
			);
		}
	}

	/**
	 * @param string $key Chiave ruolo.
	 * @return true|WP_Error
	 */
	private static function delete_role( $key ) {
		global $wpdb;

		$key = sanitize_key( $key );
		if ( '' === $key ) {
			return new WP_Error( 'cast_key', __( 'Ruolo non valido.', 'llm-con-tabelle' ) );
		}
		$used = self::count_assignments( $key );
		if ( $used > 0 ) {
			return new WP_Error(
				'cast_used',
				sprintf(
					/* translators: %d: assignment count */
					__( 'Non puoi eliminare questo ruolo: è assegnato in %d storie. Togli prima le persone.', 'llm-con-tabelle' ),
					$used
				)
			);
		}
		$table = LLM_Tabelle_Database::table( 'llm_cast_roles' );
		$wpdb->delete( $table, array( 'role_key' => $key ), array( '%s' ) );
		return true;
	}

	public static function render_roles_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non hai i permessi necessari.', 'llm-con-tabelle' ) );
		}

		$roles = self::get_roles();
		$err   = isset( $_GET['err'] ) ? sanitize_text_field( wp_unslash( $_GET['err'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Cast / Ruoli', 'llm-con-tabelle' ); ?></h1>
			<p><?php esc_html_e( 'Catalogo dei ruoli del Cast (Director, Sceneggiatura, …). Nelle schede storia assegni uno o più utenti WordPress a ogni ruolo. I ruoli senza persone non compaiono in front. In fase 1 il nome pubblico è lo username, senza link al profilo.', 'llm-con-tabelle' ); ?></p>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ruoli aggiornati.', 'llm-con-tabelle' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['added'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ruolo aggiunto.', 'llm-con-tabelle' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['deleted'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ruolo eliminato.', 'llm-con-tabelle' ); ?></p></div>
			<?php endif; ?>
			<?php if ( $err ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $err ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Ruoli esistenti', 'llm-con-tabelle' ); ?></h2>
			<?php if ( ! $roles ) : ?>
				<p><?php esc_html_e( 'Nessun ruolo. Aggiungine uno qui sotto.', 'llm-con-tabelle' ); ?></p>
			<?php else : ?>
				<form method="post" action="">
					<?php wp_nonce_field( self::NONCE_ROLES, 'llm_cast_roles_nonce' ); ?>
					<table class="widefat striped" style="max-width:720px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Chiave', 'llm-con-tabelle' ); ?></th>
								<th><?php esc_html_e( 'Nome visibile', 'llm-con-tabelle' ); ?></th>
								<th><?php esc_html_e( 'Ordine', 'llm-con-tabelle' ); ?></th>
								<th><?php esc_html_e( 'Assegnazioni', 'llm-con-tabelle' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $roles as $role ) : ?>
								<?php $used = self::count_assignments( $role['role_key'] ); ?>
								<tr>
									<td><code><?php echo esc_html( $role['role_key'] ); ?></code></td>
									<td>
										<input
											type="text"
											class="regular-text"
											name="llm_roles[<?php echo esc_attr( $role['role_key'] ); ?>][label]"
											value="<?php echo esc_attr( $role['label'] ); ?>"
											required
										/>
									</td>
									<td>
										<input
											type="number"
											name="llm_roles[<?php echo esc_attr( $role['role_key'] ); ?>][sort]"
											value="<?php echo esc_attr( (string) $role['sort_order'] ); ?>"
											style="width:5em;"
										/>
									</td>
									<td><?php echo esc_html( (string) $used ); ?></td>
									<td>
										<?php if ( $used > 0 ) : ?>
											<span class="description"><?php esc_html_e( 'In uso', 'llm-con-tabelle' ); ?></span>
										<?php else : ?>
											<button
												type="submit"
												class="button-link-delete"
												name="llm_cast_role_delete"
												value="<?php echo esc_attr( $role['role_key'] ); ?>"
												onclick="return confirm('<?php echo esc_js( __( 'Eliminare questo ruolo?', 'llm-con-tabelle' ) ); ?>');"
											><?php esc_html_e( 'Elimina', 'llm-con-tabelle' ); ?></button>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="submit">
						<button type="submit" class="button button-primary" name="llm_cast_roles_save" value="1">
							<?php esc_html_e( 'Salva ruoli', 'llm-con-tabelle' ); ?>
						</button>
					</p>
				</form>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Aggiungi ruolo', 'llm-con-tabelle' ); ?></h2>
			<form method="post" action="" style="max-width:520px;">
				<?php wp_nonce_field( self::NONCE_ROLES, 'llm_cast_roles_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="llm_new_role_key"><?php esc_html_e( 'Chiave', 'llm-con-tabelle' ); ?></label></th>
						<td>
							<input type="text" id="llm_new_role_key" name="llm_new_role_key" class="regular-text" required pattern="[a-z0-9_-]+" />
							<p class="description"><?php esc_html_e( 'Minuscolo, senza spazi. Non si può cambiare dopo.', 'llm-con-tabelle' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="llm_new_role_label"><?php esc_html_e( 'Nome visibile', 'llm-con-tabelle' ); ?></label></th>
						<td><input type="text" id="llm_new_role_label" name="llm_new_role_label" class="regular-text" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="llm_new_role_sort"><?php esc_html_e( 'Ordine', 'llm-con-tabelle' ); ?></label></th>
						<td><input type="number" id="llm_new_role_sort" name="llm_new_role_sort" value="50" style="width:5em;" /></td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button" name="llm_cast_role_add" value="1">
						<?php esc_html_e( 'Aggiungi ruolo', 'llm-con-tabelle' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	public static function meta_boxes() {
		add_meta_box(
			'llm_story_cast',
			__( 'Cast', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_metabox' ),
			LLM_STORY_CPT,
			'normal',
			'high'
		);
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public static function render_metabox( $post ) {
		$roles    = self::get_roles();
		$assigned = self::get_user_ids_by_role( (int) $post->ID );
		$logins   = self::login_datalist_values();
		?>
		<p class="description">
			<?php esc_html_e( 'Assegna gli utenti WordPress (gli stessi di Storie → Utenti LLM) a uno o più ruoli. I ruoli vuoti non si vedono in front. Il nome pubblico è lo username. Catalogo ruoli: Storie → Cast / Ruoli.', 'llm-con-tabelle' ); ?>
		</p>
		<?php if ( ! $roles ) : ?>
			<p><?php esc_html_e( 'Nessun ruolo nel catalogo. Creane almeno uno in Storie → Cast / Ruoli.', 'llm-con-tabelle' ); ?></p>
			<?php
			return;
		endif;
		?>
		<?php if ( $logins ) : ?>
			<datalist id="llm-cast-user-logins">
				<?php foreach ( $logins as $login ) : ?>
					<option value="<?php echo esc_attr( $login ); ?>"></option>
				<?php endforeach; ?>
			</datalist>
		<?php endif; ?>
		<div class="llm-cast-metabox">
			<?php foreach ( $roles as $role ) : ?>
				<?php
				$key  = $role['role_key'];
				$uids = isset( $assigned[ $key ] ) ? $assigned[ $key ] : array();
				if ( 'director' === $key && ! $uids ) {
					$def = self::default_director_user_id();
					if ( $def ) {
						$uids = array( $def );
					}
				}
				?>
				<fieldset class="llm-cast-role">
					<legend><?php echo esc_html( $role['label'] ); ?></legend>
					<?php if ( $uids ) : ?>
						<ul class="llm-cast-assigned">
							<?php foreach ( $uids as $uid ) : ?>
								<?php
								$user = get_userdata( $uid );
								if ( ! $user ) {
									continue;
								}
								$label = $user->user_login;
								if ( $user->display_name && $user->display_name !== $user->user_login ) {
									$label .= ' (' . $user->display_name . ')';
								}
								?>
								<li>
									<label>
										<input
											type="checkbox"
											name="llm_cast[<?php echo esc_attr( $key ); ?>][]"
											value="<?php echo esc_attr( (string) $uid ); ?>"
											checked
										/>
										<?php echo esc_html( $label ); ?>
									</label>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="description llm-cast-empty"><?php esc_html_e( 'Nessuno assegnato.', 'llm-con-tabelle' ); ?></p>
					<?php endif; ?>
					<p>
						<label>
							<?php esc_html_e( 'Aggiungi username', 'llm-con-tabelle' ); ?>
							<input
								type="text"
								class="regular-text"
								name="llm_cast_add[<?php echo esc_attr( $key ); ?>]"
								<?php echo $logins ? 'list="llm-cast-user-logins"' : ''; ?>
								placeholder="<?php esc_attr_e( 'es. mario, lucia', 'llm-con-tabelle' ); ?>"
								autocomplete="off"
							/>
						</label>
					</p>
				</fieldset>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * @return string[]
	 */
	private static function login_datalist_values() {
		$users = get_users(
			array(
				'fields'  => 'user_login',
				'orderby' => 'login',
				'order'   => 'ASC',
				'number'  => 250,
			)
		);
		if ( ! is_array( $users ) ) {
			return array();
		}
		$out = array();
		foreach ( $users as $login ) {
			$login = (string) $login;
			if ( '' !== $login ) {
				$out[] = $login;
			}
		}
		return $out;
	}

	/**
	 * HTML Cast sotto la trama (solo ruoli con persone).
	 *
	 * @param int    $story_id ID storia.
	 * @param string $heading  Etichetta (Cast / Obsada / …).
	 */
	public static function render_frontend( $story_id, $heading ) {
		$groups = self::get_grouped_for_story( $story_id );
		if ( ! $groups ) {
			return;
		}
		$heading = trim( (string) $heading );
		if ( '' === $heading ) {
			$heading = 'Cast';
		}
		?>
		<div class="llm-story-hero__cast-block">
			<p class="llm-story-hero__plot-label"><?php echo esc_html( $heading ); ?></p>
			<ul class="llm-story-hero__cast">
				<?php foreach ( $groups as $group ) : ?>
					<li class="llm-story-hero__cast-role">
						<span class="llm-story-hero__cast-role-label"><?php echo esc_html( rtrim( (string) $group['label'], ':' ) ); ?>:</span>
						<span class="llm-story-hero__cast-people"><?php echo self::people_links( $group['users'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * @param array<int, array{id:int,login:string}> $users Utenti.
	 * @return string
	 */
	private static function people_links( $users ) {
		$bits = array();
		foreach ( $users as $u ) {
			$login = isset( $u['login'] ) ? trim( (string) $u['login'] ) : '';
			$uid   = isset( $u['id'] ) ? absint( $u['id'] ) : 0;
			if ( '' === $login ) {
				continue;
			}
			$url = ( $uid && class_exists( 'LLM_Scheda_Utente' ) )
				? LLM_Scheda_Utente::profile_url( $uid )
				: ( $uid ? get_author_posts_url( $uid ) : '' );
			if ( $url ) {
				$bits[] = '<a class="llm-story-hero__cast-user" href="' . esc_url( $url ) . '">' . esc_html( $login ) . '</a>';
			} else {
				$bits[] = '<span class="llm-story-hero__cast-user">' . esc_html( $login ) . '</span>';
			}
		}
		return implode( '<span class="llm-story-hero__cast-sep">, </span>', $bits );
	}
}
