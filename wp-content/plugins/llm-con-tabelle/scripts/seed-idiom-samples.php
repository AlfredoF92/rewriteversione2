<?php
/**
 * Seed banche espressioni (it→en, it→pl, pl→it) dallo stile rivista.
 *
 * CLI: C:\xampp\php\php.exe wp-content/plugins/llm-con-tabelle/scripts/seed-idiom-samples.php
 * Web: solo se LLM_IDIOM_SEED_WEB è definito.
 *
 * @package LLM_Tabelle
 */

$llm_idiom_seed_web = defined( 'LLM_IDIOM_SEED_WEB' ) && LLM_IDIOM_SEED_WEB;
if ( php_sapi_name() !== 'cli' && ! $llm_idiom_seed_web ) {
	exit( "CLI only.\n" );
}

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_file( $wp_load ) ) {
	fwrite( STDERR, "wp-load.php non trovato: {$wp_load}\n" );
	exit( 1 );
}
require $wp_load;

if ( ! class_exists( 'LLM_Idiom' ) ) {
	fwrite( STDERR, "LLM_Idiom non caricato.\n" );
	exit( 1 );
}

/**
 * @param string $category Categoria.
 * @param string $phrase Frase.
 * @param string $meaning Significato.
 * @param string $equivalent Equivalente.
 * @return array
 */
function llm_idiom_item( $category, $phrase, $meaning, $equivalent ) {
	return array(
		'id'         => LLM_Idiom::new_item_id(),
		'category'   => $category,
		'phrase'     => $phrase,
		'meaning'    => $meaning,
		'equivalent' => $equivalent,
	);
}

/**
 * @param string $title Titolo.
 * @param string $known Known.
 * @param string $target Target.
 * @param array  $items Items.
 * @return int
 */
function llm_seed_idiom_bank( $title, $known, $target, array $items ) {
	$existing = LLM_Idiom::find_for_pair( $known, $target );
	if ( $existing ) {
		$post_id = $existing;
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_title'  => $title,
				'post_status' => 'publish',
			)
		);
		echo "Aggiorno banca #{$post_id} ({$known}→{$target})\n";
	} else {
		$post_id = wp_insert_post(
			array(
				'post_type'   => LLM_Idiom::CPT,
				'post_title'  => $title,
				'post_status' => 'publish',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			fwrite( STDERR, $post_id->get_error_message() . "\n" );
			exit( 1 );
		}
		echo "Creata banca #{$post_id} ({$known}→{$target})\n";
	}
	update_post_meta( $post_id, LLM_Idiom::META_KNOWN, $known );
	update_post_meta( $post_id, LLM_Idiom::META_TARGET, $target );
	LLM_Idiom::save_items( $post_id, $items );
	echo '  Items: ' . count( LLM_Idiom::get_items( $post_id ) ) . "\n";
	return (int) $post_id;
}

$pl_it = array(
	llm_idiom_item( '🍕 Espressioni legate al cibo (Culinaria)', "Facile come bere un bicchiere d'acqua", 'Significa che qualcosa è semplicissimo da fare.', 'Equivalente polacco: Bułka z masłem (Un panino con il burro).' ),
	llm_idiom_item( '🍕 Espressioni legate al cibo (Culinaria)', "C'entrare come i cavoli a merenda", 'Significa che un argomento o un oggetto non ha alcun legame con quello di cui si sta parlando, è del tutto fuori luogo.', 'Equivalente polacco: Pasować jak pięść do nosa (Starci come un pugno sul naso).' ),
	llm_idiom_item( '🍕 Espressioni legate al cibo (Culinaria)', 'Non piangere sul latte versato', 'Significa che è inutile disperarsi o lamentarsi per un errore passato che ormai non si può più correggere.', 'Equivalente polacco: Mleko się rozlało (Il latte si è versato).' ),
	llm_idiom_item( '🐺 Espressioni legate agli animali (Zwierzęta)', 'In bocca al lupo!', 'È l\'augurio di buona fortuna più usato in Italia (la risposta corretta è "Crepi!" o "Crepi il lupo!").', 'Equivalente polacco: Połamania nóg! (Rompiti le gambe!).' ),
	llm_idiom_item( '🐺 Espressioni legate agli animali (Zwierzęta)', 'Prendere due piccioni con una fava', 'Ottenere due risultati utili o risolvere due problemi contemporaneamente con un\'unica azione.', 'Equivalente polacco: Upiec dwie pieczenie na jednym ogniu (Cuocere due arrosti sullo stesso fuoco).' ),
	llm_idiom_item( '🐺 Espressioni legate agli animali (Zwierzęta)', "Essere un pesce fuor d'acqua", 'Sentirsi a disagio, in imbarazzo o fuori posto in un determinato ambiente.', 'Al contrario del polacco: Czuć się jak ryba w wodzie (che significa l\'opposto, cioè essere a proprio agio). Il contrario in polacco sarebbe Czuć się nieswojo.' ),
	llm_idiom_item( '🌍 Vita quotidiana e reazioni', 'Non sono affari miei / Non sono fatti miei', 'Significa che una situazione non ci riguarda e non vogliamo intrometterci.', 'Equivalente polacco: Nie mój cyrk, nie moje małpy (Non è il mio circo, non sono le mie scimmie).' ),
	llm_idiom_item( '🌍 Vita quotidiana e reazioni', 'Mandare qualcuno a quel paese', 'Un modo colorito ma non eccessivamente volgare per dire a qualcuno di andarsene e non infastidirci più.', 'Equivalente polacco: Gdzie pieprz rośnie (Dove cresce il pepe).' ),
	llm_idiom_item( '🌍 Vita quotidiana e reazioni', 'Comprare a scatola chiusa', 'Acquistare qualcosa senza prima averlo controllato o visto di persona.', 'Equivalente polacco: Kupować kota w worku (Comprare un gatto nel sacco).' ),
	llm_idiom_item( '🌍 Vita quotidiana e reazioni', 'Avere un diavolo per capello', 'Essere estremamente arrabbiati, nervosi o irritati per qualcosa.', 'Equivalente polacco: Dostawać białej gorączki (Prendere la febbre bianca).' ),
);

$it_en = array(
	llm_idiom_item( '🍕 Food-related expressions', "It's a piece of cake", 'Significa che qualcosa è semplicissimo da fare.', "Equivalente italiano: Facile come bere un bicchiere d'acqua / È una passeggiata." ),
	llm_idiom_item( '🍕 Food-related expressions', 'Spill the beans', 'Significa rivelare un segreto o dire tutto quello che si sa.', 'Equivalente italiano: Spifferare tutto / Cantare.' ),
	llm_idiom_item( '🍕 Food-related expressions', "Don't cry over spilled milk", 'È inutile disperarsi per un errore passato che non si può più correggere.', 'Equivalente italiano: Non piangere sul latte versato.' ),
	llm_idiom_item( '🐺 Animal expressions', 'Break a leg!', 'Augurio di buona fortuna (soprattutto a teatro o prima di una prova importante).', 'Equivalente italiano: In bocca al lupo! (risposta: Crepi!).' ),
	llm_idiom_item( '🐺 Animal expressions', 'Kill two birds with one stone', "Ottenere due risultati utili con un'unica azione.", 'Equivalente italiano: Prendere due piccioni con una fava.' ),
	llm_idiom_item( '🐺 Animal expressions', 'Feel like a fish out of water', 'Sentirsi a disagio o fuori posto in un ambiente.', "Equivalente italiano: Essere un pesce fuor d'acqua." ),
	llm_idiom_item( '🌍 Everyday life', "That's none of my business", 'Una situazione non ci riguarda e non vogliamo intrometterci.', 'Equivalente italiano: Non sono affari miei.' ),
	llm_idiom_item( '🌍 Everyday life', 'Buy something sight unseen', 'Acquistare senza aver controllato o visto di persona.', 'Equivalente italiano: Comprare a scatola chiusa.' ),
	llm_idiom_item( '🌍 Everyday life', 'Be under the weather', 'Non sentirsi bene, essere un po\' giù o leggermente malati.', 'Equivalente italiano: Non sentirsi in forma / Essere un po\' giù.' ),
	llm_idiom_item( '🌍 Everyday life', 'Hit the books', 'Mettersi a studiare sul serio.', 'Equivalente italiano: Mettersi sui libri / Studiare sodo.' ),
);

$it_pl = array(
	llm_idiom_item( '🍕 Espressioni legate al cibo', 'Bułka z masłem', 'Significa che qualcosa è semplicissimo da fare (letteralmente: un panino con il burro).', "Equivalente italiano: Facile come bere un bicchiere d'acqua." ),
	llm_idiom_item( '🍕 Espressioni legate al cibo', 'Mleko się rozlało', 'È inutile lamentarsi di un errore ormai fatto (letteralmente: il latte si è versato).', 'Equivalente italiano: Non piangere sul latte versato.' ),
	llm_idiom_item( '🍕 Espressioni legate al cibo', 'Upiec dwie pieczenie na jednym ogniu', "Ottenere due risultati con un'unica azione (cuocere due arrosti sullo stesso fuoco).", 'Equivalente italiano: Prendere due piccioni con una fava.' ),
	llm_idiom_item( '🐺 Espressioni legate agli animali', 'Nie mój cyrk, nie moje małpy', 'Non è affar mio: non voglio intromettermi (letteralmente: non è il mio circo, non sono le mie scimmie).', 'Equivalente italiano: Non sono affari miei.' ),
	llm_idiom_item( '🐺 Espressioni legate agli animali', 'Czuć się jak ryba w wodzie', 'Sentirsi a proprio agio in un ambiente.', 'Equivalente italiano: Essere come un pesce nell\'acqua (opposto di “pesce fuor d\'acqua”).' ),
	llm_idiom_item( '🐺 Espressioni legate agli animali', 'Pasować jak pięść do nosa', "Non c'entrare per niente / essere del tutto fuori luogo.", "Equivalente italiano: C'entrare come i cavoli a merenda." ),
	llm_idiom_item( '🌍 Vita quotidiana', 'Połamania nóg!', 'Augurio di buona fortuna (letteralmente: rompiti le gambe!).', 'Equivalente italiano: In bocca al lupo!' ),
	llm_idiom_item( '🌍 Vita quotidiana', 'Kupować kota w worku', 'Acquistare senza aver controllato (comprare un gatto nel sacco).', 'Equivalente italiano: Comprare a scatola chiusa.' ),
	llm_idiom_item( '🌍 Vita quotidiana', 'Dostawać białej gorączki', 'Essere estremamente arrabbiati o irritati.', 'Equivalente italiano: Avere un diavolo per capello.' ),
	llm_idiom_item( '🌍 Vita quotidiana', 'Gdzie pieprz rośnie', 'Mandare qualcuno lontano / dirgli di non scocciare più (dove cresce il pepe).', 'Equivalente italiano: Mandare a quel paese.' ),
);

llm_seed_idiom_bank( 'Espressioni IT→EN', 'it', 'en', $it_en );
llm_seed_idiom_bank( 'Espressioni IT→PL', 'it', 'pl', $it_pl );
llm_seed_idiom_bank( 'Espressioni PL→IT (włoski)', 'pl', 'it', $pl_it );

echo "\nFatto.\n";
