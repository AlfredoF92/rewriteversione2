<?php
/**
 * Backfill attività + Bravo casuali per utenti demo.
 *
 * @package LLS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLS_Demo_Community {

	const OPTION = 'lls_demo_community_v1';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_seed' ), 45 );
	}

	public static function maybe_seed() {
		if ( get_option( self::OPTION, '' ) === '1' ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$demo_users = get_users(
			array(
				'meta_key'   => LLS_User_Meta::DEMO_FLAG,
				'meta_value' => '1',
				'number'     => 20,
				'fields'     => 'ID',
			)
		);

		if ( empty( $demo_users ) ) {
			$demo_users = get_users(
				array(
					'login__in' => array( 'lls_learn_1', 'lls_learn_2', 'lls_learn_3' ),
					'number'    => 5,
				)
			);
		}

		$uid_list = array();
		foreach ( $demo_users as $u ) {
			$uid = is_object( $u ) ? (int) $u->ID : (int) $u;
			if ( ! $uid ) {
				continue;
			}
			$uid_list[] = $uid;
			LLS_Community::backfill_user_from_progress( $uid );
		}

		$activities = get_posts(
			array(
				'post_type'      => LLS_ACTIVITY_CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 80,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		$uids = array_values( array_filter( array_unique( $uid_list ) ) );

		if ( ! empty( $activities ) && count( $uids ) >= 2 ) {
			$tries = min( 45, count( $activities ) * 3 );
			for ( $i = 0; $i < $tries; $i++ ) {
				$aid = (int) $activities[ array_rand( $activities ) ];
				$post = get_post( $aid );
				if ( ! $post ) {
					continue;
				}
				$author = (int) $post->post_author;
				$liker  = $uids[ array_rand( $uids ) ];
				if ( $liker === $author ) {
					continue;
				}
				LLS_Community::add_bravo( $aid, $liker );
			}
		}

		update_option( self::OPTION, '1', false );
	}
}
