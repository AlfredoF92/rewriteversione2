<?php
/**
 * Aggiorna Milo e Toby: 20 frasi + note EN/IT + intro/finale solo EN.
 *
 * @package LLM_Tabelle
 */

ini_set( 'display_errors', '1' );
error_reporting( E_ALL );

$allow_web = ( defined( 'LLM_MILO_IMPORT_WEB' ) && LLM_MILO_IMPORT_WEB ) || ( php_sapi_name() !== 'cli' );

if ( ! defined( 'ABSPATH' ) ) {
	if ( php_sapi_name() !== 'cli' ) {
		$key = isset( $_GET['key'] ) ? (string) $_GET['key'] : '';
		if ( ! hash_equals( 'milo-toby-import-20260926', $key ) ) {
			http_response_code( 403 );
			exit( 'forbidden' );
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
	}
	$wp_load = '';
	foreach (
		array(
			dirname( __DIR__, 4 ) . '/wp-load.php',
			dirname( __DIR__, 3 ) . '/wp-load.php',
			dirname( __DIR__, 2 ) . '/wp-load.php',
		) as $cand
	) {
		if ( is_file( $cand ) ) {
			$wp_load = $cand;
			break;
		}
	}
	if ( '' === $wp_load ) {
		echo "wp-load.php non trovato.\n";
		exit( 1 );
	}
	require $wp_load;
}

if ( ! class_exists( 'LLM_Story_Repository' ) || ! class_exists( 'LLM_Story_Meta' ) ) {
	echo "Plugin LLM CON TABELLE non caricato.\n";
	exit( 1 );
}

/**
 * @return list<array{interface:string,target:string,notes:string,notes_target:string}>
 */
function llm_milo_toby_phrases_v2() {
	return array(
		array(
			'interface'    => 'Where is everyone?',
			'target'       => 'Dove sono tutti?',
			'notes'        => 'Milo looks for the family in the kitchen, in the bedrooms and in the garden. He can\'t find anyone and begins to realize that he and Toby have been left alone.',
			'notes_target' => 'Milo cerca la famiglia in cucina, nelle camere e in giardino. Non trova nessuno e comincia a capire che lui e Toby sono rimasti soli.',
		),
		array(
			'interface'    => 'How come we\'re alone?',
			'target'       => 'Come mai siamo soli?',
			'notes'        => 'Toby looks out of the window and sees that the car is gone too. The two remember the chaos of the morning and finally understand what happened.',
			'notes_target' => 'Toby guarda fuori dalla finestra e vede che anche l\'auto non c\'è più. I due ricordano la confusione della mattina e capiscono finalmente cosa è successo.',
		),
		array(
			'interface'    => 'What do we do now?',
			'target'       => 'Che facciamo adesso?',
			'notes'        => 'After a few minutes of fear, Toby starts to see the bright side of the situation. For the first time, they can run on the sofa, play and eat without anyone saying no.',
			'notes_target' => 'Dopo qualche minuto di paura, Toby comincia a vedere il lato positivo della situazione. Per la prima volta possono correre sul divano, giocare e mangiare senza che nessuno dica di no.',
		),
		array(
			'interface'    => 'What do you want to eat?',
			'target'       => 'Che cosa vuoi mangiare?',
			'notes'        => 'Milo opens a cupboard and finds lots of tasty things. Toby stares at the food with wide eyes and immediately forgets his fear.',
			'notes_target' => 'Milo apre una credenza e trova tantissime cose buone. Toby guarda il cibo con gli occhi grandi e dimentica immediatamente la paura.',
		),
		array(
			'interface'    => 'How much food should we take?',
			'target'       => 'Quanto cibo prendiamo?',
			'notes'        => 'The two carry biscuits, bread and cheese into the living room. Toby would like to take everything, but Milo reminds him that the family might be away for several days.',
			'notes_target' => 'I due portano biscotti, pane e formaggio in soggiorno. Toby vorrebbe prendere tutto, ma Milo gli ricorda che la famiglia potrebbe restare lontana per diversi giorni.',
		),
		array(
			'interface'    => 'When are they coming back?',
			'target'       => 'Quando tornano?',
			'notes'        => 'Milo checks the calendar by the door. He doesn\'t like the answer: the family won\'t be back that evening.',
			'notes_target' => 'Milo controlla il calendario vicino alla porta. La risposta non gli piace: la famiglia non tornerà quella sera.',
		),
		array(
			'interface'    => 'Who\'s outside?',
			'target'       => 'Chi c\'è fuori?',
			'notes'        => 'Suddenly they hear a noise in the garden. Toby runs to the window and sees a shadow moving near the door.',
			'notes_target' => 'Improvvisamente sentono un rumore in giardino. Toby corre alla finestra e vede un\'ombra muoversi vicino alla porta.',
		),
		array(
			'interface'    => 'What can you hear?',
			'target'       => 'Che cosa senti?',
			'notes'        => 'Milo turns off the light and stays silent. Someone is walking slowly around the house and trying to open a window.',
			'notes_target' => 'Milo spegne la luce e rimane in silenzio. Qualcuno cammina lentamente intorno alla casa e prova ad aprire una finestra.',
		),
		array(
			'interface'    => 'How come he\'s here?',
			'target'       => 'Come mai è qui?',
			'notes'        => 'The two finally see the intruder: it\'s a raccoon with a big bag. Milo realizes that he probably thinks the house is empty.',
			'notes_target' => 'I due vedono finalmente l\'intruso: è un procione con una grande borsa. Milo capisce che probabilmente pensa che la casa sia vuota.',
		),
		array(
			'interface'    => 'Who\'s with him? Why do they want to get in?',
			'target'       => 'Chi è con lui? Perché vogliono entrare?',
			'notes'        => 'A second animal appears behind the first raccoon. The two look into the kitchen through the window and point at the big box of food the family left behind.',
			'notes_target' => 'Dietro il primo procione compare un secondo animale. I due osservano la cucina dalla finestra e indicano la grande scatola di cibo lasciata dalla famiglia.',
		),
		array(
			'interface'    => 'Where can we hide?',
			'target'       => 'Dove possiamo nasconderci?',
			'notes'        => 'Milo and Toby run upstairs. They don\'t want to be seen, but they want to keep an eye on what the two intruders are doing.',
			'notes_target' => 'Milo e Toby corrono al piano superiore. Non vogliono essere visti, ma vogliono continuare a controllare quello che fanno i due intrusi.',
		),
		array(
			'interface'    => 'How can we stop them?',
			'target'       => 'Come possiamo fermarli?',
			'notes'        => 'Milo has an idea: instead of running away, they can make the raccoons believe there are still lots of people in the house. Toby is thrilled and immediately starts preparing the plan.',
			'notes_target' => 'Milo ha un\'idea: invece di scappare, possono far credere ai procioni che in casa ci siano ancora molte persone. Toby è entusiasta e comincia subito a preparare il piano.',
		),
		array(
			'interface'    => 'What should we take?',
			'target'       => 'Che cosa prendiamo?',
			'notes'        => 'The two look for anything that might be useful: a rope, some boxes, a few cushions and an old radio.',
			'notes_target' => 'I due cercano tutto quello che può essere utile: una corda, alcune scatole, dei cuscini e una vecchia radio.',
		),
		array(
			'interface'    => 'What kind of trap should we set? When should we set it?',
			'target'       => 'Che tipo di trappola prepariamo? Quando la prepariamo?',
			'notes'        => 'Milo suggests a simple trap near the door, and Toby wants to turn on the radio to make noise. They have to do everything right away, because the raccoons could come back at any moment.',
			'notes_target' => 'Milo propone una trappola semplice vicino alla porta e Toby vuole accendere la radio per creare rumore. Devono fare tutto subito, perché i procioni potrebbero tornare da un momento all\'altro.',
		),
		array(
			'interface'    => 'How much time do we have?',
			'target'       => 'Quanto tempo abbiamo?',
			'notes'        => 'Toby looks out of the window and sees the two raccoons approaching again. There are only a few minutes left and the house isn\'t ready yet.',
			'notes_target' => 'Toby guarda dalla finestra e vede i due procioni avvicinarsi di nuovo. Mancano solo pochi minuti e la casa non è ancora pronta.',
		),
		array(
			'interface'    => 'Why is the door opening?',
			'target'       => 'Perché la porta si sta aprendo?',
			'notes'        => 'Milo turns around and sees the handle slowly moving. The raccoons have found a way in.',
			'notes_target' => 'Milo si gira e vede lentamente muoversi la maniglia. I procioni hanno trovato un modo per entrare.',
		),
		array(
			'interface'    => 'Who\'s coming first? How are you, Toby?',
			'target'       => 'Chi viene per primo? Come stai, Toby?',
			'notes'        => 'One of the raccoons puts a paw inside the house. Milo looks at Toby: the dog is scared, but he decides not to abandon his friend.',
			'notes_target' => 'Uno dei procioni mette una zampa dentro la casa. Milo guarda Toby: il cane ha paura, ma decide di non abbandonare il suo amico.',
		),
		array(
			'interface'    => 'What do you say?',
			'target'       => 'Che ne dici?',
			'notes'        => 'Milo points at the radio and looks at Toby. It\'s time to start their plan, and Toby answers with a firm nod.',
			'notes_target' => 'Milo indica la radio e guarda Toby. È il momento di iniziare il loro piano e Toby risponde con un cenno deciso.',
		),
		array(
			'interface'    => 'Where are they going? Why are they running away?',
			'target'       => 'Dove vanno? Perché stanno scappando?',
			'notes'        => 'The radio starts playing, the boxes fall and Toby starts barking really loudly. The raccoons think the family has come back and run out of the house.',
			'notes_target' => 'La radio parte, le scatole cadono e Toby comincia ad abbaiare fortissimo. I procioni credono che la famiglia sia tornata e corrono fuori dalla casa.',
		),
		array(
			'interface'    => 'How did it go? How long until the family comes back?',
			'target'       => 'Come è andata? Quanto manca al ritorno della famiglia?',
			'notes'        => 'Milo and Toby look at the mess they\'ve made and start laughing. They\'ve saved the house, but now they have another problem: they have to tidy everything up before the family gets back.',
			'notes_target' => 'Milo e Toby guardano il disastro che hanno creato e cominciano a ridere. Hanno salvato la casa, ma adesso hanno un altro problema: devono sistemare tutto prima che torni la famiglia.',
		),
	);
}

$story_id = 10597;
$post     = get_post( $story_id );
if ( ! $post || LLM_STORY_CPT !== $post->post_type ) {
	$by_slug = get_page_by_path( 'milo-e-toby-soli-in-casa', OBJECT, LLM_STORY_CPT );
	if ( $by_slug ) {
		$story_id = (int) $by_slug->ID;
		$post     = $by_slug;
	}
}
if ( ! $post || LLM_STORY_CPT !== $post->post_type ) {
	echo "Storia non trovata.\n";
	exit( 1 );
}

echo "Aggiorno story_id={$story_id}\n";

update_post_meta( $story_id, LLM_Story_Meta::KNOWN_LANG, 'en' );
update_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, 'it' );
update_post_meta( $story_id, LLM_Story_Meta::TITLE_TARGET, 'Milo e Toby: Soli in Casa' );

$intro = "It's the morning before the holidays. The house is in chaos: suitcases, bags and clothes are everywhere. Milo, a very clever young cat, is sleeping inside a box, while Toby, a curious and brave dog, is sleeping under the table. The family rushes out of the house and leaves for a long trip. When Milo and Toby wake up, they notice a strange silence. They don't know yet that they are about to have the most incredible night of their lives.";

$finale = 'The next morning, Milo and Toby are still busy putting everything back in order. They hide the rope, pick up the cushions and bring the food back to the kitchen. Then they hear a car in front of the house. This time they know exactly who it is. The family opens the door, and Milo and Toby run to greet them. Everything looks almost normal, but a small raccoon paw print has been left on the floor. The family looks at it, puzzled. Milo looks at Toby. Toby looks at Milo. Neither of them says a word.';

$plot = 'When their family leaves for a holiday, clever cat Milo and brave dog Toby find themselves home alone. At first it feels like freedom — sofas, snacks, no rules. Then two raccoons try to break in, and the pets must use wit, traps and a lot of noise to protect the house before morning.';

$card = 'A clever cat and a brave dog home alone — until raccoons try to break in.';

update_post_meta( $story_id, LLM_Story_Meta::STORY_INTRO, $intro );
update_post_meta( $story_id, LLM_Story_Meta::STORY_FINALE, $finale );
update_post_meta( $story_id, LLM_Story_Meta::STORY_PLOT, $plot );
update_post_meta( $story_id, LLM_Story_Meta::STORY_CARD_TEXT, $card );
update_post_meta( $story_id, LLM_Story_Meta::STORY_CEFR_LEVEL, 'A2' );
update_post_meta(
	$story_id,
	LLM_Story_Meta::STORY_GRAMMAR_TOPICS,
	"Question words (chi, che, dove, come, quando, perché, quanto)\nPresent tense narrative\nModal-like expressions (possiamo, dobbiamo)\nSimple connectors (ma, poi, perché)"
);

$phrases = llm_milo_toby_phrases_v2();
LLM_Story_Repository::save_phrases( $story_id, $phrases );
echo 'Frasi salvate: ' . count( $phrases ) . "\n";

if ( class_exists( 'LLM_Story_Checklist' ) ) {
	LLM_Story_Checklist::refresh( $story_id );
}

echo 'URL=' . get_permalink( $story_id ) . "\n";
echo 'Edit=' . admin_url( 'post.php?post=' . $story_id . '&action=edit' ) . "\n";
echo "OK\n";
