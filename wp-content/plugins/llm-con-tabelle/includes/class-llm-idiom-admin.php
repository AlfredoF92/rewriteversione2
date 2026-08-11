<?php
/**
 * Admin banca espressioni: coppia lingue + CRUD items.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Idiom_Admin {

	const NONCE_ACTION = 'llm_idiom_save';
	const NONCE_NAME   = 'llm_idiom_nonce';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . LLM_Idiom::CPT, array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'manage_' . LLM_Idiom::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . LLM_Idiom::CPT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	private static function is_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && LLM_Idiom::CPT === $screen->post_type;
	}

	public static function enqueue( $hook ) {
		if ( ! self::is_screen() ) {
			return;
		}
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'llm-idiom-admin',
			LLM_TABELLE_URL . 'assets/llm-idiom-admin.css',
			array(),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_script(
			'llm-idiom-admin',
			LLM_TABELLE_URL . 'assets/llm-idiom-admin.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
	}

	public static function meta_boxes() {
		add_meta_box(
			'llm_idiom_settings',
			__( 'Coppia linguistica', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_settings' ),
			LLM_Idiom::CPT,
			'side',
			'high'
		);
		add_meta_box(
			'llm_idiom_items',
			__( 'Espressioni / modi di dire', 'llm-con-tabelle' ),
			array( __CLASS__, 'render_items' ),
			LLM_Idiom::CPT,
			'normal',
			'default'
		);
	}

	/**
	 * @param WP_Post $post Banca.
	 */
	public static function render_settings( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$known  = LLM_Idiom::get_known( $post->ID );
		$target = LLM_Idiom::get_target( $post->ID );
		$langs  = LLM_Languages::get_codes();
		?>
		<p>
			<label for="llm_idiom_known"><strong><?php esc_html_e( 'Lingua interfaccia (nota)', 'llm-con-tabelle' ); ?></strong></label>
			<select name="llm_idiom_known" id="llm_idiom_known" class="widefat" required>
				<option value=""><?php esc_html_e( 'Seleziona…', 'llm-con-tabelle' ); ?></option>
				<?php foreach ( $langs as $code ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $known, $code ); ?>><?php echo esc_html( LLM_Languages::label( $code ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="llm_idiom_target"><strong><?php esc_html_e( 'Lingua da imparare', 'llm-con-tabelle' ); ?></strong></label>
			<select name="llm_idiom_target" id="llm_idiom_target" class="widefat" required>
				<option value=""><?php esc_html_e( 'Seleziona…', 'llm-con-tabelle' ); ?></option>
				<?php foreach ( $langs as $code ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $target, $code ); ?>><?php echo esc_html( LLM_Languages::label( $code ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description"><?php esc_html_e( 'Una banca espressioni per coppia. Poi in Rivista scegli quale mostrare.', 'llm-con-tabelle' ); ?></p>
		<?php
	}

	/**
	 * @param WP_Post $post Banca.
	 */
	public static function render_items( $post ) {
		$items  = LLM_Idiom::get_items( $post->ID );
		$groups = LLM_Idiom::group_by_category( $items );
		$cats   = LLM_Idiom::default_categories();
		?>
		<p class="llm-idiom-admin__toolbar">
			<button type="button" class="button button-secondary" id="llm-idiom-add"><?php esc_html_e( 'Aggiungi espressione', 'llm-con-tabelle' ); ?></button>
			<span class="llm-idiom-admin__count" id="llm-idiom-count">
				<?php
				printf(
					/* translators: %d: count */
					esc_html( _n( '%d espressione', '%d espressioni', count( $items ), 'llm-con-tabelle' ) ),
					count( $items )
				);
				?>
			</span>
		</p>
		<div id="llm-idiom-items" class="llm-idiom-admin__items" data-count="<?php echo esc_attr( (string) count( $items ) ); ?>">
			<?php if ( empty( $items ) ) : ?>
				<p class="llm-idiom-admin__empty"><?php esc_html_e( 'Nessuna espressione. Aggiungine una.', 'llm-con-tabelle' ); ?></p>
			<?php else : ?>
				<?php foreach ( $groups as $cat_label => $group_items ) : ?>
					<section class="llm-idiom-admin__cat">
						<h4 class="llm-idiom-admin__cat-title"><?php echo esc_html( $cat_label ); ?> <span class="llm-idiom-admin__cat-count">(<?php echo count( $group_items ); ?>)</span></h4>
						<div class="llm-idiom-admin__cat-list">
							<?php
							$n = 0;
							foreach ( $group_items as $item ) {
								++$n;
								self::render_item_block( $item, $n - 1, false );
							}
							?>
						</div>
					</section>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<template id="llm-idiom-item-template">
			<?php
			self::render_item_block(
				array(
					'id'         => '',
					'category'   => '',
					'phrase'     => '',
					'meaning'    => '',
					'equivalent' => '',
				),
				0,
				true
			);
			?>
		</template>
		<datalist id="llm-idiom-category-list">
			<?php foreach ( $cats as $cat ) : ?>
				<option value="<?php echo esc_attr( $cat ); ?>"></option>
			<?php endforeach; ?>
		</datalist>
		<?php
	}

	/**
	 * @param array $item Item.
	 * @param int   $index Index.
	 * @param bool  $open  Open editor.
	 */
	private static function render_item_block( array $item, $index, $open = false ) {
		$id       = isset( $item['id'] ) ? (string) $item['id'] : '';
		$category = isset( $item['category'] ) ? (string) $item['category'] : '';
		$phrase   = isset( $item['phrase'] ) ? (string) $item['phrase'] : '';
		$meaning  = isset( $item['meaning'] ) ? (string) $item['meaning'] : '';
		$equiv    = isset( $item['equivalent'] ) ? (string) $item['equivalent'] : '';
		$preview  = $phrase !== '' ? $phrase : __( '(vuota)', 'llm-con-tabelle' );
		$open_cls = $open ? ' is-open' : '';
		?>
		<div class="llm-idiom-admin__item<?php echo esc_attr( $open_cls ); ?>" data-index="<?php echo esc_attr( (string) $index ); ?>">
			<div class="llm-idiom-admin__row">
				<span class="llm-idiom-admin__preview" title="<?php echo esc_attr( $preview ); ?>"><?php echo esc_html( wp_html_excerpt( $preview, 100, '…' ) ); ?></span>
				<span class="llm-idiom-admin__tools">
					<button type="button" class="button-link llm-idiom-toggle"><?php esc_html_e( 'Modifica', 'llm-con-tabelle' ); ?></button>
					<button type="button" class="button-link-delete llm-idiom-remove"><?php esc_html_e( 'Elimina', 'llm-con-tabelle' ); ?></button>
				</span>
			</div>
			<div class="llm-idiom-admin__editor">
				<input type="hidden" class="llm-idiom-id" name="llm_idiom_items[<?php echo (int) $index; ?>][id]" value="<?php echo esc_attr( $id ); ?>">
				<p>
					<label><?php esc_html_e( 'Categoria', 'llm-con-tabelle' ); ?></label>
					<input type="text" class="widefat llm-idiom-category" list="llm-idiom-category-list" name="llm_idiom_items[<?php echo (int) $index; ?>][category]" value="<?php echo esc_attr( $category ); ?>">
				</p>
				<p>
					<label><?php esc_html_e( 'Espressione', 'llm-con-tabelle' ); ?></label>
					<input type="text" class="widefat llm-idiom-phrase" name="llm_idiom_items[<?php echo (int) $index; ?>][phrase]" value="<?php echo esc_attr( $phrase ); ?>">
				</p>
				<p>
					<label><?php esc_html_e( 'Significato', 'llm-con-tabelle' ); ?></label>
					<textarea class="widefat llm-idiom-meaning" rows="2" name="llm_idiom_items[<?php echo (int) $index; ?>][meaning]"><?php echo esc_textarea( $meaning ); ?></textarea>
				</p>
				<p>
					<label><?php esc_html_e( 'Equivalente (lingua nota)', 'llm-con-tabelle' ); ?></label>
					<textarea class="widefat llm-idiom-equivalent" rows="2" name="llm_idiom_items[<?php echo (int) $index; ?>][equivalent]"><?php echo esc_textarea( $equiv ); ?></textarea>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * @param int     $post_id ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save_post( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$known  = isset( $_POST['llm_idiom_known'] ) ? sanitize_key( wp_unslash( $_POST['llm_idiom_known'] ) ) : '';
		$target = isset( $_POST['llm_idiom_target'] ) ? sanitize_key( wp_unslash( $_POST['llm_idiom_target'] ) ) : '';
		if ( ! LLM_Languages::is_valid( $known ) ) {
			$known = '';
		}
		if ( ! LLM_Languages::is_valid( $target ) ) {
			$target = '';
		}
		if ( $known && $target && $known === $target ) {
			$target = '';
		}

		$raw_items = isset( $_POST['llm_idiom_items'] ) && is_array( $_POST['llm_idiom_items'] )
			? wp_unslash( $_POST['llm_idiom_items'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();

		update_post_meta( $post_id, LLM_Idiom::META_KNOWN, $known );
		update_post_meta( $post_id, LLM_Idiom::META_TARGET, $target );
		LLM_Idiom::save_items( $post_id, $raw_items );
	}

	/**
	 * @param array $columns Colonne.
	 * @return array
	 */
	public static function columns( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['llm_idiom_pair']  = __( 'Coppia', 'llm-con-tabelle' );
				$out['llm_idiom_count'] = __( 'Espressioni', 'llm-con-tabelle' );
			}
		}
		return $out;
	}

	/**
	 * @param string $column  Colonna.
	 * @param int    $post_id ID.
	 */
	public static function column_content( $column, $post_id ) {
		if ( 'llm_idiom_pair' === $column ) {
			$known  = LLM_Idiom::get_known( $post_id );
			$target = LLM_Idiom::get_target( $post_id );
			echo ( $known && $target )
				? esc_html( LLM_Languages::label( $known ) . ' → ' . LLM_Languages::label( $target ) )
				: '—';
		}
		if ( 'llm_idiom_count' === $column ) {
			echo (int) count( LLM_Idiom::get_items( $post_id ) );
		}
	}
}
