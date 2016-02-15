<?php

/**
 * Spider Cache Output Cache
 *
 * @package Plugins/Cache/Output
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
