<?php

/**
 * Plugin name: Spider-Cache
 * Plugin URI:  https://wordpress.org/plugins/wp-spider-cache/
 * Description: Fully rendered pages stored in & served from persistent cache.
 * Version:     6.0.0 2017-05-20
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Full path, no trailing slash
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}

// Required files
require_once WP_PLUGIN_DIR . '/wp-spider-cache/wp-spider-cache/includes/functions.php';

// Initialize the caches
if ( ! wp_skip_output_cache() ) {
	wp_object_cache_init();
	wp_output_cache_init();
}
