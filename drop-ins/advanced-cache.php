<?php

/**
 * 
 */

/** Checks ********************************************************************/

// Define globals
global $spider_cache, $wp_object_cache;

// Bail if no content directory
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	return;
}

// Bail if no persistent object cache
if ( file_exists( WP_CONTENT_DIR . '/object-cache.php' ) ) {
	require_once WP_CONTENT_DIR . '/object-cache.php';
	if ( ! function_exists( 'wp_cache_init' ) ) {
		return;
	}
} else {
	return;
}

// Bail if not a WP_Object_Cache drop-in
if ( empty( $wp_object_cache ) || ! is_a( $wp_object_cache, 'WP_Object_Cache' ) ) {
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
require_once WP_CONTENT_DIR . '/mu-plugins/wp-spider-cache/includes/class-spider-cache.php';
require_once WP_CONTENT_DIR . '/mu-plugins/wp-spider-cache/includes/functions.php';

// Pass in the global variable which may be an array of settings to
// override defaults.
$spider_cache = new Spider_Cache( $spider_cache );

// Never spider_cache when cookies indicate a cache-exempt visitor.
if ( is_array( $_COOKIE ) && ! empty( $_COOKIE ) ) {
	$cookie_keys = array_keys( $_COOKIE );
	foreach ( $cookie_keys as $spider_cache->cookie ) {
		if ( ! in_array( $spider_cache->cookie, $spider_cache->noskip_cookies ) && ( substr( $spider_cache->cookie, 0, 2 ) == 'wp' || substr( $spider_cache->cookie, 0, 9 ) == 'wordpress' || substr( $spider_cache->cookie, 0, 14 ) == 'comment_author' ) ) {
			return;
		}
	}
}

// Note: wp-settings.php calls wp_cache_init() which clobbers the object made here.
wp_cache_init();

// Disabled
if ( $spider_cache->max_age < 1 ) {
	return;
}

// Make sure we can increment. If not, turn off the traffic sensor.
if ( ! method_exists( $wp_object_cache, 'incr' ) ) {
	$spider_cache->times = 0;
}

// Necessary to prevent clients using cached version after login cookies set.
// If this is a problem, comment it out and remove all Last-Modified headers.
header( 'Vary: Cookie', false );

// Things that define a unique page.
if ( isset( $_SERVER['QUERY_STRING'] ) ) {
	parse_str( $_SERVER['QUERY_STRING'], $spider_cache->query );
}

// Build different versions for HTTP/1.1 and HTTP/2.0
if ( isset( $_SERVER['SERVER_PROTOCOL'] ) ) {
	$spider_cache->unique['server_protocol'] = $_SERVER['SERVER_PROTOCOL'];
}

$spider_cache->keys = array(
	'host'   => $_SERVER['HTTP_HOST'],
	'method' => $_SERVER['REQUEST_METHOD'],
	'path'   => ( $spider_cache->pos = strpos( $_SERVER['REQUEST_URI'], '?' ) ) ? substr( $_SERVER['REQUEST_URI'], 0, $spider_cache->pos ) : $_SERVER['REQUEST_URI'],
	'query'  => $spider_cache->query,
	'extra'  => $spider_cache->unique
);

if ( $spider_cache->is_ssl() ) {
	$spider_cache->keys['ssl'] = true;
}

// Recreate the permalink from the URL
$protocol                  = ( isset( $spider_cache->keys['ssl'] ) && true === $spider_cache->keys['ssl'] ) ? 'https://' : 'http://';
$spider_cache->permalink   = $protocol . $spider_cache->keys['host'] . $spider_cache->keys['path'] . ( isset( $spider_cache->keys['query']['p'] ) ? "?p=" . $spider_cache->keys['query']['p'] : '' );
$spider_cache->url_key     = md5( $spider_cache->permalink );
$spider_cache->url_version = (int) wp_cache_get( "{$spider_cache->url_key}_version", $spider_cache->group );

// Setup keys and variants
$spider_cache->do_variants();
$spider_cache->generate_keys();

// Get the spider_cache
$spider_cache->cache = wp_cache_get( $spider_cache->key, $spider_cache->group );

// Are we only caching frequently-requested pages?
if ( $spider_cache->seconds < 1 || $spider_cache->times < 2 ) {
	$spider_cache->do = true;
} else {

	// No spider_cache item found, or ready to sample traffic again at the end of the spider_cache life?
	if ( ! is_array( $spider_cache->cache ) || ( time() >= $spider_cache->cache['time'] + $spider_cache->max_age - $spider_cache->seconds ) ) {
		wp_cache_add( $spider_cache->req_key, 0, $spider_cache->group );
		$spider_cache->requests = wp_cache_incr( $spider_cache->req_key, 1, $spider_cache->group );

		if ( $spider_cache->requests >= $spider_cache->times ) {
			$spider_cache->do = true;
		} else {
			$spider_cache->do = false;
		}
	}
}

// If the document has been updated and we are the first to notice, regenerate it.
if ( $spider_cache->do !== false && isset( $spider_cache->cache['version'] ) && $spider_cache->cache['version'] < $spider_cache->url_version ) {
	$spider_cache->genlock = wp_cache_add( "{$spider_cache->url_key}_genlock", 1, $spider_cache->group, 10 );
}

// Temporary: remove after 2010-11-12. I added max_age to the cache. This upgrades older caches on the fly.
if ( ! isset( $spider_cache->cache['max_age'] ) ) {
	$spider_cache->cache['max_age'] = $spider_cache->max_age;
}

// Did we find a spider_cached page that hasn't expired?
if ( isset( $spider_cache->cache['time'] ) && empty( $spider_cache->genlock ) && ( time() < $spider_cache->cache['time'] + $spider_cache->cache['max_age'] ) ) {

	// Issue redirect if cached and enabled
	if ( $spider_cache->cache['redirect_status'] && $spider_cache->cache['redirect_location'] && $spider_cache->cache_redirects ) {
		$status   = $spider_cache->cache['redirect_status'];
		$location = $spider_cache->cache['redirect_location'];

		// From vars.php
		$is_IIS = ( strpos( $_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS' ) !== false || strpos( $_SERVER['SERVER_SOFTWARE'], 'ExpressionDevServer' ) !== false );

		$spider_cache->do_headers( $spider_cache->headers );
		if ( ! empty( $is_IIS ) ) {
			header( "Refresh: 0;url={$location}" );
		} else {
			if ( php_sapi_name() !== 'cgi-fcgi' ) {
				$texts = array(
					300 => 'Multiple Choices',
					301 => 'Moved Permanently',
					302 => 'Found',
					303 => 'See Other',
					304 => 'Not Modified',
					305 => 'Use Proxy',
					306 => 'Reserved',
					307 => 'Temporary Redirect',
				);

				$protocol = $_SERVER["SERVER_PROTOCOL"];
				if ( 'HTTP/1.1' !== $protocol && 'HTTP/1.0' !== $protocol ) {
					$protocol = 'HTTP/1.0';
				}

				if ( isset( $texts[ $status ] ) ) {
					header( "{$protocol} {$status} " . $texts[ $status ] );
				} else {
					header( "{$protocol} 302 Found");
				}
			}
			header( "Location: {$location}" );
		}
		exit;
	}

	// Respect ETags served with feeds.
	$three_oh_four = false;
	if ( isset( $SERVER['HTTP_IF_NONE_MATCH'] ) && isset( $spider_cache->cache['headers']['ETag'][0] ) && $_SERVER['HTTP_IF_NONE_MATCH'] == $spider_cache->cache['headers']['ETag'][0] ) {
		$three_oh_four = true;

	// Respect If-Modified-Since.
	} elseif ( $spider_cache->cache_control && isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) {

		$client_time = strtotime( $_SERVER['HTTP_IF_MODIFIED_SINCE'] );

		if ( isset( $spider_cache->cache['headers']['Last-Modified'][0] ) ) {
			$cache_time = strtotime( $spider_cache->cache['headers']['Last-Modified'][0] );
		} else {
			$cache_time = $spider_cache->cache['time'];
		}

		if ( $client_time >= $cache_time ) {
			$three_oh_four = true;
		}
	}

	// Use the spider_cache save time for Last-Modified so we can issue
	// "304 Not Modified" but don't clobber a cached Last-Modified header.
	if ( $spider_cache->cache_control && ! isset( $spider_cache->cache['headers']['Last-Modified'][0] ) ) {
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $spider_cache->cache['time'] ) . ' GMT', true );
		header( 'Cache-Control: max-age=' . ( $spider_cache->cache['max_age'] - time() + $spider_cache->cache['time'] ) . ', must-revalidate', true );
	}

	// Add some debug info just before </head>
	if ( true === $spider_cache->debug ) {
		$spider_cache->add_debug_from_cache();
	}

	$spider_cache->do_headers( $spider_cache->headers, $spider_cache->cache['headers'] );

	if ( true === $three_oh_four ) {
		header( "HTTP/1.1 304 Not Modified", true, 304 );
		die;
	}

	if ( ! empty( $spider_cache->cache['status_header'] ) ) {
		header( $spider_cache->cache['status_header'], true );
	}

	// Have you ever heard a death rattle before?
	die( $spider_cache->cache['output'] );
}

// Didn't meet the minimum condition?
if ( empty( $spider_cache->do ) && empty( $spider_cache->genlock ) ) {
	return;
}

// Headers and such
$wp_filter['status_header'][10]['spider_cache']      = array( 'function' => array( &$spider_cache, 'status_header'   ), 'accepted_args' => 2 );
$wp_filter['wp_redirect_status'][10]['spider_cache'] = array( 'function' => array( &$spider_cache, 'redirect_status' ), 'accepted_args' => 2 );

// Start the spidey-sense listening
ob_start( array( &$spider_cache, 'ob' ) );
