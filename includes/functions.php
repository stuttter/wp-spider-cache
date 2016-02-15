<?php


/**
 * Cancel Spider-Cache
 *
 * @global spider_cache $spider_cache
 */
function spider_cache_cancel() {
	global $spider_cache;

	if ( is_object( $spider_cache ) ) {
		$spider_cache->cancel = true;
	}
}

/**
 * Variants can be set by functions which use early-set globals like $_SERVER to
 * run simple tests. Functions defined in WordPress, plugins, and themes are not
 * available and MUST NOT be used.
 *
 * Example: vary_cache_on_function('return preg_match("/feedburner/i", $_SERVER["HTTP_USER_AGENT"]);');
 *          This will cause spider_cache to cache a variant for requests from Feedburner.
 *
 * Tips for writing $function:
 * - DO NOT use any functions from your theme or plugins. Those files have not
 *   been included. Fatal error.
 * - DO NOT use any WordPress functions except is_admin() and is_multisite().
 *   Fatal error.
 * - DO NOT include or require files from anywhere without consulting expensive
 *   professionals first. Fatal error.
 * - DO NOT use $wpdb, $blog_id, $current_user, etc. These have not been initialized.
 * - DO understand how create_function works. This is how your code is used: create_function('', $function);
 * - DO remember to return something. The return value determines the cache variant.
 */
function vary_cache_on_function( $function = '' ) {
	global $spider_cache;

	if ( empty( $function ) ) {
		die( 'Variant determiner cannot be empty.' );
	}

	if ( preg_match( '/include|require|echo|print|dump|export|open|sock|unlink|`|eval/i', $function ) ) {
		die( 'Illegal word in variant determiner.' );
	}

	if ( ! preg_match( '/\$_/', $function ) ) {
		die( 'Variant determiner should refer to at least one $_ variable.' );
	}

	$spider_cache->add_variant( $function );
}
