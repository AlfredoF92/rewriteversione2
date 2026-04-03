<?php
/**
 * Tre storie demo (3 frasi ciascuna, testi fissi); rimosse in disattivazione.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Demo_Stories {

	const DEMO_META = '_llm_is_demo_story';

	const SEED_OPTION = 'llm_demo_stories_seeded';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_repair_seed' ), 15 );
	}

	/**
	 * Se non ci sono storie o l’opzione “seed fatto” non corrisponde a storie demo reali, ripete il seed (una tantum per visita).
	 */
	public static function maybe_repair_seed() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$any_story = get_posts(
			array(
				'post_type'              => LLM_STORY_CPT,
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$need_seed = false;
		if ( empty( $any_story ) ) {
			$need_seed = true;
		} elseif ( get_option( self::SEED_OPTION ) ) {
			$one_demo = get_posts(
				array(
					'post_type'              => LLM_STORY_CPT,
					'post_status'            => 'any',
					'posts_per_page'         => 1,
					'fields'                 => 'ids',
					'meta_key'               => self::DEMO_META,
					'meta_value'             => '1',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			if ( empty( $one_demo ) ) {
				$need_seed = true;
			}
		}

		if ( ! $need_seed ) {
			return;
		}

		delete_option( self::SEED_OPTION );
		self::seed_on_activate();
	}

	/**
	 * Tre frasi curate per indice storia 0–2.
	 *
	 * @param int $story_index 0, 1 o 2.
	 * @return array<int, array{interface:string,target:string,grammar:string,alt:string}>
	 */
	public static function get_three_phrases_for_story( $story_index ) {
		$story_index = (int) $story_index % 3;

		$all = array(
			array(
				array(
					'interface' => __( '[Demo 1] Buongiorno, vorrei una mappa della città.', 'llm-con-tabelle' ),
					'target'    => 'Good morning, I would like a city map, please.',
					'grammar'   => __( 'Forma cortese: would like + sostantivo.', 'llm-con-tabelle' ),
					'alt'       => __( 'Variante: «Could I have a map?»', 'llm-con-tabelle' ),
				),
				array(
					'interface' => __( '[Demo 1] Il museo è vicino alla fontana.', 'llm-con-tabelle' ),
					'target'    => 'The museum is near the fountain.',
					'grammar'   => __( 'Near + luogo; articolo determinativo.', 'llm-con-tabelle' ),
					'alt'       => __( 'Syn.: close to / next to', 'llm-con-tabelle' ),
				),
				array(
					'interface' => __( '[Demo 1] Grazie, arrivederci e buona giornata.', 'llm-con-tabelle' ),
					'target'    => 'Thank you, goodbye and have a nice day.',
					'grammar'   => __( 'Saluti formali; have a nice day.', 'llm-con-tabelle' ),
					'alt'       => __( 'UK: «Cheerio» (informale).', 'llm-con-tabelle' ),
				),
			),
			array(
				array(
					'interface' => __( '[Demo 2] Quanto costano queste mele?', 'llm-con-tabelle' ),
					'target'    => 'How much do these apples cost?',
					'grammar'   => __( 'How much + do + plurale.', 'llm-con-tabelle' ),
					'alt'       => __( 'Alternativa: «What’s the price of…?»', 'llm-con-tabelle' ),
				),
				array(
					'interface' => __( '[Demo 2] Prendo un chilo di arance.', 'llm-con-tabelle' ),
					'target'    => 'I’ll take one kilo of oranges.',
					'grammar'   => __( 'Will / I’ll per decisione sul momento.', 'llm-con-tabelle' ),
					'alt'       => __( 'US: «I’ll get…»', 'llm-con-tabelle' ),
				),
				array(
					'interface' => __( '[Demo 2] Il verde è il mio colore preferito.', 'llm-con-tabelle' ),
					'target'    => 'Green is my favourite color.',
					'grammar'   => __( 'US color / UK colour; favourite.', 'llm-con-tabelle' ),
					'alt'       => __( 'US: favorite', 'llm-con-tabelle' ),
				),
			),
			array(
				array(
					'interface' => __( '[Demo 3] The weather is lovely today.', 'llm-con-tabelle' ),
					'target'    => 'Oggi il tempo è splendido.',
					'grammar'   => __( 'Lovely / splendido; weather + be.', 'llm-con-tabelle' ),
					'alt'       => __( 'Alt.: «It’s beautiful weather.»', 'llm-con-tabelle' ),
				),
				array(
					'interface' => __( '[Demo 3] We could swim before lunch.', 'llm-con-tabelle' ),
					'target'    => 'Potremmo nuotare prima di pranzo.',
					'grammar'   => __( 'Could per suggerimento; before + nome.', 'llm-con-tabelle' ),
					'alt'       => __( 'Forma: «Let’s swim…»', 'llm-con-tabelle' ),
				),
				array(
					'interface' => __( '[Demo 3] See you at sunset on the beach.', 'llm-con-tabelle' ),
					'target'    => 'Ci vediamo al tramonto in spiaggia.',
					'grammar'   => __( 'See you; sunset; preposizioni in/at.', 'llm-con-tabelle' ),
					'alt'       => __( 'Syn.: «Catch you later at dusk.»', 'llm-con-tabelle' ),
				),
			),
		);

		return $all[ $story_index ];
	}

	public static function seed_on_activate() {
		if ( get_option( self::SEED_OPTION ) ) {
			return;
		}

		$author = self::default_author_id();

		$stories = array(
			array(
				'title'   => __( 'Demo LLM — Mattina a Roma', 'llm-con-tabelle' ),
				'known'   => 'it',
				'target'  => 'en',
				'title_t' => 'Morning in Rome',
				'plot'    => __( 'Tre frasi demo: turismo, indicazioni e saluti.', 'llm-con-tabelle' ),
				'cost'    => 5,
				'reward'  => 12,
			),
			array(
				'title'   => __( 'Demo LLM — Mercato e colori', 'llm-con-tabelle' ),
				'known'   => 'it',
				'target'  => 'es',
				'title_t' => 'Mercado y colores',
				'plot'    => __( 'Tre frasi demo: prezzi, acquisti e preferenze.', 'llm-con-tabelle' ),
				'cost'    => 0,
				'reward'  => 15,
			),
			array(
				'title'   => __( 'Demo LLM — Tramonto in riva al mare', 'llm-con-tabelle' ),
				'known'   => 'en',
				'target'  => 'it',
				'title_t' => 'Tramonto sul mare',
				'plot'    => __( 'Tre frasi demo: tempo, piani e appuntamenti.', 'llm-con-tabelle' ),
				'cost'    => 8,
				'reward'  => 18,
			),
		);

		$created_ids = array();

		foreach ( $stories as $si => $def ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => LLM_STORY_CPT,
					'post_status'  => 'publish',
					'post_title'   => $def['title'],
					'post_content' => __( 'Contenuto demo LLM: usa le tre frasi nel flusso didattico.', 'llm-con-tabelle' ),
					'post_excerpt' => __( 'Storia di esempio con tre frasi in tabella.', 'llm-con-tabelle' ),
					'post_author'  => $author,
				),
				true
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			$created_ids[] = (int) $post_id;

			update_post_meta( $post_id, self::DEMO_META, '1' );
			update_post_meta( $post_id, LLM_Story_Meta::KNOWN_LANG, $def['known'] );
			update_post_meta( $post_id, LLM_Story_Meta::TARGET_LANG, $def['target'] );
			update_post_meta( $post_id, LLM_Story_Meta::TITLE_TARGET, $def['title_t'] );
			update_post_meta( $post_id, LLM_Story_Meta::STORY_PLOT, $def['plot'] );
			update_post_meta( $post_id, LLM_Story_Meta::COIN_COST, (int) $def['cost'] );
			update_post_meta( $post_id, LLM_Story_Meta::COIN_REWARD, (int) $def['reward'] );

			LLM_Story_Repository::save_phrases( $post_id, self::get_three_phrases_for_story( $si ) );
		}

		$expected = count( $stories );
		if ( count( $created_ids ) === $expected ) {
			update_option( self::SEED_OPTION, '1', false );
		} else {
			foreach ( $created_ids as $pid ) {
				wp_delete_post( (int) $pid, true );
			}
		}
	}

	public static function delete_demo_posts() {
		$ids = get_posts(
			array(
				'post_type'      => LLM_STORY_CPT,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::DEMO_META,
				'meta_value'     => '1',
			)
		);
		foreach ( $ids as $pid ) {
			wp_delete_post( (int) $pid, true );
		}
		delete_option( self::SEED_OPTION );
	}

	/**
	 * ID delle storie demo (max 3), ordinati per ID.
	 *
	 * @return int[]
	 */
	public static function get_demo_story_ids() {
		$ids = get_posts(
			array(
				'post_type'      => LLM_STORY_CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'fields'         => 'ids',
				'meta_key'       => self::DEMO_META,
				'meta_value'     => '1',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		return array_map( 'absint', is_array( $ids ) ? $ids : array() );
	}

	private static function default_author_id() {
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => array( 'ID' ),
			)
		);
		if ( ! empty( $admins ) ) {
			return (int) $admins[0]->ID;
		}
		return 1;
	}
}
