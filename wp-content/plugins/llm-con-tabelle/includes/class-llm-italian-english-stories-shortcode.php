<?php
/**
 * Shortcode catalogo riviste e storie per coppia:
 * [italian-english-stories], [italian-polish-stories], [english-polish-stories], [polish-italian-stories].
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Italian_English_Stories_Shortcode {

	const SHORTCODE = 'italian-english-stories';
	const COLS      = 5;

	/** @var string */
	private static $known = 'it';

	/** @var string */
	private static $target = 'en';

	/**
	 * Shortcode → coppia.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	private static function catalogs() {
		return array(
			'italian-english-stories' => array( 'it', 'en' ),
			'italian-polish-stories'  => array( 'it', 'pl' ),
			'english-polish-stories'  => array( 'en', 'pl' ),
			'polish-italian-stories'  => array( 'pl', 'it' ),
		);
	}

	public static function init() {
		foreach ( array_keys( self::catalogs() ) as $tag ) {
			add_shortcode( $tag, array( __CLASS__, 'render' ) );
		}
		add_action( 'wp', array( __CLASS__, 'apply_catalog_learning_lang' ), 3 );
	}

	/**
	 * Sulle pagine catalogo imposta subito la lingua da imparare,
	 * così il sopratitolo sotto il logo è già «Impara il polacco una frase alla volta».
	 */
	public static function apply_catalog_learning_lang() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( ! empty( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! class_exists( 'LLM_Visitor_Lang' ) || ! class_exists( 'LLM_Languages' ) ) {
			return;
		}
		$pair = self::pair_from_current_page();
		if ( ! $pair ) {
			return;
		}
		$known  = $pair[0];
		$target = $pair[1];
		if ( LLM_Visitor_Lang::stored_known() === $known && LLM_Visitor_Lang::stored_learning() === $target ) {
			return;
		}
		LLM_Visitor_Lang::set_pair( $known, $target );
	}

	/**
	 * @return array{0:string,1:string}|null
	 */
	private static function pair_from_current_page() {
		if ( ! is_singular() ) {
			return null;
		}
		$page_id = get_queried_object_id();
		if ( $page_id <= 0 ) {
			return null;
		}

		if ( class_exists( 'LLM_Home_Redirect' ) ) {
			$pairs = (array) get_option( LLM_Home_Redirect::OPT_PAIRS, array() );
			foreach ( $pairs as $key => $dest_id ) {
				if ( (int) $dest_id !== (int) $page_id ) {
					continue;
				}
				$parts = explode( '_', (string) $key, 2 );
				if ( 2 !== count( $parts ) ) {
					continue;
				}
				$known  = sanitize_key( $parts[0] );
				$target = sanitize_key( $parts[1] );
				if ( LLM_Languages::is_valid( $known ) && LLM_Languages::is_valid( $target ) && $known !== $target ) {
					return array( $known, $target );
				}
			}
		}

		$haystack = '';
		$post     = get_post( $page_id );
		if ( $post ) {
			$haystack .= (string) $post->post_content;
			$el        = get_post_meta( $page_id, '_elementor_data', true );
			if ( is_string( $el ) && '' !== $el ) {
				$haystack .= $el;
			}
		}
		foreach ( self::catalogs() as $tag => $pair ) {
			if ( false !== strpos( $haystack, $tag ) ) {
				return $pair;
			}
		}

		return null;
	}

	/**
	 * @param array<string,string>|string $atts Attributi.
	 * @param string                      $content Contenuto.
	 * @param string                      $tag     Nome shortcode.
	 * @return string
	 */
	public static function render( $atts = array(), $content = '', $tag = '' ) {
		unset( $atts, $content );
		$tag  = sanitize_key( (string) $tag );
		$map  = self::catalogs();
		$pair = isset( $map[ $tag ] ) ? $map[ $tag ] : array( 'it', 'en' );
		self::$known  = $pair[0];
		self::$target = $pair[1];
		if ( class_exists( 'LLM_Visitor_Lang' ) ) {
			LLM_Visitor_Lang::set_pair( self::$known, self::$target );
		}

		if ( class_exists( 'LLM_Home_Page_Uscite_Shortcode' ) ) {
			add_action( 'wp_head', array( 'LLM_Home_Page_Uscite_Shortcode', 'print_font_preconnect' ), 2 );
		}
		wp_enqueue_style(
			'llm-uscite-fonts',
			'https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Bebas+Neue&family=Elms+Sans:ital,wght@0,100..900;1,100..900&family=Lora:ital,wght@0,400..700;1,400..700&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Roboto+Slab:wght@100..900&display=swap',
			array(),
			null
		);
		wp_enqueue_style( 'llm-ui' );
		wp_enqueue_style(
			'llm-italian-english-stories',
			LLM_TABELLE_URL . 'assets/llm-italian-english-stories.css',
			array( 'llm-ui', 'llm-uscite-fonts' ),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_script(
			'llm-italian-english-stories',
			LLM_TABELLE_URL . 'assets/llm-italian-english-stories.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);

		if ( class_exists( 'LLM_Home_Page_Uscite_Shortcode' ) ) {
			LLM_Home_Page_Uscite_Shortcode::print_font_preconnect();
		}

		$sections = self::build_sections();
		$page_title = self::page_heading( self::$known, self::$target );
		$page_desc = class_exists( 'LLM_Nav_Menu_Shortcode' )
			? LLM_Nav_Menu_Shortcode::pair_desc( self::$known, self::$target )
			: '';

		ob_start();
		?>
		<div class="llm-ie-stories llm-ui-scope" data-llm-ie-catalog>
			<div class="llm-ie-stories__backdrop" hidden></div>
			<header class="llm-ie-stories__header">
				<?php if ( $page_title ) : ?>
					<h2 class="llm-ie-stories__page-title"><?php echo esc_html( $page_title ); ?></h2>
				<?php endif; ?>
				<?php if ( $page_desc ) : ?>
					<p class="llm-ie-stories__subtitle"><?php echo esc_html( $page_desc ); ?></p>
				<?php endif; ?>
			</header>

			<?php foreach ( $sections as $section ) : ?>
				<?php
				$grid_mod = isset( $section['grid'] ) ? sanitize_key( (string) $section['grid'] ) : '';
				$grid_cls = 'llm-ie-stories__grid' . ( $grid_mod ? ' llm-ie-stories__grid--' . $grid_mod : '' );
				?>
				<section class="llm-ie-stories__section" id="<?php echo esc_attr( $section['anchor'] ); ?>">
					<h3 class="llm-ie-stories__title"><?php echo esc_html( $section['title'] ); ?></h3>
					<div class="<?php echo esc_attr( $grid_cls ); ?>">
						<?php foreach ( $section['cards'] as $card ) : ?>
							<?php echo self::render_card( $card ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @return array<int,array{title:string,anchor:string,cards:array<int,array<string,mixed>>}>
	 */
	private static function build_sections() {
		$sections = array();
		$sections[] = array(
			'title'  => self::ui( 'latest' ),
			'anchor' => 'llm-ie-ultime',
			'grid'   => 'latest',
			'cards'  => self::pad_cards( self::latest_cards(), 4, 2 ),
		);
		$mag_cards = self::magazine_cards();
		if ( ! empty( $mag_cards ) ) {
			$sections[] = array(
				'title'  => self::ui( 'magazines' ),
				'anchor' => 'llm-ie-riviste',
				'cards'  => self::pad_cards( $mag_cards ),
			);
		}

		$grouped = self::stories_grouped();
		foreach ( $grouped as $group ) {
			$sections[] = array(
				'title'  => $group['title'],
				'anchor' => $group['anchor'],
				'cards'  => self::pad_cards( $group['cards'] ),
			);
		}

		return $sections;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private static function magazine_cards() {
		$ids = class_exists( 'LLM_Magazine' )
			? LLM_Magazine::get_pair_ids_by_date( self::$known, self::$target )
			: array();

		$pair_url = class_exists( 'LLM_Home_Redirect' )
			? LLM_Home_Redirect::pair_url( self::$known, self::$target )
			: '';
		if ( $pair_url ) {
			$pair_url = add_query_arg(
				array(
					'llm_set_known' => self::$known,
					'llm_set_learn' => self::$target,
				),
				$pair_url
			);
		}

		$cards = array();
		foreach ( $ids as $mag_id ) {
			$mag_id = absint( $mag_id );
			$post   = get_post( $mag_id );
			if ( ! $post || LLM_Magazine::CPT !== $post->post_type ) {
				continue;
			}
			$nav       = LLM_Magazine::get_nav_context( $mag_id );
			$issue_num = ! empty( $nav['issue'] ) ? (int) $nav['issue'] : 0;
			$cover_id  = (int) get_post_thumbnail_id( $mag_id );
			$cover     = $cover_id ? wp_get_attachment_image_url( $cover_id, 'medium_large' ) : '';
			$date      = LLM_Magazine::get_date( $mag_id );
			$url       = LLM_Magazine::public_url( $mag_id );
			if ( '' === $url ) {
				$url = $pair_url;
			}

			$excerpt = '';
			if ( $post->post_excerpt ) {
				$excerpt = wp_strip_all_tags( (string) $post->post_excerpt );
			} elseif ( $post->post_content ) {
				$excerpt = wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 28, '…' );
			}

			$cards[] = array(
				'soon'          => false,
				'url'           => $url,
				'title'         => get_the_title( $mag_id ),
				'subtitle'      => '',
				'kicker'        => $issue_num > 0 ? sprintf( 'N. %02d', $issue_num ) : '',
				'cover'         => (string) $cover,
				'kind'          => 'magazine',
				'cefr'          => '',
				'phrase_count'  => 0,
				'unit'          => '',
				'preview'       => array(),
				'excerpt'       => $excerpt,
				'plot'          => '',
				'category'      => self::ui( 'magazines' ),
				'date_label'    => $date ? mysql2date( get_option( 'date_format' ), $date . ' 00:00:00' ) : '',
			);
		}

		return $cards;
	}

	/**
	 * @return array<int,array{title:string,anchor:string,cards:array<int,array<string,mixed>>}>
	 */
	private static function stories_grouped() {
		$stories = self::query_stories();
		$root    = class_exists( 'LLM_Magazine' ) ? LLM_Magazine::pair_root_category( self::$known, self::$target ) : null;
		$child_ids = array();
		if ( $root ) {
			$children = get_terms(
				array(
					'taxonomy'   => 'category',
					'hide_empty' => false,
					'parent'     => (int) $root->term_id,
					'orderby'    => 'name',
					'order'      => 'ASC',
				)
			);
			if ( ! is_wp_error( $children ) ) {
				foreach ( $children as $child ) {
					$child_ids[] = (int) $child->term_id;
				}
			}
		}

		$music = get_term_by( 'slug', 'brani-musicali', 'category' );

		$buckets     = array();
		$order_terms = array();

		foreach ( $child_ids as $cid ) {
			$term = get_term( $cid, 'category' );
			if ( $term && ! is_wp_error( $term ) ) {
				if ( class_exists( 'LLM_Category_Translations' ) && LLM_Category_Translations::is_draft( $term ) ) {
					continue;
				}
				$order_terms[] = $term;
				$buckets[ $cid ] = array();
			}
		}
		if ( $music && ! is_wp_error( $music ) && ! in_array( (int) $music->term_id, $child_ids, true ) ) {
			$order_terms[] = $music;
			$buckets[ (int) $music->term_id ] = array();
		}

		$other_terms = array();
		$uncat       = array();

		foreach ( $stories as $story ) {
			$sid  = (int) $story->ID;
			$term = self::primary_content_term( $sid, $child_ids, $music ? (int) $music->term_id : 0 );
			if ( $term ) {
				$tid = (int) $term->term_id;
				if ( ! isset( $buckets[ $tid ] ) ) {
					$buckets[ $tid ] = array();
					$other_terms[]   = $term;
				}
				$buckets[ $tid ][] = $story;
			} else {
				$uncat[] = $story;
			}
		}

		usort(
			$other_terms,
			static function ( $a, $b ) {
				return strcasecmp( $a->name, $b->name );
			}
		);

		$groups = array();
		foreach ( array_merge( $order_terms, $other_terms ) as $term ) {
			$tid = (int) $term->term_id;
			if ( ! isset( $buckets[ $tid ] ) ) {
				continue;
			}
			$seen = false;
			foreach ( $groups as $g ) {
				if ( $g['term_id'] === $tid ) {
					$seen = true;
					break;
				}
			}
			if ( $seen ) {
				continue;
			}
			if ( empty( $buckets[ $tid ] ) ) {
				continue;
			}
			$groups[] = array(
				'term_id' => $tid,
				'title'   => self::term_title( $term ),
				'anchor'  => 'llm-ie-cat-' . $term->slug,
				'cards'   => self::story_cards( $buckets[ $tid ], self::term_title( $term ) ),
			);
		}

		if ( ! empty( $uncat ) ) {
			$groups[] = array(
				'term_id' => 0,
				'title'   => self::ui( 'other_stories' ),
				'anchor'  => 'llm-ie-altre',
				'cards'   => self::story_cards( $uncat, self::ui( 'other_stories' ) ),
			);
		}

		return $groups;
	}

	/**
	 * @return WP_Post[]
	 */
	private static function query_stories() {
		$q = new WP_Query(
			array(
				'post_type'              => LLM_STORY_CPT,
				'post_status'            => 'publish',
				'posts_per_page'         => 400,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'meta_query'             => array(
					'relation' => 'AND',
					array(
						'key'   => LLM_Story_Meta::KNOWN_LANG,
						'value' => self::$known,
					),
					array(
						'key'   => LLM_Story_Meta::TARGET_LANG,
						'value' => self::$target,
					),
				),
			)
		);

		$out = array();
		foreach ( $q->posts as $post ) {
			if ( self::belongs_to_other_pair( (int) $post->ID ) ) {
				continue;
			}
			$out[] = $post;
		}
		return $out;
	}

	/**
	 * @param int $story_id ID storia.
	 * @return bool
	 */
	private static function belongs_to_other_pair( $story_id ) {
		$terms = get_the_terms( $story_id, 'category' );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return false;
		}
		$own_slugs = class_exists( 'LLM_Magazine' )
			? LLM_Magazine::pair_category_slugs( self::$known, self::$target )
			: array( self::$known . '-' . self::$target );
		$has_own = false;
		$has_other = false;
		foreach ( $terms as $term ) {
			if ( self::is_pair_slug( $term->slug ) ) {
				if ( in_array( $term->slug, $own_slugs, true ) ) {
					$has_own = true;
				} else {
					$has_other = true;
				}
			}
		}
		return $has_other && ! $has_own;
	}

	/**
	 * @param int $story_id  ID.
	 * @param int[] $child_ids Figli it-english.
	 * @param int $music_id  ID brani-musicali.
	 * @return WP_Term|null
	 */
	private static function primary_content_term( $story_id, array $child_ids, $music_id ) {
		$terms = get_the_terms( $story_id, 'category' );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return null;
		}

		$by_id = array();
		foreach ( $terms as $term ) {
			$by_id[ (int) $term->term_id ] = $term;
		}

		foreach ( $child_ids as $cid ) {
			if ( isset( $by_id[ $cid ] ) ) {
				return $by_id[ $cid ];
			}
		}
		if ( $music_id && isset( $by_id[ $music_id ] ) ) {
			return $by_id[ $music_id ];
		}

		foreach ( $terms as $term ) {
			if ( self::is_pair_slug( $term->slug ) ) {
				continue;
			}
			if ( in_array( $term->slug, array( 'uncategorized', 'senza-categoria' ), true ) ) {
				continue;
			}
			return $term;
		}

		return null;
	}

	/**
	 * @param WP_Post[] $stories  Storie.
	 * @param string    $category Nome categoria.
	 * @return array<int,array<string,mixed>>
	 */
	private static function story_cards( array $stories, $category = '' ) {
		$ids    = array();
		foreach ( $stories as $story ) {
			$ids[] = (int) $story->ID;
		}
		$counts   = self::phrase_counts( $ids );
		$previews = self::preview_phrases( $ids );

		$cards = array();
		foreach ( $stories as $story ) {
			$id           = (int) $story->ID;
			$title_it     = get_the_title( $id );
			$title_en     = trim( (string) get_post_meta( $id, LLM_Story_Meta::TITLE_TARGET, true ) );
			$english      = $title_en !== '' ? $title_en : $title_it;
			$translated   = ( $title_en !== '' && $title_it !== '' && strcasecmp( $title_en, $title_it ) !== 0 )
				? $title_it
				: '';
			$cefr_raw     = trim( (string) get_post_meta( $id, LLM_Story_Meta::STORY_CEFR_LEVEL, true ) );
			$cefr_code    = '';
			if ( $cefr_raw && preg_match( '/\b([ABC][12])\b/i', $cefr_raw, $m ) ) {
				$cefr_code = strtoupper( $m[1] );
			}
			$n            = isset( $counts[ $id ] ) ? (int) $counts[ $id ] : 0;
			$is_song      = class_exists( 'LLM_Magazine' ) && LLM_Magazine::is_music_story( $id );
			$unit         = $is_song
				? self::ui( 1 === $n ? 'verse' : 'verses' )
				: self::ui( 1 === $n ? 'phrase' : 'phrases' );
			$thumb_id = get_post_thumbnail_id( $id );
			$cover    = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium_large' ) : '';

			$plot = '';
			if ( class_exists( 'LLM_Story_Meta' ) ) {
				$plot = trim( (string) get_post_meta( $id, LLM_Story_Meta::STORY_PLOT, true ) );
			}

			$cards[] = array(
				'soon'         => false,
				'url'          => (string) get_permalink( $id ),
				'title'        => $english,
				'subtitle'     => $translated,
				'kicker'       => '',
				'cover'        => (string) $cover,
				'kind'         => 'story',
				'cefr'         => $cefr_code,
				'phrase_count' => $n,
				'unit'         => $unit,
				'preview'      => isset( $previews[ $id ] ) ? $previews[ $id ] : array(),
				'excerpt'      => '',
				'plot'         => $plot,
				'category'     => (string) $category,
				'date_label'   => '',
			);
		}
		return $cards;
	}

	/**
	 * @param int[] $story_ids ID storie.
	 * @return array<int,int>
	 */
	private static function phrase_counts( array $story_ids ) {
		global $wpdb;
		$story_ids = array_values( array_unique( array_filter( array_map( 'absint', $story_ids ) ) ) );
		if ( empty( $story_ids ) || ! class_exists( 'LLM_Tabelle_Database' ) ) {
			return array();
		}
		$table = LLM_Tabelle_Database::table( 'llm_story_phrases' );
		$in    = implode( ',', $story_ids );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- solo ID interi.
		$rows = $wpdb->get_results( "SELECT story_id, COUNT(*) AS n FROM {$table} WHERE story_id IN ({$in}) GROUP BY story_id", ARRAY_A );
		$out  = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[ (int) $row['story_id'] ] = (int) $row['n'];
			}
		}
		return $out;
	}

	/**
	 * Prime 2 frasi (la seconda troncata con puntini).
	 *
	 * @param int[] $story_ids ID storie.
	 * @return array<int,array<int,array{interface:string,target:string,ellipsis?:bool}>>
	 */
	private static function preview_phrases( array $story_ids ) {
		global $wpdb;
		$story_ids = array_values( array_unique( array_filter( array_map( 'absint', $story_ids ) ) ) );
		if ( empty( $story_ids ) || ! class_exists( 'LLM_Tabelle_Database' ) ) {
			return array();
		}
		$table = LLM_Tabelle_Database::table( 'llm_story_phrases' );
		$in    = implode( ',', $story_ids );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- solo ID interi.
		$rows = $wpdb->get_results(
			"SELECT story_id, phrase_interface, phrase_target FROM {$table} WHERE story_id IN ({$in}) ORDER BY story_id ASC, sort_order ASC, id ASC",
			ARRAY_A
		);
		$out = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			$sid    = (int) $row['story_id'];
			$target = trim( (string) $row['phrase_target'] );
			$known  = trim( (string) $row['phrase_interface'] );
			if ( '' === $target && '' === $known ) {
				continue;
			}
			if ( ! isset( $out[ $sid ] ) ) {
				$out[ $sid ] = array();
			}
			if ( count( $out[ $sid ] ) >= 2 ) {
				continue;
			}
			$item = array(
				'interface' => $known,
				'target'    => $target,
			);
			if ( 1 === count( $out[ $sid ] ) ) {
				$item['target']    = self::ellipsis_text( $item['target'] );
				$item['interface'] = self::ellipsis_text( $item['interface'] );
				$item['ellipsis']  = true;
			}
			$out[ $sid ][] = $item;
		}
		return $out;
	}

	/**
	 * @param string $text Testo.
	 * @return string
	 */
	private static function ellipsis_text( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return '';
		}
		$cut = wp_trim_words( $text, 10, '' );
		if ( $cut === $text ) {
			return $text . '…';
		}
		return rtrim( $cut, " \t.,;:–-" ) . '…';
	}

	/**
	 * Ultime 8 storie pubblicate della coppia corrente.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function latest_cards() {
		$q = new WP_Query(
			array(
				'post_type'              => LLM_STORY_CPT,
				'post_status'            => 'publish',
				'posts_per_page'         => 8,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'meta_query'             => array(
					'relation' => 'AND',
					array(
						'key'   => LLM_Story_Meta::KNOWN_LANG,
						'value' => self::$known,
					),
					array(
						'key'   => LLM_Story_Meta::TARGET_LANG,
						'value' => self::$target,
					),
				),
			)
		);
		$out = array();
		foreach ( $q->posts as $post ) {
			if ( self::belongs_to_other_pair( (int) $post->ID ) ) {
				continue;
			}
			$out[] = $post;
			if ( count( $out ) >= 8 ) {
				break;
			}
		}
		return self::story_cards( $out, self::ui( 'latest' ) );
	}

	/**
	 * Completa la griglia (minimo $min_rows righe).
	 *
	 * @param array<int,array<string,mixed>> $cards    Card reali.
	 * @param int                            $cols     Colonne.
	 * @param int                            $min_rows Righe minime.
	 * @return array<int,array<string,mixed>>
	 */
	private static function pad_cards( array $cards, $cols = 0, $min_rows = 1 ) {
		$cols     = $cols > 0 ? (int) $cols : self::COLS;
		$min_rows = max( 1, (int) $min_rows );
		$n        = count( $cards );
		$target   = max( $min_rows * $cols, (int) ( ceil( $n / $cols ) * $cols ) );
		$need     = max( 0, $target - $n );
		for ( $i = 0; $i < $need; $i++ ) {
			$cards[] = array(
				'soon'         => true,
				'url'          => '',
				'title'        => '',
				'subtitle'     => '',
				'kicker'       => '',
				'cover'        => '',
				'kind'         => 'placeholder',
				'cefr'         => '',
				'phrase_count' => 0,
				'unit'         => '',
				'preview'      => array(),
				'excerpt'      => '',
				'plot'         => '',
				'category'     => '',
				'date_label'   => '',
			);
		}
		return $cards;
	}

	/**
	 * @param array<string,mixed> $card Dati card.
	 * @return string
	 */
	private static function render_card( array $card ) {
		$soon         = ! empty( $card['soon'] );
		$url          = isset( $card['url'] ) ? (string) $card['url'] : '';
		$title        = isset( $card['title'] ) ? (string) $card['title'] : '';
		$subtitle     = isset( $card['subtitle'] ) ? (string) $card['subtitle'] : '';
		$kicker       = isset( $card['kicker'] ) ? (string) $card['kicker'] : '';
		$cover        = isset( $card['cover'] ) ? (string) $card['cover'] : '';
		$kind         = isset( $card['kind'] ) ? (string) $card['kind'] : 'story';
		$cefr         = isset( $card['cefr'] ) ? (string) $card['cefr'] : '';
		$phrase_count = isset( $card['phrase_count'] ) ? (int) $card['phrase_count'] : 0;
		$unit         = isset( $card['unit'] ) ? (string) $card['unit'] : '';
		$date_label   = isset( $card['date_label'] ) ? (string) $card['date_label'] : '';
		$category     = isset( $card['category'] ) ? (string) $card['category'] : '';
		$interactive  = ( ! $soon && $url !== '' );
		$pop_id       = $interactive ? 'llm-ie-pop-' . wp_unique_id() : '';

		$class = 'llm-ie-stories__card';
		if ( $soon ) {
			$class .= ' llm-ie-stories__card--soon';
		}
		if ( 'magazine' === $kind ) {
			$class .= ' llm-ie-stories__card--mag';
		}
		if ( $interactive ) {
			$class .= ' llm-ie-stories__card--openable';
		}

		$cta_label = self::ui( 'open_story' );
		if ( 'magazine' === $kind ) {
			$cta_label = self::ui( 'open_magazine' );
		} elseif ( $phrase_count > 0 && $unit ) {
			$cta_label = sprintf( self::ui( 'learn_n' ), $phrase_count, $unit );
			if ( $cefr ) {
				$cta_label .= ' [' . $cefr . ']';
			}
		}

		$show_clock    = ( $phrase_count > 0 && $unit );
		$duration_text = $show_clock ? ( $phrase_count . ' ' . $unit ) : $date_label;

		ob_start();
		?>
		<article class="<?php echo esc_attr( $class ); ?>"<?php echo $interactive ? ' data-llm-ie-card data-llm-ie-card-id="' . esc_attr( $pop_id ) . '"' : ''; ?>>
			<?php if ( $interactive ) : ?>
			<div
				class="llm-ie-stories__trigger"
				role="button"
				tabindex="0"
				aria-expanded="false"
				aria-controls="<?php echo esc_attr( $pop_id ); ?>"
			>
			<?php else : ?>
			<div class="llm-ie-stories__trigger" role="group">
			<?php endif; ?>
				<div
					class="llm-ie-stories__cover<?php echo $cover ? '' : ' llm-ie-stories__cover--empty'; ?>"
					<?php if ( $cover ) : ?>
						style="background-image:url('<?php echo esc_url( $cover ); ?>');"
					<?php endif; ?>
					<?php if ( $title ) : ?>
						role="img" aria-label="<?php echo esc_attr( $title ); ?>"
					<?php else : ?>
						aria-hidden="true"
					<?php endif; ?>
				>
					<?php if ( ! $soon ) : ?>
						<?php echo self::cover_badges( $cefr, $duration_text, $show_clock, '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endif; ?>
					<?php if ( $soon ) : ?>
						<span class="llm-ie-stories__soon"><?php echo esc_html( self::ui( 'soon' ) ); ?></span>
					<?php endif; ?>
				</div>
				<div class="llm-ie-stories__body">
					<?php if ( $kicker ) : ?>
						<p class="llm-ie-stories__card-kicker"><?php echo esc_html( $kicker ); ?></p>
					<?php endif; ?>
					<?php if ( $title ) : ?>
						<h4 class="llm-ie-stories__card-title"><?php echo esc_html( $title ); ?></h4>
					<?php endif; ?>
					<?php if ( $subtitle ) : ?>
						<p class="llm-ie-stories__card-sub"><?php echo esc_html( $subtitle ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( $interactive ) : ?>
				<?php echo self::render_popup( $pop_id, $card, $cta_label, $duration_text, $show_clock ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param string               $pop_id Popup id.
	 * @param array<string,mixed>  $card   Dati.
	 * @param string               $cta    Testo pulsante.
	 * @param string              $duration_text Durata visibile.
	 * @param bool                $show_clock    Mostra icona orologio.
	 * @return string
	 */
	private static function render_popup( $pop_id, array $card, $cta, $duration_text, $show_clock = true ) {
		$url      = isset( $card['url'] ) ? (string) $card['url'] : '';
		$title    = isset( $card['title'] ) ? (string) $card['title'] : '';
		$subtitle = isset( $card['subtitle'] ) ? (string) $card['subtitle'] : '';
		$kicker   = isset( $card['kicker'] ) ? (string) $card['kicker'] : '';
		$cover    = isset( $card['cover'] ) ? (string) $card['cover'] : '';
		$kind     = isset( $card['kind'] ) ? (string) $card['kind'] : 'story';
		$cefr     = isset( $card['cefr'] ) ? (string) $card['cefr'] : '';
		$preview  = isset( $card['preview'] ) && is_array( $card['preview'] ) ? $card['preview'] : array();
		$excerpt  = isset( $card['excerpt'] ) ? (string) $card['excerpt'] : '';

		ob_start();
		?>
		<div
			id="<?php echo esc_attr( $pop_id ); ?>"
			class="llm-ie-stories__popup"
			role="dialog"
			aria-modal="true"
			hidden
			data-llm-ie-for="<?php echo esc_attr( $pop_id ); ?>"
			aria-labelledby="<?php echo esc_attr( $pop_id ); ?>-title"
		>
			<button type="button" class="llm-ie-stories__popup-close" aria-label="<?php echo esc_attr( self::ui( 'close' ) ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
			<div
				class="llm-ie-stories__popup-cover<?php echo $cover ? '' : ' llm-ie-stories__cover--empty'; ?>"
				<?php if ( $cover ) : ?>
					style="background-image:url('<?php echo esc_url( $cover ); ?>');"
				<?php endif; ?>
				aria-hidden="true"
			></div>
			<div class="llm-ie-stories__popup-body">
				<?php if ( $kicker ) : ?>
					<p class="llm-ie-stories__popup-kicker"><?php echo esc_html( $kicker ); ?></p>
				<?php endif; ?>
				<h4 id="<?php echo esc_attr( $pop_id ); ?>-title" class="llm-ie-stories__popup-title"><?php echo esc_html( $title ); ?></h4>
				<?php if ( $subtitle ) : ?>
					<p class="llm-ie-stories__popup-sub"><?php echo esc_html( $subtitle ); ?></p>
				<?php endif; ?>
				<?php echo self::popup_meta_tags( isset( $card['category'] ) ? (string) $card['category'] : '', $cefr, $duration_text, $show_clock ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php
				$plot = isset( $card['plot'] ) ? trim( (string) $card['plot'] ) : '';
				if ( $plot ) :
					?>
					<p class="llm-ie-stories__plot"><?php echo esc_html( $plot ); ?></p>
				<?php endif; ?>
				<a class="llm-ie-stories__cta" href="<?php echo esc_url( $url ); ?>">
					<span class="llm-ie-stories__cta-icon" aria-hidden="true"></span>
					<span><?php echo esc_html( $cta ); ?></span>
				</a>
				<?php if ( 'story' === $kind && ! empty( $preview ) ) : ?>
					<ol class="llm-ie-stories__preview">
						<?php foreach ( $preview as $row ) : ?>
							<?php
							$en  = isset( $row['target'] ) ? (string) $row['target'] : '';
							$it  = isset( $row['interface'] ) ? (string) $row['interface'] : '';
							$cut = ! empty( $row['ellipsis'] );
							?>
							<li<?php echo $cut ? ' class="llm-ie-stories__preview-item--last"' : ''; ?>>
								<?php if ( $en ) : ?>
									<span class="llm-ie-stories__preview-learn"><?php echo esc_html( $en ); ?></span>
								<?php endif; ?>
								<?php if ( $it ) : ?>
									<span class="llm-ie-stories__preview-known"><?php echo esc_html( $it ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php elseif ( $excerpt ) : ?>
					<p class="llm-ie-stories__popup-excerpt"><?php echo esc_html( $excerpt ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Etichette sotto il sottotitolo del popup: categoria, livello, frasi.
	 *
	 * @param string $category      Categoria.
	 * @param string $cefr          Livello.
	 * @param string $duration_text Durata.
	 * @param bool   $show_clock    Icona orologio.
	 * @return string
	 */
	private static function popup_meta_tags( $category, $cefr, $duration_text, $show_clock ) {
		$bits     = array();
		$category = trim( (string) $category );
		if ( '' !== $category ) {
			$bits[] = '<span class="llm-ie-stories__cat">' . esc_html( $category ) . '</span>';
		}
		$level = self::level_badge( $cefr, true );
		if ( '' !== $level ) {
			$bits[] = $level;
		}
		if ( $duration_text ) {
			$dur  = '<span class="llm-ie-stories__duration llm-ie-stories__duration--popup">';
			$dur .= $show_clock ? self::clock_icon() : '';
			$dur .= '<span>' . esc_html( $duration_text ) . '</span></span>';
			$bits[] = $dur;
		}
		if ( empty( $bits ) ) {
			return '';
		}
		return '<div class="llm-ie-stories__popup-tags">' . implode( '', $bits ) . '</div>';
	}

	/**
	 * Etichette in overlay sulla copertina.
	 *
	 * @param string $cefr          Livello.
	 * @param string $duration_text Durata.
	 * @param bool   $show_clock    Icona orologio.
	 * @param string $category      Categoria.
	 * @return string
	 */
	private static function cover_badges( $cefr, $duration_text, $show_clock, $category = '' ) {
		$cat = '';
		$category = trim( (string) $category );
		if ( '' !== $category ) {
			$cat = '<span class="llm-ie-stories__cat">' . esc_html( $category ) . '</span>';
		}
		$level = self::level_badge( $cefr );
		$dur   = '';
		if ( $duration_text ) {
			$dur  = '<span class="llm-ie-stories__duration llm-ie-stories__duration--cover">';
			$dur .= $show_clock ? self::clock_icon() : '';
			$dur .= '<span>' . esc_html( $duration_text ) . '</span></span>';
		}
		if ( '' === $cat && '' === $level && '' === $dur ) {
			return '';
		}
		return '<div class="llm-ie-stories__cover-badges">' . $cat . $level . $dur . '</div>';
	}

	/**
	 * @param string $cefr       A1–C2.
	 * @param bool   $with_label Prefisso «Livello».
	 * @return string
	 */
	private static function level_badge( $cefr, $with_label = false ) {
		$cefr = strtoupper( trim( (string) $cefr ) );
		if ( ! preg_match( '/^[ABC][12]$/', $cefr ) ) {
			return '';
		}
		$band = strtolower( $cefr[0] );
		$text = $with_label ? ( self::ui( 'level' ) . ' ' . $cefr ) : $cefr;
		return '<span class="llm-ie-stories__level llm-ie-stories__level--' . esc_attr( $band ) . '">' . esc_html( $text ) . '</span>';
	}

	/**
	 * @return string
	 */
	private static function clock_icon() {
		return '<svg class="llm-ie-stories__clock" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="8.25" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 7.5v5l3.2 1.8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}

	/**
	 * @param WP_Term $term Termine.
	 * @return string
	 */
	private static function term_title( WP_Term $term ) {
		if ( class_exists( 'LLM_Category_Translations' ) ) {
			return LLM_Category_Translations::get_translated_name( $term, self::$known );
		}
		return $term->name;
	}

	/**
	 * @param string $slug Slug categoria.
	 * @return bool
	 */
	private static function is_pair_slug( $slug ) {
		return (bool) preg_match( '/^[a-z]{2,3}-[a-z]{2,10}$/', (string) $slug );
	}

	/**
	 * Titolo pagina: «Storie per imparare il polacco».
	 *
	 * @param string $known  Lingua nota.
	 * @param string $target Lingua obiettivo.
	 * @return string
	 */
	private static function page_heading( $known, $target ) {
		$map = array(
			'it' => array(
				'en' => 'Storie per imparare l\'inglese',
				'pl' => 'Storie per imparare il polacco',
				'es' => 'Storie per imparare lo spagnolo',
				'it' => 'Storie per imparare l\'italiano',
			),
			'en' => array(
				'it' => 'Stories to learn Italian',
				'pl' => 'Stories to learn Polish',
				'es' => 'Stories to learn Spanish',
				'en' => 'Stories to learn English',
			),
			'pl' => array(
				'it' => 'Historie do nauki włoskiego',
				'en' => 'Historie do nauki angielskiego',
				'es' => 'Historie do nauki hiszpańskiego',
				'pl' => 'Historie do nauki polskiego',
			),
			'es' => array(
				'it' => 'Historias para aprender italiano',
				'en' => 'Historias para aprender inglés',
				'pl' => 'Historias para aprender polaco',
				'es' => 'Historias para aprender español',
			),
		);
		if ( isset( $map[ $known ][ $target ] ) ) {
			return $map[ $known ][ $target ];
		}
		return class_exists( 'LLM_Nav_Menu_Shortcode' )
			? LLM_Nav_Menu_Shortcode::pair_title( $known, $target )
			: '';
	}

	/**
	 * Testi UI del catalogo nella lingua 1 della coppia.
	 *
	 * @param string $key Chiave.
	 * @return string
	 */
	private static function ui( $key ) {
		$lang = self::$known;
		$all  = array(
			'it' => array(
				'latest'        => 'Ultime uscite',
				'magazines'     => 'Riviste',
				'other_stories' => 'Altre storie',
				'open_story'    => 'Apri storia',
				'open_magazine' => 'Apri rivista',
				'learn_n'       => 'Impara %d %s',
				'soon'          => 'In arrivo',
				'close'         => 'Chiudi',
				'level'         => 'Livello',
				'phrase'        => 'frase',
				'phrases'       => 'frasi',
				'verse'         => 'verso',
				'verses'        => 'versi',
			),
			'en' => array(
				'latest'        => 'Latest releases',
				'magazines'     => 'Magazines',
				'other_stories' => 'Other stories',
				'open_story'    => 'Open story',
				'open_magazine' => 'Open magazine',
				'learn_n'       => 'Learn %d %s',
				'soon'          => 'Coming soon',
				'close'         => 'Close',
				'level'         => 'Level',
				'phrase'        => 'phrase',
				'phrases'       => 'phrases',
				'verse'         => 'line',
				'verses'        => 'lines',
			),
			'pl' => array(
				'latest'        => 'Ostatnie publikacje',
				'magazines'     => 'Magazyny',
				'other_stories' => 'Inne historie',
				'open_story'    => 'Otwórz historię',
				'open_magazine' => 'Otwórz magazyn',
				'learn_n'       => 'Ucz się: %d %s',
				'soon'          => 'Wkrótce',
				'close'         => 'Zamknij',
				'level'         => 'Poziom',
				'phrase'        => 'zdanie',
				'phrases'       => 'zdań',
				'verse'         => 'wers',
				'verses'        => 'wersów',
			),
			'es' => array(
				'latest'        => 'Últimas salidas',
				'magazines'     => 'Revistas',
				'other_stories' => 'Otras historias',
				'open_story'    => 'Abrir historia',
				'open_magazine' => 'Abrir revista',
				'learn_n'       => 'Aprende %d %s',
				'soon'          => 'Próximamente',
				'close'         => 'Cerrar',
				'level'         => 'Nivel',
				'phrase'        => 'frase',
				'phrases'       => 'frases',
				'verse'         => 'verso',
				'verses'        => 'versos',
			),
		);
		if ( isset( $all[ $lang ][ $key ] ) ) {
			return $all[ $lang ][ $key ];
		}
		return isset( $all['it'][ $key ] ) ? $all['it'][ $key ] : $key;
	}
}
