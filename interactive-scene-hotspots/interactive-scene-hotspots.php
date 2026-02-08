<?php
/**
 * Plugin Name: Interactive Scene Hotspots
 * Description: Create interactive multi-scene image projects with clickable hotspots.
 * Version: 1.0.0
 * Author: OpenAI
 * Text Domain: interactive-scene-hotspots
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ISH_PLUGIN_VERSION', '1.0.0' );
define( 'ISH_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'ISH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once ISH_PLUGIN_PATH . 'includes/sanitize.php';
require_once ISH_PLUGIN_PATH . 'includes/cpt.php';
require_once ISH_PLUGIN_PATH . 'includes/admin-menu.php';
require_once ISH_PLUGIN_PATH . 'includes/save-meta.php';
require_once ISH_PLUGIN_PATH . 'includes/shortcode.php';

add_action( 'plugins_loaded', 'ish_plugin_init' );

/**
 * Initialize plugin features.
 */
function ish_plugin_init() {
	ish_register_project_cpt();
	ish_register_shortcodes();

	if ( is_admin() ) {
		ish_register_admin_menu();
		ish_register_admin_metabox();
		ish_register_save_handlers();
		ish_register_admin_assets();
	}
}
