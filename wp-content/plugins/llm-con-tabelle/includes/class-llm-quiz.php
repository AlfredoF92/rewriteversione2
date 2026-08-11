<?php
/**
 * Modello quiz: CPT banca domande per coppia linguistica.
 * Ogni domanda ha 3 risposte; ogni risposta ha una spiegazione (nessuna “corretta”).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Quiz {

	const CPT = 'llm_quiz';

	const META_KNOWN     = '_llm_quiz_known_lang';
	const META_TARGET    = '_llm_quiz_target_lang';
	const META_QUESTIONS = '_llm_quiz_questions';

	/** Numero fisso di risposte per domanda. */
	const ANSWERS_PER_QUESTION = 3;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		$labels = array(
			'name'               => _x( 'Quiz', 'post type general name', 'llm-con-tabelle' ),
			'singular_name'      => _x( 'Quiz', 'post type singular name', 'llm-con-tabelle' ),
			'menu_name'          => _x( 'Quiz', 'admin menu', 'llm-con-tabelle' ),
			'add_new'            => _x( 'Aggiungi quiz', 'quiz', 'llm-con-tabelle' ),
			'add_new_item'       => __( 'Aggiungi banca quiz', 'llm-con-tabelle' ),
			'new_item'           => __( 'Nuovo quiz', 'llm-con-tabelle' ),
			'edit_item'          => __( 'Modifica quiz', 'llm-con-tabelle' ),
			'view_item'          => __( 'Vedi quiz', 'llm-con-tabelle' ),
			'all_items'          => __( 'Quiz', 'llm-con-tabelle' ),
			'search_items'       => __( 'Cerca quiz', 'llm-con-tabelle' ),
			'not_found'          => __( 'Nessun quiz trovato.', 'llm-con-tabelle' ),
			'not_found_in_trash' => __( 'Nessun quiz nel cestino.', 'llm-con-tabelle' ),
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
	 * @param int $post_id ID quiz.
	 * @return string
	 */
	public static function get_known( $post_id ) {
		return sanitize_key( (string) get_post_meta( absint( $post_id ), self::META_KNOWN, true ) );
	}

	/**
	 * @param int $post_id ID quiz.
	 * @return string
	 */
	public static function get_target( $post_id ) {
		return sanitize_key( (string) get_post_meta( absint( $post_id ), self::META_TARGET, true ) );
	}

	/**
	 * @param int $post_id ID quiz.
	 * @return array<int,array{id:string,question:string,answers:array<int,array{text:string,explanation:string}>}>
	 */
	public static function get_questions( $post_id ) {
		$raw = get_post_meta( absint( $post_id ), self::META_QUESTIONS, true );
		return self::normalize_questions( $raw );
	}

	/**
	 * @param int   $post_id   ID quiz.
	 * @param array $questions Domande.
	 */
	public static function save_questions( $post_id, $questions ) {
		update_post_meta( absint( $post_id ), self::META_QUESTIONS, self::normalize_questions( $questions ) );
	}

	/**
	 * @param mixed $raw Meta o array grezzo.
	 * @return array<int,array{id:string,question:string,answers:array<int,array{text:string,explanation:string}>}>
	 */
	public static function normalize_questions( $raw ) {
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$raw = $decoded;
			}
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$question = isset( $row['question'] ) ? self::sanitize_text( $row['question'] ) : '';
			$answers  = array();
			$raw_ans  = isset( $row['answers'] ) && is_array( $row['answers'] ) ? $row['answers'] : array();
			for ( $i = 0; $i < self::ANSWERS_PER_QUESTION; $i++ ) {
				$a = isset( $raw_ans[ $i ] ) && is_array( $raw_ans[ $i ] ) ? $raw_ans[ $i ] : array();
				$answers[] = array(
					'text'        => isset( $a['text'] ) ? self::sanitize_text( $a['text'] ) : '',
					'explanation' => isset( $a['explanation'] ) ? self::sanitize_long_text( $a['explanation'] ) : '',
				);
			}

			/* Salta righe totalmente vuote. */
			$has_content = ( '' !== $question );
			if ( ! $has_content ) {
				foreach ( $answers as $a ) {
					if ( '' !== $a['text'] || '' !== $a['explanation'] ) {
						$has_content = true;
						break;
					}
				}
			}
			if ( ! $has_content ) {
				continue;
			}

			$id = isset( $row['id'] ) ? sanitize_key( (string) $row['id'] ) : '';
			if ( '' === $id ) {
				$id = self::new_question_id();
			}

			$out[] = array(
				'id'       => $id,
				'question' => $question,
				'answers'  => $answers,
			);
		}

		return array_values( $out );
	}

	/**
	 * @return string
	 */
	public static function new_question_id() {
		return 'q_' . substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 12 );
	}

	/**
	 * @param string $value Testo breve.
	 * @return string
	 */
	public static function sanitize_text( $value ) {
		return sanitize_text_field( is_string( $value ) ? $value : '' );
	}

	/**
	 * @param string $value Testo lungo (spiegazione).
	 * @return string
	 */
	public static function sanitize_long_text( $value ) {
		return sanitize_textarea_field( is_string( $value ) ? $value : '' );
	}

	/**
	 * Parse CSV domande.
	 * Colonne: question, a1, exp1, a2, exp2, a3, exp3
	 * Prima riga opzionale di header (se contiene "question" / "domanda").
	 *
	 * @param string $csv CSV grezzo.
	 * @return array|WP_Error Domande normalizzate oppure errore.
	 */
	public static function parse_csv( $csv ) {
		$csv = is_string( $csv ) ? str_replace( array( "\r\n", "\r" ), "\n", $csv ) : '';
		$csv = trim( $csv );
		if ( '' === $csv ) {
			return new WP_Error( 'llm_quiz_csv_empty', __( 'Il CSV è vuoto.', 'llm-con-tabelle' ) );
		}

		$lines = preg_split( '/\n+/', $csv );
		if ( ! is_array( $lines ) || empty( $lines ) ) {
			return new WP_Error( 'llm_quiz_csv_empty', __( 'Il CSV è vuoto.', 'llm-con-tabelle' ) );
		}

		$rows = array();
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			$parsed = str_getcsv( $line );
			if ( ! is_array( $parsed ) || count( $parsed ) < 2 ) {
				continue;
			}
			$rows[] = $parsed;
		}

		if ( empty( $rows ) ) {
			return new WP_Error( 'llm_quiz_csv_empty', __( 'Nessuna riga valida nel CSV.', 'llm-con-tabelle' ) );
		}

		/* Salta header. */
		$first = array_map( 'strtolower', array_map( 'trim', $rows[0] ) );
		$header_hit = false;
		foreach ( $first as $cell ) {
			if ( in_array( $cell, array( 'question', 'domanda', 'q', 'answer1', 'risposta1', 'a1' ), true ) ) {
				$header_hit = true;
				break;
			}
		}
		if ( $header_hit ) {
			array_shift( $rows );
		}

		$questions = array();
		foreach ( $rows as $cols ) {
			$cols = array_pad( array_values( $cols ), 7, '' );
			$questions[] = array(
				'id'       => self::new_question_id(),
				'question' => $cols[0],
				'answers'  => array(
					array(
						'text'        => $cols[1],
						'explanation' => $cols[2],
					),
					array(
						'text'        => $cols[3],
						'explanation' => $cols[4],
					),
					array(
						'text'        => $cols[5],
						'explanation' => $cols[6],
					),
				),
			);
		}

		$normalized = self::normalize_questions( $questions );
		if ( empty( $normalized ) ) {
			return new WP_Error( 'llm_quiz_csv_empty', __( 'Nessuna domanda utile nel CSV.', 'llm-con-tabelle' ) );
		}

		return $normalized;
	}

	/**
	 * Banca quiz pubblicata per una coppia (la più recente).
	 *
	 * @param string $known  Lingua nota.
	 * @param string $target Lingua di studio.
	 * @return int ID oppure 0.
	 */
	public static function find_for_pair( $known, $target ) {
		$known  = sanitize_key( (string) $known );
		$target = sanitize_key( (string) $target );
		if ( ! LLM_Languages::is_valid( $known ) || ! LLM_Languages::is_valid( $target ) || $known === $target ) {
			return 0;
		}

		$q = new WP_Query(
			array(
				'post_type'              => self::CPT,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					'relation' => 'AND',
					array(
						'key'   => self::META_KNOWN,
						'value' => $known,
					),
					array(
						'key'   => self::META_TARGET,
						'value' => $target,
					),
				),
				'orderby'                => 'date',
				'order'                  => 'DESC',
			)
		);

		return ! empty( $q->posts[0] ) ? (int) $q->posts[0] : 0;
	}

	/**
	 * Elenco banche quiz per select admin.
	 *
	 * @return array<int,string> ID → etichetta.
	 */
	public static function choices() {
		$q = new WP_Query(
			array(
				'post_type'              => self::CPT,
				'post_status'            => array( 'publish', 'draft', 'private' ),
				'posts_per_page'         => 200,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		$out = array();
		foreach ( $q->posts as $post ) {
			$known  = self::get_known( $post->ID );
			$target = self::get_target( $post->ID );
			$count  = count( self::get_questions( $post->ID ) );
			$pair   = ( $known && $target )
				? LLM_Languages::label( $known ) . ' → ' . LLM_Languages::label( $target )
				: '—';
			$title  = $post->post_title ? $post->post_title : ( '#' . $post->ID );
			$out[ (int) $post->ID ] = sprintf( '%s (%s, %d)', $title, $pair, $count );
		}
		return $out;
	}
}
