<?php

/**
 * Spider Cache Object Cache
 *
 * @package Plugins/Cache/Object
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

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
class WP_Spider_Cache_Object_Base {

	/**
	 * Holds the cache engine class name.
	 *
	 * @var Memcache, Redis, etc...
	 */
	public $engine_class_name = '';

	/**
	 * Holds the cache daemon class name.
	 *
	 * @var Memcached, Redis, etc...
	 */
	public $daemon_class_name = '';

	/**
	 * Holds the cache servers.
	 *
	 * @var Memcached
	 */
	public $servers_global = '';

	/**
	 * Holds the fallback servers.
	 *
	 * @var array Something like: array( array( '127.0.0.1', 11211, 20 ) )
	 */
	public $servers_fallback = array();

	/**
	 * Holds the cache engine.
	 *
	 * @var Memcache, Redis, etc...
	 */
	protected $engine;

	/**
	 * Holds the cache daemon.
	 *
	 * @var Memcached, Redis, etc...
	 */
	protected $daemon;

	/**
	 * Hold the server details.
	 *
	 * @var array
	 */
	public $servers;

	/**
	 * Result code that determines successful cache interaction
	 *
	 * @var int
	 */
	public $success_code = 0;

	/**
	 * Should order be preserved?
	 *
	 * @var int
	 */
	public $preserve_order = null;

	/**
	 * Holds the non-cached objects.
	 *
	 * @var array
	 */
	public $cache = array();

	/**
	 * List of global groups.
	 *
	 * @var array
	 */
	public $global_groups = array(

		// Users
		'users',
		'userlogins',
		'usermeta',
		'user_meta',
		'useremail',
		'userslugs',

		// Networks & Sites
		'site-transient',
		'site-options',
		'blog-lookup',
		'blog-details',

		// Posts
		'rss',
		'global-posts',

		// New networks & Sites
		'networks',
		'sites',
		'site-details'
	);

	/**
	 * List of additionally supported, non-core, known, persistent, global
	 * cache-groups. This array is merged with $global_groups on construct.
	 *
	 * @var array
	 */
	public $global_groups_extended = array(

		// System
		'ludicrousdb',

		// Users
		'user_signups',

		// Blog Aliases
		'blog-aliases',
		'blog_aliasmeta',

		// Blog Meta
		'blogmeta',

		// Core (Persistent)
		'plugins',
		'themes'
	);

	/**
	 * List of groups not saved to cache.
	 *
	 * @var array
	 */
	public $no_mc_groups = array( 'counts' );

	/**
	 * List of groups to only look locally for
	 *
	 * @var type
	 */
	public $no_remote_groups = array();

	/**
	 * Prefix used for global groups.
	 *
	 * @var string
	 */
	public $global_prefix = '';

	/**
	 * Prefix used for non-global groups.
	 *
	 * @var string
	 */
	public $blog_prefix = '';

	/**
	 * Salt to prefix all keys with
	 *
	 * @var string
	 */
	public $cache_key_salt = '';

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
	public function __construct( $persistent_id = null ) {

		// Set values for handling expiration times
		$this->thirty_days = DAY_IN_SECONDS * 30;
		$this->now         = time();

		// Add extended global groups
		$this->add_global_groups( $this->global_groups_extended );

		// Set objects and properties
		$this->set_engine();
		$this->set_daemon( $persistent_id );
		$this->set_servers();
		$this->set_salt();
		$this->set_prefixes();
	}

	public function __call( $name, $args = array() ) {
		switch ( $name ) {
			case 'getResultCode' :

		}
	}

	/**
	 * Set the daemon
	 *
	 * @since 2.2.0
	 *
	 * @param int $persistent_id
	 */
	private function set_daemon( $persistent_id = 0 ) {

		// Bail if daemon not found
		if ( ! class_exists( $this->daemon_class_name ) ) {
			return;
		}

		// Set the daemon
		$this->daemon = ( is_null( $persistent_id ) || ! is_string( $persistent_id ) )
			? new $this->daemon_class_name
			: $this->daemon_class_name( $persistent_id );
	}

	/**
	 * Set the engine
	 *
	 * @since 2.2.0
	 */
	private function set_engine() {
		$this->engine = new $this->engine_class_name;
	}

	/**
	 * Set global and site prefixes
	 *
	 * @since 2.2.0
	 *
	 * @global  int    $blog_id
	 * @global  string $table_prefix
	 */
	private function set_prefixes() {
		global $blog_id, $table_prefix;

		// Global prefix
		$this->global_prefix = is_multisite() || ( defined( 'CUSTOM_USER_TABLE' ) && defined( 'CUSTOM_USER_META_TABLE' ) )
			? ''
			: $table_prefix;

		// Blog prefix
		$this->blog_prefix = is_multisite()
			? $blog_id
			: $table_prefix;
	}

	/**
	 * This approach is borrowed from Sivel and Boren. Use the salt for easy
	 * cache invalidation and for multiple single WordPress installations on
	 * the same server.
	 *
	 * @since 2.2.0
	 */
	private function set_salt() {
		if ( defined( 'WP_CACHE_KEY_SALT' ) && WP_CACHE_KEY_SALT ) {
			$this->cache_key_salt = rtrim( WP_CACHE_KEY_SALT, ':' );
		}
	}

	/**
	 * Add servers
	 *
	 * @since 2.2.0
	 */
	private function set_servers() {

		// Setup servers
		$this->servers = ! empty( $GLOBALS[ $this->servers_global ] )
			? $GLOBALS[ $this->servers_global ]
			: $this->servers_fallback;

		// Only add servers if daemon exists
		$this->addServers( $this->servers );
	}

	/**
	 * Adds a value to cache.
	 *
	 * If the specified key already exists, the value is not stored and the function
	 * returns false.
	 *
	 * @link    http://www.php.net/manual/en/memcached.add.php
	 *
	 * @param   string      $key            The key under which to store the value.
	 * @param   mixed       $value          The value to store.
	 * @param   string      $group          The group value appended to the $key.
	 * @param   int         $expiration     The expiration time, defaults to 0.
	 * @param   string      $server_key     The key identifying the server to store the value on.
	 * @param   bool        $byKey          True to store in internal cache by key; false to not store by key
	 * @return  bool                        Returns true on success or false on failure.
	 */
	public function add( $key, $value, $group = 'default', $expiration = 0, $server_key = '', $byKey = false ) {
		$result = false;

		/**
		 * Ensuring that wp_suspend_cache_addition is defined before calling, because sometimes an advanced-cache.php
		 * file will load object-cache.php before wp-includes/functions.php is loaded. In those cases, if wp_cache_add
		 * is called in advanced-cache.php before any more of WordPress is loaded, we get a fatal error because
		 * wp_suspend_cache_addition will not be defined until wp-includes/functions.php is loaded.
		 */
		if ( function_exists( 'wp_suspend_cache_addition' ) && wp_suspend_cache_addition() ) {
			return $result;
		}

		$derived_key = $this->buildKey( $key, $group );
		$expiration  = $this->sanitize_expiration( $expiration );

		// If group is a non-cache group, save to runtime cache, not cache
		if ( in_array( $group, $this->no_mc_groups, false ) ) {

			// Add does not set the value if the key exists; mimic that here
			if ( isset( $this->cache[ $derived_key ] ) ) {
				return $result;
			}

			$this->add_to_internal_cache( $derived_key, $value );

			return true;
		}

		// Save to cache
		if ( method_exists( $this->daemon, 'add' ) ) {
			$result = ( false !== $byKey )
				? $this->daemon->addByKey( $server_key, $derived_key, $value, $expiration )
				: $this->daemon->add( $derived_key, $value, $expiration );
		}

		// Store in runtime cache if add was successful
		if ( $this->success() ) {
			$this->add_to_internal_cache( $derived_key, $value );
		}

		return $result;
	}

	/**
	 * Adds a value to cache on a specific server.
	 *
	 * Using a server_key value, the object can be stored on a specified server as opposed
	 * to a random server in the stack. Note that this method will add the key/value to the
	 * _cache object as part of the runtime cache. It will add it to an array for the
	 * specified server_key.
	 *
	 * @link    http://www.php.net/manual/en/memcached.addbykey.php
	 *
	 * @param   string      $server_key     The key identifying the server to store the value on.
	 * @param   string      $key            The key under which to store the value.
	 * @param   mixed       $value          The value to store.
	 * @param   string      $group          The group value appended to the $key.
	 * @param   int         $expiration     The expiration time, defaults to 0.
	 * @return  bool                        Returns true on success or false on failure.
	 */
	public function addByKey( $server_key, $key, $value, $group = 'default', $expiration = 0 ) {
		return $this->add( $key, $value, $group, $expiration, $server_key, true );
	}

	/**
	 * Add a single server to the list of cache servers.
	 *
	 * @link http://www.php.net/manual/en/memcached.addserver.php
	 *
	 * @param   string      $host           The hostname of the server.
	 * @param   int         $port           The port on which is running.
	 * @param   int         $weight         The weight of the server relative to the total weight of all the servers in the pool.
	 * @return  bool                        Returns true on success or false on failure.
	 */
	public function addServer( $host, $port, $weight = 0 ) {
		$host   = is_string( $host ) ? $host : '127.0.0.1';
		$port   = is_numeric( $port ) && $port > 0 ? $port : 11211;
		$weight = is_numeric( $weight ) && $weight > 0 ? $weight : 1;

		return method_exists( $this->daemon, 'addServer' )
			? $this->daemon->addServer( $host, $port, $weight )
			: false;
	}

	/**
	 * Adds an array of servers to the pool.
	 *
	 * Each individual server in the array must include a domain and port, with an optional
	 * weight value: $servers = array( array( '127.0.0.1', 11211, 0 ) );
	 *
	 * @link    http://www.php.net/manual/en/memcached.addservers.php
	 *
	 * @param   array       $servers        Array of server to register.
	 * @return  bool                        True on success; false on failure.
	 */
	public function addServers( $servers ) {
		return method_exists( $this->daemon, 'addServers' )
			? $this->daemon->addServers( $servers )
			: false;
	}

	/**
	 * Append data to an existing item.
	 *
	 * This method should throw an error if it is used with compressed data. This
	 * is an expected behavior. Memcached casts the value to be appended to the initial value to the
	 * type of the initial value. Be careful as this leads to unexpected behavior at times. Due to
	 * how memcached treats types, the behavior has been mimicked in the internal cache to produce
	 * similar results and improve consistency. It is recommend that appends only occur with data of
	 * the same type.
	 *
	 * @link    http://www.php.net/manual/en/memcached.append.php
	 *
	 * @param   string      $key            The key under which to store the value.
	 * @param   mixed       $value          Must be string as appending mixed values is not well-defined.
	 * @param   string      $group          The group value appended to the $key.
	 * @param   string      $server_key     The key identifying the server to store the value on.
	 * @param   bool        $byKey          True to store in internal cache by key; false to not store by key
	 * @return  bool                        Returns true on success or false on failure.
	 */
	public function append( $key, $value, $group = 'default', $server_key = '', $byKey = false ) {
		$result = false;

		if ( ! is_string( $value ) && ! is_int( $value ) && ! is_float( $value ) ) {
			return $result;
		}

		$derived_key = $this->buildKey( $key, $group );

		// If group is a non-cache group, append to runtime cache value, not cache
		if ( in_array( $group, $this->no_mc_groups, false ) ) {
			if ( ! isset( $this->cache[ $derived_key ] ) ) {
				return false;
			}

			$combined = $this->combine_values( $this->cache[ $derived_key ], $value, 'app' );
			$this->add_to_internal_cache( $derived_key, $combined );

			return true;
		}

		// Append to cache value
		if ( method_exists( $this->daemon, 'append' ) ) {
			$result = ( false !== $byKey )
				? $this->daemon->appendByKey( $server_key, $derived_key, $value )
				: $this->daemon->append( $derived_key, $value );
		}

		// Store in runtime cache if add was successful
		if ( $this->success() ) {
			$combined = $this->combine_values( $this->cache[ $derived_key ], $value, 'app' );
			$this->add_to_internal_cache( $derived_key, $combined );
		}

		return $result;
	}

	/**
	 * Append data to an existing item by server key.
	 *
	 * This method should throw an error if it is used with compressed data. This
	 * is an expected behavior. Memcached casts the value to be appended to the initial value to the
	 * type of the initial value. Be careful as this leads to unexpected behavior at times. Due to
	 * how memcached treats types, the behavior has been mimicked in the internal cache to produce
	 * similar results and improve consistency. It is recommend that appends only occur with data of
	 * the same type.
	 *
	 * @link    http://www.php.net/manual/en/memcached.appendbykey.php
	 *
	 * @param   string      $server_key     The key identifying the server to store the value on.
	 * @param   string      $key            The key under which to store the value.
	 * @param   mixed       $value          Must be string as appending mixed values is not well-defined
	 * @param   string      $group          The group value appended to the $key.
	 * @return  bool                        Returns true on success or false on failure.
	 */
	public function appendByKey( $server_key, $key, $value, $group = 'default' ) {
		return $this->append( $key, $value, $group, $server_key, true );
	}

	/**
	 * Performs a "check and set" to store data.
	 *
	 * The set will be successful only if the no other request has updated the value since it was fetched since
	 * this request.
	 *
	 * @link    http://www.php.net/manual/en/memcached.cas.php
	 *
	 * @param   float       $cas_token      Unique value associated with the existing item. Generated by memcached.
	 * @param   string      $key            The key under which to store the value.
	 * @param   mixed       $value          The value to store.
	 * @param   string      $group          The group value appended to the $key.
	 * @param   int         $expiration     The expiration time, defaults to 0.
	 * @param   string      $server_key     The key identifying the server to store the value on.
	 * @param   bool        $byKey          True to store in internal cache by key; false to not store by key
	 * @return  bool                        Returns true on success or false on failure.
	 */
	public function cas( $cas_token, $key, $value, $group = 'default', $expiration = 0, $server_key = '', $byKey = false ) {
		$result      = false;
		$derived_key = $this->buildKey( $key, $group );
		$expiration  = $this->sanitize_expiration( $expiration );

		/**
		 * If group is a non-cached group, save to runtime cache, not cache. Note
		 * that since check and set cannot be emulated in the run time cache, this value
		 * operation is treated as a normal "add" for no_mc_groups.
		 */
		if ( in_array( $group, $this->no_mc_groups, false ) ) {
			$this->add_to_internal_cache( $derived_key, $value );
			return true;
		}

		// Save to cache
		if ( method_exists( $this->daemon, 'cas' ) ) {
			$result = ( false !== $byKey )
				? $this->daemon->casByKey( $cas_token, $server_key, $derived_key, $value, $expiration )
				: $this->daemon->cas( $cas_token, $derived_key, $value, $expiration );
		}

		// Store in runtime cache if cas was successful
		if ( $this->success() ) {
			$this->add_to_internal_cache( $derived_key, $value );
		}

		return $result;
	}

	/**
	 * Performs a "check and set" to store data with a server key.
	 *
	 * The set will be successful only if the no other request has updated the value since it was fetched by
	 * this request.
	 *
	 * @link    http://www.php.net/manual/en/memcached.casbykey.php
	 *
	 * @param   float       $cas_token      Unique value associated with the existing item. Generated by memcached.
	 * @param   string      $server_key     The key identifying the server to store the value on.
	 * @param   string      $key            The key under which to store the value.
	 * @param   mixed       $value          The value to store.
	 * @param   string      $group          The group value appended to the $key.
	 * @param   int         $expiration     The expiration time, defaults to 0.
	 * @return  bool                        Returns true on success or false on failure.
	 */
	public function casByKey( $cas_token, $server_key, $key, $value, $group = 'default', $expiration = 0 ) {
		return $this->cas( $cas_token, $key, $value, $group, $expiration, $server_key, true );
	}

	/**
	 * Close a cache server connection
	 *
	 * @link    http://php.net/manual/en/memcache.close.php
	 *
	 * @return  bool  Returns true on success or false on failure.
	 */
	public function close() {
		return true;
	}

	/**
	 * Connect directly to a cache server
	 *
	 * @link    http://php.net/manual/en/memcache.connect.php
	 *
	 * @param   string   $server The host where the daemon is listening for connections
	 * @param   int      $port   The port where the daemon is listening for connections
	 * @return  bool             Returns true on success or false on failure.
	 */
	public function connect( $server = '127.0.0.1', $port = 11211 ) {
		return method_exists( $this->engine, 'connect' )
			? $this->engine->connect( $server, $port )
			: false;
	}

	/**
	 * Decrement a numeric item's value.
	 *
	 * @link http://www.php.net/manual/en/memcached.decrement.php
	 *
	 * @param string    $key    The key under which to store the value.
	 * @param int       $offset The amount by which to decrement the item's value.
	 * @param string    $group  The group value appended to the $key.
	 * @return int|bool         Returns item's new value on success or false on failure.
	 */
	public function decrement( $key, $offset = 1, $group = 'default' ) {
		$derived_key = $this->buildKey( $key, $group );

		// Decrement values in no_mc_groups
		if ( in_array( $group, $this->no_mc_groups, false ) ) {

			// Only decrement if the key already exists and value is 0 or greater (mimics memcached behavior)
			if ( isset( $this->cache[ $derived_key ] ) && $this->cache[ $derived_key ] >= 0 ) {

				// If numeric, subtract; otherwise, consider it 0 and do nothing
				if ( is_numeric( $this->cache[ $derived_key ] ) ) {
					$this->cache[ $derived_key ] -= (int) $offset;
				} else {
					$this->cache[ $derived_key ] = 0;
				}

				// Returned value cannot be less than 0
				if ( $this->cache[ $derived_key ] < 0 ) {
					$this->cache[ $derived_key ] = 0;
				}

				return $this->cache[ $derived_key ];
			} else {
				return false;
			}
		}

		$result = $this->daemon->decrement( $derived_key, $offset );

		if ( $this->success() ) {
			$this->add_to_internal_cache( $derived_key, $result );
		}

		return $result;
	}

	/**
	 * Decrement a numeric item's value.
	 *
	 * Alias for $this->decrement. Other caching backends use this abbreviated form of the function. It *may* cause
	 * breakage somewhere, so it is nice to have. This function will also allow the core unit tests to pass.
	 *
	 * @param string    $key    The key under which to store the value.
	 * @param int       $offset The amount by which to decrement the item's value.
	 * @param string    $group  The group value appended to the $key.
	 * @return int|bool         Returns item's new value on success or false on failure.
	 */
	public function decr( $key, $offset = 1, $group = 'default' ) {
		return $this->decrement( $key, $offset, $group );
	}

	/**
	 * Remove the item from the cache.
	 *
	 * Remove an item from cache with identified by $key after $time seconds. The
	 * $time parameter allows an object to be queued for deletion without immediately
	 * deleting. Between the time that it is queued and the time it's deleted, add,
	 * replace, and get will fail, but set will succeed.
	 *
	 * @link http://www.php.net/manual/en/memcached.delete.php
	 *
	 * @param   string      $key        The key under which to store the value.
	 * @param   string      $group      The group value appended to the $key.
	 * @param   int         $time       The amount of time the server will wait to delete the item in seconds.
	 * @param   string      $server_key The key identifying the server to store the value on.
	 * @param   bool        $byKey      True to store in internal cache by key; false to not store by key
	 * @return  bool                    Returns true on success or false on failure.
	 */
	public function delete( $key, $group = 'default', $time = 0, $server_key = '', $byKey = false ) {
		$result      = false;
		$derived_key = $this->buildKey( $key, $group );

		// Remove from no_mc_groups array
		if ( in_array( $group, $this->no_mc_groups, false ) ) {
			if ( isset( $this->cache[ $derived_key ] ) ) {
				unset( $this->cache[ $derived_key ] );
			}

			return true;
		}

		if ( method_exists( $this->daemon, 'delete' ) ) {
			$result = ( false !== $byKey )
				? $this->daemon->deleteByKey( $server_key, $derived_key, $time )
				: $this->daemon->delete( $derived_key, $time );
		}

		if ( $this->success() ) {
			unset( $this->cache[ $derived_key ] );
		}

		return $result;
	}

	/**
	 * Remove the item from the cache by server key.
	 *
	 * Remove an item from cache with identified by $key after $time seconds. The
	 * $time parameter allows an object to be queued for deletion without immediately
	 * deleting. Between the time that it is queued and the time it's deleted, add,
	 * replace, and get will fail, but set will succeed.
	 *
	 * @link http://www.php.net/manual/en/memcached.deletebykey.php
	 *
	 * @param   string      $server_key The key identifying the server to store the value on.
	 * @param   string      $key        The key under which to store the value.
	 * @param   string      $group      The group value appended to the $key.
	 * @param   int         $time       The amount of time the server will wait to delete the item in seconds.
	 * @return  bool                    Returns true on success or false on failure.
	 */
	public function deleteByKey( $server_key, $key, $group = 'default', $time = 0 ) {
		return $this->delete( $key, $group, $time, $server_key, true );
	}

	/**
	 * Fetch the next result.
	 *
	 * @link http://www.php.net/manual/en/memcached.fetch.php
	 *
	 * @return array|bool   Returns the next result or false on failure.
	 */
	public function fetch() {
		return $this->daemon->fetch();
	}

	/**
	 * Fetch all remaining results from the last request.
	 *
	 * @link http://www.php.net/manual/en/memcached.fetchall.php
	 *
	 * @return  array|bool          Returns the results or false on failure.
	 */
	public function fetchAll() {
		return $this->daemon->fetchAll();
	}

	/**
	 * Invalidate all items in the cache.
	 *
	 * @link http://www.php.net/manual/en/memcached.flush.php
	 *
	 * @param   int     $delay      Number of seconds to wait before invalidating the items.
	 * @return  bool                Returns true on success or false on failure.
	 */
	public function flush( $delay = 0 ) {
		$result = $this->daemon->flush( $delay );

		// Only reset the runtime cache if properly flushed
		if ( $this->success() ) {
			$this->cache = array();
		}

		return $result;
	}

	/**
	 * Retrieve object from cache.
	 *
	 * Gets an object from cache based on $key and $group. In order to fully support the $cache_cb and $cas_token
	 * parameters, the runtime cache is ignored by this function if either of those values are set. If either of
	 * those values are set, the request is made directly to the cache server for proper handling of the
	 * callback and/or token. Note that the $cas_token variable cannot be directly passed to the function. The
	 * variable need to be first defined with a non null value.
	 *
	 * If using the $cache_cb argument, the new value will always have an expiration of time of 0 (forever). This
	 * is a limitation of the Memcached PECL extension.
	 *
	 * @link http://www.php.net/manual/en/memcached.get.php
	 *
	 * @param   string          $key        The key under which to store the value.
	 * @param   string          $group      The group value appended to the $key.
	 * @param   bool            $force      Whether or not to force a cache invalidation.
	 * @param   null|bool       $found      Variable passed by reference to determine if the value was found or not.
	 * @param   string          $server_key The key identifying the server to store the value on.
	 * @param   bool            $byKey      True to store in internal cache by key; false to not store by key
	 * @param   null|callable   $cache_cb   Read-through caching callback.
	 * @param   null|float      $cas_token  The variable to store the CAS token in.
	 * @return  bool|mixed                  Cached object value.
	 */
	public function get( $key, $group = 'default', $force = false, &$found = null, $server_key = '', $byKey = false, $cache_cb = null, &$cas_token = null ) {
		$derived_key = $this->buildKey( $key, $group );

		// Assume object is not found
		$found = $value = false;

		// If either $cache_db, or $cas_token is set, must hit Memcached and bypass runtime cache
		if ( method_exists( $this->daemon, 'get' ) ) {
			if ( func_num_args() > 6 && ! in_array( $group, $this->no_mc_groups, false ) ) {
				$value = ( false !== $byKey )
					? $this->daemon->getByKey( $server_key, $derived_key, $cache_cb, $cas_token )
					: $this->daemon->get( $derived_key, $cache_cb, $cas_token );
			} else {
				if ( isset( $this->cache[ $derived_key ] ) ) {
					$found = true;
					return is_object( $this->cache[ $derived_key ] ) ? clone $this->cache[ $derived_key ] : $this->cache[ $derived_key ];
				} elseif ( in_array( $group, $this->no_mc_groups, false ) ) {
					return false;
				} else {
					$value = ( false !== $byKey )
						? $this->daemon->getByKey( $server_key, $derived_key )
						: $this->daemon->get( $derived_key );
				}
			}
		}

		if ( $this->success() ) {
			$this->add_to_internal_cache( $derived_key, $value );
			$found = true;
		}

		return is_object( $value ) ? clone $value : $value;
	}

	/**
	 * Retrieve object from cache from specified server.
	 *
	 * Gets an object from cache based on $key, $group and $server_key. In order to fully support the $cache_cb and $cas_token
	 * parameters, the runtime cache is ignored by this function if either of those values are set. If either of
	 * those values are set, the request is made directly to the cache server for proper handling of the
	 * callback and/or token. Note that the $cas_token variable cannot be directly passed to the function. The
	 * variable need to be first defined with a non null value.
	 *
	 * If using the $cache_cb argument, the new value will always have an expiration of time of 0 (forever). This
	 * is a limitation of the Memcached PECL extension.
	 *
	 * @link http://www.php.net/manual/en/memcached.getbykey.php
	 *
	 * @param   string          $server_key The key identifying the server to store the value on.
	 * @param   string          $key        The key under which to store the value.
	 * @param   string          $group      The group value appended to the $key.
	 * @param   bool            $force      Whether or not to force a cache invalidation.
	 * @param   null|bool       $found      Variable passed by reference to determine if the value was found or not.
	 * @param   null|string     $cache_cb   Read-through caching callback.
	 * @param   null|float      $cas_token  The variable to store the CAS token in.
	 * @return  bool|mixed                  Cached object value.
	 */
	public function getByKey( $server_key, $key, $group = 'default', $force = false, &$found = null, $cache_cb = null, &$cas_token = null ) {
		/**
		 * Need to be careful how "get" is called. If you send $cache_cb, and $cas_token, it will hit cache.
		 * Only send those args if they were sent to this function.
		 */
		if ( func_num_args() > 5 ) {
			return $this->get( $key, $group, $force, $found, $server_key, true, $cache_cb, $cas_token );
		} else {
			return $this->get( $key, $group, $force, $found, $server_key, true );
		}
	}

	/**
	 * Request multiple keys without blocking.
	 *
	 * @link http://www.php.net/manual/en/memcached.getdelayed.php
	 *
	 * @param   string|array    $keys       Array or string of key(s) to request.
	 * @param   string|array    $groups     Array or string of group(s) for the key(s). See buildKeys for more on how these are handled.
	 * @param   bool            $with_cas   Whether to request CAS token values also.
	 * @param   null            $value_cb   The result callback or null.
	 * @return  bool                        Returns true on success or false on failure.
	 */
	public function getDelayed( $keys, $groups = 'default', $with_cas = false, $value_cb = null ) {
		$derived_keys = $this->buildKeys( $keys, $groups );
		return method_exists( $this->daemon, 'getDelayed' )
			? $this->daemon->getDelayed( $derived_keys, $with_cas, $value_cb )
			: false;
	}

	/**
	 * Request multiple keys without blocking from a specified server.
	 *
	 * @link http://www.php.net/manual/en/memcached.getdelayed.php
	 *
	 * @param   string          $server_key The key identifying the server to store the value on.
	 * @param   string|array    $keys       Array or string of key(s) to request.
	 * @param   string|array    $groups     Array or string of group(s) for the key(s). See buildKeys for more on how these are handled.
	 * @param   bool            $with_cas   Whether to request CAS token values also.
	 * @param   null            $value_cb   The result callback or null.
	 * @return  bool                        Returns true on success or false on failure.
	 */
	public function getDelayedByKey( $server_key, $keys, $groups = 'default', $with_cas = false, $value_cb = null ) {
		$derived_keys = $this->buildKeys( $keys, $groups );

		return method_exists( $this->daemon, 'getDelayedByKey' )
			? $this->daemon->getDelayedByKey( $server_key, $derived_keys, $with_cas, $value_cb )
			: false;
	}

	/**
	 * Get extended server pool statistics.
	 *
	 * @link http://php.net/manual/en/memcache.getextendedstats.php
	 *
	 * @param   string          $type    The type of statistics to retrieve
	 * @param   string          $slab_id The name of the slab to retrieve
	 * @param   int             $limit
	 * @return  array
	 */
	public function getExtendedStats( $type, $slab_id = 0, $limit = 100 ) {
		return method_exists( $this->engine, 'getExtendedStats' )
			? $this->engine->getExtendedStats( $type, $slab_id, $limit )
			: array();
	}

	/**
	 * Gets multiple values from cache daemon in one request.
	 *
	 * See the buildKeys method definition to understand the $keys/$groups parameters.
	 *
	 * @link http://www.php.net/manual/en/memcached.getmulti.php
	 *
	 * @param   array           $keys       Array of keys to retrieve.
	 * @param   string|array    $groups     If string, used for all keys. If arrays, corresponds with the $keys array.
	 * @param   string          $server_key The key identifying the server to store the value on.
	 * @param   null|array      $cas_tokens The variable to store the CAS tokens for the found items.
	 * @param   int             $flags      The flags for the get operation.
	 * @return  bool|array                  Returns the array of found items or false on failure.
	 */
	public function getMulti( $keys, $groups = 'default', $server_key = '', &$cas_tokens = null, $flags = null ) {
		$values       = array();
		$derived_keys = $this->buildKeys( $keys, $groups );

		// Bail if no method exists
		if ( ! method_exists( $this->daemon, 'getMulti' ) ) {
			return false;
		}

		/**
		 * If either $cas_tokens, or $flags is set, must hit Memcached and bypass runtime cache. Note that
		 * this will purposely ignore no_mc_groups values as they cannot handle CAS tokens or the special
		 * flags; however, if the groups of groups contains a no_mc_group, this is bypassed.
		 */
		if ( func_num_args() > 3 && ! $this->contains_no_mc_group( $groups ) ) {
			if ( ! empty( $this->daemon ) ) {
				$values = ! empty( $server_key )
					? $this->daemon->getMultiByKey( $server_key, $derived_keys, $cas_tokens, $flags )
					: $this->daemon->getMulti( $derived_keys, $cas_tokens, $flags );
			}

		} else {
			$need_to_get = array();

			// Pull out values from runtime cache, or mark for retrieval
			foreach ( $derived_keys as $key ) {
				if ( isset( $this->cache[ $key ] ) ) {
					$values[ $key ] = $this->cache[ $key ];
				} else {
					$need_to_get[ $key ] = $key;
				}
			}

			// Get those keys not found in the runtime cache
			if ( ! empty( $need_to_get ) && ! empty( $this->daemon ) ) {
				$result = ! empty( $server_key )
					? $this->daemon->getMultiByKey( $server_key, array_keys( $need_to_get ) )
					: $this->daemon->getMulti( array_keys( $need_to_get ) );

				// Merge with values found in runtime cache
				if ( $this->success() ) {
					$values = array_merge( $values, $result );
				}
			}

			// If order should be preserved, reorder now
			if ( ! empty( $need_to_get ) && $this->preserveOrder( $flags ) ) {
				$ordered_values = array();

				foreach ( $derived_keys as $key ) {
					if ( isset( $values[ $key ] ) ) {
						$ordered_values[ $key ] = $values[ $key ];
					}
				}

				$values = $ordered_values;

				unset( $ordered_values );
			}
		}

		// Add the values to the runtime cache
		$this->cache = array_merge( $this->cache, $values );

		return $values;
	}

	/**
	 * Gets multiple values from cache daemon in one request by specified server key.
	 *
	 * See the buildKeys method definition to understand the $keys/$groups parameters.
	 *
	 * @link http://www.php.net/manual/en/memcached.getmultibykey.php
	 *
	 * @param   string          $server_key The key identifying the server to store the value on.
	 * @param   array           $keys       Array of keys to retrieve.
	 * @param   string|array    $groups     If string, used for all keys. If arrays, corresponds with the $keys array.
	 * @param   null|array      $cas_tokens The variable to store the CAS tokens for the found items.
	 * @param   int             $flags      The flags for the get operation.
	 * @return  bool|array                  Returns the array of found items or false on failure.
	 */
	public function getMultiByKey( $server_key, $keys, $groups = 'default', &$cas_tokens = null, $flags = null ) {
		/**
		 * Need to be careful how "getMulti" is called. If you send $cache_cb, and $cas_token, it will hit cache.
		 * Only send those args if they were sent to this function.
		 */
		if ( func_num_args() > 3 ) {
			return $this->getMulti( $keys, $groups, $server_key, $cas_tokens, $flags );
		} else {
			return $this->getMulti( $keys, $groups, $server_key );
		}
	}

	/**
	 * Retrieve a daemon option value.
	 *
	 * @link http://www.php.net/manual/en/memcached.getoption.php
	 *
	 * @param   int         $option     One of the Memcached::OPT_* constants.
	 * @return  mixed                   Returns the value of the requested option, or false on error.
	 */
	public function getOption( $option ) {
		return method_exists( $this->daemon, 'getOption' )
			? $this->daemon->getOption( $option )
			: false;
	}

	/**
	 * Return the result code of the last option.
	 *
	 * @link http://www.php.net/manual/en/memcached.getresultcode.php
	 *
	 * @return  int     Result code of the last cache operation.
	 */
	public function getResultCode() {
		return method_exists( $this->daemon, 'getResultCode' )
			? $this->daemon->getResultCode()
			: 0;
	}

	/**
	 * Return the message describing the result of the last operation.
	 *
	 * @link    http://www.php.net/manual/en/memcached.getresultmessage.php
	 *
	 * @return  string      Message describing the result of the last cache operation.
	 */
	public function getResultMessage() {
		return method_exists( $this->daemon, 'getResultMessage' )
			? $this->daemon->getResultMessage()
			: '';
	}

	/**
	 * Get server information by key.
	 *
	 * @link    http://www.php.net/manual/en/memcached.getserverbykey.php
	 *
	 * @param   string      $server_key     The key identifying the server to store the value on.
	 * @return  array                       Array with host, post, and weight on success, false on failure.
	 */
	public function getServerByKey( $server_key ) {
		return method_exists( $this->daemon, 'getServerByKey' )
			? $this->daemon->getServerByKey( $server_key )
			: false;
	}

	/**
	 * Get the list of servers in the pool.
	 *
	 * @link    http://www.php.net/manual/en/memcached.getserverlist.php
	 *
	 * @return  array       The list of all servers in the server pool.
	 */
	public function getServerList() {
		return method_exists( $this->daemon, 'getServerList' )
			? $this->daemon->getServerList()
			: array();
	}

	/**
	 * Get server pool statistics.
	 *
	 * @link    http://www.php.net/manual/en/memcached.getstats.php
	 *
	 * @return  array       Array of server statistics, one entry per server.
	 */
	public function getStats() {
		return method_exists( $this->daemon, 'getStats' )
			? $this->daemon->getStats()
			: array();
	}

	/**
	 * Get server pool cache version information.
	 *
	 * @link    http://www.php.net/manual/en/memcached.getversion.php
	 *
	 * @return  array       Array of server versions, one entry per server.
	 */
	public function getVersion() {
		return method_exists( $this->daemon, 'getVersion' )
			? $this->daemon->getVersion()
			: array();
	}

	/**
	 * Increment a numeric item's value.
	 *
	 * @link http://www.php.net/manual/en/memcached.increment.php
	 *
	 * @param   string      $key        The key under which to store the value.
	 * @param   int         $offset     The amount by which to increment the item's value.
	 * @param   string      $group      The group value appended to the $key.
	 * @return  int|bool                Returns item's new value on success or false on failure.
	 */
	public function increment( $key, $offset = 1, $group = 'default' ) {
		$derived_key = $this->buildKey( $key, $group );

		// Increment values in no_mc_groups
		if ( in_array( $group, $this->no_mc_groups, false ) ) {

			// Only increment if the key already exists and the number is currently 0 or greater (mimics memcached behavior)
			if ( isset( $this->cache[ $derived_key ] ) &&  $this->cache[ $derived_key ] >= 0 ) {

				// If numeric, add; otherwise, consider it 0 and do nothing
				if ( is_numeric( $this->cache[ $derived_key ] ) ) {
					$this->cache[ $derived_key ] += (int) $offset;
				} else {
					$this->cache[ $derived_key ] = 0;
				}

				// Returned value cannot be less than 0
				if ( $this->cache[ $derived_key ] < 0 ) {
					$this->cache[ $derived_key ] = 0;
				}

				return $this->cache[ $derived_key ];
			} else {
				return false;
			}
		}

		// Get result
		$result = $this->daemon->increment( $derived_key, $offset );

		if ( $this->success() ) {
			$this->add_to_internal_cache( $derived_key, $result );
		}

		return $result;
	}

	/**
	 * Synonymous with $this->incr.
	 *
	 * Certain plugins expect an "incr" method on the $wp_object_cache object (e.g., Spider-Cache). Since the original
	 * version of this library matched names to the memcached methods, the "incr" method was missing. Adding this
	 * method restores compatibility with plugins expecting an "incr" method.
	 *
	 * @param   string      $key        The key under which to store the value.
	 * @param   int         $offset     The amount by which to increment the item's value.
	 * @param   string      $group      The group value appended to the $key.
	 * @return  int|bool                Returns item's new value on success or false on failure.
	 */
	public function incr( $key, $offset = 1, $group = 'default' ) {
		return $this->increment( $key, $offset, $group );
	}

	/**
	 * Prepend data to an existing item.
	 *
	 * This method should throw an error if it is used with compressed data. This is an expected behavior.
	 * Memcached casts the value to be prepended to the initial value to the type of the initial value. Be
	 * careful as this leads to unexpected behavior at times. For instance, prepending (float) 45.23 to
	 * (int) 23 will result in 45, because the value is first combined (45.2323) then cast to "integer"
	 * (the original value), which will be (int) 45. Due to how memcached treats types, the behavior has been
	 * mimicked in the internal cache to produce similar results and improve consistency. It is recommend
	 * that prepends only occur with data of the same type.
	 *
	 * @link    http://www.php.net/manual/en/memcached.prepend.php
	 *
	 * @param   string    $key          The key under which to store the value.
	 * @param   string    $value        Must be string as prepending mixed values is not well-defined.
	 * @param   string    $group        The group value prepended to the $key.
	 * @param   string    $server_key   The key identifying the server to store the value on.
	 * @param   bool      $byKey        True to store in internal cache by key; false to not store by key
	 * @return  bool                    Returns true on success or false on failure.
	 */
	public function prepend( $key, $value, $group = 'default', $server_key = '', $byKey = false ) {
		$result = false;

		// Bail if no value
		if ( ! is_string( $value ) && ! is_int( $value ) && ! is_float( $value ) ) {
			return $result;
		}

		$derived_key = $this->buildKey( $key, $group );

		// If group is a non-cache group, prepend to runtime cache value, not cache
		if ( in_array( $group, $this->no_mc_groups, false ) ) {
			if ( ! isset( $this->cache[ $derived_key ] ) ) {
				return $result;
			}

			$combined = $this->combine_values( $this->cache[ $derived_key ], $value, 'pre' );
			$this->add_to_internal_cache( $derived_key, $combined );

			return true;
		}

		// Append to cache value
		if ( ! empty( $this->daemon ) ) {
			$result = ( false !== $byKey )
				? $this->daemon->prependByKey( $server_key, $derived_key, $value )
				: $this->daemon->prepend( $derived_key, $value );
		}

		// Store in runtime cache if add was successful
		if ( $this->success() ) {
			$combined = $this->combine_values( $this->cache[ $derived_key ], $value, 'pre' );
			$this->add_to_internal_cache( $derived_key, $combined );
		}

		return $result;
	}

	/**
	 * Append data to an existing item by server key.
	 *
	 * This method should throw an error if it is used with compressed data. This is an expected behavior.
	 * Memcached casts the value to be prepended to the initial value to the type of the initial value. Be
	 * careful as this leads to unexpected behavior at times. For instance, prepending (float) 45.23 to
	 * (int) 23 will result in 45, because the value is first combined (45.2323) then cast to "integer"
	 * (the original value), which will be (int) 45. Due to how memcached treats types, the behavior has been
	 * mimicked in the internal cache to produce similar results and improve consistency. It is recommend
	 * that prepends only occur with data of the same type.
	 *
	 * @link    http://www.php.net/manual/en/memcached.prependbykey.php
	 *
	 * @param   string    $server_key   The key identifying the server to store the value on.
	 * @param   string    $key          The key under which to store the value.
	 * @param   string    $value        Must be string as prepending mixed values is not well-defined.
	 * @param   string    $group        The group value prepended to the $key.
	 * @return  bool                    Returns true on success or false on failure.
	 */
	public function prependByKey( $server_key, $key, $value, $group = 'default' ) {
		return $this->prepend( $key, $value, $group, $server_key, true );
	}

	/**
	 * Should order of items be preserved
	 *
	 * @since 2.2.0
	 *
	 * @param mixed $flags
	 *
	 * @return bool
	 */
	public function preserveOrder( $flags = null ) {
		return ( $this->preserve_order === $flags );
	}

	/**
	 * Replaces a value in cache.
	 *
	 * This method is similar to "add"; however, is does not successfully set a value if
	 * the object's key is not already set in cache.
	 *
	 * @link    http://www.php.net/manual/en/memcached.replace.php
	 *
	 * @param   string      $key            The key under which to store the value.
	 * @param   mixed       $value          The value to store.
	 * @param   string      $group          The group value appended to the $key.
	 * @param   int         $expiration     The expiration time, defaults to 0.
	 * @param   string      $server_key     The key identifying the server to store the value on.
	 * @param   bool        $byKey          True to store in internal cache by key; false to not store by key
	 * @return  bool                        Returns true on success or false on failure.
	 */
	public function replace( $key, $value, $group = 'default', $expiration = 0, $server_key = '', $byKey = false ) {
		$result      = false;
		$derived_key = $this->buildKey( $key, $group );
		$expiration  = $this->sanitize_expiration( $expiration );

		// If group is a non-cache group, save to runtime cache, not persistent cache
		if ( in_array( $group, $this->no_mc_groups, false ) ) {

			// Replace won't save unless the key already exists; mimic this behavior here
			if ( ! isset( $this->cache[ $derived_key ] ) ) {
				return $result;
			}

			$this->cache[ $derived_key ] = $value;
			return true;
		}

		// Save to cache
		if ( method_exists( $this->daemon, 'replace' ) ) {
			$result = ( false !== $byKey )
				? $this->daemon->replaceByKey( $server_key, $derived_key, $value, $expiration )
				: $this->daemon->replace( $derived_key, $value, $expiration );
		}

		// Store in runtime cache if add was successful
		if ( $this->success() ) {
			$this->add_to_internal_cache( $derived_key, $value );
		}

		return $result;
	}

	/**
	 * Replaces a value in cache on a specific server.
	 *
	 * This method is similar to "addByKey"; however, is does not successfully set a value if
	 * the object's key is not already set in cache.
	 *
	 * @link    http://www.php.net/manual/en/memcached.addbykey.php
	 *
	 * @param   string      $server_key     The key identifying the server to store the value on.
	 * @param   string      $key            The key under which to store the value.
	 * @param   mixed       $value          The value to store.
	 * @param   string      $group          The group value appended to the $key.
	 * @param   int         $expiration     The expiration time, defaults to 0.
	 * @return  bool                        Returns true on success or false on failure.
	 */
	public function replaceByKey( $server_key, $key, $value, $group = 'default', $expiration = 0 ) {
		return $this->replace( $key, $value, $group, $expiration, $server_key, true );
	}

	/**
	 * Sets a value in cache.
	 *
	 * The value is set whether or not this key already exists in cache.
	 *
	 * @link http://www.php.net/manual/en/memcached.set.php
	 *
	 * @param   string      $key        The key under which to store the value.
	 * @param   mixed       $value      The value to store.
	 * @param   string      $group      The group value appended to the $key.
	 * @param   int         $expiration The expiration time, defaults to 0.
	 * @param   string      $server_key The key identifying the server to store the value on.
	 * @param   bool        $byKey      True to store in internal cache by key; false to not store by key
	 * @return  bool                    Returns true on success or false on failure.
	 */
	public function set( $key, $value, $group = 'default', $expiration = 0, $server_key = '', $byKey = false ) {
		$result      = false;
		$derived_key = $this->buildKey( $key, $group );
		$expiration  = $this->sanitize_expiration( $expiration );

		// If group is a non-cache group, save to runtime cache, not cache
		if ( in_array( $group, $this->no_mc_groups, false ) ) {
			$this->add_to_internal_cache( $derived_key, $value );
			return true;
		}

		// Save to cache
		if ( method_exists( $this->daemon, 'set' ) ) {
			$result = ( false !== $byKey )
				? $this->daemon->setByKey( $server_key, $derived_key, $value, $expiration )
				: $this->daemon->set( $derived_key, $value, $expiration );
		}

		// Store in runtime cache if add was successful
		if ( $this->success() ) {
			$this->add_to_internal_cache( $derived_key, $value );
		}

		return $result;
	}

	/**
	 * Sets a value in cache on a specific server.
	 *
	 * The value is set whether or not this key already exists in cache.
	 *
	 * @link    http://www.php.net/manual/en/memcached.setbykey.php
	 *
	 * @param   string      $server_key     The key identifying the server to store the value on.
	 * @param   string      $key            The key under which to store the value.
	 * @param   mixed       $value          The value to store.
	 * @param   string      $group          The group value appended to the $key.
	 * @param   int         $expiration     The expiration time, defaults to 0.
	 * @return  bool                        Returns true on success or false on failure.
	 */
	public function setByKey( $server_key, $key, $value, $group = 'default', $expiration = 0 ) {
		return $this->set( $key, $value, $group, $expiration, $server_key, true );
	}

	/**
	 * Set multiple values to cache at once.
	 *
	 * By sending an array of $items to this function, all values are saved at once to
	 * cache, reducing the need for multiple requests to cache. The $items array
	 * keys and values are what are stored to cache. The keys in the $items array
	 * are merged with the $groups array/string value via buildKeys to determine the
	 * final key for the object.
	 *
	 * @link    http://www.php.net/manual/en/memcached.setmulti.php
	 *
	 * @param   array           $items          An array of key/value pairs to store on the server.
	 * @param   string|array    $groups         Group(s) to merge with key(s) in $items.
	 * @param   int             $expiration     The expiration time, defaults to 0.
	 * @param   string          $server_key     The key identifying the server to store the value on.
	 * @param   bool            $byKey          True to store in internal cache by key; false to not store by key
	 * @return  bool                            Returns true on success or false on failure.
	 */
	public function setMulti( $items, $groups = 'default', $expiration = 0, $server_key = '', $byKey = false ) {

		// Build final keys and replace $items keys with the new keys
		$result        = false;
		$derived_keys  = $this->buildKeys( array_keys( $items ), $groups );
		$expiration    = $this->sanitize_expiration( $expiration );
		$derived_items = array_combine( $derived_keys, $items );
		$group_offset  = empty( $this->cache_key_salt ) ? 1 : 2;

		// Do not add to cache if in no_mc_groups
		foreach ( $derived_items as $derived_key => $value ) {

			// Get the individual item's group
			$key_pieces = explode( ':', $derived_key );

			// If group is a non-cache group, save to runtime cache, not cache
			if ( in_array( $key_pieces[ $group_offset ], $this->no_mc_groups, false ) ) {
				$this->add_to_internal_cache( $derived_key, $value );
				unset( $derived_items[ $derived_key ] );
			}
		}

		// Save to cache
		if ( method_exists( $this->daemon, 'setMulti' ) ) {
			$result = ( false !== $byKey )
				? $this->daemon->setMultiByKey( $server_key, $derived_items, $expiration )
				: $this->daemon->setMulti( $derived_items, $expiration );
		}

		// Store in runtime cache if add was successful
		if ( $this->success() ) {
			$this->cache = array_merge( $this->cache, $derived_items );
		}

		return $result;
	}

	/**
	 * Set multiple values to cache at once on specified server.
	 *
	 * By sending an array of $items to this function, all values are saved at once to
	 * cache, reducing the need for multiple requests to cache. The $items array
	 * keys and values are what are stored to cache. The keys in the $items array
	 * are merged with the $groups array/string value via buildKeys to determine the
	 * final key for the object.
	 *
	 * @link    http://www.php.net/manual/en/memcached.setmultibykey.php
	 *
	 * @param   string          $server_key     The key identifying the server to store the value on.
	 * @param   array           $items          An array of key/value pairs to store on the server.
	 * @param   string|array    $groups         Group(s) to merge with key(s) in $items.
	 * @param   int             $expiration     The expiration time, defaults to 0.
	 * @return  bool                            Returns true on success or false on failure.
	 */
	public function setMultiByKey( $server_key, $items, $groups = 'default', $expiration = 0 ) {
		return $this->setMulti( $items, $groups, $expiration, $server_key, true );
	}

	/**
	 * Set a cache engine option.
	 *
	 * @link    http://www.php.net/manual/en/memcached.setoption.php
	 *
	 * @param   int         $option     Option name.
	 * @param   mixed       $value      Option value.
	 * @return  bool                    Returns true on success or false on failure.
	 */
	public function setOption( $option, $value ) {
		return method_exists( $this->daemon, 'setOption' )
			? $this->daemon->setOption( $option, $value )
			: false;
	}

	/**
	 * Was the most recent result successful?
	 *
	 * @return bool
	 */
	public function success() {
		return ( $this->success_code === $this->getResultCode() );
	}

	/**
	 * Builds a key for the cached object using the blog_id, key, and group values.
	 *
	 * @author  Ryan Boren   This function is inspired by the original Memcached plugin.
	 * @link    http://wordpress.org/plugins/memcached/
	 *
	 * @param   string      $key        The key under which to store the value.
	 * @param   string      $group      The group value appended to the $key.
	 * @return  string
	 */
	public function buildKey( $key, $group = 'default' ) {

		// Setup empty keys array
		$keys = array();

		// Force default group if none is passed
		if ( empty( $group ) ) {
			$group = 'default';
		}

		// Prefix with key salt if set
		if ( ! empty( $this->cache_key_salt ) ) {
			$keys['salt'] = $this->cache_key_salt;
		}

		// Decide the prefix
		$keys['prefix'] = ( false !== array_search( $group, $this->global_groups, true ) )
			? $this->global_prefix
			: $this->blog_prefix;

		// Setup group
		$keys['group'] = $group;

		// BuddyPress multi-network namespace
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			// Check for BuddyPress groups
			if ( ! strstr( $key, ':community:' ) && ( ( 'bp' === $group ) || ( 'bp_' === substr( $group, 0, 3 ) ) || in_array( $group, array( 'activity_meta', 'blog_meta', 'group_meta', 'message_meta', 'notification_meta', 'xprofile_meta', 'xprofile_group_meta', 'xprofile_field_meta', 'xprofile_data_meta' ) ) ) ) {
				$keys['prefix'] = get_current_site()->blog_id;
				$keys['group']  = $group . ':community';
			}
		}

		// Setup key
		$keys['key'] = $key;

		// Remove empties
		$good_keys = array_filter( $keys );

		// Assemble the cache key
		$cache_key = implode( $good_keys, ':' );

		// Prevent double colons
		$cache_key = str_replace( '::', ':', $cache_key );

		// Remove all whitespace
		$cache_key = preg_replace( '/\s+/', '', $cache_key );

		// Return the built cache key
		return $cache_key;
	}

	/**
	 * Creates an array of keys from passed key(s) and group(s).
	 *
	 * This function takes a string or array of key(s) and group(s) and combines them into a single dimensional
	 * array that merges the keys and groups. If the same number of keys and groups exist, the final keys will
	 * append $groups[n] to $keys[n]. If there are more keys than groups and the $groups parameter is an array,
	 * $keys[n] will be combined with $groups[n] until $groups runs out of values. 'default' will be used for remaining
	 * values. If $keys is an array and $groups is a string, all final values will append $groups to $keys[n].
	 * If both values are strings, they will be combined into a single string. Note that if more $groups are received
	 * than $keys, the method will return an empty array. This method is primarily a helper method for methods
	 * that call cache with an array of keys.
	 *
	 * @param   string|array    $keys       Key(s) to merge with group(s).
	 * @param   string|array    $groups     Group(s) to merge with key(s).
	 * @return  array                       Array that combines keys and groups into a single set of cache keys.
	 */
	public function buildKeys( $keys, $groups = 'default' ) {
		$derived_keys = array();

		// If strings sent, convert to arrays for proper handling
		if ( ! is_array( $groups ) ) {
			$groups = (array) $groups;
		}

		if ( ! is_array( $keys ) ) {
			$keys = (array) $keys;
		}

		// If we have equal numbers of keys and groups, merge $keys[n] and $group[n]
		if ( count( $keys ) === count( $groups ) ) {
			$imax = count( $keys );
			for ( $i = 0; $i < $imax; $i++ ) {
				$derived_keys[] = $this->buildKey( $keys[$i], $groups[$i] );
			}

		// If more keys are received than groups, merge $keys[n] and $group[n] until no more group are left; remaining groups are 'default'
		} elseif ( count( $keys ) > count( $groups ) ) {
			$imax = count( $keys );
			for ( $i = 0; $i < $imax; $i++ ) {
				if ( isset( $groups[$i] ) ) {
					$derived_keys[] = $this->buildKey( $keys[$i], $groups[$i] );
				} elseif ( count( $groups ) === 1 ) {
					$derived_keys[] = $this->buildKey( $keys[$i], $groups[0] );
				} else {
					$derived_keys[] = $this->buildKey( $keys[$i], 'default' );
				}
			}
		}

		return $derived_keys;
	}

	/**
	 * Ensure that a proper expiration time is set.
	 *
	 * Memcached treats any value over 30 days as a timestamp. If a developer sets the expiration for greater than 30
	 * days or less than the current timestamp, the timestamp is in the past and the value isn't cached. This function
	 * detects values in that range and corrects them.
	 *
	 * @param  string|int    $expiration    The dirty expiration time.
	 * @return string|int                   The sanitized expiration time.
	 */
	public function sanitize_expiration( $expiration ) {
		if ( $expiration > $this->thirty_days && $expiration <= $this->now ) {
			$expiration += $this->now;
		}

		return $expiration;
	}

	/**
	 * Concatenates two values and casts to type of the first value.
	 *
	 * This is used in append and prepend operations to match how these functions are handled
	 * by cache. In both cases, whichever value is the original value in the combined value
	 * will dictate the type of the combined value.
	 *
	 * @param   mixed       $original   Original value that dictates the combined type.
	 * @param   mixed       $pended     Value to combine with original value.
	 * @param   string      $direction  Either 'pre' or 'app'.
	 * @return  mixed                   Combined value casted to the type of the first value.
	 */
	public function combine_values( $original, $pended, $direction ) {
		$type = gettype( $original );

		// Combine the values based on direction of the "pend"
		if ( 'pre' === $direction ) {
			$combined = $pended . $original;
		} else {
			$combined = $original . $pended;
		}

		// Cast type of combined value
		settype( $combined, $type );

		return $combined;
	}

	/**
	 * Simple wrapper for saving object to the internal cache.
	 *
	 * @param   string      $derived_key    Key to save value under.
	 * @param   mixed       $value          Object value.
	 */
	public function add_to_internal_cache( $derived_key, $value ) {
		if ( is_object( $value ) ) {
			$value = clone $value;
		}

		$this->cache[ $derived_key ] = $value;
	}

	/**
	 * Determines if a no_mc_group exists in a group of groups.
	 *
	 * @param   mixed   $groups     The groups to search.
	 * @return  bool                True if a no_mc_group is present; false if a no_mc_group is not present.
	 */
	public function contains_no_mc_group( $groups ) {
		if ( is_scalar( $groups ) ) {
			return in_array( $groups, $this->no_mc_groups, false );
		}

		if ( ! is_array( $groups ) ) {
			return false;
		}

		foreach ( $groups as $group ) {
			if ( in_array( $group, $this->no_mc_groups, false ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add global groups.
	 *
	 * @author  Ryan Boren   This function comes straight from the original Memcached plugin
	 * @link    http://wordpress.org/plugins/memcached/
	 *
	 * @param   array       $groups     Array of groups.
	 * @return  void
	 */
	public function add_global_groups( $groups ) {
		if ( ! is_array( $groups ) ) {
			$groups = (array) $groups;
		}

		$this->global_groups = array_merge( $this->global_groups, $groups );
		$this->global_groups = array_unique( $this->global_groups );
	}

	/**
	 * Add non-persistent groups.
	 *
	 * @author  Ryan Boren   This function comes straight from the original Memcached plugin
	 * @link    http://wordpress.org/plugins/memcached/
	 *
	 * @param   array       $groups     Array of groups.
	 * @return  void
	 */
	public function add_non_persistent_groups( $groups ) {
		if ( ! is_array( $groups ) ) {
			$groups = (array) $groups;
		}

		$this->no_mc_groups = array_merge( $this->no_mc_groups, $groups );
		$this->no_mc_groups = array_unique( $this->no_mc_groups );
	}

	/**
	 * Get a value specifically from the internal, run-time cache, not persistent cache.
	 *
	 * @param   int|string  $key        Key value.
	 * @param   int|string  $group      Group that the value belongs to.
	 * @return  bool|mixed              Value on success; false on failure.
	 */
	public function get_from_runtime_cache( $key, $group ) {
		$derived_key = $this->buildKey( $key, $group );

		if ( isset( $this->cache[ $derived_key ] ) ) {
			return $this->cache[ $derived_key ];
		}

		return false;
	}

	/**
	 * Switch blog prefix, which changes the cache that is accessed.
	 *
	 * @param  int     $blog_id    Blog to switch to.
	 * @return void
	 */
	public function switch_to_blog( $blog_id = 0 ) {
		global $table_prefix;

		$this->blog_prefix = is_multisite()
			? (int) $blog_id
			: $table_prefix;
	}
}
