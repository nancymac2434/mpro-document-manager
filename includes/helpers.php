<?php

function get_documents_for_mentees($user_id, $assigned_client) {
	global $wpdb;

	if (empty($user_id) || empty($assigned_client)) {
		return [];
	}

	// Try to get from cache first (5 minute cache)
	$cache_key = 'mpro_docs_mentee_' . $user_id . '_' . $assigned_client;
	$cached = get_transient($cache_key);
	if ($cached !== false) {
		return $cached;
	}

	// Use direct SQL query for better performance
	$uid_int = (int) $user_id;
	$ser_int = 'i:' . $uid_int . ';';
	$ser_str = '"' . (string) $uid_int . '"';

	$sql = $wpdb->prepare("
		SELECT DISTINCT p.ID
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm_client ON p.ID = pm_client.post_id
		LEFT JOIN {$wpdb->postmeta} pm_users ON p.ID = pm_users.post_id AND pm_users.meta_key = 'document_user_mentee'
		LEFT JOIN {$wpdb->postmeta} pm_roles ON p.ID = pm_roles.post_id AND pm_roles.meta_key = 'document_roles'
		WHERE p.post_type = 'uploaded_document'
		AND p.post_status = 'publish'
		AND pm_client.meta_key = 'assigned_client'
		AND pm_client.meta_value = %s
		AND (
			pm_users.meta_value LIKE %s
			OR pm_users.meta_value LIKE %s
			OR pm_roles.meta_value LIKE %s
		)
	", $assigned_client, '%' . $wpdb->esc_like($ser_int) . '%', '%' . $wpdb->esc_like($ser_str) . '%', '%' . $wpdb->esc_like('mentee') . '%');

	$result = $wpdb->get_col($sql);
	$result = array_map('intval', $result);

	// Cache for 5 minutes
	set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);

	return $result;
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
		return [];
	}

	// Try to get from cache first (5 minute cache)
	$cache_key = 'mpro_docs_mentor_' . $user_id . '_' . $assigned_client;
	$cached = get_transient($cache_key);
	if ($cached !== false) {
		return $cached;
	}

	// Use direct SQL query for better performance
	$uid_int = (int) $user_id;
	$ser_int = 'i:' . $uid_int . ';';
	$ser_str = '"' . (string) $uid_int . '"';

	$sql = $wpdb->prepare("
		SELECT DISTINCT p.ID
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm_client ON p.ID = pm_client.post_id
		LEFT JOIN {$wpdb->postmeta} pm_users ON p.ID = pm_users.post_id AND pm_users.meta_key = 'document_user_mentor'
		LEFT JOIN {$wpdb->postmeta} pm_roles ON p.ID = pm_roles.post_id AND pm_roles.meta_key = 'document_roles'
		WHERE p.post_type = 'uploaded_document'
		AND p.post_status = 'publish'
		AND pm_client.meta_key = 'assigned_client'
		AND pm_client.meta_value = %s
		AND (
			pm_users.meta_value LIKE %s
			OR pm_users.meta_value LIKE %s
			OR pm_roles.meta_value LIKE %s
		)
	", $assigned_client, '%' . $wpdb->esc_like($ser_int) . '%', '%' . $wpdb->esc_like($ser_str) . '%', '%' . $wpdb->esc_like('mentor') . '%');

	$result = $wpdb->get_col($sql);
	$result = array_map('intval', $result);

	// Cache for 5 minutes
	set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);

	return $result;
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

/**
 * Check if user has PM role (contract, pm, or program_manager)
 */
if (!function_exists('mpro_is_pm')) {
	function mpro_is_pm($roles = null) {
		if ($roles === null) {
			$roles = wp_get_current_user()->roles;
		}
		$pm_roles = ['contract', 'pm', 'program_manager'];
		return (bool) array_intersect($pm_roles, (array) $roles);
	}
}

/**
 * Clear all document caches for a given client
 */
if (!function_exists('mpro_clear_document_caches')) {
	function mpro_clear_document_caches($client_id) {
		global $wpdb;

		// Clear all transients matching our cache keys
		$wpdb->query($wpdb->prepare("
			DELETE FROM {$wpdb->options}
			WHERE option_name LIKE %s
		", '_transient_mpro_docs_%_' . $wpdb->esc_like($client_id)));

		$wpdb->query($wpdb->prepare("
			DELETE FROM {$wpdb->options}
			WHERE option_name LIKE %s
		", '_transient_timeout_mpro_docs_%_' . $wpdb->esc_like($client_id)));
	}
}

