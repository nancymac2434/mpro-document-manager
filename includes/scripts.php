<?php
if (!defined('ABSPATH')) {
	exit;
}

function wpd_enqueue_datatables() {
	wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js', ['jquery'], '1.11.5', true);
	wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css', [], '1.11.5');
}
add_action('wp_enqueue_scripts', 'wpd_enqueue_datatables');

