<?php
/**
 * Seed banche espressioni (it→en, it→pl, pl→it) con spiegazione unica
 * (traduzione + significato + equivalente).
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
 * @param string $category    Categoria.
 * @param string $phrase      Frase.
 * @param string $explanation Spiegazione unica.
 * @return array
 */
function llm_idiom_item( $category, $phrase, $explanation ) {
	return array(
		'id'          => LLM_Idiom::new_item_id(),
		'category'    => $category,
		'phrase'      => $phrase,
		'explanation' => $explanation,
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
	llm_idiom_item( '🍕 Espressioni legate al cibo (Culinaria)', "Facile come bere un bicchiere d'acqua", "Si può tradurre come «łatwe jak picie szklanki wody»: significa che qualcosa è semplicissimo da fare.\n\nEquivalente polacco: Bułka z masłem (Un panino con il burro)." ),
	llm_idiom_item( '🍕 Espressioni legate al cibo (Culinaria)', "C'entrare come i cavoli a merenda", "Si può tradurre come «pasować jak kapusta do podwieczorku»: significa che un argomento o un oggetto non ha alcun legame con quello di cui si sta parlando, è del tutto fuori luogo.\n\nEquivalente polacco: Pasować jak pięść do nosa (Starci come un pugno sul naso)." ),
	llm_idiom_item( '🍕 Espressioni legate al cibo (Culinaria)', 'Non piangere sul latte versato', "Si può tradurre come «nie płacz nad rozlanym mlekiem»: significa che è inutile disperarsi o lamentarsi per un errore passato che ormai non si può più correggere.\n\nEquivalente polacco: Mleko się rozlało (Il latte si è versato)." ),
	llm_idiom_item( '🐺 Espressioni legate agli animali (Zwierzęta)', 'In bocca al lupo!', "Si può tradurre come «do paszczy wilka!»: è l'augurio di buona fortuna più usato in Italia (la risposta corretta è «Crepi!» o «Crepi il lupo!»).\n\nEquivalente polacco: Połamania nóg! (Rompiti le gambe!)." ),
	llm_idiom_item( '🐺 Espressioni legate agli animali (Zwierzęta)', 'Prendere due piccioni con una fava', "Si può tradurre come «upolować dwa gołębie jedną fasolą»: ottenere due risultati utili o risolvere due problemi contemporaneamente con un'unica azione.\n\nEquivalente polacco: Upiec dwie pieczenie na jednym ogniu (Cuocere due arrosti sullo stesso fuoco)." ),
	llm_idiom_item( '🐺 Espressioni legate agli animali (Zwierzęta)', "Essere un pesce fuor d'acqua", "Si può tradurre come «być jak ryba bez wody»: sentirsi a disagio, in imbarazzo o fuori posto in un determinato ambiente.\n\nAl contrario del polacco: Czuć się jak ryba w wodzie (essere a proprio agio). Il contrario in polacco sarebbe Czuć się nieswojo." ),
	llm_idiom_item( '🌍 Vita quotidiana e reazioni', 'Non sono affari miei / Non sono fatti miei', "Si può tradurre come «to nie moja sprawa»: significa che una situazione non ci riguarda e non vogliamo intrometterci.\n\nEquivalente polacco: Nie mój cyrk, nie moje małpy (Non è il mio circo, non sono le mie scimmie)." ),
	llm_idiom_item( '🌍 Vita quotidiana e reazioni', 'Mandare qualcuno a quel paese', "Si può tradurre come «wysłać kogoś do diabła» (tono colorito): un modo non eccessivamente volgare per dire a qualcuno di andarsene e non infastidirci più.\n\nEquivalente polacco: Gdzie pieprz rośnie (Dove cresce il pepe)." ),
	llm_idiom_item( '🌍 Vita quotidiana e reazioni', 'Comprare a scatola chiusa', "Si può tradurre come «kupować w zamkniętym pudełku»: acquistare qualcosa senza prima averlo controllato o visto di persona.\n\nEquivalente polacco: Kupować kota w worku (Comprare un gatto nel sacco)." ),
	llm_idiom_item( '🌍 Vita quotidiana e reazioni', 'Avere un diavolo per capello', "Si può tradurre come «mieć diabła we włosach»: essere estremamente arrabbiati, nervosi o irritati per qualcosa.\n\nEquivalente polacco: Dostawać białej gorączki (Prendere la febbre bianca)." ),
);

$it_en = array(
	llm_idiom_item( '🍕 Food-related expressions', "It's a piece of cake", "Si può tradurre come «Un pezzo di torta»: significa che qualcosa è semplicissimo da fare.\n\nEquivalente italiano: Facile come bere un bicchiere d'acqua / È una passeggiata." ),
	llm_idiom_item( '🍕 Food-related expressions', 'Spill the beans', "Si può tradurre come «Versare i fagioli»: significa rivelare un segreto o dire tutto quello che si sa.\n\nEquivalente italiano: Spifferare tutto / Cantare." ),
	llm_idiom_item( '🍕 Food-related expressions', "Don't cry over spilled milk", "Si può tradurre come «Non piangere sul latte versato»: è inutile disperarsi per un errore passato che non si può più correggere.\n\nEquivalente italiano: Non piangere sul latte versato." ),
	llm_idiom_item( '🐺 Animal expressions', 'Break a leg!', "Si può tradurre come «Rompiti una gamba!»: augurio di buona fortuna (soprattutto a teatro o prima di una prova importante).\n\nEquivalente italiano: In bocca al lupo! (risposta: Crepi!)." ),
	llm_idiom_item( '🐺 Animal expressions', 'Kill two birds with one stone', "Si può tradurre come «Uccidere due uccelli con una pietra»: ottenere due risultati utili con un'unica azione.\n\nEquivalente italiano: Prendere due piccioni con una fava." ),
	llm_idiom_item( '🐺 Animal expressions', 'Feel like a fish out of water', "Si può tradurre come «Sentirsi un pesce fuori dall'acqua»: sentirsi a disagio o fuori posto in un ambiente.\n\nEquivalente italiano: Essere un pesce fuor d'acqua." ),
	llm_idiom_item( '🌍 Everyday life', "That's none of my business", "Si può tradurre come «Non sono affari miei»: una situazione non ci riguarda e non vogliamo intrometterci.\n\nEquivalente italiano: Non sono affari miei." ),
	llm_idiom_item( '🌍 Everyday life', 'Buy something sight unseen', "Si può tradurre come «Comprare senza aver visto»: acquistare senza aver controllato o visto di persona.\n\nEquivalente italiano: Comprare a scatola chiusa." ),
	llm_idiom_item( '🌍 Everyday life', 'Be under the weather', "Si può tradurre come «Essere sotto il tempo»: non sentirsi bene, essere un po' giù o leggermente malati.\n\nEquivalente italiano: Non sentirsi in forma / Essere un po' giù." ),
	llm_idiom_item( '🌍 Everyday life', 'Hit the books', "Si può tradurre come «Colpire i libri»: mettersi a studiare sul serio.\n\nEquivalente italiano: Mettersi sui libri / Studiare sodo." ),
);

$it_pl = array(
	llm_idiom_item( '🍕 Espressioni legate al cibo', 'Bułka z masłem', "Si può tradurre come «Un panino con il burro»: significa che qualcosa è semplicissimo da fare.\n\nEquivalente italiano: Facile come bere un bicchiere d'acqua." ),
	llm_idiom_item( '🍕 Espressioni legate al cibo', 'Mleko się rozlało', "Si può tradurre come «Il latte si è versato»: è inutile lamentarsi di un errore ormai fatto.\n\nEquivalente italiano: Non piangere sul latte versato." ),
	llm_idiom_item( '🍕 Espressioni legate al cibo', 'Upiec dwie pieczenie na jednym ogniu', "Si può tradurre come «Cuocere due arrosti sullo stesso fuoco»: ottenere due risultati con un'unica azione.\n\nEquivalente italiano: Prendere due piccioni con una fava." ),
	llm_idiom_item( '🐺 Espressioni legate agli animali', 'Nie mój cyrk, nie moje małpy', "Si può tradurre come «Non è il mio circo, non sono le mie scimmie»: non è affar mio, non voglio intromettermi.\n\nEquivalente italiano: Non sono affari miei." ),
	llm_idiom_item( '🐺 Espressioni legate agli animali', 'Czuć się jak ryba w wodzie', "Si può tradurre come «Sentirsi come un pesce nell'acqua»: sentirsi a proprio agio in un ambiente.\n\nEquivalente italiano: Essere come un pesce nell'acqua (opposto di «pesce fuor d'acqua»)." ),
	llm_idiom_item( '🐺 Espressioni legate agli animali', 'Pasować jak pięść do nosa', "Si può tradurre come «Starci come un pugno sul naso»: non c'entrare per niente / essere del tutto fuori luogo.\n\nEquivalente italiano: C'entrare come i cavoli a merenda." ),
	llm_idiom_item( '🌍 Vita quotidiana', 'Połamania nóg!', "Si può tradurre come «Rompiti le gambe!»: augurio di buona fortuna.\n\nEquivalente italiano: In bocca al lupo!" ),
	llm_idiom_item( '🌍 Vita quotidiana', 'Kupować kota w worku', "Si può tradurre come «Comprare un gatto nel sacco»: acquistare senza aver controllato.\n\nEquivalente italiano: Comprare a scatola chiusa." ),
	llm_idiom_item( '🌍 Vita quotidiana', 'Dostawać białej gorączki', "Si può tradurre come «Prendere la febbre bianca»: essere estremamente arrabbiati o irritati.\n\nEquivalente italiano: Avere un diavolo per capello." ),
	llm_idiom_item( '🌍 Vita quotidiana', 'Gdzie pieprz rośnie', "Si può tradurre come «Dove cresce il pepe»: mandare qualcuno lontano / dirgli di non scocciare più.\n\nEquivalente italiano: Mandare a quel paese." ),
);

llm_seed_idiom_bank( 'Espressioni IT→EN', 'it', 'en', $it_en );
llm_seed_idiom_bank( 'Espressioni IT→PL', 'it', 'pl', $it_pl );
llm_seed_idiom_bank( 'Espressioni PL→IT (włoski)', 'pl', 'it', $pl_it );

echo "\nFatto.\n";
