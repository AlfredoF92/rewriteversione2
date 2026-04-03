<?php
/**
 * Bacheca: Community LLM — feed attività e Bravo (tabelle).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Admin_Community {

	const PAGE_SLUG = 'llm-community';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue( $hook ) {
		if ( strpos( $hook, self::PAGE_SLUG ) === false ) {
			return;
		}
		wp_enqueue_style(
			'llm-admin-users',
			LLM_TABELLE_URL . 'assets/llm-admin-users.css',
			array(),
			LLM_TABELLE_VERSION
		);
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . LLM_STORY_CPT,
			__( 'Community LLM', 'llm-con-tabelle' ),
			__( 'Community LLM', 'llm-con-tabelle' ),
			'list_users',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Cella Bravi: numero cliccabile (details) con elenco utenti.
	 *
	 * @param int[] $kudos ID utenti.
	 */
	private static function render_bravi_cell( array $kudos ) {
		$n = count( $kudos );
		if ( 0 === $n ) {
			echo '0';
			return;
		}

		echo '<details class="llm-bravi-details">';
		/* translators: %d: number of “Bravo” */
		$summary_label = sprintf( __( '%d — mostra chi ha messo Bravo', 'llm-con-tabelle' ), $n );
		echo '<summary class="llm-bravi-summary" title="' . esc_attr( $summary_label ) . '">';
		echo esc_html( (string) $n );
		echo '</summary>';
		echo '<ul class="llm-bravi-list">';
		foreach ( $kudos as $kid ) {
			$u = get_userdata( (int) $kid );
			if ( ! $u ) {
				echo '<li>' . esc_html( sprintf( /* translators: %d user id */ __( 'Utente #%d', 'llm-con-tabelle' ), (int) $kid ) ) . '</li>';
				continue;
			}
			echo '<li>' . esc_html( $u->display_name ) . ' <span class="description">(' . esc_html( $u->user_login ) . ')</span></li>';
		}
		echo '</ul>';
		echo '</details>';
	}

	public static function render() {
		if ( ! current_user_can( 'list_users' ) ) {
			wp_die( esc_html__( 'Permesso negato.', 'llm-con-tabelle' ) );
		}

		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per   = 30;

		$q = new WP_Query(
			array(
				'post_type'      => LLM_ACTIVITY_CPT,
				'post_status'    => 'publish',
				'posts_per_page' => $per,
				'paged'          => $paged,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		echo '<div class="wrap llm-users-wrap llm-community-wrap">';
		echo '<h1>' . esc_html__( 'Community', 'llm-con-tabelle' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Attività degli utenti: frasi completate, storie completate, storie iniziate (sblocco). Il conteggio Bravi è solo lettura qui; i Bravo si aggiungeranno dal front-end.', 'llm-con-tabelle' ) . '</p>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Data', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Utente', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Tipo', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Dettaglio', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Bravi', 'llm-con-tabelle' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( ! $q->have_posts() ) {
			echo '<tr><td colspan="5">' . esc_html__( 'Nessuna attività. Completando frasi o sbloccando storie compariranno qui.', 'llm-con-tabelle' ) . '</td></tr>';
		} else {
			while ( $q->have_posts() ) {
				$q->the_post();
				$aid    = (int) get_the_ID();
				$author = (int) get_the_author_meta( 'ID' );
				$au     = get_userdata( $author );
				$type   = (string) get_post_meta( $aid, LLM_Community::META_TYPE, true );
				$kudos  = LLM_Community::get_kudos_user_ids( $aid );
				$detail = LLM_Community::format_detail( $aid );

				echo '<tr>';
				echo '<td>' . esc_html( get_the_date( 'Y-m-d H:i' ) ) . '</td>';
				echo '<td>' . ( $au ? esc_html( $au->display_name ) : '—' ) . '</td>';
				echo '<td>' . esc_html( LLM_Community::type_label( $type ) ) . '</td>';
				echo '<td>' . esc_html( $detail ) . '</td>';
				echo '<td class="llm-bravi-cell">';
				self::render_bravi_cell( $kudos );
				echo '</td>';
				echo '</tr>';
			}
			wp_reset_postdata();
		}

		echo '</tbody></table>';

		if ( $q->max_num_pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			$base = admin_url( 'edit.php?post_type=' . rawurlencode( LLM_STORY_CPT ) . '&page=' . rawurlencode( self::PAGE_SLUG ) . '&paged=%#%' );
			echo paginate_links(
				array(
					'base'      => $base,
					'format'    => '',
					'current'   => $paged,
					'total'     => $q->max_num_pages,
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
				)
			);
			echo '</div></div>';
		}

		echo '</div>';
	}
}
