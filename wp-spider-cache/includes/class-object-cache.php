<?php

/**
 * Spider Cache Object Cache
 *
 * @package Plugins/Cache/Object
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Require the base object
require_once dirname( __FILE__ ) . '/base-object.php';

if ( class_exists( 'WP_Spider_Cache_Object_Base' ) ) :
/**
 * Persistent WordPress Object Cache (in this case) powered by Memcached
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
	 * @var Memcache
	 */
	public $engine_class_name = 'Memcache';

	/**
	 * Holds the cache daemon class name.
	 *
	 * @var Memcached
	 */
	public $daemon_class_name = 'Memcached';

	/**
	 * Holds the cache servers.
	 *
	 * @var Memcached
	 */
	public $servers_global = 'memcached_servers';

	/**
	 * Instantiate the class.
	 *
	 * Instantiates the class and returns adds the servers specified
	 * in the $memcached_servers global array.
	 *
	 * @link    http://www.php.net/manual/en/memcached.construct.php
	 *
	 * @param   null    $persistent_id      To create an instance that persists between requests, use persistent_id to specify a unique ID for the instance.
	 */
	public function __construct( $persistent_id = NULL ) {

		// Start your engines
		parent::__construct( $persistent_id );

		// Set daemon flags
		if ( class_exists( $this->daemon_class_name ) ) {
			$this->success_code   = Memcached::RES_SUCCESS;
			$this->preserve_order = Memcached::GET_PRESERVE_ORDER;
		}
	}
}
endif;
