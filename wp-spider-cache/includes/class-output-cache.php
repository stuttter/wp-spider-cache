<?php

/**
 * Spider Cache Output Cache
 *
 * @package Plugins/Cache/Output
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * The main Spider-Cache output cache class
 */
class WP_Spider_Cache_Output {

	/**
	 * The timestamp when the object was created
	 *
	 * @var int
	 */
	public $started = 0;

	/**
	 * Maximum age of a cached page in seconds
	 *
	 * @var int
	 */
	public $max_age = 600;

	/**
	 * False disables sending buffers to remote data centers
	 *
	 * @var bool
	 */
	public $remote = true;

	/**
	 * Only spider_cache a page after it is accessed this many times...
	 * (two or more)
	 *
	 * @var int
	 */
	public $times = 2;

	/**
	 * ...in this many seconds (zero to ignore this and use spider_cache immediately)
	 *
	 * @var int
	 */
	public $seconds = 120;

	/**
	 * Name of cache group. You can simulate a cache flush by changing this.
	 *
	 * @var string
	 */
	public $group = 'spider_cache';

	/**
	 * If you conditionally serve different content, put the variable values here.
	 *
	 * @var array
	 */
	public $unique = array();

	/**
	 * Array of functions for create_function. The return value is added to
	 * $unique above.
	 *
	 * @var array
	 */
	public $vary = array();

	/**
	 * Add headers here as name=>value or name=>array(values).
	 * These will be sent with every response from the cache.
	 *
	 * @var array
	 */
	public $headers = array();

	/**
	 * Set true to enable redirect caching.
	 *
	 * @var bool
	 */
	public $cache_redirects = false;

	/**
	 * This is set to the response code during a redirect.
	 *
	 * @var bool
	 */
	public $redirect_status = false;

	/**
	 * This is set to the redirect location.
	 *
	 * @var bool
	 */
	public $redirect_location = false;

	/**
	 * These headers will never be cached. Apply strtolower.
	 *
	 * @var array
	 */
	public $uncached_headers = array( 'transfer-encoding' );

	/**
	 * Set false to hide the spider_cache info <!-- comment -->
	 *
	 * @var bool
	 */
	public $debug = false;

	/**
	 * Set false to disable Last-Modified and Cache-Control headers
	 *
	 * @var bool
	 */
	public $cache_control = true;

	/**
	 * Change this to cancel the output buffer. Use wp_output_cache_cancel();
	 *
	 * @var bool
	 */
	public $cancel = false;

	/**
	 * Names of cookies - if they exist and the cache would normally be
	 * bypassed, don't bypass it.
	 *
	 * @var array
	 */
	public $noskip_cookies = array();

	/**
	 * Names of cookies - if they exist bypass caching.
	 *
	 * @var array
	 */
	public $skip_cookies = array();

	/**
	 * Used internally
	 *
	 * @var bool
	 */
	private $genlock = false;

	/**
	 * Used internally
	 *
	 * @var bool
	 */
	private $do = false;

	/**
	 * The request count
	 *
	 * @var int
	 */
	private $requests = 0;

	/**
	 * The permalink
	 *
	 * @var string
	 */
	private $permalink = '';

	/**
	 * The URL key
	 *
	 * @var string
	 */
	private $url_key = '';

	/**
	 * The URL version
	 *
	 * @var string
	 */
	private $url_version = '';

	/**
	 * The status header
	 *
	 * @var string
	 */
	private $status_header = 'HTTP/1.1 200 OK';

	/**
	 * The status code
	 *
	 * @var int
	 */
	private $status_code = 200;

	/**
	 * The cache data
	 *
	 * @var array
	 */
	private $cache = array();

	/**
	 * The key data
	 *
	 * @var array
	 */
	private $keys = array();

	/**
	 * The key
	 *
	 * @var string
	 */
	private $key = '';

	/**
	 * The request cache key
	 *
	 * @var string
	 */
	private $req_key = '';

	/**
	 * Setup the query attribute if a query string exists
	 *
	 * @since 6.0.0
	 */
	private $query = array();

	/**
	 * Main output cache constructor
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		// Maybe pull values from pre-existing output cache global
		if ( ! empty( $GLOBALS['wp_output_cache'] ) && is_array( $GLOBALS['wp_output_cache'] ) ) {
			foreach ( $GLOBALS['wp_output_cache'] as $key => $value ) {
				$this->{$key} = $value;
			}
		}

		// Bail if cookies are present
		if ( $this->has_cookies() ) {
			return;
		}

		// Support cache debug constant
		if ( defined( 'WP_CACHE_DEBUG' ) ) {
			$this->debug = (bool) WP_CACHE_DEBUG;
		}

		// Set the started time
		$this->started = time();

		// Always set cache groups
		$this->configure_groups();

		// Start caching
		$this->start();
	}

	/**
	 * Set the status header & code
	 *
	 * @since 2.0.0
	 *
	 * @param  string  $status_header
	 * @param  int     $status_code
	 *
	 * @return string
	 */
	public function status_header( $status_header, $status_code = 200 ) {
		$this->status_header = $status_header;
		$this->status_code   = (int) $status_code;

		return $status_header;
	}

	/**
	 * Set the redirect status, and whether it should be cached
	 *
	 * @since 2.0.0
	 *
	 * @param string $status
	 * @param string $location
	 *
	 * @return string
	 */
	public function redirect_status( $status, $location ) {

		// Cache this redirect
		if ( true === $this->cache_redirects ) {
			$this->redirect_status   = $status;
			$this->redirect_location = $location;
		}

		return $status;
	}

	/**
	 * Merge the headers and send them off
	 *
	 * @since 2.0.0
	 *
	 * @param array $headers1
	 * @param array $headers2
	 */
	protected function do_headers( $headers1, $headers2 = array() ) {

		// Merge the arrays of headers into one
		$headers = array();
		$keys    = array_unique( array_merge( array_keys( $headers1 ), array_keys( $headers2 ) ) );

		foreach ( $keys as $k ) {
			$headers[ $k ] = array();

			if ( isset( $headers1[ $k ] ) && isset( $headers2[ $k ] ) ) {
				$headers[ $k ] = array_merge( (array) $headers2[ $k ], (array) $headers1[ $k ] );
			} elseif ( isset( $headers2[ $k ] ) ) {
				$headers[ $k ] = (array) $headers2[ $k ];
			} else {
				$headers[ $k ] = (array) $headers1[ $k ];
			}

			$headers[ $k ] = array_unique( $headers[ $k ] );
		}

		// These headers take precedence over any previously sent with the same names
		foreach ( $headers as $k => $values ) {
			$clobber = true;
			foreach ( $values as $v ) {
				@header( "{$k}: {$v}", $clobber );
				$clobber = false;
			}
		}
	}

	/**
	 * Configure the cache client
	 *
	 * @since 2.0.0
	 */
	protected function configure_groups() {

		// Local cache instance only
		if ( ( false === $this->remote ) && function_exists( 'wp_cache_add_no_remote_groups' ) ) {
			wp_cache_add_no_remote_groups( array( $this->group ) );
		}
	}

	/**
	 * Start the output buffer
	 *
	 * @since 2.0.0
	 *
	 * @param string $output
	 * @return string
	 */
	protected function ob( $output = '' ) {

		// Bail if cancelling
		if ( true === $this->cancel ) {
			return $output;
		}

		// PHP5 and objects disappearing before output buffers?
		wp_object_cache_init();

		// $wp_object_cache was clobbered in wp-settings.php so repeat this
		$this->configure_groups();

		// Unlock regeneration
		wp_cache_delete( "{$this->url_key}_genlock", $this->group );

		// Do not cache blank pages unless they are HTTP redirects
		$output = trim( $output );
		if ( empty( $output ) && ( empty( $this->redirect_status ) || empty( $this->redirect_location ) ) ) {
			return $output;
		}

		// Do not cache 5xx responses
		if ( isset( $this->status_code ) && intval( $this->status_code / 100 ) === 5 ) {
			return $output;
		}

		// Variants and keys
		$this->do_variants( $this->vary );
		$this->generate_keys();

		// Construct and save the spider_cache
		$this->cache = array(
			'output'            => $output,
			'time'              => $this->started,
			'headers'           => array(),
			'timer'             => $this->timer_stop( false, 3 ),
			'status_header'     => $this->status_header,
			'redirect_status'   => $this->redirect_status,
			'redirect_location' => $this->redirect_location,
			'version'           => $this->url_version
		);

		// PHP5 and higher (
		foreach ( headers_list() as $header ) {
			list( $k, $v ) = array_map( 'trim', explode( ':', $header, 2 ) );
			$this->cache['headers'][ $k ] = array( $v );
		}

		// Unset uncached headers
		if ( ! empty( $this->cache['headers'] ) && ! empty( $this->uncached_headers ) ) {
			foreach ( $this->uncached_headers as $header ) {
				unset( $this->cache['headers'][ $header ] );
			}
		}

		// Set cached headers
		foreach ( $this->cache['headers'] as $header => $values ) {

			// Bail if cookies were set
			if ( strtolower( $header ) === 'set-cookie' ) {
				return $output;
			}

			foreach ( (array) $values as $value ) {
				if ( preg_match( '/^Cache-Control:.*max-?age=(\d+)/i', "{$header}: {$value}", $matches ) ) {
					$this->max_age = intval( $matches[1] );
				}
			}
		}

		// Set max-age & expiration
		$this->cache['max_age'] = $this->max_age;
		$this->cache['expires'] = $this->max_age + $this->seconds + 30;

		// Set cache
		wp_cache_set( $this->key, $this->cache, $this->group, $this->cache['expires'] );

		// Cache control
		if ( true === $this->cache_control ) {

			// Don't clobber Last-Modified header if already set, e.g. by WP::send_headers()
			if ( ! isset( $this->cache['headers']['Last-Modified'] ) ) {
				@header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $this->cache['time'] ) . ' GMT', true );
			}

			if ( ! isset( $this->cache['headers']['Cache-Control'] ) ) {
				@header( "Cache-Control: max-age={$this->max_age}, must-revalidate", false );
			}
		}

		$this->do_headers( $this->headers );

		// Add some debug info just before <head
		if ( true === $this->debug ) {
			$this->add_debug_just_cached();
		}

		// Pass output to next ob handler
		return $this->cache['output'];
	}

	/**
	 * Add a variant to the cache key
	 *
	 * @since 2.0.0
	 *
	 * @param string $function
	 */
	public function add_variant( $function = '' ) {
		$key = md5( $function );
		$this->vary[ $key ] = $function;
	}

	/**
	 * Set the cache with it's variant keys
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $dimensions False by default, array of arguments otherwise
	 */
	protected function do_variants( $dimensions = false ) {

		// This function is called without arguments early in the page load,
		// then with arguments during the OB handler.
		if ( false === $dimensions ) {
			$dimensions = wp_cache_get( "{$this->url_key}_vary", $this->group );
		} elseif ( ! empty( $dimensions ) ) {
			wp_cache_set( "{$this->url_key}_vary", $dimensions, $this->group, $this->max_age + 10 );
		}

		// Bail if no dimensions
		if ( empty( $dimensions ) || ! is_array( $dimensions ) ) {
			return;
		}

		// Sort keys
		ksort( $dimensions );

		// Evaluate
		foreach ( $dimensions as $key => $function ) {

			// Callable (Required in PHP7.2)
			if ( is_callable( $function ) ) {
				$this->keys[ $key ] = $function();

			// Raw code, so try to evaluate it
			} else {
				$created_function   = create_function( '', $function );
				$this->keys[ $key ] = $created_function();
			}
		}
	}

	/**
	 * Generate cache keys for the request
	 *
	 * @since 2.0.0
	 */
	protected function generate_keys() {
		$this->key     = md5( serialize( $this->keys ) );
		$this->req_key = "{$this->key}_req";
	}

	/**
	 * Add some debug info
	 *
	 * @since 2.0.0
	 */
	protected function add_debug_just_cached() {
		$generation = $this->cache['timer'];
		$bytes      = strlen( serialize( $this->cache ) );
		$html       = <<<HTML
<!--
	generated in {$generation} seconds
	{$bytes} bytes Spider-Cached for {$this->max_age} seconds
-->

HTML;
		$this->add_debug_html_to_output( $html );
	}

	/**
	 * Add verbose debug info
	 *
	 * @since 2.0.0
	 */
	protected function add_debug_from_cache() {
		$time        = $this->started;
		$seconds_ago = $time - $this->cache['time'];
		$generation  = $this->cache['timer'];
		$serving     = $this->timer_stop( false, 3 );
		$expires     = $this->cache['max_age'] - $time + $this->cache['time'];
		$html        = <<<HTML
<!--
	generated {$seconds_ago} seconds ago
	generated in {$generation} seconds
	served from Spider-Cache in {$serving} seconds
	expires in {$expires} seconds
-->

HTML;
		$this->add_debug_html_to_output( $html );
	}

	/**
	 * Determine where to put what debug output
	 *
	 * @since 2.0.0
	 *
	 * @param string $debug_html
	 * @return void
	 */
	protected function add_debug_html_to_output( $debug_html = '' ) {

		// Casing on the Content-Type header is inconsistent
		foreach ( array( 'Content-Type', 'Content-type' ) as $key ) {
			if ( isset( $this->cache['headers'][ $key ][0] ) && 0 !== strpos( $this->cache['headers'][ $key ][0], 'text/html' ) ) {
				return;
			}
		}

		// Bail if output does not include a head tag
		$head_position = strpos( $this->cache['output'], '<head' );
		if ( false === $head_position ) {
			return;
		}

		// Put debug HTML ahead of the <head> tag
		$this->cache['output'] = substr_replace( $this->cache['output'], $debug_html, $head_position, 0 );
	}

	/**
	 * Start page caching and hook into requests to complete it later
	 *
	 * @since 2.1.0
	 *
	 * @return void
	 */
	protected function start() {

		// Disabled
		if ( $this->max_age < 1 ) {
			return;
		}

		// Necessary to prevent clients using cached version after login cookies
		// set. If this is a problem, comment it out and remove all
		// Last-Modified headers.
		@header( 'Vary: Cookie', false );

		// Setup attributes
		$this->setup_query();
		$this->setup_protocol();
		$this->setup_keys();
		$this->setup_url();

		// Setup keys and variants
		$this->do_variants();
		$this->generate_keys();

		// Maybe output from cache
		$this->maybe_update_cache();
		$this->maybe_output_cache();

		// Didn't meet the minimum condition?
		if ( ( false === $this->do ) && ( false === $this->genlock ) ) {
			return;
		}

		// Headers and such
		add_filter( 'status_header',      array( $this, 'status_header'   ), 10, 2 );
		add_filter( 'wp_redirect_status', array( $this, 'redirect_status' ), 10, 2 );

		// Start the spidey-sense listening
		ob_start( array( $this, 'ob' ) );
	}

	/**
	 * Setup the query attribute if a query string exists
	 *
	 * @since 6.0.0
	 */
	private function setup_query() {

		// Things that define a unique page.
		if ( isset( $_SERVER['QUERY_STRING'] ) ) {
			parse_str( $_SERVER['QUERY_STRING'], $this->query );
		}
	}

	/**
	 * Setup the unique attribute based on the server protocol
	 *
	 * @since 6.0.0
	 */
	private function setup_protocol() {

		// Build different versions for HTTP/1.1 and HTTP/2.0
		$protocol = wp_get_server_protocol();
		if ( 'HTTP/1.0' !== $protocol ) {
			$this->unique['server_protocol'] = $protocol;
		}
	}

	/**
	 * Setup the keys array attribute
	 *
	 * @since 6.0.0
	 */
	private function setup_keys() {

		// Get the path
		$path = ( $_pos = strpos( $_SERVER['REQUEST_URI'], '?' ) )
			? substr( $_SERVER['REQUEST_URI'], 0, $_pos )
			: $_SERVER['REQUEST_URI'];

		// Setup keys
		$this->keys = array(
			'host'   => $_SERVER['HTTP_HOST'],
			'method' => $_SERVER['REQUEST_METHOD'],
			'path'   => $path,
			'query'  => $this->query,
			'extra'  => $this->unique,
			'ssl'    => is_ssl()
		);
	}

	/**
	 * Setup the URL/permalink attributes.
	 *
	 * Must be called after setup_keys()
	 *
	 * @since 6.0.0
	 */
	private function setup_url() {

		// Get HTTP scheme
		$scheme = ( true === $this->keys['ssl'] )
			? 'https://'
			: 'http://';

		// Maybe get post query string
		$p = isset( $this->keys['query']['p'] )
			? '?p=' . $this->keys['query']['p']
			: '';

		// Recreate the permalink from the URL
		$this->permalink   = $scheme . $this->keys['host'] . $this->keys['path'] . $p;
		$this->url_key     = md5( $this->permalink );
		$this->url_version = (int) wp_cache_get( "{$this->url_key}_version", $this->group );
	}

	/**
	 * Populate the cache variable, and maybe add/increment related caches.
	 *
	 * @since 6.0.0
	 */
	private function maybe_update_cache() {

		// Get the spider_cache
		$this->cache = wp_cache_get( $this->key, $this->group );

		// Are we only caching frequently-requested pages?
		if ( ( $this->seconds < 1 ) || ( $this->times < 2 ) ) {
			$this->do = true;

		// No spider_cache item found, or ready to sample traffic again at
		// the end of the spider_cache life?
		} elseif ( ! is_array( $this->cache ) || ( $this->started >= ( $this->cache['time'] + $this->max_age - $this->seconds ) ) ) {
			wp_cache_add( $this->req_key, 0, $this->group );

			$this->requests = wp_cache_incr( $this->req_key, 1, $this->group );
			$this->do       = (bool) ( $this->requests >= $this->times );
		}

		// If the document has been updated and we are the first to notice, regenerate it.
		if ( ( true === $this->do ) && isset( $this->cache['version'] ) && ( $this->cache['version'] < $this->url_version ) ) {
			$this->genlock = wp_cache_add( "{$this->url_key}_genlock", 1, $this->group, 10 );
		}
	}

	/**
	 * Maybe output the contents from cache, including performing a redirection
	 * if necessary.
	 *
	 * @since 6.0.0
	 *
	 * @return void
	 */
	private function maybe_output_cache() {

		// Bail if no page, or is locked or expired
		if ( ! isset( $this->cache['time'] ) || ( true === $this->genlock ) || ( $this->started > ( $this->cache['time'] + $this->cache['max_age'] ) ) ) {
			return;
		}

		// Maybe perform a redirection
		$this->maybe_do_redirect();

		// Use the spider_cache save time for Last-Modified so we can issue
		// "304 Not Modified" but don't clobber a cached Last-Modified header.
		if ( ( true === $this->cache_control ) && ! isset( $this->cache['headers']['Last-Modified'][0] ) ) {
			@header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $this->cache['time'] ) . ' GMT', true );
			@header( 'Cache-Control: max-age=' . ( $this->cache['max_age'] - $this->started + $this->cache['time'] ) . ', must-revalidate', true );
		}

		// Add some debug info just before </head>
		if ( true === $this->debug ) {
			$this->add_debug_from_cache();
		}

		$this->do_headers( $this->headers, $this->cache['headers'] );

		// Bail if not modified
		if ( $this->not_modified() ) {
			@header( "HTTP/1.1 304 Not Modified", true, 304 );
			die;
		}

		// Set header if cached
		if ( ! empty( $this->cache['status_header'] ) ) {
			@header( $this->cache['status_header'], true );
		}

		// Have you ever heard a death rattle before?
		die( $this->cache['output'] );
	}

	/**
	 * Maybe perform a redirection
	 *
	 * @since 6.0.0
	 */
	private function maybe_do_redirect() {

		// Issue redirect if cached and enabled
		if ( $this->cache['redirect_status'] && $this->cache['redirect_location'] && $this->cache_redirects ) {

			// Do headers
			$this->do_headers( $this->headers );

			// From vars.php
			$is_IIS = ( strpos( $_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS' ) !== false || strpos( $_SERVER['SERVER_SOFTWARE'], 'ExpressionDevServer' ) !== false );

			// IIS
			if ( ! empty( $is_IIS ) ) {
				@header( "Refresh: 0;url={$this->cache['redirect_location']}" );
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

					// Get the protocol
					$protocol = wp_get_server_protocol();

					// Found/Redirect header
					isset( $texts[ $this->cache['redirect_status'] ] )
						? @header( "{$protocol} {$this->cache['redirect_status']} {$texts[ $this->cache['redirect_status'] ]}" )
						: @header( "{$protocol} 302 Found" );
				}

				@header( "Location: {$this->cache['redirect_location']}" );
			}

			// Exit so redirect takes effect
			exit;
		}
	}

	/**
	 * Has the cached page changed?
	 *
	 * @since 6.0.0
	 *
	 * @return boolean
	 */
	private function not_modified() {

		// Default value
		$three_oh_four = false;

		// Respect ETags served with feeds.
		if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && isset( $this->cache['headers']['ETag'][0] ) && ( $_SERVER['HTTP_IF_NONE_MATCH'] == $this->cache['headers']['ETag'][0] ) ) {
			$three_oh_four = true;

		// Respect If-Modified-Since.
		} elseif ( ( true === $this->cache_control ) && isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) {

			// Get times
			$client_time = strtotime( $_SERVER['HTTP_IF_MODIFIED_SINCE'] );
			$cache_time  = isset( $this->cache['headers']['Last-Modified'][0] )
				? strtotime( $this->cache['headers']['Last-Modified'][0] )
				: $this->cache['time'];

			// Maybe 304
			if ( $client_time >= $cache_time ) {
				$three_oh_four = true;
			}
		}

		// Return 304 status
		return (bool) $three_oh_four;
	}

	/**
	 * Look for predefined constants, and add them to the skip_cookies array
	 *
	 * @since 2.3.0
	 */
	private function set_skip_cookies() {

		// Auth cookie
		if ( defined( 'AUTH_COOKIE' ) && AUTH_COOKIE && ! in_array( AUTH_COOKIE, $this->skip_cookies, true ) ) {
			$this->skip_cookies[] = AUTH_COOKIE;
		}

		// User cookie
		if ( defined( 'USER_COOKIE' ) && USER_COOKIE && ! in_array( USER_COOKIE, $this->skip_cookies, true ) ) {
			$this->skip_cookies[] = USER_COOKIE;
		}

		// Logged-in cookie
		if ( defined( 'LOGGED_IN_COOKIE' ) && LOGGED_IN_COOKIE && ! in_array( LOGGED_IN_COOKIE, $this->skip_cookies, true ) ) {
			$this->skip_cookies[] = LOGGED_IN_COOKIE;
		}
	}

	/**
	 * Return whether or not user related cookies have been detected
	 *
	 * @since 2.3.0
	 *
	 * @return boolean
	 */
	private function has_cookies() {

		// Bail if cookies indicate a cache-exempt visitor
		if ( empty( $_COOKIE ) || ! is_array( $_COOKIE ) ) {
			return false;
		}

		// Set cookies used to skip
		$this->set_skip_cookies();

		// Get cookie keys
		$cookie_keys = array_keys( $_COOKIE );

		// Loop through keys
		foreach ( $cookie_keys as $cookie_key ) {

			// Skip cookie and keep caching
			if ( in_array( $cookie_key, $this->noskip_cookies, true ) ) {
				continue;
			}

			// Don't skip cookie, and don't cache
			if ( in_array( $cookie_key, $this->skip_cookies, true ) ) {
				return true;
			}

			// Comment cookie, so don't cache
			if ( substr( $cookie_key, 0, 14 ) === 'comment_author' ) {
				return true;
			}

			// Generic 'wordpress' cookies (that are not test cookies)
			if ( ( substr( $cookie_key, 0, 9 ) === 'wordpress' ) && ( $cookie_key !== 'wordpress_test_cookie' ) ) {
				return true;
			}
		}

		// No cookies
		return false;
	}

	/**
	 * Defined here because timer_stop() calls number_format_i18n()
	 *
	 * @since 2.0.0
	 */
	private function timer_stop( $display = true, $precision = 3 ) {
		global $timestart, $timeend;

		$mtime     = microtime();
		$mtime     = explode( ' ', $mtime );
		$timeend   = $mtime[1] + $mtime[0];
		$timetotal = $timeend - $timestart;
		$r         = number_format( $timetotal, $precision );

		if ( true === $display ) {
			echo $r;
		}

		return $r;
	}
}
