<?php

/**
 * Plugin name: Spider-Cache
 * Plugin URI:  https://wordpress.org/plugins/wp-spider-cache/
 * Description: Objects stored in & served from Memcached.
 * Version:     2.1.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
} // full path, no trailing slash

// Pull in required files
require_once WP_PLUGIN_DIR . '/wp-spider-cache/includes/functions.php';
require_once WP_PLUGIN_DIR . '/wp-spider-cache/includes/class-object-cache.php';
