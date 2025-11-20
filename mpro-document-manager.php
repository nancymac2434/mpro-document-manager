<?php
/**
 * Plugin Name:  MPro Document Manager
 * Plugin URI:   https://template.mentorpro.com/
 * Description:  A document management system for MentorPRO with role-based and client-based access control.
 * Version:      2.0.0
 * Author:       Nancy McNamara
 * Author URI:   https://mentorpro.com/
 * License:      GPL2
 * Text Domain:  mpro-document-manager
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit; // Prevent direct access.

// -----------------------------------------------------------------------------
// Constants
// -----------------------------------------------------------------------------
if ( ! defined('MPRO_DM_VERSION') )     define('MPRO_DM_VERSION', '1.3.2');
if ( ! defined('MPRO_DM_MIN_WP') )      define('MPRO_DM_MIN_WP', '6.0');
if ( ! defined('MPRO_DM_FILE') )        define('MPRO_DM_FILE', __FILE__);
if ( ! defined('MPRO_DM_PATH') )        define('MPRO_DM_PATH', plugin_dir_path(__FILE__));
if ( ! defined('MPRO_DM_URL') )         define('MPRO_DM_URL', plugin_dir_url(__FILE__));
if ( ! defined('MPRO_DM_MAX_FILE_SIZE') ) define('MPRO_DM_MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB in bytes

// -----------------------------------------------------------------------------
// Bootstrap / Safety checks
// -----------------------------------------------------------------------------
function mpro_dm_maybe_deactivate_with_notice() {
	global $wp_version;

	if ( version_compare( PHP_VERSION, '7.4', '<' ) || version_compare( $wp_version, MPRO_DM_MIN_WP, '<' ) ) {
		add_action('admin_notices', function () {
			echo '<div class="notice notice-error"><p>'
			   . esc_html__( 'MPro Document Manager requires WordPress 6.0+ and PHP 7.4+. The plugin has been deactivated.', 'mpro-document-manager' )
			   . '</p></div>';
		});
		deactivate_plugins( plugin_basename( MPRO_DM_FILE ) );
	}
}
register_activation_hook( MPRO_DM_FILE, 'mpro_dm_maybe_deactivate_with_notice' );

// Load translations (if you add /languages).
function mpro_dm_load_textdomain() {
	load_plugin_textdomain( 'mpro-document-manager', false, dirname( plugin_basename( MPRO_DM_FILE ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'mpro_dm_load_textdomain' );

// -----------------------------------------------------------------------------
// Includes
// -----------------------------------------------------------------------------
require_once MPRO_DM_PATH . 'includes/post-types.php';
require_once MPRO_DM_PATH . 'includes/display-documents.php';
require_once MPRO_DM_PATH . 'includes/delete-handler.php';
require_once MPRO_DM_PATH . 'includes/upload-handler.php';
require_once MPRO_DM_PATH . 'includes/scripts.php';
require_once MPRO_DM_PATH . 'includes/upload-page.php';
require_once MPRO_DM_PATH . 'includes/helpers.php';
require_once MPRO_DM_PATH . 'includes/admin-columns.php';

// -----------------------------------------------------------------------------
// Asset registration (CDN + fallbacks + conditional loading)
// -----------------------------------------------------------------------------

/**
 * Register assets so we can enqueue them conditionally.
 */
function mpro_dm_register_assets() {
	// Select2 (CDN).
	wp_register_script(
		'mpro-select2',
		'https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js',
		array('jquery'),
		'4.1.0',
		true
	);
	wp_register_style(
		'mpro-select2-css',
		'https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css',
		array(),
		'4.1.0'
	);

	// SweetAlert2 (CDN).
	wp_register_script(
		'mpro-sweetalert2',
		'https://cdn.jsdelivr.net/npm/sweetalert2@11',
		array('jquery'),
		'11.0.0',
		true
	);

	// Plugin script.
	wp_register_script(
		'mpro-custom-scripts',
		MPRO_DM_URL . 'assets/js/scripts.js',
		array('jquery', 'mpro-sweetalert2', 'mpro-select2'),
		MPRO_DM_VERSION,
		true
	);

	// Plugin styles (if/when you add them).
	wp_register_style(
		'mpro-custom-styles',
		MPRO_DM_URL . 'assets/css/styles.css',
		array('mpro-select2-css'),
		MPRO_DM_VERSION
	);
}
add_action( 'init', 'mpro_dm_register_assets' );

/**
 * Determine if we’re on a page that needs plugin assets.
 * Adjust this as your shortcodes/pages evolve.
 */
function mpro_dm_should_enqueue() : bool {
   // Load on our custom post type single/archive.
   if ( is_singular( 'mpro_document' ) || is_post_type_archive( 'mpro_document' ) ) {
	 return true;
   }
 
   // Load when our shortcode is present on the page.
   if ( is_singular() ) {
	 global $post;
	 if ( $post ) {
	   if ( has_shortcode( $post->post_content, 'wpd_document_manager' )   // <-- add this line
		 || has_shortcode( $post->post_content, 'mpro_document_manager' ) // keep if you ever use it
		 || ( function_exists('has_block') && has_block( 'mpro/document-manager' ) ) ) {
		 return true;
	   }
	 }
   }
 
   return false;
 }

/**
 * Enqueue front-end assets conditionally.
 */

 
function mpro_dm_enqueue_frontend_assets() {
	if ( ! mpro_dm_should_enqueue() ) {
		return;
	}

	wp_enqueue_style( 'mpro-select2-css' );
	wp_enqueue_style( 'mpro-custom-styles' );

	wp_enqueue_script( 'mpro-select2' );
	wp_enqueue_script( 'mpro-sweetalert2' );
	wp_enqueue_script( 'mpro-custom-scripts' );

	// Compute dynamic UI data for JS.
	$current_user = wp_get_current_user();
	$role         = $current_user && ! empty( $current_user->roles ) ? $current_user->roles[0] : 'guest';
	$role_label   = ucfirst( $role );

	// Secure, extensible data for JS.
	wp_localize_script( 'mpro-custom-scripts', 'mproDM', array(
		'version'   => MPRO_DM_VERSION,
		'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
		'nonce'     => wp_create_nonce( 'mpro_dm_nonce' ),
		'maxFileSize' => MPRO_DM_MAX_FILE_SIZE,
		'user'      => array(
			'id'    => get_current_user_id(),
			'role'  => $role,
			'label' => $role_label,
		),
		'i18n'      => array(
			'confirmDelete' => __( 'Are you sure you want to delete this document?', 'mpro-document-manager' ),
			'uploading'     => __( 'Uploading…', 'mpro-document-manager' ),
		),
	) );
}
add_action( 'wp_enqueue_scripts', 'mpro_dm_enqueue_frontend_assets' );

/**
 * Optionally enqueue limited assets in wp-admin where your CPT UI/list tables appear.
 */
function mpro_dm_enqueue_admin_assets( $hook_suffix ) {
	$screen = get_current_screen();
	if ( $screen && in_array( $screen->id, array( 'edit-mpro_document', 'mpro_document' ), true ) ) {
		wp_enqueue_style( 'mpro-select2-css' );
		wp_enqueue_script( 'mpro-select2' );
	}
}
add_action( 'admin_enqueue_scripts', 'mpro_dm_enqueue_admin_assets' );
