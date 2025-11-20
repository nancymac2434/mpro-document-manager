<?php
if (!defined('ABSPATH')) {
	exit;
}

function wpd_handle_document_delete() {
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_document'])) {
		if (!isset($_POST['wpd_delete_nonce']) || !wp_verify_nonce($_POST['wpd_delete_nonce'], 'wpd_delete_document')) {
			die('Security check failed.');
		}

		if (!is_user_logged_in()) {
			die('You must be logged in to delete documents.');
		}

		$document_id = intval($_POST['delete_document_id']);
		if (!$document_id) {
			die('Invalid document ID.');
		}

		$document = get_post($document_id);
		$current_user_id = get_current_user_id();

		$user_roles = wp_get_current_user()->roles;
		$user_role = get_highest_priority_role($user_roles);

		if ($document && ($document->post_author == $current_user_id || current_user_can('manage_options') || $user_role === 'contract')) {
			$file_url = get_post_meta($document_id, 'document_url', true);
			$upload_dir = wp_upload_dir();
			$file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);

			if (file_exists($file_path)) {
				unlink($file_path);
			}

			wp_delete_post($document_id, true);

			// Clear document list caches
			$assigned_client = get_post_meta($document_id, 'assigned_client', true);
			if ($assigned_client) {
				mpro_clear_document_caches($assigned_client);
			}

			wp_safe_redirect($_SERVER['REQUEST_URI']);
			exit;
		} else {
			die('You do not have permission to delete this document.');
		}
	}
}
add_action('init', 'wpd_handle_document_delete');