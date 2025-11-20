<?php

function wpd_document_upload_page() {
	if (!is_user_logged_in()) {
		return '<p>You must be logged in to upload and manage documents.</p>';
	}

	ob_start();
	$user_id = get_current_user_id();
	$user_roles = wp_get_current_user()->roles;
	$user_role = get_highest_priority_role($user_roles);
	$assigned_client = get_user_meta($user_id, 'assigned_client', true);

	/****
	if (!in_array($assigned_client, ['ngyc-wa' , 'ngyc-pa' ,'ngyc-va' , 'ngyc-disc' , 'ngyc-ca' , 'ngyc-bg' , 'id-youth-challenge' , 'sadie', 'demo', 'mentorpro']) && !current_user_can('manage_options')) 
	{
		return '<p>The document manager tool is coming soon! If you would like to test it out before it goes live, <a href="mailto:support@mentorpro.com">send us an email and let us know!</a></p>';
	}
	*******/
	?>
	
	<div style="display: flex; flex-direction: column; gap: 20px; background-color: #F0F0F0;">

		<!-- 🔹 Section 1: Upload Form -->
		<div class="mpro-doc-grey-box">
			<?php
			if (session_status() == PHP_SESSION_NONE) {
				session_start();
			}

			if (!empty($_SESSION['wpd_upload_error'])) {
				echo '<div style="color: red; padding: 10px; border: 1px solid red; background: #ffe6e6;">' . esc_html($_SESSION['wpd_upload_error']) . '</div>';
				unset($_SESSION['wpd_upload_error']);
			}

			if (!empty($_SESSION['wpd_upload_success'])) {
				echo '<div style="color: green; padding: 10px; border: 1px solid green; background: #e6ffe6;">' . esc_html($_SESSION['wpd_upload_success']) . '</div>';
				unset($_SESSION['wpd_upload_success']);
			}


			
			?>
			<h2>Upload a Document</h2>
			<strong style="font-size: 20px; font-weight: 600;">Document Title:</strong>
			<?php 
			$user = wp_get_current_user();
			if ( $user_role == 'contract') $display_role = "PM";
			if ( $user_role == 'group_leader') { $user_role = 'mentor'; $display_role = "Mentor"; }
			if ( $user_role == 'mentee') $display_role = "Mentee";
			$display_name = $user->display_name;

			//echo '<p>Admin Debugging info -> User: ' . $display_name . ', Role: ' . $display_role . ', Client: ' . $assigned_client . '</p>'; 
			
			?>
			<form action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" method="post" enctype="multipart/form-data">
				<?php wp_nonce_field('wpd_document_upload', 'wpd_document_nonce'); ?>
				
				<input type="text" name="document_title" placeholder="Document Title" required style="width:100%; margin-bottom: 10px;">
				<div class="mpro-drop-wrap">
				  <div id="mpro-dropzone" class="mpro-dropzone" role="button" tabindex="0" aria-label="Drag and drop, or browse">
					<div class="mpro-drop-inner">
					  <svg aria-hidden="true" width="32" height="32" viewBox="0 0 24 24"><path d="M12 16V4m0 12l-4-4m4 4l4-4M6 20h12"/></svg>
					  <div class="mpro-drop-text">
						<strong>Drag & drop</strong> your file here
						<div>or <button type="button" id="mpro-browse-btn" class="mpro-link">browse</button></div>
						<small>Accepted: JPG, PNG, GIF, DOC, DOCX, PDF, PPT, PPTX, XLS, XLSX · Max 5MB</small>
					  </div>
					</div>
					<div id="mpro-preview" class="mpro-preview" aria-live="polite"></div>
				  </div>
				
				  <!-- Keep the same name the PHP expects; we’ll set this programmatically -->
				  <input type="file"
						 id="document_file"
						 name="document_file"
						 accept=".jpg,.jpeg,.gif,.png,.doc,.docx,.pdf,.ppt,.pptx,.xls,.xlsx"
						 style="display:none;">
				</div>

			
				 <div class="mpro-doc-title">Share with:</div>

				 <?php if ($user_role === 'mentee') { ?>
				  <div class="mpro-doc-white-box">
				   <label><div class="mpro-doc-title">Program Managers</div><em>Any document shared with Program Managers will not be visible to Mentors.</em></label>
					<?php build_user_checkboxes('contract', "Program Manager", ['show_all' => true]); ?>
					 </div>

				  <div class="mpro-doc-white-box">
				   <label><div class="mpro-doc-title">Mentors</div><em>Any document shared with Mentors will also be visible to Program Managers.</em></label>
				  <?php build_user_checkboxes('mentor', "Mentor", ['show_all' => true]); ?>
				  </div>
				

				<?php } elseif ($user_role === 'mentor') { ?>
				  <div class="mpro-doc-white-box">
				  <label><div class="mpro-doc-title">Mentees</div><em>Any document shared with Mentees will also be visible to Program Managers.</em></label>
				  <?php build_user_checkboxes('mentee', "Mentee"); ?>
				  </div>
				
				<?php } elseif ($user_role === 'contract') { ?>
				  <div class="mpro-doc-white-box">
				   <label><div class="mpro-doc-title">Mentee</div><em>Any document shared with Mentees are visible to all Program Managers.</em></label>
				  <?php build_user_checkboxes('mentee', "Mentee"); ?>
				  </div>
				<?php } ?>
				
				<input type="submit" name="upload_document" value="Upload" style="width:100%; max-width: 200px; background-color: #2B4D59; color: white; border: none; padding: 10px; cursor: pointer; display: block; margin: 0 auto;">
			</form>
			
		</div>
		

		<!-- 🔹 Section 2: Documents Uploaded Tabs -->
		<div class="mpro-doc-grey-box">
		<?php		
			if ( function_exists('mpro_render_document_tabs') ) {
				  mpro_render_document_tabs();
			}
		?>
		</div>

	</div>

	<?php
	return ob_get_clean();
}
add_shortcode('wpd_document_manager', 'wpd_document_upload_page');