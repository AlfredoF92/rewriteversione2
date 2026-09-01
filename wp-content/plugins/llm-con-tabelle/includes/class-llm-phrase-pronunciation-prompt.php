<?php
/**
 * Prompt e seed del campo pronuncia delle frasi.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Phrase_Pronunciation_Prompt {

	const SEED_OPTION = 'llm_seed_david_pronunciation_first3';

	const SEED_IPA_OPTION = 'llm_seed_david_ipa_approx_first3';

	/**
	 * Prompt per generare il campo pronuncia (LINGUA B = lingua che lo studente già parla).
	 *
	 * @param string $lingua_b Nome della lingua B (es. italiano).
	 * @return string
	 */
	public static function for_lingua_b( $lingua_b ) {
		$lingua_b = trim( (string) $lingua_b );
		if ( '' === $lingua_b ) {
			$lingua_b = 'italiano';
		}

		return "Scomponi la frase parola per parola. Per ogni parola scrivi ESATTAMENTE così:\n\n"
			. "Parola --> /trascrizione IPA/ (pronuncia approssimata in stile LINGUA B)\n"
			. "Massimo due frasi di consiglio, come un insegnante paziente che spiega a un bambino come si pronuncia.\n\n"
			. "Esempio di forma:\n\n"
			. "Dzień --> /dʑɛɲ/ (GIÈN)\n"
			. "Di’ “già”, ma più piano e morbido. Alla fine fai la “gn” di “gnocco”, non una “n” normale.\n\n"
			. "Regole:\n"
			. "- Tono semplice, caldo, parlato. Niente termini da manuale (niente “affricata”, “palatale”, “denasalizza”).\n"
			. "- Aiuta con un suono già noto della LINGUA B (“come in…”, “non come…”).\n"
			. "- Massimo due frasi per parola. Una riga vuota tra una parola e la successiva.\n"
			. "- La pronuncia tra parentesi deve essere facile da leggere per chi parla LINGUA B.\n"
			. "- Non tradurre il significato. Testo semplice, senza tag HTML: la formattazione la aggiunge lo script Formatta appunti.\n\n"
			. 'LINGUA B = ' . $lingua_b . '.';
	}

	/**
	 * Riempie le prime 3 frasi del David se il campo pronuncia è ancora vuoto.
	 */
	public static function maybe_seed_david_first3() {
		if ( '1' === (string) get_option( self::SEED_OPTION, '' ) ) {
			return;
		}
		if ( ! class_exists( 'LLM_Story_Repository' ) || ! post_type_exists( LLM_STORY_CPT ) ) {
			return;
		}

		$story_id = self::find_david_story_id();
		if ( ! $story_id ) {
			return;
		}

		$data = self::david_first3_texts();
		$ok   = 0;
		foreach ( $data as $index => $text ) {
			$row = LLM_Story_Repository::get_phrase_at( $story_id, $index );
			if ( ! is_array( $row ) ) {
				continue;
			}
			$current = isset( $row['pronunciation'] ) ? trim( wp_strip_all_tags( (string) $row['pronunciation'] ) ) : '';
			if ( '' !== $current ) {
				++$ok;
				continue;
			}
			if ( LLM_Story_Repository::update_phrase_rich_field( $story_id, $index, 'pronunciation', $text ) ) {
				++$ok;
			}
		}

		if ( $ok >= 3 ) {
			update_option( self::SEED_OPTION, '1', false );
		}
	}

	/**
	 * Riempie IPA e pronuncia approssimata delle prime 3 frasi del David.
	 */
	public static function maybe_seed_david_ipa_approx_first3() {
		if ( '1' === (string) get_option( self::SEED_IPA_OPTION, '' ) ) {
			return;
		}
		if ( ! class_exists( 'LLM_Story_Repository' ) || ! post_type_exists( LLM_STORY_CPT ) ) {
			return;
		}

		$story_id = self::find_david_story_id();
		if ( ! $story_id ) {
			return;
		}

		$data = self::david_first3_ipa_approx();
		$ok   = 0;
		foreach ( $data as $index => $pair ) {
			$ipa_ok    = LLM_Story_Repository::update_phrase_rich_field( $story_id, $index, 'ipa', $pair['ipa'] );
			$approx_ok = LLM_Story_Repository::update_phrase_rich_field( $story_id, $index, 'approx', $pair['approx'] );
			if ( $ipa_ok && $approx_ok ) {
				++$ok;
			}
		}

		if ( $ok >= 3 ) {
			update_option( self::SEED_IPA_OPTION, '1', false );
		}
	}

	/**
	 * Prime 3 frasi: una cella IPA e una approssimata, parola per parola.
	 *
	 * @return array<int, array{ipa:string,approx:string}>
	 */
	public static function david_first3_ipa_approx() {
		return array(
			0 => array(
				'ipa'    => '/ ðə / / ˈmɑː.bəl / / hæd / / bɪn / / rɪˈdʒek.tɪd / / baɪ / / tuː / / ˈskʌlp.təz /',
				'approx' => '( de ) ( MÀA-bol ) ( hèd ) ( bin ) ( ri-GÈK-tid ) ( bài ) ( TU ) ( SKÀLP-toz )',
			),
			1 => array(
				'ipa'    => '/ ˌmaɪ.kəlˈæn.dʒə.loʊ / / ˈstɑː.tɪd / / ˈwɜː.kɪŋ / / ɒn / / ɪt / / ɪn / / ˌfɪfˈtiːn əʊ ˈwʌn / / hiː / / wɒz / / ˌtwen.ti ˈsɪks / / jɪəz / / əʊld /',
				'approx' => '( mài-kel-ÀN-gio-lo ) ( STÀA-tid ) ( UÈR-king ) ( òn ) ( it ) ( in ) ( fif-TÌIN ou uàn ) ( hìi ) ( uòz ) ( TUÈN-ti siks ) ( iìez ) ( òuld )',
			),
			2 => array(
				'ipa'    => '/ ˈdeɪ.vɪd / / wɒz / / ə / / jʌŋ / / ˈʃep.əd / / frɒm / / ðə / / ˈbaɪ.bəl /',
				'approx' => '( DÈI-vid ) ( uòz ) ( e ) ( iàng ) ( SCÈ-ped ) ( fròm ) ( de ) ( BÀI-bol )',
			),
		);
	}

	/**
	 * @return int
	 */
	private static function find_david_story_id() {
		$q = new WP_Query(
			array(
				'post_type'              => LLM_STORY_CPT,
				'post_status'            => 'any',
				'title'                  => 'Il David di Michelangelo',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( ! empty( $q->posts ) ) {
			return (int) $q->posts[0];
		}

		$ids = get_posts(
			array(
				'post_type'      => LLM_STORY_CPT,
				'post_status'    => 'any',
				'posts_per_page' => 20,
				'fields'         => 'ids',
				's'              => 'David di Michelangelo',
			)
		);
		if ( ! empty( $ids ) ) {
			return (int) $ids[0];
		}
		if ( get_post( 3168 ) && LLM_STORY_CPT === get_post_type( 3168 ) ) {
			return 3168;
		}
		return 0;
	}

	/**
	 * Prime 3 frasi (indice 0-based) — prova.
	 *
	 * @return array<int,string>
	 */
	public static function david_first3_texts() {
		return array(
			0 => "The\n/ðə/  (de)\nLa \"th\" sonora non esiste in italiano: lingua tra i denti e un soffio, non una \"t\" dura.\n\nmarble\n/ˈmɑː.bəl/  (MÀA-bol)\nAccento sulla prima sillaba. La \"e\" finale è muta; non dire \"marblee\".\n\nhad\n/hæd/  (hèd)\nLa \"h\" si aspira. Vocale breve, come in \"cat\", non \"a\" italiana.\n\nbeen\n/bɪn/  (bin)\nNel parlato veloce è breve: /bɪn/, non \"biin\".\n\nrejected\n/rɪˈdʒek.tɪd/  (ri-GÈK-tid)\nAccento sulla seconda. La \"j\" è come la \"g\" di \"gioco\". Il \"-ed\" dopo \"t\" si legge \"-id\".\n\nby\n/baɪ/  (bài)\nDittongo come l'\"ai\" italiano. Non \"bi\".\n\ntwo\n/tuː/  (tùu)\nLa \"w\" non si sente. Vocale lunga.\n\nsculptors\n/ˈskʌlp.təz/  (SKÀLP-toz)\nLa \"c\" è muta: non \"scultor\". Accento sulla prima. La \"s\" finale suona \"z\".",
			1 => "Michelangelo\n/ˌmaɪ.kəlˈæn.dʒə.loʊ/  (mài-kel-ÀN-gio-lo)\nIn inglese l'accento cade su \"-an-\", non all'inizio. La \"ch\" è un suono \"k\".\n\nstarted\n/ˈstɑː.tɪd/  (STÀA-tid)\nDopo la \"t\" il \"-ed\" si legge \"-id\": tre sillabe, non \"startt\".\n\nworking\n/ˈwɜː.kɪŋ/  (UÈR-king)\nLa \"w\" è una \"u\" rapida. La \"r\" colora la vocale.\n\non\n/ɒn/  (òn)\nVocale breve e aperta. Non \"oun\".\n\nit\n/ɪt/  (it)\nLa \"i\" è breve, come in \"sit\", non \"iit\".\n\nin\n/ɪn/  (in)\nStessa \"i\" breve.\n\n1501\n/ˌfɪfˈtiːn əʊ ˈwʌn/  (fif-TÌIN ou uàn)\nSi dice \"fifteen oh one\": lo zero è \"oh\", non si legge cifra per cifra all'italiana.\n\nHe\n/hiː/  (hìi)\nLa \"h\" si aspira. Vocale lunga.\n\nwas\n/wɒz/  (uòz)\nLa \"s\" finale è \"z\". Non \"vas\".\n\ntwenty-six\n/ˌtwen.ti ˈsɪks/  (TUÈN-ti siks)\nUna sola parola composta. Accento su \"six\".\n\nyears\n/jɪəz/  (iìez)\nLa \"y\" è una \"i\" consonante. La \"s\" finale è \"z\".\n\nold\n/əʊld/  (òuld)\nDittongo \"ou\". La \"l\" si sente.",
			2 => "David\n/ˈdeɪ.vɪd/  (DÈI-vid)\nLa \"a\" è il dittongo \"ei\" di \"day\", non la \"a\" italiana di \"Davide\".\n\nwas\n/wɒz/  (uòz)\nLa \"s\" è \"z\". Vocale breve e aperta.\n\na\n/ə/  (e)\nSchwa debolissimo, quasi inghiottito. Non \"ei\".\n\nyoung\n/jʌŋ/  (iàng)\nLa \"y\" è \"i\" consonante; la \"ou\" è una \"a\" breve. La \"g\" è il nasale \"ng\", non una \"g\" dura.\n\nshepherd\n/ˈʃep.əd/  (SCÈ-ped)\nLa \"sh\" è come \"sc\" di \"scena\". Il gruppo \"ph\" è \"f\". Accento sulla prima.\n\nfrom\n/frɒm/  (fròm)\nVocale breve. Nel parlato veloce può indebolirsi.\n\nthe\n/ðə/  (de)\nDi nuovo la \"th\" sonora: lingua tra i denti.\n\nBible\n/ˈbaɪ.bəl/  (BÀI-bol)\nDittongo \"ai\". Nome proprio: la \"B\" è maiuscola. La \"e\" finale è muta.",
		);
	}
}
