<?php
/**
 * Pagina singola rivista (CPT llm_magazine).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$mag_id = get_queried_object_id();
if ( $mag_id && class_exists( 'LLM_Magazine_Shortcode' ) ) {
	echo LLM_Magazine_Shortcode::render_magazine( $mag_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

get_footer();
