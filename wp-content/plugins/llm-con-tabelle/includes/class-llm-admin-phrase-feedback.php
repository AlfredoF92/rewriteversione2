<?php
/**
 * Pagina impostazioni admin: Feedback Traduzione (Fase 1).
 *
 * Per ogni fascia percentuale di parole corrette definisce un pool di frasi
 * suddiviso in Parte 1 (messaggio motivazionale) e Parte 2 (introduzione agli appunti).
 * A runtime viene pescata una frase a caso da ciascun pool.
 *
 * Opzione salvata: llm_phrase_feedback
 * Struttura: array( 'it' => array( tier => array( 'p1' => string[], 'p2' => string[] ) ), ... )
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Admin_Phrase_Feedback {

	const PAGE_SLUG  = 'llm-phrase-feedback';
	const OPT_KEY    = 'llm_phrase_feedback';
	const NONCE_KEY  = 'llm_pf_settings_save';
	const ACTION_KEY = 'llm_pf_save';

	/** Fasce percentuali e casi speciali. */
	private static function tiers() {
		return array(
			'0'          => array(
				'label' => array(
					'it' => '0% — Nessuna parola giusta',
					'en' => '0% — No correct words',
					'pl' => '0% — Żadne słowo poprawne',
					'es' => '0% — Ninguna palabra correcta',
				),
				'desc' => array(
					'it' => 'Mostrato quando nessuna parola è corretta (primo click).',
					'en' => 'Shown when no word is correct (first click).',
					'pl' => 'Wyświetlane gdy żadne słowo nie jest poprawne (pierwsze kliknięcie).',
					'es' => 'Mostrado cuando ninguna palabra es correcta (primer clic).',
				),
			),
			'gt0'        => array(
				'label' => array(
					'it' => '>0% — Qualcosa di giusto',
					'en' => '>0% — Something correct',
					'pl' => '>0% — Coś poprawnego',
					'es' => '>0% — Algo correcto',
				),
				'desc' => array(
					'it' => 'Applicato quando le parole corrette sono tra 1% e 50%.',
					'en' => 'Applied when correct words are between 1% and 50%.',
					'pl' => 'Stosowane gdy poprawne słowa stanowią od 1% do 50%.',
					'es' => 'Aplicado cuando las palabras correctas están entre el 1% y el 50%.',
				),
			),
			'gt50'       => array(
				'label' => array(
					'it' => '>50% — A metà strada',
					'en' => '>50% — Halfway there',
					'pl' => '>50% — W połowie drogi',
					'es' => '>50% — A mitad de camino',
				),
				'desc' => array(
					'it' => 'Applicato quando le parole corrette sono tra 51% e 60%.',
					'en' => 'Applied when correct words are between 51% and 60%.',
					'pl' => 'Stosowane gdy poprawne słowa stanowią od 51% do 60%.',
					'es' => 'Aplicado cuando las palabras correctas están entre el 51% y el 60%.',
				),
			),
			'gt60lt90'   => array(
				'label' => array(
					'it' => '>60% <90% — Quasi perfetto',
					'en' => '>60% <90% — Almost perfect',
					'pl' => '>60% <90% — Prawie idealne',
					'es' => '>60% <90% — Casi perfecto',
				),
				'desc' => array(
					'it' => 'Applicato quando le parole corrette sono tra 61% e 89%.',
					'en' => 'Applied when correct words are between 61% and 89%.',
					'pl' => 'Stosowane gdy poprawne słowa stanowią od 61% do 89%.',
					'es' => 'Aplicado cuando las palabras correctas están entre el 61% y el 89%.',
				),
			),
			'100'        => array(
				'label' => array(
					'it' => '100% — Perfetto',
					'en' => '100% — Perfect',
					'pl' => '100% — Idealnie',
					'es' => '100% — Perfecto',
				),
				'desc' => array(
					'it' => 'Applicato quando tutte le parole sono corrette.',
					'en' => 'Applied when all words are correct.',
					'pl' => 'Stosowane gdy wszystkie słowa są poprawne.',
					'es' => 'Aplicado cuando todas las palabras son correctas.',
				),
			),
			'empty_input' => array(
				'label' => array(
					'it' => '⚠ Feedback Area di Testo Vuota',
					'en' => '⚠ Empty Text Area Feedback',
					'pl' => '⚠ Feedback — Puste pole tekstowe',
					'es' => '⚠ Feedback Área de Texto Vacía',
				),
				'desc' => array(
					'it' => 'Mostrato quando l\'utente clicca Continua senza aver scritto nulla.',
					'en' => 'Shown when the user clicks Continue without writing anything.',
					'pl' => 'Wyświetlane gdy użytkownik kliknie Dalej bez wpisania czegokolwiek.',
					'es' => 'Mostrado cuando el usuario hace clic en Continuar sin escribir nada.',
				),
			),
			'double_click' => array(
				'label' => array(
					'it' => '🔁 Feedback doppio click su Continua',
					'en' => '🔁 Double-click on Continue feedback',
					'pl' => '🔁 Feedback podwójnego kliknięcia Dalej',
					'es' => '🔁 Feedback doble clic en Continuar',
				),
				'desc' => array(
					'it' => 'Mostrato quando l\'utente clicca Continua una seconda volta con 0% parole corrette — procede comunque alla fase 2.',
					'en' => 'Shown when the user clicks Continue a second time with 0% correct words — proceeds to phase 2 anyway.',
					'pl' => 'Wyświetlane gdy użytkownik klika Dalej po raz drugi z 0% poprawnych słów — przechodzi do fazy 2.',
					'es' => 'Mostrado cuando el usuario hace clic en Continuar por segunda vez con 0% de palabras correctas — pasa a la fase 2 de todos modos.',
				),
			),
			'phase2_fail'  => array(
				'label' => array(
					'it' => '✏️ Errore Fase 2 — traduzione non corretta',
					'en' => '✏️ Phase 2 error — incorrect translation',
					'pl' => '✏️ Błąd Fazy 2 — niepoprawne tłumaczenie',
					'es' => '✏️ Error Fase 2 — traducción incorrecta',
				),
				'desc' => array(
					'it' => 'Mostrato quando la traduzione nella fase 2 non corrisponde. Testo fisso (non random).',
					'en' => 'Shown when the phase 2 translation does not match. Fixed text (not random).',
					'pl' => 'Wyświetlane gdy tłumaczenie w fazie 2 nie pasuje. Stały tekst (nie losowy).',
					'es' => 'Mostrado cuando la traducción de la fase 2 no coincide. Texto fijo (no aleatorio).',
				),
				'type' => 'fixed',
			),
		);
	}

	/** Frasi di default per tutte le lingue. */
	public static function defaults() {
		return array(
			'it' => array(
				'0'        => array(
					'p1' => array(
						'Nessuna parola giusta... prova ad ascoltare la frase con attenzione!',
						'Non ci siamo ancora — riascolta la frase e riprova!',
						'Hmm, nessuna parola esatta... proviamo ad ascoltare insieme!',
					),
					'p2' => array(
						'Ascolta bene la frase e prova a ripeterla prima di riscriverla.',
						'Prova ad ascoltare la frase e ripetila ad alta voce.',
						'Riascolta la frase con attenzione e poi riprova.',
					),
				),
				'gt0'      => array(
					'p1' => array(
						"Ok, bravo... c'è qualche parola esatta!",
						'Ottimo, alcune parole sono corrette!',
						'Ok bravo... ci sono alcune parole giuste!',
					),
					'p2' => array(
						'Vediamo gli appunti su questa frase per trovare le parole mancanti.',
						'Guardiamo insieme le note per completare le parole che mancano.',
						'Vediamo gli appunti per capire le parole ancora da trovare.',
					),
				),
				'gt50'     => array(
					'p1' => array(
						'Ok, sei a metà strada con la traduzione!',
						'Ottimo, bravo... metà delle parole sono esatte!',
						'Bravo, hai tradotto metà della frase correttamente!',
					),
					'p2' => array(
						'Vediamo le note di questa frase per completare la seconda metà.',
						'Guardiamo insieme gli appunti per trovare le ultime parole.',
						'Quasi a metà — vediamo le note per completare la traduzione.',
					),
				),
				'gt60lt90' => array(
					'p1' => array(
						'Ok, la maggior parte è corretta!',
						'Bene, quasi tutta la frase è giusta!',
						'Hai tradotto quasi tutto correttamente!',
					),
					'p2' => array(
						'Ok, vediamo le note di questa frase per completare la traduzione.',
						'Manca pochissimo — guardiamo gli appunti per rifinire la frase.',
						'Vediamo insieme i dettagli per correggere le ultime parole.',
					),
				),
				'100'         => array(
					'p1' => array(
						'Ok, la traduzione è perfettamente corretta!',
						'Wow, hai tradotto tutto esattamente!',
						'Perfetto, ogni parola è al suo posto!',
					),
					'p2' => array(
						'Ora vediamo gli appunti e prova a pronunciarla o a riscriverla per memorizzarla al meglio.',
						'Leggi gli appunti su questa frase e poi prova a riscriverla a memoria.',
						"Dai un'occhiata agli appunti e ripeti la frase ad alta voce per fissarla bene.",
					),
				),
				'empty_input'  => array(
					'p1' => array(
						"L'area di testo è vuota.",
					),
					'p2' => array(
						'Scrivi qualcosa prima di andare avanti — anche una sola parola!',
					),
				),
				'double_click' => array(
					'p1' => array(
						'Ok, prima di riscrivere la traduzione corretta',
					),
					'p2' => array(
						'prova ad aiutarti con queste note',
					),
				),
			'phase2_fail'  => array(
					'p1' => array(
						'La frase deve coincidere con la traduzione principale (ignorando punteggiatura e simboli). Riprova.',
					),
					'p2' => array(),
				),
			),
			'en' => array(
				'0'        => array(
					'p1' => array(
						'No correct words... try listening to the sentence carefully!',
						'Not there yet — listen again and try once more!',
						"Hmm, no exact words... let's listen together!",
					),
					'p2' => array(
						'Listen to the sentence carefully and try to repeat it before rewriting.',
						'Try listening to the sentence and repeat it out loud.',
						'Listen to the sentence again carefully, then try again.',
					),
				),
				'gt0'      => array(
					'p1' => array(
						'Good, there is at least one correct word!',
						'Great, some words are correct!',
						'Nice, there are a few right words!',
					),
					'p2' => array(
						"Let's look at the notes for this sentence to find the missing words.",
						"Let's check the notes together to complete the missing words.",
						"Let's look at the notes to understand the words still missing.",
					),
				),
				'gt50'     => array(
					'p1' => array(
						"You're halfway there with the translation!",
						'Great, half the words are correct!',
						"Well done, you've translated half the sentence correctly!",
					),
					'p2' => array(
						"Let's look at the notes for this sentence to complete the second half.",
						"Let's check the notes together to find the last words.",
						"Almost halfway — let's look at the notes to complete the translation.",
					),
				),
				'gt60lt90' => array(
					'p1' => array(
						'Most of it is correct!',
						'Great, almost the whole sentence is right!',
						"You've translated almost everything correctly!",
					),
					'p2' => array(
						"Let's look at the notes for this sentence to complete the translation.",
						"Just a bit more — let's check the notes to polish the sentence.",
						"Let's look at the details together to fix the last few words.",
					),
				),
				'100'         => array(
					'p1' => array(
						'The translation is perfectly correct!',
						"Wow, you've translated everything exactly!",
						'Perfect, every word is in the right place!',
					),
					'p2' => array(
						"Now let's look at the notes and try to say it or rewrite it to memorise it.",
						'Read the notes for this sentence and then try to rewrite it from memory.',
						'Take a look at the notes and repeat the sentence out loud to fix it in your memory.',
					),
				),
				'empty_input'  => array(
					'p1' => array(
						'The text area is empty.',
					),
					'p2' => array(
						'Write something before continuing — even just one word!',
					),
				),
				'double_click' => array(
					'p1' => array(
						'Ok, before rewriting the correct translation',
					),
					'p2' => array(
						'try using these notes to help you',
					),
				),
			'phase2_fail'  => array(
					'p1' => array(
						'The sentence must match the main translation (ignoring punctuation and symbols). Try again.',
					),
					'p2' => array(),
				),
			),
			'pl' => array(
				'0'        => array(
					'p1' => array(
						'Żadne słowo nie jest poprawne... spróbuj uważnie przesłuchać zdanie!',
						'Jeszcze nie — posłuchaj jeszcze raz i spróbuj ponownie!',
						'Hmm, żadne słowo nie jest dokładne... posłuchajmy razem!',
					),
					'p2' => array(
						'Posłuchaj uważnie zdania i spróbuj je powtórzyć przed przepisaniem.',
						'Posłuchaj zdania i powtórz je głośno.',
						'Posłuchaj zdania jeszcze raz uważnie, a potem spróbuj ponownie.',
					),
				),
				'gt0'      => array(
					'p1' => array(
						'Dobrze, jest przynajmniej jedno poprawne słowo!',
						'Świetnie, niektóre słowa są poprawne!',
						'Brawo, jest kilka dobrych słów!',
					),
					'p2' => array(
						'Sprawdźmy notatki do tego zdania, żeby znaleźć brakujące słowa.',
						'Przejrzyjmy razem notatki, żeby uzupełnić brakujące słowa.',
						'Zobaczmy notatki, żeby zrozumieć, których słów jeszcze brakuje.',
					),
				),
				'gt50'     => array(
					'p1' => array(
						'Jesteś w połowie drogi z tłumaczeniem!',
						'Świetnie, połowa słów jest poprawna!',
						'Brawo, przetłumaczyłeś połowę zdania poprawnie!',
					),
					'p2' => array(
						'Sprawdźmy notatki do tego zdania, żeby uzupełnić drugą połowę.',
						'Przejrzyjmy razem notatki, żeby znaleźć ostatnie słowa.',
						'Prawie w połowie — sprawdźmy notatki, żeby dokończyć tłumaczenie.',
					),
				),
				'gt60lt90' => array(
					'p1' => array(
						'Większość jest poprawna!',
						'Dobrze, prawie całe zdanie jest dobre!',
						'Przetłumaczyłeś prawie wszystko poprawnie!',
					),
					'p2' => array(
						'Sprawdźmy notatki do tego zdania, żeby ukończyć tłumaczenie.',
						'Brakuje tylko trochę — przejrzyjmy notatki, żeby dopracować zdanie.',
						'Przyjrzyjmy się szczegółom razem, żeby poprawić ostatnie słowa.',
					),
				),
				'100'         => array(
					'p1' => array(
						'Tłumaczenie jest idealnie poprawne!',
						'Wow, przetłumaczyłeś wszystko dokładnie!',
						'Idealnie, każde słowo jest na swoim miejscu!',
					),
					'p2' => array(
						'Teraz sprawdźmy notatki i spróbuj to wymówić lub przepisać, żeby lepiej zapamiętać.',
						'Przeczytaj notatki do tego zdania i spróbuj je przepisać z pamięci.',
						'Rzuć okiem na notatki i powtórz zdanie głośno, żeby je utrwalić.',
					),
				),
				'empty_input'  => array(
					'p1' => array(
						'Pole tekstowe jest puste.',
					),
					'p2' => array(
						'Napisz coś przed kontynuowaniem — choć jedno słowo!',
					),
				),
				'double_click' => array(
					'p1' => array(
						'Ok, zanim przepiszesz poprawne tłumaczenie',
					),
					'p2' => array(
						'spróbuj skorzystać z tych notatek',
					),
				),
			'phase2_fail'  => array(
					'p1' => array(
						'Zdanie musi być zgodne z głównym tłumaczeniem (ignorując interpunkcję i symbole). Spróbuj ponownie.',
					),
					'p2' => array(),
				),
			),
			'es' => array(
				'0'        => array(
					'p1' => array(
						'Ninguna palabra correcta... ¡intenta escuchar la frase con atención!',
						'Todavía no — ¡escucha de nuevo e inténtalo otra vez!',
						'Hmm, ninguna palabra exacta... ¡escuchemos juntos!',
					),
					'p2' => array(
						'Escucha bien la frase e intenta repetirla antes de reescribirla.',
						'Intenta escuchar la frase y repítela en voz alta.',
						'Vuelve a escuchar la frase con atención y luego inténtalo de nuevo.',
					),
				),
				'gt0'      => array(
					'p1' => array(
						'¡Bien, hay al menos una palabra correcta!',
						'¡Genial, algunas palabras son correctas!',
						'¡Bravo, hay algunas palabras acertadas!',
					),
					'p2' => array(
						'Veamos las notas de esta frase para encontrar las palabras que faltan.',
						'Revisemos juntos las notas para completar las palabras que faltan.',
						'Veamos las notas para entender qué palabras faltan aún.',
					),
				),
				'gt50'     => array(
					'p1' => array(
						'¡Estás a mitad de camino con la traducción!',
						'¡Genial, la mitad de las palabras son correctas!',
						'¡Bien hecho, has traducido la mitad de la frase correctamente!',
					),
					'p2' => array(
						'Veamos las notas de esta frase para completar la segunda mitad.',
						'Revisemos juntos las notas para encontrar las últimas palabras.',
						'Casi a la mitad — veamos las notas para completar la traducción.',
					),
				),
				'gt60lt90' => array(
					'p1' => array(
						'¡La mayor parte es correcta!',
						'¡Bien, casi toda la frase está bien!',
						'¡Has traducido casi todo correctamente!',
					),
					'p2' => array(
						'Veamos las notas de esta frase para completar la traducción.',
						'Falta muy poco — revisemos las notas para afinar la frase.',
						'Veamos juntos los detalles para corregir las últimas palabras.',
					),
				),
				'100'         => array(
					'p1' => array(
						'¡La traducción es perfectamente correcta!',
						'¡Wow, has traducido todo exactamente!',
						'¡Perfecto, cada palabra está en su lugar!',
					),
					'p2' => array(
						'Ahora veamos las notas e intenta pronunciarla o reescribirla para memorizarla mejor.',
						'Lee las notas de esta frase e intenta reescribirla de memoria.',
						'Echa un vistazo a las notas y repite la frase en voz alta para fijarla bien.',
					),
				),
				'empty_input'  => array(
					'p1' => array(
						'El área de texto está vacía.',
					),
					'p2' => array(
						'¡Escribe algo antes de continuar — aunque sea una sola palabra!',
					),
				),
				'double_click' => array(
					'p1' => array(
						'Ok, antes de reescribir la traducción correcta',
					),
					'p2' => array(
						'intenta ayudarte con estas notas',
					),
				),
			'phase2_fail'  => array(
					'p1' => array(
						'La frase debe coincidir con la traducción principal (ignorando puntuación y símbolos). Inténtalo de nuevo.',
					),
					'p2' => array(),
				),
			),
		);
	}

	/**
	 * Restituisce le frasi salvate per una lingua, con fallback ai default.
	 *
	 * @param string $lang Codice lingua (it|en|pl|es).
	 * @return array
	 */
	public static function get_for_lang( $lang ) {
		$lang     = sanitize_key( (string) $lang );
		$all      = (array) get_option( self::OPT_KEY, array() );
		$defaults = self::defaults();
		if ( isset( $all[ $lang ] ) && is_array( $all[ $lang ] ) ) {
			return $all[ $lang ];
		}
		return isset( $defaults[ $lang ] ) ? $defaults[ $lang ] : $defaults['it'];
	}

	/**
	 * Restituisce una frase casuale da un pool (Parte 1 o Parte 2) per una fascia e una lingua.
	 *
	 * @param string $tier  Chiave fascia (0|gt0|gt50|gt60lt90|100).
	 * @param string $part  Parte (p1|p2).
	 * @param string $lang  Codice lingua.
	 * @return string
	 */
	public static function random( $tier, $part, $lang ) {
		$data = self::get_for_lang( $lang );
		if ( ! isset( $data[ $tier ][ $part ] ) || ! is_array( $data[ $tier ][ $part ] ) || ! $data[ $tier ][ $part ] ) {
			return '';
		}
		$pool = array_filter( array_map( 'trim', $data[ $tier ][ $part ] ) );
		if ( ! $pool ) {
			return '';
		}
		$pool = array_values( $pool );
		return $pool[ array_rand( $pool ) ];
	}

	/**
	 * Restituisce il testo fisso (p1[0]) per un tier di tipo fixed.
	 *
	 * @param string $tier_key Chiave tier (es. 'phase2_fail').
	 * @param string $lang     Codice lingua.
	 * @return string
	 */
	public static function get_fixed_string( $tier_key, $lang ) {
		$data = self::get_for_lang( $lang );
		if ( isset( $data[ $tier_key ]['p1'][0] ) && '' !== trim( (string) $data[ $tier_key ]['p1'][0] ) ) {
			return trim( (string) $data[ $tier_key ]['p1'][0] );
		}
		$defaults = self::defaults();
		return isset( $defaults[ $lang ][ $tier_key ]['p1'][0] )
			? (string) $defaults[ $lang ][ $tier_key ]['p1'][0]
			: '';
	}

	/**
	 * Restituisce la chiave fascia corrispondente a una percentuale (0–100).
	 *
	 * @param int $pct Percentuale (0–100).
	 * @return string
	 */
	public static function tier_for_pct( $pct ) {
		$pct = max( 0, min( 100, (int) $pct ) );
		if ( 100 === $pct ) {
			return '100';
		}
		if ( $pct > 60 ) {
			return 'gt60lt90';
		}
		if ( $pct > 50 ) {
			return 'gt50';
		}
		if ( $pct > 0 ) {
			return 'gt0';
		}
		return '0';
	}

	// -------------------------------------------------------------------------
	// Boot
	// -------------------------------------------------------------------------

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_' . self::ACTION_KEY, array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . LLM_STORY_CPT,
			__( 'Feedback Traduzione', 'llm-con-tabelle' ),
			__( 'Feedback Traduzione', 'llm-con-tabelle' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	public static function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'llm-ui' );
	}

	// -------------------------------------------------------------------------
	// Salvataggio
	// -------------------------------------------------------------------------

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permesso negato.', 'llm-con-tabelle' ) );
		}
		check_admin_referer( self::NONCE_KEY );

		$lang       = sanitize_key( (string) ( $_POST['llm_pf_lang'] ?? 'it' ) );
		$valid_lang = array( 'it', 'en', 'pl', 'es' );
		if ( ! in_array( $lang, $valid_lang, true ) ) {
			$lang = 'it';
		}

		$tier_keys = array_keys( self::tiers() );
		$all       = (array) get_option( self::OPT_KEY, array() );
		$lang_data = array();

		foreach ( $tier_keys as $tier ) {
			foreach ( array( 'p1', 'p2' ) as $part ) {
				$field = sanitize_text_field( wp_unslash( $_POST[ 'llm_pf_' . $tier . '_' . $part ] ?? '' ) );
				$lines = array_filter(
					array_map( 'trim', explode( "\n", str_replace( "\r", '', $field ) ) )
				);
				$lang_data[ $tier ][ $part ] = array_values( $lines );
			}
		}

		$all[ $lang ] = $lang_data;
		update_option( self::OPT_KEY, $all, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => self::PAGE_SLUG,
					'lang'  => $lang,
					'saved' => '1',
				),
				admin_url( 'edit.php?post_type=' . LLM_STORY_CPT )
			)
		);
		exit;
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permesso negato.', 'llm-con-tabelle' ) );
		}

		$valid_lang  = array( 'it', 'en', 'pl', 'es' );
		$active_lang = sanitize_key( (string) ( $_GET['lang'] ?? 'it' ) );
		if ( ! in_array( $active_lang, $valid_lang, true ) ) {
			$active_lang = 'it';
		}

		$lang_names = array(
			'it' => 'Italiano',
			'en' => 'English',
			'pl' => 'Polski',
			'es' => 'Español',
		);

		$tiers     = self::tiers();
		$lang_data = self::get_for_lang( $active_lang );
		$page_base = admin_url( 'edit.php?post_type=' . LLM_STORY_CPT . '&page=' . self::PAGE_SLUG );
		$action_url = admin_url( 'admin-post.php' );

		echo '<div class="wrap">';
		echo '<h1 style="margin-bottom:4px;">' . esc_html__( 'Feedback Traduzione — Fase 1', 'llm-con-tabelle' ) . '</h1>';
		echo '<p class="description" style="margin:0 0 20px;">';
		echo esc_html__( 'Per ogni fascia di parole corrette, definisci il pool di frasi (una per riga). A runtime viene scelta una frase casuale da Parte 1 e una da Parte 2.', 'llm-con-tabelle' );
		echo '</p>';

		// Notice successo
		if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html__( 'Frasi salvate correttamente.', 'llm-con-tabelle' );
			echo '</p></div>';
		}

		// Tab lingua
		echo '<div style="display:flex;gap:4px;margin-bottom:24px;border-bottom:2px solid #c3c4c7;padding-bottom:0;">';
		foreach ( $lang_names as $code => $label ) {
			$is_active = ( $code === $active_lang );
			$tab_url   = add_query_arg( 'lang', $code, $page_base );
			$style     = $is_active
				? 'display:inline-block;padding:8px 18px;background:#fff;border:2px solid #c3c4c7;border-bottom:2px solid #fff;border-radius:4px 4px 0 0;font-weight:600;color:#1d2327;text-decoration:none;margin-bottom:-2px;'
				: 'display:inline-block;padding:8px 18px;background:#f0f0f1;border:2px solid transparent;border-radius:4px 4px 0 0;color:#50575e;text-decoration:none;margin-bottom:-2px;';
			echo '<a href="' . esc_url( $tab_url ) . '" style="' . esc_attr( $style ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</div>';

		// Form
		echo '<form method="post" action="' . esc_url( $action_url ) . '">';
		wp_nonce_field( self::NONCE_KEY );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_KEY ) . '" />';
		echo '<input type="hidden" name="llm_pf_lang" value="' . esc_attr( $active_lang ) . '" />';

		foreach ( $tiers as $tier_key => $tier_info ) {
			$tier_label = isset( $tier_info['label'][ $active_lang ] )
				? $tier_info['label'][ $active_lang ]
				: $tier_info['label']['it'];

			$p1_lines = isset( $lang_data[ $tier_key ]['p1'] ) && is_array( $lang_data[ $tier_key ]['p1'] )
				? $lang_data[ $tier_key ]['p1']
				: array();
			$p2_lines = isset( $lang_data[ $tier_key ]['p2'] ) && is_array( $lang_data[ $tier_key ]['p2'] )
				? $lang_data[ $tier_key ]['p2']
				: array();

			$p1_value = implode( "\n", $p1_lines );
			$p2_value = implode( "\n", $p2_lines );

			$field_p1 = 'llm_pf_' . $tier_key . '_p1';
			$field_p2 = 'llm_pf_' . $tier_key . '_p2';

			echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px 24px;margin-bottom:20px;">';

			// Intestazione fascia
			echo '<h2 style="font-size:1rem;font-weight:700;margin:0 0 4px;color:#1d2327;">' . esc_html( $tier_label ) . '</h2>';
			$tier_desc = isset( $tier_info['desc'][ $active_lang ] )
				? $tier_info['desc'][ $active_lang ]
				: ( isset( $tier_info['desc']['it'] ) ? $tier_info['desc']['it'] : '' );
			if ( '' !== $tier_desc ) {
				echo '<p style="font-size:0.8rem;color:#646970;margin:0 0 16px;">' . esc_html( $tier_desc ) . '</p>';
			}

			$is_fixed = isset( $tier_info['type'] ) && 'fixed' === $tier_info['type'];

			if ( $is_fixed ) {
				// Testo fisso: singola textarea full-width (solo p1)
				echo '<div>';
				echo '<label style="display:block;font-weight:600;font-size:0.85rem;margin-bottom:6px;color:#1d2327;" for="' . esc_attr( $field_p1 ) . '">';
				echo esc_html__( 'Testo del messaggio', 'llm-con-tabelle' );
				echo '</label>';
				echo '<p style="font-size:0.77rem;color:#646970;margin:0 0 8px;">' . esc_html__( 'Testo fisso — viene sempre mostrato questo testo (non random).', 'llm-con-tabelle' ) . '</p>';
				echo '<textarea'
					. ' id="' . esc_attr( $field_p1 ) . '"'
					. ' name="' . esc_attr( $field_p1 ) . '"'
					. ' rows="3"'
					. ' style="width:100%;font-family:inherit;font-size:0.875rem;line-height:1.6;resize:vertical;border:1px solid #8c8f94;border-radius:3px;padding:8px 10px;"'
					. '>';
				echo esc_textarea( $p1_value );
				echo '</textarea>';
				// Campo p2 vuoto nascosto per coerenza con il save handler
				echo '<input type="hidden" name="' . esc_attr( $field_p2 ) . '" value="" />';
				echo '</div>';
			} else {
				// Due colonne — pool random
				echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">';

				// Parte 1
				echo '<div>';
				echo '<label style="display:block;font-weight:600;font-size:0.85rem;margin-bottom:6px;color:#1d2327;" for="' . esc_attr( $field_p1 ) . '">';
				echo esc_html__( 'Parte 1 — Messaggio motivazionale', 'llm-con-tabelle' );
				echo '</label>';
				echo '<p style="font-size:0.77rem;color:#646970;margin:0 0 8px;">' . esc_html__( 'Una frase per riga. A runtime ne viene scelta una a caso.', 'llm-con-tabelle' ) . '</p>';
				echo '<textarea'
					. ' id="' . esc_attr( $field_p1 ) . '"'
					. ' name="' . esc_attr( $field_p1 ) . '"'
					. ' rows="5"'
					. ' style="width:100%;font-family:inherit;font-size:0.875rem;line-height:1.6;resize:vertical;border:1px solid #8c8f94;border-radius:3px;padding:8px 10px;"'
					. '>';
				echo esc_textarea( $p1_value );
				echo '</textarea>';
				echo '</div>';

				// Parte 2
				echo '<div>';
				echo '<label style="display:block;font-weight:600;font-size:0.85rem;margin-bottom:6px;color:#1d2327;" for="' . esc_attr( $field_p2 ) . '">';
				echo esc_html__( 'Parte 2 — Introduzione agli appunti', 'llm-con-tabelle' );
				echo '</label>';
				echo '<p style="font-size:0.77rem;color:#646970;margin:0 0 8px;">' . esc_html__( 'Una frase per riga. A runtime ne viene scelta una a caso.', 'llm-con-tabelle' ) . '</p>';
				echo '<textarea'
					. ' id="' . esc_attr( $field_p2 ) . '"'
					. ' name="' . esc_attr( $field_p2 ) . '"'
					. ' rows="5"'
					. ' style="width:100%;font-family:inherit;font-size:0.875rem;line-height:1.6;resize:vertical;border:1px solid #8c8f94;border-radius:3px;padding:8px 10px;"'
					. '>';
				echo esc_textarea( $p2_value );
				echo '</textarea>';
				echo '</div>';

				echo '</div>'; // fine grid
			}
			echo '</div>'; // fine card
		}

		echo '<p style="margin-top:8px;">';
		echo '<button type="submit" class="button button-primary" style="font-size:0.9rem;padding:6px 18px;">';
		echo esc_html__( 'Salva frasi', 'llm-con-tabelle' );
		echo '</button>';
		echo '<span style="margin-left:12px;font-size:0.8rem;color:#646970;">';
		printf(
			/* translators: %s = nome lingua attiva */
			esc_html__( 'Stai modificando: %s', 'llm-con-tabelle' ),
			'<strong>' . esc_html( $lang_names[ $active_lang ] ) . '</strong>'
		);
		echo '</span>';
		echo '</p>';

		echo '</form>';
		echo '</div>'; // fine wrap
	}
}
