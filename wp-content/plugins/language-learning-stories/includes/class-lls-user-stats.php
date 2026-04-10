<?php
/**
 * Statistiche, progressi e ledger coin utente.
 *
 * @package LLS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLS_User_Stats {

	const LEDGER_MAX = 500;

	/**
	 * @param int $user_id ID utente.
	 * @return int
	 */
	public static function get_balance( $user_id ) {
		$v = get_user_meta( $user_id, LLS_User_Meta::COIN_BALANCE, true );
		return max( 0, (int) $v );
	}

	/**
	 * @param int $user_id ID utente.
	 * @return array<string, array<int>>
	 */
	public static function get_phrase_map( $user_id ) {
		$raw = get_user_meta( $user_id, LLS_User_Meta::PHRASE_DONE, true );
		if ( $raw === '' || $raw === false ) {
			return array();
		}
		$d = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		if ( ! is_array( $d ) ) {
			return array();
		}
		$out = array();
		foreach ( $d as $sid => $indices ) {
			$key = (string) absint( $sid );
			if ( ! is_array( $indices ) ) {
				continue;
			}
			$nums = array();
			foreach ( $indices as $i ) {
				$nums[] = (int) $i;
			}
			sort( $nums );
			$out[ $key ] = array_values( array_unique( $nums ) );
		}
		return $out;
	}

	/**
	 * @param int $user_id ID utente.
	 * @return int[]
	 */
	public static function get_unlocked_story_ids( $user_id ) {
		$raw = get_user_meta( $user_id, LLS_User_Meta::UNLOCKED_STORIES, true );
		if ( $raw === '' || $raw === false ) {
			return array();
		}
		$d = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		if ( ! is_array( $d ) ) {
			return array();
		}
		$ids = array();
		foreach ( $d as $id ) {
			$ids[] = absint( $id );
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * @param int $user_id ID utente.
	 * @return array<string, string> story_id => datetime mysql
	 */
	public static function get_completed_stories_map( $user_id ) {
		$raw = get_user_meta( $user_id, LLS_User_Meta::STORY_COMPLETED, true );
		if ( $raw === '' || $raw === false ) {
			return array();
		}
		$d = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		return is_array( $d ) ? $d : array();
	}

	/**
	 * @param int $user_id ID utente.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_ledger( $user_id ) {
		$raw = get_user_meta( $user_id, LLS_User_Meta::COIN_LEDGER, true );
		if ( $raw === '' || $raw === false ) {
			return array();
		}
		$d = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		return is_array( $d ) ? $d : array();
	}

	/**
	 * @param int $user_id ID utente.
	 * @return void
	 */
	protected static function save_phrase_map( $user_id, array $map ) {
		update_user_meta( $user_id, LLS_User_Meta::PHRASE_DONE, wp_json_encode( $map ) );
	}

	/**
	 * @param int   $user_id ID utente.
	 * @param array $ids     ID storie.
	 */
	protected static function save_unlocked( $user_id, array $ids ) {
		$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
		update_user_meta( $user_id, LLS_User_Meta::UNLOCKED_STORIES, wp_json_encode( $ids ) );
	}

	/**
	 * @param int   $user_id ID utente.
	 * @param array $map     story_id => ts.
	 */
	protected static function save_completed_map( $user_id, array $map ) {
		update_user_meta( $user_id, LLS_User_Meta::STORY_COMPLETED, wp_json_encode( $map ) );
	}

	/**
	 * @param int   $user_id ID utente.
	 * @param array $ledger  Voci.
	 */
	protected static function save_ledger( $user_id, array $ledger ) {
		if ( count( $ledger ) > self::LEDGER_MAX ) {
			$ledger = array_slice( $ledger, -self::LEDGER_MAX );
		}
		update_user_meta( $user_id, LLS_User_Meta::COIN_LEDGER, wp_json_encode( array_values( $ledger ) ) );
	}

	/**
	 * @param int $user_id ID utente.
	 * @param int $balance Saldo.
	 */
	protected static function save_balance( $user_id, $balance ) {
		update_user_meta( $user_id, LLS_User_Meta::COIN_BALANCE, max( 0, (int) $balance ) );
	}

	/**
	 * @param int    $user_id ID utente.
	 * @param string $type    Tipo voce.
	 * @param int    $amount  Importo (positivo o negativo).
	 * @param int    $story_id ID storia.
	 * @param int|null $phrase_index Indice frase.
	 * @param string $label   Etichetta.
	 */
	protected static function push_ledger( $user_id, $type, $amount, $story_id = 0, $phrase_index = null, $label = '' ) {
		$balance = self::get_balance( $user_id );
		$balance = max( 0, $balance + (int) $amount );
		self::save_balance( $user_id, $balance );

		$ledger = self::get_ledger( $user_id );
		$ledger[] = array(
			'id'            => uniqid( 'lls_', true ),
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

	/**
	 * Imposta saldo e registra riga di aggiustamento (admin).
	 *
	 * @param int $user_id ID utente.
	 * @param int $new_balance Nuovo saldo.
	 * @param string $note Nota.
	 */
	public static function set_balance_admin( $user_id, $new_balance, $note = '' ) {
		$new_balance = max( 0, (int) $new_balance );
		$old         = self::get_balance( $user_id );
		$delta       = $new_balance - $old;
		self::save_balance( $user_id, $new_balance );

		$ledger = self::get_ledger( $user_id );
		$ledger[] = array(
			'id'            => uniqid( 'lls_', true ),
			'type'          => 'admin_adjust',
			'amount'        => $delta,
			'balance_after' => $new_balance,
			'story_id'      => 0,
			'phrase_index'  => null,
			'ts'            => current_time( 'mysql', true ),
			'label'         => $note !== '' ? $note : __( 'Modifica manuale saldo', 'language-learning-stories' ),
		);
		self::save_ledger( $user_id, $ledger );
	}

	/**
	 * Registra completamento frase: +1 coin, aggiorna mappa; se ultima frase, premio storia.
	 *
	 * @param int $user_id ID utente.
	 * @param int $story_id ID storia.
	 * @param int $phrase_index Indice 0-based.
	 * @return bool True se nuovo completamento.
	 */
	public static function record_phrase_completion( $user_id, $story_id, $phrase_index ) {
		$story_id      = absint( $story_id );
		$phrase_index  = (int) $phrase_index;
		$map           = self::get_phrase_map( $user_id );
		$key           = (string) $story_id;
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
			__( 'Frase completata (+1)', 'language-learning-stories' )
		);

		LLS_Community::record_phrase_completed( $user_id, $story_id, $phrase_index );

		$phrases_story = LLS_Story_Meta::get_phrases( $story_id );
		$total         = count( $phrases_story );
		if ( $total > 0 && count( $map[ $key ] ) >= $total ) {
			self::maybe_complete_story( $user_id, $story_id );
		}

		return true;
	}

	/**
	 * Se tutte le frasi sono fatte, segna storia completata e assegna premio una sola volta.
	 *
	 * @param int $user_id ID utente.
	 * @param int $story_id ID storia.
	 */
	public static function maybe_complete_story( $user_id, $story_id ) {
		$story_id = absint( $story_id );
		$done     = self::get_phrase_map( $user_id );
		$key      = (string) $story_id;
		$need     = LLS_Story_Meta::get_phrases( $story_id );
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

		LLS_Community::record_story_completed( $user_id, $story_id );

		$reward = (int) get_post_meta( $story_id, LLS_Story_Meta::COIN_REWARD, true );
		if ( $reward > 0 ) {
			self::push_ledger(
				$user_id,
				'story_reward',
				$reward,
				$story_id,
				null,
				__( 'Storia completata (premio)', 'language-learning-stories' )
			);
		} else {
			self::push_ledger(
				$user_id,
				'story_done',
				0,
				$story_id,
				null,
				__( 'Storia completata', 'language-learning-stories' )
			);
		}
	}

	/**
	 * Sblocco storia a pagamento: scala coin e traccia.
	 *
	 * @param int $user_id ID utente.
	 * @param int $story_id ID storia.
	 * @return bool True se ok.
	 */
	public static function record_story_unlock( $user_id, $story_id ) {
		$story_id = absint( $story_id );
		$cost     = (int) get_post_meta( $story_id, LLS_Story_Meta::COIN_COST, true );
		$ids      = self::get_unlocked_story_ids( $user_id );
		if ( in_array( $story_id, $ids, true ) ) {
			return false;
		}
		$balance = self::get_balance( $user_id );
		if ( $cost > 0 && $balance < $cost ) {
			return false;
		}
		if ( $cost > 0 ) {
			self::push_ledger(
				$user_id,
				'unlock',
				-$cost,
				$story_id,
				null,
				__( 'Sblocco storia', 'language-learning-stories' )
			);
		} else {
			self::push_ledger(
				$user_id,
				'unlock',
				0,
				$story_id,
				null,
				__( 'Sblocco gratuito', 'language-learning-stories' )
			);
		}
		$ids[] = $story_id;
		self::save_unlocked( $user_id, $ids );
		LLS_Community::record_story_started( $user_id, $story_id );
		return true;
	}

	/**
	 * Forza sblocco admin (senza pagamento).
	 *
	 * @param int $user_id ID utente.
	 * @param int $story_id ID storia.
	 */
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
			__( 'Sblocco concesso (admin)', 'language-learning-stories' )
		);
		LLS_Community::record_story_started( $user_id, $story_id );
	}

	/**
	 * Conteggio frasi completate totali.
	 *
	 * @param int $user_id ID utente.
	 * @return int
	 */
	public static function count_completed_phrases( $user_id ) {
		$m = self::get_phrase_map( $user_id );
		$n = 0;
		foreach ( $m as $indices ) {
			$n += count( $indices );
		}
		return $n;
	}

	/**
	 * Conteggio storie completate.
	 *
	 * @param int $user_id ID utente.
	 * @return int
	 */
	public static function count_completed_stories( $user_id ) {
		return count( self::get_completed_stories_map( $user_id ) );
	}

	/**
	 * Somma guadagni/spese da ledger per tipo (approssimazione economica).
	 *
	 * @param int $user_id ID utente.
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
			'earned'        => $earned,
			'spent'         => $spent,
			'phrase_gain'   => $ph,
			'story_reward'  => $sr,
		);
	}

	/**
	 * Simulazione random per demo (sovrascrive meta LLS dell’utente).
	 *
	 * @param int $user_id ID utente.
	 */
	public static function random_simulate( $user_id ) {
		$langs = array_keys( LLS_Languages::get_codes() );
		if ( count( $langs ) < 2 ) {
			$langs = array( 'it', 'en' );
		}
		shuffle( $langs );
		$known  = $langs[0];
		$target = $langs[1];

		update_user_meta( $user_id, LLS_User_Meta::INTERFACE_LANG, $known );
		update_user_meta( $user_id, LLS_User_Meta::LEARNING_LANG, $target );

		delete_user_meta( $user_id, LLS_User_Meta::PHRASE_DONE );
		delete_user_meta( $user_id, LLS_User_Meta::UNLOCKED_STORIES );
		delete_user_meta( $user_id, LLS_User_Meta::STORY_COMPLETED );
		delete_user_meta( $user_id, LLS_User_Meta::COIN_LEDGER );
		delete_user_meta( $user_id, LLS_User_Meta::COIN_BALANCE );

		$start = wp_rand( 5, 40 );
		self::push_ledger(
			$user_id,
			'sim_seed',
			$start,
			0,
			null,
			__( 'Saldo iniziale (simulazione)', 'language-learning-stories' )
		);

		$stories = get_posts(
			array(
				'post_type'      => LLS_CPT,
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
			$cost = (int) get_post_meta( $sid, LLS_Story_Meta::COIN_COST, true );

			if ( $cost > 0 ) {
				if ( self::get_balance( $user_id ) >= $cost && wp_rand( 0, 1 ) ) {
					self::record_story_unlock( $user_id, $sid );
				} else {
					self::admin_grant_unlock( $user_id, $sid );
				}
			} else {
				self::record_story_unlock( $user_id, $sid );
			}

			$phrases = LLS_Story_Meta::get_phrases( $sid );
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
