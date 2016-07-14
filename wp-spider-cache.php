<?php
/**
 * Plugin Name: WP Spider Cache
 * Plugin URI:  https://wordpress.org/plugins/wp-spider-cache/
 * Author:      John James Jacoby
 * Author URI:  https://profiles.wordpress.org/johnjamesjacoby/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Description: Your friendly neighborhood caching solution for WordPress
 * Version:     2.2.0
 * Text Domain: wp-spider-cache
 * Domain Path: /assets/lang/
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * The main Spider-Cache admin interface
 *
 * @since 2.0.0
 */
class WP_Spider_Cache_UI {

	/**
	 * The URL used for assets
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private $asset_url = '';

	/**
	 * The version used for assets
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private $asset_version = '201602160001';

	/**
	 * The resulting page's hook_suffix.
	 *
	 * @since 2016-02-18
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Allows UI to be cache engine agnostic
	 *
	 * @since 2.2.0
	 *
	 * @var string
	 */
	private $cache_engine = '';

	/**
	 * Array of blog IDs to show
	 *
	 * @since 2.2.0
	 *
	 * @var array
	 */
	private $blog_ids = array( 0 );

	/**
	 * Nonce ID for getting the cache instance
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	const INSTANCE_NONCE = 'sc_get_instance';

	/**
	 * Nonce ID for flushing a cache group
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	const FLUSH_NONCE = 'sc_flush_group';

	/**
	 * Nonce ID for removing an item from cache
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	const REMOVE_NONCE = 'sc_remove_item';

	/**
	 * Nonce ID for retrieving an item from cache
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	const GET_NONCE = 'sc_get_item';

	/**
	 * Initialize the protected singleton
	 *
	 * @since 2.2.0
	 *
	 * @return WP_Spider_Cache_UI
	 */
	public static function init() {
		static $class_object = null;

		if ( null === $class_object ) {
			$class_object = new self;
		}

		return $class_object;
	}

	/**
	 * The main constructor
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		$this->cache_engine = 'Memcache';

		// Notices
		add_action( 'spider_cache_notice', array( $this, 'notice' ) );

		// Admin menus
		add_action( 'admin_menu',            array( $this, 'admin_menu' ) );
		add_action( 'user_admin_menu',       array( $this, 'admin_menu' ) );
		add_action( 'network_admin_menu',    array( $this, 'admin_menu' ) );

		// Admin styling
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );

		// AJAX
		add_action( 'wp_ajax_sc-get-item',     array( $this, 'ajax_get_item'     ) );
		add_action( 'wp_ajax_sc-get-instance', array( $this, 'ajax_get_instance' ) );
		add_action( 'wp_ajax_sc-flush-group',  array( $this, 'ajax_flush_group'  ) );
		add_action( 'wp_ajax_sc-remove-item',  array( $this, 'ajax_remove_item'  ) );

		// Capabilities
		add_filter( 'map_meta_cap', array( $this, 'map_meta_cap' ), 10, 2 );

		// Posts
		add_action( 'clean_post_cache', array( $this, 'clean_post' ) );
	}

	/**
	 * Add the top-level admin menu
	 *
	 * @since 2.0.0
	 */
	public function admin_menu() {

		// Add menu page
		$this->hook = add_menu_page(
			esc_html__( 'Spider Cache', 'wp-spider-cache' ),
			esc_html__( 'Spider Cache', 'wp-spider-cache' ),
			'manage_cache', // Single-site admins and multi-site super admins
			'wp-spider-cache',
			array( $this, 'page' ),
			'dashicons-editor-code'
		);

		// Load page on hook
		add_action( "load-{$this->hook}", array( $this, 'load' ) );
		add_action( "load-{$this->hook}", array( $this, 'help' ) );
	}

	/**
	 * Enqueue assets
	 *
	 * @since 2.0.0
	 */
	public function admin_enqueue() {

		// Bail if not this page
		if ( $GLOBALS['page_hook'] !== $this->hook ) {
			return;
		}

		// Setup the plugin URL, for enqueues
		$this->asset_url = plugin_dir_url( __FILE__ );

		// Enqueue
		wp_enqueue_style( 'wp-spider-cache', $this->asset_url . 'assets/css/spider-cache.css', array(),          $this->asset_version );
		wp_enqueue_script( 'wp-spider-cache', $this->asset_url . 'assets/js/spider-cache.js', array( 'jquery' ), $this->asset_version, true );

		// Localize JS
		wp_localize_script( 'wp-spider-cache', 'WP_Spider_Cache', array(
			'no_results'         => $this->get_no_results_row(),
			'refreshing_results' => $this->get_refreshing_results_row()
		) );
	}

	/**
	 * Map `manage_cache` capability
	 *
	 * @since 2.3.0
	 *
	 * @param array  $caps
	 * @param string $cap
	 */
	public function map_meta_cap( $caps = array(), $cap = '' ) {

		// Map single-site cap check to 'manage_options'
		if ( 'manage_cache' === $cap ) {
			if ( ! is_multisite() ) {
				$caps = array( 'manage_options' );
			}
		}

		// Return maybe-mapped caps
		return $caps;
	}

	/**
	 * Maybe clear a cache group, based on user request
	 *
	 * @since 2.0.0
	 *
	 * @param bool $redirect
	 */
	private function maybe_clear_cache_group( $redirect = true ) {

		// Bail if not clearing
		if ( empty( $_GET['cache_group'] ) ) {
			return;
		}

		// Sanitize cache group to clear
		$group = $this->sanitize_key( $_GET['cache_group'] );

		// Clear the cache group
		$cleared = $this->clear_group( $group );

		// Bail if not redirecting
		if ( false === $redirect ) {
			return;
		}

		// Assemble the URL
		$url = add_query_arg( array(
			'type'          => 'group',
			'keys_cleared'  => $cleared,
			'cache_cleared' => $group
		), menu_page_url( 'wp-spider-cache', false ) );

		// Redirect
		wp_safe_redirect( $url );
		exit();
	}

	/**
	 * Maybe clear a user's entire cache, based on user request
	 *
	 * @since 2.0.0
	 *
	 * @param bool $redirect
	 */
	private function maybe_clear_user_cache( $redirect = true ) {

		// Clear user ID
		if ( empty( $_GET['user_id'] ) ) {
			return;
		}

		// How are we getting the user?
		if ( is_numeric( $_GET['user_id'] ) ) {
			$by = 'id';
		} elseif ( is_email( $_GET['user_id'] ) ) {
			$by = 'email';
		} elseif ( is_string( $_GET['user_id'] ) ) {
			$by = 'slug';
		} else {
			$by = 'login';
		}

		// Get the user
		$_user = get_user_by( $by, $_GET['user_id'] );

		// Bail if no user found
		if ( empty( $_user ) ) {
			return;
		}

		$cleared = array();

		// Delete user caches
		$cleared[] = wp_cache_delete( $_user->ID,            'users'      );
		$cleared[] = wp_cache_delete( $_user->ID,            'user_meta'  );
		$cleared[] = wp_cache_delete( $_user->user_login,    'userlogins' );
		$cleared[] = wp_cache_delete( $_user->user_nicename, 'userslugs'  );
		$cleared[] = wp_cache_delete( $_user->user_email,    'useremail'  );

		// Bail if not redirecting
		if ( false === $redirect ) {
			return;
		}

		// Assemble the URL
		$url = add_query_arg( array(
			'type'          => 'user',
			'keys_cleared'  => count( array_filter( $cleared ) ),
			'cache_cleared' => $_user->ID
		), menu_page_url( 'wp-spider-cache', false ) );

		// Redirect
		wp_safe_redirect( $url );
		exit();
	}

	/**
	 * Check for network activation and init to add menu item.
	 *
	 * @since 2.2.0
	 */
	public function set_blog_ids() {

		// Blog
		if ( empty( $_POST['type'] ) || ( 'blog' === $_POST['type'] ) ) {
			$this->blog_ids = array( (int) get_current_blog_id() );

		// Network
		} elseif ( 'network' === $_POST['type'] ) {
			$this->blog_ids = array( 0 );

		// User
		} elseif ( 'user' === $_POST['type'] ) {
			$this->blog_ids = array( 0 );
		}
	}

	/**
	 * Helper function to check nonce and avoid caching the request
	 *
	 * @since 2.0.0
	 *
	 * @param string $nonce
	 */
	private function check_nonce( $nonce = '' ) {
		check_ajax_referer( $nonce , 'nonce' );

		nocache_headers();
	}

	/**
	 * Attempt to output the server cache contents
	 *
	 * @since 2.0.0
	 */
	public function ajax_get_instance() {
		$this->check_nonce( self::INSTANCE_NONCE );

		// Attempt to output the server contents
		if ( ! empty( $_POST['name'] ) ) {
			$server = filter_var( $_POST['name'], FILTER_VALIDATE_IP );
			$this->set_blog_ids();
			$this->do_rows( $server );
		}

		exit();
	}

	/**
	 * Delete all cache keys in a cache group
	 *
	 * @since 2.0.0
	 */
	public function ajax_flush_group() {
		$this->check_nonce( self::FLUSH_NONCE );

		// Loop through keys and attempt to delete them
		if ( ! empty( $_POST['keys'] ) && ! empty( $_GET['group'] ) ) {
			foreach ( $_POST['keys'] as $key ) {
				wp_cache_delete(
					$this->sanitize_key( $key           ),
					$this->sanitize_key( $_GET['group'] )
				);
			}
		}

		exit();
	}

	/**
	 * Delete a single cache key in a specific group
	 *
	 * @since 2.0.0
	 */
	public function ajax_remove_item() {
		$this->check_nonce( self::REMOVE_NONCE );

		// Delete a key in a group
		if ( ! empty( $_GET['key'] ) && ! empty( $_GET['group'] ) ) {
			wp_cache_delete(
				$this->sanitize_key( $_GET['key']   ),
				$this->sanitize_key( $_GET['group'] )
			);
		}

		exit();
	}

	/**
	 * Attempt to get a cached item
	 *
	 * @since 2.0.0
	 */
	public function ajax_get_item() {
		$this->check_nonce( self::GET_NONCE );

		// Bail if invalid posted data
		if ( ! empty( $_GET['key'] ) && ! empty( $_GET['group'] ) ) {
			$this->do_item(
				$this->sanitize_key( $_GET['key']   ),
				$this->sanitize_key( $_GET['group'] )
			);
		}

		exit();
	}

	/**
	 * Clear all of the items in a cache group
	 *
	 * @since 2.0.0
	 *
	 * @param string $group
	 * @return int
	 */
	public function clear_group( $group = '' ) {

		// Setup counter
		$cleared = 0;
		$servers = $this->get_servers();

		// Loop through servers
		foreach ( $servers as $server ) {
			$port = empty( $server[1] ) ? 11211 : $server['port'];
			$list = $this->retrieve_keys( $server['host'], $port );

			// Loop through items
			foreach ( $list as $item ) {
				if ( strstr( $item, "{$group}:" ) ) {
					wp_cache_delete( $item );
					$cleared++;
				}
			}
		}

		// Return count
		return $cleared;
	}

	/**
	 * Check for actions
	 *
	 * @since 2.0.0
	 */
	public function load() {
		$this->maybe_clear_cache_group( true );
		$this->maybe_clear_user_cache( true );
	}

	/**
	 * Help text
	 *
	 * @since 2.1.0
	 */
	public function help() {

		// Overview
		get_current_screen()->add_help_tab( array(
			'id'      => 'overview',
			'title'   => esc_html__( 'Overview', 'wp-spider-cache' ),
			'content' =>
				'<p>' . esc_html__( 'All the cached objects and output is listed alphabetically in Spider Cache, starting with global groups and ending with this specific site.',   'wp-spider-cache' ) . '</p>' .
				'<p>' . esc_html__( 'You can narrow the list by searching for specific group & key names.', 'wp-spider-cache' ) . '</p>'
		) );

		// Using cache key salt
		if ( defined( 'WP_CACHE_KEY_SALT' ) && WP_CACHE_KEY_SALT ) {
			get_current_screen()->add_help_tab( array(
				'id'      => 'salt',
				'title'   => esc_html__( 'Cache Key', 'wp-spider-cache' ),
				'content' =>
					'<p>' . sprintf( esc_html__( 'A Cache Key Salt was identified: %s', 'wp-spider-cache' ), '<code>' . WP_CACHE_KEY_SALT . '</code>' ) . '</p>' .
					'<p>' . __( 'This advanced configuration option is usually defined in <code>wp-config.php</code> and is commonly used as a way to invalidate all cached data for the entire installation by updating the value of the <code>WP_CACHE_KEY_SALT</code> constant.', 'wp-spider-cache' ) . '</p>'
			) );
		}

		// Servers
		get_current_screen()->add_help_tab( array(
			'id'      => 'servers',
			'title'   => esc_html__( 'Servers', 'wp-spider-cache' ),
			'content' =>
				'<p>' . esc_html__( 'Choose a registered cache server from the list. Content from that server is automatically retrieved & presented in the table.', 'wp-spider-cache' ) . '</p>' .
				'<p>' . esc_html__( 'It is possible to have more than one cache server, and each server may have different cached content available to it.', 'wp-spider-cache' ) . '</p>' .
				'<p>' . esc_html__( 'Clicking "Refresh" will fetch fresh data from the selected server, and repopulate the table.', 'wp-spider-cache' ) . '</p>'
		) );

		// Screen Content
		get_current_screen()->add_help_tab( array(
			'id'		=> 'content',
			'title'		=> __( 'Screen Content', 'wp-spider-cache' ),
			'content'	=>
				'<p>'  . esc_html__( 'Cached content is displayed in the following way:', 'wp-spider-cache' ) . '</p><ul>' .
				'<li>' . esc_html__( 'Cache groups are listed alphabetically.', 'wp-spider-cache' ) . '</li>' .
				'<li>' . esc_html__( 'Global cache groups will be shown first.', 'wp-spider-cache' ) . '</li>' .
				'<li>' . esc_html__( 'Cache groups for this specific site are shown last.', 'wp-spider-cache' ) . '</li></ul>'
		) );

		// Available actions
		get_current_screen()->add_help_tab( array(
			'id'      => 'actions',
			'title'   => __( 'Available Actions', 'wp-spider-cache' ),
			'content' =>
				'<p>'  . esc_html__( 'Hovering over a row in the list will display action links that allow you to manage that content. You can perform the following actions:', 'wp-spider-cache' ) . '</strong></p><ul>' .
				'<li>' .         __( '<strong>Search</strong> for content within the list.', 'wp-spider-cache' ) . '</li>' .
				'<li>' .         __( '<strong>Clear</strong> many caches at the same time, by group or user ID.', 'wp-spider-cache' ) . '</li>' .
				'<li>' .         __( '<strong>Flush</strong> an entire cache group to remove all of the subsequent keys.', 'wp-spider-cache' ) . '</li>' .
				'<li>' .         __( '<strong>Remove</strong> a single cache key from within a cache group.', 'wp-spider-cache' ) . '</li>' .
				'<li>' .         __( '<strong>View</strong> the contents of a single cache key.', 'wp-spider-cache' ) . '</li></ul>'
		) );

		// Help Sidebar
		get_current_screen()->set_help_sidebar(
			'<p><i class="dashicons dashicons-wordpress"></i> '     . esc_html__( 'Blog ID',     'wp-spider-cache' ) . '</p>' .
			'<p><i class="dashicons dashicons-admin-site"></i> '    . esc_html__( 'Cache Group', 'wp-spider-cache' ) . '</p>' .
			'<p><i class="dashicons dashicons-admin-network"></i> ' . esc_html__( 'Keys',        'wp-spider-cache' ) . '</p>' .
			'<p><i class="dashicons dashicons-editor-code"></i> '   . esc_html__( 'Count',       'wp-spider-cache' ) . '</p>'
		);
	}

	/**
	 * Get all cache keys on a server
	 *
	 * @since 2.0.0
	 *
	 * @param  string $server
	 * @param  int    $port
	 *
	 * @return array
	 */
	public function retrieve_keys( $server, $port = 11211 ) {

		// No errors
		$old_errors = error_reporting( 0 );

		// Get slabs
		$list  = array();

		// Connect to cache server
		wp_cache_connect( $server, $port );

		// Get slabs from extended stats
		$slabs = wp_cache_get_extended_stats( 'slabs' );

		// Loop through servers to get slabs
		foreach ( $slabs as $server => $slabs ) {

			// Loop through slabs to target single slabs
			foreach ( array_keys( $slabs ) as $slab_id ) {

				// Skip if slab ID is empty
				if ( empty( $slab_id ) ) {
					continue;
				}

				// Get the entire slab
				$cache_dump = wp_cache_get_extended_stats( 'cachedump', (int) $slab_id );

				// Loop through slab to find keys
				foreach ( $cache_dump as $slab_dump ) {

					// Skip if key isn't an array (how'd that happen?)
					if ( ! is_array( $slab_dump ) ) {
						continue;
					}

					// Loop through keys and add to list
					foreach( array_keys( $slab_dump ) as $k ) {
						$list[] = $k;
					}
				}
			}
		}

		// Restore error reporting
		error_reporting( $old_errors );

		// Return the list of cache server slab keys
		return $list;
	}

	/**
	 * Output the contents of a cached item into a textarea
	 *
	 * @since 2.0.0
	 *
	 * @param  string  $key
	 * @param  string  $group
	 */
	public function do_item( $key, $group ) {

		// Get results directly from cache
		$cache   = wp_cache_get( $key, $group );
		$full    = wp_cache_get_key( $key, $group );
		$code    = wp_cache_get_result_code();
		$message = wp_cache_get_result_message();

		// @todo Something prettier with cached value
		$value   = is_array( $cache ) || is_object( $cache )
			? serialize( $cache )
			: $cache;

		// Not found?
		if ( false === $value ) {
			$value = 'ERR';
		}

		// Combine results
		$results =
			sprintf( esc_html__( 'Key:     %s',      'wp-spider-cache' ), $key            ) . "\n" .
			sprintf( esc_html__( 'Group:   %s',      'wp-spider-cache' ), $group          ) . "\n" .
			sprintf( esc_html__( 'Full:    %s',      'wp-spider-cache' ), $full           ) . "\n" .
			sprintf( esc_html__( 'Code:    %s - %s', 'wp-spider-cache' ), $code, $message ) . "\n" .
			sprintf( esc_html__( 'Value:   %s',      'wp-spider-cache' ), $value          ); ?>

		<textarea class="sc-item" class="widefat" rows="10" cols="35"><?php echo esc_textarea( $results ); ?></textarea>

		<?php
	}

	/**
	 * Output a link used to flush an entire cache group
	 *
	 * @since 0.2.0
	 *
	 * @param int    $blog_id
	 * @param string $group
	 * @param string $nonce
	 */
	private function get_flush_group_link( $blog_id, $group, $nonce ) {

		// Setup the URL
		$url = add_query_arg( array(
			'action'  => 'sc-flush-group',
			'blog_id' => (int) $blog_id,
			'group'   => $this->sanitize_key( $group ),
			'nonce'   => $nonce
		), admin_url( 'admin-ajax.php' ) );

		// Start the output buffer
		ob_start(); ?>

		<a class="sc-flush-group" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Flush Group', 'wp-spider-cache' ); ?></a>

		<?php

		// Return the output buffer
		return ob_get_clean();
	}

	/**
	 * Get the map of cache groups $ keys
	 *
	 * The keymap is limited to global keys and keys to the current site. This
	 * is because cache keys are built inside the WP_Object_Cache class, and
	 * are occasionally prefixed with the current blog ID, meaning we cannot
	 * reliably ask the cache server for data without a way to force the key.
	 *
	 * Maybe in a future version of WP_Object_Cache, a method to retrieve a raw
	 * value based on a full cache key will exist. Until then, no bueno.
	 *
	 * @since 2.0.0
	 *
	 * @param  string $server
	 * @return array
	 */
	private function get_keymaps( $server = '' ) {

		// Set an empty keymap array
		$keymaps = array();
		$offset  = 0;

		// Offset by 1 if using cache-key salt
		if ( wp_object_cache()->cache_key_salt ) {
			$offset = 1;
		}

		// Get keys for this server and loop through them
		foreach ( $this->retrieve_keys( $server ) as $item ) {

			// Skip if CLIENT_ERROR or malforwed [sic]
			if ( empty( $item ) || ! strstr( $item, ':' ) ) {
				continue;
			}

			// Separate the item into parts
			$parts = explode( ':', $item );

			// Remove key salts
			if ( $offset > 0 ) {
				$parts = array_slice( $parts, $offset );
			}

			// Multisite means first part is numeric
			if ( is_numeric( $parts[ 0 ] ) ) {
				$blog_id = (int) $parts[ 0 ];
				$group   = $parts[ 1 ];
				$global  = false;

			// Single site or global cache group
			} else {
				$blog_id = 0;
				$group   = $parts[ 0 ];
				$global  = true;
			}

			// Only show global keys and keys for this site
			if ( ! in_array( $blog_id, $this->blog_ids, true ) ) {
				continue;
			}

			// Build the cache key based on number of parts
			if ( ( count( $parts ) === 1 ) ) {
				$key = $parts[ 0 ];
			} else {
				if ( true === $global ) {
					$key = implode( ':', array_slice( $parts, 1 ) );
				} else {
					$key = implode( ':', array_slice( $parts, 2 ) );
				}
			}

			// Build group key by combining blog ID & group
			$group_key = $blog_id . $group;

			// Build the keymap
			if ( isset( $keymaps[ $group_key ] ) ) {
				$keymaps[ $group_key ]['keys'][] = $key;
			} else {
				$keymaps[ $group_key ] = array(
					'blog_id' => $blog_id,
					'group'   => $group,
					'keys'    => array( $key ),
					'item'    => $item
				);
			}
		}

		// Sort the keymaps by key
		ksort( $keymaps );

		return $keymaps;
	}

	/**
	 * Output contents of cache group keys
	 *
	 * @since 2.0.0
	 *
	 * @param int    $blog_id
	 * @param string $group
	 * @param array  $keys
	 */
	private function get_cache_key_links( $blog_id = 0, $group = '', $keys = array() ) {

		// Setup variables used in the loop
		$admin_url = admin_url( 'admin-ajax.php' );

		// Start the output buffer
		ob_start();

		// Loop through keys and output data & action links
		foreach ( $keys as $key ) :

			// Get URL
			$get_url = add_query_arg( array(
				'blog_id' => (int) $blog_id,
				'group'   => $this->sanitize_key( $group ),
				'key'     => $this->sanitize_key( $key   ),
				'action'  => 'sc-get-item',
				'nonce'   => wp_create_nonce( self::GET_NONCE    ),
			), $admin_url );

			// Maybe include the blog ID in the group
			$include_blog_id = ! empty( $blog_id )
				? "{$blog_id}:{$group}"
				: $group;

			// Remove URL
			$remove_url = add_query_arg( array(
				'group'   => $this->sanitize_key( $include_blog_id ),
				'key'     => $this->sanitize_key( $key             ),
				'action'  => 'sc-remove-item',
				'nonce'   => wp_create_nonce( self::REMOVE_NONCE )
			), $admin_url ); ?>

			<div class="item" data-key="<?php echo esc_attr( $key ); ?>">
				<code><?php echo implode( '</code> : <code>', explode( ':', $key ) ); ?></code>
				<div class="row-actions">
					<span class="trash">
						<a class="sc-remove-item" href="<?php echo esc_url( $remove_url ); ?>"><?php esc_html_e( 'Remove', 'wp-spider-cache' ); ?></a>
					</span>
					| <a class="sc-view-item" href="<?php echo esc_url( $get_url ); ?>"><?php esc_html_e( 'View', 'wp-spider-cache' ); ?></a>
				</div>
			</div>

			<?php
		endforeach;

		// Return the output buffer
		return ob_get_clean();
	}

	/**
	 * Get the type of admin request this is
	 *
	 * @since 2.2.0
	 *
	 * @return string
	 */
	private function get_admin_type() {

		// Default
		$type = 'blog';

		// Network admin
		if ( is_network_admin() ) {
			$type = 'network';

		// User admin
		} elseif ( is_user_admin() ) {
			$type = 'user';
		}

		// Return the type
		return $type;
	}

	/**
	 * Output the WordPress admin page
	 *
	 * @since 2.0.0
	 */
	public function page() {
		?>

		<div class="wrap spider-cache" id="sc-wrapper">
			<h2><?php esc_html_e( 'Spider Cache', 'wp-spider-cache' ); ?></h2>

			<?php do_action( 'spider_cache_notice' ); ?>

			<div class="wp-filter">
				<div class="sc-toolbar-secondary">
					<select class="sc-server-selector" data-nonce="<?php echo wp_create_nonce( self::INSTANCE_NONCE ); ?>">
						<option value=""><?php esc_html_e( 'Select a Server', 'wp-spider-cache' ); ?></option><?php

						// Loop through servers
						foreach ( $this->get_servers() as $server ) :

							?><option value="<?php echo esc_attr( $server['host'] ); ?>"><?php echo esc_html( $server['host'] ); ?></option><?php

						endforeach;

					?></select>
					<button class="button action sc-refresh-instance" disabled><?php esc_html_e( 'Refresh', 'wp-spider-cache' ); ?></button>
					<input type="hidden" name="sc-admin-type" id="sc-admin-type" value="<?php echo esc_attr( $this->get_admin_type() ); ?>">
				</div>
				<div class="sc-toolbar-primary search-form">
					<label for="sc-search-input" class="screen-reader-text"><?php esc_html_e( 'Search Cache', 'wp-spider-cache' ); ?></label>
					<input type="search" placeholder="<?php esc_html_e( 'Search', 'wp-spider-cache' ); ?>" id="sc-search-input" class="search">
				</div>
			</div>

			<div id="sc-show-item"></div>

			<div class="tablenav top">
				<div class="alignleft actions bulkactions">
					<?php echo $this->bulk_actions(); ?>
				</div>
				<div class="alignright">
					<form action="<?php menu_page_url( 'wp-spider-cache' ) ?>" method="get">
						<input type="hidden" name="page" value="wp-spider-cache">
						<input type="text" name="cache_group" />
						<button class="button"><?php esc_html_e( 'Clear Cache Group', 'wp-spider-cache' ); ?></button>
					</form>
					<form action="<?php menu_page_url( 'wp-spider-cache' ) ?>" method="get">
						<input type="hidden" name="page" value="wp-spider-cache">
						<input type="text" name="user_id" />
						<button class="button"><?php esc_html_e( 'Clear User Cache', 'wp-spider-cache' ); ?></button>
					</form>
				</div>
			</div>

			<table class="wp-list-table widefat fixed striped posts">
				<thead>
					<tr>
						<td id="cb" class="manage-column column-cb check-column">
							<label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e( 'Select All', 'wp-spider-cache' ); ?></label>
							<input id="cb-select-all-1" type="checkbox">
						</td>
						<th class="blog-id"><?php esc_html_e( 'Blog ID', 'wp-spider-cache' ); ?></th>
						<th class="cache-group"><?php esc_html_e( 'Cache Group', 'wp-spider-cache' ); ?></th>
						<th class="keys"><?php esc_html_e( 'Keys', 'wp-spider-cache' ); ?></th>
						<th class="count"><?php esc_html_e( 'Count', 'wp-spider-cache' ); ?></th>
					</tr>
				</thead>

				<tbody class="sc-contents">
					<?php echo $this->get_no_results_row(); ?>
				</tbody>

				<tfoot>
					<tr>
						<td id="cb" class="manage-column column-cb check-column">
							<label class="screen-reader-text" for="cb-select-all-2"><?php esc_html_e( 'Select All', 'wp-spider-cache' ); ?></label>
							<input id="cb-select-all-2" type="checkbox">
						</td>
						<th class="blog-id"><?php esc_html_e( 'Blog ID', 'wp-spider-cache' ); ?></th>
						<th class="cache-group"><?php esc_html_e( 'Cache Group', 'wp-spider-cache' ); ?></th>
						<th class="keys"><?php esc_html_e( 'Keys', 'wp-spider-cache' ); ?></th>
						<th class="count"><?php esc_html_e( 'Count', 'wp-spider-cache' ); ?></th>
					</tr>
				</tfoot>
			</table>
		</div>

		<?php
	}

	/**
	 * Return the bulk actions dropdown
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function bulk_actions() {

		// Start an output buffer
		ob_start(); ?>

		<label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'wp-spider-cache' ); ?></label>
		<select name="action" id="bulk-action-selector-top">
			<option value="-1"><?php esc_html_e( 'Bulk Actions', 'wp-spider-cache' ); ?></option>
			<option value="edit" class="hide-if-no-js"><?php esc_html_e( 'Flush Groups', 'wp-spider-cache' ); ?></option>
		</select>
		<input type="submit" id="doaction" class="button action" value="<?php esc_html_e( 'Apply', 'wp-spider-cache' ); ?>">

		<?php

		// Return the output buffer
		return ob_get_clean();
	}

	/**
	 * Output the cache server contents in a table
	 *
	 * @since 2.0.0
	 *
	 * @param string $server
	 */
	public function do_rows( $server = '' ) {

		// Setup the nonce
		$nonce = wp_create_nonce( self::FLUSH_NONCE );

		// Get server key map & output groups in rows
		foreach ( $this->get_keymaps( $server ) as $values ) {
			$this->do_row( $values, $nonce );
		}
	}

	/**
	 * Output a table row based on values
	 *
	 * @since 2.0.0
	 *
	 * @param  array   $values
	 * @param  string  $nonce
	 */
	private function do_row( $values = array(), $nonce = '' ) {
		?>

		<tr>
			<th scope="row" class="check-column">
				<input type="checkbox" name="checked[]" value="<?php echo esc_attr( $values['group'] ); ?>" id="checkbox_<?php echo esc_attr( $values['group'] ); ?>">
				<label class="screen-reader-text" for="checkbox_<?php echo esc_attr( $values['group'] ); ?>"><?php esc_html_e( 'Select', 'wp-spider-cache' ); ?></label>
			</th>
			<td>
				<code><?php echo esc_html( $values['blog_id'] ); ?></code>
			</td>
			<td>
				<span class="row-title"><?php echo esc_html( $values['group'] ); ?></span>
				<div class="row-actions"><span class="trash"><?php echo $this->get_flush_group_link( $values['blog_id'], $values['group'], $nonce ); ?></span></div>
			</td>
			<td>
				<?php echo $this->get_cache_key_links( $values['blog_id'], $values['group'], $values['keys'] ); ?>
			</td>
			<td>
				<?php echo number_format_i18n( count( $values['keys'] ) ); ?>
			</td>
		</tr>

	<?php
	}

	/**
	 * Returns a table row used to show no results were found
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function get_no_results_row() {

		// Buffer
		ob_start(); ?>

		<tr class="sc-no-results">
			<td colspan="5">
				<?php esc_html_e( 'No results found.', 'wp-spider-cache' ); ?>
			</td>
		</tr>

		<?php

		// Return the output buffer
		return ob_get_clean();
	}

	/**
	 * Returns a table row used to show results are loading
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function get_refreshing_results_row() {

		// Buffer
		ob_start(); ?>

		<tr class="sc-refreshing-results">
			<td colspan="5">
				<?php esc_html_e( 'Refreshing...', 'wp-spider-cache' ); ?>
			</td>
		</tr>

		<?php

		// Return the output buffer
		return ob_get_clean();
	}

	/**
	 * Return list of servers, if function exists
	 *
	 * @since 2.1.1
	 *
	 * @return array
	 */
	private function get_servers() {
		return function_exists( 'wp_cache_get_server_list' )
			? wp_cache_get_server_list()
			: array();
	}

	/**
	 * Sanitize a user submitted cache group or key value
	 *
	 * This strips out unwanted and/or unexpected characters from cache keys
	 * and groups.
	 *
	 * @since 2.1.2
	 *
	 * @param  string  $key
	 *
	 * @return string
	 */
	private function sanitize_key( $key = '' ) {
		return preg_replace( '/[^a-z0-9:_\-]/', '', $key );
	}

	/**
	 * Maybe output a notice to the user that action has taken place
	 *
	 * @since 2.0.0
	 */
	public function notice() {

		// Default status & message
		$status  = 'notice-warning';
		$message = '';

		// Bail if no notice
		if ( isset( $_GET['cache_cleared'] ) ) {

			// Cleared
			$keys = isset( $_GET['keys_cleared'] )
				? (int) $_GET['keys_cleared']
				: 0;

			// Cache
			$cache = isset( $_GET['cache_cleared'] )
				? $_GET['cache_cleared']
				: 'none returned';

			// Type
			$type = isset( $_GET['type'] )
				? sanitize_key( $_GET['type'] )
				: 'none';

			// Success
			$status = 'notice-success';

			// Assemble the message
			if ( 'group' === $type ) {
				$message = sprintf(
					_n( 'Cleared %s key from cache group: %s', 'Cleared %s keys from cache group: %s', $keys, 'wp-spider-cache' ),
					'<strong>' . esc_html( $keys  ) . '</strong>',
					'<strong>' . esc_html( $cache ) . '</strong>'
				);
			} elseif ( 'user' === $type ) {
				$message = sprintf(
					_n( 'Cleared %s key for user ID: %s', 'Cleared %s keys for user ID: %s', $keys, 'wp-spider-cache' ),
					'<strong>' . esc_html( $keys  ) . '</strong>',
					'<strong>' . esc_html( $cache ) . '</strong>'
				);
			}
		}

		// No cache engine
		if ( ! class_exists( $this->cache_engine ) ) {
			$message = sprintf( esc_html__( 'Please install the %s extension.', 'wp-spider-cache' ), $this->cache_engine );
		}

		// No object cache
		if ( ! function_exists( 'wp_object_cache' ) ) {
			$message .= sprintf( esc_html__( 'Hmm. It looks like you do not have a persistent object cache installed. Did you forget to copy the drop-in plugins to %s?', 'wp-spider-cache' ), '<code>' . str_replace( ABSPATH, '', WP_CONTENT_DIR ) . '</code>' );
		}

		// Bail if no message
		if ( empty( $message ) ) {
			return;
		} ?>

		<div id="message" class="notice <?php echo esc_attr( $status ); ?>">
			<p><?php echo $message; // May contain HTML ?></p>
		</div>

		<?php
	}

	/**
	 * Clean a post's cached URLs
	 *
	 * @since 2.3.0
	 *
	 * @param int $post_id
	 */
	public static function clean_post( $post_id = 0 ) {

		// Get home URL
		$home = trailingslashit( get_option( 'home' ) );

		// Clear cached URLs
		self::clean_url( $home );
		self::clean_url( $home . 'feed/' );
		self::clean_url( get_permalink( $post_id ) );
	}

	/**
	 * Clear a cached URL
	 *
	 * @since 2.3.0
	 *
	 * @param string $url
	 *
	 * @return boolean
	 */
	public static function clean_url( $url = '' ) {

		// Bail if no URL
		if ( empty( $url ) ) {
			return false;
		}

		// Bail if no persistent output cache
		if ( ! function_exists( 'wp_output_cache' ) ) {
			return false;
		}

		// Normalize the URL
		if ( 0 === strpos( $url, 'https://' ) ) {
			$url = str_replace( 'https://', 'http://', $url );
		}

		if ( 0 !== strpos( $url, 'http://' ) ) {
			$url = 'http://' . $url;
		}

		$url_key = md5( $url );

		// Get cache objects
		$output_cache = wp_output_cache_init();
		$object_cache = wp_object_cache_init();

		// Bail if either cache is missing
		if ( empty( $output_cache ) || empty( $object_cache ) ) {
			return;
		}

		wp_cache_add( "{$url_key}_version", 0, $output_cache->group );

		$retval = wp_cache_incr( "{$url_key}_version", 1, $output_cache->group );

		$output_cache_no_remote_group_key = array_search( $output_cache->group, (array) $object_cache->no_remote_groups );

		// The *_version key needs to be replicated remotely, otherwise invalidation won't work.
		// The race condition here should be acceptable.
		if ( false !== $output_cache_no_remote_group_key ) {
			unset( $object_cache->no_remote_groups[ $output_cache_no_remote_group_key ] );
			$retval = wp_cache_set( "{$url_key}_version", $retval, $output_cache->group );
			$object_cache->no_remote_groups[ $output_cache_no_remote_group_key ] = $output_cache->group;
		}

		return $retval;
	}
}

// Go web. Fly. Up, up, and away web! Shazam! Go! Go! Go web go! Tally ho!
add_action( 'plugins_loaded', array( 'WP_Spider_Cache_UI', 'init' ) );
