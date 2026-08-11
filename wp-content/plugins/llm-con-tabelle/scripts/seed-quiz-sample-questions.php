<?php
/**
 * Seed: 2 banche quiz di prova (it→en, it→pl), 10 domande ciascuna.
 *
 * Uso CLI (da root WP):
 *   C:\xampp\php\php.exe wp-content/plugins/llm-con-tabelle/scripts/seed-quiz-sample-questions.php
 *
 * Uso web (solo se LLM_QUIZ_SEED_WEB è definito dal wrapper one-shot).
 *
 * @package LLM_Tabelle
 */

$llm_quiz_seed_web = defined( 'LLM_QUIZ_SEED_WEB' ) && LLM_QUIZ_SEED_WEB;
if ( php_sapi_name() !== 'cli' && ! $llm_quiz_seed_web ) {
	exit( "CLI only.\n" );
}

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_file( $wp_load ) ) {
	fwrite( STDERR, "wp-load.php non trovato: {$wp_load}\n" );
	exit( 1 );
}

require $wp_load;

if ( ! class_exists( 'LLM_Quiz' ) ) {
	fwrite( STDERR, "Plugin LLM CON TABELLE / LLM_Quiz non caricato.\n" );
	exit( 1 );
}

/**
 * @param string               $title    Titolo post.
 * @param string               $known    Codice lingua nota.
 * @param string               $target   Codice lingua obiettivo.
 * @param array<int,array>     $questions Domande.
 * @return int Post ID.
 */
function llm_seed_quiz_bank( $title, $known, $target, array $questions ) {
	$existing = LLM_Quiz::find_for_pair( $known, $target );
	if ( $existing ) {
		$post_id = $existing;
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_title'  => $title,
				'post_status' => 'publish',
			)
		);
		echo "Aggiorno quiz esistente #{$post_id} ({$known}→{$target})\n";
	} else {
		$post_id = wp_insert_post(
			array(
				'post_type'   => LLM_Quiz::CPT,
				'post_title'  => $title,
				'post_status' => 'publish',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			fwrite( STDERR, $post_id->get_error_message() . "\n" );
			exit( 1 );
		}
		echo "Creato quiz #{$post_id} ({$known}→{$target})\n";
	}

	update_post_meta( $post_id, LLM_Quiz::META_KNOWN, $known );
	update_post_meta( $post_id, LLM_Quiz::META_TARGET, $target );
	LLM_Quiz::save_questions( $post_id, $questions );
	echo '  Domande salvate: ' . count( LLM_Quiz::get_questions( $post_id ) ) . "\n";
	return (int) $post_id;
}

/**
 * @param string $category Categoria.
 * @param string $q        Domanda.
 * @param array  $a        Tre risposte [text, explanation].
 * @param int    $correct  Indice 0..2 della risposta corretta.
 * @return array
 */
function llm_q( $category, $q, array $a, $correct = 0 ) {
	return array(
		'id'       => LLM_Quiz::new_question_id(),
		'category' => $category,
		'question' => $q,
		'correct'  => $correct,
		'answers'  => array(
			array(
				'text'        => $a[0][0],
				'explanation' => $a[0][1],
			),
			array(
				'text'        => $a[1][0],
				'explanation' => $a[1][1],
			),
			array(
				'text'        => $a[2][0],
				'explanation' => $a[2][1],
			),
		),
	);
}

$cat_country = 'Curiosità sul paese';
$cat_italy   = 'Curiosità sull’Italia';
$cat_poland  = 'Curiosità sulla Polonia';
$cat_history = 'Eventi storici';

$it_en = array(
	llm_q(
		$cat_italy,
		'Perché a Napoli la pizza Margherita è legata ai colori della bandiera?',
		array(
			array( 'Pomodoro, mozzarella e basilico', 'Rosso, bianco e verde richiamano i tre colori del tricolore: la pizza fu dedicata alla regina Margherita di Savoia nel 1889.' ),
			array( 'Solo per il nome della regina', 'Il nome onora la regina, ma i colori degli ingredienti sono la spiegazione “ufficiosa” più famosa della tradizione napoletana.' ),
			array( 'Era un ordine militare', 'Non era un piatto militare: nacque come omaggio reale e diventò simbolo della cucina italiana nel mondo.' ),
		),
		0
	),
	llm_q(
		$cat_country,
		'Big Ben (UK) è il nome di cosa, di preciso?',
		array(
			array( 'Della campana grande', 'Tecnicamente Big Ben è la campana: la torre oggi si chiama Elizabeth Tower, ma nel parlato comune “Big Ben” indica tutto l’insieme.' ),
			array( 'Solo dell’orologio', 'L’orologio è famoso, ma il soprannome storico “Big Ben” nasce dalla campana, non dal meccanismo.' ),
			array( 'Di tutta Londra', 'È un simbolo di Londra, ma il nome non indica la città: indica (in origine) la campana del Palazzo di Westminster.' ),
		),
		0
	),
	llm_q(
		$cat_history,
		'Nel 1066 cosa cambia la storia dell’Inghilterra?',
		array(
			array( 'La conquista normanna', 'Guglielmo il Conquistatore vince a Hastings: arriva il francese-normanno nella lingua e nella cultura inglese.' ),
			array( 'La nascita del tè', 'Il tè arriverà secoli dopo dai commerci con l’Asia: nel 1066 il tema centrale è politico-militare, non gastronomico.' ),
			array( 'L’indipendenza americana', 'L’indipendenza USA è del 1776: molto dopo. Nel 1066 siamo ancora nel Medioevo inglese.' ),
		),
		0
	),
	llm_q(
		$cat_italy,
		'Perché Venezia è “costruita sull’acqua”?',
		array(
			array( 'Su palafitte e isole della laguna', 'La città sorge su isole lagunari: edifici e fondamenta poggiano su legni conficcati nel fondale, una tecnica antica e ingeniosa.' ),
			array( 'Su un unico ponte di pietra', 'Ci sono tanti ponti, ma Venezia non poggia su un solo ponte: è un tessuto di isole, canali e fondamenta lignee.' ),
			array( 'Su una diga moderna', 'Le dighe e il MOSE sono opere recenti di protezione: la struttura storica della città è lagunare e medievale.' ),
		),
		0
	),
	llm_q(
		$cat_country,
		'Perché New York è chiamata “The Big Apple”?',
		array(
			array( 'Slogan e giornali degli anni ’20', 'Il soprannome si diffuse con giornalisti e mondi dello sport/spettacolo: “big apple” = grande opportunità, grande città.' ),
			array( 'Per i meleti di Central Park', 'Central Park ha alberi, ma non è l’origine del nome: è una metafora culturale, non botanica.' ),
			array( 'Per una legge sulle mele', 'Non c’è una legge sulle mele dietro al nickname: è linguaggio popolare e marketing urbano.' ),
		),
		0
	),
	llm_q(
		$cat_history,
		'Cosa celebra il 4 luglio negli Stati Uniti?',
		array(
			array( 'La Dichiarazione d’Indipendenza (1776)', 'Il Independence Day ricorda il 4 luglio 1776: le colonie americane dichiarano indipendenza dalla Gran Bretagna.' ),
			array( 'La fine della Prima Guerra Mondiale', 'L’armistizio della Grande Guerra è l’11 novembre 1918: un’altra ricorrenza, in altri paesi.' ),
			array( 'L’arrivo di Colombo', 'Colombo è legato al 12 ottobre (e a dibattiti storici): non al 4 luglio americano.' ),
		),
		0
	),
	llm_q(
		$cat_italy,
		'Cosa rende speciale il Colosseo rispetto a molti edifici antichi?',
		array(
			array( 'Era un anfiteatro per spettacoli pubblici', 'Il Colosseo (Anfiteatro Flavio) ospitava giochi e spettacoli: è architettura romana pensata per grandi folle.' ),
			array( 'Era solo un tempio chiuso', 'Non era un tempio tipico: la pianta ellittica e gli ordini di archi servivano allo spettacolo, non al culto chiuso.' ),
			array( 'Fu costruito nel Rinascimento', 'È romano imperiale (I secolo d.C.): il Rinascimento arriverà oltre mille anni dopo.' ),
		),
		0
	),
	llm_q(
		$cat_country,
		'Perché si guida a sinistra in Gran Bretagna?',
		array(
			array( 'Tradizione storica (spada / cavallo)', 'Una spiegazione storica comune: a cavallo si teneva la destra libera per la spada; la regola rimase e fu codificata.' ),
			array( 'Perché le strade sono più strette', 'Ci sono strade strette, ma non è la causa ufficiale: è una convenzione storica diversa da gran parte d’Europa.' ),
			array( 'Per un accordo con l’UE', 'Il Regno Unito guida a sinistra da molto prima dell’UE; non dipende da un accordo europeo recente.' ),
		),
		0
	),
	llm_q(
		$cat_history,
		'L’Unità d’Italia (XIX secolo) è legata soprattutto a quale processo?',
		array(
			array( 'Il Risorgimento', 'Il Risorgimento unisce progressivamente gli Stati italiani: figure come Cavour, Garibaldi e Vittorio Emanuele II sono centrali.' ),
			array( 'La fondazione di Roma antica', 'Roma antica è millenni prima: l’Unità è un processo moderno dell’Ottocento.' ),
			array( 'La scoperta dell’America', 'Il 1492 riguarda l’espansione atlantica: non spiega l’unificazione politica italiana dell’Ottocento.' ),
		),
		0
	),
	llm_q(
		$cat_country,
		'Cosa indica tipicamente “afternoon tea” nel mondo anglofono?',
		array(
			array( 'Uno spuntino con tè e dolci/salati', 'Nell’immaginario britannico è una pausa pomeridiana: tè, sandwich piccoli, scones e dolci — più rituale sociale che “cena”.' ),
			array( 'Solo un bicchiere di tè al bar', 'Si può bere tè ovunque, ma “afternoon tea” richiama un format culturale più ricco e cerimonioso.' ),
			array( 'Una festa di Capodanno', 'Non è legata a Capodanno: è una tradizione pomeridiana, non una festa di fine anno.' ),
		),
		0
	),
);

$it_pl = array(
	llm_q(
		$cat_poland,
		'Perché Varsavia ha una “città vecchia” ricostruita?',
		array(
			array( 'Fu devastata nella Seconda Guerra Mondiale', 'Gran parte del centro storico fu distrutta; la ricostruzione postbellica è così fedele da essere patrimonio UNESCO.' ),
			array( 'Fu spostata di 100 km', 'Varsavia non fu “traslocata”: restò sul posto, ma doveva rinascere dalle macerie.' ),
			array( 'È una città inventata per i turisti', 'È vera storia urbana: ricostruzione civile e memoria nazionale, non un parco a tema.' ),
		),
		0
	),
	llm_q(
		$cat_italy,
		'Perché si dice che “tutte le strade portano a Roma”?',
		array(
			array( 'Per la rete di vie consolari romane', 'Nell’antichità le grandi strade imperiali convergevano su Roma: la frase è diventata proverbio sul “centro” di un sistema.' ),
			array( 'Perché Roma ha più metro di Milano', 'La metro è moderna: il proverbio nasce dalla geografia viaria antica, non dal trasporto odierno.' ),
			array( 'Perché era l’unica città d’Italia', 'C’erano tante città: Roma era però il baricentro politico e stradale dell’impero.' ),
		),
		0
	),
	llm_q(
		$cat_history,
		'Cosa ricorda Solidarność (Solidarietà) in Polonia?',
		array(
			array( 'Il sindacato e il movimento contro il regime', 'Nato a Gdańsk (1980) intorno a Lech Wałęsa: fu chiave del cambiamento politico che portò verso la fine del comunismo.' ),
			array( 'Una squadra di calcio nazionale', 'Non è un club sportivo: è un movimento sociale e sindacale di portata storica europea.' ),
			array( 'Una festa di primavera', 'Non è una festa stagionale: è storia politica del tardo Novecento.' ),
		),
		0
	),
	llm_q(
		$cat_poland,
		'Cosa sono tipicamente i pierogi?',
		array(
			array( 'Ravioli ripieni (salati o dolci)', 'I pierogi sono pasta ripiena: formaggio, patate, carne, frutta… un comfort food nazionale con tante varianti regionali.' ),
			array( 'Una zuppa di barbabietola', 'La zuppa di barbabietola è piuttosto il barszcz: vicino nella cucina polacca, ma diverso dai pierogi.' ),
			array( 'Un pane dolce di Natale', 'Esistono dolci natalizi, ma i pierogi non sono “il” panettone polacco: sono pasta ripiena.' ),
		),
		0
	),
	llm_q(
		$cat_italy,
		'Perché il Vesuvio è famoso accanto a Napoli?',
		array(
			array( 'È un vulcano attivo legato a Pompei', 'L’eruzione del 79 d.C. seppellì Pompei ed Ercolano: oggi il Vesuvio resta un vulcano sorvegliato e icona del golfo.' ),
			array( 'È una collina artificiale', 'Non è artificiale: è un complesso vulcanico naturale, tra i più studiati al mondo.' ),
			array( 'Si è spento definitivamente nel Medioevo', 'Non è “spento per sempre”: è considerato attivo, anche se i tempi delle eruzioni sono irregolari.' ),
		),
		0
	),
	llm_q(
		$cat_history,
		'Nel 1410 a Grunwald (Tannenberg) cosa succede?',
		array(
			array( 'Una grande vittoria polacco-lituana', 'La battaglia di Grunwald è una vittoria storica contro l’Ordine Teutonico: mito fondativo della memoria polacca.' ),
			array( 'L’incoronazione di Carlo Magno', 'Carlo Magno è dell’800 d.C.: epoca diversa e contesto franco-europeo, non polacco-lituano del 1410.' ),
			array( 'La fondazione di Cracovia', 'Cracovia è molto più antica come insediamento importante; Grunwald è una battaglia, non la fondazione della città.' ),
		),
		0
	),
	llm_q(
		$cat_poland,
		'Perché Cracovia è spesso amata dai visitatori?',
		array(
			array( 'Centro storico, Wawel e vita universitaria', 'Cracovia conserva un nucleo storico ricco (piazza del Mercato, castello Wawel) e un’atmosfera culturale/universitaria molto viva.' ),
			array( 'È la capitale attuale', 'La capitale odierna è Varsavia: Cracovia lo fu in passato ed è ancora un grande polo culturale.' ),
			array( 'Non ha edifici antichi', 'Al contrario: è famosa proprio per il patrimonio storico e architettonico.' ),
		),
		0
	),
	llm_q(
		$cat_italy,
		'Cosa rende unica la Torre di Pisa nel racconto popolare?',
		array(
			array( 'La pendenza accidentale', 'La torre “pendente” inclinò per il terreno soffice: oggi è un’icona mondiale proprio per quel difetto diventato fama.' ),
			array( 'È la torre più alta d’Europa', 'Non lo è: la fama non nasce dall’altezza record, ma dall’inclinazione e dalla storia del campanile.' ),
			array( 'Fu costruita in un giorno', 'Impiegò secoli, a fasi: non è un’opera lampo, ed è proprio nei secoli che l’inclinazione si accentuò.' ),
		),
		0
	),
	llm_q(
		$cat_history,
		'Cosa segna il 1989 in Polonia (e in Europa centro-orientale)?',
		array(
			array( 'Le elezioni semi-libere e la svolta democratica', 'Nel 1989 la Polonia apre a elezioni che cambiano il potere: è un tassello centrale della fine dei regimi comunisti in Europa.' ),
			array( 'L’ingresso nell’euro', 'La Polonia non adotta l’euro come valuta nazionale: il 1989 è politico, non monetario UE.' ),
			array( 'L’inizio della Seconda Guerra Mondiale', 'La guerra inizia nel 1939: cinquant’anni prima. Il 1989 è invece la svolta della fine Guerra Fredda.' ),
		),
		0
	),
	llm_q(
		$cat_poland,
		'Cosa è Chopin per la Polonia?',
		array(
			array( 'Compositore-simbolo della cultura nazionale', 'Fryderyk Chopin è un’icona: piano, romantico, memoria nazionale — aeroporti, musei e concerti portano il suo nome.' ),
			array( 'Un re medievale', 'Non era un monarca: era musicista. I re polacchi appartengono ad altre epoche e biografie.' ),
			array( 'L’inventore della vodka', 'La vodka ha una storia produttiva complessa: non “inventata” da Chopin. Lui è musica, non distillazione.' ),
		),
		0
	),
);

llm_seed_quiz_bank( 'Quiz prova IT→EN — curiosità e storia', 'it', 'en', $it_en );
llm_seed_quiz_bank( 'Quiz prova IT→PL — curiosità e storia', 'it', 'pl', $it_pl );

echo "\nFatto.\n";
