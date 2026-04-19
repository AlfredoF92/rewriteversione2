<?php
/**
 * Feed attività e Bravi: kudos e storico Bravo solo in tabelle (no JSON).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Community {

	const META_TYPE   = '_llm_activity_type';
	const META_STORY  = '_llm_story_id';
	const META_PHRASE = '_llm_phrase_index';
	const META_FINGER = '_llm_activity_fingerprint';

	const TYPE_PHRASE      = 'phrase_complete';
	const TYPE_STORY_DONE  = 'story_complete';
	const TYPE_STORY_START = 'story_started';

	public static function init() {
		add_action( 'before_delete_post', array( __CLASS__, 'before_delete_activity' ), 5, 2 );
	}

	/**
	 * Rimuove tutti i post attività di un utente (es. prima di ripopolare dati demo).
	 *
	 * @param int $user_id ID utente (autore post).
	 */
	public static function delete_activities_for_user( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'              => LLM_ACTIVITY_CPT,
				'post_status'            => 'any',
				'author'                 => $user_id,
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( ! is_array( $ids ) ) {
			return;
		}
		foreach ( $ids as $pid ) {
			wp_delete_post( (int) $pid, true );
		}
	}

	/**
	 * @param int      $post_id Post ID.
	 * @param WP_Post|null $post Post.
	 */
	public static function before_delete_activity( $post_id, $post ) {
		if ( ! $post instanceof WP_Post ) {
			$post = get_post( $post_id );
		}
		if ( ! $post || LLM_ACTIVITY_CPT !== $post->post_type ) {
			return;
		}
		self::delete_activity_relations( (int) $post_id );
	}

	/**
	 * @param int $activity_id ID attività.
	 */
	public static function delete_activity_relations( $activity_id ) {
		global $wpdb;
		$activity_id = absint( $activity_id );
		$k           = LLM_Tabelle_Database::table( 'llm_activity_kudos' );
		$b           = LLM_Tabelle_Database::table( 'llm_user_bravo_given' );
		$wpdb->delete( $k, array( 'activity_id' => $activity_id ), array( '%d' ) );
		$wpdb->delete( $b, array( 'activity_id' => $activity_id ), array( '%d' ) );
	}

	public static function find_post_id_by_fingerprint( $fp ) {
		$q = new WP_Query(
			array(
				'post_type'      => LLM_ACTIVITY_CPT,
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
			$title = sprintf( __( 'Frase %1$d completata — %2$s', 'llm-con-tabelle' ), $n, $story_title );
		} elseif ( self::TYPE_STORY_DONE === $type ) {
			$title = sprintf( __( 'Storia completata — %s', 'llm-con-tabelle' ), $story_title );
		} else {
			$title = sprintf( __( 'Storia iniziata / sbloccata — %s', 'llm-con-tabelle' ), $story_title );
		}

		$gmt = get_gmt_from_date( $post_date_mysql );

		$post_id = wp_insert_post(
			array(
				'post_type'     => LLM_ACTIVITY_CPT,
				'post_status'   => 'publish',
				'post_author'   => $user_id,
				'post_title'    => $title,
				'post_date'     => $post_date_mysql,
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

		return (int) $post_id;
	}

	public static function record_phrase_completed( $user_id, $story_id, $phrase_index ) {
		self::create_activity(
			self::TYPE_PHRASE,
			$user_id,
			$story_id,
			(int) $phrase_index,
			current_time( 'mysql' )
		);
	}

	public static function record_story_completed( $user_id, $story_id ) {
		self::create_activity(
			self::TYPE_STORY_DONE,
			$user_id,
			$story_id,
			null,
			current_time( 'mysql' )
		);
	}

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
		global $wpdb;
		$activity_id = absint( $activity_id );
		if ( ! $activity_id ) {
			return array();
		}
		$table = LLM_Tabelle_Database::table( 'llm_activity_kudos' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$table} WHERE activity_id = %d ORDER BY created_gmt ASC, user_id ASC", $activity_id ) );
		return is_array( $col ) ? array_map( 'absint', $col ) : array();
	}

	/**
	 * @param int $activity_id ID attività.
	 * @return int
	 */
	public static function count_kudos( $activity_id ) {
		global $wpdb;
		$activity_id = absint( $activity_id );
		if ( ! $activity_id ) {
			return 0;
		}
		$table = LLM_Tabelle_Database::table( 'llm_activity_kudos' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE activity_id = %d", $activity_id ) );
	}

	/**
	 * @param int $activity_id ID attività.
	 * @param int $user_id     ID utente.
	 */
	public static function user_has_kudos( $activity_id, $user_id ) {
		return in_array( absint( $user_id ), self::get_kudos_user_ids( $activity_id ), true );
	}

	/**
	 * Rimuove Bravo / like (toggle).
	 *
	 * @param int $activity_id ID attività.
	 * @param int $liker_id    ID utente che toglie il proprio like.
	 * @return bool
	 */
	public static function remove_bravo( $activity_id, $liker_id ) {
		global $wpdb;
		$activity_id = absint( $activity_id );
		$liker_id    = absint( $liker_id );
		if ( ! $activity_id || ! $liker_id ) {
			return false;
		}
		$post = get_post( $activity_id );
		if ( ! $post || LLM_ACTIVITY_CPT !== $post->post_type ) {
			return false;
		}
		if ( ! self::user_has_kudos( $activity_id, $liker_id ) ) {
			return false;
		}
		$k = LLM_Tabelle_Database::table( 'llm_activity_kudos' );
		$b = LLM_Tabelle_Database::table( 'llm_user_bravo_given' );
		$wpdb->delete( $k, array( 'activity_id' => $activity_id, 'user_id' => $liker_id ), array( '%d', '%d' ) );
		$wpdb->delete( $b, array( 'user_id' => $liker_id, 'activity_id' => $activity_id ), array( '%d', '%d' ) );
		return true;
	}

	public static function add_bravo( $activity_id, $liker_id ) {
		global $wpdb;
		$activity_id = absint( $activity_id );
		$liker_id    = absint( $liker_id );
		$post        = get_post( $activity_id );
		if ( ! $post || LLM_ACTIVITY_CPT !== $post->post_type ) {
			return false;
		}
		$author = (int) $post->post_author;
		if ( $author === $liker_id ) {
			return false;
		}
		if ( in_array( $liker_id, self::get_kudos_user_ids( $activity_id ), true ) ) {
			return false;
		}

		$now = current_time( 'mysql', true );
		$k   = LLM_Tabelle_Database::table( 'llm_activity_kudos' );
		$b   = LLM_Tabelle_Database::table( 'llm_user_bravo_given' );

		$wpdb->insert(
			$k,
			array(
				'activity_id' => $activity_id,
				'user_id'     => $liker_id,
				'created_gmt' => $now,
			),
			array( '%d', '%d', '%s' )
		);

		$wpdb->insert(
			$b,
			array(
				'user_id'     => $liker_id,
				'activity_id' => $activity_id,
				'ts_gmt'      => $now,
			),
			array( '%d', '%d', '%s' )
		);

		return true;
	}

	/**
	 * @param int $user_id ID utente.
	 * @return array<int, array{activity_id:int, ts:string}>
	 */
	public static function get_bravo_given_raw( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}
		$table = LLM_Tabelle_Database::table( 'llm_user_bravo_given' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT activity_id, ts_gmt FROM {$table} WHERE user_id = %d ORDER BY id ASC", $user_id ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $r ) {
			$out[] = array(
				'activity_id' => isset( $r['activity_id'] ) ? (int) $r['activity_id'] : 0,
				'ts'          => isset( $r['ts_gmt'] ) ? (string) $r['ts_gmt'] : '',
			);
		}
		return $out;
	}

	public static function count_bravi_received( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}
		$k    = LLM_Tabelle_Database::table( 'llm_activity_kudos' );
		$posts = $wpdb->posts;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$n = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$k} k INNER JOIN {$posts} p ON p.ID = k.activity_id WHERE p.post_author = %d AND p.post_type = %s",
				$user_id,
				LLM_ACTIVITY_CPT
			)
		);
		return (int) $n;
	}

	public static function count_bravi_given( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}
		$table = LLM_Tabelle_Database::table( 'llm_user_bravo_given' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id ) );
	}

	public static function type_label( $type ) {
		switch ( $type ) {
			case self::TYPE_PHRASE:
				return __( 'Frase completata', 'llm-con-tabelle' );
			case self::TYPE_STORY_DONE:
				return __( 'Storia completata', 'llm-con-tabelle' );
			case self::TYPE_STORY_START:
				return __( 'Storia iniziata / sbloccata', 'llm-con-tabelle' );
			default:
				return $type;
		}
	}

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
			return sprintf( __( 'Storia «%1$s», frase %2$d', 'llm-con-tabelle' ), $title, $pi + 1 );
		}
		return sprintf( __( 'Storia «%s»', 'llm-con-tabelle' ), $title );
	}

	public static function backfill_user_from_progress( $user_id ) {
		$user_id = absint( $user_id );
		$map     = LLM_User_Stats::get_phrase_map( $user_id );
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

		$done = LLM_User_Stats::get_completed_stories_map( $user_id );
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

		$unlock = LLM_User_Stats::get_unlocked_story_ids( $user_id );
		foreach ( $unlock as $sid ) {
			$sid = absint( $sid );
			$fp  = 'ss|' . $user_id . '|' . $sid;
			if ( self::find_post_id_by_fingerprint( $fp ) ) {
				continue;
			}
			self::create_activity( self::TYPE_STORY_START, $user_id, $sid, null, self::random_past_mysql( 4, 25 ) );
		}
	}

	protected static function random_past_mysql( $min_days, $max_days ) {
		$days = wp_rand( (int) $min_days, (int) $max_days );
		$t    = time() - ( $days * DAY_IN_SECONDS ) - wp_rand( 0, 86400 );
		return wp_date( 'Y-m-d H:i:s', $t );
	}
}
