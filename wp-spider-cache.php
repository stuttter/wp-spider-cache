<?php

/**
 * Plugin Name: WP Spider Cache
 * Plugin URI:  https://wordpress.org/plugins/wp-spider-cache/
 * Author:      John James Jacoby
 * Author URI:  https://profiles.wordpress.org/johnjamesjacoby/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Description: A responsible caching layer for WordPress
 * Version:     2.0.0
 * Text Domain: wp-spider-cache
 * Domain Path: /assets/lang/
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Always include the admin UI
if ( is_admin() ) {
	include dirname( __FILE__ ) . 'includes/admin.php';
}
