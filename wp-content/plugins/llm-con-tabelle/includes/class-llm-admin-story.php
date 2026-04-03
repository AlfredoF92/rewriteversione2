<?php
/**
 * Meta box storia LLM: impostazioni in post meta; frasi/media in tabelle.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Admin_Story {

	const NONCE_ACTION = 'llm_story_save';
	const NONCE_NAME   = 'llm_story_nonce';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . LLM_STORY_CPT, array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'before_delete_post' ) );
		add_filter( 'manage_' . LLM_STORY_CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . LLM_STORY_CPT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	public static function enqueue( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || LLM_STORY_CPT !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_style(
			'llm-admin-story',
			LLM_TABELLE_URL . 'assets/llm-admin.css',
			array(),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_script(
			'llm-admin-story',
			LLM_TABELLE_URL . 'assets/llm-admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			LLM_TABELLE_VERSION,
			true
		);
		wp_localize_script(
			'llm-admin-story',
			'llmAdmin',
			array(
				'selectImage'      => __( 'Scegli immagine', 'llm-con-tabelle' ),
				'changeImage'      => __( 'Cambia immagine', 'llm-con-tabelle' ),
				'removeRow'        => __( 'Rimuovi', 'llm-con-tabelle' ),
				'beforeAllPhrases' => __( 'Prima di tutte le frasi', 'llm-con-tabelle' ),
				'phraseLabel'      => __( 'Frase', 'llm-con-tabelle' ),
				'emptyPhraseHint'  => __( '(nessun testo interfaccia)', 'llm-con-tabelle' ),
			)
		);
	}

	public static function meta_boxes() {
		add_meta_box(
			'llm_story_settings',
			__( 'Impostazioni storia', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_settings' ),
			LLM_STORY_CPT,
			'normal',
			'high'
		);
		add_meta_box(
			'llm_story_phrases',
			__( 'Frasi (ordinate, database)', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_phrases' ),
			LLM_STORY_CPT,
			'normal',
			'default'
		);
		add_meta_box(
			'llm_story_media',
			__( 'Immagini nel flusso (database)', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_media' ),
			LLM_STORY_CPT,
			'normal',
			'default'
		);
		add_meta_box(
			'llm_story_preview',
			__( 'Anteprima struttura', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_preview' ),
			LLM_STORY_CPT,
			'side',
			'default'
		);
	}

	public static function render_settings( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$known   = get_post_meta( $post->ID, LLM_Story_Meta::KNOWN_LANG, true );
		$target  = get_post_meta( $post->ID, LLM_Story_Meta::TARGET_LANG, true );
		$title_t = get_post_meta( $post->ID, LLM_Story_Meta::TITLE_TARGET, true );
		$plot    = get_post_meta( $post->ID, LLM_Story_Meta::STORY_PLOT, true );
		$cost    = (int) get_post_meta( $post->ID, LLM_Story_Meta::COIN_COST, true );
		$reward  = (int) get_post_meta( $post->ID, LLM_Story_Meta::COIN_REWARD, true );

		$langs = LLM_Languages::get_codes();
		?>
		<div class="llm-field-row">
			<label for="llm_known_lang"><strong><?php esc_html_e( 'Lingua interfaccia (nota)', 'llm-con-tabelle' ); ?></strong></label>
			<select name="llm_known_lang" id="llm_known_lang" class="widefat">
				<option value=""><?php esc_html_e( '— non impostata —', 'llm-con-tabelle' ); ?></option>
				<?php foreach ( $langs as $code => $label ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $known, $code ); ?>><?php echo esc_html( $label . ' (' . $code . ')' ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="llm-field-row">
			<label for="llm_target_lang"><strong><?php esc_html_e( 'Lingua da imparare (obiettivo)', 'llm-con-tabelle' ); ?></strong></label>
			<select name="llm_target_lang" id="llm_target_lang" class="widefat">
				<option value=""><?php esc_html_e( '— non impostata —', 'llm-con-tabelle' ); ?></option>
				<?php foreach ( $langs as $code => $label ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $target, $code ); ?>><?php echo esc_html( $label . ' (' . $code . ')' ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="llm-field-row">
			<label for="llm_title_target_lang"><strong><?php esc_html_e( 'Titolo nella lingua obiettivo', 'llm-con-tabelle' ); ?></strong></label>
			<input type="text" class="widefat" name="llm_title_target_lang" id="llm_title_target_lang" value="<?php echo esc_attr( $title_t ); ?>" />
		</div>
		<div class="llm-field-row">
			<label for="llm_story_plot"><strong><?php esc_html_e( 'Trama della storia', 'llm-con-tabelle' ); ?></strong></label>
			<textarea name="llm_story_plot" id="llm_story_plot" class="widefat" rows="5"><?php echo esc_textarea( is_string( $plot ) ? $plot : '' ); ?></textarea>
		</div>
		<hr />
		<div class="llm-field-row llm-field-inline">
			<div>
				<label for="llm_story_coin_cost"><strong><?php esc_html_e( 'Costo coin (sblocco)', 'llm-con-tabelle' ); ?></strong></label>
				<input type="number" min="0" step="1" name="llm_story_coin_cost" id="llm_story_coin_cost" value="<?php echo esc_attr( (string) $cost ); ?>" />
			</div>
			<div>
				<label for="llm_story_coin_reward"><strong><?php esc_html_e( 'Premio coin (completamento)', 'llm-con-tabelle' ); ?></strong></label>
				<input type="number" min="0" step="1" name="llm_story_coin_reward" id="llm_story_coin_reward" value="<?php echo esc_attr( (string) $reward ); ?>" />
			</div>
		</div>
		<?php
	}

	public static function render_phrases( $post ) {
		$phrases = LLM_Story_Repository::get_phrases( $post->ID );
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
		<p class="description"><?php esc_html_e( 'Salvate in tabella dedicata (nessun JSON). Trascina per riordinare.', 'llm-con-tabelle' ); ?></p>
		<div id="llm-phrases-list" class="llm-sortable-list">
			<?php
			foreach ( $phrases as $i => $p ) {
				self::render_phrase_row( $i, $p );
			}
			?>
		</div>
		<p>
			<button type="button" class="button" id="llm-add-phrase"><?php esc_html_e( 'Aggiungi frase', 'llm-con-tabelle' ); ?></button>
		</p>
		<script type="text/template" id="llm-phrase-template">
			<?php self::render_phrase_row( '{{IDX}}', array( 'interface' => '', 'target' => '', 'grammar' => '', 'alt' => '' ) ); ?>
		</script>
		<?php
	}

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
		<div class="llm-phrase-row">
			<span class="llm-drag-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Trascina', 'llm-con-tabelle' ); ?>"></span>
			<details class="llm-phrase-details">
				<summary class="llm-phrase-summary">
					<span class="llm-phrase-summary-title">
						<?php echo esc_html( __( 'Frase', 'llm-con-tabelle' ) ); ?>
						<span class="llm-phrase-num"><?php echo esc_html( $num ); ?></span>
					</span>
					<span class="llm-phrase-preview"><?php echo esc_html( $prev ); ?></span>
				</summary>
				<div class="llm-phrase-fields">
					<label><?php esc_html_e( 'Frase (lingua interfaccia)', 'llm-con-tabelle' ); ?></label>
					<textarea name="llm_phrases[<?php echo esc_attr( $i ); ?>][interface]" class="widefat llm-phrase-interface" rows="2"><?php echo esc_textarea( $iface ); ?></textarea>
					<label><?php esc_html_e( 'Frase (lingua obiettivo)', 'llm-con-tabelle' ); ?></label>
					<textarea name="llm_phrases[<?php echo esc_attr( $i ); ?>][target]" class="widefat" rows="2"><?php echo esc_textarea( isset( $p['target'] ) ? $p['target'] : '' ); ?></textarea>
					<label><?php esc_html_e( 'Analisi grammaticale', 'llm-con-tabelle' ); ?></label>
					<textarea name="llm_phrases[<?php echo esc_attr( $i ); ?>][grammar]" class="widefat" rows="3"><?php echo esc_textarea( isset( $p['grammar'] ) ? $p['grammar'] : '' ); ?></textarea>
					<label><?php esc_html_e( 'Traduzione alternativa', 'llm-con-tabelle' ); ?></label>
					<textarea name="llm_phrases[<?php echo esc_attr( $i ); ?>][alt]" class="widefat" rows="2"><?php echo esc_textarea( isset( $p['alt'] ) ? $p['alt'] : '' ); ?></textarea>
					<button type="button" class="button-link llm-remove-phrase"><?php esc_html_e( 'Rimuovi frase', 'llm-con-tabelle' ); ?></button>
				</div>
			</details>
		</div>
		<?php
	}

	private static function phrase_preview_text( $interface ) {
		$t = trim( preg_replace( '/\s+/', ' ', (string) $interface ) );
		if ( $t === '' ) {
			return __( '(nessun testo interfaccia)', 'llm-con-tabelle' );
		}
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $t ) > 90 ) {
			return mb_substr( $t, 0, 87 ) . '…';
		}
		if ( strlen( $t ) > 90 ) {
			return substr( $t, 0, 87 ) . '…';
		}
		return $t;
	}

	public static function render_media( $post ) {
		$blocks  = LLM_Story_Repository::get_media_blocks( $post->ID );
		$phrases = LLM_Story_Repository::get_phrases( $post->ID );
		$n       = max( 0, count( $phrases ) );
		?>
		<p class="description"><?php esc_html_e( 'Salvate in tabella dedicata.', 'llm-con-tabelle' ); ?></p>
		<div id="llm-media-list" data-phrase-label-after="<?php echo esc_attr__( 'Dopo la frase %d', 'llm-con-tabelle' ); ?>">
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
			<button type="button" class="button" id="llm-add-media"><?php esc_html_e( 'Aggiungi immagine', 'llm-con-tabelle' ); ?></button>
		</p>
		<script type="text/template" id="llm-media-template">
			<?php self::render_media_row( '{{IDX}}', 0, -1, $n ); ?>
		</script>
		<?php
	}

	private static function render_media_row( $index, $attachment_id, $after_index, $phrase_count = 0 ) {
		$i   = (string) $index;
		$url = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
		?>
		<div class="llm-media-row" data-llm-media-row>
			<div class="llm-media-thumb">
				<?php if ( $url ) : ?>
					<img src="<?php echo esc_url( $url ); ?>" alt="" />
				<?php endif; ?>
			</div>
			<input type="hidden" name="llm_media_blocks[<?php echo esc_attr( $i ); ?>][attachment_id]" value="<?php echo esc_attr( (string) $attachment_id ); ?>" class="llm-attachment-id" />
			<div class="llm-media-actions">
				<button type="button" class="button llm-pick-image"><?php esc_html_e( 'Scegli immagine', 'llm-con-tabelle' ); ?></button>
				<button type="button" class="button-link llm-remove-media"><?php esc_html_e( 'Rimuovi', 'llm-con-tabelle' ); ?></button>
			</div>
			<label><?php esc_html_e( 'Posizione nel flusso', 'llm-con-tabelle' ); ?></label>
			<select name="llm_media_blocks[<?php echo esc_attr( $i ); ?>][after_phrase_index]" class="llm-after-phrase widefat">
				<option value="-1" <?php selected( (int) $after_index, -1 ); ?>><?php esc_html_e( 'Prima di tutte le frasi', 'llm-con-tabelle' ); ?></option>
				<?php
				$count = max( (int) $phrase_count, 0 );
				for ( $k = 0; $k < $count; $k++ ) {
					printf(
						'<option value="%1$d" %3$s>%2$s</option>',
						(int) $k,
						esc_html( sprintf( __( 'Dopo la frase %d', 'llm-con-tabelle' ), $k + 1 ) ),
						selected( (int) $after_index, $k, false )
					);
				}
				?>
			</select>
		</div>
		<?php
	}

	public static function render_preview( $post ) {
		$known   = get_post_meta( $post->ID, LLM_Story_Meta::KNOWN_LANG, true );
		$target  = get_post_meta( $post->ID, LLM_Story_Meta::TARGET_LANG, true );
		$title_t = get_post_meta( $post->ID, LLM_Story_Meta::TITLE_TARGET, true );
		$plot    = get_post_meta( $post->ID, LLM_Story_Meta::STORY_PLOT, true );
		$phrases = LLM_Story_Repository::get_phrases( $post->ID );
		$media   = LLM_Story_Repository::get_media_blocks( $post->ID );
		$cost    = (int) get_post_meta( $post->ID, LLM_Story_Meta::COIN_COST, true );
		$reward  = (int) get_post_meta( $post->ID, LLM_Story_Meta::COIN_REWARD, true );

		echo '<p><strong>' . esc_html__( 'Titolo post', 'llm-con-tabelle' ) . ':</strong> ' . esc_html( get_the_title( $post ) ) . '</p>';
		if ( $title_t !== '' ) {
			echo '<p><strong>' . esc_html__( 'Titolo obiettivo', 'llm-con-tabelle' ) . ':</strong> ' . esc_html( $title_t ) . '</p>';
		}
		if ( is_string( $plot ) && $plot !== '' ) {
			echo '<p><strong>' . esc_html__( 'Trama', 'llm-con-tabelle' ) . ':</strong><br />' . esc_html( wp_trim_words( $plot, 40 ) ) . '</p>';
		}
		echo '<p><strong>' . esc_html__( 'Lingue', 'llm-con-tabelle' ) . ':</strong> ';
		echo esc_html( $known ? LLM_Languages::label( $known ) : '—' );
		echo ' → ';
		echo esc_html( $target ? LLM_Languages::label( $target ) : '—' );
		echo '</p>';
		echo '<p><strong>' . esc_html__( 'Coin', 'llm-con-tabelle' ) . ':</strong> ';
		echo esc_html( sprintf( __( 'costo %1$d · premio %2$d', 'llm-con-tabelle' ), $cost, $reward ) );
		echo '</p>';
		echo '<p><strong>' . esc_html__( 'Frasi (in DB)', 'llm-con-tabelle' ) . ':</strong> ' . esc_html( (string) count( $phrases ) ) . '</p>';

		if ( empty( $phrases ) && empty( $media ) ) {
			echo '<p class="description">' . esc_html__( 'Aggiungi frasi e immagini per vedere il flusso.', 'llm-con-tabelle' ) . '</p>';
			return;
		}

		$flow = self::build_flow_preview( $phrases, $media );
		echo '<ol class="llm-preview-flow">';
		foreach ( $flow as $item ) {
			if ( 'image' === $item['type'] ) {
				$url = wp_get_attachment_image_url( $item['id'], 'thumbnail' );
				echo '<li class="llm-preview-image">';
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
				echo '<li class="llm-preview-phrase"><strong>' . esc_html( sprintf( __( 'Frase %d', 'llm-con-tabelle' ), $idx + 1 ) ) . '</strong>';
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
				echo '<p><a href="' . esc_url( $link ) . '" class="button button-secondary" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Apri sul sito', 'llm-con-tabelle' ) . '</a></p>';
			}
		}
	}

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

		$known = isset( $_POST['llm_known_lang'] ) ? sanitize_key( wp_unslash( $_POST['llm_known_lang'] ) ) : '';
		if ( ! LLM_Languages::is_valid( $known ) ) {
			$known = '';
		}
		$target = isset( $_POST['llm_target_lang'] ) ? sanitize_key( wp_unslash( $_POST['llm_target_lang'] ) ) : '';
		if ( ! LLM_Languages::is_valid( $target ) ) {
			$target = '';
		}

		update_post_meta( $post_id, LLM_Story_Meta::KNOWN_LANG, $known );
		update_post_meta( $post_id, LLM_Story_Meta::TARGET_LANG, $target );
		update_post_meta( $post_id, LLM_Story_Meta::TITLE_TARGET, isset( $_POST['llm_title_target_lang'] ) ? sanitize_text_field( wp_unslash( $_POST['llm_title_target_lang'] ) ) : '' );
		update_post_meta(
			$post_id,
			LLM_Story_Meta::STORY_PLOT,
			isset( $_POST['llm_story_plot'] ) ? LLM_Story_Meta::sanitize_plot( wp_unslash( $_POST['llm_story_plot'] ) ) : ''
		);

		$cost   = isset( $_POST['llm_story_coin_cost'] ) ? LLM_Story_Meta::sanitize_coin( wp_unslash( $_POST['llm_story_coin_cost'] ) ) : 0;
		$reward = isset( $_POST['llm_story_coin_reward'] ) ? LLM_Story_Meta::sanitize_coin( wp_unslash( $_POST['llm_story_coin_reward'] ) ) : 0;
		update_post_meta( $post_id, LLM_Story_Meta::COIN_COST, $cost );
		update_post_meta( $post_id, LLM_Story_Meta::COIN_REWARD, $reward );

		$phrases_raw = isset( $_POST['llm_phrases'] ) ? wp_unslash( $_POST['llm_phrases'] ) : array();
		$phrases     = LLM_Story_Repository::sanitize_phrases_from_post( $phrases_raw );
		LLM_Story_Repository::save_phrases( $post_id, $phrases );

		$media_raw = isset( $_POST['llm_media_blocks'] ) ? wp_unslash( $_POST['llm_media_blocks'] ) : array();
		$media     = LLM_Story_Repository::sanitize_media_from_post( $media_raw );
		LLM_Story_Repository::save_media_blocks( $post_id, $media );
	}

	/**
	 * @param int $post_id ID post in eliminazione.
	 */
	public static function before_delete_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || LLM_STORY_CPT !== $post->post_type ) {
			return;
		}
		LLM_Story_Repository::delete_for_story( $post_id );
	}

	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['llm_langs']   = __( 'Lingue', 'llm-con-tabelle' );
				$new['llm_phrases'] = __( 'Frasi (DB)', 'llm-con-tabelle' );
				$new['llm_coins']   = __( 'Coin', 'llm-con-tabelle' );
			}
		}
		return $new;
	}

	public static function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'llm_langs':
				$k = get_post_meta( $post_id, LLM_Story_Meta::KNOWN_LANG, true );
				$t = get_post_meta( $post_id, LLM_Story_Meta::TARGET_LANG, true );
				echo esc_html( ( $k ? $k : '—' ) . ' → ' . ( $t ? $t : '—' ) );
				break;
			case 'llm_phrases':
				echo esc_html( (string) count( LLM_Story_Repository::get_phrases( $post_id ) ) );
				break;
			case 'llm_coins':
				$c = (int) get_post_meta( $post_id, LLM_Story_Meta::COIN_COST, true );
				$r = (int) get_post_meta( $post_id, LLM_Story_Meta::COIN_REWARD, true );
				echo esc_html( sprintf( '%d / %d', $c, $r ) );
				break;
		}
	}
}
