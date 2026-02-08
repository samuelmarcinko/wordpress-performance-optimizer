<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the shortcode for interactive hotspots.
 */
function ish_register_shortcodes() {
	add_shortcode( 'interactive_hotspots', 'ish_render_shortcode' );
}

/**
 * Render the interactive hotspots viewer.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function ish_render_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'id' => 0,
		),
		$atts,
		'interactive_hotspots'
	);

	$post_id = absint( $atts['id'] );
	if ( ! $post_id ) {
		return '';
	}

	$project_data = get_post_meta( $post_id, '_ish_project_data', true );
	if ( empty( $project_data['scenes'] ) ) {
		return '';
	}

	wp_enqueue_style(
		'ish-viewer',
		ISH_PLUGIN_URL . 'assets/public/viewer.css',
		array(),
		ISH_PLUGIN_VERSION
	);
	wp_enqueue_script(
		'ish-viewer',
		ISH_PLUGIN_URL . 'assets/public/viewer.js',
		array(),
		ISH_PLUGIN_VERSION,
		true
	);

	$project_key = 'ish-project-' . $post_id;
	$inline_data = 'window.ISHProjects = window.ISHProjects || {};';
	$inline_data .= 'window.ISHProjects[' . wp_json_encode( $project_key ) . '] = ' . wp_json_encode( $project_data ) . ';';
	wp_add_inline_script( 'ish-viewer', $inline_data, 'before' );

	$container_id = 'ish-viewer-' . $post_id;
	return sprintf(
		'<div id="%1$s" class="ish-viewer" data-project="%2$s"></div>',
		esc_attr( $container_id ),
		esc_attr( $project_key )
	);
}
