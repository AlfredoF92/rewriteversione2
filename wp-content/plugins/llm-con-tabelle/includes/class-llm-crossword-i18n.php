<?php
/**
 * Testi UI del cruciverba, nella lingua interfaccia del visitatore.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Crossword_I18n {

	/**
	 * Lingua UI del visitatore (utente loggato o guest).
	 *
	 * @return string
	 */
	public static function lang() {
		return LLM_Visitor_Lang::known();
	}

	/**
	 * @param string $key  Chiave testo.
	 * @param string $lang Codice lingua opzionale.
	 * @return string
	 */
	public static function get( $key, $lang = '' ) {
		$lang = sanitize_key( (string) $lang );
		if ( '' === $lang ) {
			$lang = self::lang();
		}
		$all = self::bundles();
		if ( isset( $all[ $lang ][ $key ] ) ) {
			return (string) $all[ $lang ][ $key ];
		}
		return isset( $all['it'][ $key ] ) ? (string) $all['it'][ $key ] : '';
	}

	/**
	 * Bundle completo da passare al JavaScript.
	 *
	 * @param string $lang Codice lingua opzionale.
	 * @return array<string,string>
	 */
	public static function bundle( $lang = '' ) {
		$lang = sanitize_key( (string) $lang );
		if ( '' === $lang ) {
			$lang = self::lang();
		}
		$all = self::bundles();
		$out = $all['it'];
		if ( isset( $all[ $lang ] ) ) {
			$out = array_merge( $out, $all[ $lang ] );
		}
		return $out;
	}

	/**
	 * @return array<string,array<string,string>>
	 */
	private static function bundles() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		$cache = array(
			'it' => array(
				'across'           => 'Orizzontali',
				'down'             => 'Verticali',
				'reveal_letter'    => 'Rivela lettera',
				'check'            => 'Controlla',
				'restart'          => 'Ricomincia',
				'letters_count'    => '(%d lettere)',
				'start_hint'       => 'Buon divertimento! Clicca una casella per iniziare.',
				'mobile_clue_empty'=> 'Tocca una casella per vedere la definizione.',
				'resumed'          => 'Ripresa la partita salvata nel tuo browser.',
				'reveal_no_cell'   => 'Seleziona prima una casella nella griglia.',
				'reveal_no_answer' => 'Questa casella non ha una soluzione salvata.',
				'revealed'         => 'Rivelata la lettera "%s".',
				'solved'           => 'Perfetto! Hai risolto tutto: %1$d lettere e %2$d parole corrette.',
				'check_progress'   => 'Corrette: %1$d/%2$d - Sbagliate: %3$d - Da completare: %4$d - Parole complete: %5$d/%6$d',
				'restart_confirm'  => 'Vuoi cancellare tutte le lettere inserite?',
				'cleared'          => 'Griglia pulita, ricomincia pure.',
				'error'            => 'Cruciverba non disponibile.',
			),
			'en' => array(
				'across'           => 'Across',
				'down'             => 'Down',
				'reveal_letter'    => 'Reveal letter',
				'check'            => 'Check',
				'restart'          => 'Restart',
				'letters_count'    => '(%d letters)',
				'start_hint'       => 'Have fun! Click a square to start.',
				'mobile_clue_empty'=> 'Tap a square to see the clue.',
				'resumed'          => 'Resumed the game saved in your browser.',
				'reveal_no_cell'   => 'Select a square in the grid first.',
				'reveal_no_answer' => 'This square has no saved solution.',
				'revealed'         => 'Revealed the letter "%s".',
				'solved'           => 'Perfect! You solved everything: %1$d letters and %2$d correct words.',
				'check_progress'   => 'Correct: %1$d/%2$d - Wrong: %3$d - To complete: %4$d - Complete words: %5$d/%6$d',
				'restart_confirm'  => 'Do you want to clear all the letters you entered?',
				'cleared'          => 'Grid cleared, start again whenever you like.',
				'error'            => 'Crossword not available.',
			),
			'pl' => array(
				'across'           => 'Poziomo',
				'down'             => 'Pionowo',
				'reveal_letter'    => 'Odkryj litere',
				'check'            => 'Sprawdz',
				'restart'          => 'Zacznij od nowa',
				'letters_count'    => '(%d liter)',
				'start_hint'       => 'Milej zabawy! Kliknij pole, aby zaczac.',
				'mobile_clue_empty'=> 'Dotknij pola, aby zobaczyc definicje.',
				'resumed'          => 'Wznowiono gre zapisana w przegladarce.',
				'reveal_no_cell'   => 'Najpierw wybierz pole w siatce.',
				'reveal_no_answer' => 'To pole nie ma zapisanego rozwiazania.',
				'revealed'         => 'Odkryto litere "%s".',
				'solved'           => 'Doskonale! Rozwiazales wszystko: %1$d liter i %2$d poprawnych slow.',
				'check_progress'   => 'Poprawne: %1$d/%2$d - Bledne: %3$d - Do uzupelnienia: %4$d - Pelne slowa: %5$d/%6$d',
				'restart_confirm'  => 'Czy chcesz usunac wszystkie wpisane litery?',
				'cleared'          => 'Siatka wyczyszczona, mozesz zaczac od nowa.',
				'error'            => 'Krzyzowka niedostepna.',
			),
			'es' => array(
				'across'           => 'Horizontales',
				'down'             => 'Verticales',
				'reveal_letter'    => 'Revelar letra',
				'check'            => 'Comprobar',
				'restart'          => 'Reiniciar',
				'letters_count'    => '(%d letras)',
				'start_hint'       => 'Que te diviertas! Haz clic en una casilla para empezar.',
				'mobile_clue_empty'=> 'Toca una casilla para ver la definicion.',
				'resumed'          => 'Retomada la partida guardada en tu navegador.',
				'reveal_no_cell'   => 'Selecciona primero una casilla en la cuadricula.',
				'reveal_no_answer' => 'Esta casilla no tiene una solucion guardada.',
				'revealed'         => 'Revelada la letra "%s".',
				'solved'           => 'Perfecto! Lo has resuelto todo: %1$d letras y %2$d palabras correctas.',
				'check_progress'   => 'Correctas: %1$d/%2$d - Incorrectas: %3$d - Por completar: %4$d - Palabras completas: %5$d/%6$d',
				'restart_confirm'  => 'Quieres borrar todas las letras que has escrito?',
				'cleared'          => 'Cuadricula vaciada, puedes empezar de nuevo.',
				'error'            => 'Crucigrama no disponible.',
			),
		);

		return $cache;
	}
}
