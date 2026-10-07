<?php
/**
 * Cancella selezioni + audio note-listen per una storia.
 * CLI: php script-pulisci-note-listen.php --story=3365
 * HTTP one-shot: ?k=...&story=3597 (si autoelimina)
 */
$is_cli = ( php_sapi_name() === 'cli' );
if ( ! $is_cli ) {
	if ( ! isset( $_GET['k'] ) || $_GET['k'] !== 'lrwClearNotesListen' ) {
		http_response_code( 403 );
		exit;
	}
	header( 'Content-Type: text/plain; charset=utf-8' );
}

$wp_load = $is_cli
	? dirname( __DIR__, 4 ) . '/wp-load.php'
	: dirname( __DIR__, 4 ) . '/wp-load.php';

// Questo file vive in scripts/ in CLI; in assets/ in HTTP one-shot.
if ( ! is_file( $wp_load ) ) {
	$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
}
if ( ! is_file( $wp_load ) ) {
	fwrite( $is_cli ? STDERR : STDOUT, "wp-load missing\n" );
	exit( 1 );
}
require $wp_load;

@set_time_limit( 0 );

$story_id = 0;
if ( $is_cli ) {
	foreach ( array_slice( $argv, 1 ) as $arg ) {
		if ( preg_match( '/^--story=(\d+)$/', $arg, $m ) ) {
			$story_id = (int) $m[1];
		}
	}
} else {
	$story_id = isset( $_GET['story'] ) ? (int) $_GET['story'] : 0;
}

if ( $story_id < 1 ) {
	fwrite( $is_cli ? STDERR : STDOUT, "Manca story id\n" );
	exit( 1 );
}

global $wpdb;
$table = LLM_Tabelle_Database::table( 'llm_story_phrase_listen' );

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT id, audio_id, audio_male_id FROM {$table} WHERE story_id = %d",
		$story_id
	),
	ARRAY_A
);

$att_ids = array();
$row_n   = is_array( $rows ) ? count( $rows ) : 0;
if ( is_array( $rows ) ) {
	foreach ( $rows as $r ) {
		$a = isset( $r['audio_id'] ) ? (int) $r['audio_id'] : 0;
		$m = isset( $r['audio_male_id'] ) ? (int) $r['audio_male_id'] : 0;
		if ( $a > 0 ) {
			$att_ids[ $a ] = true;
		}
		if ( $m > 0 ) {
			$att_ids[ $m ] = true;
		}
	}
}

$del_att = 0;
foreach ( array_keys( $att_ids ) as $aid ) {
	$r = wp_delete_attachment( (int) $aid, true );
	if ( false !== $r ) {
		++$del_att;
	}
}

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$del_rows = $wpdb->delete( $table, array( 'story_id' => $story_id ), array( '%d' ) );

$left = (int) $wpdb->get_var(
	$wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		"SELECT COUNT(*) FROM {$table} WHERE story_id = %d",
		$story_id
	)
);

$msg = "story={$story_id} rows_before={$row_n} rows_deleted={$del_rows} attachments_deleted={$del_att} rows_left={$left}\n";
fwrite( STDOUT, $msg );

if ( ! $is_cli ) {
	@unlink( __FILE__ );
	echo "done\n";
}
