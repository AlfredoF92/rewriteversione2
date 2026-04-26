<?php
/**
 * Compila traduzioni categorie LLM (term meta _llm_cat_name_{lang}).
 *
 * Uso:
 *   c:\xampp\php\php.exe wp-content\plugins\llm-con-tabelle\scripts\fill-category-translations.php
 */

if ( php_sapi_name() !== 'cli' ) {
	exit( "Solo CLI.\n" );
}

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	fwrite( STDERR, "Impossibile trovare wp-load.php: {$wp_load}\n" );
	exit( 1 );
}

require_once $wp_load;

$map = array(
	'Favole del bosco' => array(
		'en' => 'Forest Fables',
		'it' => 'Favole del bosco',
		'pl' => 'Basnie lesne',
		'es' => 'Fabulas del bosque',
	),
	'Fiabe della notte' => array(
		'en' => 'Night Fairy Tales',
		'it' => 'Fiabe della notte',
		'pl' => 'Basnie nocy',
		'es' => 'Cuentos de la noche',
	),
	'Senza categoria' => array(
		'en' => 'Uncategorized',
		'it' => 'Senza categoria',
		'pl' => 'Bez kategorii',
		'es' => 'Sin categoria',
	),
	'Storie di animali' => array(
		'en' => 'Animal Stories',
		'it' => 'Storie di animali',
		'pl' => 'Historie o zwierzetach',
		'es' => 'Historias de animales',
	),
	'Storie per polacchi (italiano)' => array(
		'en' => 'Stories for Polish Italian Learners',
		'it' => "Storie per polacchi (italiano)",
		'pl' => 'Historie dla Polakow uczacych sie wloskiego',
		'es' => 'Historias para polacos que aprenden italiano',
	),
	'Uncategorized' => array(
		'en' => 'Uncategorized',
		'it' => 'Senza categoria',
		'pl' => 'Bez kategorii',
		'es' => 'Sin categoria',
	),
);

$updated = 0;
$missing = array();

foreach ( $map as $name => $translations ) {
	$term = get_term_by( 'name', $name, 'category' );
	if ( ! $term || is_wp_error( $term ) ) {
		$missing[] = $name;
		continue;
	}

	foreach ( $translations as $lang => $label ) {
		update_term_meta( (int) $term->term_id, '_llm_cat_name_' . sanitize_key( $lang ), (string) $label );
	}
	++$updated;
	echo "Aggiornata categoria {$term->term_id}: {$name}\n";
}

echo "\nTotale categorie aggiornate: {$updated}\n";
if ( ! empty( $missing ) ) {
	echo "Non trovate: " . implode( ', ', $missing ) . "\n";
}
