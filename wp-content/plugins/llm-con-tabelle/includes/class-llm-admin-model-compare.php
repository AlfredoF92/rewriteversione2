<?php
/**
 * Pagina wp-admin: Confronto modelli (solo visualizzazione).
 *
 * Blocchi storia:
 * - 4001 / locale 3767 (IT→PL) — Claude Opus 5 / Gemini / Claude
 * - 3661 / locale 3429 (EN→IT) — Grok / Gemini / Claude
 * - 3597 / locale 3365 (IT→EN) — Claude Opus 5 / Gemini / ChatGPT GPT-5.6
 * Prime 2 frasi ciascuno.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Admin_Model_Compare {

	const PAGE_SLUG  = 'llm-model-compare';
	const PHRASE_MAX = 1; // sort_order 0..1 (prime 2 frasi)

	/**
	 * @return list<array{key:string,live_id:int,local_id:int,data_file:string}>
	 */
	private static function stories_config() {
		return array(
			array(
				'key'       => '4001',
				'live_id'   => 4001,
				'local_id'  => 3767,
				'data_file' => 'model-compare-4001.php',
			),
			array(
				'key'       => '3661',
				'live_id'   => 3661,
				'local_id'  => 3429,
				'data_file' => 'model-compare-3661.php',
			),
			array(
				'key'       => '3597',
				'live_id'   => 3597,
				'local_id'  => 3365,
				'data_file' => 'model-compare-3597.php',
			),
			array(
				'key'       => '3513',
				'live_id'   => 3513,
				'local_id'  => 3281,
				'data_file' => 'model-compare-3513.php',
			),
		);
	}

	/** ID storia sul DB corrente per una coppia live/local. */
	private static function resolve_story_id( $live_id, $local_id ) {
		$live_id  = (int) $live_id;
		$local_id = (int) $local_id;
		if ( get_post( $live_id ) && 'llm_story' === get_post_type( $live_id ) ) {
			return $live_id;
		}
		if ( get_post( $local_id ) && 'llm_story' === get_post_type( $local_id ) ) {
			return $local_id;
		}
		return $live_id;
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . LLM_STORY_CPT,
			__( 'Confronto modelli', 'llm-con-tabelle' ),
			__( 'Confronto modelli', 'llm-con-tabelle' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	public static function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}
		if ( function_exists( 'llm_tabelle_register_shared_style_handles' ) ) {
			llm_tabelle_register_shared_style_handles();
		}
		wp_enqueue_style( 'llm-ui' );
		wp_enqueue_style(
			'llm-mc-phrase-game',
			LLM_TABELLE_URL . 'assets/llm-story-phrase-game.css',
			array( 'llm-ui' ),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_style(
			'llm-admin-model-compare',
			LLM_TABELLE_URL . 'assets/llm-admin-model-compare.css',
			array( 'llm-mc-phrase-game' ),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_script(
			'llm-admin-model-compare',
			LLM_TABELLE_URL . 'assets/llm-admin-model-compare.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
	}

	private static function compare_data_for( $data_file ) {
		$path = LLM_TABELLE_DIR . 'includes/data/' . ltrim( (string) $data_file, '/' );
		if ( ! is_readable( $path ) ) {
			return array();
		}
		$data = include $path;
		return is_array( $data ) ? $data : array();
	}

	/**
	 * @return array<int|string,array<string,string>>
	 */
	private static function model_variants_from( $data ) {
		if ( empty( $data['models'] ) || ! is_array( $data['models'] ) ) {
			$legacy = $data;
			unset( $legacy['models'], $legacy['meta'] );
			return $legacy;
		}
		$out = array();
		foreach ( $data['models'] as $key => $model ) {
			$outputs = isset( $model['outputs'] ) && is_array( $model['outputs'] ) ? $model['outputs'] : array();
			foreach ( $outputs as $ix => $html ) {
				if ( ! isset( $out[ $ix ] ) ) {
					$out[ $ix ] = array();
				}
				$out[ $ix ][ $key ] = (string) $html;
			}
		}
		return $out;
	}

	/**
	 * Catalogo modelli noti (fallback metadata).
	 *
	 * @return array<string,array{key:string,label:string,slug:string,provider:string,notes:string,prompt:string}>
	 */
	private static function model_catalog() {
		return array(
			'grok'    => array(
				'key'      => 'grok',
				'label'    => 'Grok 4.6',
				'slug'     => 'cursor-grok-4.6-high-fast',
				'provider' => 'xAI (via Cursor)',
				'notes'    => 'Generato in Cursor Agent con Grok 4.6 high-fast.',
				'prompt'   => '',
			),
			'gemini'  => array(
				'key'      => 'gemini',
				'label'    => 'Gemini 3.1 Pro',
				'slug'     => 'gemini-3.1-pro',
				'provider' => 'Google (via Cursor)',
				'notes'    => 'Generato in Cursor Agent con Gemini 3.1 Pro.',
				'prompt'   => '',
			),
			'claude'  => array(
				'key'      => 'claude',
				'label'    => 'Claude Opus 4.8',
				'slug'     => 'claude-opus-4-8-thinking-high',
				'provider' => 'Anthropic (via Cursor)',
				'notes'    => 'Generato in Cursor Agent con Claude Opus 4.8 (thinking high).',
				'prompt'   => '',
			),
			'opus5'   => array(
				'key'      => 'opus5',
				'label'    => 'Claude Opus 5',
				'slug'     => 'claude-opus-5-thinking-high',
				'provider' => 'Anthropic (via Cursor)',
				'notes'    => 'Generato in Cursor Agent con Claude Opus 5 (thinking high).',
				'prompt'   => '',
			),
			'chatgpt' => array(
				'key'      => 'chatgpt',
				'label'    => 'ChatGPT (GPT-5.6)',
				'slug'     => 'gpt-5.6-sol-medium',
				'provider' => 'OpenAI (via Cursor)',
				'notes'    => 'Generato in Cursor Agent con GPT-5.6 (scrittura).',
				'prompt'   => '',
			),
		);
	}

	/**
	 * @return array<string,array{key:string,label:string,slug:string,provider:string,notes:string,prompt:string}>
	 */
	private static function models_meta_from( $data ) {
		$catalog = self::model_catalog();
		if ( empty( $data['models'] ) || ! is_array( $data['models'] ) ) {
			return array(
				'grok'   => $catalog['grok'],
				'gemini' => $catalog['gemini'],
				'claude' => $catalog['claude'],
			);
		}
		$out = array();
		foreach ( $data['models'] as $key => $m ) {
			if ( ! is_array( $m ) ) {
				continue;
			}
			$def         = isset( $catalog[ $key ] ) ? $catalog[ $key ] : array(
				'key'      => (string) $key,
				'label'    => (string) $key,
				'slug'     => (string) $key,
				'provider' => '',
				'notes'    => '',
				'prompt'   => '',
			);
			$out[ $key ] = array(
				'key'      => (string) $key,
				'label'    => isset( $m['label'] ) ? (string) $m['label'] : $def['label'],
				'slug'     => isset( $m['slug'] ) ? (string) $m['slug'] : $def['slug'],
				'provider' => isset( $m['provider'] ) ? (string) $m['provider'] : $def['provider'],
				'notes'    => isset( $m['notes'] ) ? (string) $m['notes'] : $def['notes'],
				'prompt'   => isset( $m['prompt'] ) ? (string) $m['prompt'] : '',
				'usage'    => ( isset( $m['usage'] ) && is_array( $m['usage'] ) ) ? $m['usage'] : array(),
			);
		}
		return $out;
	}

	private static function story_info_for( $cfg ) {
		$sid    = self::resolve_story_id( $cfg['live_id'], $cfg['local_id'] );
		$post   = get_post( $sid );
		$known  = (string) get_post_meta( $sid, '_llm_known_lang', true );
		$target = (string) get_post_meta( $sid, '_llm_target_lang', true );
		$title_target = (string) get_post_meta( $sid, '_llm_title_target_lang', true );
		$cefr   = (string) get_post_meta( $sid, '_llm_story_cefr_level', true );
		global $wpdb;
		$table = $wpdb->prefix . 'llm_story_phrases';
		$count = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE story_id = %d", $sid )
		);
		$known_label  = class_exists( 'LLM_Languages' ) ? LLM_Languages::label( $known ) : $known;
		$target_label = class_exists( 'LLM_Languages' ) ? LLM_Languages::label( $target ) : $target;
		$known_flag   = class_exists( 'LLM_Languages' ) ? LLM_Languages::flag_emoji( $known ) : '';
		$target_flag  = class_exists( 'LLM_Languages' ) ? LLM_Languages::flag_emoji( $target ) : '';

		return array(
			'id'               => $sid,
			'live_id'          => (int) $cfg['live_id'],
			'local_id'         => (int) $cfg['local_id'],
			'title'            => $post ? $post->post_title : '—',
			'title_target'     => $title_target,
			'slug'             => $post ? $post->post_name : '—',
			'excerpt'          => $post ? $post->post_excerpt : '',
			'known'            => $known ?: '—',
			'target'           => $target ?: '—',
			'known_label'      => $known_label,
			'target_label'     => $target_label,
			'known_flag'       => $known_flag,
			'target_flag'      => $target_flag,
			'cefr'             => $cefr,
			'count'            => $count,
			'i_know_line'      => trim( 'I know ' . $known_label . ' ' . $known_flag ),
			'im_learning_line' => trim( "I'm learning " . $target_label . ' ' . $target_flag ),
		);
	}

	/**
	 * @return list<array{sort_order:int,interface:string,target:string,grammar:string,alt:string}>
	 */
	private static function phrases_for( $story_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'llm_story_phrases';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sort_order, phrase_interface, phrase_target, phrase_grammar, phrase_alt
				 FROM {$table}
				 WHERE story_id = %d AND sort_order BETWEEN 0 AND %d
				 ORDER BY sort_order ASC",
				(int) $story_id,
				self::PHRASE_MAX
			),
			ARRAY_A
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'sort_order' => (int) $r['sort_order'],
				'interface'  => (string) $r['phrase_interface'],
				'target'     => (string) $r['phrase_target'],
				'grammar'    => (string) $r['phrase_grammar'],
				'alt'        => (string) $r['phrase_alt'],
			);
		}
		return $out;
	}

	/**
	 * @return list<array{type:string,label:string,html:string}>
	 */
	public static function split_grammar_sections( $html, $known_lang = 'it' ) {
		$html = trim( (string) $html );
		if ( '' === $html ) {
			return array();
		}
		if ( false === stripos( $html, '<p' ) ) {
			$html = '<p>' . $html . '</p>';
		}
		libxml_use_internal_errors( true );
		$dom     = new DOMDocument( '1.0', 'UTF-8' );
		$wrapped = '<?xml encoding="UTF-8"><div id="llm-root">' . $html . '</div>';
		$dom->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		$root = $dom->getElementById( 'llm-root' );
		if ( ! $root ) {
			return array();
		}
		$sections   = array();
		$current_ix = null;
		$xpath      = new DOMXPath( $dom );
		$paras      = $xpath->query( './/p', $root );
		foreach ( $paras as $p ) {
			/** @var DOMElement $p */
			$inner        = self::inner_html( $p );
			$strongs      = $p->getElementsByTagName( 'strong' );
			$first_strong = $strongs->length ? $strongs->item( 0 ) : null;
			$heading_type = null;
			$label        = '';
			if ( $first_strong ) {
				$heading_text = trim( $first_strong->textContent );
				$heading_type = self::classify_heading( $heading_text );
				$label_html   = self::inner_html( $first_strong );
				$label        = rtrim( preg_replace( '/:\s*$/u', '', $label_html ) );
			}
			$is_heading = false;
			if ( $heading_type && $first_strong ) {
				$idx_strong = strpos( $inner, '<strong' );
				$before     = ( false === $idx_strong ) ? $inner : substr( $inner, 0, $idx_strong );
				$before     = preg_replace( '/(<br\s*\/?>|\s|&nbsp;|\x{FE0F}|[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}])+/u', '', $before );
				$is_heading = ( '' === (string) $before );
			}
			if ( ! $is_heading || ! $heading_type ) {
				if ( null !== $current_ix ) {
					$sections[ $current_ix ]['html_parts'][] = $inner;
				} else {
					$sections[] = array(
						'type'       => 'misc',
						'label'      => '',
						'html_parts' => array( $inner ),
					);
					$current_ix = count( $sections ) - 1;
				}
				continue;
			}
			if ( 'remember' === $heading_type ) {
				continue;
			}
			if ( 'conjugation' === $heading_type ) {
				$tense = self::conjugation_tense_label( $known_lang );
				if ( preg_match( '/"([^"]+)"\s*:?\s*$/u', $first_strong->textContent, $vm ) ) {
					$verb  = $vm[1];
					$label = $tense . ' - "' . mb_strtoupper( mb_substr( $verb, 0, 1 ) ) . mb_substr( $verb, 1 ) . '"';
				} else {
					$label = $tense;
				}
			}
			if ( 'etymology' === $heading_type ) {
				$label = ( 'en' === $known_lang ) ? 'Etymology curiosity' : 'Curiosità etimologia';
			}
			$sections[] = array(
				'type'       => $heading_type,
				'label'      => $label,
				'html_parts' => array( $inner ),
			);
			$current_ix = ( 'pair' === $heading_type ) ? ( count( $sections ) - 1 ) : null;
		}
		$out = array();
		foreach ( $sections as $sec ) {
			$parts = isset( $sec['html_parts'] ) ? $sec['html_parts'] : array();
			$body  = '';
			foreach ( $parts as $part ) {
				$body .= '<p>' . $part . '</p>';
			}
			$out[] = array(
				'type'  => $sec['type'],
				'label' => wp_strip_all_tags( html_entity_decode( $sec['label'], ENT_QUOTES, 'UTF-8' ) ),
				'html'  => $body,
			);
		}
		return $out;
	}

	private static function conjugation_tense_label( $known_lang ) {
		if ( 'en' === $known_lang ) {
			return 'Present';
		}
		if ( 'pl' === $known_lang ) {
			return 'Czas teraźniejszy';
		}
		return 'Presente';
	}

	private static function classify_heading( $text ) {
		$t = trim( (string) $text );
		if ( '' === $t ) {
			return null;
		}
		if ( preg_match( '/→|->/u', $t ) ) {
			return 'pair';
		}
		if ( preg_match( '/conjugat|conjugaci|coniugazione|odmiana|koniugacj/iu', $t ) ) {
			return 'conjugation';
		}
		if ( preg_match( '/^(remember|ricorda|recuerda|pamiętaj)\b/iu', $t ) ) {
			return 'remember';
		}
		if ( preg_match( '/etimolog|etymolog/iu', $t ) ) {
			return 'etymology';
		}
		return null;
	}

	private static function inner_html( DOMNode $node ) {
		$html = '';
		foreach ( $node->childNodes as $child ) {
			$html .= $node->ownerDocument->saveHTML( $child );
		}
		return $html;
	}

	/**
	 * @param list<array{type:string,label:string,html:string}> $sections
	 */
	public static function render_accordion( $sections, $alt = '', $uid = '', $known_lang = 'it' ) {
		$uid      = $uid ?: uniqid( 'mc-', false );
		$alt_label = ( 'en' === $known_lang ) ? 'Alternative translation' : 'Traduzione alternativa';
		$i         = 0;
		echo '<div class="llm-phrase-game__grammar-sections llm-mc-accordion">';
		foreach ( $sections as $sec ) {
			++$i;
			$type     = isset( $sec['type'] ) ? $sec['type'] : '';
			$label    = isset( $sec['label'] ) ? $sec['label'] : '';
			$html     = isset( $sec['html'] ) ? $sec['html'] : '';
			$panel_id = $uid . '-s' . $i;
			$class    = 'llm-phrase-game__grammar-section';
			if ( $type ) {
				$class .= ' llm-phrase-game__grammar-section--' . sanitize_html_class( $type );
			}
			echo '<div class="' . esc_attr( $class ) . '">';
			echo '<button type="button" class="llm-phrase-game__grammar-section-toggle" aria-expanded="false" aria-controls="' . esc_attr( $panel_id ) . '">';
			echo '<span class="llm-phrase-game__grammar-section-text">' . esc_html( '📖 ' . $i . '. ' . $label ) . '</span>';
			echo '<span class="llm-phrase-game__tool-accordion-chevron" aria-hidden="true"></span>';
			echo '</button>';
			echo '<div class="llm-phrase-game__grammar-section-panel" id="' . esc_attr( $panel_id ) . '" hidden>';
			echo '<div class="llm-phrase-game__grammar-section-body">' . wp_kses_post( $html ) . '</div>';
			echo '</div></div>';
		}
		if ( trim( (string) $alt ) !== '' ) {
			++$i;
			$panel_id = $uid . '-alt';
			echo '<div class="llm-phrase-game__grammar-section llm-phrase-game__grammar-section--alt">';
			echo '<button type="button" class="llm-phrase-game__grammar-section-toggle" aria-expanded="false" aria-controls="' . esc_attr( $panel_id ) . '">';
			echo '<span class="llm-phrase-game__grammar-section-text">' . esc_html( '📖 ' . $i . '. ' . $alt_label ) . '</span>';
			echo '<span class="llm-phrase-game__tool-accordion-chevron" aria-hidden="true"></span>';
			echo '</button>';
			echo '<div class="llm-phrase-game__grammar-section-panel" id="' . esc_attr( $panel_id ) . '" hidden>';
			echo '<div class="llm-phrase-game__grammar-section-body">' . wp_kses_post( $alt ) . '</div>';
			echo '</div></div>';
		}
		echo '</div>';
	}

	private static function render_story_block( $cfg ) {
		$info     = self::story_info_for( $cfg );
		$phrases  = self::phrases_for( $info['id'] );
		$data     = self::compare_data_for( $cfg['data_file'] );
		$variants = self::model_variants_from( $data );
		$models   = self::models_meta_from( $data );
		$gen_at   = isset( $data['meta']['generated_at'] ) ? (string) $data['meta']['generated_at'] : '';
		$known    = $info['known'];
		$notes_head = ( 'en' === $known ) ? '📝 Translation tips' : '📝 Consigli sulla traduzione';
		?>
		<section class="llm-mc-story-block" id="llm-mc-story-<?php echo esc_attr( $cfg['key'] ); ?>">
			<div class="llm-mc-story-card">
				<div class="llm-mc-story-langs">
					<div class="llm-mc-story-lang"><?php echo esc_html( $info['i_know_line'] ); ?></div>
					<div class="llm-mc-story-lang"><?php echo esc_html( $info['im_learning_line'] ); ?></div>
				</div>
				<h2 class="llm-mc-story-title"><?php echo esc_html( $info['title'] ); ?></h2>
				<?php if ( $info['title_target'] ) : ?>
					<p class="llm-mc-story-title-target"><?php echo esc_html( $info['title_target'] ); ?></p>
				<?php endif; ?>
				<ul class="llm-mc-story-meta">
					<li><strong>ID live:</strong> <?php echo (int) $info['live_id']; ?></li>
					<li><strong>ID locale:</strong> <?php echo (int) $info['local_id']; ?></li>
					<li><strong>ID usato qui:</strong> <?php echo (int) $info['id']; ?></li>
					<li><strong>Slug:</strong> <?php echo esc_html( $info['slug'] ); ?></li>
					<li><strong>Lingue:</strong> <?php echo esc_html( $info['known'] . ' → ' . $info['target'] ); ?></li>
					<?php if ( $info['cefr'] ) : ?>
						<li><strong>Livello:</strong> <?php echo esc_html( $info['cefr'] ); ?></li>
					<?php endif; ?>
					<li><strong>Frasi in storia:</strong> <?php echo (int) $info['count']; ?></li>
					<li><strong>In questa pagina:</strong> prime 2 frasi (sort_order 0–1)</li>
					<?php if ( $gen_at ) : ?>
						<li><strong>Generato:</strong> <?php echo esc_html( $gen_at ); ?></li>
					<?php endif; ?>
				</ul>
				<?php if ( $info['excerpt'] ) : ?>
					<p class="llm-mc-excerpt"><?php echo esc_html( $info['excerpt'] ); ?></p>
				<?php endif; ?>
				<p class="description">Solo visualizzazione. I DOPO sono generati dai modelli elencati nelle colonne (stesso prompt di controllo qualità). Non vengono salvati sul database delle frasi.</p>
				<?php
				$phrases_panel_id = 'llm-mc-phrases-' . $cfg['key'];
				?>
				<p class="llm-mc-story-actions">
					<button
						type="button"
						class="button button-primary llm-mc-phrases-toggle"
						aria-expanded="false"
						aria-controls="<?php echo esc_attr( $phrases_panel_id ); ?>"
						data-label-show="<?php echo esc_attr__( 'Visualizza frasi', 'llm-con-tabelle' ); ?>"
						data-label-hide="<?php echo esc_attr__( 'Nascondi frasi', 'llm-con-tabelle' ); ?>"
					>
						<?php echo esc_html__( 'Visualizza frasi', 'llm-con-tabelle' ); ?>
					</button>
				</p>
			</div>

			<?php if ( empty( $phrases ) ) : ?>
				<div class="notice notice-error"><p>Nessuna frase trovata per la storia <?php echo (int) $info['id']; ?>.</p></div>
			<?php endif; ?>

			<div class="llm-mc-phrases" id="<?php echo esc_attr( $phrases_panel_id ); ?>" hidden>
			<?php
			foreach ( $phrases as $phrase ) :
				$ix       = (int) $phrase['sort_order'];
				$before   = self::split_grammar_sections( $phrase['grammar'], $known );
				$alt      = $phrase['alt'];
				$model_ix = isset( $variants[ $ix ] ) ? $variants[ $ix ] : ( isset( $variants[ (string) $ix ] ) ? $variants[ (string) $ix ] : array() );
				?>
				<section class="llm-mc-phrase">
					<header class="llm-mc-phrase__head">
						<h2>
							Frase <?php echo (int) ( $ix + 1 ); ?>
							<span class="llm-mc-phrase__pair">
								“<?php echo esc_html( $phrase['interface'] ); ?>”
								→
								“<?php echo esc_html( $phrase['target'] ); ?>”
							</span>
						</h2>
						<p class="llm-mc-phrase__meta">sort_order <?php echo (int) $ix; ?></p>
					</header>

					<div class="llm-mc-block llm-mc-block--before">
						<h3>PRIMA <span>(DB attuale)</span></h3>
						<div class="llm-mc-panel llm-phrase-game">
							<div class="llm-mc-notes-head"><?php echo esc_html( $notes_head ); ?></div>
							<?php self::render_accordion( $before, $alt, $cfg['key'] . '-before-' . $ix, $known ); ?>
						</div>
					</div>

					<div class="llm-mc-after-grid">
						<?php
						foreach ( $models as $key => $model ) :
							$html      = isset( $model_ix[ $key ] ) ? (string) $model_ix[ $key ] : '';
							$secs      = $html ? self::split_grammar_sections( $html, $known ) : array();
							$prompt_id = 'llm-mc-prompt-' . $cfg['key'] . '-' . $key . '-' . $ix;
							?>
							<div class="llm-mc-block llm-mc-block--after llm-mc-block--<?php echo esc_attr( $key ); ?>">
								<div class="llm-mc-model-card">
									<h3>DOPO · <?php echo esc_html( $model['label'] ); ?></h3>
									<ul class="llm-mc-model-meta">
										<li><strong>Slug Cursor:</strong> <code><?php echo esc_html( $model['slug'] ); ?></code></li>
										<li><strong>Provider:</strong> <?php echo esc_html( $model['provider'] ); ?></li>
										<?php if ( $model['notes'] ) : ?>
											<li><?php echo esc_html( $model['notes'] ); ?></li>
										<?php endif; ?>
										<?php
										$usage = isset( $model['usage'] ) && is_array( $model['usage'] ) ? $model['usage'] : array();
										if ( $usage ) :
											$tok_in  = isset( $usage['tokens_in_est'] ) ? (int) $usage['tokens_in_est'] : 0;
											$tok_out = isset( $usage['tokens_out_est'] ) ? (int) $usage['tokens_out_est'] : 0;
											$tok_tot = isset( $usage['tokens_total_est'] ) ? (int) $usage['tokens_total_est'] : ( $tok_in + $tok_out );
											$chars_in  = isset( $usage['prompt_chars'] ) ? (int) $usage['prompt_chars'] : 0;
											$chars_out = isset( $usage['output_chars'] ) ? (int) $usage['output_chars'] : 0;
											?>
											<li class="llm-mc-usage">
												<strong>Utilizzo (stimato):</strong>
												~<?php echo (int) $tok_tot; ?> token totali
												(in ~<?php echo (int) $tok_in; ?> · out ~<?php echo (int) $tok_out; ?>)
												· prompt <?php echo number_format_i18n( $chars_in ); ?> char
												· output <?php echo number_format_i18n( $chars_out ); ?> char
											</li>
											<li class="llm-mc-usage-note">Stima locale ≈ caratteri ÷ 4 (Cursor non espone i token reali di questi subagent). Utile per confrontare le colonne, non per fatturazione esatta.</li>
										<?php endif; ?>
									</ul>
									<button type="button" class="button llm-mc-prompt-btn" data-prompt-target="<?php echo esc_attr( $prompt_id ); ?>">
										Vedi prompt usato
									</button>
									<dialog class="llm-mc-prompt-dialog" id="<?php echo esc_attr( $prompt_id ); ?>">
										<form method="dialog" class="llm-mc-prompt-dialog__inner">
											<header>
												<strong>Prompt — <?php echo esc_html( $model['label'] ); ?> · storia <?php echo esc_html( (string) $info['live_id'] ); ?></strong>
												<button type="submit" class="button" value="close" aria-label="Chiudi">Chiudi</button>
											</header>
											<pre class="llm-mc-prompt-pre"><?php echo esc_html( $model['prompt'] ? $model['prompt'] : '(prompt non disponibile)' ); ?></pre>
										</form>
									</dialog>
								</div>
								<div class="llm-mc-panel llm-phrase-game">
									<div class="llm-mc-notes-head"><?php echo esc_html( $notes_head ); ?></div>
									<?php if ( $html ) : ?>
										<?php self::render_accordion( $secs, $alt, $cfg['key'] . '-' . $key . '-' . $ix, $known ); ?>
									<?php else : ?>
										<p class="llm-mc-empty">Variante non ancora compilata.</p>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permesso negato.', 'llm-con-tabelle' ) );
		}
		?>
		<div class="wrap llm-mc-wrap">
			<h1><?php echo esc_html__( 'Confronto modelli', 'llm-con-tabelle' ); ?></h1>
			<p class="llm-mc-page-intro">Storie fisse · prime 2 frasi · PRIMA dal DB · DOPO da modelli reali (Grok / Gemini / Claude o ChatGPT a seconda della storia).</p>
			<nav class="llm-mc-toc">
				<?php foreach ( self::stories_config() as $cfg ) : ?>
					<a class="button" href="#llm-mc-story-<?php echo esc_attr( $cfg['key'] ); ?>">Storia <?php echo (int) $cfg['live_id']; ?></a>
				<?php endforeach; ?>
			</nav>
			<?php foreach ( self::stories_config() as $cfg ) : ?>
				<?php self::render_story_block( $cfg ); ?>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
