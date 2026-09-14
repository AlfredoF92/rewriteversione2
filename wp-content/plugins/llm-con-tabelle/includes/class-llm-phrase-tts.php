<?php
/**
 * Audio IA delle frasi: Deepgram Aura-2 sul sito (EN/IT/ES); Azure Neural in più, solo wp-admin.
 * Per lingue senza Aura-2 (es. polacco) il pubblico usa Azure.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Phrase_TTS {

	const AJAX  = 'llm_story_tts_phrase';
	const NONCE = 'llm_story_tts_phrase';

	public static function init() {
		add_action( 'wp_ajax_' . self::AJAX, array( __CLASS__, 'ajax_phrase' ) );
	}

	/**
	 * @param string $locale BCP-47 (it-IT, en-US, …).
	 * @return array{male:string,female:string}
	 */
	public static function voices_for_locale( $locale ) {
		$locale = (string) $locale;
		$map    = array(
			'it-IT' => array(
				'male'   => 'it-IT-GiuseppeNeural',
				'female' => 'it-IT-IsabellaNeural',
			),
			'en-US' => array(
				'male'   => 'en-US-GuyNeural',
				'female' => 'en-US-JennyNeural',
			),
			'en-GB' => array(
				'male'   => 'en-GB-RyanNeural',
				'female' => 'en-GB-SoniaNeural',
			),
			'es-ES' => array(
				'male'   => 'es-ES-AlvaroNeural',
				'female' => 'es-ES-ElviraNeural',
			),
			'pl-PL' => array(
				'male'   => 'pl-PL-MarekNeural',
				'female' => 'pl-PL-ZofiaNeural',
			),
		);
		if ( isset( $map[ $locale ] ) ) {
			return $map[ $locale ];
		}
		$short = strtolower( substr( $locale, 0, 2 ) );
		$by    = array(
			'it' => 'it-IT',
			'en' => 'en-US',
			'es' => 'es-ES',
			'pl' => 'pl-PL',
		);
		$key = isset( $by[ $short ] ) ? $by[ $short ] : 'en-US';
		return $map[ $key ];
	}

	/**
	 * @param int $attachment_id ID allegato.
	 * @return string
	 */
	public static function url( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return '';
		}
		$url = wp_get_attachment_url( $attachment_id );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * @param int $male_id         Allegato maschile (sito).
	 * @param int $female_id       Allegato femminile (sito).
	 * @param int $azure_male_id     Allegato Azure maschile (admin).
	 * @param int $azure_female_id   Allegato Azure femminile (admin).
	 * @param int $notes_female_id   Allegato Azure note storia.
	 */
	public static function delete_attachments( $male_id, $female_id, $azure_male_id = 0, $azure_female_id = 0, $notes_female_id = 0 ) {
		$ids = array_unique(
			array_filter(
				array_map(
					'absint',
					array( $male_id, $female_id, $azure_male_id, $azure_female_id, $notes_female_id )
				)
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_attachment( $id, true );
		}
	}

	/**
	 * Pulsanti ascolto in wp-admin: nome voce (AURA-2-flavio-it, azure-giuseppe).
	 *
	 * @param string $locale BCP-47.
	 * @return array<int, array{play:string,label:string}>
	 */
	public static function admin_voice_buttons( $locale ) {
		$lang  = strtolower( substr( (string) $locale, 0, 2 ) );
		$azure = self::voices_for_locale( $locale );
		$out   = array();
		if ( self::deepgram_supports( $lang ) ) {
			$out[] = array(
				'play'  => 'aura-male',
				'label' => strtoupper( self::deepgram_model( $lang, 'male' ) ),
			);
			$out[] = array(
				'play'  => 'aura-female',
				'label' => strtoupper( self::deepgram_model( $lang, 'female' ) ),
			);
		}
		$out[] = array(
			'play'  => 'azure-male',
			'label' => 'azure-' . self::azure_short_name( $azure['male'] ),
		);
		$out[] = array(
			'play'  => 'azure-female',
			'label' => 'azure-' . self::azure_short_name( $azure['female'] ),
		);
		return $out;
	}

	/**
	 * URL ascolto admin per chiave play (aura-male, azure-female, …).
	 *
	 * @param array  $row    Riga frase.
	 * @param string $locale Locale.
	 * @return array<string,string>
	 */
	public static function admin_audio_urls( array $row, $locale ) {
		$lang = strtolower( substr( (string) $locale, 0, 2 ) );
		$aura = self::deepgram_supports( $lang );
		$pub_m = isset( $row['audio_male_url'] ) ? (string) $row['audio_male_url'] : '';
		$pub_f = isset( $row['audio_female_url'] ) ? (string) $row['audio_female_url'] : '';
		$az_m  = isset( $row['audio_azure_male_url'] ) ? (string) $row['audio_azure_male_url'] : '';
		$az_f  = isset( $row['audio_azure_female_url'] ) ? (string) $row['audio_azure_female_url'] : '';
		return array(
			'aura-male'     => $aura ? $pub_m : '',
			'aura-female'   => $aura ? $pub_f : '',
			'azure-male'    => $az_m ? $az_m : ( $aura ? '' : $pub_m ),
			'azure-female'  => $az_f ? $az_f : ( $aura ? '' : $pub_f ),
		);
	}

	/**
	 * URL dei due pulsanti in front-end: Azure se c’è, altrimenti Aura-2.
	 *
	 * @param array $row Riga frase.
	 * @return array{male:string,female:string}
	 */
	public static function frontend_audio_urls( array $row ) {
		$az_m  = isset( $row['audio_azure_male_url'] ) ? (string) $row['audio_azure_male_url'] : '';
		$az_f  = isset( $row['audio_azure_female_url'] ) ? (string) $row['audio_azure_female_url'] : '';
		$pub_m = isset( $row['audio_male_url'] ) ? (string) $row['audio_male_url'] : '';
		$pub_f = isset( $row['audio_female_url'] ) ? (string) $row['audio_female_url'] : '';
		return array(
			'male'   => $az_m ? $az_m : $pub_m,
			'female' => $az_f ? $az_f : $pub_f,
		);
	}

	/**
	 * @param string $voice Voce Azure (es. it-IT-GiuseppeNeural).
	 * @return string
	 */
	public static function azure_short_name( $voice ) {
		if ( preg_match( '/-([A-Za-z]+)Neural$/', (string) $voice, $m ) ) {
			return strtolower( $m[1] );
		}
		$slug = strtolower( preg_replace( '/[^a-z0-9]+/i', '-', (string) $voice ) );
		return trim( is_string( $slug ) ? $slug : '', '-' );
	}

	/**
	 * Testo parlato: niente HTML, spazi normali.
	 *
	 * @param string $html Frase target.
	 * @return string
	 */
	public static function plain_text( $html ) {
		$t = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$t = preg_replace( '/\s+/u', ' ', $t );
		return trim( is_string( $t ) ? $t : '' );
	}

	/**
	 * Punto finale se manca: evita che Deepgram/Azure taglino l’ultima sillaba.
	 *
	 * @param string $text Testo piano.
	 * @return string
	 */
	public static function padded_text( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return '';
		}
		if ( ! preg_match( '/[.!?…]$/u', $text ) ) {
			$text .= '.';
		}
		return $text;
	}

	public static function ajax_phrase() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permesso negato.', 'llm-con-tabelle' ) ), 403 );
		}
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 90 );
		}

		$story_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		$index    = isset( $_POST['index'] ) ? absint( wp_unslash( $_POST['index'] ) ) : 0;
		$force    = isset( $_POST['force'] ) && '1' === (string) wp_unslash( $_POST['force'] );
		$until    = isset( $_POST['until'] ) ? absint( wp_unslash( $_POST['until'] ) ) : 0;

		if ( ! $story_id || ! current_user_can( 'edit_post', $story_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Storia non valida.', 'llm-con-tabelle' ) ), 400 );
		}

		$post = get_post( $story_id );
		if ( ! $post || LLM_STORY_CPT !== $post->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Storia non valida.', 'llm-con-tabelle' ) ), 400 );
		}

		if ( ! class_exists( 'LLM_STT' ) || ( ! LLM_STT::deepgram_ready() && ! LLM_STT::azure_ready() ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Configura Deepgram o Azure Speech in Storie → Microfoni IA.', 'llm-con-tabelle' ),
				),
				400
			);
		}

		$phrases = LLM_Story_Repository::get_phrases( $story_id );
		$total   = count( $phrases );
		$limit   = ( $until > 0 ) ? min( $total, $until ) : $total;
		if ( $index >= $limit ) {
			wp_send_json_success(
				array(
					'done'    => true,
					'total'   => $total,
					'message' => sprintf(
						/* translators: %d: phrase count */
						__( 'Audio IA completato (%d frasi).', 'llm-con-tabelle' ),
						$limit
					),
				)
			);
		}

		$row = $phrases[ $index ];

		$target_code = (string) get_post_meta( $story_id, LLM_Story_Meta::TARGET_LANG, true );
		$locale      = class_exists( 'LLM_Story_Phrase_Game' )
			? LLM_Story_Phrase_Game::speech_locale( $target_code )
			: 'en-US';
		$lang        = strtolower( substr( $locale, 0, 2 ) );

		$has_public = ! empty( $row['audio_male_url'] ) && ! empty( $row['audio_female_url'] );
		$need_azure = class_exists( 'LLM_STT' ) && LLM_STT::azure_ready() && self::deepgram_supports( $lang );
		$has_azure  = ! empty( $row['audio_azure_male_url'] ) && ! empty( $row['audio_azure_female_url'] );
		if ( $has_public && ( ! $need_azure || $has_azure ) && ! $force ) {
			wp_send_json_success( self::progress_payload( $index, $limit, $row, true, $locale ) );
		}

		$result = self::generate_for_phrase( $story_id, (int) $row['id'], (string) $row['target'], $locale, $force );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: 1: phrase number, 2: error */
						__( 'Frase %1$d: %2$s', 'llm-con-tabelle' ),
						$index + 1,
						$result->get_error_message()
					),
				),
				502
			);
		}

		$row['audio_male_url']         = self::url( (int) $result['male'] );
		$row['audio_female_url']       = self::url( (int) $result['female'] );
		$row['audio_azure_male_url']   = self::url( isset( $result['azure_male'] ) ? (int) $result['azure_male'] : 0 );
		$row['audio_azure_female_url'] = self::url( isset( $result['azure_female'] ) ? (int) $result['azure_female'] : 0 );
		wp_send_json_success( self::progress_payload( $index, $limit, $row, false, $locale ) );
	}

	/**
	 * @param int   $index  Indice appena elaborato.
	 * @param int   $total  Totale frasi.
	 * @param array $row    Riga frase.
	 * @param bool  $skipped True se già presente.
	 * @return array<string,mixed>
	 */
	private static function progress_payload( $index, $total, array $row, $skipped, $locale = 'en-US' ) {
		$next = $index + 1;
		$done = $next >= $total;
		$n    = $index + 1;
		$urls = self::admin_audio_urls( $row, $locale );
		if ( $done ) {
			$msg = sprintf(
				/* translators: %d: phrase count */
				__( 'Audio IA completato (%d frasi).', 'llm-con-tabelle' ),
				$total
			);
		} elseif ( $skipped ) {
			$msg = sprintf(
				/* translators: 1: current, 2: total */
				__( 'Frase %1$d/%2$d: audio già presente, salto.', 'llm-con-tabelle' ),
				$n,
				$total
			);
		} else {
			$msg = sprintf(
				/* translators: 1: current, 2: total */
				__( 'Frase %1$d/%2$d: audio salvati.', 'llm-con-tabelle' ),
				$n,
				$total
			);
		}
		$ready = 0;
		foreach ( $urls as $u ) {
			if ( $u ) {
				++$ready;
			}
		}
		$shown = count( self::admin_voice_buttons( $locale ) );
		return array(
			'done'      => $done,
			'nextIndex' => $next,
			'total'     => $total,
			'index'     => $index,
			'phraseId'  => isset( $row['id'] ) ? (int) $row['id'] : 0,
			'male'      => ! empty( $row['audio_male_url'] ),
			'female'    => ! empty( $row['audio_female_url'] ),
			'maleUrl'   => isset( $row['audio_male_url'] ) ? (string) $row['audio_male_url'] : '',
			'femaleUrl' => isset( $row['audio_female_url'] ) ? (string) $row['audio_female_url'] : '',
			'urls'      => $urls,
			'ready'     => $ready,
			'shown'     => $shown,
			'message'   => $msg,
		);
	}

	/**
	 * Genera Aura-2 (sito) e, se disponibile, Azure Neural (solo admin).
	 *
	 * @param int    $story_id   ID storia.
	 * @param int    $phrase_id  ID riga.
	 * @param string $html       Testo target (HTML possibile).
	 * @param string $locale     Locale voce.
	 * @param bool   $force      Rigenera anche se già presenti.
	 * @return array{male:int,female:int,azure_male:int,azure_female:int}|WP_Error
	 */
	public static function generate_for_phrase( $story_id, $phrase_id, $html, $locale, $force = false ) {
		$story_id  = absint( $story_id );
		$phrase_id = absint( $phrase_id );
		$text      = self::padded_text( self::plain_text( $html ) );
		if ( ! $story_id || ! $phrase_id || '' === $text ) {
			return new WP_Error( 'llm_tts_empty', __( 'Frase obiettivo vuota.', 'llm-con-tabelle' ) );
		}

		$lang   = strtolower( substr( (string) $locale, 0, 2 ) );
		$use_dg = class_exists( 'LLM_STT' ) && LLM_STT::deepgram_ready() && self::deepgram_supports( $lang );
		$use_az = class_exists( 'LLM_STT' ) && LLM_STT::azure_ready();
		$voices = self::voices_for_locale( $locale );

		$male   = LLM_Story_Repository::get_phrase_audio_id( $story_id, $phrase_id, 'male' );
		$female = LLM_Story_Repository::get_phrase_audio_id( $story_id, $phrase_id, 'female' );
		$az_m   = LLM_Story_Repository::get_phrase_azure_audio_id( $story_id, $phrase_id, 'male' );
		$az_f   = LLM_Story_Repository::get_phrase_azure_audio_id( $story_id, $phrase_id, 'female' );

		if ( $use_dg && ( $force || ! $male || ! $female ) ) {
			$pair = self::synthesize_pair( $story_id, $phrase_id, $text, $locale, 'deepgram', $voices );
			if ( is_wp_error( $pair ) ) {
				return $pair;
			}
			if ( ! LLM_Story_Repository::set_phrase_audio_ids( $story_id, $phrase_id, $pair['male'], $pair['female'] ) ) {
				self::delete_attachments( $pair['male'], $pair['female'] );
				return new WP_Error( 'llm_tts_save', __( 'Impossibile salvare i riferimenti audio sulla frase.', 'llm-con-tabelle' ) );
			}
			if ( $male && (int) $male !== (int) $pair['male'] ) {
				wp_delete_attachment( (int) $male, true );
			}
			if ( $female && (int) $female !== (int) $pair['female'] && (int) $female !== (int) $pair['male'] ) {
				wp_delete_attachment( (int) $female, true );
			}
			$male   = $pair['male'];
			$female = $pair['female'];
		} elseif ( ! $use_dg && $use_az && ( $force || ! $male || ! $female ) ) {
			$pair = self::synthesize_pair( $story_id, $phrase_id, $text, $locale, 'azure', $voices );
			if ( is_wp_error( $pair ) ) {
				return $pair;
			}
			if ( ! LLM_Story_Repository::set_phrase_audio_ids( $story_id, $phrase_id, $pair['male'], $pair['female'] ) ) {
				self::delete_attachments( $pair['male'], $pair['female'] );
				return new WP_Error( 'llm_tts_save', __( 'Impossibile salvare i riferimenti audio sulla frase.', 'llm-con-tabelle' ) );
			}
			if ( $male && (int) $male !== (int) $pair['male'] ) {
				wp_delete_attachment( (int) $male, true );
			}
			if ( $female && (int) $female !== (int) $pair['female'] && (int) $female !== (int) $pair['male'] ) {
				wp_delete_attachment( (int) $female, true );
			}
			$male   = $pair['male'];
			$female = $pair['female'];
			$az_m   = $pair['male'];
			$az_f   = $pair['female'];
			LLM_Story_Repository::set_phrase_azure_audio_ids( $story_id, $phrase_id, $az_m, $az_f );
		}

		if ( $use_dg && $use_az && ( $force || ! $az_m || ! $az_f ) ) {
			$pair = self::synthesize_pair( $story_id, $phrase_id, $text, $locale, 'azure', $voices );
			if ( is_wp_error( $pair ) ) {
				return $pair;
			}
			if ( ! LLM_Story_Repository::set_phrase_azure_audio_ids( $story_id, $phrase_id, $pair['male'], $pair['female'] ) ) {
				self::delete_attachments( $pair['male'], $pair['female'] );
				return new WP_Error( 'llm_tts_save', __( 'Impossibile salvare i riferimenti audio Azure sulla frase.', 'llm-con-tabelle' ) );
			}
			if ( $az_m && (int) $az_m !== (int) $pair['male'] && (int) $az_m !== (int) $male && (int) $az_m !== (int) $female ) {
				wp_delete_attachment( (int) $az_m, true );
			}
			if ( $az_f && (int) $az_f !== (int) $pair['female'] && (int) $az_f !== (int) $pair['male'] && (int) $az_f !== (int) $male && (int) $az_f !== (int) $female ) {
				wp_delete_attachment( (int) $az_f, true );
			}
			$az_m = $pair['male'];
			$az_f = $pair['female'];
		}

		if ( ! $male || ! $female ) {
			return new WP_Error( 'llm_tts_none', __( 'Nessun motore TTS configurato.', 'llm-con-tabelle' ) );
		}

		return array(
			'male'         => (int) $male,
			'female'       => (int) $female,
			'azure_male'   => (int) $az_m,
			'azure_female' => (int) $az_f,
		);
	}

	/**
	 * Audio unico: frase obiettivo + note nella lingua da imparare (voce Azure femminile).
	 *
	 * @param int    $story_id    ID storia.
	 * @param int    $phrase_id   ID riga.
	 * @param string $target_html Frase obiettivo.
	 * @param string $notes_html  Note in lingua obiettivo.
	 * @param string $locale      Locale voce.
	 * @param bool   $force       Rigenera.
	 * @return int|WP_Error Attachment ID.
	 */
	public static function generate_notes_female_audio( $story_id, $phrase_id, $target_html, $notes_html, $locale, $force = false ) {
		$story_id  = absint( $story_id );
		$phrase_id = absint( $phrase_id );
		$phrase    = self::padded_text( self::plain_text( $target_html ) );
		$notes     = self::padded_text( self::plain_text( $notes_html ) );
		if ( ! $story_id || ! $phrase_id || '' === $phrase ) {
			return new WP_Error( 'llm_tts_empty', __( 'Frase obiettivo vuota.', 'llm-con-tabelle' ) );
		}
		if ( ! class_exists( 'LLM_STT' ) || ! LLM_STT::azure_ready() ) {
			return new WP_Error( 'llm_tts_none', __( 'Azure Speech non configurato.', 'llm-con-tabelle' ) );
		}
		$existing = LLM_Story_Repository::get_phrase_notes_audio_id( $story_id, $phrase_id );
		if ( $existing && ! $force ) {
			return (int) $existing;
		}
		$voices = self::voices_for_locale( $locale );
		$att    = self::synthesize_to_attachment(
			$story_id,
			$phrase_id,
			'female',
			$phrase,
			$locale,
			$voices['female'],
			'azure',
			'azure-notes-female',
			$notes
		);
		if ( is_wp_error( $att ) ) {
			return $att;
		}
		if ( ! LLM_Story_Repository::set_phrase_notes_audio_id( $story_id, $phrase_id, (int) $att ) ) {
			self::delete_attachments( 0, 0, 0, 0, (int) $att );
			return new WP_Error( 'llm_tts_save', __( 'Impossibile salvare l’audio delle note sulla frase.', 'llm-con-tabelle' ) );
		}
		if ( $existing && (int) $existing !== (int) $att ) {
			wp_delete_attachment( (int) $existing, true );
		}
		return (int) $att;
	}

	/**
	 * TTS Azure femminile senza allegato (ascolto on-demand).
	 *
	 * @param string $text   Testo piano.
	 * @param string $locale Locale voce.
	 * @return string|WP_Error Byte MP3.
	 */
	public static function azure_female_mp3( $text, $locale ) {
		if ( ! class_exists( 'LLM_STT' ) || ! LLM_STT::azure_ready() ) {
			return new WP_Error( 'llm_tts_none', __( 'Azure Speech non configurato.', 'llm-con-tabelle' ) );
		}
		$plain = self::padded_text( self::plain_text( $text ) );
		if ( '' === $plain ) {
			return new WP_Error( 'llm_tts_empty', __( 'Testo vuoto.', 'llm-con-tabelle' ) );
		}
		$voices = self::voices_for_locale( $locale );
		return self::azure_tts( $plain, $locale, $voices['female'] );
	}

	/**
	 * Azure femminile salvato come allegato (parole/frasi negli appunti).
	 *
	 * @param int    $story_id  ID storia.
	 * @param int    $phrase_id ID frase.
	 * @param string $text      Testo.
	 * @param string $locale    Locale.
	 * @param string $file_tag  Suffisso file.
	 * @return int|WP_Error
	 */
	public static function azure_female_attachment( $story_id, $phrase_id, $text, $locale, $file_tag = 'notes-word' ) {
		$voices = self::voices_for_locale( $locale );
		return self::synthesize_to_attachment( (int) $story_id, (int) $phrase_id, 'female', $text, $locale, $voices['female'], 'azure', $file_tag );
	}

	/**
	 * @param int    $story_id  ID storia.
	 * @param int    $phrase_id ID frase.
	 * @param string $text      Testo piano.
	 * @param string $locale    Locale.
	 * @param string $engine    deepgram|azure.
	 * @param array  $voices    Voci Azure male/female.
	 * @return array{male:int,female:int}|WP_Error
	 */
	private static function synthesize_pair( $story_id, $phrase_id, $text, $locale, $engine, array $voices ) {
		$male = self::synthesize_to_attachment( $story_id, $phrase_id, 'male', $text, $locale, $voices['male'], $engine );
		if ( is_wp_error( $male ) ) {
			return $male;
		}
		$female = self::synthesize_to_attachment( $story_id, $phrase_id, 'female', $text, $locale, $voices['female'], $engine );
		if ( is_wp_error( $female ) ) {
			self::delete_attachments( $male, 0 );
			return $female;
		}
		return array(
			'male'   => $male,
			'female' => $female,
		);
	}

	/**
	 * @param int    $story_id  ID storia.
	 * @param int    $phrase_id ID frase.
	 * @param string $gender    male|female.
	 * @param string $text      Testo piano.
	 * @param string $locale    Locale SSML.
	 * @param string $voice     Nome voce Azure.
	 * @param string $engine    deepgram|azure.
	 * @param string $file_tag  Suffisso file (opzionale).
	 * @param string $second    Secondo blocco (note), solo Azure.
	 * @return int|WP_Error Attachment ID.
	 */
	private static function synthesize_to_attachment( $story_id, $phrase_id, $gender, $text, $locale, $voice, $engine = 'deepgram', $file_tag = '', $second = '' ) {
		$engine = 'azure' === $engine ? 'azure' : 'deepgram';
		if ( 'azure' === $engine && '' !== $second ) {
			$bytes = self::azure_tts( $text, $locale, $voice, $second );
		} else {
			$bytes = self::tts_bytes( $text, $locale, $gender, $voice, $engine );
		}
		if ( is_wp_error( $bytes ) ) {
			return $bytes;
		}

		$gender = 'female' === $gender ? 'female' : 'male';
		$suffix = '' !== $file_tag
			? sanitize_file_name( $file_tag )
			: ( 'azure' === $engine ? 'azure-' . $gender : $gender );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = wp_tempnam( 'llm-tts.mp3' );
		if ( ! $tmp ) {
			return new WP_Error( 'llm_tts_tmp', __( 'Impossibile creare il file temporaneo.', 'llm-con-tabelle' ) );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $tmp, $bytes ) ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'llm_tts_tmp', __( 'Impossibile scrivere il file temporaneo.', 'llm-con-tabelle' ) );
		}

		$file_array = array(
			'name'     => sprintf( 'llm-story-%d-phrase-%d-%s.mp3', $story_id, $phrase_id, $suffix ),
			'tmp_name' => $tmp,
		);
		$allow_mp3 = static function ( $mimes ) {
			if ( ! is_array( $mimes ) ) {
				$mimes = array();
			}
			$mimes['mp3'] = 'audio/mpeg';
			return $mimes;
		};
		add_filter( 'upload_mimes', $allow_mp3 );
		$att_id = media_handle_sideload( $file_array, $story_id, '' );
		remove_filter( 'upload_mimes', $allow_mp3 );
		if ( is_wp_error( $att_id ) ) {
			if ( file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			return $att_id;
		}

		update_post_meta( $att_id, '_llm_tts_story', $story_id );
		update_post_meta( $att_id, '_llm_tts_phrase', $phrase_id );
		update_post_meta( $att_id, '_llm_tts_gender', $gender );
		update_post_meta( $att_id, '_llm_tts_engine', $engine );

		return (int) $att_id;
	}

	/**
	 * Deepgram Aura-2 per EN/IT/ES; Azure per il resto (es. polacco).
	 *
	 * @param string $text   Testo.
	 * @param string $locale Locale.
	 * @param string $gender male|female.
	 * @param string $azure_voice Voce Azure di riserva.
	 * @param string $engine      deepgram|azure.
	 * @return string|WP_Error
	 */
	private static function tts_bytes( $text, $locale, $gender, $azure_voice, $engine = '' ) {
		$lang = strtolower( substr( (string) $locale, 0, 2 ) );
		if ( 'azure' === $engine ) {
			if ( class_exists( 'LLM_STT' ) && LLM_STT::azure_ready() ) {
				return self::azure_tts( $text, $locale, $azure_voice );
			}
			return new WP_Error( 'llm_tts_none', __( 'Azure Speech non configurato.', 'llm-con-tabelle' ) );
		}
		if ( ( 'deepgram' === $engine || '' === $engine ) && class_exists( 'LLM_STT' ) && LLM_STT::deepgram_ready() && self::deepgram_supports( $lang ) ) {
			return self::deepgram_tts( $text, self::deepgram_model( $lang, $gender ) );
		}
		if ( '' === $engine && class_exists( 'LLM_STT' ) && LLM_STT::azure_ready() ) {
			return self::azure_tts( $text, $locale, $azure_voice );
		}
		return new WP_Error( 'llm_tts_none', __( 'Nessun motore TTS configurato.', 'llm-con-tabelle' ) );
	}

	/**
	 * @param string $lang it|en|es|pl.
	 * @return bool
	 */
	public static function deepgram_supports( $lang ) {
		return in_array( $lang, array( 'en', 'it', 'es' ), true );
	}

	/**
	 * @param string $lang   it|en|es.
	 * @param string $gender male|female.
	 * @return string
	 */
	public static function deepgram_model( $lang, $gender ) {
		$female = 'female' === $gender;
		if ( 'it' === $lang ) {
			return $female ? 'aura-2-cinzia-it' : 'aura-2-flavio-it';
		}
		if ( 'es' === $lang ) {
			return $female ? 'aura-2-selena-es' : 'aura-2-javier-es';
		}
		return $female ? 'aura-2-thalia-en' : 'aura-2-orion-en';
	}

	/**
	 * @param string $text  Testo.
	 * @param string $model Modello Aura (es. aura-2-thalia-en).
	 * @return string|WP_Error Byte MP3.
	 */
	private static function deepgram_tts( $text, $model ) {
		$key = (string) LLM_STT::settings()['deepgram_key'];
		$url = add_query_arg(
			array(
				'model'    => $model,
				'encoding' => 'mp3',
			),
			'https://api.deepgram.com/v1/speak'
		);
		$res = wp_remote_post(
			$url,
			array(
				'timeout' => 40,
				'headers' => array(
					'Authorization' => 'Token ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'text' => $text ) ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'llm_tts_net', $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = (string) wp_remote_retrieve_body( $res );
		if ( $code < 200 || $code >= 300 || '' === $body ) {
			$hint = trim( wp_strip_all_tags( substr( $body, 0, 180 ) ) );
			return new WP_Error(
				'llm_tts_deepgram',
				$hint
					? sprintf(
						/* translators: 1: HTTP code, 2: body snippet */
						__( 'Deepgram TTS HTTP %1$d: %2$s', 'llm-con-tabelle' ),
						$code,
						$hint
					)
					: sprintf(
						/* translators: %d: HTTP code */
						__( 'Deepgram TTS HTTP %d.', 'llm-con-tabelle' ),
						$code
					)
			);
		}
		return $body;
	}

	/**
	 * @param string $text   Testo.
	 * @param string $locale Locale.
	 * @param string $voice  Voce Azure.
	 * @param string $second Secondo blocco di testo (note).
	 * @return string|WP_Error Byte MP3.
	 */
	private static function azure_tts( $text, $locale, $voice, $second = '' ) {
		$s      = LLM_STT::settings();
		$region = sanitize_key( (string) $s['azure_region'] );
		$key    = (string) $s['azure_key'];
		$url    = 'https://' . $region . '.tts.speech.microsoft.com/cognitiveservices/v1';

		$ssml  = '<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xmlns:mstts="http://www.w3.org/2001/mstts" xml:lang="' . esc_attr( $locale ) . '">';
		$ssml .= '<voice name="' . esc_attr( $voice ) . '">';
		$ssml .= '<mstts:silence type="Leading-exact" value="180ms"/>';
		$ssml .= self::escape_ssml( $text );
		if ( '' !== trim( (string) $second ) ) {
			$ssml .= '<break time="550ms"/>';
			$ssml .= self::escape_ssml( $second );
		}
		$ssml .= '<break time="400ms"/>';
		$ssml .= '</voice>';
		$ssml .= '</speak>';

		$timeout = '' !== trim( (string) $second ) ? 90 : 40;
		$res     = wp_remote_post(
			$url,
			array(
				'timeout' => $timeout,
				'headers' => array(
					'Ocp-Apim-Subscription-Key' => $key,
					'Content-Type'              => 'application/ssml+xml',
					'X-Microsoft-OutputFormat'  => 'audio-24khz-96kbitrate-mono-mp3',
					'User-Agent'                => 'llm-con-tabelle',
				),
				'body'    => $ssml,
			)
		);
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'llm_tts_net', $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = (string) wp_remote_retrieve_body( $res );
		if ( $code < 200 || $code >= 300 || '' === $body ) {
			$hint = trim( wp_strip_all_tags( substr( $body, 0, 180 ) ) );
			return new WP_Error(
				'llm_tts_azure',
				$hint
					? sprintf(
						/* translators: 1: HTTP code, 2: body snippet */
						__( 'Azure TTS HTTP %1$d: %2$s', 'llm-con-tabelle' ),
						$code,
						$hint
					)
					: sprintf(
						/* translators: %d: HTTP code */
						__( 'Azure TTS HTTP %d.', 'llm-con-tabelle' ),
						$code
					)
			);
		}
		return $body;
	}

	/**
	 * @param string $text Testo.
	 * @return string
	 */
	private static function escape_ssml( $text ) {
		return htmlspecialchars( $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}
}
