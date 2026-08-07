<?php
/**
 * Admin riviste: coppia, data, prima pagina, cruciverba, storie.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Magazine_Admin {

	const NONCE_ACTION = 'llm_magazine_save';
	const NONCE_NAME   = 'llm_magazine_nonce';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . LLM_Magazine::CPT, array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'manage_' . LLM_Magazine::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . LLM_Magazine::CPT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'list_filters' ), 10, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_list_query' ) );
	}

	private static function is_magazine_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && LLM_Magazine::CPT === $screen->post_type;
	}

	public static function enqueue( $hook ) {
		if ( ! self::is_magazine_screen() ) {
			return;
		}
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'llm-magazine-admin',
			LLM_TABELLE_URL . 'assets/llm-magazine-admin.css',
			array(),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_script(
			'llm-magazine-admin',
			LLM_TABELLE_URL . 'assets/llm-magazine-admin.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
		wp_localize_script(
			'llm-magazine-admin',
			'llmMagazineAdmin',
			array(
				'storyTitles'       => self::stories_section_titles_map(),
				'defaultStoryTitle' => __( 'Storie del giorno', 'llm-con-tabelle' ),
				'pickPairFirst'     => __( 'Seleziona prima la coppia di lingue nelle impostazioni.', 'llm-con-tabelle' ),
				'noStories'         => __( 'Nessuna storia nella categoria dedicata a questa coppia (escluse le storie “Brani musicali”).', 'llm-con-tabelle' ),
				'noMusic'           => __( 'Nessun brano: servono storie con la categoria di coppia e anche “Brani musicali”.', 'llm-con-tabelle' ),
				'shortcodeCopied'   => __( 'Shortcode copiato.', 'llm-con-tabelle' ),
			)
		);
	}

	public static function meta_boxes() {
		add_meta_box(
			'llm_magazine_settings',
			__( 'Impostazioni rivista', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_settings' ),
			LLM_Magazine::CPT,
			'normal',
			'high'
		);
		add_meta_box(
			'llm_magazine_rubriche',
			__( 'Rubriche', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_rubriche' ),
			LLM_Magazine::CPT,
			'normal',
			'default'
		);
	}

	/**
	 * @param WP_Post $post Rivista.
	 */
	public static function render_settings( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$known    = LLM_Magazine::get_known( $post->ID );
		$target   = LLM_Magazine::get_target( $post->ID );
		$date     = LLM_Magazine::get_date( $post->ID );
		$homepage = LLM_Magazine::is_homepage( $post->ID );
		$langs    = LLM_Languages::get_codes();
		$shortcode = ( $known && $target && $known !== $target )
			? '[' . LLM_Magazine::homepage_shortcode_name( $known, $target ) . ']'
			: '';
		?>
		<div class="llm-mag-admin">
			<div class="llm-mag-admin__row">
				<div class="llm-mag-admin__field">
					<label for="llm_mag_known"><strong><?php esc_html_e( 'Lingua interfaccia (nota)', 'llm-con-tabelle' ); ?></strong></label>
					<select name="llm_mag_known" id="llm_mag_known" class="widefat" required>
						<option value=""><?php esc_html_e( 'Seleziona…', 'llm-con-tabelle' ); ?></option>
						<?php foreach ( $langs as $code => $label ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $known, $code ); ?>><?php echo esc_html( $label . ' (' . $code . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="llm-mag-admin__field">
					<label for="llm_mag_target"><strong><?php esc_html_e( 'Lingua da imparare', 'llm-con-tabelle' ); ?></strong></label>
					<select name="llm_mag_target" id="llm_mag_target" class="widefat" required>
						<option value=""><?php esc_html_e( 'Seleziona…', 'llm-con-tabelle' ); ?></option>
						<?php foreach ( $langs as $code => $label ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $target, $code ); ?>><?php echo esc_html( $label . ' (' . $code . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="llm-mag-admin__field">
					<label for="llm_mag_date"><strong><?php esc_html_e( 'Data di pubblicazione', 'llm-con-tabelle' ); ?></strong></label>
					<input type="date" name="llm_mag_date" id="llm_mag_date" class="widefat" value="<?php echo esc_attr( $date ); ?>">
					<p class="description"><?php esc_html_e( 'Di default è la data di creazione; puoi cambiarla.', 'llm-con-tabelle' ); ?></p>
				</div>
			</div>

			<div class="llm-mag-admin__homepage">
				<label>
					<input type="checkbox" name="llm_mag_homepage" id="llm_mag_homepage" value="1" <?php checked( $homepage ); ?>>
					<strong><?php esc_html_e( 'Pubblica in prima pagina', 'llm-con-tabelle' ); ?></strong>
				</label>
				<p class="description">
					<?php esc_html_e( 'Se attiva, questa rivista diventa quella mostrata dallo shortcode di prima pagina della coppia. Le altre riviste della stessa coppia perdono il flag.', 'llm-con-tabelle' ); ?>
				</p>
			</div>

			<?php if ( $shortcode ) : ?>
				<div class="llm-mag-admin__shortcode">
					<label for="llm-mag-shortcode"><?php esc_html_e( 'Shortcode prima pagina di questa coppia', 'llm-con-tabelle' ); ?></label>
					<div class="llm-mag-admin__shortcode-row">
						<input type="text" id="llm-mag-shortcode" readonly value="<?php echo esc_attr( $shortcode ); ?>">
						<button type="button" class="button" id="llm-mag-copy"><?php esc_html_e( 'Copia', 'llm-con-tabelle' ); ?></button>
					</div>
				</div>
			<?php else : ?>
				<p class="description" id="llm-mag-shortcode-hint"><?php esc_html_e( 'Scegli la coppia di lingue per vedere lo shortcode da usare nella pagina.', 'llm-con-tabelle' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param WP_Post $post Rivista.
	 */
	public static function render_rubriche( $post ) {
		$known         = LLM_Magazine::get_known( $post->ID );
		$target        = LLM_Magazine::get_target( $post->ID );
		$crossword_id  = LLM_Magazine::get_crossword_id( $post->ID );
		$selected      = LLM_Magazine::get_story_ids( $post->ID );
		$selected_music = LLM_Magazine::get_music_ids( $post->ID );
		$crosswords    = LLM_Magazine::crossword_choices();
		$all_stories   = self::all_stories_for_admin();
		$pair_term_map = self::pair_term_to_keys_map();
		$pair_cat      = ( $known && $target ) ? LLM_Magazine::pair_root_category( $known, $target ) : null;
		$music_cat     = LLM_Magazine::music_category();
		$current_key   = ( $known && $target ) ? LLM_Magazine::pair_key( $known, $target ) : '';
		?>
		<div class="llm-mag-admin llm-mag-admin--rubriche">
			<div class="llm-mag-admin__section">
				<h3><?php esc_html_e( 'Cruciverba del giorno', 'llm-con-tabelle' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Seleziona un cruciverba già creato. In frontend verrà mostrato con lo shortcode del gioco.', 'llm-con-tabelle' ); ?></p>
				<select name="llm_mag_crossword" id="llm_mag_crossword" class="widefat">
					<option value="0"><?php esc_html_e( '— Nessun cruciverba —', 'llm-con-tabelle' ); ?></option>
					<?php foreach ( $crosswords as $id => $title ) : ?>
						<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( $crossword_id, $id ); ?>>
							<?php echo esc_html( $title . ' (#' . $id . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="llm-mag-admin__section">
				<h3 id="llm-mag-stories-title"><?php echo esc_html( self::stories_section_title( $known, $target ) ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Spunta una o più storie della categoria dedicata alla coppia (inclusi i sotto-argomenti). Le storie con anche la categoria “Brani musicali” compaiono solo nella rubrica sotto.', 'llm-con-tabelle' ); ?>
					<?php if ( $pair_cat ) : ?>
						<br>
						<span id="llm-mag-pair-cat-label">
						<?php
						printf(
							/* translators: %s: category name/slug */
							esc_html__( 'Categoria coppia attuale: %s', 'llm-con-tabelle' ),
							esc_html( $pair_cat->name . ' (' . $pair_cat->slug . ')' )
						);
						?>
						</span>
					<?php else : ?>
						<br><span id="llm-mag-pair-cat-label"></span>
					<?php endif; ?>
				</p>
				<p class="llm-mag-admin__stories-empty" id="llm-mag-stories-empty" <?php echo ( $known && $target ) ? 'hidden' : ''; ?>>
					<?php esc_html_e( 'Seleziona prima la coppia di lingue nelle impostazioni.', 'llm-con-tabelle' ); ?>
				</p>
				<div class="llm-mag-admin__stories" id="llm-mag-stories" data-known="<?php echo esc_attr( $known ); ?>" data-target="<?php echo esc_attr( $target ); ?>">
					<?php foreach ( $all_stories as $story ) : ?>
						<?php
						$pair_keys = self::story_pair_keys( $story->ID, $pair_term_map );
						$is_music  = LLM_Magazine::is_music_story( $story->ID );
						$cefr      = (string) get_post_meta( $story->ID, LLM_Story_Meta::STORY_CEFR_LEVEL, true );
						$match     = $current_key && in_array( $current_key, $pair_keys, true ) && ! $is_music;
						$checked   = in_array( (int) $story->ID, $selected, true );
						?>
						<label class="llm-mag-admin__story"
							data-pairs="<?php echo esc_attr( implode( ',', $pair_keys ) ); ?>"
							data-music="<?php echo $is_music ? '1' : '0'; ?>"
							<?php echo $match ? '' : 'hidden'; ?>>
							<input type="checkbox" name="llm_mag_stories[]" value="<?php echo esc_attr( (string) $story->ID ); ?>" <?php checked( $checked ); ?>>
							<span class="llm-mag-admin__story-title"><?php echo esc_html( $story->post_title ); ?></span>
							<span class="llm-mag-admin__story-meta">
								#<?php echo (int) $story->ID; ?>
								· <?php echo esc_html( mysql2date( 'd/m/Y', $story->post_date ) ); ?>
								<?php if ( $cefr ) : ?>
									· <?php echo esc_html( $cefr ); ?>
								<?php endif; ?>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="llm-mag-admin__section">
				<h3><?php esc_html_e( 'Brani musicali', 'llm-con-tabelle' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Spunta una o più storie che appartengono sia alla categoria della coppia sia a “Brani musicali”. In frontend le card saranno orizzontali.', 'llm-con-tabelle' ); ?>
					<?php if ( $music_cat ) : ?>
						<br>
						<?php
						printf(
							/* translators: %s: category name/slug */
							esc_html__( 'Categoria brani: %s', 'llm-con-tabelle' ),
							esc_html( $music_cat->name . ' (' . $music_cat->slug . ')' )
						);
						?>
					<?php else : ?>
						<br>
						<span class="llm-mag-admin__warn">
							<?php esc_html_e( 'Attenzione: crea prima la categoria WordPress con slug “brani-musicali”.', 'llm-con-tabelle' ); ?>
						</span>
					<?php endif; ?>
				</p>
				<p class="llm-mag-admin__stories-empty" id="llm-mag-music-empty" <?php echo ( $known && $target ) ? 'hidden' : ''; ?>>
					<?php esc_html_e( 'Seleziona prima la coppia di lingue nelle impostazioni.', 'llm-con-tabelle' ); ?>
				</p>
				<div class="llm-mag-admin__stories" id="llm-mag-music">
					<?php foreach ( $all_stories as $story ) : ?>
						<?php
						$pair_keys = self::story_pair_keys( $story->ID, $pair_term_map );
						$is_music  = LLM_Magazine::is_music_story( $story->ID );
						$cefr      = (string) get_post_meta( $story->ID, LLM_Story_Meta::STORY_CEFR_LEVEL, true );
						$match     = $current_key && in_array( $current_key, $pair_keys, true ) && $is_music;
						$checked   = in_array( (int) $story->ID, $selected_music, true );
						?>
						<label class="llm-mag-admin__story"
							data-pairs="<?php echo esc_attr( implode( ',', $pair_keys ) ); ?>"
							data-music="<?php echo $is_music ? '1' : '0'; ?>"
							<?php echo $match ? '' : 'hidden'; ?>>
							<input type="checkbox" name="llm_mag_music[]" value="<?php echo esc_attr( (string) $story->ID ); ?>" <?php checked( $checked ); ?>>
							<span class="llm-mag-admin__story-title"><?php echo esc_html( $story->post_title ); ?></span>
							<span class="llm-mag-admin__story-meta">
								#<?php echo (int) $story->ID; ?>
								· <?php echo esc_html( mysql2date( 'd/m/Y', $story->post_date ) ); ?>
								<?php if ( $cefr ) : ?>
									· <?php echo esc_html( $cefr ); ?>
								<?php endif; ?>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Mappa term_id categoria → chiavi coppia (es. it-en) che includono quel termine.
	 *
	 * @return array<int,string[]>
	 */
	private static function pair_term_to_keys_map() {
		$map = array();
		foreach ( LLM_Magazine::all_pairs() as $pair ) {
			foreach ( LLM_Magazine::pair_category_term_ids( $pair['known'], $pair['target'] ) as $tid ) {
				if ( ! isset( $map[ $tid ] ) ) {
					$map[ $tid ] = array();
				}
				$map[ $tid ][] = $pair['key'];
			}
		}
		return $map;
	}

	/**
	 * Chiavi coppia a cui appartiene una storia (via categoria root/figli).
	 * Fallback: meta known/target se non è in nessuna categoria di coppia.
	 *
	 * @param int               $story_id      ID storia.
	 * @param array<int,string[]> $pair_term_map Mappa termini.
	 * @return string[]
	 */
	private static function story_pair_keys( $story_id, array $pair_term_map ) {
		$keys = array();
		$cats = wp_get_post_terms( absint( $story_id ), 'category', array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $cats ) ) {
			foreach ( $cats as $cid ) {
				$cid = (int) $cid;
				if ( isset( $pair_term_map[ $cid ] ) ) {
					$keys = array_merge( $keys, $pair_term_map[ $cid ] );
				}
			}
		}
		$keys = array_values( array_unique( $keys ) );
		if ( ! empty( $keys ) ) {
			return $keys;
		}

		$sk = sanitize_key( (string) get_post_meta( $story_id, LLM_Story_Meta::KNOWN_LANG, true ) );
		$st = sanitize_key( (string) get_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, true ) );
		if ( LLM_Languages::is_valid( $sk ) && LLM_Languages::is_valid( $st ) && $sk !== $st ) {
			return array( LLM_Magazine::pair_key( $sk, $st ) );
		}
		return array();
	}

	/**
	 * Titolo rubrica storie in base alla coppia.
	 *
	 * @param string $known  Lingua nota.
	 * @param string $target Lingua di studio.
	 * @return string
	 */
	public static function stories_section_title( $known, $target ) {
		$known  = sanitize_key( (string) $known );
		$target = sanitize_key( (string) $target );
		if ( LLM_Languages::is_valid( $known ) && LLM_Languages::is_valid( $target ) && $known !== $target ) {
			return sprintf(
				/* translators: 1: language to learn with article, 2: known language with article */
				__( 'Storie per imparare %1$s per chi conosce %2$s', 'llm-con-tabelle' ),
				self::lang_with_article( $target ),
				self::lang_with_article( $known )
			);
		}
		return __( 'Storie del giorno', 'llm-con-tabelle' );
	}

	/**
	 * Nome lingua con articolo italiano.
	 *
	 * @param string $code Codice lingua.
	 * @return string
	 */
	private static function lang_with_article( $code ) {
		$labels = array(
			'en' => 'l’inglese',
			'it' => 'l’italiano',
			'pl' => 'il polacco',
			'es' => 'lo spagnolo',
		);
		$code = sanitize_key( (string) $code );
		return isset( $labels[ $code ] ) ? $labels[ $code ] : LLM_Languages::label( $code );
	}

	/**
	 * Titoli sezione storie per tutte le coppie (per aggiornamento JS).
	 *
	 * @return array<string,string> Chiave coppia → titolo.
	 */
	public static function stories_section_titles_map() {
		$map = array();
		foreach ( LLM_Magazine::all_pairs() as $pair ) {
			$map[ $pair['key'] ] = self::stories_section_title( $pair['known'], $pair['target'] );
		}
		return $map;
	}

	/**
	 * @param string $field Nome campo POST (array di ID).
	 * @return int[]
	 */
	private static function sanitize_id_list_from_post( $field ) {
		$ids = array();
		if ( empty( $_POST[ $field ] ) || ! is_array( $_POST[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $ids;
		}
		foreach ( wp_unslash( $_POST[ $field ] ) as $sid ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$sid = absint( $sid );
			if ( $sid ) {
				$ids[] = $sid;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * @return WP_Post[]
	 */
	private static function all_stories_for_admin() {
		$q = new WP_Query(
			array(
				'post_type'              => LLM_STORY_CPT,
				'post_status'            => 'publish',
				'posts_per_page'         => 300,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);
		return $q->posts;
	}

	/**
	 * @param int     $post_id ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save_post( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$known  = isset( $_POST['llm_mag_known'] ) ? sanitize_key( wp_unslash( $_POST['llm_mag_known'] ) ) : '';
		$target = isset( $_POST['llm_mag_target'] ) ? sanitize_key( wp_unslash( $_POST['llm_mag_target'] ) ) : '';
		if ( ! LLM_Languages::is_valid( $known ) ) {
			$known = '';
		}
		if ( ! LLM_Languages::is_valid( $target ) ) {
			$target = '';
		}
		if ( $known && $target && $known === $target ) {
			$target = '';
		}

		$date = isset( $_POST['llm_mag_date'] ) ? sanitize_text_field( wp_unslash( $_POST['llm_mag_date'] ) ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$date = mysql2date( 'Y-m-d', $post->post_date, false );
		}

		$homepage    = ! empty( $_POST['llm_mag_homepage'] ) ? '1' : '';
		$crossword   = isset( $_POST['llm_mag_crossword'] ) ? absint( $_POST['llm_mag_crossword'] ) : 0;
		$story_ids   = self::sanitize_id_list_from_post( 'llm_mag_stories' );
		$music_ids   = self::sanitize_id_list_from_post( 'llm_mag_music' );

		update_post_meta( $post_id, LLM_Magazine::META_KNOWN, $known );
		update_post_meta( $post_id, LLM_Magazine::META_TARGET, $target );
		update_post_meta( $post_id, LLM_Magazine::META_DATE, $date );
		update_post_meta( $post_id, LLM_Magazine::META_CROSSWORD, $crossword );
		update_post_meta( $post_id, LLM_Magazine::META_STORY_IDS, $story_ids );
		update_post_meta( $post_id, LLM_Magazine::META_MUSIC_IDS, $music_ids );

		if ( '1' === $homepage && $known && $target ) {
			update_post_meta( $post_id, LLM_Magazine::META_HOMEPAGE, '1' );
			LLM_Magazine::clear_other_homepages( $post_id, $known, $target );
		} else {
			delete_post_meta( $post_id, LLM_Magazine::META_HOMEPAGE );
		}
	}

	/**
	 * @param array<string,string> $columns Colonne.
	 * @return array<string,string>
	 */
	public static function columns( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['llm_mag_pair']      = __( 'Coppia', 'llm-con-tabelle' );
				$out['llm_mag_date']      = __( 'Data', 'llm-con-tabelle' );
				$out['llm_mag_homepage']  = __( 'Prima pagina', 'llm-con-tabelle' );
				$out['llm_mag_shortcode'] = __( 'Shortcode', 'llm-con-tabelle' );
			}
		}
		return $out;
	}

	/**
	 * @param string $column  Colonna.
	 * @param int    $post_id ID.
	 */
	public static function column_content( $column, $post_id ) {
		$known  = LLM_Magazine::get_known( $post_id );
		$target = LLM_Magazine::get_target( $post_id );

		switch ( $column ) {
			case 'llm_mag_pair':
				if ( $known && $target ) {
					echo esc_html( LLM_Languages::label( $known ) . ' → ' . LLM_Languages::label( $target ) );
				} else {
					echo '—';
				}
				break;
			case 'llm_mag_date':
				echo esc_html( mysql2date( 'd/m/Y', LLM_Magazine::get_date( $post_id ) . ' 00:00:00' ) );
				break;
			case 'llm_mag_homepage':
				echo LLM_Magazine::is_homepage( $post_id )
					? '<span class="llm-mag-col-yes">' . esc_html__( 'Sì', 'llm-con-tabelle' ) . '</span>'
					: '—';
				break;
			case 'llm_mag_shortcode':
				if ( $known && $target ) {
					echo '<code>[' . esc_html( LLM_Magazine::homepage_shortcode_name( $known, $target ) ) . ']</code>';
				} else {
					echo '—';
				}
				break;
		}
	}

	/**
	 * @param string $post_type Post type.
	 * @param string $which     Top/bottom.
	 */
	public static function list_filters( $post_type, $which = '' ) {
		if ( LLM_Magazine::CPT !== $post_type ) {
			return;
		}
		$current = isset( $_GET['llm_mag_pair'] ) ? sanitize_text_field( wp_unslash( $_GET['llm_mag_pair'] ) ) : '';
		echo '<select name="llm_mag_pair">';
		echo '<option value="">' . esc_html__( 'Tutte le coppie', 'llm-con-tabelle' ) . '</option>';
		foreach ( LLM_Magazine::all_pairs() as $pair ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $pair['key'] ),
				selected( $current, $pair['key'], false ),
				esc_html( $pair['label'] )
			);
		}
		echo '</select>';
	}

	/**
	 * @param WP_Query $query Query.
	 */
	public static function filter_list_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( LLM_Magazine::CPT !== $query->get( 'post_type' ) ) {
			return;
		}
		$pair = isset( $_GET['llm_mag_pair'] ) ? sanitize_text_field( wp_unslash( $_GET['llm_mag_pair'] ) ) : '';
		if ( ! preg_match( '/^[a-z]{2}-[a-z]{2}$/', $pair ) ) {
			return;
		}
		list( $known, $target ) = explode( '-', $pair );
		$query->set(
			'meta_query',
			array(
				'relation' => 'AND',
				array(
					'key'   => LLM_Magazine::META_KNOWN,
					'value' => $known,
				),
				array(
					'key'   => LLM_Magazine::META_TARGET,
					'value' => $target,
				),
			)
		);
	}
}
