<?php
if (!defined('ABSPATH')) {
	exit;
}

function wpd_register_document_cpt() {
	register_post_type('uploaded_document', array(
		'labels' => array(
			'name'          => __('Documents'),
			'singular_name' => __('Document')
		),
		'public'       => false, // Not publicly visible
		'show_ui'      => true,  // Show in Admin UI
		'supports'     => array('title', 'editor', 'author'),
		'capability_type' => 'post',
		'map_meta_cap' => true,
		'has_archive'  => false
	));
}
add_action('init', 'wpd_register_document_cpt');