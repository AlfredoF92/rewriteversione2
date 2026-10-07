<?php
/**
 * Genera audio ascolto traduzione (M+F), Azure (M+F) e audio delle note.
 * Non tocca le parole evidenziate degli appunti.
 *
 *   php script-genera-audio-frasi.php --story=4498 [--offset=0] [--count=0] [--notes-only] [--phrases-only]
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

@ini_set( 'memory_limit', '512M' );
@set_time_limit( 0 );

$opts = array(
	'story'        => 0,
	'count'        => 0,
	'offset'       => 0,
	'notes_only'   => false,
	'phrases_only' => false,
);

foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( preg_match( '/^--story=(\d+)$/', $arg, $m ) ) {
		$opts['story'] = (int) $m[1];
	} elseif ( preg_match( '/^--count=(\d+)$/', $arg, $m ) ) {
		$opts['count'] = (int) $m[1];
	} elseif ( preg_match( '/^--offset=(\d+)$/', $arg, $m ) ) {
		$opts['offset'] = (int) $m[1];
	} elseif ( '--notes-only' === $arg ) {
		$opts['notes_only'] = true;
	} elseif ( '--phrases-only' === $arg ) {
		$opts['phrases_only'] = true;
	}
}

if ( $opts['story'] < 1 ) {
	fwrite( STDERR, "Manca --story=ID\n" );
	exit( 1 );
}

$dg = class_exists( 'LLM_STT' ) && LLM_STT::deepgram_ready();
$az = class_exists( 'LLM_STT' ) && LLM_STT::azure_ready();
fwrite( STDOUT, 'Deepgram=' . ( $dg ? 'yes' : 'no' ) . ' Azure=' . ( $az ? 'yes' : 'no' ) . "\n" );
if ( ! $dg && ! $az ) {
	fwrite( STDERR, "Nessun motore TTS configurato.\n" );
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

fwrite( STDOUT, "Storia #{$story_id} locale={$locale} frasi " . ( $offset + 1 ) . "–{$limit}/{$total}\n" );

$ok   = 0;
$fail = 0;

for ( $i = $offset; $i < $limit; $i++ ) {
	$row       = $phrases[ $i ];
	$phrase_id = isset( $row['id'] ) ? (int) $row['id'] : 0;
	$n         = $i + 1;
	$target    = isset( $row['target'] ) ? (string) $row['target'] : '';
	$notes     = isset( $row['notes_target'] ) ? (string) $row['notes_target'] : '';

	if ( ! $opts['notes_only'] ) {
		$result = LLM_Phrase_TTS::generate_for_phrase( $story_id, $phrase_id, $target, $locale, false );
		if ( is_wp_error( $result ) ) {
			++$fail;
			fwrite( STDERR, "  Frase {$n} (id {$phrase_id}) voci: " . $result->get_error_message() . "\n" );
		} else {
			++$ok;
			fwrite(
				STDOUT,
				"  Frase {$n} voci: M={$result['male']} F={$result['female']} AzM={$result['azure_male']} AzF={$result['azure_female']}\n"
			);
		}
	}

	if ( ! $opts['phrases_only'] ) {
		$notes_id = LLM_Phrase_TTS::generate_notes_female_audio( $story_id, $phrase_id, $target, $notes, $locale, false );
		if ( is_wp_error( $notes_id ) ) {
			++$fail;
			fwrite( STDERR, "  Frase {$n} (id {$phrase_id}) note: " . $notes_id->get_error_message() . "\n" );
		} else {
			++$ok;
			fwrite( STDOUT, "  Frase {$n} note: att={$notes_id}\n" );
		}
	}

	if ( function_exists( 'flush' ) ) {
		flush();
	}
}

if ( class_exists( 'LLM_Story_Checklist' ) ) {
	LLM_Story_Checklist::refresh( $story_id );
}

fwrite( STDOUT, "Fatto. ok={$ok} fail={$fail}\n" );
exit( $fail > 0 ? 1 : 0 );
