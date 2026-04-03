<?php
/**
 * Progressi, coin e ledger utente — solo tabelle (no JSON).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_User_Stats {

	const LEDGER_MAX = 500;

	/**
	 * Se true, non crea post attività community (per seed demo: poi backfill con date variate).
	 *
	 * @var bool
	 */
	private static $suppress_community = false;

	public static function init() {
	}

	/**
	 * @param bool $suppress Se true, salta record su LLM_Community durante le operazioni utente.
	 */
	public static function suppress_community_events( $suppress ) {
		self::$suppress_community = (bool) $suppress;
	}

	/**
	 * Riga ledger extra per dati demo (bonus, spese simulate, ecc.).
	 *
	 * @param int    $user_id ID utente.
	 * @param string $type    Tipo voce (es. demo_bonus).
	 * @param int    $amount  Delta coin (positivo o negativo).
	 * @param string $label   Etichetta leggibile.
	 */
	public static function demo_append_ledger( $user_id, $type, $amount, $label ) {
		self::push_ledger(
			$user_id,
			sanitize_key( (string) $type ),
			(int) $amount,
			0,
			null,
			sanitize_text_field( (string) $label )
		);
	}

	public static function get_balance( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}
		$t = LLM_Tabelle_Database::table( 'llm_user_coin_balance' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$v = $wpdb->get_var( $wpdb->prepare( "SELECT balance FROM {$t} WHERE user_id = %d", $user_id ) );
		return max( 0, (int) $v );
	}

	/**
	 * @return array<string, array<int>>
	 */
	public static function get_phrase_map( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}
		$table = LLM_Tabelle_Database::table( 'llm_user_phrase_done' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT story_id, phrase_index FROM {$table} WHERE user_id = %d ORDER BY story_id ASC, phrase_index ASC", $user_id ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $r ) {
			$sid = (string) absint( $r['story_id'] );
			if ( ! isset( $out[ $sid ] ) ) {
				$out[ $sid ] = array();
			}
			$out[ $sid ][] = (int) $r['phrase_index'];
		}
		foreach ( $out as $k => $nums ) {
			$nums = array_values( array_unique( array_map( 'intval', $nums ) ) );
			sort( $nums );
			$out[ $k ] = $nums;
		}
		return $out;
	}

	/**
	 * @return int[]
	 */
	public static function get_unlocked_story_ids( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}
		$table = LLM_Tabelle_Database::table( 'llm_user_unlocked_story' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col = $wpdb->get_col( $wpdb->prepare( "SELECT story_id FROM {$table} WHERE user_id = %d ORDER BY story_id ASC", $user_id ) );
		return is_array( $col ) ? array_values( array_unique( array_map( 'absint', $col ) ) ) : array();
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_completed_stories_map( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}
		$table = LLM_Tabelle_Database::table( 'llm_user_story_completed' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT story_id, completed_at_gmt FROM {$table} WHERE user_id = %d", $user_id ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $r ) {
			$out[ (string) absint( $r['story_id'] ) ] = (string) $r['completed_at_gmt'];
		}
		return $out;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_ledger( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}
		$table = LLM_Tabelle_Database::table( 'llm_user_coin_ledger' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT entry_key, entry_type, amount, balance_after, story_id, phrase_index, ts_gmt, label FROM {$table} WHERE user_id = %d ORDER BY id ASC", $user_id ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $r ) {
			$pi = isset( $r['phrase_index'] ) ? $r['phrase_index'] : null;
			$pi = ( null === $pi || '' === $pi || (int) $pi < 0 ) ? null : (int) $pi;
			$out[] = array(
				'id'            => isset( $r['entry_key'] ) ? (string) $r['entry_key'] : '',
				'type'          => isset( $r['entry_type'] ) ? (string) $r['entry_type'] : '',
				'amount'        => isset( $r['amount'] ) ? (int) $r['amount'] : 0,
				'balance_after' => isset( $r['balance_after'] ) ? (int) $r['balance_after'] : 0,
				'story_id'      => isset( $r['story_id'] ) ? (int) $r['story_id'] : 0,
				'phrase_index'  => $pi,
				'ts'            => isset( $r['ts_gmt'] ) ? (string) $r['ts_gmt'] : '',
				'label'         => isset( $r['label'] ) ? (string) $r['label'] : '',
			);
		}
		return $out;
	}

	protected static function save_phrase_map( $user_id, array $map ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$table   = LLM_Tabelle_Database::table( 'llm_user_phrase_done' );
		$wpdb->delete( $table, array( 'user_id' => $user_id ), array( '%d' ) );
		foreach ( $map as $sid => $indices ) {
			$sid = absint( $sid );
			if ( ! is_array( $indices ) ) {
				continue;
			}
			foreach ( $indices as $pi ) {
				$wpdb->insert(
					$table,
					array(
						'user_id'      => $user_id,
						'story_id'     => $sid,
						'phrase_index' => (int) $pi,
					),
					array( '%d', '%d', '%d' )
				);
			}
		}
	}

	protected static function save_unlocked( $user_id, array $ids ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$table   = LLM_Tabelle_Database::table( 'llm_user_unlocked_story' );
		$wpdb->delete( $table, array( 'user_id' => $user_id ), array( '%d' ) );
		foreach ( array_unique( array_map( 'absint', $ids ) ) as $sid ) {
			if ( ! $sid ) {
				continue;
			}
			$wpdb->insert( $table, array( 'user_id' => $user_id, 'story_id' => $sid ), array( '%d', '%d' ) );
		}
	}

	protected static function save_completed_map( $user_id, array $map ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$table   = LLM_Tabelle_Database::table( 'llm_user_story_completed' );
		$wpdb->delete( $table, array( 'user_id' => $user_id ), array( '%d' ) );
		foreach ( $map as $sid => $ts ) {
			$sid = absint( $sid );
			if ( ! $sid || ! is_string( $ts ) || $ts === '' ) {
				continue;
			}
			$wpdb->insert(
				$table,
				array(
					'user_id'          => $user_id,
					'story_id'         => $sid,
					'completed_at_gmt' => $ts,
				),
				array( '%d', '%d', '%s' )
			);
		}
	}

	protected static function save_balance( $user_id, $balance ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$table   = LLM_Tabelle_Database::table( 'llm_user_coin_balance' );
		$wpdb->replace(
			$table,
			array(
				'user_id' => $user_id,
				'balance' => max( 0, (int) $balance ),
			),
			array( '%d', '%d' )
		);
	}

	protected static function save_ledger( $user_id, array $ledger ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( count( $ledger ) > self::LEDGER_MAX ) {
			$ledger = array_slice( $ledger, -self::LEDGER_MAX );
		}
		$table = LLM_Tabelle_Database::table( 'llm_user_coin_ledger' );
		$wpdb->delete( $table, array( 'user_id' => $user_id ), array( '%d' ) );
		foreach ( $ledger as $row ) {
			$pi = array_key_exists( 'phrase_index', $row ) && null !== $row['phrase_index'] ? (int) $row['phrase_index'] : -1;
			$wpdb->insert(
				$table,
				array(
					'user_id'       => $user_id,
					'entry_key'     => isset( $row['id'] ) ? substr( sanitize_text_field( (string) $row['id'] ), 0, 128 ) : '',
					'entry_type'    => isset( $row['type'] ) ? sanitize_key( (string) $row['type'] ) : '',
					'amount'        => isset( $row['amount'] ) ? (int) $row['amount'] : 0,
					'balance_after' => isset( $row['balance_after'] ) ? (int) $row['balance_after'] : 0,
					'story_id'      => isset( $row['story_id'] ) ? absint( $row['story_id'] ) : 0,
					'phrase_index'  => $pi,
					'ts_gmt'        => isset( $row['ts'] ) ? (string) $row['ts'] : current_time( 'mysql', true ),
					'label'         => isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '',
				),
				array( '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
			);
		}
	}

	protected static function push_ledger( $user_id, $type, $amount, $story_id = 0, $phrase_index = null, $label = '' ) {
		$balance = self::get_balance( $user_id );
		$balance = max( 0, $balance + (int) $amount );
		self::save_balance( $user_id, $balance );

		$ledger   = self::get_ledger( $user_id );
		$ledger[] = array(
			'id'            => uniqid( 'llm_', true ),
			'type'          => sanitize_key( $type ),
			'amount'        => (int) $amount,
			'balance_after' => $balance,
			'story_id'      => absint( $story_id ),
			'phrase_index'  => null === $phrase_index ? null : (int) $phrase_index,
			'ts'            => current_time( 'mysql', true ),
			'label'         => sanitize_text_field( $label ),
		);
		self::save_ledger( $user_id, $ledger );
	}

	public static function set_balance_admin( $user_id, $new_balance, $note = '' ) {
		$new_balance = max( 0, (int) $new_balance );
		$old         = self::get_balance( $user_id );
		$delta       = $new_balance - $old;
		self::save_balance( $user_id, $new_balance );

		$ledger   = self::get_ledger( $user_id );
		$ledger[] = array(
			'id'            => uniqid( 'llm_', true ),
			'type'          => 'admin_adjust',
			'amount'        => $delta,
			'balance_after' => $new_balance,
			'story_id'      => 0,
			'phrase_index'  => null,
			'ts'            => current_time( 'mysql', true ),
			'label'         => $note !== '' ? $note : __( 'Modifica manuale saldo', 'llm-con-tabelle' ),
		);
		self::save_ledger( $user_id, $ledger );
	}

	public static function record_phrase_completion( $user_id, $story_id, $phrase_index ) {
		$story_id     = absint( $story_id );
		$phrase_index = (int) $phrase_index;
		$map          = self::get_phrase_map( $user_id );
		$key          = (string) $story_id;
		if ( ! isset( $map[ $key ] ) ) {
			$map[ $key ] = array();
		}
		if ( in_array( $phrase_index, $map[ $key ], true ) ) {
			return false;
		}
		$map[ $key ][] = $phrase_index;
		sort( $map[ $key ] );
		self::save_phrase_map( $user_id, $map );

		self::push_ledger(
			$user_id,
			'phrase',
			1,
			$story_id,
			$phrase_index,
			__( 'Frase completata (+1)', 'llm-con-tabelle' )
		);

		if ( ! self::$suppress_community ) {
			LLM_Community::record_phrase_completed( $user_id, $story_id, $phrase_index );
		}

		$phrases_story = LLM_Story_Repository::get_phrases( $story_id );
		$total         = count( $phrases_story );
		if ( $total > 0 && count( $map[ $key ] ) >= $total ) {
			self::maybe_complete_story( $user_id, $story_id );
		}

		return true;
	}

	public static function maybe_complete_story( $user_id, $story_id ) {
		$story_id = absint( $story_id );
		$done     = self::get_phrase_map( $user_id );
		$key      = (string) $story_id;
		$need     = LLM_Story_Repository::get_phrases( $story_id );
		if ( empty( $need ) ) {
			return;
		}
		if ( ! isset( $done[ $key ] ) || count( $done[ $key ] ) < count( $need ) ) {
			return;
		}

		$completed = self::get_completed_stories_map( $user_id );
		if ( isset( $completed[ $key ] ) ) {
			return;
		}

		$completed[ $key ] = current_time( 'mysql', true );
		self::save_completed_map( $user_id, $completed );

		if ( ! self::$suppress_community ) {
			LLM_Community::record_story_completed( $user_id, $story_id );
		}

		$reward = (int) get_post_meta( $story_id, LLM_Story_Meta::COIN_REWARD, true );
		if ( $reward > 0 ) {
			self::push_ledger(
				$user_id,
				'story_reward',
				$reward,
				$story_id,
				null,
				__( 'Storia completata (premio)', 'llm-con-tabelle' )
			);
		} else {
			self::push_ledger(
				$user_id,
				'story_done',
				0,
				$story_id,
				null,
				__( 'Storia completata', 'llm-con-tabelle' )
			);
		}
	}

	public static function record_story_unlock( $user_id, $story_id ) {
		$story_id = absint( $story_id );
		$cost     = (int) get_post_meta( $story_id, LLM_Story_Meta::COIN_COST, true );
		$ids      = self::get_unlocked_story_ids( $user_id );
		if ( in_array( $story_id, $ids, true ) ) {
			return false;
		}
		$balance = self::get_balance( $user_id );
		if ( $cost > 0 && $balance < $cost ) {
			return false;
		}
		if ( $cost > 0 ) {
			self::push_ledger( $user_id, 'unlock', -$cost, $story_id, null, __( 'Sblocco storia', 'llm-con-tabelle' ) );
		} else {
			self::push_ledger( $user_id, 'unlock', 0, $story_id, null, __( 'Sblocco gratuito', 'llm-con-tabelle' ) );
		}
		$ids[] = $story_id;
		self::save_unlocked( $user_id, $ids );
		if ( ! self::$suppress_community ) {
			LLM_Community::record_story_started( $user_id, $story_id );
		}
		return true;
	}

	public static function admin_grant_unlock( $user_id, $story_id ) {
		$story_id = absint( $story_id );
		$ids      = self::get_unlocked_story_ids( $user_id );
		if ( in_array( $story_id, $ids, true ) ) {
			return;
		}
		$ids[] = $story_id;
		self::save_unlocked( $user_id, $ids );
		self::push_ledger(
			$user_id,
			'admin_unlock',
			0,
			$story_id,
			null,
			__( 'Sblocco concesso (admin)', 'llm-con-tabelle' )
		);
		if ( ! self::$suppress_community ) {
			LLM_Community::record_story_started( $user_id, $story_id );
		}
	}

	public static function count_completed_phrases( $user_id ) {
		$m = self::get_phrase_map( $user_id );
		$n = 0;
		foreach ( $m as $indices ) {
			$n += count( $indices );
		}
		return $n;
	}

	public static function count_completed_stories( $user_id ) {
		return count( self::get_completed_stories_map( $user_id ) );
	}

	/**
	 * @return array{earned:int, spent:int, phrase_gain:int, story_reward:int}
	 */
	public static function sum_economy( $user_id ) {
		$ledger = self::get_ledger( $user_id );
		$earned = 0;
		$spent  = 0;
		$ph     = 0;
		$sr     = 0;
		foreach ( $ledger as $row ) {
			$a = isset( $row['amount'] ) ? (int) $row['amount'] : 0;
			$t = isset( $row['type'] ) ? $row['type'] : '';
			if ( $a > 0 ) {
				$earned += $a;
				if ( 'phrase' === $t ) {
					$ph += $a;
				}
				if ( 'story_reward' === $t ) {
					$sr += $a;
				}
			} elseif ( $a < 0 ) {
				$spent += abs( $a );
			}
		}
		return array(
			'earned'       => $earned,
			'spent'        => $spent,
			'phrase_gain'  => $ph,
			'story_reward' => $sr,
		);
	}

	/**
	 * Cancella dati LLM utente (tabelle) per simulazione demo.
	 *
	 * @param int $user_id ID utente.
	 */
	public static function wipe_user_tables( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return;
		}
		foreach (
			array(
				'llm_user_phrase_done',
				'llm_user_unlocked_story',
				'llm_user_story_completed',
				'llm_user_coin_ledger',
				'llm_user_bravo_given',
				'llm_user_coin_balance',
			) as $suffix
		) {
			$t = LLM_Tabelle_Database::table( $suffix );
			$wpdb->delete( $t, array( 'user_id' => $user_id ), array( '%d' ) );
		}
	}

	public static function random_simulate( $user_id ) {
		$langs = array_keys( LLM_Languages::get_codes() );
		if ( count( $langs ) < 2 ) {
			$langs = array( 'it', 'en' );
		}
		shuffle( $langs );
		$known  = $langs[0];
		$target = $langs[1];

		update_user_meta( $user_id, LLM_User_Meta::INTERFACE_LANG, $known );
		update_user_meta( $user_id, LLM_User_Meta::LEARNING_LANG, $target );

		self::wipe_user_tables( $user_id );

		$start = wp_rand( 5, 40 );
		self::push_ledger(
			$user_id,
			'sim_seed',
			$start,
			0,
			null,
			__( 'Saldo iniziale (simulazione)', 'llm-con-tabelle' )
		);

		$stories = get_posts(
			array(
				'post_type'      => LLM_STORY_CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'orderby'        => 'rand',
			)
		);

		if ( empty( $stories ) ) {
			return;
		}

		$pick_n = min( count( $stories ), wp_rand( 2, min( 4, count( $stories ) ) ) );
		shuffle( $stories );
		$subset = array_slice( $stories, 0, $pick_n );

		foreach ( $subset as $post ) {
			$sid  = (int) $post->ID;
			$cost = (int) get_post_meta( $sid, LLM_Story_Meta::COIN_COST, true );

			if ( $cost > 0 ) {
				if ( self::get_balance( $user_id ) >= $cost && wp_rand( 0, 1 ) ) {
					self::record_story_unlock( $user_id, $sid );
				} else {
					self::admin_grant_unlock( $user_id, $sid );
				}
			} else {
				self::record_story_unlock( $user_id, $sid );
			}

			$phrases = LLM_Story_Repository::get_phrases( $sid );
			$count   = count( $phrases );
			if ( $count === 0 ) {
				continue;
			}

			$complete_all = (bool) wp_rand( 0, 1 );
			if ( $complete_all ) {
				for ( $i = 0; $i < $count; $i++ ) {
					self::record_phrase_completion( $user_id, $sid, $i );
				}
			} else {
				$how = wp_rand( 1, max( 1, $count - 1 ) );
				$idx = range( 0, $count - 1 );
				shuffle( $idx );
				$idx = array_slice( $idx, 0, $how );
				sort( $idx );
				foreach ( $idx as $pi ) {
					self::record_phrase_completion( $user_id, $sid, (int) $pi );
				}
			}
		}
	}
}
