<?php

/**
 * Spider Cache Output Cache
 *
 * @package Plugins/Cache/Output
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Checks ********************************************************************/

// Bail if caching not turned on
if ( ! defined( 'WP_CACHE' ) || ( true !== WP_CACHE ) ) {
	return;
}

// Bail if no content directory
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	return;
}

// Never cache interactive scripts or API endpoints.
if ( in_array( basename( $_SERVER['SCRIPT_FILENAME'] ), array(
	'wp-app.php',
	'xmlrpc.php',
	'ms-files.php',
	'wp-cron.php'
) ) ) {
	return;
}

// Never cache JavaScript generators
if ( strstr( $_SERVER['SCRIPT_FILENAME'], 'wp-includes/js' ) ) {
	return;
}

// Never cache when POST data is present.
if ( ! empty( $GLOBALS['HTTP_RAW_POST_DATA'] ) || ! empty( $_POST ) ) {
	return;
}

/** Start *********************************************************************/

// Required files
require_once WP_CONTENT_DIR . '/plugins/wp-spider-cache/includes/functions.php';

// Initialize the caches
wp_cache_init();
wp_output_cache_init();
