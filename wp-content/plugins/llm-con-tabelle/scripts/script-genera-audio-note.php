<?php
/**
 * Genera audio Azure femminile: frase obiettivo + note nella lingua da imparare.
 *
 *   php script-genera-audio-note.php --story=3597 --count=3 [--force] [--offset=0]
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

if ( ! class_exists( 'LLM_Phrase_TTS' ) || ! class_exists( 'LLM_Story_Repository' ) ) {
	fwrite( STDERR, "Plugin LLM CON TABELLE non caricato.\n" );
	exit( 1 );
}

@ini_set( 'memory_limit', '256M' );
@set_time_limit( 300 );

$opts = array(
	'story'  => 0,
	'count'  => 0,
	'offset' => 0,
	'force'  => false,
);

foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( preg_match( '/^--story=(\d+)$/', $arg, $m ) ) {
		$opts['story'] = (int) $m[1];
	} elseif ( preg_match( '/^--count=(\d+)$/', $arg, $m ) ) {
		$opts['count'] = (int) $m[1];
	} elseif ( preg_match( '/^--offset=(\d+)$/', $arg, $m ) ) {
		$opts['offset'] = (int) $m[1];
	} elseif ( '--force' === $arg ) {
		$opts['force'] = true;
	} elseif ( in_array( $arg, array( '-h', '--help' ), true ) ) {
		fwrite( STDOUT, "Uso: --story=ID [--count=N] [--offset=0] [--force]\n" );
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

$total  = count( $phrases );
$offset = max( 0, $opts['offset'] );
$limit  = $opts['count'] > 0 ? min( $total, $offset + $opts['count'] ) : $total;
if ( $offset >= $total ) {
	fwrite( STDERR, "Offset {$offset} oltre le {$total} frasi.\n" );
	exit( 1 );
}

$target_code = (string) get_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, true );
$locale      = class_exists( 'LLM_Story_Phrase_Game' )
	? LLM_Story_Phrase_Game::speech_locale( $target_code )
	: 'en-US';

fwrite(
	STDOUT,
	"Audio note storia #{$story_id} locale={$locale} frasi " . ( $offset + 1 ) . "–{$limit}/{$total}"
	. ( $opts['force'] ? ' (force)' : '' ) . "\n"
);

$ok   = 0;
$skip = 0;
$fail = 0;

for ( $i = $offset; $i < $limit; $i++ ) {
	$row         = $phrases[ $i ];
	$phrase_id   = isset( $row['id'] ) ? (int) $row['id'] : 0;
	$target_html = isset( $row['target'] ) ? (string) $row['target'] : '';
	$notes_html  = isset( $row['notes_target'] ) ? (string) $row['notes_target'] : '';
	$n           = $i + 1;
	$result      = LLM_Phrase_TTS::generate_notes_female_audio(
		$story_id,
		$phrase_id,
		$target_html,
		$notes_html,
		$locale,
		$opts['force']
	);
	if ( is_wp_error( $result ) ) {
		++$fail;
		fwrite( STDERR, "  Frase {$n} (id {$phrase_id}): " . $result->get_error_message() . "\n" );
		continue;
	}
	$url = LLM_Phrase_TTS::url( (int) $result );
	$had = ! empty( $row['audio_notes_female_id'] ) && (int) $row['audio_notes_female_id'] === (int) $result;
	if ( $had && ! $opts['force'] ) {
		++$skip;
		fwrite( STDOUT, "  Frase {$n}: già presente att={$result}\n" );
	} else {
		++$ok;
		fwrite( STDOUT, "  Frase {$n}: att={$result} {$url}\n" );
	}
}

fwrite( STDOUT, "Fatto. ok={$ok} skip={$skip} fail={$fail}\n" );
exit( $fail > 0 ? 1 : 0 );
