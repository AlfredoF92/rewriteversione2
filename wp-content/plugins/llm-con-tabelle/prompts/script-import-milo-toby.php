<?php
/**
 * Import one-shot: Milo e Toby soli in casa (EN known → IT target).
 *
 *   C:\xampp\php\php.exe "prompt/CREA STORIA/script-import-milo-toby.php"
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
if ( '' === $wp_load ) {
	fwrite( STDERR, "wp-load.php non trovato.\n" );
	exit( 1 );
}

require $wp_load;

if ( ! class_exists( 'LLM_Story_Repository' ) || ! class_exists( 'LLM_Story_Meta' ) ) {
	fwrite( STDERR, "Plugin LLM CON TABELLE non caricato.\n" );
	exit( 1 );
}

@ini_set( 'memory_limit', '256M' );

/**
 * @return list<array{interface:string,target:string}>
 */
function llm_milo_toby_phrases() {
	// interface = EN (known), target = IT (learning)
	return array(
		array(
			'interface' => "It's the morning before the holidays.",
			'target'    => 'È la mattina prima delle vacanze.',
		),
		array(
			'interface' => 'The house is in chaos: suitcases, bags and clothes are everywhere.',
			'target'    => "In casa c'è molta confusione: valigie, borse e vestiti sono dappertutto.",
		),
		array(
			'interface' => 'Milo, a very clever young cat, is sleeping inside a box, while Toby, a curious and brave dog, is sleeping under the table.',
			'target'    => 'Milo, un giovane gatto molto intelligente, dorme dentro una scatola, mentre Toby, un cane curioso e coraggioso, dorme sotto il tavolo.',
		),
		array(
			'interface' => 'The family rushes out of the house and leaves for a long trip.',
			'target'    => 'La famiglia esce di casa di fretta e parte per un lungo viaggio.',
		),
		array(
			'interface' => 'When Milo and Toby wake up, they notice a strange silence.',
			'target'    => 'Quando Milo e Toby si svegliano, sentono uno strano silenzio.',
		),
		array(
			'interface' => 'They don\'t know yet that they are about to have the most incredible night of their lives.',
			'target'    => 'Non sanno ancora che stanno per vivere la notte più incredibile della loro vita.',
		),
		array(
			'interface' => 'Where is everyone?',
			'target'    => 'Dove sono tutti?',
		),
		array(
			'interface' => 'Milo looks for the family in the kitchen, in the bedrooms and in the garden.',
			'target'    => 'Milo cerca la famiglia in cucina, nelle camere e in giardino.',
		),
		array(
			'interface' => 'He can\'t find anyone and begins to realize that he and Toby have been left alone.',
			'target'    => 'Non trova nessuno e comincia a capire che lui e Toby sono rimasti soli.',
		),
		array(
			'interface' => "How come we're alone?",
			'target'    => 'Come mai siamo soli?',
		),
		array(
			'interface' => 'Toby looks out of the window and sees that the car is gone too.',
			'target'    => "Toby guarda fuori dalla finestra e vede che anche l'auto non c'è più.",
		),
		array(
			'interface' => 'The two remember the chaos of the morning and finally understand what happened.',
			'target'    => 'I due ricordano la confusione della mattina e capiscono finalmente cosa è successo.',
		),
		array(
			'interface' => 'What do we do now?',
			'target'    => 'Che facciamo adesso?',
		),
		array(
			'interface' => 'After a few minutes of fear, Toby starts to see the bright side of the situation.',
			'target'    => 'Dopo qualche minuto di paura, Toby comincia a vedere il lato positivo della situazione.',
		),
		array(
			'interface' => 'For the first time, they can run on the sofa, play and eat without anyone saying no.',
			'target'    => 'Per la prima volta possono correre sul divano, giocare e mangiare senza che nessuno dica di no.',
		),
		array(
			'interface' => 'What do you want to eat?',
			'target'    => 'Che cosa vuoi mangiare?',
		),
		array(
			'interface' => 'Milo opens a cupboard and finds lots of tasty things.',
			'target'    => 'Milo apre una credenza e trova tantissime cose buone.',
		),
		array(
			'interface' => 'Toby stares at the food with wide eyes and immediately forgets his fear.',
			'target'    => 'Toby guarda il cibo con gli occhi grandi e dimentica immediatamente la paura.',
		),
		array(
			'interface' => 'How much food should we take?',
			'target'    => 'Quanto cibo prendiamo?',
		),
		array(
			'interface' => 'The two carry biscuits, bread and cheese into the living room.',
			'target'    => 'I due portano biscotti, pane e formaggio in soggiorno.',
		),
		array(
			'interface' => 'Toby would like to take everything, but Milo reminds him that the family might be away for several days.',
			'target'    => 'Toby vorrebbe prendere tutto, ma Milo gli ricorda che la famiglia potrebbe restare lontana per diversi giorni.',
		),
		array(
			'interface' => 'When are they coming back?',
			'target'    => 'Quando tornano?',
		),
		array(
			'interface' => 'Milo checks the calendar by the door.',
			'target'    => 'Milo controlla il calendario vicino alla porta.',
		),
		array(
			'interface' => "He doesn't like the answer: the family won't be back that evening.",
			'target'    => 'La risposta non gli piace: la famiglia non tornerà quella sera.',
		),
		array(
			'interface' => "Who's outside?",
			'target'    => "Chi c'è fuori?",
		),
		array(
			'interface' => 'Suddenly they hear a noise in the garden.',
			'target'    => 'Improvvisamente sentono un rumore in giardino.',
		),
		array(
			'interface' => 'Toby runs to the window and sees a shadow moving near the door.',
			'target'    => "Toby corre alla finestra e vede un'ombra muoversi vicino alla porta.",
		),
		array(
			'interface' => 'What can you hear?',
			'target'    => 'Che cosa senti?',
		),
		array(
			'interface' => 'Milo turns off the light and stays silent.',
			'target'    => 'Milo spegne la luce e rimane in silenzio.',
		),
		array(
			'interface' => 'Someone is walking slowly around the house and trying to open a window.',
			'target'    => 'Qualcuno cammina lentamente intorno alla casa e prova ad aprire una finestra.',
		),
		array(
			'interface' => "How come he's here?",
			'target'    => 'Come mai è qui?',
		),
		array(
			'interface' => "The two finally see the intruder: it's a raccoon with a big bag.",
			'target'    => "I due vedono finalmente l'intruso: è un procione con una grande borsa.",
		),
		array(
			'interface' => 'Milo realizes that he probably thinks the house is empty.',
			'target'    => 'Milo capisce che probabilmente pensa che la casa sia vuota.',
		),
		array(
			'interface' => "Who's with him? Why do they want to get in?",
			'target'    => 'Chi è con lui? Perché vogliono entrare?',
		),
		array(
			'interface' => 'A second animal appears behind the first raccoon.',
			'target'    => 'Dietro il primo procione compare un secondo animale.',
		),
		array(
			'interface' => 'The two look into the kitchen through the window and point at the big box of food the family left behind.',
			'target'    => 'I due osservano la cucina dalla finestra e indicano la grande scatola di cibo lasciata dalla famiglia.',
		),
		array(
			'interface' => 'Where can we hide?',
			'target'    => 'Dove possiamo nasconderci?',
		),
		array(
			'interface' => 'Milo and Toby run upstairs.',
			'target'    => 'Milo e Toby corrono al piano superiore.',
		),
		array(
			'interface' => "They don't want to be seen, but they want to keep an eye on what the two intruders are doing.",
			'target'    => 'Non vogliono essere visti, ma vogliono continuare a controllare quello che fanno i due intrusi.',
		),
		array(
			'interface' => 'How can we stop them?',
			'target'    => 'Come possiamo fermarli?',
		),
		array(
			'interface' => 'Milo has an idea: instead of running away, they can make the raccoons believe there are still lots of people in the house.',
			'target'    => "Milo ha un'idea: invece di scappare, possono far credere ai procioni che in casa ci siano ancora molte persone.",
		),
		array(
			'interface' => 'Toby is thrilled and immediately starts preparing the plan.',
			'target'    => 'Toby è entusiasta e comincia subito a preparare il piano.',
		),
		array(
			'interface' => 'What should we take?',
			'target'    => 'Che cosa prendiamo?',
		),
		array(
			'interface' => 'The two look for anything that might be useful: a rope, some boxes, a few cushions and an old radio.',
			'target'    => 'I due cercano tutto quello che può essere utile: una corda, alcune scatole, dei cuscini e una vecchia radio.',
		),
		array(
			'interface' => 'What kind of trap should we set? When should we set it?',
			'target'    => 'Che tipo di trappola prepariamo? Quando la prepariamo?',
		),
		array(
			'interface' => 'Milo suggests a simple trap near the door, and Toby wants to turn on the radio to make noise.',
			'target'    => 'Milo propone una trappola semplice vicino alla porta e Toby vuole accendere la radio per creare rumore.',
		),
		array(
			'interface' => 'They have to do everything right away, because the raccoons could come back at any moment.',
			'target'    => 'Devono fare tutto subito, perché i procioni potrebbero tornare da un momento all\'altro.',
		),
		array(
			'interface' => 'How much time do we have?',
			'target'    => 'Quanto tempo abbiamo?',
		),
		array(
			'interface' => 'Toby looks out of the window and sees the two raccoons approaching again.',
			'target'    => 'Toby guarda dalla finestra e vede i due procioni avvicinarsi di nuovo.',
		),
		array(
			'interface' => "There are only a few minutes left and the house isn't ready yet.",
			'target'    => 'Mancano solo pochi minuti e la casa non è ancora pronta.',
		),
		array(
			'interface' => 'Why is the door opening?',
			'target'    => 'Perché la porta si sta aprendo?',
		),
		array(
			'interface' => 'Milo turns around and sees the handle slowly moving.',
			'target'    => 'Milo si gira e vede lentamente muoversi la maniglia.',
		),
		array(
			'interface' => 'The raccoons have found a way in.',
			'target'    => 'I procioni hanno trovato un modo per entrare.',
		),
		array(
			'interface' => "Who's coming first? How are you, Toby?",
			'target'    => 'Chi viene per primo? Come stai, Toby?',
		),
		array(
			'interface' => 'One of the raccoons puts a paw inside the house.',
			'target'    => 'Uno dei procioni mette una zampa dentro la casa.',
		),
		array(
			'interface' => 'Milo looks at Toby: the dog is scared, but he decides not to abandon his friend.',
			'target'    => 'Milo guarda Toby: il cane ha paura, ma decide di non abbandonare il suo amico.',
		),
		array(
			'interface' => 'What do you say?',
			'target'    => 'Che ne dici?',
		),
		array(
			'interface' => 'Milo points at the radio and looks at Toby.',
			'target'    => 'Milo indica la radio e guarda Toby.',
		),
		array(
			'interface' => "It's time to start their plan, and Toby answers with a firm nod.",
			'target'    => 'È il momento di iniziare il loro piano e Toby risponde con un cenno deciso.',
		),
		array(
			'interface' => 'Where are they going? Why are they running away?',
			'target'    => 'Dove vanno? Perché stanno scappando?',
		),
		array(
			'interface' => 'The radio starts playing, the boxes fall and Toby starts barking really loudly.',
			'target'    => 'La radio parte, le scatole cadono e Toby comincia ad abbaiare fortissimo.',
		),
		array(
			'interface' => 'The raccoons think the family has come back and run out of the house.',
			'target'    => 'I procioni credono che la famiglia sia tornata e corrono fuori dalla casa.',
		),
		array(
			'interface' => 'How did it go? How long until the family comes back?',
			'target'    => 'Come è andata? Quanto manca al ritorno della famiglia?',
		),
		array(
			'interface' => "Milo and Toby look at the mess they've made and start laughing.",
			'target'    => 'Milo e Toby guardano il disastro che hanno creato e cominciano a ridere.',
		),
		array(
			'interface' => "They've saved the house, but now they have another problem: they have to tidy everything up before the family gets back.",
			'target'    => 'Hanno salvato la casa, ma adesso hanno un altro problema: devono sistemare tutto prima che torni la famiglia.',
		),
		array(
			'interface' => 'The next morning, Milo and Toby are still busy putting everything back in order.',
			'target'    => 'La mattina seguente Milo e Toby sono ancora impegnati a mettere tutto in ordine.',
		),
		array(
			'interface' => 'They hide the rope, pick up the cushions and bring the food back to the kitchen.',
			'target'    => 'Nascondono la corda, raccolgono i cuscini e riportano il cibo in cucina.',
		),
		array(
			'interface' => 'Then they hear a car in front of the house.',
			'target'    => "Poi sentono un'auto davanti alla casa.",
		),
		array(
			'interface' => 'This time they know exactly who it is.',
			'target'    => 'Questa volta sanno esattamente chi è.',
		),
		array(
			'interface' => 'The family opens the door, and Milo and Toby run to greet them.',
			'target'    => 'La famiglia apre la porta e Milo e Toby corrono ad accoglierla.',
		),
		array(
			'interface' => 'Everything looks almost normal, but a small raccoon paw print has been left on the floor.',
			'target'    => 'Tutto sembra quasi normale, ma sul pavimento è rimasta una piccola impronta di procione.',
		),
		array(
			'interface' => 'The family looks at it, puzzled.',
			'target'    => 'La famiglia la guarda senza capire.',
		),
		array(
			'interface' => 'Milo looks at Toby. Toby looks at Milo.',
			'target'    => 'Milo guarda Toby. Toby guarda Milo.',
		),
		array(
			'interface' => 'Neither of them says a word.',
			'target'    => 'Nessuno dei due dice niente.',
		),
	);
}

$slug = 'milo-e-toby-soli-in-casa';
$existing = get_page_by_path( $slug, OBJECT, LLM_STORY_CPT );
if ( $existing ) {
	$story_id = (int) $existing->ID;
	fwrite( STDOUT, "Storia già esistente ID={$story_id}, aggiorno.\n" );
	wp_update_post(
		array(
			'ID'          => $story_id,
			'post_title'  => 'Milo and Toby Home Alone',
			'post_status' => 'draft',
			'post_name'   => $slug,
		)
	);
} else {
	$story_id = (int) wp_insert_post(
		array(
			'post_type'    => LLM_STORY_CPT,
			'post_title'   => 'Milo and Toby Home Alone',
			'post_name'    => $slug,
			'post_status'  => 'draft',
			'post_content' => '',
		),
		true
	);
	if ( is_wp_error( $story_id ) || ! $story_id ) {
		fwrite( STDERR, "Creazione post fallita.\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "Creata storia ID={$story_id}\n" );
}

update_post_meta( $story_id, LLM_Story_Meta::KNOWN_LANG, 'en' );
update_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, 'it' );
update_post_meta( $story_id, LLM_Story_Meta::TITLE_TARGET, 'Milo e Toby: Soli in Casa' );

$plot = 'When their family leaves for a holiday, clever cat Milo and brave dog Toby find themselves home alone. At first it feels like freedom — sofas, snacks, no rules. Then two raccoons try to break in, and the pets must use wit, traps and a lot of noise to protect the house before morning.';
$intro = "It's the morning before the holidays. The house is full of suitcases and chaos. Milo sleeps in a box, Toby under the table — and neither of them knows they are about to face the most incredible night of their lives.";
$finale = 'By morning the house looks almost normal again. The family walks in, and Milo and Toby run to greet them. Only a tiny raccoon paw print remains on the floor — a secret neither pet will ever tell.';
$card = 'A clever cat and a brave dog home alone — until raccoons try to break in.';

update_post_meta( $story_id, LLM_Story_Meta::STORY_PLOT, $plot );
update_post_meta( $story_id, LLM_Story_Meta::STORY_INTRO, $intro );
update_post_meta( $story_id, LLM_Story_Meta::STORY_FINALE, $finale );
update_post_meta( $story_id, LLM_Story_Meta::STORY_CARD_TEXT, $card );
update_post_meta( $story_id, LLM_Story_Meta::STORY_CEFR_LEVEL, 'A2' );
update_post_meta(
	$story_id,
	LLM_Story_Meta::STORY_GRAMMAR_TOPICS,
	"Question words (chi, che, dove, come, quando, perché, quanto)\nPresent tense narrative\nModal-like expressions (possiamo, dobbiamo)\nSimple connectors (ma, poi, perché)"
);

$phrases = llm_milo_toby_phrases();
LLM_Story_Repository::save_phrases( $story_id, $phrases );
fwrite( STDOUT, 'Frasi salvate: ' . count( $phrases ) . "\n" );

$cover_src = 'C:/Users/gabri/.cursor/projects/c-xampp-htdocs-localloverewrite202608/assets/c__Users_gabri_AppData_Roaming_Cursor_User_workspaceStorage_086259f04e8c39dfd9fe315c0bb9b3c9_images_image-f54fb398-ac62-4eb0-8634-d2d6c8a40851.jpg';
if ( is_readable( $cover_src ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = wp_tempnam( 'milo-toby-cover.jpg' );
	if ( $tmp && copy( $cover_src, $tmp ) ) {
		$file_array = array(
			'name'     => 'milo-e-toby-soli-in-casa-cover.jpg',
			'tmp_name' => $tmp,
		);
		$att_id = media_handle_sideload( $file_array, $story_id );
		if ( is_wp_error( $att_id ) ) {
			fwrite( STDERR, 'Cover upload error: ' . $att_id->get_error_message() . "\n" );
			@unlink( $tmp );
		} else {
			set_post_thumbnail( $story_id, (int) $att_id );
			fwrite( STDOUT, "Cover impostata attachment={$att_id}\n" );
		}
	} else {
		fwrite( STDERR, "Impossibile copiare cover temporanea.\n" );
	}
} else {
	fwrite( STDERR, "Cover non trovata: {$cover_src}\n" );
}

if ( class_exists( 'LLM_Story_Checklist' ) ) {
	LLM_Story_Checklist::refresh( $story_id );
}

$url = get_permalink( $story_id );
fwrite( STDOUT, "OK story_id={$story_id}\n" );
fwrite( STDOUT, "URL={$url}\n" );
fwrite( STDOUT, 'Edit: ' . admin_url( 'post.php?post=' . $story_id . '&action=edit' ) . "\n" );
