<?php

function get_documents_for_mentees($user_id, $assigned_client) {
	global $wpdb;

	//error_log("Debug: Entering get_documents_for_mentees with user_id = {$user_id} and assigned_client = {$assigned_client}");

	if (empty($user_id) || empty($assigned_client)) {
		error_log("Error: user_id or assigned_client is empty");
		return [];
	}

	// Fetch all published documents
	$documents = get_posts([
		'post_type'   => 'uploaded_document',
		'post_status' => 'publish',
		'numberposts' => -1,
	]);
		
//error_log("Debug: Documents array retrieved: " . print_r($documents, true));

	$mentee_assigned_docs = [];

	foreach ($documents as $doc) {
		

		// Check if this document belongs to the given client.
		$doc_client = get_post_meta($doc->ID, 'assigned_client', true);
//error_log("Debug: Document ID {$doc->ID} client is: " .  $doc_client . " assigned client is " . $assigned_client);
		if (($doc_client != $assigned_client) || ($assigned_client === 'demo'))
		if ($doc_client != $assigned_client)
		{
			// Skip documents not assigned to the correct client.
			continue;
		} 
		
		$mentees = get_post_meta($doc->ID, 'document_user_mentee', true);
		$roles = get_post_meta($doc->ID, 'document_roles', true);

		// Log metadata retrieval
		//error_log("Debug: Document ID {$doc->ID} mentee meta retrieved: " . print_r($mentees, true));
		//error_log("Debug: Document ID {$doc->ID} roles meta retrieved: " . print_r($roles, true)); 

		// Ensure `mentees` is an array
		if (!empty($mentees)) {
			if (!is_array($mentees)) {
				$mentees = maybe_unserialize($mentees);
			}

//error_log("Debug: Document ID {$doc->ID} unserialized mentees: " . print_r($mentees, true));

			// If this user is specifically assigned to the document, add it
			if (is_array($mentees) && in_array($user_id, $mentees)) {
				$mentee_assigned_docs[] = $doc->ID;
			}
		}

		// Ensure `roles` is an array
		if (!empty($roles) && !is_array($roles)) {
			$roles = (array) $roles;
		}
//error_log("Debug: Document ID {$doc->ID} to all mentees: " . print_r($roles, true));

		// If the document is shared with all mentees, include it
		if (!empty($roles) && in_array('mentee', $roles)) {
//error_log("Debug: Document ID {$doc->ID} to all mentees: " . print_r($roles, true));
			$mentee_assigned_docs[] = $doc->ID;
		}
	}

	//error_log("Debug: Final mentee_assigned_docs - " . print_r($mentee_assigned_docs, true));

	return array_unique($mentee_assigned_docs);
}

function get_documents_for_pms($assigned_client) {
	global $wpdb;

	$all_docs = $wpdb->get_col($wpdb->prepare("
		SELECT DISTINCT p.ID 
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm 
		ON p.ID = pm.post_id 
		WHERE p.post_type = 'uploaded_document'
		AND p.post_status = 'publish'
		AND pm.meta_key = 'assigned_client'
		AND pm.meta_value = %s
	", $assigned_client));

	return array_unique($all_docs);
}

function get_documents_for_mentors($user_id, $assigned_client) {
	global $wpdb;

	if (empty($user_id) || empty($assigned_client)) {
		error_log("Error: user_id or assigned_client is empty");
		return [];
	}

	// Fetch all published documents
	$documents = get_posts([
		'post_type'   => 'uploaded_document',
		'post_status' => 'publish',
		'numberposts' => -1,
	]);

	$mentor_assigned_docs = [];

	foreach ($documents as $doc) {
		
		// Check if this document belongs to the given client.
		$doc_client = get_post_meta($doc->ID, 'assigned_client', true);
		if (($doc_client != $assigned_client) || ($assigned_client === 'demo'))
		{
			// Skip documents not assigned to the correct client.
			continue;
		}
		
		$mentors = get_post_meta($doc->ID, 'document_user_mentor', true);
		$roles = get_post_meta($doc->ID, 'document_roles', true);

		// Log metadata retrieval
		//error_log("Debug: Document ID {$doc->ID} mentee meta retrieved: " . print_r($mentees, true));
		//error_log("Debug: Document ID {$doc->ID} roles meta retrieved: " . print_r($roles, true));

		// Ensure `mentors` is an array
		if (!empty($mentors)) {
			if (!is_array($mentors)) {
				$mentors = maybe_unserialize($mentors);
			}

			//error_log("Debug: Document ID {$doc->ID} unserialized mentees: " . print_r($mentees, true));

			// If this user is specifically assigned to the document, add it
			if (is_array($mentors) && in_array($user_id, $mentors)) {
				$mentor_assigned_docs[] = $doc->ID;
			}
		}

		// Ensure `roles` is an array
		if (!empty($roles) && !is_array($roles)) {
			$roles = (array) $roles;
		}

		// If the document is shared with all mentees, include it
		if (!empty($roles) && in_array('mentor', $roles)) {
			$mentor_assigned_docs[] = $doc->ID;
		}
	}

	return array_unique($mentor_assigned_docs);
}

function build_user_checkboxes($role, $label, $options = []) {
	// Keep Select2 UI; add robust toggle-all behavior + correct field names
	$options = array_merge(['show_all' => true], (array) $options);

	$current_user_id = get_current_user_id();
	$assigned_client = get_user_meta($current_user_id, 'assigned_client', true);

	// Fetch with WP's stored role (mentors are group_leader), but DO NOT mutate $role
	$fetch_role = ($role === 'mentor') ? 'group_leader' : $role;

	// Field names your upload handler expects (based on ORIGINAL $role)
	if ($role === 'mentor') {
		$items_name = 'document_user_mentor[]';
		$all_name   = 'share_with_all_mentors';
	} elseif ($role === 'contract') {
		$items_name = 'document_user_contract[]';
		$all_name   = 'share_with_all_pms';
	} else { // mentee
		$items_name = 'document_user_mentee[]';
		$all_name   = 'share_with_all_mentees';
	}

	$users = get_users([
		'role'       => $fetch_role,
		'meta_query' => [[ 'key' => 'assigned_client', 'value' => $assigned_client, 'compare' => '=' ]],
		'orderby'    => 'display_name',
		'order'      => 'ASC',
		'fields'     => ['ID','display_name'],
	]);

	if (empty($users)) { echo "<p>No users found.</p>"; return; }

	// Unique container so multiple blocks (PM + Mentor) don't clash on the mentee page
	$container_id = 'mpro-selectbox-' . esc_attr($role) . '-' . wp_generate_uuid4();

	// Classes for scoped selectors
	$select_class = 'mpro-user-select';
	$button_class = 'mpro-select-all';
	$hidden_class = 'mpro-all-flag';
	?>
	<div id="<?php echo $container_id; ?>" class="mpro-selectbox-block" style="margin-bottom:16px;">
		<?php if (!empty($options['show_all'])): ?>
			<!-- Hidden "All" flag that the handler reads -->
			<input type="hidden"
				   class="<?php echo esc_attr($hidden_class); ?>"
				   name="<?php echo esc_attr($all_name); ?>"
				   value="0">
			<div style="margin-bottom:10px;">
				<button type="button" class="<?php echo esc_attr($button_class); ?>" style="padding:5px 10px;">
					Select All <?php echo esc_html($label); ?>s
				</button>
			</div>
		<?php endif; ?>

		<select class="<?php echo esc_attr($select_class); ?>"
				name="<?php echo esc_attr($items_name); ?>"
				multiple="multiple"
				style="width:100%;">
			<?php foreach ($users as $u): ?>
				<option value="<?php echo (int) $u->ID; ?>"><?php echo esc_html($u->display_name); ?></option>
			<?php endforeach; ?>
		</select>
	</div>

	<?php
}




// In mpro-document-manager/includes/helpers.php
if (!function_exists('get_highest_priority_role')) {
	function get_highest_priority_role($roles) {
		$role_priority = ['mentee', 'group_leader', 'contract', 'administrator']; // Prioritization order
		foreach ($role_priority as $role) {
			if (in_array($role, $roles)) {
				return $role;
			}
		}
		return ''; // Default to empty if no match
	}
}

