<?php
/**
 * Crea 3 categorie WordPress, 9 storie LLM ciascuna (27 totali).
 * Lingua obiettivo: inglese (en), lingua interfaccia: italiano (it).
 * 5 frasi per storia, trama ~20–30 parole, featured image casuale da URL noti.
 *
 * Esecuzione (da cartella del sito WordPress, es. htdocs/rewrite):
 *   c:\xampp\php\php.exe wp-content\plugins\llm-con-tabelle\scripts\seed-27-stories-english.php
 *
 * Elimina e ricrea solo i post marcati da questo script:
 *   c:\xampp\php\php.exe wp-content\plugins\llm-con-tabelle\scripts\seed-27-stories-english.php --purge
 *
 * @package LLM_Tabelle
 */

if ( php_sapi_name() !== 'cli' ) {
	exit( 'Solo CLI.' );
}

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	fwrite( STDERR, "Impossibile trovare wp-load.php. Percorso atteso: {$wp_load}\n" );
	exit( 1 );
}

require_once $wp_load;

if ( ! function_exists( 'wp_insert_post' ) || ! class_exists( 'LLM_Story_Repository' ) ) {
	fwrite( STDERR, "WordPress o plugin LLM CON TABELLE non caricati.\n" );
	exit( 1 );
}

const LLM_SEED_BATCH_META = '_llm_seed_batch';
const LLM_SEED_BATCH_ID  = 'seed_en_27_stories_2026';

$purge = in_array( '--purge', $argv, true );

/** @var list<string> */
$image_urls = array(
	'http://localhost/rewrite/wp-content/uploads/2026/04/tre-porcellini.jpg',
	'http://localhost/rewrite/wp-content/uploads/2026/04/storie-da-favola-favole-nella-storia.png',
	'http://localhost/rewrite/wp-content/uploads/2026/04/nwFavoleCaffo-kLo-U32601013240676GkG-656x492@Corriere-Web-Sezioni.jpg',
	'http://localhost/rewrite/wp-content/uploads/2026/04/Fiabe._cosi.png',
	'http://localhost/rewrite/wp-content/uploads/2026/04/favole-per-bambini.jpg',
	'http://localhost/rewrite/wp-content/uploads/2026/04/Favole.jpg',
	'http://localhost/rewrite/wp-content/uploads/2026/04/favola.jpg',
	'http://localhost/rewrite/wp-content/uploads/2026/04/cop-scaled-1.jpg',
	'http://localhost/rewrite/wp-content/uploads/2026/04/6398347_Notte_da_favola2.jpg',
	'http://localhost/rewrite/wp-content/uploads/2026/04/images.jpg',
	'http://localhost/rewrite/wp-content/uploads/2026/04/The_Ant_and_the_Grasshopper_-_Project_Gutenberg_etext_19994.jpg',
	'http://localhost/rewrite/wp-content/uploads/2026/04/ThumbJpeg.jpg',
);

$categories_config = array(
	array(
		'name' => 'Favole del bosco',
		'slug' => 'llm-seed-favole-bosco',
		'desc' => 'Storie demo per chi impara l’inglese: animali, sentieri e piccole avventure.',
	),
	array(
		'name' => 'Fiabe della notte',
		'slug' => 'llm-seed-fiabe-notte',
		'desc' => 'Storie demo serali: luna, sogni e morali dolci con frasi in inglese.',
	),
	array(
		'name' => 'Storie di animali',
		'slug' => 'llm-seed-storie-animali',
		'desc' => 'Protagonisti pelosi o piumati: vocaboli utili per principianti di inglese.',
	),
);

/**
 * @param string $url
 * @return int
 */
function llm_seed_attachment_id_from_url( $url ) {
	$id = attachment_url_to_postid( $url );
	if ( $id ) {
		return (int) $id;
	}
	$path = wp_parse_url( $url, PHP_URL_PATH );
	if ( ! is_string( $path ) || $path === '' ) {
		return 0;
	}
	$uploads = wp_upload_dir();
	$base     = trailingslashit( $uploads['baseurl'] );
	if ( strpos( $url, $base ) === 0 ) {
		$rel = ltrim( substr( $url, strlen( $base ) ), '/' );
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
		$found = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
				$rel
			)
		);
		if ( $found ) {
			return $found;
		}
		$file = basename( $rel );
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQueryWithPlaceholder
		$found = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1",
				'%' . $wpdb->esc_like( $file )
			)
		);
		return $found;
	}
	return 0;
}

/**
 * Trama tra 20 e 30 parole (italiano).
 *
 * @param string $cat_name
 * @param int    $story_num 1–9
 * @param int    $global_idx 1–27
 * @return string
 */
function llm_seed_build_plot( $cat_name, $story_num, $global_idx ) {
	$chunks = array(
		sprintf(
			'Nella raccolta «%s», episodio %d di nove, un protagonista modesto incontra una piccola prova quotidiana.',
			$cat_name,
			$story_num
		),
		'Il tono è calmo, i dialoghi brevi e il finale positivo invita a ripetere le frasi in inglese con gradualità.',
		sprintf( 'La storia catalogo numero %d rafforza vocaboli semplici utili a principianti motivati.', $global_idx ),
		'Ambientazione chiara, ostacolo minimo e lieto fine chiudono il racconto senza fretta.',
	);
	$text    = implode( ' ', $chunks );
	$words   = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
	if ( count( $words ) > 30 ) {
		$words = array_slice( $words, 0, 30 );
	}
	$fillers = array(
		'Lo studio quotidiano aiuta memoria e pronuncia.',
		'Ogni ripetizione guidata rende l’ascolto più naturale.',
		'Le parole scelte supportano chi impara l’inglese passo dopo passo.',
	);
	while ( count( $words ) < 20 && $fillers ) {
		$words = array_merge( $words, preg_split( '/\s+/u', array_shift( $fillers ), -1, PREG_SPLIT_NO_EMPTY ) );
	}
	if ( count( $words ) > 30 ) {
		$words = array_slice( $words, 0, 30 );
	}
	return implode( ' ', $words );
}

/**
 * @param string $title_it
 * @param int    $idx 1-based story index in category
 * @return list<array{interface:string,target:string,grammar:string,alt:string}>
 */
function llm_seed_five_phrases( $title_it, $idx ) {
	$n = (int) $idx;
	return array(
		array(
			'interface' => sprintf( 'C’era una volta, nella storia «%s», un inizio quieto e promettente.', $title_it ),
			'target'    => sprintf( 'Once upon a time, in the story «%s», a quiet and promising beginning.', $title_it ),
			'grammar'   => 'Once upon a time: formula tipica delle fiabe; present simple per descrivere stato.',
			'alt'       => 'Long ago, in a peaceful start to the tale…',
		),
		array(
			'interface' => 'Il protagonista osserva il mondo intorno e decide di agire con gentilezza.',
			'target'    => 'The main character looks at the world around them and chooses to act with kindness.',
			'grammar'   => 'Present simple; choose + infinito per decisione.',
			'alt'       => 'The hero watches everything and acts kindly.',
		),
		array(
			'interface' => sprintf( 'Un piccolo ostacolo compare, ma numero %d della serie suggerisce calma e astuzia.', $n ),
			'target'    => sprintf( 'A small obstacle appears, but episode %d of the series suggests calm and cleverness.', $n ),
			'grammar'   => 'Suggest + sostantivo / verbo in -ing; appear al presente.',
			'alt'       => 'A tiny problem shows up; stay calm and think.',
		),
		array(
			'interface' => 'Gli amici (o la natura) offrono un suggerimento semplice e chiaro.',
			'target'    => 'Friends (or nature) offer a simple and clear suggestion.',
			'grammar'   => 'Offer + oggetto; aggettivi coordinati con and.',
			'alt'       => 'Someone gives a short, clear piece of advice.',
		),
		array(
			'interface' => 'Il lieto fine arriva piano: gratitudine, sorriso e invito a continuare a studiare.',
			'target'    => 'The happy ending arrives gently: gratitude, a smile, and an invitation to keep studying.',
			'grammar'   => 'To keep + -ing; invito con imperativo implicito nel sostantivo invitation.',
			'alt'       => 'Everything ends well, with thanks and encouragement to learn more.',
		),
	);
}

if ( $purge ) {
	$q = new WP_Query(
		array(
			'post_type'      => LLM_STORY_CPT,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => LLM_SEED_BATCH_META,
			'meta_value'     => LLM_SEED_BATCH_ID,
			'no_found_rows'  => true,
		)
	);
	foreach ( $q->posts as $pid ) {
		wp_delete_post( (int) $pid, true );
	}
	echo "Rimossi " . count( $q->posts ) . " post seed precedenti.\n";
}

$term_ids = array();
foreach ( $categories_config as $cfg ) {
	$term = term_exists( $cfg['slug'], 'category' );
	if ( $term ) {
		$tid = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
	} else {
		$t = wp_insert_term(
			$cfg['name'],
			'category',
			array(
				'slug'        => $cfg['slug'],
				'description' => $cfg['desc'],
			)
		);
		if ( is_wp_error( $t ) ) {
			fwrite( STDERR, 'Errore categoria ' . $cfg['slug'] . ': ' . $t->get_error_message() . "\n" );
			exit( 1 );
		}
		$tid = (int) $t['term_id'];
	}
	$term_ids[] = $tid;
	echo "Categoria OK: {$cfg['name']} (ID {$tid})\n";
}

$author_id = 1;
$users     = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! empty( $users ) ) {
	$author_id = (int) $users[0];
}

$global_idx = 0;
foreach ( $categories_config as $c => $cfg ) {
	$cat_term_id = $term_ids[ $c ];
	$cat_name    = $cfg['name'];

	for ( $s = 1; $s <= 9; $s++ ) {
		++$global_idx;
		$title_it    = sprintf( '%s — Storia %d', $cat_name, $s );
		$title_en    = sprintf( '%s — Story %d (English practice)', $cfg['slug'], $s );
		$plot        = llm_seed_build_plot( $cat_name, $s, $global_idx );
		$excerpt     = sprintf(
			'Demo per studenti di inglese: %s, episodio %d. Cinque frasi bilingue italiano–inglese.',
			$cat_name,
			$s
		);
		$content = sprintf(
			'<p>%s</p><p><em>Lingua nota: italiano · Lingua obiettivo: English</em></p>',
			esc_html( $plot )
		);

		$post_id = wp_insert_post(
			array(
				'post_type'    => LLM_STORY_CPT,
				'post_status'  => 'publish',
				'post_title'   => $title_it,
				'post_excerpt' => $excerpt,
				'post_content' => $content,
				'post_author'  => $author_id,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			fwrite( STDERR, 'Errore insert: ' . $post_id->get_error_message() . "\n" );
			exit( 1 );
		}

		update_post_meta( $post_id, LLM_SEED_BATCH_META, LLM_SEED_BATCH_ID );

		update_post_meta( $post_id, LLM_Story_Meta::KNOWN_LANG, 'it' );
		update_post_meta( $post_id, LLM_Story_Meta::TARGET_LANG, 'en' );
		update_post_meta( $post_id, LLM_Story_Meta::TITLE_TARGET, $title_en );
		update_post_meta( $post_id, LLM_Story_Meta::STORY_PLOT, $plot );
		update_post_meta( $post_id, LLM_Story_Meta::COIN_COST, 10 );
		update_post_meta( $post_id, LLM_Story_Meta::COIN_REWARD, 25 );

		wp_set_post_terms( $post_id, array( $cat_term_id ), 'category', false );

		LLM_Story_Repository::save_phrases( $post_id, llm_seed_five_phrases( $title_it, $s ) );

		$img_url = $image_urls[ array_rand( $image_urls ) ];
		$att_id  = llm_seed_attachment_id_from_url( $img_url );
		if ( $att_id && wp_attachment_is_image( $att_id ) ) {
			set_post_thumbnail( $post_id, $att_id );
		} else {
			echo "  [avviso] Nessun attachment per immagine: {$img_url}\n";
		}

		echo "Creato post {$post_id}: {$title_it}\n";
	}
}

echo "\nFatto: 3 categorie, 27 storie LLM (target en, 5 frasi, trama 20–30 parole).\n";
echo "Featured: URL casuali dalla lista (se i file esistono in Media).\n";
