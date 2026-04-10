<?php
/**
 * Tre utenti WordPress demo con dati LLS simulati.
 *
 * @package LLS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLS_Demo_Users {

	const OPTION_GLOBAL = 'lls_demo_wp_users_v1';
	const USER_SEEDED   = '_lls_demo_data_seeded';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_seed' ), 35 );
	}

	/**
	 * Crea (se mancanti) tre utenti subscriber e popola meta + simulazione random.
	 */
	public static function maybe_seed() {
		if ( get_option( self::OPTION_GLOBAL, '' ) === '1' ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		for ( $i = 1; $i <= 3; $i++ ) {
			$login = 'lls_learn_' . $i;
			$email = 'lls_learn_' . $i . '@example.com';

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
						'display_name' => sprintf( /* translators: %d learner number */ __( 'Learner demo %d', 'language-learning-stories' ), $i ),
						'role'         => 'subscriber',
					)
				);
				update_user_meta( $uid, LLS_User_Meta::DEMO_FLAG, '1' );
			} else {
				$uid = (int) $user->ID;
			}

			if ( ! get_user_meta( $uid, self::USER_SEEDED, true ) ) {
				LLS_User_Stats::random_simulate( $uid );
				update_user_meta( $uid, self::USER_SEEDED, '1' );
			}
		}

		update_option( self::OPTION_GLOBAL, '1', false );
	}
}
