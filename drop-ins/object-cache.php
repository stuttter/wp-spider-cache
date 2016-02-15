<?php

/**
 * Plugin name: Spider-Cache
 * Plugin URI:  https://wordpress.org/plugins/wp-spider-cache/
 * Description: Objects stored in & served from Memcached.
 * Version:     2.1.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Pull in required files
require_once WP_CONTENT_DIR . '/plugins/wp-spider-cache/includes/functions.php';
require_once WP_CONTENT_DIR . '/plugins/wp-spider-cache/includes/class-object-cache.php';
