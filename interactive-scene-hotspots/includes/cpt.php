<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the project custom post type.
 */
function ish_register_project_cpt() {
	$labels = array(
		'name'               => __( 'Projects', 'interactive-scene-hotspots' ),
		'singular_name'      => __( 'Project', 'interactive-scene-hotspots' ),
		'add_new'            => __( 'Add New', 'interactive-scene-hotspots' ),
		'add_new_item'       => __( 'Add New Project', 'interactive-scene-hotspots' ),
		'edit_item'          => __( 'Edit Project', 'interactive-scene-hotspots' ),
		'new_item'           => __( 'New Project', 'interactive-scene-hotspots' ),
		'view_item'          => __( 'View Project', 'interactive-scene-hotspots' ),
		'search_items'       => __( 'Search Projects', 'interactive-scene-hotspots' ),
		'not_found'          => __( 'No projects found', 'interactive-scene-hotspots' ),
		'not_found_in_trash' => __( 'No projects found in Trash', 'interactive-scene-hotspots' ),
	);

	$args = array(
		'labels'             => $labels,
		'public'             => false,
		'show_ui'            => true,
		'show_in_menu'       => 'ish-projects',
		'capability_type'    => 'post',
		'supports'           => array( 'title' ),
		'menu_position'      => 25,
		'menu_icon'          => 'dashicons-location',
	);

	register_post_type( 'ish_project', $args );
}
