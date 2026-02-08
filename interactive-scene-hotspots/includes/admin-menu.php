<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register admin menu for projects.
 */
function ish_register_admin_menu() {
	add_menu_page(
		__( 'Interactive Hotspots', 'interactive-scene-hotspots' ),
		__( 'Interactive Hotspots', 'interactive-scene-hotspots' ),
		'edit_posts',
		'ish-projects',
		'ish_render_projects_page',
		'dashicons-location',
		25
	);
	add_submenu_page(
		'ish-projects',
		__( 'All Projects', 'interactive-scene-hotspots' ),
		__( 'All Projects', 'interactive-scene-hotspots' ),
		'edit_posts',
		'edit.php?post_type=ish_project'
	);
	add_submenu_page(
		'ish-projects',
		__( 'Add New', 'interactive-scene-hotspots' ),
		__( 'Add New', 'interactive-scene-hotspots' ),
		'edit_posts',
		'post-new.php?post_type=ish_project'
	);
}

/**
 * Render the main menu page by redirecting to project list.
 */
function ish_render_projects_page() {
	wp_safe_redirect( admin_url( 'edit.php?post_type=ish_project' ) );
	exit;
}

/**
 * Register the project data metabox.
 */
function ish_register_admin_metabox() {
	add_action(
		'add_meta_boxes',
		function () {
			add_meta_box(
				'ish_project_editor',
				__( 'Interactive Scenes', 'interactive-scene-hotspots' ),
				'ish_render_project_editor',
				'ish_project',
				'normal',
				'high'
			);
		},
		10,
		0
	);

	remove_post_type_support( 'ish_project', 'editor' );
}

/**
 * Render the project editor UI container.
 *
 * @param WP_Post $post Current post.
 */
function ish_render_project_editor( $post ) {
	wp_nonce_field( 'ish_project_save', 'ish_project_nonce' );
	$data = get_post_meta( $post->ID, '_ish_project_data', true );
	if ( empty( $data ) ) {
		$data = array(
			'scenes' => array(),
		);
	}
	?>
	<div id="ish-admin-app" class="ish-admin-app"></div>
	<input type="hidden" id="ish-project-data" name="ish_project_data" value="<?php echo esc_attr( wp_json_encode( $data ) ); ?>" />
	<?php
}

/**
 * Enqueue admin assets only on project editor screens.
 */
function ish_register_admin_assets() {
	add_action(
		'admin_enqueue_scripts',
		function ( $hook ) {
			$screen = get_current_screen();
			if ( ! $screen || 'ish_project' !== $screen->post_type ) {
				return;
			}

			wp_enqueue_style(
				'ish-admin',
				ISH_PLUGIN_URL . 'assets/admin/admin.css',
				array(),
				ISH_PLUGIN_VERSION
			);
			wp_enqueue_media();
			wp_enqueue_script(
				'ish-admin',
				ISH_PLUGIN_URL . 'assets/admin/admin.js',
				array(),
				ISH_PLUGIN_VERSION,
				true
			);

			$project_data = get_post_meta( get_the_ID(), '_ish_project_data', true );
			if ( empty( $project_data ) ) {
				$project_data = array( 'scenes' => array() );
			}

			wp_localize_script(
				'ish-admin',
				'ISHAdminData',
				array(
					'projectData' => $project_data,
					'nonce'       => wp_create_nonce( 'ish_admin_action' ),
				)
			);
		}
	);
}
