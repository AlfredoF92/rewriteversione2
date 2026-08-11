<?php
/**
 * Esporta CSV delle banche quiz di prova (#3090 it-en, #3091 it-pl) se presenti,
 * altrimenti riesegue il seed e poi esporta.
 *
 * @package LLM_Tabelle
 */

if ( php_sapi_name() !== 'cli' ) {
	exit( "CLI only.\n" );
}

require dirname( __DIR__, 4 ) . '/wp-load.php';

$dir = __DIR__;
$map = array(
	'it-en' => array( 'known' => 'it', 'target' => 'en' ),
	'it-pl' => array( 'known' => 'it', 'target' => 'pl' ),
);

foreach ( $map as $slug => $pair ) {
	$id = LLM_Quiz::find_for_pair( $pair['known'], $pair['target'] );
	if ( ! $id ) {
		echo "Nessun quiz per {$slug}\n";
		continue;
	}
	$path = $dir . '/quiz-sample-' . $slug . '.csv';
	$out  = fopen( $path, 'w' );
	if ( ! $out ) {
		fwrite( STDERR, "Impossibile scrivere {$path}\n" );
		exit( 1 );
	}
	fputcsv( $out, array( 'category', 'question', 'answer1', 'explanation1', 'answer2', 'explanation2', 'answer3', 'explanation3', 'correct' ) );
	foreach ( LLM_Quiz::get_questions( $id ) as $q ) {
		$a = $q['answers'];
		fputcsv(
			$out,
			array(
				$q['category'],
				$q['question'],
				$a[0]['text'],
				$a[0]['explanation'],
				$a[1]['text'],
				$a[1]['explanation'],
				$a[2]['text'],
				$a[2]['explanation'],
				chr( 65 + (int) $q['correct'] ),
			)
		);
	}
	fclose( $out );
	echo "Scritto {$path} (" . count( LLM_Quiz::get_questions( $id ) ) . " domande, post #{$id})\n";
}
