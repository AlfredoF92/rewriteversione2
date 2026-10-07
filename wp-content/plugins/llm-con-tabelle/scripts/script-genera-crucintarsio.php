<?php
/**
 * script-genera-crucintarsio
 *
 * Legge le definizioni già salvate sulla storia, genera N varianti 15x15
 * (algoritmo Genera crucintarsio) e tiene quella più vicina al numero di intrecci
 * (parole incastrate). A parità vince chi ne mette di più.
 *
 *   php script-genera-crucintarsio.php --story=123 --intrecci=22 --varianti=6
 *
 * Opzionali: --size=15
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

if ( ! class_exists( 'LLM_Story_Crossword' ) ) {
	fwrite( STDERR, "Plugin LLM CON TABELLE / LLM_Story_Crossword non caricato.\n" );
	exit( 1 );
}

@ini_set( 'memory_limit', '256M' );
@set_time_limit( 180 );

$opts = array(
	'story'    => 0,
	'intrecci' => 0,
	'varianti' => 6,
	'size'     => 15,
);

foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( preg_match( '/^--story=(\d+)$/', $arg, $m ) ) {
		$opts['story'] = (int) $m[1];
	} elseif ( preg_match( '/^--intrecci=(\d+)$/', $arg, $m ) ) {
		$opts['intrecci'] = (int) $m[1];
	} elseif ( preg_match( '/^--varianti=(\d+)$/', $arg, $m ) ) {
		$opts['varianti'] = (int) $m[1];
	} elseif ( preg_match( '/^--size=(\d+)$/', $arg, $m ) ) {
		$opts['size'] = (int) $m[1];
	} elseif ( in_array( $arg, array( '-h', '--help' ), true ) ) {
		fwrite( STDOUT, "Uso: --story=ID --intrecci=N --varianti=N [--size=15]\n" );
		exit( 0 );
	}
}

if ( $opts['story'] < 1 ) {
	fwrite( STDERR, "Manca --story=ID\n" );
	exit( 1 );
}
if ( $opts['varianti'] < 1 ) {
	fwrite( STDERR, "Manca --varianti=N\n" );
	exit( 1 );
}

fwrite( STDOUT, "Genero crucintarsio storia #{$opts['story']} (intrecci={$opts['intrecci']}, varianti={$opts['varianti']}, size={$opts['size']})...\n" );

$result = LLM_Story_Crossword::generate_for_story(
	$opts['story'],
	$opts['intrecci'],
	$opts['varianti'],
	$opts['size']
);

if ( is_wp_error( $result ) ) {
	fwrite( STDERR, $result->get_error_message() . "\n" );
	exit( 1 );
}

fwrite( STDOUT, 'Cruciverba #' . $result['crossword_id'] . "\n" );
fwrite( STDOUT, 'Shortcode: ' . $result['shortcode'] . "\n" );
fwrite( STDOUT, 'Incastrate: ' . $result['placed_count'] . '  (obiettivo intrecci: ' . $result['intrecci'] . ")\n" );
fwrite( STDOUT, 'Griglia: ' . $result['rows'] . 'x' . $result['cols'] . "\n" );
fwrite( STDOUT, 'Scelte: ' . implode( ', ', $result['placed'] ) . "\n" );
if ( ! empty( $result['unplaced'] ) ) {
	fwrite( STDOUT, 'Scartate: ' . implode( ', ', $result['unplaced'] ) . "\n" );
}
fwrite( STDOUT, "Vedi Modifica storia, box in fondo.\n" );
exit( 0 );
