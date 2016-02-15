<?php

/**
 * Plugin name: Spider-Cache
 * Plugin URI:  https://wordpress.org/plugins/wp-spider-cache/
 * Description: Fully rendered pages stored in & served from Memcached.
 * Version:     2.1.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Required files
require_once WP_CONTENT_DIR . '/plugins/wp-spider-cache/includes/functions.php';

// Initialize the caches
if ( ! wp_skip_output_cache() ) {
	wp_cache_init();
	wp_output_cache_init();	
}
