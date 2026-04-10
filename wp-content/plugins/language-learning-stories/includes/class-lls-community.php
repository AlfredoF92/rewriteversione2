<?php
/**
 * Feed attività e “Bravo” (like) tra utenti.
 *
 * @package LLS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLS_Community {

	const META_TYPE       = '_lls_activity_type';
	const META_STORY      = '_lls_story_id';
	const META_PHRASE     = '_lls_phrase_index';
	const META_FINGER     = '_lls_activity_fingerprint';
	const META_KUDOS      = '_lls_kudos_users';

	const TYPE_PHRASE     = 'phrase_complete';
	const TYPE_STORY_DONE = 'story_complete';
	const TYPE_STORY_START = 'story_started';

	public static function init() {
	}

	/**
	 * @param string $fp Impronta.
	 * @return int 0 se assente.
	 */
	public static function find_post_id_by_fingerprint( $fp ) {
		$q = new WP_Query(
			array(
				'post_type'      => LLS_ACTIVITY_CPT,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::META_FINGER,
				'meta_value'     => $fp,
			)
		);
		$ids = $q->posts;
		return ! empty( $ids ) ? (int) $ids[0] : 0;
	}

	/**
	 * @param string     $type Tipo attività.
	 * @param int        $user_id Autore.
	 * @param int        $story_id ID storia.
	 * @param int|null   $phrase_index Indice o null.
	 * @param string     $post_date_mysql Data evento locale.
	 * @return int ID post attività o 0.
	 */
	public static function create_activity( $type, $user_id, $story_id, $phrase_index, $post_date_mysql ) {
		$user_id  = absint( $user_id );
		$story_id = absint( $story_id );
		$type     = sanitize_key( $type );

		if ( self::TYPE_PHRASE === $type ) {
			$fp = 'p|' . $user_id . '|' . $story_id . '|' . (int) $phrase_index;
		} elseif ( self::TYPE_STORY_DONE === $type ) {
			$fp = 'sd|' . $user_id . '|' . $story_id;
		} elseif ( self::TYPE_STORY_START === $type ) {
			$fp = 'ss|' . $user_id . '|' . $story_id;
		} else {
			return 0;
		}

		$existing = self::find_post_id_by_fingerprint( $fp );
		if ( $existing ) {
			return $existing;
		}

		$story_title = get_the_title( $story_id );
		if ( ! $story_title ) {
			$story_title = '#' . $story_id;
		}

		if ( self::TYPE_PHRASE === $type ) {
			$n = (int) $phrase_index + 1;
			/* translators: 1: phrase number, 2: story title */
			$title = sprintf( __( 'Frase %1$d completata — %2$s', 'language-learning-stories' ), $n, $story_title );
		} elseif ( self::TYPE_STORY_DONE === $type ) {
			/* translators: %s story title */
			$title = sprintf( __( 'Storia completata — %s', 'language-learning-stories' ), $story_title );
		} else {
			/* translators: %s story title */
			$title = sprintf( __( 'Storia iniziata / sbloccata — %s', 'language-learning-stories' ), $story_title );
		}

		$gmt = get_gmt_from_date( $post_date_mysql );

		$post_id = wp_insert_post(
			array(
				'post_type'    => LLS_ACTIVITY_CPT,
				'post_status'  => 'publish',
				'post_author'  => $user_id,
				'post_title'   => $title,
				'post_date'    => $post_date_mysql,
				'post_date_gmt' => $gmt,
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		update_post_meta( $post_id, self::META_FINGER, $fp );
		update_post_meta( $post_id, self::META_TYPE, $type );
		update_post_meta( $post_id, self::META_STORY, $story_id );
		if ( self::TYPE_PHRASE === $type ) {
			update_post_meta( $post_id, self::META_PHRASE, (int) $phrase_index );
		} else {
			update_post_meta( $post_id, self::META_PHRASE, '' );
		}
		update_post_meta( $post_id, self::META_KUDOS, wp_json_encode( array() ) );

		return (int) $post_id;
	}

	/**
	 * @param int $user_id ID utente.
	 * @param int $story_id ID storia.
	 * @param int $phrase_index Indice 0-based.
	 */
	public static function record_phrase_completed( $user_id, $story_id, $phrase_index ) {
		self::create_activity(
			self::TYPE_PHRASE,
			$user_id,
			$story_id,
			(int) $phrase_index,
			current_time( 'mysql' )
		);
	}

	/**
	 * @param int $user_id ID utente.
	 * @param int $story_id ID storia.
	 */
	public static function record_story_completed( $user_id, $story_id ) {
		self::create_activity(
			self::TYPE_STORY_DONE,
			$user_id,
			$story_id,
			null,
			current_time( 'mysql' )
		);
	}

	/**
	 * @param int $user_id ID utente.
	 * @param int $story_id ID storia.
	 */
	public static function record_story_started( $user_id, $story_id ) {
		self::create_activity(
			self::TYPE_STORY_START,
			$user_id,
			$story_id,
			null,
			current_time( 'mysql' )
		);
	}

	/**
	 * @param int $activity_id ID attività.
	 * @return int[]
	 */
	public static function get_kudos_user_ids( $activity_id ) {
		$raw = get_post_meta( $activity_id, self::META_KUDOS, true );
		if ( $raw === '' || $raw === false ) {
			return array();
		}
		$d = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		if ( ! is_array( $d ) ) {
			return array();
		}
		$out = array();
		foreach ( $d as $id ) {
			$out[] = absint( $id );
		}
		return array_values( array_unique( array_filter( $out ) ) );
	}

	/**
	 * Aggiunge un “Bravo” (vietato sull’attività propria o doppio voto).
	 *
	 * @param int $activity_id ID attività.
	 * @param int $liker_id    ID utente che mette il like.
	 * @return bool
	 */
	public static function add_bravo( $activity_id, $liker_id ) {
		$activity_id = absint( $activity_id );
		$liker_id    = absint( $liker_id );
		$post        = get_post( $activity_id );
		if ( ! $post || LLS_ACTIVITY_CPT !== $post->post_type ) {
			return false;
		}
		$author = (int) $post->post_author;
		if ( $author === $liker_id ) {
			return false;
		}
		$kudos = self::get_kudos_user_ids( $activity_id );
		if ( in_array( $liker_id, $kudos, true ) ) {
			return false;
		}
		$kudos[] = $liker_id;
		update_post_meta( $activity_id, self::META_KUDOS, wp_json_encode( $kudos ) );

		$given = self::get_bravo_given_raw( $liker_id );
		$given[] = array(
			'activity_id' => $activity_id,
			'ts'          => current_time( 'mysql', true ),
		);
		update_user_meta( $liker_id, LLS_User_Meta::BRAVO_GIVEN, wp_json_encode( $given ) );

		return true;
	}

	/**
	 * @param int $user_id ID utente.
	 * @return array<int, array{activity_id:int, ts:string}>
	 */
	public static function get_bravo_given_raw( $user_id ) {
		$raw = get_user_meta( $user_id, LLS_User_Meta::BRAVO_GIVEN, true );
		if ( $raw === '' || $raw === false ) {
			return array();
		}
		$d = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		return is_array( $d ) ? $d : array();
	}

	/**
	 * Quanti “Bravo” ha ricevuto l’utente sulle proprie attività.
	 *
	 * @param int $user_id ID utente.
	 * @return int
	 */
	public static function count_bravi_received( $user_id ) {
		$user_id = absint( $user_id );
		$q       = new WP_Query(
			array(
				'post_type'      => LLS_ACTIVITY_CPT,
				'post_status'    => 'publish',
				'author'         => $user_id,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$sum = 0;
		foreach ( $q->posts as $aid ) {
			$sum += count( self::get_kudos_user_ids( (int) $aid ) );
		}
		return $sum;
	}

	/**
	 * Quanti “Bravo” ha messo l’utente.
	 *
	 * @param int $user_id ID utente.
	 * @return int
	 */
	public static function count_bravi_given( $user_id ) {
		return count( self::get_bravo_given_raw( $user_id ) );
	}

	/**
	 * Etichetta tipo per tabella admin.
	 *
	 * @param string $type Slug.
	 * @return string
	 */
	public static function type_label( $type ) {
		switch ( $type ) {
			case self::TYPE_PHRASE:
				return __( 'Frase completata', 'language-learning-stories' );
			case self::TYPE_STORY_DONE:
				return __( 'Storia completata', 'language-learning-stories' );
			case self::TYPE_STORY_START:
				return __( 'Storia iniziata / sbloccata', 'language-learning-stories' );
			default:
				return $type;
		}
	}

	/**
	 * Testo dettaglio riga (storia + frase se applicabile).
	 *
	 * @param int $activity_id ID attività.
	 * @return string
	 */
	public static function format_detail( $activity_id ) {
		$story_id = (int) get_post_meta( $activity_id, self::META_STORY, true );
		$type     = (string) get_post_meta( $activity_id, self::META_TYPE, true );
		$title    = get_the_title( $story_id );
		if ( ! $title ) {
			$title = '#' . $story_id;
		}
		if ( self::TYPE_PHRASE === $type ) {
			$pi = get_post_meta( $activity_id, self::META_PHRASE, true );
			$pi = ( $pi !== '' && $pi !== false ) ? (int) $pi : 0;
			/* translators: 1: story, 2: phrase number 1-based */
			return sprintf( __( 'Storia «%1$s», frase %2$d', 'language-learning-stories' ), $title, $pi + 1 );
		}
		/* translators: %s story title */
		return sprintf( __( 'Storia «%s»', 'language-learning-stories' ), $title );
	}

	/**
	 * Crea attività mancanti da progressi salvati (es. dati pre-Community).
	 *
	 * @param int $user_id ID utente.
	 */
	public static function backfill_user_from_progress( $user_id ) {
		$user_id = absint( $user_id );
		$map     = LLS_User_Stats::get_phrase_map( $user_id );
		foreach ( $map as $sid => $indices ) {
			$sid = absint( $sid );
			foreach ( $indices as $pi ) {
				$fp = 'p|' . $user_id . '|' . $sid . '|' . (int) $pi;
				if ( self::find_post_id_by_fingerprint( $fp ) ) {
					continue;
				}
				self::create_activity( self::TYPE_PHRASE, $user_id, $sid, (int) $pi, self::random_past_mysql( 2, 20 ) );
			}
		}

		$done = LLS_User_Stats::get_completed_stories_map( $user_id );
		foreach ( $done as $sid => $ts ) {
			$sid = absint( $sid );
			$fp  = 'sd|' . $user_id . '|' . $sid;
			if ( self::find_post_id_by_fingerprint( $fp ) ) {
				continue;
			}
			if ( is_string( $ts ) && $ts !== '' ) {
				$when = get_date_from_gmt( $ts, 'Y-m-d H:i:s' );
			} else {
				$when = self::random_past_mysql( 1, 18 );
			}
			self::create_activity( self::TYPE_STORY_DONE, $user_id, $sid, null, $when );
		}

		$unlock = LLS_User_Stats::get_unlocked_story_ids( $user_id );
		foreach ( $unlock as $sid ) {
			$sid = absint( $sid );
			$fp  = 'ss|' . $user_id . '|' . $sid;
			if ( self::find_post_id_by_fingerprint( $fp ) ) {
				continue;
			}
			self::create_activity( self::TYPE_STORY_START, $user_id, $sid, null, self::random_past_mysql( 4, 25 ) );
		}
	}

	/**
	 * Data/ora locale casuale negli ultimi N–M giorni.
	 *
	 * @param int $min_days Giorni minimi fa.
	 * @param int $max_days Giorni massimi fa.
	 * @return string mysql locale.
	 */
	protected static function random_past_mysql( $min_days, $max_days ) {
		$days = wp_rand( (int) $min_days, (int) $max_days );
		$t    = time() - ( $days * DAY_IN_SECONDS ) - wp_rand( 0, 86400 );
		return wp_date( 'Y-m-d H:i:s', $t );
	}
}
