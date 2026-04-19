<?php
/**
 * Esegue un backup manuale (CLI o browser con permessi).
 * Uso: php run-backup-once.php
 */

require dirname( __DIR__, 3 ) . '/wp-load.php';

if ( ! function_exists( 'auto_backup_run' ) ) {
	require_once __DIR__ . '/auto-backup.php';
}

if ( php_sapi_name() !== 'cli' ) {
	if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Non autorizzato.' );
	}
}

echo "Avvio backup Auto Backup...\n";
flush();
$start = microtime( true );
$ok    = auto_backup_run();
$sec   = round( microtime( true ) - $start, 1 );

if ( $ok ) {
	$logs = auto_backup_read_log();
	$last = $logs[0] ?? array();
	echo "Completato in {$sec}s\n";
	echo "File: " . ( $last['file'] ?? '?' ) . "\n";
	if ( ! empty( $last['zip_size'] ) ) {
		echo "Dimensione ZIP: " . round( $last['zip_size'] / 1048576, 2 ) . " MB\n";
	}
	exit( 0 );
}

$logs  = auto_backup_read_log();
$error = $logs[0]['error'] ?? 'sconosciuto';
echo "Fallito in {$sec}s: {$error}\n";
exit( 1 );
