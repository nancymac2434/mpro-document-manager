<?php
if (!defined('ABSPATH')) {
	exit;
}

// Secure File Upload Function
function wpd_secure_file_upload($file) {
	$allowed_mime_types = [
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/gif'  => 'gif',
		'application/pdf' => 'pdf',
		'application/msword' => 'doc',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
		'application/vnd.ms-powerpoint' => 'ppt',
		'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
		'application/vnd.ms-excel' => 'xls',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx'
	];

	$file_type = wp_check_filetype($file['name']);
	if (!array_key_exists($file_type['type'], $allowed_mime_types)) {
		return ['error' => "Invalid file type."];
	}

	if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
		return ['error' => "File size exceeds 5MB."];
	}

	return wp_handle_upload($file, ['test_form' => false]);
}

// Handle Upload
function wpd_handle_document_upload() {
		
	if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['upload_document'])) {

		if (session_status() == PHP_SESSION_NONE) {
			session_start();
		}

		// Verify Nonce Security Check
		if (!isset($_POST['wpd_document_nonce']) || !wp_verify_nonce($_POST['wpd_document_nonce'], 'wpd_document_upload')) {
			$_SESSION['wpd_upload_error'] = "Security check failed.";
			wp_redirect($_SERVER['REQUEST_URI']);
			exit;
		}

		if (!is_user_logged_in()) {
			$_SESSION['wpd_upload_error'] = "You must be logged in to upload documents.";
			wp_redirect($_SERVER['REQUEST_URI']);
			exit;
		}

		if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== 0) {
			$_SESSION['wpd_upload_error'] = "No file uploaded or file upload failed.";
			wp_redirect($_SERVER['REQUEST_URI']);
			exit;
		}
		
		// Allowed file extensions
		$allowed_extensions = ['jpg', 'jpeg', 'gif', 'png', 'doc', 'docx', 'pdf', 'ppt', 'pptx', 'xls', 'xlsx'];

		// Extract file extension
		$file_name = $_FILES['document_file']['name'];
		$file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

		// Validate file type
		if (!in_array($file_extension, $allowed_extensions)) {
			$_SESSION['wpd_upload_error'] = "Invalid file type. Allowed types: " . implode(", ", $allowed_extensions);
			wp_redirect($_SERVER['REQUEST_URI']);
			exit;
		}
				
		// Check if neither mentees nor mentors (or their "select all" flags) were provided.
		if ( 
			empty($_POST['document_user_mentee']) && empty($_POST['share_with_all_mentees']) && 
			empty($_POST['document_user_mentor']) && empty($_POST['share_with_all_mentors']) &&
			empty($_POST['document_user_contract']) && empty($_POST['share_with_all_pms'])
		) {
			$_SESSION['wpd_upload_error'] = "You must select at least one mentee, mentor, or program manager to share the document with.";
			wp_redirect($_SERVER['REQUEST_URI']);
			exit;
		}
		
		// Continue with normal file handling...
		$user_id = get_current_user_id();
		$title = sanitize_text_field($_POST['document_title']);

		$upload_dir = wp_upload_dir();
		$custom_dir = $upload_dir['basedir'] . '/mpro-document-manager/';
		if (!file_exists($custom_dir)) {
			wp_mkdir_p($custom_dir);
		}

		// Ensure unique filename
		$new_file_name = sanitize_file_name($file_name);
		$file_path = $custom_dir . $new_file_name;
		$file_url = $upload_dir['baseurl'] . '/mpro-document-manager/' . $new_file_name;

		// Upload file
		if (move_uploaded_file($_FILES['document_file']['tmp_name'], $file_path)) {
			$post_id = wp_insert_post([
				'post_title'  => $title,
				'post_status' => 'publish',
				'post_type'   => 'uploaded_document',
				'post_author' => $user_id
			]);
						
			$current_user_id = get_current_user_id();
			$assigned_client = get_user_meta($current_user_id, 'assigned_client', true);

			update_post_meta($post_id, 'document_url', $file_url);
			update_post_meta($post_id, 'document_type', $file_extension);
			update_post_meta($post_id, 'assigned_client', $assigned_client);

			//  Retrieve existing document_roles to avoid overwriting
			$existing_roles = get_post_meta($post_id, 'document_roles', true);
			if (!is_array($existing_roles)) {
				$existing_roles = []; // Ensure it's an array
			}

			//  Handle "Share with All Mentees"
			if (!empty($_POST['share_with_all_mentees'])) { 
				if (!in_array('mentee', $existing_roles)) {
					$existing_roles[] = 'mentee';
				}
				update_post_meta($post_id, 'document_roles', $existing_roles);
				//  If sharing with all mentees, remove individual mentees
				delete_post_meta($post_id, 'document_user_mentee');
				
			} else {
				//  Store multiple assigned mentees **only** if "Share with All Mentees" is NOT selected
				if (!empty($_POST['document_user_mentee']) && is_array($_POST['document_user_mentee'])) {
					$mentee_ids = array_map('intval', $_POST['document_user_mentee']);
					update_post_meta($post_id, 'document_user_mentee', $mentee_ids);
				}
			}
			
			//  Handle "Share with All Mentors"
			if (!empty($_POST['share_with_all_mentors'])) {
				if (!in_array('mentor', $existing_roles)) {
					$existing_roles[] = 'mentor';
				}
				update_post_meta($post_id, 'document_roles', $existing_roles);

				//  If sharing with all mentors, remove individual mentors
				delete_post_meta($post_id, 'document_user_mentor');
				
			} else {
				//  Store multiple assigned mentors **only** if "Share with All Mentors" is NOT selected
				if (!empty($_POST['document_user_mentor']) && is_array($_POST['document_user_mentor'])) {
					$mentor_ids = array_map('intval', $_POST['document_user_mentor']);				
					update_post_meta($post_id, 'document_user_mentor', $mentor_ids);
				}
			}
			
			// --- Auto-share to ALL PMs whenever a mentee shares with any mentor ---
			$user          = wp_get_current_user();
			$user_roles    = (array) $user->roles;
			$actor_role    = function_exists('get_highest_priority_role') ? get_highest_priority_role($user_roles) : ($user_roles[0] ?? '');
			
			$shared_all_mentors  = !empty($_POST['share_with_all_mentors']);
			$shared_some_mentors = !empty($_POST['document_user_mentor']) && is_array($_POST['document_user_mentor']);
			
			if ($actor_role === 'mentee' && ($shared_all_mentors || $shared_some_mentors)) {
				if (!in_array('contract', $existing_roles, true)) {
					$existing_roles[] = 'contract';
				}
			}

			if ($actor_role === 'mentee' && ($shared_all_mentors || $shared_some_mentors)) {
				// Read existing role visibility
				$roles = get_post_meta($post_id, 'document_roles', true);
				if (!is_array($roles)) {
					$roles = [];
				}
			
				// Ensure 'contract' is present (means: All PMs)
				if (!in_array('contract', $roles, true)) {
					$roles[] = 'contract';
				}
			
				// Save unique, normalized roles
				$roles = array_values(array_unique(array_map('strval', $roles)));
				update_post_meta($post_id, 'document_roles', $roles);
			
				// Optional: remove any per-PM targeting to avoid confusion (not required)
				// delete_post_meta($post_id, 'document_user_contract');
			}

			
			//  Handle "Share with All PMs"
			$incoming_pm_all = !empty($_POST['share_with_all_pms']) ? '1' : '';
			$incoming_pm_ids = !empty($_POST['document_user_contract']) ? (array) $_POST['document_user_contract'] : [];
			
			if (!empty($incoming_pm_all)) {
				if (!in_array('contract', $existing_roles, true)) {
					$existing_roles[] = 'contract';
				}
				update_post_meta($post_id, 'document_roles', $existing_roles);
			
				// If sharing with all PMs, remove individual PM targets
				delete_post_meta($post_id, 'document_user_contract');
			} else {
				if (!empty($incoming_pm_ids)) {
					$pm_ids = array_map('intval', $incoming_pm_ids);
					update_post_meta($post_id, 'document_user_contract', $pm_ids);
				} else {
					delete_post_meta($post_id, 'document_user_contract');
				}
			}

			//  Ensure document_roles is updated only once
			update_post_meta($post_id, 'document_roles', array_unique($existing_roles));
			$_SESSION['wpd_upload_success'] = "Document uploaded successfully!";
		} else {
			$_SESSION['wpd_upload_error'] = "File upload failed. Please try again.";
		}
		
		//  Redirect to prevent re-submission on refresh
		wp_redirect($_SERVER['REQUEST_URI']);
		exit;
	}
}
add_action('init', 'wpd_handle_document_upload');