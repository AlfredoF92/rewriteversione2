<?php
/**
 * script-crucintarsio-crea-definizioni
 *
 * 1) Dump contesto storia (quante parole vuoi):
 *    php script-crucintarsio-crea-definizioni.php --story=123 --count=50
 *
 * 2) Salva le definizioni generate:
 *    php script-crucintarsio-crea-definizioni.php --apply=percorso.json
 *
 * JSON apply:
 * {
 *   "story_id": 123,
 *   "words": [
 *     {
 *       "word": "THANKS",
 *       "category": "Note di storia",
 *       "kind": "notes",
 *       "phrase_n": 1,
 *       "def_known": "Definizione breve.",
 *       "def_target": "Short definition."
 *     }
 *   ]
 * }
 *
 * kind: notes | grammar | curiosity | pronunciation | translation | conjugation | etymology
 *
 * La soluzione (word) non deve comparire nelle definizioni.
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

$opts = array(
	'story' => 0,
	'count' => 50,
	'apply' => '',
);

foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( preg_match( '/^--story=(\d+)$/', $arg, $m ) ) {
		$opts['story'] = (int) $m[1];
	} elseif ( preg_match( '/^--count=(\d+)$/', $arg, $m ) ) {
		$opts['count'] = (int) $m[1];
	} elseif ( preg_match( '/^--apply=(.+)$/', $arg, $m ) ) {
		$opts['apply'] = $m[1];
	} elseif ( in_array( $arg, array( '-h', '--help' ), true ) ) {
		fwrite( STDOUT, "Uso:\n  --story=ID --count=N     dump contesto\n  --apply=file.json        salva definizioni\n" );
		exit( 0 );
	}
}

if ( $opts['apply'] ) {
	$path = $opts['apply'];
	if ( ! is_file( $path ) ) {
		$path = getcwd() . DIRECTORY_SEPARATOR . $opts['apply'];
	}
	if ( ! is_readable( $path ) ) {
		fwrite( STDERR, "File apply non leggibile: {$opts['apply']}\n" );
		exit( 1 );
	}
	$raw  = file_get_contents( $path );
	$data = json_decode( (string) $raw, true );
	if ( ! is_array( $data ) ) {
		fwrite( STDERR, "JSON non valido.\n" );
		exit( 1 );
	}
	$story_id = isset( $data['story_id'] ) ? absint( $data['story_id'] ) : $opts['story'];
	$words    = array();
	if ( isset( $data['words'] ) && is_array( $data['words'] ) ) {
		$words = $data['words'];
	} elseif ( isset( $data[0] ) ) {
		$words = $data;
	}
	$bank = LLM_Story_Crossword::apply_definitions( $story_id, $words );
	if ( is_wp_error( $bank ) ) {
		fwrite( STDERR, $bank->get_error_message() . "\n" );
		exit( 1 );
	}
	fwrite( STDOUT, 'Salvate ' . count( $bank ) . " definizioni sulla storia #{$story_id}.\n" );
	fwrite( STDOUT, "Apri Modifica storia in fondo: elenco definizioni (tutte in Scartate finché non lanci script-genera-crucintarsio).\n" );
	exit( 0 );
}

if ( $opts['story'] < 1 ) {
	fwrite( STDERR, "Manca --story=ID\n" );
	exit( 1 );
}

$ctx = LLM_Story_Crossword::dump_story_context( $opts['story'], $opts['count'] );
if ( is_wp_error( $ctx ) ) {
	fwrite( STDERR, $ctx->get_error_message() . "\n" );
	exit( 1 );
}

$json = wp_json_encode( $ctx, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
fwrite( STDOUT, $json ? $json . "\n" : "{}\n" );
exit( 0 );
