<?php
/**
 * Widget Elementor: filtri per Loop Grid storie LLM (categoria + ambito progresso).
 *
 * Il Loop Grid deve avere post type «Storia LLM» e Query ID uguale al valore impostato qui
 * (predefinito: Loop-storie-homepage), gestito da LLM_Elementor_Homepage_Stories_Loop.
 * Opzionale: aggiornamento AJAX del contenitore del loop (stessa URL, sostituzione HTML).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * Widget filtri homepage / catalogo storie.
 */
class LLM_Elementor_Widget_Loop_Stories_Filters extends Widget_Base {

	public function get_name() {
		return 'llm-loop-stories-filters';
	}

	public function get_title() {
		return __( 'Filtri storie LLM (loop)', 'llm-con-tabelle' );
	}

	public function get_icon() {
		return 'eicon-filter';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'llm', 'storie', 'loop', 'filtri', 'categoria' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_main',
			array(
				'label' => __( 'Impostazioni', 'llm-con-tabelle' ),
			)
		);

		$this->add_control(
			'query_id_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => '<p class="elementor-control-field-description">' . esc_html__( 'Nel Loop Grid imposta lo stesso valore in Query → Query ID.', 'llm-con-tabelle' ) . '</p>',
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->add_control(
			'loop_query_id',
			array(
				'label'       => __( 'Query ID del loop (deve coincidere)', 'llm-con-tabelle' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'Loop-storie-homepage',
				'placeholder' => 'Loop-storie-homepage',
			)
		);

		$this->add_control(
			'show_category_filter',
			array(
				'label'   => __( 'Mostra filtro categorie', 'llm-con-tabelle' ),
				'type'    => Controls_Manager::SWITCHER,
				'label_on' => __( 'Sì', 'llm-con-tabelle' ),
				'label_off' => __( 'No', 'llm-con-tabelle' ),
				'return_value' => 'yes',
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_scope_tabs',
			array(
				'label'   => __( 'Mostra schede progresso', 'llm-con-tabelle' ),
				'type'    => Controls_Manager::SWITCHER,
				'label_on' => __( 'Sì', 'llm-con-tabelle' ),
				'label_off' => __( 'No', 'llm-con-tabelle' ),
				'return_value' => 'yes',
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_date_tab',
			array(
				'label'       => __( 'Mostra scheda «Per data»', 'llm-con-tabelle' ),
				'description' => __( 'Ordina tutte le storie per data (ignora gruppi in corso / completate).', 'llm-con-tabelle' ),
				'type'        => Controls_Manager::SWITCHER,
				'label_on'    => __( 'Sì', 'llm-con-tabelle' ),
				'label_off'   => __( 'No', 'llm-con-tabelle' ),
				'return_value' => 'yes',
				'default'     => 'yes',
				'condition'   => array( 'show_scope_tabs' => 'yes' ),
			)
		);

		$this->add_control(
			'ajax_filter',
			array(
				'label'       => __( 'Aggiorna il loop in AJAX', 'llm-con-tabelle' ),
				'description' => __( 'Aggiorna solo il contenitore del loop senza ricaricare la pagina (richiesta a admin-ajax.php con render Elementor lato server).', 'llm-con-tabelle' ),
				'type'        => Controls_Manager::SWITCHER,
				'label_on'    => __( 'Sì', 'llm-con-tabelle' ),
				'label_off'   => __( 'No', 'llm-con-tabelle' ),
				'return_value' => 'yes',
				'default'     => 'yes',
			)
		);

		$this->add_control(
			'loop_target_selector',
			array(
				'label'       => __( 'Selettore CSS del contenitore del loop', 'llm-con-tabelle' ),
				'description' => __( 'In Elementor: contenitore che avvolge solo il Loop Grid → Avanzate → ID CSS (es. llm-stories-loop-home) e qui scrivi #llm-stories-loop-home.', 'llm-con-tabelle' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '#llm-stories-loop-home',
				'placeholder' => '#llm-stories-loop-home',
				'condition'   => array( 'ajax_filter' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Tutte le categorie (taxonomy category), ordinate per nome.
	 *
	 * @return array<int, WP_Term>
	 */
	private function get_all_categories() {
		if ( ! taxonomy_exists( 'category' ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		$filtered = apply_filters( 'llm_loop_stories_filter_category_terms', $terms );
		return is_array( $filtered ) ? $filtered : $terms;
	}

	/**
	 * @return bool
	 */
	private function is_elementor_edit_preview() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}
		$ed = \Elementor\Plugin::$instance->editor;
		return $ed && $ed->is_edit_mode();
	}

	/**
	 * ID del documento Elementor in frontend (pagina con il template).
	 *
	 * @return int
	 */
	private function get_ajax_document_post_id() {
		if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->documents ) {
			$doc = \Elementor\Plugin::$instance->documents->get_current();
			if ( $doc ) {
				return (int) $doc->get_main_post()->ID;
			}
		}
		$qid = (int) get_queried_object_id();
		if ( $qid > 0 ) {
			return $qid;
		}
		return (int) get_the_ID();
	}

	protected function render() {
		if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			echo '<p>' . esc_html__( 'Richiede Elementor Pro (Loop Grid).', 'llm-con-tabelle' ) . '</p>';
			return;
		}

		wp_enqueue_style(
			'llm-loop-stories-filters',
			LLM_TABELLE_URL . 'assets/llm-loop-stories-filters.css',
			array(),
			LLM_TABELLE_VERSION
		);

		$show_cat   = 'yes' === $this->get_settings_for_display( 'show_category_filter' );
		$show_scope = 'yes' === $this->get_settings_for_display( 'show_scope_tabs' );
		$show_date  = 'yes' === $this->get_settings_for_display( 'show_date_tab' );
		$ajax_wanted = 'yes' === $this->get_settings_for_display( 'ajax_filter' ) && ! $this->is_elementor_edit_preview();
		$ajax_doc_id = $ajax_wanted ? $this->get_ajax_document_post_id() : 0;
		$use_ajax    = $ajax_wanted && $ajax_doc_id > 0;
		$target_sel  = trim( (string) $this->get_settings_for_display( 'loop_target_selector' ) );
		if ( $target_sel === '' ) {
			$target_sel = '#llm-stories-loop-home';
		}

		if ( $use_ajax ) {
			wp_enqueue_script( 'llm-loop-stories-filters' );
		}

		$current_cat   = isset( $_GET[ LLM_Elementor_Homepage_Stories_Loop::GET_CAT ] ) ? sanitize_title( wp_unslash( (string) $_GET[ LLM_Elementor_Homepage_Stories_Loop::GET_CAT ] ) ) : '';
		$current_scope = isset( $_GET[ LLM_Elementor_Homepage_Stories_Loop::GET_SCOPE ] ) ? sanitize_key( wp_unslash( (string) $_GET[ LLM_Elementor_Homepage_Stories_Loop::GET_SCOPE ] ) ) : '';

		$wrap_class = 'llm-hs-filters';
		if ( $use_ajax ) {
			$wrap_class .= ' llm-hs-filters--ajax';
		}

		echo '<div class="' . esc_attr( $wrap_class ) . '" data-loop-target="' . esc_attr( $target_sel ) . '"';
		if ( $use_ajax ) {
			echo ' data-llm-ajax-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-llm-nonce="' . esc_attr( wp_create_nonce( LLM_Elementor_Homepage_Stories_Loop::AJAX_NONCE_ACTION ) ) . '" data-post-id="' . esc_attr( (string) (int) $ajax_doc_id ) . '"';
		}
		echo '>';

		if ( $show_cat ) {
			$cats = $this->get_all_categories();
			if ( ! empty( $cats ) ) {
				echo '<nav class="llm-hs-filters__cats" aria-label="' . esc_attr__( 'Categorie', 'llm-con-tabelle' ) . '">';
				$url_all = remove_query_arg( LLM_Elementor_Homepage_Stories_Loop::GET_CAT );
				if ( $current_scope !== '' ) {
					$url_all = add_query_arg( LLM_Elementor_Homepage_Stories_Loop::GET_SCOPE, $current_scope, $url_all );
				}
				$cls = $current_cat === '' ? ' is-active' : '';
				echo '<a class="llm-hs-chip' . esc_attr( $cls ) . '" data-llm-cat="" href="' . esc_url( $url_all ) . '">' . esc_html__( 'Tutte le categorie', 'llm-con-tabelle' ) . '</a>';
				foreach ( $cats as $t ) {
					$slug = $t->slug;
					$url  = add_query_arg(
						array(
							LLM_Elementor_Homepage_Stories_Loop::GET_CAT => $slug,
						),
						remove_query_arg( LLM_Elementor_Homepage_Stories_Loop::GET_CAT )
					);
					if ( $current_scope !== '' ) {
						$url = add_query_arg( LLM_Elementor_Homepage_Stories_Loop::GET_SCOPE, $current_scope, $url );
					}
					$cls = ( $current_cat === $slug ) ? ' is-active' : '';
					echo '<a class="llm-hs-chip' . esc_attr( $cls ) . '" data-llm-cat="' . esc_attr( strtolower( (string) $slug ) ) . '" href="' . esc_url( $url ) . '">' . esc_html( $t->name ) . '</a>';
				}
				echo '</nav>';
			}
		}

		if ( $show_scope ) {
			echo '<nav class="llm-hs-filters__scope" aria-label="' . esc_attr__( 'Stato lettura', 'llm-con-tabelle' ) . '">';

			$url_smart = remove_query_arg( LLM_Elementor_Homepage_Stories_Loop::GET_SCOPE );
			if ( $current_cat !== '' ) {
				$url_smart = add_query_arg( LLM_Elementor_Homepage_Stories_Loop::GET_CAT, $current_cat, $url_smart );
			}
			$cls = ( '' === $current_scope || 'smart' === $current_scope ) ? ' is-active' : '';
			echo '<a class="llm-hs-tab' . esc_attr( $cls ) . '" data-llm-scope="smart" href="' . esc_url( $url_smart ) . '">' . esc_html__( 'Tutte (ordine consigliato)', 'llm-con-tabelle' ) . '</a>';

			$url_active = add_query_arg( LLM_Elementor_Homepage_Stories_Loop::GET_SCOPE, 'active', remove_query_arg( LLM_Elementor_Homepage_Stories_Loop::GET_SCOPE ) );
			if ( $current_cat !== '' ) {
				$url_active = add_query_arg( LLM_Elementor_Homepage_Stories_Loop::GET_CAT, $current_cat, $url_active );
			}
			$cls = ( 'active' === $current_scope ) ? ' is-active' : '';
			echo '<a class="llm-hs-tab' . esc_attr( $cls ) . '" data-llm-scope="active" href="' . esc_url( $url_active ) . '">' . esc_html__( 'Continua storie', 'llm-con-tabelle' ) . '</a>';

			$url_done = add_query_arg( LLM_Elementor_Homepage_Stories_Loop::GET_SCOPE, 'completed', remove_query_arg( LLM_Elementor_Homepage_Stories_Loop::GET_SCOPE ) );
			if ( $current_cat !== '' ) {
				$url_done = add_query_arg( LLM_Elementor_Homepage_Stories_Loop::GET_CAT, $current_cat, $url_done );
			}
			$cls = ( 'completed' === $current_scope ) ? ' is-active' : '';
			echo '<a class="llm-hs-tab' . esc_attr( $cls ) . '" data-llm-scope="completed" href="' . esc_url( $url_done ) . '">' . esc_html__( 'Storie completate', 'llm-con-tabelle' ) . '</a>';

			if ( $show_date ) {
				$url_all_order = add_query_arg( LLM_Elementor_Homepage_Stories_Loop::GET_SCOPE, 'all', remove_query_arg( LLM_Elementor_Homepage_Stories_Loop::GET_SCOPE ) );
				if ( $current_cat !== '' ) {
					$url_all_order = add_query_arg( LLM_Elementor_Homepage_Stories_Loop::GET_CAT, $current_cat, $url_all_order );
				}
				$cls = ( 'all' === $current_scope ) ? ' is-active' : '';
				echo '<a class="llm-hs-tab' . esc_attr( $cls ) . '" data-llm-scope="all" href="' . esc_url( $url_all_order ) . '">' . esc_html__( 'Per data', 'llm-con-tabelle' ) . '</a>';
			}

			echo '</nav>';
		}

		if ( ! is_user_logged_in() && ( $show_scope ) ) {
			echo '<p class="llm-hs-filters__hint">' . esc_html__( 'Accedi per ordinare le storie in base ai tuoi progressi.', 'llm-con-tabelle' ) . '</p>';
		}

		echo '</div>';
	}
}
