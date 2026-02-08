<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register save handlers for project data.
 */
function ish_register_save_handlers() {
	add_action( 'save_post_ish_project', 'ish_save_project_data' );
}

/**
 * Save project data from the editor.
 *
 * @param int $post_id Post ID.
 */
function ish_save_project_data( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! isset( $_POST['ish_project_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ish_project_nonce'] ) ), 'ish_project_save' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['ish_project_data'] ) ) {
		return;
	}

	$raw_data = wp_unslash( $_POST['ish_project_data'] );
	$decoded  = json_decode( $raw_data, true );
	if ( null === $decoded ) {
		return;
	}

	$sanitized = ish_sanitize_project_data( $decoded );
	update_post_meta( $post_id, '_ish_project_data', $sanitized );
}
