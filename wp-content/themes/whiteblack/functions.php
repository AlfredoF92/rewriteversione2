<?php
/**
 * Register block styles.
 * Theme: WhiteBlack
 */
function whiteblack_register_block_styles() {
	$blocks = array( 'image', 'post-featured-image' );

	foreach( $blocks as $block ) {

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-grayscale',
				'label'        => __( 'Grayscale', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-grayscale{ filter: grayscale(100%); }',
			)
		);
	
		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-blur',
				'label'        => __( 'Blur', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-blur{ filter: blur(5px); }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-brightness',
				'label'        => __( 'Brightness', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-brightness{ filter: brightness(200%); }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-contrast',
				'label'        => __( 'Contrast', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-contrast{ filter: contrast(200%); }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-hue-90',
				'label'        => __( 'Hue-90', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-hue-90{ filter: hue-rotate(90deg); }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-hue-180',
				'label'        => __( 'Hue-180', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-hue-180{ filter: hue-rotate(180deg); }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-hue-270',
				'label'        => __( 'Hue-270', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-hue-270{ filter: hue-rotate(270deg); }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-invert',
				'label'        => __( 'Invert', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-invert{ filter: invert(100%); }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-opacity',
				'label'        => __( 'Opacity', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-opacity{ filter: opacity(30%); }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-saturate',
				'label'        => __( 'Saturate', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-saturate{ filter: saturate(8); }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-sepia',
				'label'        => __( 'Sepia', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-sepia{ filter: sepia(100%); }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-fromgray',
				'label'        => __( 'Gray →', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-fromgray { filter: grayscale(100%); } .is-style-whiteblack-fromgray:hover { filter: grayscale(0%); }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-togray',
				'label'        => __( '→ Gray', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-togray:hover { filter: grayscale(100%); }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-fromblur',
				'label'        => __( 'Blur →', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-fromblur { filter: blur(5px); } .is-style-whiteblack-fromblur:hover { filter: none; }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-toblur',
				'label'        => __( '→ Blur', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-toblur:hover { filter: blur(5px); }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-frombrightness',
				'label'        => __( 'Brightness →', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-frombrightness { filter: brightness(200%); } .is-style-whiteblack-frombrightness:hover { filter: none; }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-tobrightness',
				'label'        => __( '→ Brightness', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-tobrightness:hover { filter: brightness(200%); }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-fromcontrast',
				'label'        => __( 'Contrast →', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-fromcontrast { filter: contrast(200%); } .is-style-whiteblack-fromcontrast:hover { filter: none; }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-tocontrast',
				'label'        => __( '→ Contrast', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-tocontrast:hover { filter: contrast(200%); }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-fromhue90',
				'label'        => __( 'Hue90 →', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-fromhue90 { filter: hue-rotate(90deg); } .is-style-whiteblack-fromhue90:hover { filter: none; }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-tohue90',
				'label'        => __( '→ Hue90', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-tohue90:hover { filter: hue-rotate(90deg); }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-fromhue180',
				'label'        => __( 'Hue180 →', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-fromhue180 { filter: hue-rotate(180deg); } .is-style-whiteblack-fromhue180:hover { filter: none; }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-tohue180',
				'label'        => __( '→ Hue180', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-tohue180:hover { filter: hue-rotate(180deg); }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-fromhue270',
				'label'        => __( 'Hue270 →', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-fromhue270 { filter: hue-rotate(270deg); } .is-style-whiteblack-fromhue270:hover { filter: none; }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-tohue270',
				'label'        => __( '→ Hue270', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-tohue270:hover { filter: hue-rotate(270deg); }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-frominvert',
				'label'        => __( 'Invert →', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-frominvert { filter: invert(100%); } .is-style-whiteblack-frominvert:hover { filter: none; }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-toinvert',
				'label'        => __( '→ Invert', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-toinvert:hover { filter: invert(100%); }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-fromopacity',
				'label'        => __( 'Opacity →', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-fromopacity { filter: opacity(30%); } .is-style-whiteblack-fromopacity:hover { filter: none; }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-toopacity',
				'label'        => __( '→ Opacity', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-toopacity:hover { filter: opacity(30%); }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-fromsaturate',
				'label'        => __( 'Saturate →', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-fromsaturate { filter: saturate(8); } .is-style-whiteblack-fromsaturate:hover { filter: none; }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-tosaturate',
				'label'        => __( '→ Saturate', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-tosaturate:hover { filter: saturate(8); }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-fromsepia',
				'label'        => __( 'Sepia →', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-fromsepia { filter: sepia(100%); } .is-style-whiteblack-fromsepia:hover { filter: none; }',
			)
		);

		register_block_style(
			'core/' . $block,
			array(
				'name'         => 'whiteblack-tosepia',
				'label'        => __( '→ Sepia', 'whiteblack' ),
				'inline_style' => '.is-style-whiteblack-tosepia:hover { filter: sepia(100%); }',
			)
		);

	}
}

add_action( 'init', 'whiteblack_register_block_styles' );



function whiteblack_wp_custom_welcome_page() {
	
	 include(get_template_directory() .'/assets/benvenuto/benvenuto.php');

}

function whiteblack_wp_custom_welcome_menu() {
    add_menu_page(
        __( 'Welcome', 'whiteblack' ),
        __( 'Welcome', 'whiteblack' ),
        'edit_posts', /* min Author role */
        'custom-welcome',
        'whiteblack_wp_custom_welcome_page',
        'dashicons-welcome-learn-more',
        3
    );
}
add_action( 'admin_menu', 'whiteblack_wp_custom_welcome_menu' );
