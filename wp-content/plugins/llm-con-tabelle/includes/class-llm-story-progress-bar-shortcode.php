<?php
/**
 * Shortcode: barra progresso frasi completate (utente corrente / storia).
 *
 * - `[llm_story_progress_bar]` — pagina singola storia o `story_id="…"`.
 * - `[llm_story_progress_bar compact="1"]` — traccia più sottile (es. Loop Elementor).
 * - `[llm_story_loop_progress]` — alias con compact attivo.
 *
 * L’ID storia senza `story_id` usa il post del loop (`get_the_ID` / `$post`),
 * non solo `get_queried_object_id()`.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_Story_Progress_Bar_Shortcode
 */
class LLM_Story_Progress_Bar_Shortcode {

	const SHORTCODE = 'llm_story_progress_bar';

	/**
	 * Avvio.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_shortcode( 'llm_story_loop_progress', array( __CLASS__, 'render_loop_compact' ) );
	}

	/**
	 * Alias per loop: traccia più sottile.
	 *
	 * @param array<string, string>|string $atts Attributi.
	 * @return string
	 */
	public static function render_loop_compact( $atts ) {
		$atts            = is_array( $atts ) ? $atts : array();
		$atts['compact'] = '1';
		return self::render( $atts );
	}

	/**
	 * ID storia nel contesto corrente (singola storia, Loop Elementor, widget).
	 *
	 * @param int $from_att story_id dallo shortcode (0 = auto).
	 * @return int
	 */
	private static function resolve_story_id( $from_att ) {
		$from_att = absint( $from_att );
		if ( $from_att && LLM_STORY_CPT === get_post_type( $from_att ) && 'publish' === get_post_status( $from_att ) ) {
			return $from_att;
		}

		$candidates = array();
		if ( in_the_loop() ) {
			$tid = (int) get_the_ID();
			if ( $tid ) {
				$candidates[] = $tid;
			}
		}
		if ( isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof WP_Post ) {
			$candidates[] = (int) $GLOBALS['post']->ID;
		}
		$tid = (int) get_the_ID();
		if ( $tid ) {
			$candidates[] = $tid;
		}
		$qid = (int) get_queried_object_id();
		if ( $qid ) {
			$candidates[] = $qid;
		}

		foreach ( array_unique( array_filter( $candidates ) ) as $sid ) {
			if ( LLM_STORY_CPT === get_post_type( $sid ) && 'publish' === get_post_status( $sid ) ) {
				return $sid;
			}
		}

		return 0;
	}

	/**
	 * @param mixed $v Valore attributo compact.
	 * @return bool
	 */
	private static function is_compact( $v ) {
		$v = is_string( $v ) ? strtolower( trim( $v ) ) : '';
		return in_array( $v, array( '1', 'yes', 'true', 'compact' ), true );
	}

	/**
	 * Script e stile (una volta per richiesta).
	 */
	private static function enqueue_assets() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		wp_enqueue_style(
			'llm-story-progress-bar',
			LLM_TABELLE_URL . 'assets/llm-story-progress-bar.css',
			array(),
			LLM_TABELLE_VERSION
		);
		wp_enqueue_script(
			'llm-story-progress-bar',
			LLM_TABELLE_URL . 'assets/llm-story-progress-bar.js',
			array(),
			LLM_TABELLE_VERSION,
			true
		);
		wp_localize_script(
			'llm-story-progress-bar',
			'llmStoryProgressBar',
			array(
				'i18n' => array(
					'sr' => LLM_Phrase_Game_I18n::get( 'story_progress_sr' ),
				),
			)
		);
	}

	/**
	 * @param array<string, string>|string $atts Attributi.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'story_id' => '',
				'compact'  => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE
		);

		$compact  = self::is_compact( $atts['compact'] );
		$story_id = self::resolve_story_id( $atts['story_id'] );

		if ( ! $story_id || LLM_STORY_CPT !== get_post_type( $story_id ) || 'publish' !== get_post_status( $story_id ) ) {
			return '';
		}

		$phrases = LLM_Story_Repository::get_phrases( $story_id );
		$total   = count( $phrases );
		if ( $total < 1 ) {
			return '';
		}

		self::enqueue_assets();

		$done = 0;
		if ( is_user_logged_in() ) {
			$done = LLM_Story_Game_Progress::bar_completed_count( get_current_user_id(), $story_id, $total );
		}

		$pct = $total > 0 ? (int) min( 100, round( ( 100 * $done ) / $total ) ) : 0;

		$logged_in = is_user_logged_in();
		$sr        = LLM_Phrase_Game_I18n::format( 'story_progress_sr', $done, $total );

		$wrap_classes = 'llm-story-progress-bar' . ( $compact ? ' llm-story-progress-bar--compact' : '' );

		ob_start();
		?>
		<div
			class="<?php echo esc_attr( $wrap_classes ); ?>"
			data-story-id="<?php echo esc_attr( (string) $story_id ); ?>"
		>
			<div class="llm-story-progress-bar__row">
				<div
					class="llm-story-progress-bar__track"
					role="progressbar"
					aria-valuemin="0"
					aria-valuenow="<?php echo esc_attr( (string) $done ); ?>"
					aria-valuemax="<?php echo esc_attr( (string) $total ); ?>"
					aria-label="<?php echo esc_attr( $sr ); ?>"
				>
					<div class="llm-story-progress-bar__fill" style="width: <?php echo esc_attr( (string) $pct ); ?>%;"></div>
				</div>
				<span class="llm-story-progress-bar__count"><?php echo esc_html( (string) $done . ' / ' . (string) $total ); ?></span>
			</div>
			<?php if ( ! $logged_in ) : ?>
				<p class="llm-story-progress-bar__guest-hint<?php echo $compact ? ' llm-story-progress-bar__guest-hint--compact' : ''; ?>">
					<?php
					echo esc_html(
						$compact
							? __( 'Accedi per salvare i progressi.', 'llm-con-tabelle' )
							: LLM_Phrase_Game_I18n::get( 'story_progress_guest' )
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
