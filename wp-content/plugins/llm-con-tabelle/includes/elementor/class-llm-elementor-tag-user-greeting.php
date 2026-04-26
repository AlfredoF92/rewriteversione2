<?php
/**
 * Dynamic Tag Elementor — saluto utente ("Ciao, username").
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Modules\DynamicTags\Module as DynamicTagsModule;

class LLM_Elementor_Tag_User_Greeting extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'llm-user-greeting';
	}

	public function get_title() {
		return __( 'LLM User — Ciao, username', 'llm-con-tabelle' );
	}

	public function get_group() {
		return array( 'llm-user' );
	}

	public function get_categories() {
		return array( DynamicTagsModule::TEXT_CATEGORY );
	}

	public function render() {
		$name = '';
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$name = ( $user && $user->exists() ) ? (string) $user->display_name : '';
			if ( '' === trim( $name ) && $user && $user->exists() ) {
				$name = (string) $user->user_login;
			}
		}
		$name = trim( (string) $name );
		if ( '' === $name ) {
			$name = __( 'Utente', 'llm-con-tabelle' );
		}

		$ui_lang = class_exists( 'LLM_Category_Translations' )
			? LLM_Category_Translations::current_user_lang()
			: 'it';
		$greetings = array(
			'it' => 'Ciao, %s',
			'en' => 'Hi, %s',
			'pl' => 'Czesc, %s',
			'es' => 'Hola, %s',
		);
		$tpl   = isset( $greetings[ $ui_lang ] ) ? $greetings[ $ui_lang ] : $greetings['it'];
		$label = sprintf( $tpl, $name );

		echo esc_html( $label );
	}
}
