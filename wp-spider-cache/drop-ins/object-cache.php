<?php

/**
 * Plugin name: Spider-Cache
 * Plugin URI:  https://wordpress.org/plugins/wp-spider-cache/
 * Description: Objects stored in & served from persistent cache.
 * Version:     6.0.0 2017-05-20
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Full path, no trailing slash
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}

// Pull in required files
require_once WP_PLUGIN_DIR . '/wp-spider-cache/wp-spider-cache/includes/functions.php';
require_once WP_PLUGIN_DIR . '/wp-spider-cache/wp-spider-cache/includes/class-object-cache.php';
