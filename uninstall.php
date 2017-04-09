<?php

// Bail if not uninstalling
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wp_filesystem;

// Start an output buffer
ob_start();

// Object cache
if ( file_exists( WP_CONTENT_DIR . '/object-cache.php' ) ) {

	// Flush the entire cache before procedeing
	wp_cache_flush();

	// Delete
	if ( WP_Filesystem( request_filesystem_credentials( '' ) ) ) {
		$wp_filesystem->delete( WP_CONTENT_DIR . '/object-cache.php' );
	}
}

// Output cache
if ( file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) ) {

	// Flush the entire cache before procedeing
	wp_cache_flush();

	// Delete
	if ( WP_Filesystem( request_filesystem_credentials( '' ) ) ) {
		$wp_filesystem->delete( WP_CONTENT_DIR . '/advanced-cache.php' );
	}
}

// End & clean the buffer
ob_end_clean();
