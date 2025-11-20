<?php
if (!defined('ABSPATH')) {
	exit;
}

function wpd_handle_document_delete() {
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_document'])) {
		if (!isset($_POST['wpd_delete_nonce']) || !wp_verify_nonce($_POST['wpd_delete_nonce'], 'wpd_delete_document')) {
			error_log("Nonce check failed.");
			die('Security check failed.');
		}

		if (!is_user_logged_in()) {
			error_log("User not logged in.");
			die('You must be logged in to delete documents.');
		}

		$document_id = intval($_POST['delete_document_id']);
		if (!$document_id) {
			error_log("Invalid document ID.");
			die('Invalid document ID.');
		}

		$document = get_post($document_id);
		$current_user_id = get_current_user_id();

		$user_roles = wp_get_current_user()->roles;
		$user_role = get_highest_priority_role($user_roles);
		error_log("User role: " . $user_role);

		if ($document && ($document->post_author == $current_user_id || current_user_can('manage_options') || $user_role === 'contract')) {
			error_log("Deleting document ID: " . $document_id);

			$file_url = get_post_meta($document_id, 'document_url', true);
			$upload_dir = wp_upload_dir();
			$file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);

			if (file_exists($file_path)) {
				unlink($file_path);
				error_log("File deleted: " . $file_path);
			} else {
				error_log("File not found at: " . $file_path);
			}

			wp_delete_post($document_id, true);
			//error_log("Post deleted: " . $document_id);

			wp_redirect($_SERVER['REQUEST_URI']);
			exit;
		} else {
			error_log("Permission denied for deletion.");
			die('You do not have permission to delete this document.');
		}
	}
}
add_action('init', 'wpd_handle_document_delete');