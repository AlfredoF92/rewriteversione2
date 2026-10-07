<?php
/**
 * Motori STT (speech-to-text): browser di default, Azure opzionale, altri dopo.
 *
 * La chiave Azure resta sul server. Il browser riceve solo un token breve.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_STT {

	const OPTION     = 'llm_stt_settings';
	const PAGE_SLUG  = 'llm-microfoni';
	const AJAX       = 'llm_stt_token';
	const NONCE      = 'llm_stt_token';
	const SDK_URL    = 'https://cdn.jsdelivr.net/npm/microsoft-cognitiveservices-speech-sdk@1.42.0/distrib/browser/microsoft.cognitiveservices.speech.sdk.bundle-min.js';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_post' ) );
		add_action( 'wp_ajax_' . self::AJAX, array( __CLASS__, 'ajax_token' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX, array( __CLASS__, 'ajax_token' ) );
		add_action( 'wp_ajax_llm_stt_transcribe', array( __CLASS__, 'ajax_transcribe' ) );
		add_action( 'wp_ajax_nopriv_llm_stt_transcribe', array( __CLASS__, 'ajax_transcribe' ) );
	}

	public static function defaults() {
		return array(
			'provider'     => 'browser',
			'azure_key'    => '',
			'azure_region' => 'westeurope',
			'deepgram_key' => '',
		);
	}

	public static function settings() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::defaults(), $saved );
	}

	public static function provider() {
		$s = self::settings();
		$p = isset( $s['provider'] ) ? sanitize_key( $s['provider'] ) : 'browser';
		if ( 'azure' === $p && self::azure_ready() ) {
			return 'azure';
		}
		if ( 'deepgram' === $p && self::deepgram_ready() ) {
			return 'deepgram';
		}
		return 'browser';
	}

	public static function azure_ready() {
		$s = self::settings();
		return ( '' !== trim( (string) $s['azure_key'] ) && '' !== trim( (string) $s['azure_region'] ) );
	}

	public static function deepgram_ready() {
		$s = self::settings();
		return ( '' !== trim( (string) $s['deepgram_key'] ) );
	}

	private static function posted_secret( $field, $current ) {
		$in = isset( $_POST[ $field ] ) ? trim( (string) wp_unslash( $_POST[ $field ] ) ) : '';
		if ( '' === $in || 0 === strpos( $in, '••••' ) ) {
			return $current;
		}
		return sanitize_text_field( $in );
	}

	public static function frontend_config() {
		return array(
			'provider' => self::provider(),
			'region'   => sanitize_key( self::settings()['azure_region'] ),
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( self::NONCE ),
			'sdkUrl'         => self::SDK_URL,
			'azureReady'     => self::azure_ready(),
			'deepgramReady'  => self::deepgram_ready(),
		);
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . LLM_STORY_CPT,
			__( 'Microfoni IA', 'llm-con-tabelle' ),
			__( 'Microfoni IA', 'llm-con-tabelle' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	public static function handle_post() {
		if ( ! isset( $_POST['llm_stt_save'] ) || ! isset( $_POST['_wpnonce'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permesso negato.', 'llm-con-tabelle' ) );
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'llm_stt_save' ) ) {
			return;
		}

		$cur  = self::settings();
		$prov = isset( $_POST['llm_stt_provider'] ) ? sanitize_key( wp_unslash( $_POST['llm_stt_provider'] ) ) : 'browser';
		if ( ! in_array( $prov, array( 'browser', 'azure', 'deepgram' ), true ) ) {
			$prov = 'browser';
		}
		$region = isset( $_POST['llm_stt_azure_region'] ) ? sanitize_key( wp_unslash( $_POST['llm_stt_azure_region'] ) ) : 'westeurope';
		if ( '' === $region ) {
			$region = 'westeurope';
		}

		update_option(
			self::OPTION,
			array(
				'provider'     => $prov,
				'azure_key'    => self::posted_secret( 'llm_stt_azure_key', $cur['azure_key'] ),
				'azure_region' => $region,
				'deepgram_key' => self::posted_secret( 'llm_stt_deepgram_key', $cur['deepgram_key'] ),
			),
			false
		);
		delete_transient( 'llm_stt_azure_token' );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => LLM_STORY_CPT,
					'page'      => self::PAGE_SLUG,
					'updated'   => '1',
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	private static function bump_rate_limit() {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '0';
		$rlk = 'llm_stt_rl_' . md5( $ip );
		$n   = (int) get_transient( $rlk );
		if ( $n > 80 ) {
			wp_send_json_error( array( 'code' => 'rate' ), 429 );
		}
		set_transient( $rlk, $n + 1, HOUR_IN_SECONDS );
	}

	private static function deepgram_language( $raw ) {
		$raw = sanitize_text_field( (string) $raw );
		$map = array(
			'en'    => 'en-US',
			'en-us' => 'en-US',
			'en-gb' => 'en-GB',
			'it'    => 'it',
			'it-it' => 'it',
			'es'    => 'es',
			'es-es' => 'es',
			'pl'    => 'pl',
			'pl-pl' => 'pl',
		);
		$key = strtolower( $raw );
		return isset( $map[ $key ] ) ? $map[ $key ] : 'en-US';
	}

	public static function ajax_token() {
		check_ajax_referer( self::NONCE, 'nonce' );
		$engine = isset( $_POST['engine'] ) ? sanitize_key( wp_unslash( $_POST['engine'] ) ) : self::provider();
		if ( 'azure' !== $engine && 'deepgram' !== $engine ) {
			$engine = self::provider();
		}

		self::bump_rate_limit();

		if ( 'deepgram' === $engine ) {
			self::ajax_deepgram_token();
			return;
		}
		if ( 'azure' === $engine ) {
			self::ajax_azure_token();
			return;
		}
		wp_send_json_error( array( 'code' => 'disabled' ), 400 );
	}

	private static function ajax_azure_token() {
		if ( ! self::azure_ready() ) {
			wp_send_json_error( array( 'code' => 'disabled' ), 400 );
		}
		$cached = get_transient( 'llm_stt_azure_token' );
		if ( is_array( $cached ) && ! empty( $cached['token'] ) ) {
			wp_send_json_success( $cached );
		}

		$s      = self::settings();
		$region = sanitize_key( $s['azure_region'] );
		$key    = (string) $s['azure_key'];
		$url    = 'https://' . $region . '.api.cognitive.microsoft.com/sts/v1.0/issueToken';
		$res    = wp_remote_post(
			$url,
			array(
				'timeout' => 12,
				'headers' => array(
					'Ocp-Apim-Subscription-Key' => $key,
					'Content-Length'            => '0',
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'code' => 'network' ), 502 );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = trim( (string) wp_remote_retrieve_body( $res ) );
		if ( $code < 200 || $code >= 300 || '' === $body ) {
			wp_send_json_error( array( 'code' => 'azure' ), 502 );
		}

		$payload = array(
			'token'  => $body,
			'region' => $region,
		);
		set_transient( 'llm_stt_azure_token', $payload, 8 * MINUTE_IN_SECONDS );
		wp_send_json_success( $payload );
	}

	private static function ajax_deepgram_token() {
		if ( ! self::deepgram_ready() ) {
			wp_send_json_error( array( 'code' => 'disabled' ), 400 );
		}
		$s   = self::settings();
		$key = (string) $s['deepgram_key'];
		$res = wp_remote_post(
			'https://api.deepgram.com/v1/auth/grant',
			array(
				'timeout' => 12,
				'headers' => array(
					'Authorization' => 'Token ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'ttl_seconds' => 60 ) ),
			)
		);
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'code' => 'network' ), 502 );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$raw  = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 || empty( $raw['access_token'] ) ) {
			wp_send_json_error( array( 'code' => 'deepgram' ), 502 );
		}
		wp_send_json_success(
			array(
				'token' => (string) $raw['access_token'],
			)
		);
	}

	public static function ajax_transcribe() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! self::deepgram_ready() ) {
			wp_send_json_error( array( 'code' => 'disabled' ), 400 );
		}
		self::bump_rate_limit();
		if ( empty( $_FILES['audio'] ) || ! is_array( $_FILES['audio'] ) ) {
			wp_send_json_error( array( 'code' => 'file' ), 400 );
		}
		$err = isset( $_FILES['audio']['error'] ) ? (int) $_FILES['audio']['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $err ) {
			wp_send_json_error( array( 'code' => 'file' ), 400 );
		}
		$size = isset( $_FILES['audio']['size'] ) ? (int) $_FILES['audio']['size'] : 0;
		if ( $size < 80 || $size > 3 * 1024 * 1024 ) {
			wp_send_json_error( array( 'code' => 'size' ), 400 );
		}
		$tmp = isset( $_FILES['audio']['tmp_name'] ) ? (string) $_FILES['audio']['tmp_name'] : '';
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			wp_send_json_error( array( 'code' => 'file' ), 400 );
		}
		$bytes = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_string( $bytes ) || strlen( $bytes ) < 80 ) {
			wp_send_json_error( array( 'code' => 'file' ), 400 );
		}
		$ctype = isset( $_FILES['audio']['type'] ) ? strtolower( (string) $_FILES['audio']['type'] ) : '';
		if ( ! preg_match( '#^audio/(webm|mp4|mpeg|ogg|wav|x-wav|mp3)(;.*)?$#', $ctype ) ) {
			$ctype = 'audio/webm';
		}
		$lang = self::deepgram_language( isset( $_POST['lang'] ) ? wp_unslash( $_POST['lang'] ) : 'en-US' );
		$key  = (string) self::settings()['deepgram_key'];
		$url  = add_query_arg(
			array(
				'model'        => 'nova-2',
				'smart_format' => 'true',
				'punctuate'    => 'true',
				'language'     => $lang,
			),
			'https://api.deepgram.com/v1/listen'
		);
		$res  = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Token ' . $key,
					'Content-Type'  => $ctype,
				),
				'body'    => $bytes,
			)
		);
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'code' => 'network' ), 502 );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$raw  = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $raw ) ) {
			wp_send_json_error( array( 'code' => 'deepgram' ), 502 );
		}
		$text = '';
		if ( isset( $raw['results']['channels'][0]['alternatives'][0]['transcript'] ) ) {
			$text = trim( (string) $raw['results']['channels'][0]['alternatives'][0]['transcript'] );
		}
		wp_send_json_success( array( 'text' => $text ) );
	}

	public static function regions() {
		return array(
			'westeurope'         => 'West Europe',
			'swedencentral'      => 'Sweden Central',
			'northeurope'        => 'North Europe',
			'francecentral'      => 'France Central',
			'germanywestcentral' => 'Germany West Central',
			'uksouth'            => 'UK South',
			'eastus'             => 'East US',
			'westus'             => 'West US',
			'eastus2'            => 'East US 2',
		);
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permesso negato.', 'llm-con-tabelle' ) );
		}
		$s        = self::settings();
		$az_key   = (string) $s['azure_key'];
		$dg_key   = (string) $s['deepgram_key'];
		$az_mask  = '' === $az_key ? '' : '••••' . substr( $az_key, -4 );
		$dg_mask  = '' === $dg_key ? '' : '••••' . substr( $dg_key, -4 );
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Microfoni IA', 'llm-con-tabelle' ) . '</h1>';
		if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Impostazioni salvate.', 'llm-con-tabelle' ) . '</p></div>';
		}
		echo '<p>' . esc_html__( 'Il microfono del browser resta gratis. Azure e Deepgram sono i motori IA; altri si aggiungono qui senza cambiare il gioco frasi. La scelta per lo studente sul frontend arriva dopo.', 'llm-con-tabelle' ) . '</p>';
		echo '<p>' . esc_html__( 'Le chiavi API non vanno mai nel browser: gli studenti usano un token temporaneo emesso dal sito.', 'llm-con-tabelle' ) . '</p>';
		echo '<form method="post">';
		wp_nonce_field( 'llm_stt_save' );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Motore attivo', 'llm-con-tabelle' ) . '</th><td>';
		echo '<select name="llm_stt_provider">';
		echo '<option value="browser"' . selected( $s['provider'], 'browser', false ) . '>' . esc_html__( 'Browser (gratis)', 'llm-con-tabelle' ) . '</option>';
		echo '<option value="azure"' . selected( $s['provider'], 'azure', false ) . '>' . esc_html__( 'Azure AI Speech', 'llm-con-tabelle' ) . '</option>';
		echo '<option value="deepgram"' . selected( $s['provider'], 'deepgram', false ) . '>' . esc_html__( 'Deepgram', 'llm-con-tabelle' ) . '</option>';
		echo '</select></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Area Azure', 'llm-con-tabelle' ) . '</th><td>';
		echo '<select name="llm_stt_azure_region">';
		foreach ( self::regions() as $code => $label ) {
			echo '<option value="' . esc_attr( $code ) . '"' . selected( $s['azure_region'], $code, false ) . '>' . esc_html( $label . ' (' . $code . ')' ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Chiave Azure Speech', 'llm-con-tabelle' ) . '</th><td>';
		echo '<input type="password" class="regular-text" name="llm_stt_azure_key" value="' . esc_attr( $az_mask ) . '" autocomplete="new-password" />';
		echo '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Chiave Deepgram', 'llm-con-tabelle' ) . '</th><td>';
		echo '<input type="password" class="regular-text" name="llm_stt_deepgram_key" value="' . esc_attr( $dg_mask ) . '" autocomplete="new-password" />';
		echo '<p class="description">' . esc_html__( 'Lascia il campo mascherato per non cambiare una chiave già salvata.', 'llm-con-tabelle' ) . '</p>';
		echo '</td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Salva', 'llm-con-tabelle' ), 'primary', 'llm_stt_save' );
		echo '</form></div>';
	}
}
