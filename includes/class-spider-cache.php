<?php

/**
 * Plugin name: Spider-Cache
 * Plugin URI:  https://store.flox.io/plugins/spider-cache/
 * Description: Fully rendered pages stored in & served from Memcache.
 * Version:     2.0.0
 */

// WordPress or bust
defined( 'ABSPATH' ) || exit();

/**
 * The main Spider-Cache class
 *
 * @todo Detail what this is and how it works
 */
class Spider_Cache {

	/**
	 * This is the base configuration. You can edit these variables or move them
	 * into your wp-config.php file.
	 *
	 * @var int
	 */
	public $max_age = 600;

	/**
	 * Zero disables sending buffers to remote datacenters
	 * (req/sec is never sent)
	 *
	 * @var int
	 */
	public $remote = 0;

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
	 * Name of memcached group. You can simulate a cache flush by changing this.
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
	 * Change this to cancel the output buffer. Use spider_cache_cancel();
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
	public $noskip_cookies = array( TEST_COOKIE );

	/**
	 * Used internally
	 *
	 * @var bool
	 */
	public $genlock = false;

	/**
	 * Used internally
	 *
	 * @var bool
	 */
	public $do = false;

	/**
	 * Main spider_cache constructor
	 *
	 * @param array $settings
	 */
	public function __construct( $settings ) {
		if ( is_array( $settings ) ) {
			foreach ( $settings as $k => $v ) {
				$this->$k = $v;
			}
		}

		// Always set cache groups
		$this->configure_groups();
	}

	public function is_ssl() {
		if ( isset( $_SERVER['HTTPS'] ) ) {

			if ( 'on' === strtolower( $_SERVER['HTTPS'] ) ) {
				return true;
			}

			if ( '1' === $_SERVER['HTTPS'] ) {
				return true;
			}

		} elseif ( isset( $_SERVER['SERVER_PORT'] ) && ( '443' === $_SERVER['SERVER_PORT'] ) ) {
			return true;
		}

		return false;
	}

	public function status_header( $status_header, $status_code ) {
		$this->status_header = $status_header;
		$this->status_code   = $status_code;

		return $status_header;
	}

	public function redirect_status( $status, $location ) {
		if ( $this->cache_redirects ) {
			$this->redirect_status   = $status;
			$this->redirect_location = $location;
		}

		return $status;
	}

	public function do_headers( $headers1, $headers2 = array() ) {

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
				header( "$k: $v", $clobber );
				$clobber = false;
			}
		}
	}

	/**
	 * Configure the memcached client
	 */
	public function configure_groups() {

		if ( ! $this->remote ) {
			if ( function_exists( 'wp_cache_add_no_remote_groups' ) ) {
				wp_cache_add_no_remote_groups( array( $this->group ) );
			}
		}

		if ( function_exists( 'wp_cache_add_global_groups' ) ) {
			wp_cache_add_global_groups( array( $this->group ) );
		}
	}

	/**
	 * Defined here because timer_stop() calls number_format_i18n()
	 */
	public function timer_stop( $display = 0, $precision = 3 ) {
		global $timestart, $timeend;

		$mtime     = microtime();
		$mtime     = explode( ' ',$mtime );
		$mtime     = $mtime[1] + $mtime[0];
		$timeend   = $mtime;
		$timetotal = $timeend-$timestart;
		$r         = number_format( $timetotal, $precision );

		if ( ! empty( $display ) ) {
			echo $r;
		}

		return $r;
	}

	public function ob( $output ) {

		if ( false !== $this->cancel ) {
			return $output;
		}

		// PHP5 and objects disappearing before output buffers?
		wp_cache_init();

		// Remember, $wp_object_cache was clobbered in wp-settings.php so we have to repeat this.
		$this->configure_groups();

		// Unlock regeneration
		wp_cache_delete( "{$this->url_key}_genlock", $this->group );

		// Do not cache blank pages unless they are HTTP redirects
		$output = trim( $output );
		if ( ( $output === '' ) && ( ! $this->redirect_status || ! $this->redirect_location ) ) {
			return;
		}

		// Do not cache 5xx responses
		if ( isset( $this->status_code ) && intval( $this->status_code / 100 ) == 5 ) {
			return $output;
		}

		$this->do_variants( $this->vary );
		$this->generate_keys();

		// Construct and save the spider_cache
		$this->cache = array(
			'output'            => $output,
			'time'              => time(),
			'headers'           => array(),
			'timer'             => $this->timer_stop( false, 3 ),
			'status_header'     => $this->status_header,
			'redirect_status'   => $this->redirect_status,
			'redirect_location' => $this->redirect_location,
			'version'           => $this->url_version
		);

		if ( function_exists( 'headers_list' ) ) {
			foreach ( headers_list() as $header ) {
				list( $k, $v ) = array_map( 'trim', explode( ':', $header, 2 ) );
				$cache['headers'][ $k ] = $v;
			}
		} elseif ( function_exists( 'apache_response_headers' ) ) {
			$cache['headers'] = apache_response_headers();
		}

		if ( ! empty( $this->cache['headers'] ) && ! empty( $this->uncached_headers ) ) {
			foreach ( $this->uncached_headers as $header ) {
				unset( $this->cache['headers'][ $header ] );
			}
		}

		foreach ( $this->cache['headers'] as $header => $values ) {

			// Do not cache if cookies were set
			if ( strtolower( $header ) === 'set-cookie' ) {
				return $output;
			}

			foreach ( (array) $values as $value ) {
				if ( preg_match( '/^Cache-Control:.*max-?age=(\d+)/i', "$header: $value", $matches ) ) {
					$this->max_age = intval( $matches[1] );
				}
			}
		}

		$this->cache['max_age'] = $this->max_age;

		wp_cache_set( $this->key, $this->cache, $this->group, $this->max_age + $this->seconds + 30 );

		if ( $this->cache_control ) {

			// Don't clobber Last-Modified header if already set, e.g. by WP::send_headers()
			if ( ! isset( $this->cache['headers']['Last-Modified'] ) ) {
				header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $this->cache['time'] ) . ' GMT', true );
			}

			if ( ! isset($this->cache['headers']['Cache-Control']) ) {
				header( "Cache-Control: max-age={$this->max_age}, must-revalidate", false );
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

	public function add_variant( $function ) {
		$key = md5( $function );
		$this->vary[ $key ] = $function;
	}

	public function do_variants( $dimensions = false ) {
		// This function is called without arguments early in the page load, then with arguments during the OB handler.
		if ( false === $dimensions ) {
			$dimensions = wp_cache_get( "{$this->url_key}_vary", $this->group );
		} else {
			wp_cache_set( "{$this->url_key}_vary", $dimensions, $this->group, $this->max_age + 10 );
		}

		if ( is_array( $dimensions ) ) {
			ksort( $dimensions );
			foreach ( $dimensions as $key => $function ) {
				$fun   = create_function( '', $function );
				$value = $fun();
				$this->keys[ $key ] = $value;
			}
		}
	}

	public function generate_keys() {
		//ksort($this->keys); // uncomment this when traffic is slow
		$this->key     = md5( serialize( $this->keys ) );
		$this->req_key = $this->key . '_req';
	}

	public function add_debug_just_cached() {
		$generation = $this->cache['timer'];
		$bytes      = strlen( serialize( $this->cache ) );
		$html       = <<<HTML
<!--
	generated in $generation seconds
	$bytes bytes spider_cached for {$this->max_age} seconds
-->

HTML;
		$this->add_debug_html_to_output( $html );
	}

	public function add_debug_from_cache() {
		$seconds_ago = time() - $this->cache['time'];
		$generation  = $this->cache['timer'];
		$serving     = $this->timer_stop( false, 3 );
		$expires     = $this->cache['max_age'] - time() + $this->cache['time'];
		$html        = <<<HTML
<!--
	generated $seconds_ago seconds ago
	generated in $generation seconds
	served from spider_cache in $serving seconds
	expires in $expires seconds
-->

HTML;
		$this->add_debug_html_to_output( $html );
	}

	public function add_debug_html_to_output( $debug_html ) {
		// Casing on the Content-Type header is inconsistent
		foreach ( array( 'Content-Type', 'Content-type' ) as $key ) {
			if ( isset( $this->cache['headers'][ $key ][0] ) && 0 !== strpos( $this->cache['headers'][ $key ][0], 'text/html' ) ) {
				return;
			}
		}

		$head_position = strpos( $this->cache['output'], '<head' );
		if ( false === $head_position ) {
			return;
		}
		$this->cache['output'] = substr_replace( $this->cache['output'], $debug_html, $head_position, 0 );
	}
}
