<?php
/**
 * Modello cruciverba: CPT, parsing schema CSV, numerazione parole, definizioni.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Crossword {

	const CPT       = 'llm_crossword';
	const META_CSV  = '_llm_crossword_csv';
	const META_DEFS = '_llm_crossword_defs';
	const META_LANG = '_llm_crossword_lang';

	/** Lato massimo della griglia (righe o colonne). */
	const MAX_SIDE = 40;

	/** Separatore dei campi nella textarea delle definizioni. */
	const DEF_SEPARATOR = '|';

	/** Intestazioni sezione nel file unificato. */
	const BUNDLE_SECTIONS = array( 'LINGUA', 'SCHEMA', 'DEFINIZIONI' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		$labels = array(
			'name'               => _x( 'Cruciverba', 'post type general name', 'llm-con-tabelle' ),
			'singular_name'      => _x( 'Cruciverba', 'post type singular name', 'llm-con-tabelle' ),
			'menu_name'          => _x( 'Cruciverba', 'admin menu', 'llm-con-tabelle' ),
			'add_new'            => _x( 'Aggiungi nuovo', 'cruciverba', 'llm-con-tabelle' ),
			'add_new_item'       => __( 'Aggiungi nuovo cruciverba', 'llm-con-tabelle' ),
			'new_item'           => __( 'Nuovo cruciverba', 'llm-con-tabelle' ),
			'edit_item'          => __( 'Modifica cruciverba', 'llm-con-tabelle' ),
			'view_item'          => __( 'Vedi cruciverba', 'llm-con-tabelle' ),
			'all_items'          => __( 'Cruciverba', 'llm-con-tabelle' ),
			'search_items'       => __( 'Cerca cruciverba', 'llm-con-tabelle' ),
			'not_found'          => __( 'Nessun cruciverba trovato.', 'llm-con-tabelle' ),
			'not_found_in_trash' => __( 'Nessun cruciverba nel cestino.', 'llm-con-tabelle' ),
		);

		register_post_type(
			self::CPT,
			array(
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => 'edit.php?post_type=' . LLM_STORY_CPT,
				'query_var'          => false,
				'rewrite'            => false,
				'capability_type'    => 'post',
				'has_archive'        => false,
				'hierarchical'       => false,
				'supports'           => array( 'title' ),
				'show_in_rest'       => false,
			)
		);
	}

	/**
	 * @param int $post_id ID cruciverba.
	 * @return string CSV grezzo dello schema.
	 */
	public static function get_csv( $post_id ) {
		return (string) get_post_meta( absint( $post_id ), self::META_CSV, true );
	}

	/**
	 * @param int $post_id ID cruciverba.
	 * @return string Testo grezzo delle definizioni.
	 */
	public static function get_defs( $post_id ) {
		return (string) get_post_meta( absint( $post_id ), self::META_DEFS, true );
	}

	/**
	 * Riga lingua (es. "Italiano → Polacco").
	 *
	 * @param int $post_id ID cruciverba.
	 * @return string
	 */
	public static function get_lang( $post_id ) {
		return (string) get_post_meta( absint( $post_id ), self::META_LANG, true );
	}

	/**
	 * Compone il testo unificato per l'admin.
	 *
	 * @param string $lang Lingua.
	 * @param string $csv  Schema.
	 * @param string $defs Definizioni.
	 * @return string
	 */
	public static function format_bundle( $lang = '', $csv = '', $defs = '' ) {
		$lang = trim( (string) $lang );
		$csv  = trim( (string) $csv );
		$defs = trim( (string) $defs );

		$parts = array(
			'###### LINGUA',
			'' !== $lang ? $lang : 'Italiano → Inglese',
			'',
			'###### SCHEMA',
			'' !== $csv ? $csv : '',
			'',
			'###### DEFINIZIONI',
		);
		if ( '' !== $defs ) {
			$parts[] = $defs;
		} else {
			$parts[] = '# numero|O=orizzontale V=verticale|PAROLA|Categoria|Definizione lingua target|Definizione lingua nota';
		}
		return implode( "\n", $parts ) . "\n";
	}

	/**
	 * Legge il file unificato (LINGUA / SCHEMA / DEFINIZIONI).
	 *
	 * @param string $raw Testo completo.
	 * @return array{lang:string,csv:string,defs:string}|WP_Error
	 */
	public static function parse_bundle( $raw ) {
		$raw = is_string( $raw ) ? str_replace( array( "\r\n", "\r" ), "\n", $raw ) : '';
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return new WP_Error( 'llm_cw_bundle_empty', __( 'Incolla il file del cruciverba (LINGUA, SCHEMA, DEFINIZIONI).', 'llm-con-tabelle' ) );
		}

		// Formato unificato con sezioni ###### NOME.
		if ( preg_match( '/^#{2,}\s*(LINGUA|SCHEMA|DEFINIZIONI)\b/mi', $raw ) ) {
			$sections = array(
				'LINGUA'       => '',
				'SCHEMA'       => '',
				'DEFINIZIONI'  => '',
			);
			$current = '';
			foreach ( explode( "\n", $raw ) as $line ) {
				if ( preg_match( '/^#{2,}\s*(LINGUA|SCHEMA|DEFINIZIONI)\b/i', $line, $m ) ) {
					$current = strtoupper( $m[1] );
					continue;
				}
				if ( '' === $current || ! isset( $sections[ $current ] ) ) {
					continue;
				}
				$sections[ $current ] .= ( '' === $sections[ $current ] ? '' : "\n" ) . $line;
			}

			$lang = trim( $sections['LINGUA'] );
			$csv  = trim( $sections['SCHEMA'] );
			$defs = trim( $sections['DEFINIZIONI'] );

			// Togli eventuali righe vuote iniziali/finali già gestite da trim.
			if ( '' === $csv ) {
				return new WP_Error( 'llm_cw_bundle_no_schema', __( 'Manca la sezione ###### SCHEMA.', 'llm-con-tabelle' ) );
			}

			return array(
				'lang' => $lang,
				'csv'  => $csv,
				'defs' => $defs,
			);
		}

		// Retrocompatibilità: solo schema CSV senza sezioni.
		return array(
			'lang' => '',
			'csv'  => $raw,
			'defs' => '',
		);
	}

	/**
	 * Testo unificato salvato / ricostruito per un cruciverba.
	 *
	 * @param int $post_id ID cruciverba.
	 * @return string
	 */
	public static function get_bundle( $post_id ) {
		return self::format_bundle(
			self::get_lang( $post_id ),
			self::get_csv( $post_id ),
			self::get_defs( $post_id )
		);
	}

	/**
	 * Normalizza una cella CSV: una lettera A-Z oppure `#` per la casella nera.
	 *
	 * @param string $cell Contenuto cella.
	 * @return string Un solo carattere.
	 */
	private static function normalize_cell( $cell ) {
		$clean = preg_replace( '/[^A-Za-z]/', '', (string) $cell );
		if ( '' === $clean ) {
			return '#';
		}
		return strtoupper( substr( $clean, 0, 1 ) );
	}

	/**
	 * Converte il CSV in una griglia di righe: ogni carattere è una lettera o `#`.
	 *
	 * @param string $raw Testo CSV (righe a capo, celle separate da virgola).
	 * @return array{rows:int,cols:int,grid:string[]}|WP_Error
	 */
	public static function parse_grid( $raw ) {
		$raw   = is_string( $raw ) ? str_replace( array( "\r\n", "\r" ), "\n", $raw ) : '';
		$lines = array();
		foreach ( explode( "\n", $raw ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$lines[] = $line;
			}
		}

		if ( empty( $lines ) ) {
			return new WP_Error( 'llm_cw_empty', __( 'Lo schema è vuoto: incolla il CSV della griglia.', 'llm-con-tabelle' ) );
		}
		if ( count( $lines ) > self::MAX_SIDE ) {
			return new WP_Error(
				'llm_cw_too_many_rows',
				sprintf(
					/* translators: %d: maximum number of rows */
					__( 'Troppe righe: il massimo è %d.', 'llm-con-tabelle' ),
					self::MAX_SIDE
				)
			);
		}

		$grid = array();
		$cols = null;

		foreach ( $lines as $i => $line ) {
			$cells = explode( ',', $line );
			if ( null === $cols ) {
				$cols = count( $cells );
				if ( $cols < 2 || $cols > self::MAX_SIDE ) {
					return new WP_Error(
						'llm_cw_bad_cols',
						sprintf(
							/* translators: %d: maximum number of columns */
							__( 'Numero di colonne non valido: servono da 2 a %d colonne.', 'llm-con-tabelle' ),
							self::MAX_SIDE
						)
					);
				}
			} elseif ( count( $cells ) !== $cols ) {
				return new WP_Error(
					'llm_cw_ragged',
					sprintf(
						/* translators: 1: row number, 2: columns found, 3: columns expected */
						__( 'Riga %1$d: ha %2$d colonne invece di %3$d. Tutte le righe devono avere lo stesso numero di celle.', 'llm-con-tabelle' ),
						$i + 1,
						count( $cells ),
						$cols
					)
				);
			}

			$row = '';
			foreach ( $cells as $cell ) {
				$row .= self::normalize_cell( $cell );
			}
			$grid[] = $row;
		}

		if ( '' === str_replace( '#', '', implode( '', $grid ) ) ) {
			return new WP_Error( 'llm_cw_no_letters', __( 'Lo schema non contiene nessuna lettera: sono tutte caselle nere.', 'llm-con-tabelle' ) );
		}

		return array(
			'rows' => count( $grid ),
			'cols' => (int) $cols,
			'grid' => $grid,
		);
	}

	/**
	 * Numerazione standard: una casella prende un numero se inizia una parola
	 * orizzontale o verticale (di almeno due lettere).
	 *
	 * @param string[] $grid Righe della griglia.
	 * @return array<int,array{number:int,direction:string,row:int,col:int,word:string,length:int}>
	 */
	public static function entries( array $grid ) {
		$rows = count( $grid );
		$cols = $rows > 0 ? strlen( $grid[0] ) : 0;

		$is_black = static function ( $r, $c ) use ( $grid, $rows, $cols ) {
			if ( $r < 0 || $r >= $rows || $c < 0 || $c >= $cols ) {
				return true;
			}
			return '#' === $grid[ $r ][ $c ];
		};

		$entries = array();
		$counter = 0;

		for ( $r = 0; $r < $rows; $r++ ) {
			for ( $c = 0; $c < $cols; $c++ ) {
				if ( $is_black( $r, $c ) ) {
					continue;
				}
				$starts_across = $is_black( $r, $c - 1 ) && ! $is_black( $r, $c + 1 );
				$starts_down   = $is_black( $r - 1, $c ) && ! $is_black( $r + 1, $c );
				if ( ! $starts_across && ! $starts_down ) {
					continue;
				}

				++$counter;

				if ( $starts_across ) {
					$word = '';
					for ( $cc = $c; ! $is_black( $r, $cc ); $cc++ ) {
						$word .= $grid[ $r ][ $cc ];
					}
					$entries[] = array(
						'number'    => $counter,
						'direction' => 'across',
						'row'       => $r,
						'col'       => $c,
						'word'      => $word,
						'length'    => strlen( $word ),
					);
				}

				if ( $starts_down ) {
					$word = '';
					for ( $rr = $r; ! $is_black( $rr, $c ); $rr++ ) {
						$word .= $grid[ $rr ][ $c ];
					}
					$entries[] = array(
						'number'    => $counter,
						'direction' => 'down',
						'row'       => $r,
						'col'       => $c,
						'word'      => $word,
						'length'    => strlen( $word ),
					);
				}
			}
		}

		usort(
			$entries,
			static function ( $a, $b ) {
				if ( $a['number'] !== $b['number'] ) {
					return $a['number'] < $b['number'] ? -1 : 1;
				}
				return strcmp( $a['direction'], $b['direction'] );
			}
		);

		return $entries;
	}

	/**
	 * @param string $raw Direzione scritta dall'utente.
	 * @return string 'across', 'down' oppure '' se non riconosciuta.
	 */
	private static function normalize_direction( $raw ) {
		$d = strtolower( trim( (string) $raw ) );
		if ( function_exists( 'remove_accents' ) ) {
			$d = remove_accents( $d );
		}
		$across = array( 'o', 'or', 'oriz', 'orizzontale', 'orizzontali', 'a', 'across', 'h', 'horizontal', 'poziomo' );
		$down   = array( 'v', 'ver', 'vert', 'verticale', 'verticali', 'd', 'down', 'vertical', 'pionowo' );
		if ( in_array( $d, $across, true ) ) {
			return 'across';
		}
		if ( in_array( $d, $down, true ) ) {
			return 'down';
		}
		return '';
	}

	/**
	 * @param string $direction 'across' o 'down'.
	 * @return string Lettera usata nella textarea admin.
	 */
	public static function direction_letter( $direction ) {
		return 'down' === $direction ? 'V' : 'O';
	}

	/**
	 * @param int    $number    Numero parola.
	 * @param string $direction 'across' o 'down'.
	 * @return string Chiave della mappa definizioni.
	 */
	public static function clue_key( $number, $direction ) {
		return absint( $number ) . '-' . ( 'down' === $direction ? 'down' : 'across' );
	}

	/**
	 * Legge la textarea definizioni: `numero|direzione|parola|categoria|EN|IT`.
	 *
	 * I campi in coda possono essere omessi; la parola serve solo come promemoria
	 * e viene usata per capire quali campi hai scritto quando ne mancano.
	 *
	 * @param string $raw     Testo definizioni.
	 * @param array  $entries Parole ricavate dalla griglia.
	 * @return array{clues:array<string,array{pos:string,en:string,it:string}>,warnings:string[]}
	 */
	public static function parse_definitions( $raw, array $entries ) {
		$by_key = array();
		foreach ( $entries as $entry ) {
			$by_key[ self::clue_key( $entry['number'], $entry['direction'] ) ] = $entry;
		}

		$clues    = array();
		$warnings = array();
		$raw      = is_string( $raw ) ? str_replace( array( "\r\n", "\r" ), "\n", $raw ) : '';

		foreach ( explode( "\n", $raw ) as $i => $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}

			$parts = array_map( 'trim', explode( self::DEF_SEPARATOR, $line ) );
			if ( count( $parts ) < 2 ) {
				$warnings[] = sprintf(
					/* translators: %d: line number */
					__( 'Riga %d delle definizioni ignorata: manca il separatore "|".', 'llm-con-tabelle' ),
					$i + 1
				);
				continue;
			}

			$number    = absint( $parts[0] );
			$direction = self::normalize_direction( $parts[1] );
			if ( ! $number || '' === $direction ) {
				$warnings[] = sprintf(
					/* translators: %d: line number */
					__( 'Riga %d delle definizioni ignorata: numero o direzione non validi (usa O per orizzontale, V per verticale).', 'llm-con-tabelle' ),
					$i + 1
				);
				continue;
			}

			$key = self::clue_key( $number, $direction );
			if ( ! isset( $by_key[ $key ] ) ) {
				$warnings[] = sprintf(
					/* translators: 1: line number, 2: clue number, 3: direction letter */
					__( 'Riga %1$d: nello schema non esiste la parola %2$d %3$s.', 'llm-con-tabelle' ),
					$i + 1,
					$number,
					self::direction_letter( $direction )
				);
				continue;
			}

			$rest = array_slice( $parts, 2 );
			$word = strtoupper( $by_key[ $key ]['word'] );
			$pos  = '';
			$en   = '';
			$it   = '';

			$first_is_word = isset( $rest[0] ) && strtoupper( $rest[0] ) === $word;

			switch ( count( $rest ) ) {
				case 0:
					break;
				case 1:
					$it = $rest[0];
					break;
				case 2:
					if ( $first_is_word ) {
						$it = $rest[1];
					} else {
						$en = $rest[0];
						$it = $rest[1];
					}
					break;
				case 3:
					if ( $first_is_word ) {
						$en = $rest[1];
						$it = $rest[2];
					} else {
						$pos = $rest[0];
						$en  = $rest[1];
						$it  = $rest[2];
					}
					break;
				default:
					$pos = $rest[1];
					$en  = $rest[2];
					$it  = $rest[3];
					break;
			}

			if ( '' === $pos && '' === $en && '' === $it ) {
				continue;
			}

			if ( isset( $clues[ $key ] ) ) {
				$warnings[] = sprintf(
					/* translators: 1: clue number, 2: direction letter */
					__( 'La parola %1$d %2$s ha piu di una definizione: viene usata l’ultima riga.', 'llm-con-tabelle' ),
					$number,
					self::direction_letter( $direction )
				);
			}

			$clues[ $key ] = array(
				'pos' => $pos,
				'en'  => $en,
				'it'  => $it,
			);
		}

		return array(
			'clues'    => $clues,
			'warnings' => $warnings,
		);
	}

	/**
	 * Crea (o aggiorna) l'elenco definizioni a partire dallo schema, mantenendo
	 * i testi già scritti.
	 *
	 * @param array  $entries      Parole ricavate dalla griglia.
	 * @param string $existing_raw Testo definizioni attuale.
	 * @return string
	 */
	public static function definitions_skeleton( array $entries, $existing_raw = '' ) {
		$parsed = self::parse_definitions( $existing_raw, $entries );
		$clues  = $parsed['clues'];

		$lines = array(
			'# numero|direzione|parola|categoria|definizione inglese|definizione italiana',
			'# direzione: O = orizzontale, V = verticale. I campi che non ti servono lasciali vuoti.',
		);

		foreach ( $entries as $entry ) {
			$key  = self::clue_key( $entry['number'], $entry['direction'] );
			$clue = isset( $clues[ $key ] ) ? $clues[ $key ] : array(
				'pos' => '',
				'en'  => '',
				'it'  => '',
			);

			$lines[] = implode(
				self::DEF_SEPARATOR,
				array(
					(string) $entry['number'],
					self::direction_letter( $entry['direction'] ),
					$entry['word'],
					$clue['pos'],
					$clue['en'],
					$clue['it'],
				)
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * Il formato interno usa "|": toglie eventuali barre dalle definizioni CSV.
	 *
	 * @param string $value Campo testo.
	 * @return string
	 */
	private static function sanitize_def_field( $value ) {
		$value = str_replace( array( "\r", "\n" ), ' ', (string) $value );
		$value = str_replace( '|', '/', $value );
		return trim( $value );
	}

	/**
	 * Legge un CSV di definizioni con virgole e campi tra virgolette.
	 * Formato: numero,direzione,parola,categoria,definizione_en,definizione_it
	 *
	 * @param string $raw CSV grezzo.
	 * @return array{clues:array<string,array{pos:string,en:string,it:string,word:string,number:int,direction:string}>,warnings:string[]}|WP_Error
	 */
	public static function parse_definitions_csv( $raw ) {
		$raw = is_string( $raw ) ? str_replace( array( "\r\n", "\r" ), "\n", $raw ) : '';
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return new WP_Error( 'llm_cw_defs_csv_empty', __( 'Incolla prima il CSV delle definizioni.', 'llm-con-tabelle' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$h = fopen( 'php://memory', 'rb+' );
		if ( ! $h ) {
			return new WP_Error( 'llm_cw_defs_csv_io', __( 'Impossibile leggere il CSV.', 'llm-con-tabelle' ) );
		}
		fwrite( $h, $raw );
		rewind( $h );

		$clues    = array();
		$warnings = array();
		$row_num  = 0;
		$header   = true;

		while ( ( $cells = fgetcsv( $h, 0, ',', '"' ) ) !== false ) {
			++$row_num;
			if ( ! is_array( $cells ) ) {
				continue;
			}
			$cells = array_map(
				static function ( $c ) {
					return is_string( $c ) ? trim( $c ) : '';
				},
				$cells
			);

			$non_empty = array_filter( $cells, static function ( $c ) {
				return '' !== $c;
			} );
			if ( empty( $non_empty ) ) {
				continue;
			}

			if ( $header ) {
				$header = false;
				$first  = strtolower( isset( $cells[0] ) ? $cells[0] : '' );
				if ( function_exists( 'remove_accents' ) ) {
					$first = remove_accents( $first );
				}
				if ( in_array( $first, array( 'numero', 'number', 'n', 'num' ), true ) ) {
					continue;
				}
			}

			if ( count( $cells ) < 2 ) {
				$warnings[] = sprintf(
					/* translators: %d: CSV row number */
					__( 'Riga CSV %d ignorata: troppi pochi campi.', 'llm-con-tabelle' ),
					$row_num
				);
				continue;
			}

			$number    = absint( $cells[0] );
			$direction = self::normalize_direction( isset( $cells[1] ) ? $cells[1] : '' );
			if ( ! $number || '' === $direction ) {
				$warnings[] = sprintf(
					/* translators: %d: CSV row number */
					__( 'Riga CSV %d ignorata: numero o direzione non validi (usa O / V).', 'llm-con-tabelle' ),
					$row_num
				);
				continue;
			}

			$word = isset( $cells[2] ) ? strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $cells[2] ) ) : '';
			$pos  = self::sanitize_def_field( isset( $cells[3] ) ? $cells[3] : '' );
			$en   = self::sanitize_def_field( isset( $cells[4] ) ? $cells[4] : '' );
			$it   = self::sanitize_def_field( isset( $cells[5] ) ? $cells[5] : '' );

			if ( '' === $pos && '' === $en && '' === $it ) {
				$warnings[] = sprintf(
					/* translators: %d: CSV row number */
					__( 'Riga CSV %d ignorata: manca il testo della definizione.', 'llm-con-tabelle' ),
					$row_num
				);
				continue;
			}

			$key = self::clue_key( $number, $direction );
			if ( isset( $clues[ $key ] ) ) {
				$warnings[] = sprintf(
					/* translators: 1: clue number, 2: direction letter */
					__( 'La parola %1$d %2$s compare più volte nel CSV: viene usata l’ultima riga.', 'llm-con-tabelle' ),
					$number,
					self::direction_letter( $direction )
				);
			}

			$clues[ $key ] = array(
				'number'    => $number,
				'direction' => $direction,
				'word'      => $word,
				'pos'       => $pos,
				'en'        => $en,
				'it'        => $it,
			);
		}
		fclose( $h );

		if ( empty( $clues ) ) {
			return new WP_Error( 'llm_cw_defs_csv_nodata', __( 'Nessuna definizione valida trovata nel CSV.', 'llm-con-tabelle' ) );
		}

		return array(
			'clues'    => $clues,
			'warnings' => $warnings,
		);
	}

	/**
	 * Converte un CSV di definizioni nel formato pipe della textarea admin.
	 * Se c’è uno schema, allinea le righe alle parole della griglia.
	 *
	 * @param string $csv_raw CSV definizioni.
	 * @param array  $entries Parole della griglia (opzionale).
	 * @return array{defs:string,imported:int,warnings:string[]}|WP_Error
	 */
	public static function import_definitions_csv( $csv_raw, array $entries = array() ) {
		$parsed = self::parse_definitions_csv( $csv_raw );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$clues    = $parsed['clues'];
		$warnings = $parsed['warnings'];

		if ( ! empty( $entries ) ) {
			$pipe_bits = array();
			foreach ( $clues as $clue ) {
				$pipe_bits[] = implode(
					self::DEF_SEPARATOR,
					array(
						(string) $clue['number'],
						self::direction_letter( $clue['direction'] ),
						$clue['word'],
						$clue['pos'],
						$clue['en'],
						$clue['it'],
					)
				);
			}
			$defs = self::definitions_skeleton( $entries, implode( "\n", $pipe_bits ) );

			foreach ( $clues as $key => $clue ) {
				$found = false;
				foreach ( $entries as $entry ) {
					if ( self::clue_key( $entry['number'], $entry['direction'] ) === $key ) {
						$found = true;
						break;
					}
				}
				if ( ! $found ) {
					$warnings[] = sprintf(
						/* translators: 1: clue number, 2: direction letter */
						__( 'Nel CSV c’è %1$d %2$s, ma nello schema attuale quella parola non esiste: riga ignorata.', 'llm-con-tabelle' ),
						$clue['number'],
						self::direction_letter( $clue['direction'] )
					);
				}
			}

			$matched = 0;
			foreach ( $entries as $entry ) {
				$key = self::clue_key( $entry['number'], $entry['direction'] );
				if ( isset( $clues[ $key ] ) ) {
					++$matched;
				}
			}

			return array(
				'defs'     => $defs,
				'imported' => $matched,
				'warnings' => $warnings,
			);
		}

		$lines = array(
			'# numero|direzione|parola|categoria|definizione inglese|definizione italiana',
			'# importato da CSV',
		);
		uasort(
			$clues,
			static function ( $a, $b ) {
				if ( $a['number'] !== $b['number'] ) {
					return $a['number'] < $b['number'] ? -1 : 1;
				}
				return strcmp( $a['direction'], $b['direction'] );
			}
		);
		foreach ( $clues as $clue ) {
			$lines[] = implode(
				self::DEF_SEPARATOR,
				array(
					(string) $clue['number'],
					self::direction_letter( $clue['direction'] ),
					$clue['word'],
					$clue['pos'],
					$clue['en'],
					$clue['it'],
				)
			);
		}

		return array(
			'defs'     => implode( "\n", $lines ),
			'imported' => count( $clues ),
			'warnings' => $warnings,
		);
	}

	/**
	 * Definizione formattata come nel gioco: categoria, testo inglese e
	 * italiano a capo tra parentesi. Senza inglese resta solo l'italiano.
	 *
	 * @param array<string,string>|null $clue Definizione.
	 * @return string HTML già escapato.
	 */
	public static function clue_html( $clue ) {
		if ( ! is_array( $clue ) ) {
			return '';
		}
		$pos = isset( $clue['pos'] ) ? (string) $clue['pos'] : '';
		$en  = isset( $clue['en'] ) ? (string) $clue['en'] : '';
		$it  = isset( $clue['it'] ) ? (string) $clue['it'] : '';

		if ( '' === $en ) {
			return esc_html( $it );
		}

		$html = '';
		if ( '' !== $pos ) {
			$html .= '<span class="cw-def-pos">' . esc_html( $pos ) . '</span> ';
		}
		$html .= esc_html( $en );
		if ( '' !== $it ) {
			$html .= '<br><em>(' . esc_html( $it ) . ')</em>';
		}
		return $html;
	}

	/**
	 * Dati completi di un cruciverba, pronti per il frontend.
	 *
	 * @param int $post_id ID cruciverba.
	 * @return array{title:string,rows:int,cols:int,grid:string[],entries:array,clues:array,warnings:string[]}|WP_Error
	 */
	public static function build( $post_id ) {
		$post_id = absint( $post_id );
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || self::CPT !== $post->post_type ) {
			return new WP_Error( 'llm_cw_missing', __( 'Cruciverba non trovato.', 'llm-con-tabelle' ) );
		}

		$parsed = self::parse_grid( self::get_csv( $post_id ) );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$entries = self::entries( $parsed['grid'] );
		if ( empty( $entries ) ) {
			return new WP_Error( 'llm_cw_no_entries', __( 'Lo schema non contiene nessuna parola di almeno due lettere.', 'llm-con-tabelle' ) );
		}

		$defs = self::parse_definitions( self::get_defs( $post_id ), $entries );

		return array(
			'title'    => (string) $post->post_title,
			'rows'     => $parsed['rows'],
			'cols'     => $parsed['cols'],
			'grid'     => $parsed['grid'],
			'entries'  => $entries,
			'clues'    => $defs['clues'],
			'warnings' => $defs['warnings'],
		);
	}
}
