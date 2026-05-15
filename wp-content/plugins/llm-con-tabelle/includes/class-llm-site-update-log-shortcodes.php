<?php
/**
 * Data ultimo upload in Media e log modifiche manuale (file di testo).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Site_Update_Log_Shortcodes {

	const OPT_LAST_UPLOAD_UNIX  = 'llm_last_media_upload_unix';
	const DEPLOY_TIMESTAMP_FILE = 'llm-last-deploy.txt'; // scritto dal PowerShell dopo ogni FTP.

	const SHORTCODE_LAST_UPLOAD = 'llm_last_upload_date';
	const SHORTCODE_CHANGELOG   = 'llm_changelog';

	/**
	 * Avvio.
	 */
	public static function init() {
		add_action( 'add_attachment', array( __CLASS__, 'on_new_attachment' ) );
		add_action( 'init', array( __CLASS__, 'maybe_bootstrap_upload_timestamp' ), 30 );

		add_shortcode( self::SHORTCODE_LAST_UPLOAD, array( __CLASS__, 'render_last_upload_date' ) );
		add_shortcode( self::SHORTCODE_CHANGELOG, array( __CLASS__, 'render_changelog' ) );
	}

	/**
	 * Ogni nuovo file in Libreria media aggiorna il timestamp.
	 *
	 * @param int $post_id ID attachment.
	 */
	public static function on_new_attachment( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}
		update_option( self::OPT_LAST_UPLOAD_UNIX, time(), false );
	}

	/**
	 * Alla prima installazione / opzione assente, usa la data del file più recente in uploads/.
	 */
	public static function maybe_bootstrap_upload_timestamp() {
		// Opzione assente in DB: get_option restituisce false.
		if ( false !== get_option( self::OPT_LAST_UPLOAD_UNIX ) ) {
			return;
		}
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) || empty( $upload_dir['basedir'] ) ) {
			return;
		}
		$max = self::max_mtime_in_directory( $upload_dir['basedir'] );
		if ( $max > 0 ) {
			update_option( self::OPT_LAST_UPLOAD_UNIX, $max, false );
		}
	}

	/**
	 * @param string $dir Percorso assoluto.
	 * @return int Unix timestamp massimo (file), 0 se vuoto.
	 */
	private static function max_mtime_in_directory( $dir ) {
		if ( ! is_dir( $dir ) || ! is_readable( $dir ) ) {
			return 0;
		}
		$max = 0;
		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ( $iterator as $file ) {
				if ( $file->isFile() ) {
					$max = max( $max, (int) $file->getMTime() );
				}
			}
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			return $max;
		}
		return $max;
	}

	/**
	 * Timestamp ultimo upload (opzione o 0).
	 *
	 * @return int
	 */
	public static function get_last_upload_unix() {
		$t = get_option( self::OPT_LAST_UPLOAD_UNIX, false );
		if ( false === $t || '' === $t ) {
			return 0;
		}
		return is_numeric( $t ) ? (int) $t : 0;
	}

	/**
	 * Percorso del file di deploy timestamp.
	 *
	 * @return string
	 */
	public static function deploy_timestamp_path() {
		return LLM_TABELLE_DIR . 'assets/' . self::DEPLOY_TIMESTAMP_FILE;
	}

	/**
	 * Timestamp dell'ultimo deploy FTP.
	 * Letto dal file llm-last-deploy.txt scritto dallo script PowerShell dopo ogni upload.
	 *
	 * @return int Unix timestamp, 0 se il file non esiste ancora.
	 */
	public static function get_last_ftp_unix() {
		$path = self::deploy_timestamp_path();
		if ( ! is_readable( $path ) ) {
			return 0;
		}
		$raw = trim( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_numeric( $raw ) ? (int) $raw : 0;
	}

	/**
	 * Unix timestamp ultima modifica del file di log (stesso file letto da [llm_changelog]).
	 *
	 * @return int 0 se il file non esiste o non è leggibile.
	 */
	public static function get_changelog_mtime_unix() {
		$path = self::default_changelog_path();
		if ( ! is_readable( $path ) ) {
			return 0;
		}
		$m = @filemtime( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return false !== $m ? (int) $m : 0;
	}

	/**
	 * Timestamp da mostrare in base alla sorgente.
	 *
	 * - ftp        (default) timestamp scritto dallo script PowerShell dopo ogni caricamento FTP.
	 * - auto       il più recente tra ftp, Media e salvataggio file changelog.
	 * - media      solo ultimo upload in Libreria media WordPress.
	 * - changelog  solo data/ora salvataggio file llm-changelog.txt.
	 *
	 * @param string $source Valore attributo shortcode.
	 * @return int
	 */
	public static function get_effective_activity_unix( $source ) {
		$source    = strtolower( trim( (string) $source ) );
		$ftp       = self::get_last_ftp_unix();
		$media     = self::get_last_upload_unix();
		$changelog = self::get_changelog_mtime_unix();

		if ( 'media' === $source ) {
			$unix = $media;
		} elseif ( 'changelog' === $source ) {
			$unix = $changelog;
		} elseif ( 'auto' === $source ) {
			$unix = max( $ftp, $media, $changelog );
		} else {
			// ftp (default)
			$unix = $ftp > 0 ? $ftp : max( $media, $changelog );
		}

		return (int) apply_filters( 'llm_last_upload_date_unix', $unix, $source, $ftp, $media, $changelog );
	}

	/**
	 * Percorso file changelog (modificabile via FTP / deploy).
	 *
	 * @return string
	 */
	public static function default_changelog_path() {
		/**
		 * Filtra il percorso del file di log modifiche.
		 *
		 * @param string $path Percorso assoluto.
		 */
		return apply_filters(
			'llm_changelog_file_path',
			LLM_TABELLE_DIR . 'assets/llm-changelog.txt'
		);
	}

	/**
	 * Shortcode: data/ora ultimo file caricato in Media.
	 *
	 * Attributi:
	 * - format   formato PHP per date_i18n (default dalla data del sito + ora).
	 * - prefix   testo prima della data (es. "Aggiornamento contenuti: ").
	 * - wrapper  tag wrapper (default span).
	 * - class    classi CSS (default llm-last-upload-date).
	 * - link     yes/no — la data è un link alla pagina log (default yes).
	 * - log_url  URL pagina log (default home_url( '/log/' )).
	 * - source   auto|media|changelog — auto = più recente tra Media e salvataggio file log (default auto).
	 *            Non è l’ora corrente: usa il fuso orario in Impostazioni → Generali di WordPress.
	 *
	 * @param array<string, string>|string $atts Attributi.
	 * @return string
	 */
	public static function render_last_upload_date( $atts ) {
		wp_enqueue_style( 'llm-ui' );
		wp_enqueue_style(
			'llm-site-update-log',
			LLM_TABELLE_URL . 'assets/llm-site-update-log.css',
			array( 'llm-ui' ),
			LLM_TABELLE_VERSION
		);

		$atts = shortcode_atts(
			array(
				'format'  => '',
				'prefix'  => '',
				'wrapper' => 'span',
				'class'   => 'llm-last-upload-date',
				'link'    => 'yes',
				'log_url' => '',
				'source'  => 'ftp',
			),
			$atts,
			self::SHORTCODE_LAST_UPLOAD
		);

		$unix = self::get_effective_activity_unix( (string) $atts['source'] );
		if ( $unix <= 0 ) {
			return '';
		}

		$format = trim( (string) $atts['format'] );
		if ( '' === $format ) {
			$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		}

		$date_str = date_i18n( $format, $unix );

		$prefix = trim( (string) $atts['prefix'] );
		$time_html = '<time class="llm-last-upload-date__time" datetime="' . esc_attr( gmdate( 'c', $unix ) ) . '">' . esc_html( $date_str ) . '</time>';

		$use_link = 'yes' === strtolower( trim( (string) $atts['link'] ) );
		if ( $use_link ) {
			$log_href = trim( (string) $atts['log_url'] );
			if ( '' === $log_href ) {
				$log_href = home_url( '/log/' );
			}
			$time_html = sprintf(
				'<a class="llm-last-upload-date__link" href="%s">%s</a>',
				esc_url( $log_href ),
				$time_html
			);
		}

		$inner = ( '' !== $prefix ? '<span class="llm-last-upload-date__prefix">' . esc_html( $prefix ) . ' </span>' : '' ) . $time_html;

		$tag = strtolower( preg_replace( '/[^a-z0-9]/', '', (string) $atts['wrapper'] ) );
		if ( '' === $tag || ! preg_match( '/^(span|p|div)$/', $tag ) ) {
			$tag = 'span';
		}

		$class = sanitize_html_class( (string) $atts['class'] );

		return sprintf(
			'<%1$s class="%2$s">%3$s</%1$s>',
			esc_attr( $tag ),
			esc_attr( $class ),
			$inner
		);
	}

	/**
	 * Shortcode: log modifiche da file di testo + data ultima modifica file.
	 *
	 * Attributi:
	 * - file           nome file solo dentro assets/ del plugin (default llm-changelog.txt).
	 * - show_meta      yes/no — mostra data/ora ultimo salvataggio del file (default yes).
	 * - meta_prefix    etichetta prima della data del file (tradotto).
	 * - class          classe wrapper aggiuntiva.
	 *
	 * @param array<string, string>|string $atts Attributi.
	 * @return string
	 */
	public static function render_changelog( $atts ) {
		wp_enqueue_style( 'llm-ui' );
		wp_enqueue_style(
			'llm-site-update-log',
			LLM_TABELLE_URL . 'assets/llm-site-update-log.css',
			array( 'llm-ui' ),
			LLM_TABELLE_VERSION
		);

		$atts = shortcode_atts(
			array(
				'file'        => 'llm-changelog.txt',
				'show_meta'   => 'yes',
				'meta_prefix' => '',
				'class'       => '',
			),
			$atts,
			self::SHORTCODE_CHANGELOG
		);

		$basename = sanitize_file_name( (string) $atts['file'] );
		if ( '' === $basename ) {
			$basename = 'llm-changelog.txt';
		}

		if ( 'llm-changelog.txt' === $basename ) {
			$path = self::default_changelog_path();
		} else {
			$path = LLM_TABELLE_DIR . 'assets/' . $basename;
		}
		/**
		 * Filtra il percorso risolto (es. copia del log in wp-content/uploads).
		 *
		 * @param string $path     Percorso assoluto.
		 * @param string $basename Nome file richiesto.
		 */
		$path = apply_filters( 'llm_changelog_resolved_path', $path, $basename );

		if ( ! is_readable( $path ) ) {
			return '';
		}

		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $raw ) {
			return '';
		}

		$mtime = @filemtime( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $mtime ) {
			$mtime = 0;
		}

		$meta_html = '';
		if ( 'yes' === strtolower( (string) $atts['show_meta'] ) && $mtime > 0 ) {
			$prefix = trim( (string) $atts['meta_prefix'] );
			if ( '' === $prefix ) {
				$prefix = __( 'Ultimo aggiornamento del log:', 'llm-con-tabelle' );
			}
			$df       = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
			$meta_str = date_i18n( $df, $mtime );
			$meta_html = sprintf(
				'<p class="llm-changelog__meta"><span class="llm-changelog__meta-label">%s</span> <time class="llm-changelog__meta-time" datetime="%s">%s</time></p>',
				esc_html( $prefix ),
				esc_attr( gmdate( 'c', $mtime ) ),
				esc_html( $meta_str )
			);
		}

		$content = wp_kses_post( wpautop( trim( $raw ) ) );

		$extra_class = sanitize_html_class( (string) $atts['class'] );

		return sprintf(
			'<div class="llm-changelog %1$s">%2$s<div class="llm-changelog__body">%3$s</div></div>',
			esc_attr( trim( 'llm-changelog--from-file ' . $extra_class ) ),
			$meta_html,
			$content
		);
	}
}
