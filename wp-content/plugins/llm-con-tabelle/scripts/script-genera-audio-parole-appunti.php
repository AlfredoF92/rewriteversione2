<?php
/**
 * Estrae e genera audio Azure delle frasi da ascoltare negli appunti.
 *
 *   php script-genera-audio-parole-appunti.php --story=3597 --index=0
 *
 * @package LLM_Tabelle
 */

if ( php_sapi_name() !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_file( $wp_load ) ) {
	fwrite( STDERR, "wp-load.php non trovato: {$wp_load}\n" );
	exit( 1 );
}

require $wp_load;

if ( ! class_exists( 'LLM_Notes_Listen' ) || ! class_exists( 'LLM_Story_Repository' ) ) {
	fwrite( STDERR, "Plugin LLM CON TABELLE non caricato.\n" );
	exit( 1 );
}

@ini_set( 'memory_limit', '256M' );
@set_time_limit( 300 );

$opts = array(
	'story'  => 0,
	'index'  => -1,
	'force'  => false,
);

foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( preg_match( '/^--story=(\d+)$/', $arg, $m ) ) {
		$opts['story'] = (int) $m[1];
	} elseif ( preg_match( '/^--index=(\d+)$/', $arg, $m ) ) {
		$opts['index'] = (int) $m[1];
	} elseif ( '--force' === $arg ) {
		$opts['force'] = true;
	} elseif ( in_array( $arg, array( '-h', '--help' ), true ) ) {
		fwrite( STDOUT, "Uso: --story=ID [--index=N]\n" );
		exit( 0 );
	}
}

if ( $opts['story'] < 1 ) {
	fwrite( STDERR, "Manca --story=ID\n" );
	exit( 1 );
}

$story_id = $opts['story'];
$phrases  = LLM_Story_Repository::get_phrases( $story_id );
if ( ! $phrases ) {
	fwrite( STDERR, "Nessuna frase per la storia #{$story_id}.\n" );
	exit( 1 );
}

$target_code = (string) get_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, true );
$locale      = class_exists( 'LLM_Story_Phrase_Game' )
	? LLM_Story_Phrase_Game::speech_locale( $target_code )
	: 'en-US';

$from = 0;
$to   = count( $phrases ) - 1;
if ( $opts['index'] >= 0 ) {
	$from = $opts['index'];
	$to   = $opts['index'];
}
if ( $from > $to || $from >= count( $phrases ) ) {
	fwrite( STDERR, "Indice fuori range.\n" );
	exit( 1 );
}

fwrite( STDOUT, "Storia #{$story_id} locale={$locale} frasi " . ( $from + 1 ) . '–' . ( $to + 1 ) . '/' . count( $phrases ) . "\n" );

for ( $i = $from; $i <= $to; $i++ ) {
	$row  = $phrases[ $i ];
	$pid  = isset( $row['id'] ) ? (int) $row['id'] : 0;
	$html = isset( $row['grammar'] ) ? (string) $row['grammar'] : '';
	$texts = LLM_Notes_Listen::extract_from_html( $html );
	fwrite( STDOUT, 'Frase ' . ( $i + 1 ) . " id={$pid} voci=" . count( $texts ) . "\n" );
	foreach ( $texts as $t ) {
		fwrite( STDOUT, "  - {$t}\n" );
	}
	if ( $pid < 1 ) {
		continue;
	}
	LLM_Notes_Listen::replace_texts( $story_id, $pid, $texts );
	$stats = LLM_Notes_Listen::generate_missing_audio( $story_id, $pid, $locale );
	fwrite( STDOUT, '  audio nuovi=' . $stats['ok'] . ' già=' . $stats['skip'] . ' err=' . $stats['err'] . "\n" );
}

fwrite( STDOUT, "Fine.\n" );
