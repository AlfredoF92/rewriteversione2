<?php
/**
 * Estrae e genera audio Azure delle frasi da ascoltare negli appunti.
 *
 *   php script-genera-audio-parole-appunti.php --story=3597 --index=0
 *   php script-genera-audio-parole-appunti.php --story=3597 --from=1 --to=24
 *   php script-genera-audio-parole-appunti.php --story=3597 --extract-only
 *   php script-genera-audio-parole-appunti.php --story=3597 --audio-only
 *
 * @package LLM_Tabelle
 */

if ( php_sapi_name() !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$wp_load = '';
foreach ( array( dirname( __DIR__, 2 ) . '/wp-load.php', dirname( __DIR__, 4 ) . '/wp-load.php' ) as $cand ) {
	if ( is_file( $cand ) ) {
		$wp_load = $cand;
		break;
	}
}
if ( '' === $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, "wp-load.php non trovato.\n" );
	exit( 1 );
}

require $wp_load;

if ( ! class_exists( 'LLM_Notes_Listen' ) || ! class_exists( 'LLM_Story_Repository' ) ) {
	fwrite( STDERR, "Plugin LLM CON TABELLE non caricato.\n" );
	exit( 1 );
}

@ini_set( 'memory_limit', '256M' );
@set_time_limit( 0 );

$opts = array(
	'story'        => 0,
	'index'        => -1,
	'from'         => -1,
	'to'           => -1,
	'force'        => false,
	'extract_only' => false,
	'audio_only'   => false,
);

foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( preg_match( '/^--story=(\d+)$/', $arg, $m ) ) {
		$opts['story'] = (int) $m[1];
	} elseif ( preg_match( '/^--index=(\d+)$/', $arg, $m ) ) {
		$opts['index'] = (int) $m[1];
	} elseif ( preg_match( '/^--from=(\d+)$/', $arg, $m ) ) {
		$opts['from'] = (int) $m[1];
	} elseif ( preg_match( '/^--to=(\d+)$/', $arg, $m ) ) {
		$opts['to'] = (int) $m[1];
	} elseif ( '--force' === $arg ) {
		$opts['force'] = true;
	} elseif ( '--extract-only' === $arg ) {
		$opts['extract_only'] = true;
	} elseif ( '--audio-only' === $arg ) {
		$opts['audio_only'] = true;
	} elseif ( in_array( $arg, array( '-h', '--help' ), true ) ) {
		fwrite( STDOUT, "Uso: --story=ID [--index=N|--from=N --to=N] [--extract-only|--audio-only]\n" );
		exit( 0 );
	}
}

if ( $opts['extract_only'] && $opts['audio_only'] ) {
	fwrite( STDERR, "Usa o --extract-only o --audio-only, non entrambi.\n" );
	exit( 1 );
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
} else {
	if ( $opts['from'] >= 0 ) {
		$from = $opts['from'];
	}
	if ( $opts['to'] >= 0 ) {
		$to = $opts['to'];
	}
}
if ( $from > $to || $from >= count( $phrases ) ) {
	fwrite( STDERR, "Indice fuori range.\n" );
	exit( 1 );
}

$mode = $opts['extract_only'] ? 'extract-only' : ( $opts['audio_only'] ? 'audio-only' : 'extract+audio' );
fwrite( STDOUT, "Storia #{$story_id} locale={$locale} mode={$mode} frasi " . ( $from + 1 ) . '–' . ( $to + 1 ) . '/' . count( $phrases ) . "\n" );

$total_texts = 0;
$total_ok    = 0;
$total_skip  = 0;
$total_err   = 0;

for ( $i = $from; $i <= $to; $i++ ) {
	$row = $phrases[ $i ];
	$pid = isset( $row['id'] ) ? (int) $row['id'] : 0;
	if ( $pid < 1 ) {
		fwrite( STDOUT, 'Frase ' . ( $i + 1 ) . " senza id, salto.\n" );
		continue;
	}

	if ( ! $opts['audio_only'] ) {
		$html    = isset( $row['grammar'] ) ? (string) $row['grammar'] : '';
		$grouped = LLM_Notes_Listen::extract_grouped( $html, $target_code );
		$texts   = array();
		foreach ( $grouped['conjugations'] as $t ) {
			$texts[] = $t;
		}
		foreach ( $grouped['others'] as $t ) {
			$key = function_exists( 'mb_strtolower' )
				? mb_strtolower( LLM_Notes_Listen::clean_text( $t ), 'UTF-8' )
				: strtolower( LLM_Notes_Listen::clean_text( $t ) );
			if ( '' === $key ) {
				continue;
			}
			$already = false;
			foreach ( $texts as $existing ) {
				$ek = function_exists( 'mb_strtolower' )
					? mb_strtolower( LLM_Notes_Listen::clean_text( $existing ), 'UTF-8' )
					: strtolower( LLM_Notes_Listen::clean_text( $existing ) );
				if ( $ek === $key ) {
					$already = true;
					break;
				}
			}
			if ( $already ) {
				continue;
			}
			$texts[] = $t;
		}
		fwrite( STDOUT, 'Frase ' . ( $i + 1 ) . " id={$pid} voci=" . count( $texts ) . ' (conj=' . count( $grouped['conjugations'] ) . ' other=' . ( count( $texts ) - count( $grouped['conjugations'] ) ) . ")\n" );
		foreach ( $texts as $t ) {
			fwrite( STDOUT, "  - {$t}\n" );
		}
		LLM_Notes_Listen::replace_texts( $story_id, $pid, $texts );
		$total_texts += count( $texts );
	}

	if ( $opts['extract_only'] ) {
		continue;
	}

	$stats      = LLM_Notes_Listen::generate_missing_audio( $story_id, $pid, $locale );
	$total_ok  += $stats['ok'];
	$total_skip += $stats['skip'];
	$total_err += $stats['err'];
	fwrite( STDOUT, 'Frase ' . ( $i + 1 ) . " id={$pid} audio nuovi=" . $stats['ok'] . ' già=' . $stats['skip'] . ' err=' . $stats['err'] . "\n" );
	fflush( STDOUT );
}

if ( $opts['extract_only'] ) {
	fwrite( STDOUT, "Fine estrazione. Voci scritte: {$total_texts}.\n" );
} else {
	fwrite( STDOUT, "Fine audio. nuovi={$total_ok} già={$total_skip} err={$total_err}.\n" );
}
