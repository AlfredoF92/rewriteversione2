<?php
/**
 * Utenti demo WordPress con progressi LLM variati (frasi completate → attività).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Demo_Users {

	const OPTION_GLOBAL = 'llm_demo_wp_users_v3';

	const OPTION_PREV = 'llm_demo_wp_users_v2';

	const OPTION_LEGACY = 'llm_demo_wp_users_v1';

	const USER_SEEDED = '_llm_demo_data_seeded';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_seed' ), 35 );
	}

	public static function maybe_seed() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( get_option( self::OPTION_LEGACY, '' ) === '1' ) {
			delete_option( self::OPTION_LEGACY );
			foreach ( array( 'llm_learn_1', 'llm_learn_2', 'llm_learn_3' ) as $login ) {
				$u = get_user_by( 'login', $login );
				if ( $u ) {
					delete_user_meta( (int) $u->ID, self::USER_SEEDED );
				}
			}
			delete_option( self::OPTION_GLOBAL );
		}

		if ( get_option( self::OPTION_PREV, '' ) === '1' ) {
			delete_option( self::OPTION_PREV );
			foreach ( array( 'llm_learn_1', 'llm_learn_2', 'llm_learn_3' ) as $login ) {
				$u = get_user_by( 'login', $login );
				if ( $u ) {
					delete_user_meta( (int) $u->ID, self::USER_SEEDED );
				}
			}
			delete_option( self::OPTION_GLOBAL );
			delete_option( LLM_Demo_Community::OPTION );
		}

		if ( get_option( self::OPTION_GLOBAL, '' ) === '1' ) {
			return;
		}

		for ( $i = 1; $i <= 3; $i++ ) {
			$login = 'llm_learn_' . $i;
			$email = 'llm_learn_' . $i . '@example.com';

			$user = get_user_by( 'login', $login );
			if ( ! $user ) {
				$pass = wp_generate_password( 24, true, true );
				$uid  = wp_create_user( $login, $pass, $email );
				if ( is_wp_error( $uid ) ) {
					continue;
				}
				wp_update_user(
					array(
						'ID'           => $uid,
						'display_name' => sprintf( __( 'Learner LLM demo %d', 'llm-con-tabelle' ), $i ),
						'role'         => 'subscriber',
					)
				);
				update_user_meta( $uid, LLM_User_Meta::DEMO_FLAG, '1' );
			} else {
				$uid = (int) $user->ID;
			}

			if ( ! get_user_meta( $uid, self::USER_SEEDED, true ) ) {
				self::apply_varied_demo_progress( $uid, $i );
				update_user_meta( $uid, self::USER_SEEDED, '1' );
			}
		}

		update_option( self::OPTION_GLOBAL, '1', false );
	}

	/**
	 * Sblocchi e frasi completate diversi per utente (genera attività «frase completata» / storia completata).
	 *
	 * @param int $user_id ID utente.
	 * @param int $slot    1, 2 o 3 (schema progressi).
	 */
	public static function apply_varied_demo_progress( $user_id, $slot ) {
		$user_id = absint( $user_id );
		$slot    = max( 1, min( 3, (int) $slot ) );

		$langs = array_keys( LLM_Languages::get_codes() );
		if ( count( $langs ) < 2 ) {
			$langs = array( 'it', 'en' );
		}
		$known  = $langs[ ( $slot - 1 ) % count( $langs ) ];
		$target = $langs[ $slot % count( $langs ) ];

		update_user_meta( $user_id, LLM_User_Meta::INTERFACE_LANG, $known );
		update_user_meta( $user_id, LLM_User_Meta::LEARNING_LANG, $target );

		LLM_User_Stats::wipe_user_tables( $user_id );
		LLM_Community::delete_activities_for_user( $user_id );

		$start = 25 + ( $slot * 7 );
		LLM_User_Stats::set_balance_admin( $user_id, $start, __( 'Saldo iniziale (demo LLM)', 'llm-con-tabelle' ) );

		$sids = LLM_Demo_Stories::get_demo_story_ids();
		if ( count( $sids ) < 3 ) {
			$sids = get_posts(
				array(
					'post_type'      => LLM_STORY_CPT,
					'post_status'    => 'publish',
					'posts_per_page' => 3,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);
			$sids = array_map( 'absint', is_array( $sids ) ? $sids : array() );
		}
		if ( count( $sids ) < 1 ) {
			return;
		}

		$s0 = isset( $sids[0] ) ? $sids[0] : 0;
		$s1 = isset( $sids[1] ) ? $sids[1] : 0;
		$s2 = isset( $sids[2] ) ? $sids[2] : 0;

		/**
		 * Chiavi: indici 0,1,2 → story. Valori: array di indici frase 0-based da completare.
		 * unlock: quali storie sbloccare (indici 0,1,2).
		 */
		$plans = array(
			1 => array(
				'unlock'  => array( 0, 1, 2 ),
				'phrases' => array(
					0 => array( 0, 1 ),
					1 => array( 0 ),
					2 => array( 0, 1, 2 ),
				),
			),
			2 => array(
				'unlock'  => array( 0, 1 ),
				'phrases' => array(
					0 => array( 0, 1, 2 ),
					1 => array( 0, 2 ),
				),
			),
			3 => array(
				'unlock'  => array( 0, 1, 2 ),
				'phrases' => array(
					0 => array( 0 ),
					1 => array( 0, 1, 2 ),
					2 => array( 1, 2 ),
				),
			),
		);

		$plan   = $plans[ $slot ];
		$map_id = array( 0 => $s0, 1 => $s1, 2 => $s2 );

		LLM_User_Stats::suppress_community_events( true );
		try {
			foreach ( $plan['unlock'] as $ux ) {
				$sid = isset( $map_id[ $ux ] ) ? $map_id[ $ux ] : 0;
				if ( ! $sid ) {
					continue;
				}
				if ( ! LLM_User_Stats::record_story_unlock( $user_id, $sid ) ) {
					LLM_User_Stats::admin_grant_unlock( $user_id, $sid );
				}
			}

			foreach ( $plan['phrases'] as $story_idx => $phrase_indices ) {
				$sid = isset( $map_id[ $story_idx ] ) ? $map_id[ $story_idx ] : 0;
				if ( ! $sid ) {
					continue;
				}
				$n = count( LLM_Story_Repository::get_phrases( $sid ) );
				if ( $n < 1 ) {
					continue;
				}
				foreach ( $phrase_indices as $pi ) {
					$pi = (int) $pi;
					if ( $pi < 0 || $pi >= $n ) {
						continue;
					}
					LLM_User_Stats::record_phrase_completion( $user_id, $sid, $pi );
				}
			}

			self::append_demo_ledger_extras( $user_id, $slot );
		} finally {
			LLM_User_Stats::suppress_community_events( false );
		}
	}

	/**
	 * Movimenti coin aggiuntivi in ledger (solo demo), diversi per slot.
	 *
	 * @param int $user_id ID utente.
	 * @param int $slot    1–3.
	 */
	private static function append_demo_ledger_extras( $user_id, $slot ) {
		$rows = array(
			1 => array(
				array( 'demo_bonus', 4, __( 'Bonus giornaliero streak (demo)', 'llm-con-tabelle' ) ),
				array( 'demo_spend', -2, __( 'Sfida flash: contributo simbolico (demo)', 'llm-con-tabelle' ) ),
				array( 'demo_bonus', 3, __( 'Regalo community learner (demo)', 'llm-con-tabelle' ) ),
			),
			2 => array(
				array( 'demo_bonus', 5, __( 'Sfida settimanale completata (demo)', 'llm-con-tabelle' ) ),
				array( 'demo_bonus', 2, __( 'Check-in mattutino (demo)', 'llm-con-tabelle' ) ),
			),
			3 => array(
				array( 'demo_bonus', 6, __( 'Obiettivo weekend raggiunto (demo)', 'llm-con-tabelle' ) ),
				array( 'demo_spend', -3, __( 'Extra: revisione guidata (demo)', 'llm-con-tabelle' ) ),
				array( 'demo_bonus', 2, __( 'Ricompensa coerenza studio (demo)', 'llm-con-tabelle' ) ),
			),
		);
		if ( ! isset( $rows[ $slot ] ) ) {
			return;
		}
		foreach ( $rows[ $slot ] as $r ) {
			LLM_User_Stats::demo_append_ledger( $user_id, $r[0], (int) $r[1], $r[2] );
		}
	}
}
