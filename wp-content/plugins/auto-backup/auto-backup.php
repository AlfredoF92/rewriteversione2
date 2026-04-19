<?php
/**
 * Plugin Name: Auto Backup
 * Description: Backup automatico giornaliero di file e database con pannello di gestione
 * Version:     1.0.0
 * Author:      Custom Plugin
 * Text Domain: auto-backup
 */

defined( 'ABSPATH' ) || exit;

define( 'AUTO_BACKUP_DIR',      WP_CONTENT_DIR . '/backups/' );
define( 'AUTO_BACKUP_LOG_FILE', AUTO_BACKUP_DIR . 'backup_log.json' );
define( 'AUTO_BACKUP_CRON',     'auto_backup_daily_cron' );

// ===========================================================
// ATTIVAZIONE / DISATTIVAZIONE
// ===========================================================

register_activation_hook( __FILE__, 'auto_backup_activate' );
register_deactivation_hook( __FILE__, 'auto_backup_deactivate' );

function auto_backup_activate() {
	auto_backup_create_dir();
	auto_backup_reschedule();
}

function auto_backup_deactivate() {
	wp_clear_scheduled_hook( AUTO_BACKUP_CRON );
}

function auto_backup_create_dir() {
	if ( ! file_exists( AUTO_BACKUP_DIR ) ) {
		wp_mkdir_p( AUTO_BACKUP_DIR );
	}
	$htaccess = AUTO_BACKUP_DIR . '.htaccess';
	if ( ! file_exists( $htaccess ) ) {
		file_put_contents( $htaccess, "Options -Indexes\nDeny from all\n" );
	}
	$index = AUTO_BACKUP_DIR . 'index.php';
	if ( ! file_exists( $index ) ) {
		file_put_contents( $index, '<?php // Silence is golden' );
	}
}

// ===========================================================
// CRON
// ===========================================================

function auto_backup_reschedule() {
	wp_clear_scheduled_hook( AUTO_BACKUP_CRON );
	$time      = get_option( 'auto_backup_time', '02:00' );
	$timestamp = auto_backup_next_run( $time );
	wp_schedule_event( $timestamp, 'daily', AUTO_BACKUP_CRON );
}

function auto_backup_next_run( $time_str ) {
	list( $h, $m ) = array_map( 'intval', explode( ':', $time_str ) );
	$now   = current_time( 'timestamp' );
	$today = mktime( $h, $m, 0, (int) date( 'n', $now ), (int) date( 'j', $now ), (int) date( 'Y', $now ) );
	return ( $today > $now ) ? $today : $today + DAY_IN_SECONDS;
}

add_action( AUTO_BACKUP_CRON, 'auto_backup_run' );

// ===========================================================
// LOGICA DI BACKUP
// ===========================================================

function auto_backup_run() {
	@set_time_limit( 0 );
	@ini_set( 'memory_limit', '512M' );

	auto_backup_create_dir();

	$date        = date( 'Ymd_His' );
	$backup_name = 'backup_' . $date;
	$zip_path    = AUTO_BACKUP_DIR . $backup_name . '.zip';
	$sql_tmp     = AUTO_BACKUP_DIR . $backup_name . '_tmp.sql';

	$log = array(
		'date'     => date( 'd/m/Y H:i:s' ),
		'file'     => $backup_name . '.zip',
		'status'   => 'error',
		'db_size'  => 0,
		'zip_size' => 0,
		'error'    => '',
	);

	try {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new Exception( 'ZipArchive non disponibile su questo server PHP.' );
		}

		// 1. Esporta il database in un file SQL temporaneo
		auto_backup_export_db( $sql_tmp );
		$log['db_size'] = file_exists( $sql_tmp ) ? filesize( $sql_tmp ) : 0;

		// 2. Crea il file ZIP
		$zip = new ZipArchive();
		if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
			throw new Exception( 'Impossibile creare il file ZIP: ' . $zip_path );
		}

		// Aggiunge il database
		$zip->addFile( $sql_tmp, 'database.sql' );

		// Aggiunge wp-content (esclude la cartella backups)
		auto_backup_zip_dir( $zip, WP_CONTENT_DIR, 'wp-content', array( realpath( AUTO_BACKUP_DIR ) ) );

		// Aggiunge wp-config.php
		$wp_config = ABSPATH . 'wp-config.php';
		if ( file_exists( $wp_config ) ) {
			$zip->addFile( $wp_config, 'wp-config.php' );
		}

		$zip->close();
		@unlink( $sql_tmp );

		$log['zip_size'] = file_exists( $zip_path ) ? filesize( $zip_path ) : 0;
		$log['status']   = 'success';

	} catch ( Exception $e ) {
		$log['error'] = $e->getMessage();
		@unlink( $sql_tmp );
		if ( file_exists( $zip_path ) ) {
			@unlink( $zip_path );
		}
	}

	auto_backup_write_log( $log );

	return $log['status'] === 'success';
}

function auto_backup_export_db( $output_file ) {
	global $wpdb;

	$sql  = "-- ============================================================\n";
	$sql .= "-- Auto Backup - Esportazione Database\n";
	$sql .= "-- Data: " . date( 'Y-m-d H:i:s' ) . "\n";
	$sql .= "-- Database: " . DB_NAME . "\n";
	$sql .= "-- ============================================================\n\n";
	$sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
	$sql .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
	$sql .= "SET NAMES utf8mb4;\n\n";

	$tables = $wpdb->get_col( 'SHOW TABLES' );

	foreach ( $tables as $table ) {
		// Struttura tabella
		$create_row = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
		$sql .= "-- Tabella: {$table}\n";
		$sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
		$sql .= $create_row[1] . ";\n\n";

		// Dati - caricamento a chunk per non esaurire la memoria
		$offset     = 0;
		$chunk_size = 500;

		while ( true ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM `{$table}` LIMIT %d OFFSET %d", $chunk_size, $offset ),
				ARRAY_N
			);

			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				$values = array_map(
					function ( $val ) use ( $wpdb ) {
						if ( $val === null ) {
							return 'NULL';
						}
						return "'" . $wpdb->_real_escape( $val ) . "'";
					},
					$row
				);
				$sql .= 'INSERT INTO `' . $table . '` VALUES (' . implode( ', ', $values ) . ");\n";
			}

			$offset += $chunk_size;
		}

		$sql .= "\n";
	}

	$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

	file_put_contents( $output_file, $sql );
}

function auto_backup_zip_dir( ZipArchive $zip, $src_dir, $zip_prefix, $exclude = array() ) {
	$src_real = rtrim( realpath( $src_dir ), DIRECTORY_SEPARATOR );

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $src_real, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $file ) {
		$real = realpath( $file->getPathname() );

		// Salta le cartelle escluse
		foreach ( $exclude as $excl ) {
			if ( $excl && strpos( $real, $excl ) === 0 ) {
				continue 2;
			}
		}

		$relative = $zip_prefix . '/' . str_replace( '\\', '/', substr( $real, strlen( $src_real ) + 1 ) );

		if ( $file->isDir() ) {
			$zip->addEmptyDir( $relative );
		} else {
			$zip->addFile( $real, $relative );
		}
	}
}

// ===========================================================
// LOG
// ===========================================================

function auto_backup_write_log( array $entry ) {
	$logs = auto_backup_read_log();
	array_unshift( $logs, $entry );
	$logs = array_slice( $logs, 0, 50 ); // massimo 50 voci
	file_put_contents( AUTO_BACKUP_LOG_FILE, wp_json_encode( $logs, JSON_PRETTY_PRINT ) );
}

function auto_backup_read_log() {
	if ( ! file_exists( AUTO_BACKUP_LOG_FILE ) ) {
		return array();
	}
	$data = json_decode( file_get_contents( AUTO_BACKUP_LOG_FILE ), true );
	return is_array( $data ) ? $data : array();
}

// ===========================================================
// ADMIN - MENU
// ===========================================================

add_action( 'admin_menu', 'auto_backup_admin_menu' );

function auto_backup_admin_menu() {
	add_management_page(
		'Auto Backup',
		'Auto Backup',
		'manage_options',
		'auto-backup',
		'auto_backup_admin_page'
	);
}

// ===========================================================
// ADMIN - AZIONI POST
// ===========================================================

add_action( 'admin_post_auto_backup_manual',   'auto_backup_handle_manual' );
add_action( 'admin_post_auto_backup_delete',   'auto_backup_handle_delete' );
add_action( 'admin_post_auto_backup_settings', 'auto_backup_handle_settings' );
add_action( 'admin_post_auto_backup_download', 'auto_backup_handle_download' );

function auto_backup_handle_manual() {
	check_admin_referer( 'auto_backup_manual' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Non autorizzato' );
	}
	$ok = auto_backup_run();
	$q  = $ok ? 'done=1' : 'error=1';
	wp_redirect( admin_url( 'tools.php?page=auto-backup&' . $q ) );
	exit;
}

function auto_backup_handle_delete() {
	check_admin_referer( 'auto_backup_delete' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Non autorizzato' );
	}
	$file = sanitize_file_name( $_GET['file'] ?? '' );
	if ( $file ) {
		@unlink( AUTO_BACKUP_DIR . $file );
		$logs = auto_backup_read_log();
		$logs = array_values( array_filter( $logs, fn( $l ) => $l['file'] !== $file ) );
		file_put_contents( AUTO_BACKUP_LOG_FILE, wp_json_encode( $logs, JSON_PRETTY_PRINT ) );
	}
	wp_redirect( admin_url( 'tools.php?page=auto-backup&deleted=1' ) );
	exit;
}

function auto_backup_handle_settings() {
	check_admin_referer( 'auto_backup_settings' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Non autorizzato' );
	}
	$time = sanitize_text_field( $_POST['auto_backup_time'] ?? '02:00' );
	if ( ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
		$time = '02:00';
	}
	update_option( 'auto_backup_time', $time );
	auto_backup_reschedule();
	wp_redirect( admin_url( 'tools.php?page=auto-backup&saved=1' ) );
	exit;
}

function auto_backup_handle_download() {
	check_admin_referer( 'auto_backup_download' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Non autorizzato' );
	}
	$file = sanitize_file_name( $_GET['file'] ?? '' );
	$path = AUTO_BACKUP_DIR . $file;
	if ( $file && file_exists( $path ) ) {
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $file . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path );
		exit;
	}
	wp_die( 'File non trovato.' );
}

// ===========================================================
// ADMIN - PAGINA HTML
// ===========================================================

function auto_backup_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$current_time = get_option( 'auto_backup_time', '02:00' );
	$next_run     = wp_next_scheduled( AUTO_BACKUP_CRON );
	$logs         = auto_backup_read_log();

	// Calcola dimensione totale occupata dai backup
	$total_size = 0;
	if ( is_dir( AUTO_BACKUP_DIR ) ) {
		foreach ( glob( AUTO_BACKUP_DIR . '*.zip' ) as $f ) {
			$total_size += filesize( $f );
		}
	}
	?>
	<div class="wrap">
		<h1 style="display:flex; align-items:center; gap:10px;">
			<span style="font-size:28px;">🗄️</span> Auto Backup
		</h1>

		<?php if ( isset( $_GET['done'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><strong>✅ Backup completato con successo!</strong></p></div>
		<?php elseif ( isset( $_GET['error'] ) ) : ?>
			<div class="notice notice-error is-dismissible"><p><strong>❌ Backup fallito.</strong> Controlla il log per i dettagli.</p></div>
		<?php elseif ( isset( $_GET['deleted'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><strong>🗑️ Backup eliminato.</strong></p></div>
		<?php elseif ( isset( $_GET['saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><strong>💾 Impostazioni salvate.</strong> Prossimo backup riprogrammato.</p></div>
		<?php endif; ?>

		<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:20px; max-width:1200px;">

			<!-- IMPOSTAZIONI -->
			<div class="postbox">
				<div class="postbox-header"><h2 class="hndle">⚙️ Impostazioni</h2></div>
				<div class="inside">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="auto_backup_settings">
						<?php wp_nonce_field( 'auto_backup_settings' ); ?>
						<table class="form-table" style="margin-top:0;">
							<tr>
								<th scope="row"><label for="auto_backup_time">Orario backup giornaliero</label></th>
								<td>
									<input type="time" id="auto_backup_time" name="auto_backup_time"
										value="<?php echo esc_attr( $current_time ); ?>"
										style="font-size:18px; padding:4px 8px;">
									<p class="description" style="margin-top:8px;">
										<?php if ( $next_run ) : ?>
											⏰ Prossimo backup: <strong><?php echo esc_html( date( 'd/m/Y \a\l\l\e H:i', $next_run ) ); ?></strong>
										<?php else : ?>
											<span style="color:#d63638;">⚠️ Nessun backup programmato. Salva per attivare.</span>
										<?php endif; ?>
									</p>
								</td>
							</tr>
						</table>
						<?php submit_button( '💾 Salva impostazioni', 'primary', 'submit', false ); ?>
					</form>
				</div>
			</div>

			<!-- BACKUP MANUALE -->
			<div class="postbox">
				<div class="postbox-header"><h2 class="hndle">▶️ Backup Manuale</h2></div>
				<div class="inside">
					<p>Crea subito un backup completo. Verrà incluso:</p>
					<ul style="list-style:disc; padding-left:20px; margin-bottom:16px;">
						<li>📊 Database completo <code><?php echo DB_NAME; ?></code> (SQL)</li>
						<li>📁 Cartella <code>wp-content/</code> (temi, plugin, media)</li>
						<li>⚙️ File <code>wp-config.php</code></li>
					</ul>
					<?php if ( $total_size > 0 ) : ?>
						<p style="color:#666;">📦 Spazio occupato dai backup: <strong><?php echo auto_backup_format_size( $total_size ); ?></strong></p>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="auto_backup_manual">
						<?php wp_nonce_field( 'auto_backup_manual' ); ?>
						<?php submit_button( '🚀 Esegui Backup Ora', 'primary large', 'submit', false ); ?>
					</form>
					<p class="description" style="margin-top:8px;">
						I backup vengono salvati in <code>wp-content/backups/</code> e non sono accessibili dall'esterno.
					</p>
				</div>
			</div>
		</div>

		<!-- STORICO BACKUP -->
		<div class="postbox" style="margin-top:20px; max-width:1200px;">
			<div class="postbox-header">
				<h2 class="hndle">
					📋 Storico Backup
					<?php if ( ! empty( $logs ) ) : ?>
						<span style="font-size:13px; font-weight:normal; color:#666; margin-left:8px;">(<?php echo count( $logs ); ?> backup registrati)</span>
					<?php endif; ?>
				</h2>
			</div>
			<div class="inside" style="padding-top:0;">
				<?php if ( empty( $logs ) ) : ?>
					<p style="color:#666;"><em>Nessun backup ancora eseguito. Premi "Esegui Backup Ora" per iniziare.</em></p>
				<?php else : ?>
					<table class="widefat striped" style="border:none;">
						<thead>
							<tr>
								<th>Data e ora</th>
								<th>File</th>
								<th>Dimensione ZIP</th>
								<th>Dimensione DB</th>
								<th>Stato</th>
								<th>Azioni</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $logs as $log ) : ?>
								<?php $file_exists = file_exists( AUTO_BACKUP_DIR . $log['file'] ); ?>
								<tr>
									<td><strong><?php echo esc_html( $log['date'] ); ?></strong></td>
									<td>
										<code style="font-size:12px;"><?php echo esc_html( $log['file'] ); ?></code>
										<?php if ( ! $file_exists && $log['status'] === 'success' ) : ?>
											<br><span style="color:#d63638; font-size:11px;">⚠️ File non trovato su disco</span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( auto_backup_format_size( $log['zip_size'] ?? 0 ) ); ?></td>
									<td><?php echo esc_html( auto_backup_format_size( $log['db_size'] ?? 0 ) ); ?></td>
									<td>
										<?php if ( $log['status'] === 'success' ) : ?>
											<span style="color:#00a32a; font-weight:600;">✅ Successo</span>
										<?php else : ?>
											<span style="color:#d63638; font-weight:600;" title="<?php echo esc_attr( $log['error'] ?? '' ); ?>">
												❌ Errore
											</span>
											<?php if ( ! empty( $log['error'] ) ) : ?>
												<br><small style="color:#d63638;"><?php echo esc_html( $log['error'] ); ?></small>
											<?php endif; ?>
										<?php endif; ?>
									</td>
									<td style="white-space:nowrap;">
										<?php if ( $log['status'] === 'success' && $file_exists ) : ?>
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=auto_backup_download&file=' . rawurlencode( $log['file'] ) ), 'auto_backup_download' ) ); ?>"
											   class="button button-small">⬇️ Scarica</a>
											&nbsp;
										<?php endif; ?>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=auto_backup_delete&file=' . rawurlencode( $log['file'] ) ), 'auto_backup_delete' ) ); ?>"
										   class="button button-small"
										   style="color:#d63638; border-color:#d63638;"
										   onclick="return confirm('Eliminare questo backup? L\'operazione non è reversibile.');">
											🗑️ Elimina
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

	</div>
	<?php
}

// ===========================================================
// UTILITY
// ===========================================================

function auto_backup_format_size( $bytes ) {
	if ( $bytes <= 0 ) {
		return '—';
	}
	$units = array( 'B', 'KB', 'MB', 'GB' );
	$i     = (int) floor( log( $bytes, 1024 ) );
	return round( $bytes / pow( 1024, $i ), 2 ) . ' ' . $units[ min( $i, 3 ) ];
}
