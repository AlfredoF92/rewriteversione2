<?php
/**
 * Bacheca: Design System — anteprima classi LLM UI (frontend / shortcode).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_Admin_Design_System
 */
class LLM_Admin_Design_System {

	const PAGE_SLUG = 'llm-design-system';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue( $hook ) {
		if ( strpos( (string) $hook, self::PAGE_SLUG ) === false ) {
			return;
		}
		wp_enqueue_style( 'llm-ui' );
		wp_enqueue_style(
			'llm-admin-design-system',
			LLM_TABELLE_URL . 'assets/llm-admin-design-system.css',
			array( 'llm-ui' ),
			LLM_TABELLE_VERSION
		);
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . LLM_STORY_CPT,
			__( 'Design System', 'llm-con-tabelle' ),
			__( 'Design System', 'llm-con-tabelle' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * @param string   $title   Titolo sezione.
	 * @param string[] $classes Classi da mostrare (una per riga).
	 * @param string   $preview HTML sicuro dell’anteprima (solo stringhe statiche).
	 */
	private static function section( $title, array $classes, $preview ) {
		echo '<section class="llm-ds-section">';
		echo '<h2 class="llm-ds-section__title">' . esc_html( $title ) . '</h2>';
		foreach ( $classes as $c ) {
			echo '<code class="llm-ds-class">' . esc_html( $c ) . '</code>';
		}
		echo '<div class="llm-ds-preview"><div class="llm-ui-scope">';
		echo wp_kses_post( $preview );
		echo '</div></div></section>';
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permesso negato.', 'llm-con-tabelle' ) );
		}

		echo '<div class="wrap llm-ds-wrap">';
		echo '<h1>' . esc_html__( 'Design System', 'llm-con-tabelle' ) . '</h1>';
		echo '<p class="llm-ds-intro">';
		echo esc_html__( 'Design system: palette monocromatica (scala di grigi e nero) per testi, bordi, link, pulsanti ed enfasi — senza colori accesi. Font Manrope (Google Fonts), pulsanti compatti. File: assets/llm-ui.css. Usa .llm-ui-scope sul wrapper; per tema chiaro aggiungi .llm-ui-scope--light. Registra wp_enqueue_style( \'llm-ui\' ) sul frontend.', 'llm-con-tabelle' );
		echo '</p>';

		self::render_palette_table();

		self::section(
			__( 'Tipografia — titoli', 'llm-con-tabelle' ),
			array(
				'.llm-ui-heading.llm-ui-heading--page',
				'.llm-ui-heading.llm-ui-heading--section',
				'.llm-ui-heading.llm-ui-heading--subsection',
				'.llm-ui-heading.llm-ui-heading--card',
			),
			'<h1 class="llm-ui-heading llm-ui-heading--page">' . esc_html__( 'Titolo pagina', 'llm-con-tabelle' ) . '</h1>'
			. '<h2 class="llm-ui-heading llm-ui-heading--section">' . esc_html__( 'Titolo sezione', 'llm-con-tabelle' ) . '</h2>'
			. '<h3 class="llm-ui-heading llm-ui-heading--subsection">' . esc_html__( 'Sottosezione', 'llm-con-tabelle' ) . '</h3>'
			. '<h4 class="llm-ui-heading llm-ui-heading--card">' . esc_html__( 'Titolo card', 'llm-con-tabelle' ) . '</h4>'
		);

		self::section(
			__( 'Tipografia — testo e link', 'llm-con-tabelle' ),
			array(
				'.llm-ui-body',
				'.llm-ui-text',
				'.llm-ui-text--small',
				'.llm-ui-text--muted',
				'.llm-ui-lead',
				'.llm-ui-link',
				'.llm-ui-help',
			),
			'<p class="llm-ui-body">' . esc_html__( 'Paragrafo con classe .llm-ui-body (corpo principale).', 'llm-con-tabelle' ) . '</p>'
			. '<p class="llm-ui-text">' . esc_html__( 'Testo .llm-ui-text.', 'llm-con-tabelle' ) . '</p>'
			. '<p class="llm-ui-text llm-ui-text--small">' . esc_html__( 'Testo piccolo .llm-ui-text--small.', 'llm-con-tabelle' ) . '</p>'
			. '<p class="llm-ui-text llm-ui-text--muted">' . esc_html__( 'Testo attenuato .llm-ui-text--muted.', 'llm-con-tabelle' ) . '</p>'
			. '<p class="llm-ui-lead">' . esc_html__( 'Lead / introduzione con .llm-ui-lead.', 'llm-con-tabelle' ) . '</p>'
			. '<p class="llm-ui-text"><a href="#" class="llm-ui-link" onclick="return false;">' . esc_html__( 'Link .llm-ui-link', 'llm-con-tabelle' ) . '</a></p>'
			. '<p class="llm-ui-help">' . esc_html__( 'Testo di aiuto .llm-ui-help (sotto i campi).', 'llm-con-tabelle' ) . '</p>'
		);

		self::section(
			__( 'Layout — stack e riga', 'llm-con-tabelle' ),
			array(
				'.llm-ui-stack',
				'.llm-ui-stack--lg',
				'.llm-ui-row',
			),
			'<div class="llm-ui-stack">'
			. '<span class="llm-ui-badge">A</span><span class="llm-ui-badge">B</span><span class="llm-ui-badge">C</span>'
			. '</div>'
			. '<div class="llm-ui-row" style="margin-top:0.75rem;">'
			. '<span class="llm-ui-badge">1</span><span class="llm-ui-badge">2</span><span class="llm-ui-badge">3</span>'
			. '</div>'
		);

		self::section(
			__( 'Card', 'llm-con-tabelle' ),
			array(
				'.llm-ui-card',
				'.llm-ui-card__head',
				'.llm-ui-card__body',
			),
			'<article class="llm-ui-card" style="max-width:22rem;">'
			. '<div class="llm-ui-card__head"><span class="llm-ui-heading llm-ui-heading--card" style="margin:0;">' . esc_html__( 'Intestazione card', 'llm-con-tabelle' ) . '</span></div>'
			. '<div class="llm-ui-card__body"><p class="llm-ui-text llm-ui-text--small" style="margin:0;">' . esc_html__( 'Contenuto .llm-ui-card__body.', 'llm-con-tabelle' ) . '</p></div>'
			. '</article>'
		);

		self::section(
			__( 'Badge', 'llm-con-tabelle' ),
			array(
				'.llm-ui-badge',
				'.llm-ui-badge.llm-ui-badge--accent',
				'.llm-ui-badge.llm-ui-badge--outline',
			),
			'<span class="llm-ui-row">'
			. '<span class="llm-ui-badge">' . esc_html__( 'Default', 'llm-con-tabelle' ) . '</span>'
			. '<span class="llm-ui-badge llm-ui-badge--accent">' . esc_html__( 'Enfasi', 'llm-con-tabelle' ) . '</span>'
			. '<span class="llm-ui-badge llm-ui-badge--outline">' . esc_html__( 'Outline', 'llm-con-tabelle' ) . '</span>'
			. '</span>'
		);

		self::section(
			__( 'Separatore', 'llm-con-tabelle' ),
			array( '.llm-ui-hr' ),
			'<p class="llm-ui-text--small" style="margin:0;">' . esc_html__( 'Sopra', 'llm-con-tabelle' ) . '</p>'
			. '<hr class="llm-ui-hr" />'
			. '<p class="llm-ui-text--small" style="margin:0;">' . esc_html__( 'Sotto', 'llm-con-tabelle' ) . '</p>'
		);

		self::section(
			__( 'Tabella', 'llm-con-tabelle' ),
			array(
				'.llm-ui-table-wrap',
				'table.llm-ui-table',
			),
			'<div class="llm-ui-table-wrap"><table class="llm-ui-table"><thead><tr><th>' . esc_html__( 'Colonna', 'llm-con-tabelle' ) . '</th><th>' . esc_html__( 'Valore', 'llm-con-tabelle' ) . '</th></tr></thead><tbody>'
			. '<tr><td>' . esc_html__( 'Esempio', 'llm-con-tabelle' ) . '</td><td>42</td></tr>'
			. '<tr><td>' . esc_html__( 'Altro', 'llm-con-tabelle' ) . '</td><td>—</td></tr>'
			. '</tbody></table></div>'
		);

		self::section(
			__( 'Messaggio / notice', 'llm-con-tabelle' ),
			array( '.llm-ui-notice' ),
			'<p class="llm-ui-notice" style="margin:0;">' . esc_html__( 'Avviso o messaggio informativo con bordo tratteggiato.', 'llm-con-tabelle' ) . '</p>'
		);

		self::section(
			__( 'Campi form — etichetta, input, select, textarea', 'llm-con-tabelle' ),
			array(
				'.llm-ui-field',
				'.llm-ui-field--fixed | .llm-ui-field--grow | .llm-ui-field--search',
				'.llm-ui-label',
				'.llm-ui-input',
				'.llm-ui-input.llm-ui-textarea',
				'.llm-ui-select',
			),
			'<div class="llm-ui-stack" style="max-width:24rem;">'
			. '<div class="llm-ui-field">'
			. '<label class="llm-ui-label" for="llm-ds-demo-input">' . esc_html__( 'Etichetta', 'llm-con-tabelle' ) . '</label>'
			. '<input class="llm-ui-input" type="text" id="llm-ds-demo-input" placeholder="' . esc_attr__( 'Placeholder', 'llm-con-tabelle' ) . '" />'
			. '</div>'
			. '<div class="llm-ui-field">'
			. '<label class="llm-ui-label" for="llm-ds-demo-sel">' . esc_html__( 'Select', 'llm-con-tabelle' ) . '</label>'
			. '<select class="llm-ui-select" id="llm-ds-demo-sel"><option>' . esc_html__( 'Opzione', 'llm-con-tabelle' ) . '</option></select>'
			. '</div>'
			. '<div class="llm-ui-field">'
			. '<label class="llm-ui-label" for="llm-ds-demo-ta">' . esc_html__( 'Textarea', 'llm-con-tabelle' ) . '</label>'
			. '<textarea class="llm-ui-input llm-ui-textarea" id="llm-ds-demo-ta" rows="3"></textarea>'
			. '</div>'
			. '</div>'
		);

		self::section(
			__( 'Barra form orizzontale (filtri)', 'llm-con-tabelle' ),
			array(
				'.llm-ui-form-bar',
				'.llm-ui-form-bar__inner',
				'.llm-ui-form-actions',
			),
			'<div class="llm-ui-form-bar">'
			. '<form class="llm-ui-form-bar__inner" onsubmit="return false;">'
			. '<div class="llm-ui-field llm-ui-field--fixed"><label class="llm-ui-label">' . esc_html__( 'Campo', 'llm-con-tabelle' ) . '</label><select class="llm-ui-select"><option>A</option></select></div>'
			. '<div class="llm-ui-field llm-ui-field--search"><label class="llm-ui-label">' . esc_html__( 'Cerca', 'llm-con-tabelle' ) . '</label><input type="search" class="llm-ui-input" /></div>'
			. '<div class="llm-ui-form-actions"><button type="button" class="llm-ui-btn llm-ui-btn--primary">' . esc_html__( 'Applica', 'llm-con-tabelle' ) . '</button><button type="button" class="llm-ui-btn llm-ui-btn--ghost">' . esc_html__( 'Azzera', 'llm-con-tabelle' ) . '</button></div>'
			. '</form></div>'
		);

		self::section(
			__( 'Pulsanti', 'llm-con-tabelle' ),
			array(
				'.llm-ui-btn',
				'.llm-ui-btn--primary',
				'.llm-ui-btn--secondary',
				'.llm-ui-btn--ghost',
				'.llm-ui-btn--danger',
				'.llm-ui-btn--link',
			),
			'<div class="llm-ui-row">'
			. '<button type="button" class="llm-ui-btn llm-ui-btn--primary">' . esc_html__( 'Primary', 'llm-con-tabelle' ) . '</button>'
			. '<button type="button" class="llm-ui-btn llm-ui-btn--secondary">' . esc_html__( 'Secondary', 'llm-con-tabelle' ) . '</button>'
			. '<button type="button" class="llm-ui-btn llm-ui-btn--ghost">' . esc_html__( 'Ghost', 'llm-con-tabelle' ) . '</button>'
			. '<button type="button" class="llm-ui-btn llm-ui-btn--danger">' . esc_html__( 'Danger', 'llm-con-tabelle' ) . '</button>'
			. '<button type="button" class="llm-ui-btn llm-ui-btn--link">' . esc_html__( 'Link', 'llm-con-tabelle' ) . '</button>'
			. '</div>'
		);

		self::section(
			__( 'Scope e variabili CSS', 'llm-con-tabelle' ),
			array(
				'.llm-ui-scope',
				'Variabili: --llm-ui-gray-*, --llm-ui-text, --llm-ui-link, --llm-ui-accent, --llm-ui-on-accent, --llm-ui-btn-pad-y, …',
			),
			'<p class="llm-ui-text--small" style="margin:0;">' . esc_html__( 'Sovrascrivi le variabili sul wrapper .llm-ui-scope per temi personalizzati (anche inline style).', 'llm-con-tabelle' ) . '</p>'
			. '<pre class="llm-ds-class" style="white-space:pre-wrap;margin-top:0.5rem;">.llm-ui-scope { --llm-ui-accent: var(--llm-ui-gray-300); --llm-ui-on-accent: var(--llm-ui-gray-950); }</pre>'
		);

		echo '<section class="llm-ds-section">';
		echo '<h2 class="llm-ds-section__title">' . esc_html__( 'Variante tema chiaro', 'llm-con-tabelle' ) . '</h2>';
		echo '<code class="llm-ds-class">.llm-ui-scope.llm-ui-scope--light</code>';
		echo '<div class="llm-ds-preview llm-ds-preview--light"><div class="llm-ui-scope llm-ui-scope--light" style="padding:0.75rem;border-radius:6px;">';
		echo '<p class="llm-ui-text--small" style="margin:0 0 0.5rem;">' . esc_html__( 'Stesso markup, palette chiara.', 'llm-con-tabelle' ) . '</p>';
		echo '<div class="llm-ui-row">';
		echo '<button type="button" class="llm-ui-btn llm-ui-btn--primary">' . esc_html__( 'Primary', 'llm-con-tabelle' ) . '</button>';
		echo '<button type="button" class="llm-ui-btn llm-ui-btn--ghost">' . esc_html__( 'Ghost', 'llm-con-tabelle' ) . '</button>';
		echo '</div></div></div></section>';

		self::render_reference_table();

		echo '</div>';
	}

	/**
	 * Tabella token colore (documentazione palette grigia).
	 */
	private static function render_palette_table() {
		echo '<section class="llm-ds-section">';
		echo '<h2 class="llm-ds-section__title">' . esc_html__( 'Palette — grigi, nero e token semantici', 'llm-con-tabelle' ) . '</h2>';
		echo '<p class="llm-ds-intro" style="margin-bottom:1rem;">';
		echo esc_html__( 'Il tema dark usa una scala slate; il tema light una scala zinc. Link, bordi, pulsanti primary/ghost e badge --accent derivano solo da questi token.', 'llm-con-tabelle' );
		echo '</p>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Variabile / gruppo', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Ruolo', 'llm-con-tabelle' ) . '</th>';
		echo '</tr></thead><tbody>';
		$palette_rows = array(
			array( '--llm-ui-gray-50 … --llm-ui-gray-950', __( 'Scala completa (valori diversi in .llm-ui-scope--light).', 'llm-con-tabelle' ) ),
			array( '--llm-ui-text, --llm-ui-muted', __( 'Corpo, etichette attenuate, help.', 'llm-con-tabelle' ) ),
			array( '--llm-ui-border, --llm-ui-border-strong', __( 'Card, input, tabelle, separatori.', 'llm-con-tabelle' ) ),
			array( '--llm-ui-surface, --llm-ui-surface-muted', __( 'Sfondi pannelli e campi.', 'llm-con-tabelle' ) ),
			array( '--llm-ui-accent, --llm-ui-accent-hover, --llm-ui-accent-soft', __( 'Primary button, focus input, badge .llm-ui-badge--accent, anello di focus.', 'llm-con-tabelle' ) ),
			array( '--llm-ui-on-accent', __( 'Testo sul pulsante primary (contrasto su --llm-ui-accent).', 'llm-con-tabelle' ) ),
			array( '--llm-ui-link, --llm-ui-link-hover', __( '.llm-ui-link e .llm-ui-btn--link.', 'llm-con-tabelle' ) ),
			array( '--llm-ui-danger*, --llm-ui-danger-border*', __( 'Pulsante danger in monocromatico (grigio marcato).', 'llm-con-tabelle' ) ),
		);
		foreach ( $palette_rows as $pr ) {
			echo '<tr><td><code>' . esc_html( $pr[0] ) . '</code></td><td>' . esc_html( $pr[1] ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '</section>';
	}

	private static function render_reference_table() {
		$rows = array(
			array( __( 'Colori / token', 'llm-con-tabelle' ), '--llm-ui-gray-*, --llm-ui-text, --llm-ui-muted, --llm-ui-border*, --llm-ui-surface*, --llm-ui-accent*, --llm-ui-link*, --llm-ui-on-accent, --llm-ui-danger*' ),
			array( __( 'Tipografia', 'llm-con-tabelle' ), '.llm-ui-body, .llm-ui-heading, .llm-ui-heading--page, .llm-ui-heading--section, .llm-ui-heading--subsection, .llm-ui-heading--card, .llm-ui-text, .llm-ui-text--small, .llm-ui-text--muted, .llm-ui-lead, .llm-ui-link, .llm-ui-help' ),
			array( __( 'Layout', 'llm-con-tabelle' ), '.llm-ui-stack, .llm-ui-stack--lg, .llm-ui-row' ),
			array( __( 'Card', 'llm-con-tabelle' ), '.llm-ui-card, .llm-ui-card__head, .llm-ui-card__body' ),
			array( __( 'Badge', 'llm-con-tabelle' ), '.llm-ui-badge, .llm-ui-badge--accent, .llm-ui-badge--outline' ),
			array( __( 'Separatore', 'llm-con-tabelle' ), '.llm-ui-hr' ),
			array( __( 'Tabella', 'llm-con-tabelle' ), '.llm-ui-table-wrap, .llm-ui-table' ),
			array( __( 'Notice', 'llm-con-tabelle' ), '.llm-ui-notice' ),
			array( __( 'Form', 'llm-con-tabelle' ), '.llm-ui-form-bar, .llm-ui-form-bar__inner, .llm-ui-field, .llm-ui-field--fixed, .llm-ui-field--grow, .llm-ui-field--search, .llm-ui-label, .llm-ui-input, .llm-ui-textarea, .llm-ui-select, .llm-ui-form-actions' ),
			array( __( 'Pulsanti', 'llm-con-tabelle' ), '.llm-ui-btn, .llm-ui-btn--primary, … — token: --llm-ui-btn-pad-y, --llm-ui-btn-pad-x, --llm-ui-btn-size' ),
			array( __( 'Scope / tema', 'llm-con-tabelle' ), '.llm-ui-scope (dark default), .llm-ui-scope--light' ),
		);

		echo '<div class="llm-ds-ref">';
		echo '<h2>' . esc_html__( 'Riepilogo classi (checklist sostituzione)', 'llm-con-tabelle' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Gruppo', 'llm-con-tabelle' ) . '</th>';
		echo '<th>' . esc_html__( 'Classi', 'llm-con-tabelle' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><td><strong>' . esc_html( $r[0] ) . '</strong></td><td><code>' . esc_html( $r[1] ) . '</code></td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description" style="margin-top:12px;">';
		echo esc_html__( 'File sorgente stili: wp-content/plugins/llm-con-tabelle/assets/llm-ui.css', 'llm-con-tabelle' );
		echo '</p>';
		echo '</div>';
	}
}
