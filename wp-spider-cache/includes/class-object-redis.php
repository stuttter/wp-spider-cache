<?php

/**
 * Spider Cache Redis
 *
 * @package Plugins/Cache/Object
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WP_Spider_Cache_Object_Base' ) ) :
/**
 * Persistent WordPress Object Cache (in this case) powered by Redis
 *
 * WordPress's Object Cache is used to save on trips to the database. It stores
 * all of the cache data to memory and makes the cache contents available by
 * using a key, which is used to name and later retrieve cached data.
 *
 * This Object Cache replaces WordPress's built in runtime cache by placing it
 * in the wp-content folder, and is loaded via wp-settings.php.
 */
class WP_Spider_Cache_Object extends WP_Spider_Cache_Object_Base {

	/**
	 * Holds the cache engine class name.
	 *
	 * @var string
	 */
	public $engine_class_name = 'Redis';

	/**
	 * Holds the cache daemon class name.
	 *
	 * @var string
	 */
	public $daemon_class_name = 'Redis';

	/**
	 * Holds the cache servers.
	 *
	 * @var string
	 */
	public $servers_global = 'redis_servers';

	/**
	 * Holds the fallback servers.
	 *
	 * @var array
	 */
	public $servers_fallback = array( array( '127.0.0.1', 6379, 20 ) );

	/**
	 * Instantiate the class.
	 *
	 * See: WP_Spider_Cache_Object_Base::__construct().
	 *
	 * @link    http://www.php.net/manual/en/redis.construct.php
	 *
	 * @param   null    $persistent_id      To create an instance that persists between requests, use persistent_id to specify a unique ID for the instance.
	 */
	public function __construct( $persistent_id = NULL ) {

		// Start your engines
		parent::__construct( $persistent_id );
	}
}
endif;
