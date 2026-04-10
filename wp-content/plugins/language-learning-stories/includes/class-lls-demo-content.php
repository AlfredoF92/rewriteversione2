<?php
/**
 * Storie di esempio (una tantum).
 *
 * @package LLS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLS_Demo_Content {

	const OPTION_KEY = 'lls_demo_stories_v1_imported';

	/** Percorso relativo a uploads. */
	const DEMO_IMAGE_REL_PATH = '2026/04/Il-lupo-e-Cappuccett.jpg';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_import' ), 30 );
	}

	/**
	 * Importa 3 storie demo se non già fatto.
	 */
	public static function maybe_import() {
		if ( ! current_user_can( 'publish_posts' ) ) {
			return;
		}
		if ( get_option( self::OPTION_KEY, '' ) === '1' ) {
			return;
		}

		$attach_id = self::get_or_create_attachment_for_demo_image();
		$stories   = self::get_demo_definitions( $attach_id );

		foreach ( $stories as $def ) {
			$exists = get_posts(
				array(
					'post_type'      => LLS_CPT,
					'name'           => $def['slug'],
					'posts_per_page' => 1,
					'post_status'    => 'any',
					'fields'         => 'ids',
				)
			);
			if ( ! empty( $exists ) ) {
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => LLS_CPT,
					'post_status'  => 'publish',
					'post_title'   => $def['title'],
					'post_name'    => $def['slug'],
					'post_content' => $def['content'],
					'post_excerpt' => $def['excerpt'],
				),
				true
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			update_post_meta( $post_id, '_lls_demo', '1' );
			update_post_meta( $post_id, LLS_Story_Meta::KNOWN_LANG, $def['known_lang'] );
			update_post_meta( $post_id, LLS_Story_Meta::TARGET_LANG, $def['target_lang'] );
			update_post_meta( $post_id, LLS_Story_Meta::TITLE_TARGET, $def['title_target'] );
			update_post_meta( $post_id, LLS_Story_Meta::STORY_PLOT, $def['plot'] );
			update_post_meta( $post_id, LLS_Story_Meta::COIN_COST, 0 );
			update_post_meta( $post_id, LLS_Story_Meta::COIN_REWARD, 5 );
			update_post_meta( $post_id, LLS_Story_Meta::PHRASES, wp_json_encode( $def['phrases'] ) );
			if ( ! empty( $def['media_blocks'] ) ) {
				update_post_meta( $post_id, LLS_Story_Meta::MEDIA_BLOCKS, wp_json_encode( $def['media_blocks'] ) );
			}
		}

		update_option( self::OPTION_KEY, '1', false );
	}

	/**
	 * Crea o recupera l’attachment per il file immagine demo in uploads.
	 *
	 * @return int Attachment ID o 0.
	 */
	public static function get_or_create_attachment_for_demo_image() {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return 0;
		}

		$rel  = self::DEMO_IMAGE_REL_PATH;
		$file = trailingslashit( $upload['basedir'] ) . $rel;
		if ( ! is_readable( $file ) ) {
			return 0;
		}

		$url = trailingslashit( $upload['baseurl'] ) . $rel;
		$existing = attachment_url_to_postid( $url );
		if ( $existing ) {
			return (int) $existing;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$filetype = wp_check_filetype( basename( $file ), null );
		$attachment = array(
			'post_mime_type' => $filetype['type'] ? $filetype['type'] : 'image/jpeg',
			'post_title'     => sanitize_file_name( pathinfo( $file, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attach_id = wp_insert_attachment( $attachment, $file );
		if ( ! $attach_id || is_wp_error( $attach_id ) ) {
			return 0;
		}

		$meta = wp_generate_attachment_metadata( $attach_id, $file );
		if ( ! empty( $meta ) ) {
			wp_update_attachment_metadata( $attach_id, $meta );
		}

		return (int) $attach_id;
	}

	/**
	 * Definizioni storie: 5 frasi ciascuna, immagini con posizioni variate.
	 *
	 * @param int $image_id ID attachment immagine condivisa.
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_demo_definitions( $image_id ) {
		$img = absint( $image_id );

		$mk_media = function ( array $positions ) use ( $img ) {
			if ( ! $img ) {
				return array();
			}
			$out = array();
			foreach ( $positions as $after ) {
				$out[] = array(
					'attachment_id'      => $img,
					'after_phrase_index' => (int) $after,
				);
			}
			return $out;
		};

		return array(
			array(
				'slug'         => 'lls-demo-cappuccetto',
				'title'        => 'Cappuccetto Rosso',
				'title_target' => 'Little Red Riding Hood',
				'known_lang'   => 'it',
				'target_lang'  => 'en',
				'plot'         => 'Una bambina attraversa il bosco per portare dolci alla nonna; nel sentiero incontra un lupo che le chiede informazioni. La nonna abita in una casa isolata: il lupo arriva prima e si nasconde.',
				'content'      => '<p>Una delle fiabe più note per esercitare narrativa semplice al passato e vocabolario quotidiano.</p>',
				'excerpt'      => 'Fiaba classica: bosco, nonna e lupo.',
				'phrases'      => array(
					array(
						'interface' => 'C’era una volta una bambina che viveva in un villaggio vicino al bosco.',
						'target'    => 'Once upon a time there was a little girl who lived in a village near the woods.',
						'grammar'   => 'Past simple / there was: struttura narrativa tipica delle fiabe.',
						'alt'       => 'Long ago, a little girl lived in a village by the forest.',
					),
					array(
						'interface' => 'Sua madre le chiese di portare una cesta alla nonna, che abitava dall’altra parte del bosco.',
						'target'    => 'Her mother asked her to take a basket to her grandmother, who lived on the other side of the woods.',
						'grammar'   => 'Ask someone to + infinitive; relative clause con “who”.',
						'alt'       => 'Her mother told her to bring a basket to Grandma across the woods.',
					),
					array(
						'interface' => 'Nel sentiero incontrò un lupo che le parlava con voce dolce.',
						'target'    => 'On the path she met a wolf who spoke to her in a gentle voice.',
						'grammar'   => 'Meet / met; relative clause; “who” per persone/animali antropomorfi.',
						'alt'       => 'She met a wolf on the path; it spoke softly to her.',
					),
					array(
						'interface' => 'Il lupo le chiese dove abitasse la nonna e corse per arrivare prima di lei.',
						'target'    => 'The wolf asked her where her grandmother lived and ran to get there before her.',
						'grammar'   => 'Indirect question (where…); before + pronoun.',
						'alt'       => 'The wolf asked for Grandma’s address and ran ahead.',
					),
					array(
						'interface' => 'Quando Cappuccetto arrivò, trovò la porta socchiusa e sentì un rumore strano in casa.',
						'target'    => 'When Little Red arrived, she found the door ajar and heard a strange noise inside.',
						'grammar'   => 'When + past simple; find + object + complement (ajar).',
						'alt'       => 'She arrived and noticed the door was slightly open; something felt wrong.',
					),
				),
				'media_blocks' => $mk_media( array( -1, 1, 3 ) ),
			),
			array(
				'slug'         => 'lls-demo-viaggio-in-treno',
				'title'        => 'Un viaggio in treno',
				'title_target' => 'A train journey',
				'known_lang'   => 'it',
				'target_lang'  => 'en',
				'plot'         => 'Due amici prenotano i posti, salgono sul treno e osservano il paesaggio. Chiacchierano del tempo e del viaggio fino alla destinazione.',
				'content'      => '<p>Dialoghi utili per biglietti, orari e paesaggio che scorre fuori dal finestrino.</p>',
				'excerpt'      => 'Vocabolario viaggi e trasporti.',
				'phrases'      => array(
					array(
						'interface' => 'Abbiamo prenotato due posti vicino al finestrino per vedere meglio il paesaggio.',
						'target'    => 'We booked two seats by the window so we could see the landscape better.',
						'grammar'   => 'Past simple (booked); “so (that)” + could.',
						'alt'       => 'We reserved window seats for a better view.',
					),
					array(
						'interface' => 'Il treno è in orario, ma fa molte fermate prima della nostra destinazione.',
						'target'    => 'The train is on time, but it makes many stops before our destination.',
						'grammar'   => 'Present simple; “make stops”; before + noun.',
						'alt'       => 'It’s punctual, yet there are several stops along the way.',
					),
					array(
						'interface' => 'Sul display compare il nome della prossima stazione: controlliamo i bagagli.',
						'target'    => 'The next station name appears on the display: let’s check our luggage.',
						'grammar'   => 'Present simple; imperative (let’s); possessive adjectives.',
						'alt'       => 'The screen shows the next stop; we should verify our bags.',
					),
					array(
						'interface' => 'Fuori dal finestrino i campi sembrano infiniti sotto un cielo grigio.',
						'target'    => 'Outside the window the fields look endless under a grey sky.',
						'grammar'   => 'Look + adjective; “under” per cielo.',
						'alt'       => 'Beyond the glass, fields stretch away beneath a cloudy sky.',
					),
					array(
						'interface' => 'Tra un’ora saremo arrivati: prepariamo i documenti per l’uscita.',
						'target'    => 'We will arrive in an hour: let’s get our documents ready for getting off.',
						'grammar'   => 'Future (will); prepare for + gerund/noun.',
						'alt'       => 'We’ll arrive in sixty minutes—have tickets ready to leave.',
					),
				),
				'media_blocks' => $mk_media( array( 0, 2, 4 ) ),
			),
			array(
				'slug'         => 'lls-demo-al-ristorante',
				'title'        => 'Al ristorante',
				'title_target' => 'At the restaurant',
				'known_lang'   => 'it',
				'target_lang'  => 'en',
				'plot'         => 'Una coppia entra al ristorante, legge il menù e ordina antipasto e primo. Chiedono acqua e discutono del vino consigliato dal cameriere.',
				'content'      => '<p>Utile per ordinare cibo, bere e gestire richieste semplici al personale.</p>',
				'excerpt'      => 'Ordini, menù e cortesia.',
				'phrases'      => array(
					array(
						'interface' => 'Buonasera, un tavolo per due, per favore. Possiamo sederci vicino alla finestra?',
						'target'    => 'Good evening, a table for two, please. Can we sit near the window?',
						'grammar'   => 'Polite requests; can for permission; prepositions (near).',
						'alt'       => 'Evening—table for two? We’d like to sit by the window.',
					),
					array(
						'interface' => 'Il cameriere ci porta il menù e ci chiede se preferiamo acqua naturale o frizzante.',
						'target'    => 'The waiter brings us the menu and asks whether we prefer still or sparkling water.',
						'grammar'   => 'Whether / if; bring; prefer.',
						'alt'       => 'He hands us menus and asks still or sparkling water.',
					),
					array(
						'interface' => 'Come antipasto prendiamo le verdure grigliate; come primo gli spaghetti al pomodoro.',
						'target'    => 'For starters we’ll have grilled vegetables; for the main course, spaghetti with tomato sauce.',
						'grammar'   => 'Will for decisions; “for starters/main course”.',
						'alt'       => 'Starters: grilled veg. Main: spaghetti al pomodoro.',
					),
					array(
						'interface' => 'Il vino della casa è leggero: va bene con il pesce che abbiamo ordinato.',
						'target'    => 'The house wine is light: it goes well with the fish we ordered.',
						'grammar'   => 'Present simple; relative clause; go well with.',
						'alt'       => 'The house white is light—pairs nicely with our fish.',
					),
					array(
						'interface' => 'Per finire, un caffè e il conto, grazie. È stato tutto delizioso.',
						'target'    => 'To finish, a coffee and the bill, please. Everything was delicious.',
						'grammar'   => 'Polite closing; past simple (was); bill/check.',
						'alt'       => 'Coffee and the check, please—it was wonderful.',
					),
				),
				'media_blocks' => $mk_media( array( 2, 0, -1 ) ),
			),
		);
	}
}
