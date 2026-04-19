<?php
/**
 * Estende il gruppo query Elementor Pro (Loop, ecc.) con l’opzione Include By “Storie dell’utente loggato”.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\ElementorPro\Modules\QueryControl\Controls\Group_Control_Related' ) ) {

	/**
	 * Sostituisce l’istanza `related-query` registrata da Elementor Pro (stessa chiave).
	 */
	class LLM_Elementor_Group_Control_Related extends \ElementorPro\Modules\QueryControl\Controls\Group_Control_Related {

		/**
		 * Valore salvato nel controllo Include By (allineato a terms / authors).
		 */
		const INCLUDE_VALUE = 'llm_user_unlocked';

		/**
		 * @param string $name Prefisso gruppo.
		 * @return array
		 */
		protected function init_fields_by_name( $name ) {
			$fields = parent::init_fields_by_name( $name );
			if ( isset( $fields['include']['options'] ) && is_array( $fields['include']['options'] ) ) {
				$fields['include']['options'][ self::INCLUDE_VALUE ] = esc_html__( "Storie dell'utente loggato", 'llm-con-tabelle' );
			}
			return $fields;
		}
	}
}
