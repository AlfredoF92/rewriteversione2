<?php
/**
 * Crea 9 storie LLM per utenti con lingua nota polacco (pl) che imparano italiano (it).
 * Ogni storia ha 5 frasi e immagine in evidenza casuale da URL forniti.
 *
 * Esecuzione:
 *   c:\xampp\php\php.exe wp-content\plugins\llm-con-tabelle\scripts\seed-9-stories-italian-from-polish.php
 *
 * Purge seed precedente:
 *   c:\xampp\php\php.exe wp-content\plugins\llm-con-tabelle\scripts\seed-9-stories-italian-from-polish.php --purge
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

if ( ! function_exists( 'wp_insert_post' ) || ! class_exists( 'LLM_Story_Repository' ) ) {
	fwrite( STDERR, "WordPress o plugin LLM non caricati.\n" );
	exit( 1 );
}

const LLM_SEED_BATCH_META = '_llm_seed_batch';
const LLM_SEED_BATCH_ID = 'seed_it_for_pl_9_stories_2026_04_20';

$purge = in_array( '--purge', $argv, true );

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

/**
 * @param string $url URL immagine.
 * @return int
 */
function llm_seed_attachment_id_from_url_pl_it( $url ) {
	$id = attachment_url_to_postid( $url );
	if ( $id ) {
		return (int) $id;
	}
	$path = wp_parse_url( $url, PHP_URL_PATH );
	if ( ! is_string( $path ) || '' === $path ) {
		return 0;
	}
	$uploads = wp_upload_dir();
	$base    = trailingslashit( $uploads['baseurl'] );
	if ( strpos( $url, $base ) !== 0 ) {
		return 0;
	}
	$rel = ltrim( substr( $url, strlen( $base ) ), '/' );
	global $wpdb;
	$found = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
			$rel
		)
	);
	if ( $found ) {
		return $found;
	}
	$file  = basename( $rel );
	$found = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1",
			'%' . $wpdb->esc_like( $file )
		)
	);
	return $found;
}

/**
 * @param string $title_pl Titolo in polacco.
 * @return array<int, array<string, string>>
 */
function llm_seed_five_phrases_pl_it( $title_pl ) {
	return array(
		array(
			'interface' => "W historii \"{$title_pl}\" bohater budzi sie bardzo wczesnie rano.",
			'target'    => 'Nella storia il protagonista si sveglia molto presto al mattino.',
			'grammar'   => 'In italiano: "si sveglia" al presente.',
			'alt'       => 'Il protagonista si alza presto al mattino.',
		),
		array(
			'interface' => 'Przygotowuje mala torbe i wychodzi z domu spokojnym krokiem.',
			'target'    => 'Prepara una piccola borsa ed esce di casa con passo tranquillo.',
			'grammar'   => 'Verbi al presente: prepara, esce.',
			'alt'       => 'Lui prepara la borsa e lascia casa con calma.',
		),
		array(
			'interface' => 'Po drodze spotyka przyjaciela, ktory oferuje mu pomoc.',
			'target'    => 'Per strada incontra un amico che gli offre aiuto.',
			'grammar'   => 'Costruzione: "che gli offre".',
			'alt'       => 'Lungo la strada incontra un amico disponibile.',
		),
		array(
			'interface' => 'Razem rozwiazuja maly problem i kontynuuja podroz.',
			'target'    => 'Insieme risolvono un piccolo problema e continuano il viaggio.',
			'grammar'   => 'Plurale al presente: risolvono, continuano.',
			'alt'       => 'Insieme trovano una soluzione e vanno avanti.',
		),
		array(
			'interface' => 'Na koncu dnia wracaja do domu z usmiechem i nowa energia.',
			'target'    => 'Alla fine della giornata tornano a casa con un sorriso e nuova energia.',
			'grammar'   => 'Espressione utile: "alla fine della giornata".',
			'alt'       => 'Alla sera rientrano felici e pieni di energia.',
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
	echo 'Rimossi seed precedenti: ' . count( $q->posts ) . "\n";
}

$term_slug = 'llm-seed-polish-to-italian';
$term_name = 'Storie per polacchi (italiano)';
$term_desc = 'Raccolta demo: interfaccia polacco, lingua da imparare italiano.';
$term      = term_exists( $term_slug, 'category' );
if ( $term ) {
	$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
} else {
	$created = wp_insert_term(
		$term_name,
		'category',
		array(
			'slug'        => $term_slug,
			'description' => $term_desc,
		)
	);
	if ( is_wp_error( $created ) ) {
		fwrite( STDERR, 'Errore creazione categoria: ' . $created->get_error_message() . "\n" );
		exit( 1 );
	}
	$term_id = (int) $created['term_id'];
}

$titles = array(
	'Poranek w malej wiosce',
	'Tajemnica starego mostu',
	'Spacer po lesie o swicie',
	'Przygoda na targu',
	'Wieczor przy latarni',
	'Podroz do spokojnego miasteczka',
	'List od dawnego przyjaciela',
	'Deszczowy dzien i dobre wiadomosci',
	'Nowy poczatek nad rzeka',
);

$author_id = 1;
$admins    = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
if ( ! empty( $admins ) ) {
	$author_id = (int) $admins[0];
}

foreach ( $titles as $idx => $title_pl ) {
	$story_no = $idx + 1;
	$excerpt  = "Historia {$story_no}: 5 frasi per imparare l'italiano partendo dal polacco.";
	$content  = '<p>Storia demo per studenti con lingua interfaccia polacco e lingua target italiano.</p>';
	$title_it = "PL->IT {$story_no} - {$title_pl}";

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
		fwrite( STDERR, 'Errore creazione post: ' . $post_id->get_error_message() . "\n" );
		exit( 1 );
	}

	update_post_meta( $post_id, LLM_SEED_BATCH_META, LLM_SEED_BATCH_ID );
	update_post_meta( $post_id, LLM_Story_Meta::KNOWN_LANG, 'pl' );
	update_post_meta( $post_id, LLM_Story_Meta::TARGET_LANG, 'it' );
	update_post_meta( $post_id, LLM_Story_Meta::TITLE_TARGET, "Storia {$story_no}" );
	update_post_meta( $post_id, LLM_Story_Meta::STORY_PLOT, "Breve storia {$story_no} per studenti polacchi che imparano italiano." );
	update_post_meta( $post_id, LLM_Story_Meta::COIN_COST, 0 );
	update_post_meta( $post_id, LLM_Story_Meta::COIN_REWARD, 0 );

	wp_set_post_terms( $post_id, array( $term_id ), 'category', false );
	LLM_Story_Repository::save_phrases( $post_id, llm_seed_five_phrases_pl_it( $title_pl ) );

	$img_url = $image_urls[ array_rand( $image_urls ) ];
	$att_id  = llm_seed_attachment_id_from_url_pl_it( $img_url );
	if ( $att_id && wp_attachment_is_image( $att_id ) ) {
		set_post_thumbnail( $post_id, $att_id );
	}

	echo "Creata storia {$post_id}: {$title_it}\n";
}

echo "\nFatto: create 9 storie (known=pl, target=it), 5 frasi ciascuna.\n";
