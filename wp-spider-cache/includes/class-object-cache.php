<?php

/**
 * Spider Cache Object Cache
 *
 * @package Plugins/Cache/Object
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Require the base object
require_once dirname( __FILE__ ) . '/class-object-base.php';

// Memcached
if ( extension_loaded( 'Memcached' ) ) {
	require_once dirname( __FILE__ ) . '/class-object-memcached.php';

// Memory
} else {
	require_once dirname( __FILE__ ) . '/class-object-memory.php';
}
