<?php
/**
 * Meta box e salvataggio in bacheca.
 *
 * @package LLS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLS_Admin_Story {

	const NONCE_ACTION = 'lls_story_save';
	const NONCE_NAME   = 'lls_story_nonce';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . LLS_CPT, array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'manage_' . LLS_CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . LLS_CPT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	public static function enqueue( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || LLS_CPT !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_style(
			'lls-admin',
			LLS_PLUGIN_URL . 'assets/admin.css',
			array(),
			LLS_VERSION
		);
		wp_enqueue_script(
			'lls-admin',
			LLS_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			LLS_VERSION,
			true
		);
		wp_localize_script(
			'lls-admin',
			'llsAdmin',
			array(
				'selectImage'      => __( 'Scegli immagine', 'language-learning-stories' ),
				'changeImage'      => __( 'Cambia immagine', 'language-learning-stories' ),
				'removeRow'        => __( 'Rimuovi', 'language-learning-stories' ),
				'beforeAllPhrases' => __( 'Prima di tutte le frasi', 'language-learning-stories' ),
				'phraseLabel'      => __( 'Frase', 'language-learning-stories' ),
				'emptyPhraseHint'  => __( '(nessun testo interfaccia)', 'language-learning-stories' ),
			)
		);
	}

	public static function meta_boxes() {
		add_meta_box(
			'lls_story_settings',
			__( 'Impostazioni storia', 'language-learning-stories' ),
			array( __CLASS__, 'render_settings' ),
			LLS_CPT,
			'normal',
			'high'
		);
		add_meta_box(
			'lls_story_phrases',
			__( 'Frasi (ordinate)', 'language-learning-stories' ),
			array( __CLASS__, 'render_phrases' ),
			LLS_CPT,
			'normal',
			'default'
		);
		add_meta_box(
			'lls_story_media',
			__( 'Immagini nel flusso', 'language-learning-stories' ),
			array( __CLASS__, 'render_media' ),
			LLS_CPT,
			'normal',
			'default'
		);
		add_meta_box(
			'lls_story_preview',
			__( 'Anteprima struttura', 'language-learning-stories' ),
			array( __CLASS__, 'render_preview' ),
			LLS_CPT,
			'side',
			'default'
		);
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public static function render_settings( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$known   = get_post_meta( $post->ID, LLS_Story_Meta::KNOWN_LANG, true );
		$target  = get_post_meta( $post->ID, LLS_Story_Meta::TARGET_LANG, true );
		$title_t = get_post_meta( $post->ID, LLS_Story_Meta::TITLE_TARGET, true );
		$plot    = get_post_meta( $post->ID, LLS_Story_Meta::STORY_PLOT, true );
		$cost    = (int) get_post_meta( $post->ID, LLS_Story_Meta::COIN_COST, true );
		$reward  = (int) get_post_meta( $post->ID, LLS_Story_Meta::COIN_REWARD, true );

		$langs = LLS_Languages::get_codes();
		?>
		<div class="lls-field-row">
			<label for="lls_known_lang"><strong><?php esc_html_e( 'Lingua interfaccia (nota)', 'language-learning-stories' ); ?></strong></label>
			<select name="lls_known_lang" id="lls_known_lang" class="widefat">
				<option value=""><?php esc_html_e( '— non impostata —', 'language-learning-stories' ); ?></option>
				<?php foreach ( $langs as $code => $label ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $known, $code ); ?>><?php echo esc_html( $label . ' (' . $code . ')' ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'Lingua in cui è scritto il testo “interfaccia” delle frasi.', 'language-learning-stories' ); ?></p>
		</div>
		<div class="lls-field-row">
			<label for="lls_target_lang"><strong><?php esc_html_e( 'Lingua da imparare (obiettivo)', 'language-learning-stories' ); ?></strong></label>
			<select name="lls_target_lang" id="lls_target_lang" class="widefat">
				<option value=""><?php esc_html_e( '— non impostata —', 'language-learning-stories' ); ?></option>
				<?php foreach ( $langs as $code => $label ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $target, $code ); ?>><?php echo esc_html( $label . ' (' . $code . ')' ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="lls-field-row">
			<label for="lls_title_target_lang"><strong><?php esc_html_e( 'Titolo nella lingua obiettivo', 'language-learning-stories' ); ?></strong></label>
			<input type="text" class="widefat" name="lls_title_target_lang" id="lls_title_target_lang" value="<?php echo esc_attr( $title_t ); ?>" />
			<p class="description"><?php esc_html_e( 'Opzionale; distinto dal titolo del post (WordPress).', 'language-learning-stories' ); ?></p>
		</div>
		<div class="lls-field-row">
			<label for="lls_story_plot"><strong><?php esc_html_e( 'Trama della storia', 'language-learning-stories' ); ?></strong></label>
			<textarea name="lls_story_plot" id="lls_story_plot" class="widefat" rows="5"><?php echo esc_textarea( is_string( $plot ) ? $plot : '' ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Riassunto o sinossi: ambientazione, personaggi e arco narrativo.', 'language-learning-stories' ); ?></p>
		</div>
		<hr />
		<div class="lls-field-row lls-field-inline">
			<div>
				<label for="lls_story_coin_cost"><strong><?php esc_html_e( 'Costo coin (sblocco)', 'language-learning-stories' ); ?></strong></label>
				<input type="number" min="0" step="1" name="lls_story_coin_cost" id="lls_story_coin_cost" value="<?php echo esc_attr( (string) $cost ); ?>" />
				<p class="description"><?php esc_html_e( '0 = gratuita / senza blocco.', 'language-learning-stories' ); ?></p>
			</div>
			<div>
				<label for="lls_story_coin_reward"><strong><?php esc_html_e( 'Premio coin (storia completata)', 'language-learning-stories' ); ?></strong></label>
				<input type="number" min="0" step="1" name="lls_story_coin_reward" id="lls_story_coin_reward" value="<?php echo esc_attr( (string) $reward ); ?>" />
			</div>
		</div>
		<?php
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public static function render_phrases( $post ) {
		$phrases = LLS_Story_Meta::get_phrases( $post->ID );
		if ( empty( $phrases ) ) {
			$phrases = array(
				array(
					'interface' => '',
					'target'    => '',
					'grammar'   => '',
					'alt'       => '',
				),
			);
		}
		?>
		<p class="description"><?php esc_html_e( 'Trascina le righe per riordinare. Apri ogni frase per modificare interfaccia, obiettivo, grammatica e alternativa.', 'language-learning-stories' ); ?></p>
		<div id="lls-phrases-list" class="lls-sortable-list">
			<?php
			foreach ( $phrases as $i => $p ) {
				self::render_phrase_row( $i, $p );
			}
			?>
		</div>
		<p>
			<button type="button" class="button" id="lls-add-phrase"><?php esc_html_e( 'Aggiungi frase', 'language-learning-stories' ); ?></button>
		</p>
		<script type="text/template" id="lls-phrase-template">
			<?php self::render_phrase_row( '{{IDX}}', array( 'interface' => '', 'target' => '', 'grammar' => '', 'alt' => '' ) ); ?>
		</script>
		<?php
	}

	/**
	 * @param string|int $index Indice o placeholder template.
	 * @param array      $p     Frase.
	 */
	private static function render_phrase_row( $index, array $p ) {
		$i = (string) $index;
		if ( is_numeric( $index ) ) {
			$num = (string) ( (int) $index + 1 );
		} else {
			$num = '{{NUM}}';
		}
		$iface = isset( $p['interface'] ) ? $p['interface'] : '';
		$prev  = self::phrase_preview_text( $iface );
		?>
		<div class="lls-phrase-row">
			<span class="lls-drag-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Trascina', 'language-learning-stories' ); ?>"></span>
			<details class="lls-phrase-details">
				<summary class="lls-phrase-summary">
					<span class="lls-phrase-summary-title">
						<?php echo esc_html( __( 'Frase', 'language-learning-stories' ) ); ?>
						<span class="lls-phrase-num"><?php echo esc_html( $num ); ?></span>
					</span>
					<span class="lls-phrase-preview"><?php echo esc_html( $prev ); ?></span>
				</summary>
				<div class="lls-phrase-fields">
					<label><?php esc_html_e( 'Frase (lingua interfaccia)', 'language-learning-stories' ); ?></label>
					<textarea name="lls_phrases[<?php echo esc_attr( $i ); ?>][interface]" class="widefat lls-phrase-interface" rows="2"><?php echo esc_textarea( $iface ); ?></textarea>
					<label><?php esc_html_e( 'Frase (lingua obiettivo)', 'language-learning-stories' ); ?></label>
					<textarea name="lls_phrases[<?php echo esc_attr( $i ); ?>][target]" class="widefat" rows="2"><?php echo esc_textarea( isset( $p['target'] ) ? $p['target'] : '' ); ?></textarea>
					<label><?php esc_html_e( 'Analisi grammaticale', 'language-learning-stories' ); ?></label>
					<textarea name="lls_phrases[<?php echo esc_attr( $i ); ?>][grammar]" class="widefat" rows="3"><?php echo esc_textarea( isset( $p['grammar'] ) ? $p['grammar'] : '' ); ?></textarea>
					<label><?php esc_html_e( 'Traduzione alternativa', 'language-learning-stories' ); ?></label>
					<textarea name="lls_phrases[<?php echo esc_attr( $i ); ?>][alt]" class="widefat" rows="2"><?php echo esc_textarea( isset( $p['alt'] ) ? $p['alt'] : '' ); ?></textarea>
					<button type="button" class="button-link lls-remove-phrase"><?php esc_html_e( 'Rimuovi frase', 'language-learning-stories' ); ?></button>
				</div>
			</details>
		</div>
		<?php
	}

	/**
	 * Anteprima breve per summary accordion.
	 *
	 * @param string $interface Testo interfaccia.
	 * @return string
	 */
	private static function phrase_preview_text( $interface ) {
		$t = trim( preg_replace( '/\s+/', ' ', (string) $interface ) );
		if ( $t === '' ) {
			return __( '(nessun testo interfaccia)', 'language-learning-stories' );
		}
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $t ) > 90 ) {
			return mb_substr( $t, 0, 87 ) . '…';
		}
		if ( strlen( $t ) > 90 ) {
			return substr( $t, 0, 87 ) . '…';
		}
		return $t;
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public static function render_media( $post ) {
		$blocks  = LLS_Story_Meta::get_media_blocks( $post->ID );
		$phrases = LLS_Story_Meta::get_phrases( $post->ID );
		$n       = max( 0, count( $phrases ) );
		?>
		<p class="description"><?php esc_html_e( 'Posizione: prima di tutte le frasi, oppure dopo la frase con indice indicato (0 = dopo la prima frase).', 'language-learning-stories' ); ?></p>
		<div id="lls-media-list" data-phrase-label-after="<?php echo esc_attr__( 'Dopo la frase %d', 'language-learning-stories' ); ?>">
			<?php
			if ( empty( $blocks ) ) {
				self::render_media_row( 0, 0, -1, $n );
			} else {
				foreach ( $blocks as $i => $b ) {
					$aid = isset( $b['attachment_id'] ) ? (int) $b['attachment_id'] : 0;
					$pos = isset( $b['after_phrase_index'] ) ? (int) $b['after_phrase_index'] : -1;
					self::render_media_row( $i, $aid, $pos, $n );
				}
			}
			?>
		</div>
		<p>
			<button type="button" class="button" id="lls-add-media"><?php esc_html_e( 'Aggiungi immagine', 'language-learning-stories' ); ?></button>
		</p>
		<script type="text/template" id="lls-media-template">
			<?php self::render_media_row( '{{IDX}}', 0, -1, $n ); ?>
		</script>
		<?php
	}

	/**
	 * @param string|int $index Index.
	 * @param int        $attachment_id Attachment.
	 * @param int        $after_index Posizione dopo frase (-1 = inizio).
	 * @param int        $phrase_count Numero frasi (per le opzioni select).
	 */
	private static function render_media_row( $index, $attachment_id, $after_index, $phrase_count = 0 ) {
		$i   = (string) $index;
		$url = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
		?>
		<div class="lls-media-row" data-lls-media-row>
			<div class="lls-media-thumb">
				<?php if ( $url ) : ?>
					<img src="<?php echo esc_url( $url ); ?>" alt="" />
				<?php endif; ?>
			</div>
			<input type="hidden" name="lls_media_blocks[<?php echo esc_attr( $i ); ?>][attachment_id]" value="<?php echo esc_attr( (string) $attachment_id ); ?>" class="lls-attachment-id" />
			<div class="lls-media-actions">
				<button type="button" class="button lls-pick-image"><?php esc_html_e( 'Scegli immagine', 'language-learning-stories' ); ?></button>
				<button type="button" class="button-link lls-remove-media"><?php esc_html_e( 'Rimuovi', 'language-learning-stories' ); ?></button>
			</div>
			<label><?php esc_html_e( 'Posizione nel flusso', 'language-learning-stories' ); ?></label>
			<select name="lls_media_blocks[<?php echo esc_attr( $i ); ?>][after_phrase_index]" class="lls-after-phrase widefat">
				<option value="-1" <?php selected( (int) $after_index, -1 ); ?>><?php esc_html_e( 'Prima di tutte le frasi', 'language-learning-stories' ); ?></option>
				<?php
				$count = max( (int) $phrase_count, 0 );
				for ( $k = 0; $k < $count; $k++ ) {
					printf(
						'<option value="%1$d" %3$s>%2$s</option>',
						(int) $k,
						esc_html( sprintf( __( 'Dopo la frase %d', 'language-learning-stories' ), $k + 1 ) ),
						selected( (int) $after_index, $k, false )
					);
				}
				?>
			</select>
		</div>
		<?php
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public static function render_preview( $post ) {
		$known   = get_post_meta( $post->ID, LLS_Story_Meta::KNOWN_LANG, true );
		$target  = get_post_meta( $post->ID, LLS_Story_Meta::TARGET_LANG, true );
		$title_t = get_post_meta( $post->ID, LLS_Story_Meta::TITLE_TARGET, true );
		$plot    = get_post_meta( $post->ID, LLS_Story_Meta::STORY_PLOT, true );
		$phrases = LLS_Story_Meta::get_phrases( $post->ID );
		$media   = LLS_Story_Meta::get_media_blocks( $post->ID );
		$cost    = (int) get_post_meta( $post->ID, LLS_Story_Meta::COIN_COST, true );
		$reward  = (int) get_post_meta( $post->ID, LLS_Story_Meta::COIN_REWARD, true );

		echo '<p><strong>' . esc_html__( 'Titolo post', 'language-learning-stories' ) . ':</strong> ' . esc_html( get_the_title( $post ) ) . '</p>';
		if ( $title_t !== '' ) {
			echo '<p><strong>' . esc_html__( 'Titolo obiettivo', 'language-learning-stories' ) . ':</strong> ' . esc_html( $title_t ) . '</p>';
		}
		if ( is_string( $plot ) && $plot !== '' ) {
			echo '<p><strong>' . esc_html__( 'Trama', 'language-learning-stories' ) . ':</strong><br />' . esc_html( wp_trim_words( $plot, 40 ) ) . '</p>';
		}
		echo '<p><strong>' . esc_html__( 'Lingue', 'language-learning-stories' ) . ':</strong> ';
		echo esc_html( $known ? LLS_Languages::label( $known ) : '—' );
		echo ' → ';
		echo esc_html( $target ? LLS_Languages::label( $target ) : '—' );
		echo '</p>';
		echo '<p><strong>' . esc_html__( 'Coin', 'language-learning-stories' ) . ':</strong> ';
		/* translators: 1: cost, 2: reward */
		echo esc_html( sprintf( __( 'costo %1$d · premio %2$d', 'language-learning-stories' ), $cost, $reward ) );
		echo '</p>';
		echo '<p><strong>' . esc_html__( 'Frasi', 'language-learning-stories' ) . ':</strong> ' . esc_html( (string) count( $phrases ) ) . '</p>';

		if ( empty( $phrases ) && empty( $media ) ) {
			echo '<p class="description">' . esc_html__( 'Aggiungi frasi e immagini per vedere il flusso qui.', 'language-learning-stories' ) . '</p>';
			return;
		}

		$flow = self::build_flow_preview( $phrases, $media );
		echo '<ol class="lls-preview-flow">';
		foreach ( $flow as $item ) {
			if ( 'image' === $item['type'] ) {
				$url = wp_get_attachment_image_url( $item['id'], 'thumbnail' );
				echo '<li class="lls-preview-image">';
				if ( $url ) {
					echo '<img src="' . esc_url( $url ) . '" alt="" /> ';
				}
				echo '<span class="description">#' . esc_html( (string) $item['id'] ) . '</span>';
				echo '</li>';
			} else {
				$idx = $item['index'];
				$pi  = isset( $phrases[ $idx ] ) ? $phrases[ $idx ] : array();
				$if  = isset( $pi['interface'] ) ? $pi['interface'] : '';
				$tg  = isset( $pi['target'] ) ? $pi['target'] : '';
				echo '<li class="lls-preview-phrase"><strong>' . esc_html( sprintf( /* translators: %d phrase number */ __( 'Frase %d', 'language-learning-stories' ), $idx + 1 ) ) . '</strong>';
				if ( $if !== '' ) {
					echo '<br /><em>' . esc_html( wp_trim_words( $if, 20 ) ) . '</em>';
				}
				if ( $tg !== '' ) {
					echo '<br /><span class="description">' . esc_html( wp_trim_words( $tg, 20 ) ) . '</span>';
				}
				echo '</li>';
			}
		}
		echo '</ol>';
		if ( $post->post_status === 'publish' ) {
			$link = get_permalink( $post );
			if ( $link ) {
				echo '<p><a href="' . esc_url( $link ) . '" class="button button-secondary" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Apri sul sito', 'language-learning-stories' ) . '</a></p>';
			}
		}
	}

	/**
	 * Costruisce ordine immagini + frasi per anteprima.
	 *
	 * @param array $phrases Frasi.
	 * @param array $media   Blocchi media.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_flow_preview( array $phrases, array $media ) {
		$items = array();
		foreach ( $media as $b ) {
			$aid = isset( $b['attachment_id'] ) ? (int) $b['attachment_id'] : 0;
			$ap  = isset( $b['after_phrase_index'] ) ? (int) $b['after_phrase_index'] : -1;
			if ( $aid ) {
				$items[] = array(
					'type'  => 'image',
					'id'    => $aid,
					'after' => $ap,
				);
			}
		}
		usort(
			$items,
			function ( $a, $b ) {
				if ( $a['after'] === $b['after'] ) {
					return 0;
				}
				return ( $a['after'] < $b['after'] ) ? -1 : 1;
			}
		);

		$by_after = array();
		foreach ( $items as $it ) {
			$k = (int) $it['after'];
			if ( ! isset( $by_after[ $k ] ) ) {
				$by_after[ $k ] = array();
			}
			$by_after[ $k ][] = $it;
		}

		$flow   = array();
		$pcount = count( $phrases );
		if ( ! empty( $by_after[-1] ) ) {
			foreach ( $by_after[-1] as $im ) {
				$flow[] = array( 'type' => 'image', 'id' => $im['id'] );
			}
		}
		for ( $i = 0; $i < $pcount; $i++ ) {
			$flow[] = array( 'type' => 'phrase', 'index' => $i );
			if ( ! empty( $by_after[ $i ] ) ) {
				foreach ( $by_after[ $i ] as $im ) {
					$flow[] = array( 'type' => 'image', 'id' => $im['id'] );
				}
			}
		}
		return $flow;
	}

	/**
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save_post( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$known = isset( $_POST['lls_known_lang'] ) ? sanitize_key( wp_unslash( $_POST['lls_known_lang'] ) ) : '';
		if ( ! LLS_Languages::is_valid( $known ) ) {
			$known = '';
		}
		$target = isset( $_POST['lls_target_lang'] ) ? sanitize_key( wp_unslash( $_POST['lls_target_lang'] ) ) : '';
		if ( ! LLS_Languages::is_valid( $target ) ) {
			$target = '';
		}

		update_post_meta( $post_id, LLS_Story_Meta::KNOWN_LANG, $known );
		update_post_meta( $post_id, LLS_Story_Meta::TARGET_LANG, $target );
		update_post_meta( $post_id, LLS_Story_Meta::TITLE_TARGET, isset( $_POST['lls_title_target_lang'] ) ? sanitize_text_field( wp_unslash( $_POST['lls_title_target_lang'] ) ) : '' );
		update_post_meta(
			$post_id,
			LLS_Story_Meta::STORY_PLOT,
			isset( $_POST['lls_story_plot'] ) ? LLS_Story_Meta::sanitize_plot( wp_unslash( $_POST['lls_story_plot'] ) ) : ''
		);

		$cost   = isset( $_POST['lls_story_coin_cost'] ) ? LLS_Story_Meta::sanitize_coin_int( wp_unslash( $_POST['lls_story_coin_cost'] ) ) : 0;
		$reward = isset( $_POST['lls_story_coin_reward'] ) ? LLS_Story_Meta::sanitize_coin_int( wp_unslash( $_POST['lls_story_coin_reward'] ) ) : 0;
		update_post_meta( $post_id, LLS_Story_Meta::COIN_COST, $cost );
		update_post_meta( $post_id, LLS_Story_Meta::COIN_REWARD, $reward );

		$phrases_raw = isset( $_POST['lls_phrases'] ) ? wp_unslash( $_POST['lls_phrases'] ) : array();
		$phrases     = LLS_Story_Meta::sanitize_phrases_array( $phrases_raw );
		update_post_meta( $post_id, LLS_Story_Meta::PHRASES, wp_json_encode( $phrases ) );

		$media_raw = isset( $_POST['lls_media_blocks'] ) ? wp_unslash( $_POST['lls_media_blocks'] ) : array();
		$media     = LLS_Story_Meta::sanitize_media_blocks_array( $media_raw );
		update_post_meta( $post_id, LLS_Story_Meta::MEDIA_BLOCKS, wp_json_encode( $media ) );
	}

	/**
	 * @param array $columns Colonne.
	 * @return array
	 */
	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['lls_langs']    = __( 'Lingue', 'language-learning-stories' );
				$new['lls_phrases']  = __( 'Frasi', 'language-learning-stories' );
				$new['lls_coins']    = __( 'Coin', 'language-learning-stories' );
			}
		}
		return $new;
	}

	/**
	 * @param string $column Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'lls_langs':
				$k = get_post_meta( $post_id, LLS_Story_Meta::KNOWN_LANG, true );
				$t = get_post_meta( $post_id, LLS_Story_Meta::TARGET_LANG, true );
				echo esc_html( ( $k ? $k : '—' ) . ' → ' . ( $t ? $t : '—' ) );
				break;
			case 'lls_phrases':
				echo esc_html( (string) count( LLS_Story_Meta::get_phrases( $post_id ) ) );
				break;
			case 'lls_coins':
				$c = (int) get_post_meta( $post_id, LLS_Story_Meta::COIN_COST, true );
				$r = (int) get_post_meta( $post_id, LLS_Story_Meta::COIN_REWARD, true );
				echo esc_html( sprintf( '%d / %d', $c, $r ) );
				break;
		}
	}
}
