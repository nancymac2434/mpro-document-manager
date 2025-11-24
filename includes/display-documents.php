<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Display the MPro document lists in a tabbed interface.
 * Tabs:
 * - Uploaded by You (all roles)
 * - Shared with You (all roles)
 * - Shared Directly with You (PMs only – mentee→PM targeted shares)
 */
function mpro_render_document_tabs() {
   $current_user = wp_get_current_user();
   $user_roles   = (array) $current_user->roles;
   $is_pm        = mpro_is_pm($user_roles);
 
   $uploaded_args = mpro_build_query_args('you_have_shared');
   $shared_args   = mpro_build_query_args($is_pm ? 'all_documents' : 'shared_with_you'); // <-- changed
   $direct_args   = $is_pm ? mpro_build_query_args('shared_direct_with_you') : [];
 
   ?>
   <div class="mpro-doc-tabs">
	 <ul class="mpro-tab-nav" role="tablist">
	   <li class="active" data-tab="uploaded" role="tab" aria-selected="true"><?php echo esc_html__('Uploaded by You','mpro-document-manager'); ?></li>
 
	   <li data-tab="shared" role="tab" aria-selected="false">
		 <?php echo $is_pm
		   ? esc_html__('All Documents','mpro-document-manager')   // <-- renamed for PMs
		   : esc_html__('Shared with You','mpro-document-manager'); ?>
	   </li>
 
	   <?php if ($is_pm): ?>
		 <li data-tab="shared-direct" role="tab" aria-selected="false">
		   <?php echo esc_html__('Shared Directly with You','mpro-document-manager'); ?>
		 </li>
	   <?php endif; ?>
	 </ul>
 
	 <div id="tab-uploaded" class="mpro-tab-content active" role="tabpanel">
	   <div class="mpro-tab-tools">
		 <input type="text"
				id="search-uploaded"
				class="mpro-tab-search"
				placeholder="<?php echo esc_attr__('Search documents...', 'mpro-document-manager'); ?>">
	   </div>
	   <?php display_document_list($uploaded_args, 'you_have_shared', 'uploaded-table'); ?>
	 </div>
 
	 <div id="tab-shared" class="mpro-tab-content" role="tabpanel">
	   <?php if ($is_pm): ?>
		 <p><?php echo esc_html__('View all documents shared in the program.', 'mpro-document-manager'); ?></p>
	   <?php endif; ?>
	   <div class="mpro-tab-tools">
		 <input type="text"
				id="search-shared"
				class="mpro-tab-search"
				placeholder="<?php echo esc_attr__('Search documents...', 'mpro-document-manager'); ?>">
	   </div>
	   <?php
		 display_document_list($shared_args, $is_pm ? 'all_documents' : 'shared_with_you', 'shared-table'); // <-- changed
	   ?>
	 </div>
 
	 <?php if ($is_pm): ?>
	   <div id="tab-shared-direct" class="mpro-tab-content" role="tabpanel">
		 <div class="mpro-tab-tools">
		   <input type="text"
				  id="search-shared-direct"
				  class="mpro-tab-search"
				  placeholder="<?php echo esc_attr__('Search documents...', 'mpro-document-manager'); ?>">
		 </div>
		 <?php display_document_list($direct_args, 'shared_direct_with_you', 'shared-direct-table'); ?>
	   </div>
	 <?php endif; ?>
   </div>
   <?php
 }


/**
 * Build WP_Query args for each table type.
 *
 * @param string $share_table One of: 'you_have_shared' | 'shared_with_you' | 'shared_direct_with_you'
 * @return array
 */
function mpro_build_query_args( $share_table ) : array {
	 $current_user_id = get_current_user_id();
	 $current_user    = wp_get_current_user();
	 $user_roles      = (array) $current_user->roles;
	 $user_role       = function_exists('get_highest_priority_role') ? get_highest_priority_role($user_roles) : ( $user_roles[0] ?? 'subscriber' );
	 $assigned_client = get_user_meta($current_user_id, 'assigned_client', true);
 
	 $args = [
		 'post_type'      => 'uploaded_document',
		 'post_status'    => 'publish',
		 'posts_per_page' => -1,
	 ];
 
	 switch ($share_table) {
 
		 case 'you_have_shared':
			 $args['author'] = $current_user_id;
			 $args['meta_query'] = [
				 [
					 'key'     => 'mentee_note_for',
					 'compare' => 'NOT EXISTS',
				 ],
				 [
					 'key'     => 'assigned_client',
					 'value'   => $assigned_client,
					 'compare' => '=',
				 ],
			 ];
			 break;
 
		 case 'shared_direct_with_you':
			 // PM-only "direct to you" — strictly same client, match serialized int OR string
			 $uid     = (int) $current_user_id;
			 $ser_int = 'i:' . $uid . ';';
			 $ser_str = '"' . (string) $uid . '"';
 
			 $args['meta_query'] = [
				 'relation' => 'AND',
				 [
					 'key'     => 'assigned_client',
					 'value'   => $assigned_client,
					 'compare' => '=',
				 ],
				 [
					 'relation' => 'OR',
					 [ 'key' => 'document_user_contract', 'value' => $ser_int, 'compare' => 'LIKE' ],
					 [ 'key' => 'document_user_contract', 'value' => $ser_str, 'compare' => 'LIKE' ],
				 ],
				 [
					 'key'     => 'mentee_note_for',
					 'compare' => 'NOT EXISTS',
				 ],
			 ];
			 break;
 
		 case 'shared_with_you':
		 default:
			 // normalize the role token to how it's stored in document_roles
			 $normalized_role = in_array($user_role, ['mentee','mentor','group_leader','contract','pm','program_manager'], true)
				 ? $user_role
				 : ($user_roles[0] ?? 'subscriber');
			 if (in_array($normalized_role, ['pm','program_manager'], true)) { $normalized_role = 'contract'; }
			 if ($normalized_role === 'group_leader') { $normalized_role = 'mentor'; }
 
			 // robust direct-target patterns
			 $uid     = (int) $current_user_id;
			 $ser_int = 'i:' . $uid . ';';
			 $ser_str = '"' . (string) $uid . '"';
 
			 // One flat OR group for visibility:
			 // - role-wide membership (document_roles LIKE normalized_role)
			 // - OR any of the three direct-target metas containing this user (int or string forms)
			 $visibility_or = [
				 'relation' => 'OR',
 
				 // role-wide (All Mentors / All PMs / All Mentees)
				 [ 'key' => 'document_roles', 'value' => $normalized_role, 'compare' => 'LIKE' ],
 
				 // mentee direct
				 [ 'key' => 'document_user_mentee',   'value' => $ser_int, 'compare' => 'LIKE' ],
				 [ 'key' => 'document_user_mentee',   'value' => $ser_str, 'compare' => 'LIKE' ],
 
				 // mentor direct
				 [ 'key' => 'document_user_mentor',   'value' => $ser_int, 'compare' => 'LIKE' ],
				 [ 'key' => 'document_user_mentor',   'value' => $ser_str, 'compare' => 'LIKE' ],
 
				 // PM direct
				 [ 'key' => 'document_user_contract', 'value' => $ser_int, 'compare' => 'LIKE' ],
				 [ 'key' => 'document_user_contract', 'value' => $ser_str, 'compare' => 'LIKE' ],
			 ];
 
			 $args['meta_query'] = [
				 'relation' => 'AND',
 
				 // strict client scoping for everything
				 [ 'key' => 'assigned_client', 'value' => $assigned_client, 'compare' => '=' ],
 
				 // never show private mentee notes here
				 [ 'key' => 'mentee_note_for', 'compare' => 'NOT EXISTS' ],
 
				 // visibility
				 $visibility_or,
			 ];
			 break;
			 
		case 'all_documents':
		  // PM-wide view: every document in this client (except mentee private notes)
		  $args['meta_query'] = [
			'relation' => 'AND',
			[
			  'key'     => 'assigned_client',
			  'value'   => $assigned_client,
			  'compare' => '=',
			],
			[
			  'key'     => 'mentee_note_for',
			  'compare' => 'NOT EXISTS',
			],
		  ];
		  $args['orderby'] = 'date';
		  $args['order']   = 'DESC';
		  break;

	 }
 
	 return $args;
 }

/**
 * Table renderer (existing) – lightly adjusted to recognize the new $share_table value
 * and work inside the tab panels. Core logic preserved.
 */
function display_document_list($query_args, $share_table, $table_id) {
	$query_args = array_merge([
		'post_type'      => 'uploaded_document',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
	], $query_args);

	$documents = get_posts($query_args);
	if (!$documents) {
		echo '<p>' . esc_html__('No documents found.', 'mpro-document-manager') . '</p>';
		return;
	}

	$user_roles = wp_get_current_user()->roles;
	$user_role  = function_exists('get_highest_priority_role') ? get_highest_priority_role($user_roles) : ( $user_roles[0] ?? 'subscriber' );

	$is_pm = mpro_is_pm($user_roles);
	
	// Only hide the *role-wide* PM label on "Uploaded by You" when viewer is not a PM
	$hide_pm_role_label = ($share_table === 'you_have_shared') && !$is_pm;
	 	
	$column_heading = ($share_table === 'you_have_shared') ? esc_html__('Who Can View', 'mpro-document-manager') : esc_html__('Shared By', 'mpro-document-manager');
	?>

	<div class="responsive-table-wrapper" style="position:relative; overflow-x:auto;">
		<div style="pointer-events:none; position:absolute; top:0; right:0; width:40px; height:100%; background:linear-gradient(to left, #fff, rgba(255,255,255,0)); z-index:2;"></div>

		<div class="table-responsive">
			<table id="<?php echo esc_attr($table_id); ?>" class="sortable-table" style="width:100%; min-width:600px; border-collapse:collapse;">
				<thead>
					<tr>
						<th data-sort="title" style="border-bottom:1px solid #ddd; text-align:left; cursor:pointer;"><?php echo esc_html__('Title', 'mpro-document-manager'); ?></th>
						<th data-sort="date" style="border-bottom:1px solid #ddd; text-align:left; cursor:pointer;"><?php echo esc_html__('Date', 'mpro-document-manager'); ?></th>
						<th style="border-bottom:1px solid #ddd; text-align:left;"><?php echo $column_heading; ?></th>
						<?php if ( ($user_role === 'contract') && ($share_table !== 'you_have_shared') ) : ?>
							<th style="border-bottom:1px solid #ddd; text-align:left;"><?php echo esc_html__('Who Can View', 'mpro-document-manager'); ?></th>
						<?php else: ?>
							<th style="border-bottom:1px solid #ddd; text-align:left;"></th>
						<?php endif; ?>
						<th style="border-bottom:1px solid #ddd; text-align:left;"><?php echo esc_html__('Actions', 'mpro-document-manager'); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($documents as $doc):
					$title               = esc_html($doc->post_title);
					$file_url            = get_post_meta($doc->ID, 'document_url', true);
					$allowed_roles       = get_post_meta($doc->ID, 'document_roles', true);
					$allowed_user_mentee = get_post_meta($doc->ID, 'document_user_mentee', true);
					$allowed_user_mentor = get_post_meta($doc->ID, 'document_user_mentor', true);
					$allowed_user_pm     = get_post_meta($doc->ID, 'document_user_contract', true);
					$current_user_id     = get_current_user_id();
					$is_owner_or_admin   = ($doc->post_author == $current_user_id) || current_user_can('manage_options');

					$sharer_id   = (int) $doc->post_author;
					$sharer_info = get_userdata($sharer_id);
					$sharer_name = $sharer_info ? $sharer_info->display_name : esc_html__('Unknown', 'mpro-document-manager');

					// Build viewers string
					$viewers = [];
					if (!empty($allowed_roles)) {
						$role_labels = [
							'mentee'           => esc_html__('Mentees', 'mpro-document-manager'),
							'group_leader'     => esc_html__('Mentors', 'mpro-document-manager'),
							'mentor'           => esc_html__('Mentors', 'mpro-document-manager'),
							'contract'         => esc_html__('Program Managers', 'mpro-document-manager'),
							'pm'               => esc_html__('Program Managers', 'mpro-document-manager'),
							'program_manager'  => esc_html__('Program Managers', 'mpro-document-manager'),
						];
						
						
						if (!empty($allowed_roles)) {
						  $roles_array = (array) $allowed_roles;
						
						  // 🔍 Hide ONLY the role-wide PM token when appropriate
						  if ($hide_pm_role_label) {
							$roles_array = array_values(array_filter($roles_array, function($r){
							  return !in_array($r, ['contract','pm','program_manager'], true);
							}));
						  }
						
						  $mapped_roles = array_map(function ($role) use ($role_labels) {
							return isset($role_labels[$role]) ? $role_labels[$role] : $role;
						  }, $roles_array);
						
						  if (!empty($mapped_roles)) {
							$viewers[] = implode(', ', $mapped_roles);
						  }
						}
					}

					$user_list = [];
					foreach (['mentee' => $allowed_user_mentee, 'mentor' => $allowed_user_mentor, 'pm' => $allowed_user_pm] as $type => $ids) {
						if (!empty($ids) && !is_array($ids)) { $ids = maybe_unserialize($ids); }
						foreach ((array) $ids as $uid) {
							$u = get_userdata((int) $uid);
							if ($u) { $user_list[] = $u->display_name; }
						}
					}
					if (!empty($user_list)) {
						$viewers[] = implode(', ', $user_list);
					}

					$post_date = get_the_date('Y-m-d', $doc->ID);
					?>
					<tr>
						<td><a href="<?php echo esc_url($file_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($title); ?></a></td>
						<td><?php echo esc_html($post_date); ?></td>
						<?php if ($share_table === 'you_have_shared'): ?>
						  <?php
							// Flatten the viewers array (role labels + names)
							$viewer_text = implode(', ', array_filter($viewers));
						
							// Get role info from meta for conditional labeling
							$roles = (array) get_post_meta($doc->ID, 'document_roles', true);
							$has_all_mentors = in_array('mentor', $roles, true);
							$has_all_pms      = in_array('contract', $roles, true);
						
							// Case 1: both "All Mentors" and "All PMs" → Everyone
							if ($has_all_mentors && $has_all_pms) {
							  $viewer_text = esc_html__('Everyone', 'mpro-document-manager');
							}
							// Case 2: All PMs + specific mentors → "Mentor [names], Program Managers"
							elseif ($has_all_pms && !empty($allowed_user_mentor)) {
							  $mentor_names = [];
							  $mentor_ids = is_array($allowed_user_mentor) ? $allowed_user_mentor : maybe_unserialize($allowed_user_mentor);
							  foreach ((array) $mentor_ids as $uid) {
								$u = get_userdata((int) $uid);
								if ($u) $mentor_names[] = $u->display_name;
							  }
							  if (!empty($mentor_names)) {
								$viewer_text = esc_html__('', 'mpro-document-manager') . ' ' . esc_html(implode(', ', $mentor_names)) . ', ' . esc_html__('Program Managers', 'mpro-document-manager');
							  }
							}
						
							echo '<td>' . (!empty($viewer_text) ? wp_kses_post($viewer_text) : esc_html__('Program Managers', 'mpro-document-manager')) . '</td>';
						  ?>
						<?php else: ?>
						  <td><?php echo esc_html($sharer_name); ?></td>
						<?php endif; ?>


						<?php if ( ($user_role === 'contract') && ($share_table !== 'you_have_shared') ) : ?>
							<td><?php echo !empty($viewers) ? wp_kses_post(implode('<br>', $viewers)) : esc_html__('Everyone', 'mpro-document-manager'); ?></td>
						<?php else: ?>
							<td></td>
						<?php endif; ?>

						<td>
							<?php if ($is_owner_or_admin || ($user_role === 'contract')): ?>
								<?php $nonce_field = wp_nonce_field('wpd_delete_document', 'wpd_delete_nonce', true, false); ?>
								<form action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" method="post" enctype="multipart/form-data">
									<?php echo str_replace('id="wpd_delete_nonce"', '', $nonce_field); // remove id to avoid duplicates ?>
									<input type="hidden" name="delete_document_id" value="<?php echo esc_attr($doc->ID); ?>">
									<input type="hidden" name="_wp_http_referer" value="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>">
									<button type="submit" name="delete_document" class="delete-note-button" style="color:red; border:none; background:none; cursor:pointer;"><?php echo esc_html__('Delete', 'mpro-document-manager'); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php
}
