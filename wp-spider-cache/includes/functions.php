<?php

/**
 * Spider Cache Functions
 *
 * Almost all of these functions are based on known Memcached methods, though
 * for other popular engines (Redis, etc...) you'd want to use the same function
 * names with different internals.
 *
 * Some functions (like wp_object_cache()) were introduced to wrap around an
 * otherwise ambiguous cache global, to make them engine-agnostic.
 *
 * @package Plugins/Cache/Functions
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Adds a value to cache.
 *
 * If the specified key already exists, the value is not stored and the function
 * returns false.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.add.php
 *
 * @param string    $key        The key under which to store the value.
 * @param mixed     $value      The value to store.
 * @param string    $group      The group value appended to the $key.
 * @param int       $expiration The expiration time, defaults to 0.
 * @return bool                 Returns TRUE on success or FALSE on failure.
 */
function wp_cache_add( $key, $value, $group = '', $expiration = 0 ) {
	return wp_object_cache()->add( $key, $value, $group, $expiration );
}

/**
 * Adds a value to cache on a specific server.
 *
 * Using a server_key value, the object can be stored on a specified server as opposed
 * to a random server in the stack. Note that this method will add the key/value to the
 * _cache object as part of the runtime cache. It will add it to an array for the
 * specified server_key.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.addbykey.php
 *
 * @param string    $server_key     The key identifying the server to store the value on.
 * @param string    $key            The key under which to store the value.
 * @param mixed     $value          The value to store.
 * @param string    $group          The group value appended to the $key.
 * @param int       $expiration     The expiration time, defaults to 0.
 * @return bool                     Returns TRUE on success or FALSE on failure.
 */
function wp_cache_add_by_key( $server_key, $key, $value, $group = '', $expiration = 0 ) {
	return wp_object_cache()->addByKey( $server_key, $key, $value, $group, $expiration );
}

/**
 * Add a single server to the list of cache servers.
 *
 * @link http://www.php.net/manual/en/memcached.addserver.php
 *
 * @param string        $host   The hostname of the memcache server.
 * @param int           $port   The port on which memcache is running.
 * @param int           $weight The weight of the server relative to the total weight of all the servers in the pool.
 * @return bool                 Returns TRUE on success or FALSE on failure.
 */
function wp_cache_add_server( $host, $port, $weight = 0 ) {
	return wp_object_cache()->addServer( $host, $port, $weight );
}

/**
 * Adds an array of servers to the pool.
 *
 * Each individual server in the array must include a domain and port, with an optional
 * weight value: $servers = array( array( '127.0.0.1', 11211, 0 ) );
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.addservers.php
 *
 * @param array     $servers    Array of server to register.
 * @return bool                 True on success; false on failure.
 */
function wp_cache_add_servers( $servers ) {
	return wp_object_cache()->addServers( $servers );
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
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.append.php
 *
 * @param string    $key    The key under which to store the value.
 * @param mixed     $value  Must be string as appending mixed values is not well-defined
 * @param string    $group  The group value appended to the $key.
 * @return bool             Returns TRUE on success or FALSE on failure.
 */
function wp_cache_append( $key, $value, $group = '' ) {
	return wp_object_cache()->append( $key, $value, $group );
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
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.appendbykey.php
 *
 * @param string    $server_key     The key identifying the server to store the value on.
 * @param string    $key            The key under which to store the value.
 * @param mixed     $value          Must be string as appending mixed values is not well-defined
 * @param string    $group          The group value appended to the $key.
 * @return bool                     Returns TRUE on success or FALSE on failure.
 */
function wp_cache_append_by_key( $server_key, $key, $value, $group = '' ) {
	return wp_object_cache()->appendByKey( $server_key, $key, $value, $group );
}

/**
 * Performs a "check and set" to store data.
 *
 * The set will be successful only if the no other request has updated the value since it was fetched by
 * this request.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.cas.php
 *
 * @param float     $cas_token  Unique value associated with the existing item. Generated by memcached.
 * @param string    $key        The key under which to store the value.
 * @param mixed     $value      The value to store.
 * @param string    $group      The group value appended to the $key.
 * @param int       $expiration The expiration time, defaults to 0.
 * @return bool                 Returns TRUE on success or FALSE on failure.
 */
function wp_cache_cas( $cas_token, $key, $value, $group = '', $expiration = 0 ) {
	return wp_object_cache()->cas( $cas_token, $key, $value, $group, $expiration );
}

/**
 * Performs a "check and set" to store data with a server key.
 *
 * The set will be successful only if the no other request has updated the value since it was fetched by
 * this request.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.casbykey.php
 *
 * @param float     $cas_token  Unique value associated with the existing item. Generated by memcached.
 * @param string    $server_key The key identifying the server to store the value on.
 * @param string    $key        The key under which to store the value.
 * @param mixed     $value      The value to store.
 * @param string    $group      The group value appended to the $key.
 * @param int       $expiration The expiration time, defaults to 0.
 * @return bool                 Returns TRUE on success or FALSE on failure.
 */
function wp_cache_cas_by_key( $cas_token, $server_key, $key, $value, $group = '', $expiration = 0 ) {
	return wp_object_cache()->casByKey( $cas_token, $server_key, $key, $value, $group, $expiration );
}

/**
 * Closes the cache.
 *
 * This function is used to close a connection to a cache server.
 *
 * @since 2.0.0
 *
 * @return  bool    Return TRUE on success or FALSE on failure.
 */
function wp_cache_close() {
	return wp_object_cache()->close();
}

/**
 * Connect to a cache serve
 *
 * This function is used to create a connection to a cache server.
 *
 * @since 2.2.0
 *
 * @return  bool    Return TRUE on success or FALSE on failure.
 */
function wp_cache_connect( $server = '127.0.0.1', $port = 11211 ) {
	return wp_object_cache()->connect( $server, $port );
}

/**
 * Decrement a numeric item's value.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.decrement.php
 *
 * @param string    $key    The key under which to store the value.
 * @param int       $offset The amount by which to decrement the item's value.
 * @param string    $group  The group value appended to the $key.
 * @return int|bool         Returns item's new value on success or FALSE on failure.
 */
function wp_cache_decrement( $key, $offset = 1, $group = '' ) {
	return wp_object_cache()->decrement( $key, $offset, $group );
}

/**
 * Decrement a numeric item's value.
 *
 * Same as wp_cache_decrement. Original WordPress caching backends use wp_cache_decr. I
 * want both spellings to work.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.decrement.php
 *
 * @param string    $key    The key under which to store the value.
 * @param int       $offset The amount by which to decrement the item's value.
 * @param string    $group  The group value appended to the $key.
 * @return int|bool         Returns item's new value on success or FALSE on failure.
 */
function wp_cache_decr( $key, $offset = 1, $group = '' ) {
	return wp_cache_decrement( $key, $offset, $group );
}

/**
 * Remove the item from the cache.
 *
 * Remove an item from memcached with identified by $key after $time seconds. The
 * $time parameter allows an object to be queued for deletion without immediately
 * deleting. Between the time that it is queued and the time it's deleted, add,
 * replace, and get will fail, but set will succeed.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.delete.php
 *
 * @param string    $key    The key under which to store the value.
 * @param string    $group  The group value appended to the $key.
 * @param int       $time   The amount of time the server will wait to delete the item in seconds.
 * @return bool             Returns TRUE on success or FALSE on failure.
 */
function wp_cache_delete( $key, $group = '', $time = 0 ) {
	return wp_object_cache()->delete( $key, $group, $time );
}

/**
 * Remove the item from the cache by server key.
 *
 * Remove an item from memcached with identified by $key after $time seconds. The
 * $time parameter allows an object to be queued for deletion without immediately
 * deleting. Between the time that it is queued and the time it's deleted, add,
 * replace, and get will fail, but set will succeed.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.deletebykey.php
 *
 * @param string        $server_key The key identifying the server to store the value on.
 * @param string        $key        The key under which to store the value.
 * @param string        $group      The group value appended to the $key.
 * @param int           $time       The amount of time the server will wait to delete the item in seconds.
 * @return bool                     Returns TRUE on success or FALSE on failure.
 */
function wp_cache_delete_by_key( $server_key, $key, $group = '', $time = 0 ) {
	return wp_object_cache()->deleteByKey( $server_key, $key, $group, $time );
}

/**
 * Fetch the next result.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.fetch.php
 *
 * @return  array|bool   Returns the next result or FALSE otherwise.
 */
function wp_cache_fetch() {
	return wp_object_cache()->fetch();
}

/**
 * Fetch all remaining results from the last request.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.fetchall.php
 *
 * @return  array|bool  Returns the results or FALSE on failure.
 */
function wp_cache_fetch_all() {
	return wp_object_cache()->fetchAll();
}

/**
 * Invalidate all items in the cache.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.flush.php
 *
 * @param int       $delay  Number of seconds to wait before invalidating the items.
 * @return bool             Returns TRUE on success or FALSE on failure.
 */
function wp_cache_flush( $delay = 0 ) {
	return wp_object_cache()->flush( $delay );
}

/**
 * Retrieve object from cache.
 *
 * Gets an object from cache based on $key and $group. In order to fully support the $cache_cb and $cas_token
 * parameters, the runtime cache is ignored by this function if either of those values are set. If either of
 * those values are set, the request is made directly to the memcached server for proper handling of the
 * callback and/or token.
 *
 * Note that the $deprecated and $found args are only here for compatibility with the native wp_cache_get function.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.get.php
 *
 * @param string        $key        The key under which to store the value.
 * @param string        $group      The group value appended to the $key.
 * @param bool          $force      Whether or not to force a cache invalidation.
 * @param null|bool     $found      Variable passed by reference to determine if the value was found or not.
 * @param null|string   $cache_cb   Read-through caching callback.
 * @param null|float    $cas_token  The variable to store the CAS token in.
 * @return bool|mixed               Cached object value.
 */
function wp_cache_get( $key, $group = '', $force = false, &$found = null, $cache_cb = null, &$cas_token = null ) {
	if ( func_num_args() > 4 ) {
		return wp_object_cache()->get( $key, $group, $force, $found, '', false, $cache_cb, $cas_token );
	} else {
		return wp_object_cache()->get( $key, $group, $force, $found );
	}
}

/**
 * Retrieve object from cache from specified server.
 *
 * Gets an object from cache based on $key, $group and $server_key. In order to fully support the $cache_cb and $cas_token
 * parameters, the runtime cache is ignored by this function if either of those values are set. If either of
 * those values are set, the request is made directly to the memcached server for proper handling of the
 * callback and/or token.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.getbykey.php
 *
 * @param string        $server_key The key identifying the server to store the value on.
 * @param string        $key        The key under which to store the value.
 * @param string        $group      The group value appended to the $key.
 * @param bool          $force      Whether or not to force a cache invalidation.
 * @param null|bool     $found      Variable passed by reference to determine if the value was found or not.
 * @param null|string   $cache_cb   Read-through caching callback.
 * @param null|float    $cas_token  The variable to store the CAS token in.
 * @return bool|mixed               Cached object value.
 */
function wp_cache_get_by_key( $server_key, $key, $group = '', $force = false, &$found = null, $cache_cb = NULL, &$cas_token = NULL ) {
	if ( func_num_args() > 5 ) {
		return wp_object_cache()->getByKey( $server_key, $key, $group, $force, $found, $cache_cb, $cas_token );
	} else {
		return wp_object_cache()->getByKey( $server_key, $key, $group, $force, $found );
	}
}

/**
 * Request multiple keys without blocking.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.getdelayed.php
 *
 * @param string|array  $keys       Array or string of key(s) to request.
 * @param string|array  $groups     Array or string of group(s) for the key(s). See buildKeys for more on how these are handled.
 * @param bool          $with_cas   Whether to request CAS token values also.
 * @param null          $value_cb   The result callback or NULL.
 * @return bool                     Returns TRUE on success or FALSE on failure.
 */
function wp_cache_get_delayed( $keys, $groups = '', $with_cas = false, $value_cb = NULL ) {
	return wp_object_cache()->getDelayed( $keys, $groups, $with_cas, $value_cb );
}

/**
 * Request multiple keys without blocking from a specified server.
 *
 * @link http://www.php.net/manual/en/memcached.getdelayed.php
 *
 * @param string        $server_key The key identifying the server to store the value on.
 * @param string|array  $keys       Array or string of key(s) to request.
 * @param string|array  $groups     Array or string of group(s) for the key(s). See buildKeys for more on how these are handled.
 * @param bool          $with_cas   Whether to request CAS token values also.
 * @param null          $value_cb   The result callback or NULL.
 * @return bool                     Returns TRUE on success or FALSE on failure.
 */
function wp_cache_get_delayed_by_key( $server_key, $keys, $groups = '', $with_cas = false, $value_cb = NULL ) {
	return wp_object_cache()->getDelayedByKey( $server_key, $keys, $groups, $with_cas, $value_cb );
}

/**
 * Get extended server pool statistics.
 *
 * @since 2.2.0
 *
 * @link http://www.php.net/manual/en/memcached.getstats.php
 *
 * @return array    Array of server statistics, one entry per server.
 */
function wp_cache_get_extended_stats( $type, $slab_id = 0, $limit = 100) {
	return wp_object_cache()->getExtendedStats( $type, $slab_id, $limit);
}

/**
 * Retrieve a cache key based on key & group.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.get.php
 *
 * @param string        $key        The key under which a value is stored.
 * @param string        $group      The group value appended to the $key.
 * @return string                   Returns the cache key used for getting & setting.
 */
function wp_cache_get_key( $key, $group = '' ) {
	return wp_object_cache()->buildKey( $key, $group );
}

/**
 * Gets multiple values from memcached in one request.
 *
 * See the buildKeys method definition to understand the $keys/$groups parameters.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.getmulti.php
 *
 * @param array         $keys       Array of keys to retrieve.
 * @param string|array  $groups     If string, used for all keys. If arrays, corresponds with the $keys array.
 * @param null|array    $cas_tokens The variable to store the CAS tokens for the found items.
 * @param int           $flags      The flags for the get operation.
 * @return bool|array               Returns the array of found items or FALSE on failure.
 */
function wp_cache_get_multi( $keys, $groups = '', &$cas_tokens = NULL, $flags = NULL ) {
	if ( func_num_args() > 2 ) {
		return wp_object_cache()->getMulti( $keys, $groups, '', $cas_tokens, $flags );
	} else {
		return wp_object_cache()->getMulti( $keys, $groups );
	}
}

/**
 * Gets multiple values from memcached in one request by specified server key.
 *
 * See the buildKeys method definition to understand the $keys/$groups parameters.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.getmultibykey.php
 *
 * @param string        $server_key The key identifying the server to store the value on.
 * @param array         $keys       Array of keys to retrieve.
 * @param string|array  $groups     If string, used for all keys. If arrays, corresponds with the $keys array.
 * @param null|array    $cas_tokens The variable to store the CAS tokens for the found items.
 * @param int           $flags      The flags for the get operation.
 * @return bool|array               Returns the array of found items or FALSE on failure.
 */
function wp_cache_get_multi_by_key( $server_key, $keys, $groups = '', &$cas_tokens = NULL, $flags = NULL ) {
	if ( func_num_args() > 3 ) {
		return wp_object_cache()->getMultiByKey( $server_key, $keys, $groups, $cas_tokens, $flags );
	} else {
		return wp_object_cache()->getMultiByKey( $server_key, $keys, $groups );
	}
}

/**
 * Retrieve a Memcached option value.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.getoption.php
 *
 * @param int   $option One of the Memcached::OPT_* constants.
 * @return mixed        Returns the value of the requested option, or FALSE on error.
 */
function wp_cache_get_option( $option ) {
	return wp_object_cache()->getOption( $option );
}

/**
 * Return the result code of the last option.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.getresultcode.php
 *
 * @return int  Result code of the last Memcached operation.
 */
function wp_cache_get_result_code() {
	return wp_object_cache()->getResultCode();
}

/**
 * Return the message describing the result of the last operation.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.getresultmessage.php
 *
 * @return string   Message describing the result of the last Memcached operation.
 */
function wp_cache_get_result_message() {
	return wp_object_cache()->getResultMessage();
}

/**
 * Get server information by key.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.getserverbykey.php
 *
 * @param string    $server_key The key identifying the server to store the value on.
 * @return array                Array with host, post, and weight on success, FALSE on failure.
 */
function wp_cache_get_server_by_key( $server_key ) {
	return wp_object_cache()->getServerByKey( $server_key );
}

/**
 * Get the list of servers in the pool.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.getserverlist.php
 *
 * @return array    The list of all servers in the server pool.
 */
function wp_cache_get_server_list() {
	return wp_object_cache()->getServerList();
}

/**
 * Get server pool statistics.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.getstats.php
 *
 * @return array    Array of server statistics, one entry per server.
 */
function wp_cache_get_stats() {
	return wp_object_cache()->getStats();
}

/**
 * Get server pool memcached version information.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.getversion.php
 *
 * @return array    Array of server versions, one entry per server.
 */
function wp_cache_get_version() {
	return wp_object_cache()->getVersion();
}

/**
 * Increment a numeric item's value.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.increment.php
 *
 * @param string    $key    The key under which to store the value.
 * @param int       $offset The amount by which to increment the item's value.
 * @param string    $group  The group value appended to the $key.
 * @return int|bool         Returns item's new value on success or FALSE on failure.
 */
function wp_cache_increment( $key, $offset = 1, $group = '' ) {
	return wp_object_cache()->increment( $key, $offset, $group );
}

/**
 * Increment a numeric item's value.
 *
 * This is the same as wp_cache_increment, but kept for back compatibility. The original
 * WordPress caching backends use wp_cache_incr. I want both to work.
 *
 * @since 2.0.0
 **
 * @link http://www.php.net/manual/en/memcached.increment.php
 *
 * @param string    $key    The key under which to store the value.
 * @param int       $offset The amount by which to increment the item's value.
 * @param string    $group  The group value appended to the $key.
 * @return int|bool         Returns item's new value on success or FALSE on failure.
 */
function wp_cache_incr( $key, $offset = 1, $group = '' ) {
	return wp_cache_increment( $key, $offset, $group );
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
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.prepend.php
 *
 * @param string    $key    The key under which to store the value.
 * @param string    $value  Must be string as prepending mixed values is not well-defined.
 * @param string    $group  The group value prepended to the $key.
 * @return bool             Returns TRUE on success or FALSE on failure.
 */
function wp_cache_prepend( $key, $value, $group = '' ) {
	return wp_object_cache()->prepend( $key, $value, $group );
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
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.prependbykey.php
 *
 * @param string    $server_key     The key identifying the server to store the value on.
 * @param string    $key            The key under which to store the value.
 * @param string    $value          Must be string as prepending mixed values is not well-defined.
 * @param string    $group          The group value prepended to the $key.
 * @return bool                     Returns TRUE on success or FALSE on failure.
 */
function wp_cache_prepend_by_key( $server_key, $key, $value, $group = '' ) {
	return wp_object_cache()->prependByKey( $server_key, $key, $value, $group );
}

/**
 * Replaces a value in cache.
 *
 * This method is similar to "add"; however, is does not successfully set a value if
 * the object's key is not already set in cache.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.replace.php
 *
 * @param string    $key        The key under which to store the value.
 * @param mixed     $value      The value to store.
 * @param string    $group      The group value appended to the $key.
 * @param int       $expiration The expiration time, defaults to 0.
 * @return bool                 Returns TRUE on success or FALSE on failure.
 */
function wp_cache_replace( $key, $value, $group = '', $expiration = 0 ) {
	return wp_object_cache()->replace( $key, $value, $group, $expiration );
}

/**
 * Replaces a value in cache on a specific server.
 *
 * This method is similar to "addByKey"; however, is does not successfully set a value if
 * the object's key is not already set in cache.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.addbykey.php
 *
 * @param string    $server_key     The key identifying the server to store the value on.
 * @param string    $key            The key under which to store the value.
 * @param mixed     $value          The value to store.
 * @param string    $group          The group value appended to the $key.
 * @param int       $expiration     The expiration time, defaults to 0.
 * @return bool                     Returns TRUE on success or FALSE on failure.
 */
function wp_cache_replace_by_key( $server_key, $key, $value, $group = '', $expiration = 0 ) {
	return wp_object_cache()->replaceByKey( $server_key, $key, $value, $group, $expiration );
}

/**
 * Sets a value in cache.
 *
 * The value is set whether or not this key already exists in memcached.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.set.php
 *
 * @param string    $key        The key under which to store the value.
 * @param mixed     $value      The value to store.
 * @param string    $group      The group value appended to the $key.
 * @param int       $expiration The expiration time, defaults to 0.
 * @return bool                 Returns TRUE on success or FALSE on failure.
 */
function wp_cache_set( $key, $value, $group = '', $expiration = 0 ) {
	return wp_object_cache()->set( $key, $value, $group, $expiration );
}

/**
 * Sets a value in cache.
 *
 * The value is set whether or not this key already exists in memcached.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.set.php
 *
 * @param string    $server_key     The key identifying the server to store the value on.
 * @param string    $key            The key under which to store the value.
 * @param mixed     $value          The value to store.
 * @param string    $group          The group value appended to the $key.
 * @param int       $expiration     The expiration time, defaults to 0.
 * @return bool                     Returns TRUE on success or FALSE on failure.
 */
function wp_cache_set_by_key( $server_key, $key, $value, $group = '', $expiration = 0 ) {
	return wp_object_cache()->setByKey( $server_key, $key, $value, $group, $expiration );
}

/**
 * Set multiple values to cache at once.
 *
 * By sending an array of $items to this function, all values are saved at once to
 * memcached, reducing the need for multiple requests to memcached. The $items array
 * keys and values are what are stored to memcached. The keys in the $items array
 * are merged with the $groups array/string value via buildKeys to determine the
 * final key for the object.
 *
 * @since 2.0.0
 *
 * @param array         $items      An array of key/value pairs to store on the server.
 * @param string|array  $groups     Group(s) to merge with key(s) in $items.
 * @param int           $expiration The expiration time, defaults to 0.
 * @return bool                     Returns TRUE on success or FALSE on failure.
 */
function wp_cache_set_multi( $items, $groups = '', $expiration = 0 ) {
	return wp_object_cache()->setMulti( $items, $groups, $expiration );
}

/**
 * Set multiple values to cache at once on specified server.
 *
 * By sending an array of $items to this function, all values are saved at once to
 * memcached, reducing the need for multiple requests to memcached. The $items array
 * keys and values are what are stored to memcached. The keys in the $items array
 * are merged with the $groups array/string value via buildKeys to determine the
 * final key for the object.
 *
 * @since 2.0.0
 *
 * @param string        $server_key The key identifying the server to store the value on.
 * @param array         $items      An array of key/value pairs to store on the server.
 * @param string|array  $groups     Group(s) to merge with key(s) in $items.
 * @param int           $expiration The expiration time, defaults to 0.
 * @return bool                     Returns TRUE on success or FALSE on failure.
 */
function wp_cache_set_multi_by_key( $server_key, $items, $groups = 'default', $expiration = 0 ) {
	return wp_object_cache()->setMultiByKey( $server_key, $items, $groups, $expiration );
}

/**
 * Set a Memcached option.
 *
 * @since 2.0.0
 *
 * @link http://www.php.net/manual/en/memcached.setoption.php
 *
 * @param int       $option Option name.
 * @param mixed     $value  Option value.
 * @return bool             Returns TRUE on success or FALSE on failure.
 */
function wp_cache_set_option( $option, $value ) {
	return wp_object_cache()->setOption( $option, $value );
}

/**
 * Switch blog prefix, which changes the cache that is accessed.
 *
 * @since 2.0.0
 *
 * @param  int     $blog_id    Blog to switch to.
 * @return void
 */
function wp_cache_switch_to_blog( $blog_id ) {
	return wp_object_cache()->switch_to_blog( $blog_id );
}

/**
 * Adds a group or set of groups to the list of non-persistent groups.
 *
 * @since 2.0.0
 *
 * @param   string|array    $groups     A group or an array of groups to add.
 * @return  void
 */
function wp_cache_add_global_groups( $groups ) {
	wp_object_cache()->add_global_groups( $groups );
}

/**
 * Adds a group or set of groups to the list of non-persistent groups.
 *
 * @since 2.0.0
 *
 * @param   string|array    $groups     A group or an array of groups to add.
 * @return  void
 */
function wp_cache_add_non_persistent_groups( $groups ) {
	wp_object_cache()->add_non_persistent_groups( $groups );
}

/**
 * Sets up Object Cache Global and assigns it.
 *
 * @since 2.0.0
 *
 * @global  WP_Spider_Cache_Object  $wp_object_cache   WordPress Object Cache
 * @return  void
 */
function wp_cache_init() {
	wp_object_cache_init();
}

/**
 * Returns the Object Cache Global.
 *
 * @since 2.0.0
 *
 * @global  WP_Spider_Cache_Object  $wp_object_cache   WordPress Object Cache
 * @return  WP_Spider_Cache_Object
 */
function wp_object_cache() {

	if ( ! isset( $GLOBALS['wp_object_cache'] ) ) {
		wp_object_cache_init();
	}

	return $GLOBALS['wp_object_cache'];
}

/**
 * Sets up Output Cache Global and assigns it.
 *
 * @since 2.1.0
 *
 * @global  WP_Spider_Cache_Output  $wp_object_cache   WordPress Object Cache
 * @return  void
 */
function wp_object_cache_init() {
	require_once 'class-object-cache.php';
	$GLOBALS['wp_object_cache'] = new WP_Spider_Cache_Object();
}

/**
 * Sets up Output Cache Global and assigns it.
 *
 * @since 2.1.0
 *
 * @global  WP_Spider_Cache_Output  $wp_object_cache   WordPress Object Cache
 * @return  void
 */
function wp_output_cache_init() {
	require_once 'class-output-cache.php';
	$GLOBALS['wp_output_cache'] = new WP_Spider_Cache_Output();
}

/**
 * Returns the Output Cache Global.
 *
 * @since 2.1.0
 *
 * @global  WP_Spider_Cache_Output  $wp_output_cache   WordPress Output Cache
 * @return  WP_Spider_Cache_Output
 */
function wp_output_cache() {

	if ( ! isset( $GLOBALS['wp_output_cache'] ) ) {
		wp_output_cache_init();
	}

	return $GLOBALS['wp_output_cache'];
}

/**
 * Should we skip the output cache?
 *
 * @since 2.1.0
 *
 * @return boolean
 */
function wp_skip_output_cache() {

	// Bail if caching not turned on
	if ( ! defined( 'WP_CACHE' ) || ( true !== WP_CACHE ) ) {
		return true;
	}

	// Bail if no content directory
	if ( ! defined( 'WP_CONTENT_DIR' ) ) {
		return true;
	}

	// Never cache interactive scripts or API endpoints.
	if ( in_array( basename( $_SERVER['SCRIPT_FILENAME'] ), array(
		'wp-app.php',
		'wp-cron.php',
		'ms-files.php',
		'xmlrpc.php',
	) ) ) {
		return true;
	}

	// Never cache JavaScript generators
	if ( strstr( $_SERVER['SCRIPT_FILENAME'], 'wp-includes/js' ) ) {
		return true;
	}

	// Never cache when POST data is present.
	if ( ! empty( $GLOBALS['HTTP_RAW_POST_DATA'] ) || ! empty( $_POST ) ) {
		return true;
	}

	return false;
}

/**
 * Cancel Output-Cache
 *
 * @since 2.1.0
 */
function wp_output_cache_cancel() {
	wp_output_cache()->cancel = true;
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
 *
 * @since 2.1.0
 */
function wp_output_cache_vary( $function = '' ) {

	// Bail if no function
	if ( empty( $function ) ) {
		die( 'Variant determiner cannot be empty.' );
	}

	// Bail if illegal name
	if ( preg_match( '/include|require|echo|print|dump|export|open|sock|unlink|`|eval/i', $function ) ) {
		die( 'Illegal word in variant determiner.' );
	}

	// Bail if missing variables
	if ( ! preg_match( '/\$_/', $function ) ) {
		die( 'Variant determiner should refer to at least one $_ variable.' );
	}

	wp_output_cache()->add_variant( $function );
}
