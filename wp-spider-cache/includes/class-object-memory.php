<?php

/**
 * Spider Cache Memory
 *
 * @package Plugins/Cache/Object
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WP_Spider_Cache_Object_Base' ) ) :
/**
 * Core class that implements an object cache.
 *
 * The WordPress Object Cache is used to save on trips to the database. The
 * Object Cache stores all of the cache data to memory and makes the cache
 * contents available by using a key, which is used to name and later retrieve
 * the cache contents.
 *
 * The Object Cache can be replaced by other caching mechanisms by placing files
 * in the wp-content folder which is looked at in wp-settings. If that file
 * exists, then this file will not be included.
 *
 * @since 2.0.0
 */
class WP_Spider_Cache_Object extends WP_Spider_Cache_Object_Base {

	/**
	 * Holds the cache engine class name.
	 *
	 * @var string
	 */
	public $engine_class_name = 'Memory';

	/**
	 * Holds the cache daemon class name.
	 *
	 * @var string
	 */
	public $daemon_class_name = 'Memory';

	/**
	 * Holds the cached objects.
	 *
	 * @since 7.0.0
	 * @var array
	 */
	public $cache = array();

	/**
	 * The amount of times the cache data was already stored in the cache.
	 *
	 * @since 7.0.0
	 * @var int
	 */
	public $cache_hits = 0;

	/**
	 * Amount of times the cache did not have the request in cache.
	 *
	 * @since 7.0.0
	 * @var int
	 */
	public $cache_misses = 0;

	/**
	 * List of global cache groups.
	 *
	 * @since 7.0.0
	 * @var array
	 */
	public $global_groups = array();

	/**
	 * The blog prefix to prepend to keys in non-global groups.
	 *
	 * @since 7.0.0
	 * @var int
	 */
	public $blog_prefix;

	/**
	 * Holds the value of is_multisite().
	 *
	 * @since 7.0.0
	 * @var bool
	 */
	private $multisite;

	/**
	 * Makes private properties readable for backward compatibility.
	 *
	 * @since 7.0.0
	 *
	 * @param string $name Property to get.
	 * @return mixed Property.
	 */
	public function __get( $name ) {
		return $this->{$name};
	}

	/**
	 * Makes private properties settable for backward compatibility.
	 *
	 * @since 7.0.0
	 *
	 * @param string $name  Property to set.
	 * @param mixed  $value Property value.
	 * @return mixed Newly-set property.
	 */
	public function __set( $name, $value ) {
		return $this->{$name} = $value;
	}

	/**
	 * Makes private properties checkable for backward compatibility.
	 *
	 * @since 7.0.0
	 *
	 * @param string $name Property to check if set.
	 * @return bool Whether the property is set.
	 */
	public function __isset( $name ) {
		return isset( $this->{$name} );
	}

	/**
	 * Makes private properties un-settable for backward compatibility.
	 *
	 * @since 7.0.0
	 *
	 * @param string $name Property to unset.
	 */
	public function __unset( $name ) {
		unset( $this->{$name} );
	}

	/**
	 * Adds data to the cache if it doesn't already exist.
	 *
	 * @since 7.0.0
	 *
	 * @uses WP_Object_Cache::_exists() Checks to see if the cache already has data.
	 * @uses WP_Object_Cache::set()     Sets the data after the checking the cache
	 *		                            contents existence.
	 *
	 * @param int|string $key    What to call the contents in the cache.
	 * @param mixed      $value  The contents to store in the cache.
	 * @param string     $group  Optional. Where to group the cache contents. Default 'default'.
	 * @param int        $expire Optional. When to expire the cache contents. Default 0 (no expiration).
	 * @return bool False if cache key and group already exist, true on success
	 */
	public function add( $key, $value, $group = 'default', $expiration = 0, $server_key = '', $byKey = false ) {

		/**
		 * Ensuring that wp_suspend_cache_addition is defined before calling,
		 * because sometimes an advanced-cache.php file will load object-cache.php
		 * before wp-includes/functions.php is loaded.
		 *
		 * In those cases, if wp_cache_add() is called in advanced-cache.php
		 * before any more of WordPress is loaded, we get a fatal error because
		 * wp_suspend_cache_addition() will not be defined until
		 * wp-includes/functions.php is loaded.
		 */
		if ( function_exists( 'wp_suspend_cache_addition' ) && wp_suspend_cache_addition() ) {
			return false;
		}

		if ( empty( $group ) ) {
			$group = 'default';
		}

		$id = $key;
		if ( $this->multisite && ! isset( $this->global_groups[ $group ] ) ) {
			$id = $this->blog_prefix . $key;
		}

		if ( $this->_exists( $id, $group ) ) {
			return false;
		}

		return $this->set( $key, $value, $group, (int) $expiration );
	}

	/**
	 * Sets the list of global cache groups.
	 *
	 * @since 7.0.0
	 *
	 * @param array $groups List of groups that are global.
	 */
	public function add_global_groups( $groups = array() ) {
		$groups = array_fill_keys( (array) $groups, true );
		$this->global_groups = array_merge( $this->global_groups, $groups );
	}

	/**
	 * Decrements numeric cache item's value.
	 *
	 * @since 7.0.0
	 *
	 * @param int|string $key    The cache key to decrement.
	 * @param int        $offset Optional. The amount by which to decrement the item's value. Default 1.
	 * @param string     $group  Optional. The group the key is in. Default 'default'.
	 * @return false|int False on failure, the item's new value on success.
	 */
	public function decr( $key, $offset = 1, $group = 'default' ) {
		if ( empty( $group ) ) {
			$group = 'default';
		}

		if ( $this->multisite && ! isset( $this->global_groups[ $group ] ) ) {
			$key = $this->blog_prefix . $key;
		}

		if ( ! $this->_exists( $key, $group ) ) {
			return false;
		}

		if ( ! is_numeric( $this->cache[ $group ][ $key ] ) ) {
			$this->cache[ $group ][ $key ] = 0;
		}

		$offset = (int) $offset;

		$this->cache[ $group ][ $key ] -= $offset;

		if ( $this->cache[ $group ][ $key ] < 0 ) {
			$this->cache[ $group ][ $key ] = 0;
		}

		return $this->cache[ $group ][ $key ];
	}

	/**
	 * Removes the contents of the cache key in the group.
	 *
	 * If the cache key does not exist in the group, then nothing will happen.
	 *
	 * @since 7.0.0
	 *
	 * @param int|string $key        What the contents in the cache are called.
	 * @param string     $group      Optional. Where the cache contents are grouped. Default 'default'.
	 * @param bool       $deprecated Optional. Unused. Default false.
	 * @return bool False if the contents weren't deleted and true on success.
	 */
	public function delete( $key, $group = 'default', $time = 0, $server_key = '', $byKey = false ) {
		if ( empty( $group ) ) {
			$group = 'default';
		}

		if ( $this->multisite && ! isset( $this->global_groups[ $group ] ) ) {
			$key = $this->blog_prefix . $key;
		}

		if ( ! $this->_exists( $key, $group ) ) {
			return false;
		}

		unset( $this->cache[$group][$key] );
		return true;
	}

	/**
	 * Clears the object cache of all data.
	 *
	 * @since 7.0.0
	 *
	 * @return true Always returns true.
	 */
	public function flush( $delay = 0 ) {
		$this->cache = array();

		return true;
	}

	/**
	 * Retrieves the cache contents, if it exists.
	 *
	 * The contents will be first attempted to be retrieved by searching by the
	 * key in the cache group. If the cache is hit (success) then the contents
	 * are returned.
	 *
	 * On failure, the number of cache misses will be incremented.
	 *
	 * @since 7.0.0
	 *
	 * @param int|string $key    What the contents in the cache are called.
	 * @param string     $group  Optional. Where the cache contents are grouped. Default 'default'.
	 * @param string     $force  Optional. Unused. Whether to force a refetch rather than relying on the local
	 *                           cache. Default false.
	 * @param bool       $found  Optional. Whether the key was found in the cache (passed by reference).
	 *                           Disambiguates a return of false, a storable value. Default null.
	 * @return false|mixed False on failure to retrieve contents or the cache contents on success.
	 */
	public function get( $key, $group = 'default', $force = false, &$found = NULL, $server_key = '', $byKey = false, $cache_cb = NULL, &$cas_token = NULL ) {
		if ( empty( $group ) ) {
			$group = 'default';
		}

		if ( $this->multisite && ! isset( $this->global_groups[ $group ] ) ) {
			$key = $this->blog_prefix . $key;
		}

		if ( $this->_exists( $key, $group ) ) {
			$found = true;
			$this->cache_hits += 1;
			if ( is_object($this->cache[$group][$key]) ) {
				return clone $this->cache[$group][$key];
			} else {
				return $this->cache[$group][$key];
			}
		}

		$found = false;
		$this->cache_misses += 1;
		return false;
	}

	/**
	 * Increments numeric cache item's value.
	 *
	 * @since 7.0.0
	 *
	 * @param int|string $key    The cache key to increment
	 * @param int        $offset Optional. The amount by which to increment the item's value. Default 1.
	 * @param string     $group  Optional. The group the key is in. Default 'default'.
	 * @return false|int False on failure, the item's new value on success.
	 */
	public function incr( $key, $offset = 1, $group = 'default' ) {
		if ( empty( $group ) ) {
			$group = 'default';
		}

		if ( $this->multisite && ! isset( $this->global_groups[ $group ] ) ) {
			$key = $this->blog_prefix . $key;
		}

		if ( ! $this->_exists( $key, $group ) ) {
			return false;
		}

		if ( ! is_numeric( $this->cache[ $group ][ $key ] ) ) {
			$this->cache[ $group ][ $key ] = 0;
		}

		$offset = (int) $offset;

		$this->cache[ $group ][ $key ] += $offset;

		if ( $this->cache[ $group ][ $key ] < 0 ) {
			$this->cache[ $group ][ $key ] = 0;
		}

		return $this->cache[ $group ][ $key ];
	}

	/**
	 * Replaces the contents in the cache, if contents already exist.
	 *
	 * @since 7.0.0
	 *
	 * @see WP_Object_Cache::set()
	 *
	 * @param int|string $key    What to call the contents in the cache.
	 * @param mixed      $value  The contents to store in the cache.
	 * @param string     $group  Optional. Where to group the cache contents. Default 'default'.
	 * @param int        $expire Optional. When to expire the cache contents. Default 0 (no expiration).
	 * @return bool False if not exists, true if contents were replaced.
	 */
	public function replace( $key, $value, $group = 'default', $expiration = 0, $server_key = '', $byKey = false ) {
		if ( empty( $group ) ) {
			$group = 'default';
		}

		$id = $key;
		if ( $this->multisite && ! isset( $this->global_groups[ $group ] ) ) {
			$id = $this->blog_prefix . $key;
		}

		if ( ! $this->_exists( $id, $group ) ) {
			return false;
		}

		return $this->set( $key, $value, $group, (int) $expiration );
	}

	/**
	 * Resets cache keys.
	 *
	 * @since 7.0.0
	 *
	 * @deprecated 3.5.0 Use switch_to_blog()
	 * @see switch_to_blog()
	 */
	public function reset() {
		_deprecated_function( __FUNCTION__, '3.5.0', 'switch_to_blog()' );

		// Clear out non-global caches since the blog ID has changed.
		foreach ( array_keys( $this->cache ) as $group ) {
			if ( ! isset( $this->global_groups[ $group ] ) ) {
				unset( $this->cache[ $group ] );
			}
		}
	}

	/**
	 * Sets the data contents into the cache.
	 *
	 * The cache contents is grouped by the $group parameter followed by the
	 * $key. This allows for duplicate ids in unique groups. Therefore, naming of
	 * the group should be used with care and should follow normal function
	 * naming guidelines outside of core WordPress usage.
	 *
	 * The $expire parameter is not used, because the cache will automatically
	 * expire for each time a page is accessed and PHP finishes. The method is
	 * more for cache plugins which use files.
	 *
	 * @since 7.0.0
	 *
	 * @param int|string $key    What to call the contents in the cache.
	 * @param mixed      $value  The contents to store in the cache.
	 * @param string     $group  Optional. Where to group the cache contents. Default 'default'.
	 * @param int        $expire Not Used.
	 * @return true Always returns true.
	 */
	public function set( $key, $value, $group = 'default', $expiration = 0, $server_key = '', $byKey = false ) {
		if ( empty( $group ) ) {
			$group = 'default';
		}

		if ( $this->multisite && ! isset( $this->global_groups[ $group ] ) ) {
			$key = $this->blog_prefix . $key;
		}

		if ( is_object( $value ) ) {
			$value = clone $value;
		}

		$this->cache[$group][$key] = $value;
		return true;
	}

	/**
	 * Echoes the stats of the caching.
	 *
	 * Gives the cache hits, and cache misses. Also prints every cached group,
	 * key and the data.
	 *
	 * @since 7.0.0
	 */
	public function stats() {
		echo "<p>";
		echo "<strong>Cache Hits:</strong> {$this->cache_hits}<br />";
		echo "<strong>Cache Misses:</strong> {$this->cache_misses}<br />";
		echo "</p>";
		echo '<ul>';
		foreach ($this->cache as $group => $cache) {
			echo "<li><strong>Group:</strong> $group - ( " . number_format( strlen( serialize( $cache ) ) / KB_IN_BYTES, 2 ) . 'k )</li>';
		}
		echo '</ul>';
	}

	/**
	 * Switches the internal blog ID.
	 *
	 * This changes the blog ID used to create keys in blog specific groups.
	 *
	 * @since 7.0.0
	 *
	 * @param int $blog_id Blog ID.
	 */
	public function switch_to_blog( $blog_id = 0 ) {
		$blog_id = (int) $blog_id;
		$this->blog_prefix = $this->multisite
			? $blog_id . ':'
			: '';
	}

	/**
	 * Serves as a utility function to determine whether a key exists in the cache.
	 *
	 * @since 7.0.0
	 *
	 * @param int|string $key   Cache key to check for existence.
	 * @param string     $group Cache group for the key existence check.
	 * @return bool Whether the key exists in the cache for the given group.
	 */
	protected function _exists( $key, $group ) {
		return isset( $this->cache[ $group ] ) && ( isset( $this->cache[ $group ][ $key ] ) || array_key_exists( $key, $this->cache[ $group ] ) );
	}

	/**
	 * Sets up object properties; PHP 5 style constructor.
	 *
	 * @since 7.0.0
	 */
	public function __construct() {
		$this->multisite = is_multisite();
		$this->blog_prefix =  $this->multisite
			? get_current_blog_id() . ':'
			: '';

		/**
		 * @todo This should be moved to the PHP4 style constructor, PHP5
		 * already calls __destruct()
		 */
		register_shutdown_function( array( $this, '__destruct' ) );
	}

	/**
	 * Saves the object cache before object is completely destroyed.
	 *
	 * Called upon object destruction, which should be when PHP ends.
	 *
	 * @since 7.0.0
	 *
	 * @return true Always returns true.
	 */
	public function __destruct() {
		return true;
	}
}
endif;