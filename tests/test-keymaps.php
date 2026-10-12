<?php

/**
 * Regression coverage for cache-key map parsing.
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['blog_id']            = 1;
$GLOBALS['is_multisite']       = false;
$GLOBALS['wp_object_cache']    = (object) array(
	'cache_key_salt' => '',
	'global_groups'  => array( 'users' )
);

function add_action() {}

function get_current_blog_id() {
	return (int) $GLOBALS['blog_id'];
}

function is_multisite() {
	return (bool) $GLOBALS['is_multisite'];
}

function wp_object_cache() {
	return $GLOBALS['wp_object_cache'];
}

require dirname( __DIR__ ) . '/wp-spider-cache.php';

class WP_Spider_Cache_Keymap_Test_UI extends WP_Spider_Cache_UI {
	public $items = array();

	public function retrieve_keys( $server, $port = 11211 ) {
		return $this->items;
	}
}

function set_private_property( $object, $name, $value ) {
	$property = new ReflectionProperty( 'WP_Spider_Cache_UI', $name );

	if ( PHP_VERSION_ID < 80100 ) {
		$property->setAccessible( true );
	}

	$property->setValue( $object, $value );
}

function get_keymaps( $object ) {
	$method = new ReflectionMethod( 'WP_Spider_Cache_UI', 'get_keymaps' );

	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}

	return $method->invoke( $object, '127.0.0.1' );
}

function assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, $message . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

set_error_handler( function( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );

$reflection = new ReflectionClass( 'WP_Spider_Cache_Keymap_Test_UI' );
$ui         = $reflection->newInstanceWithoutConstructor();
$ui->items = array(
	'1:options:alloptions',
	'1:123:numeric-group-key',
	'1:users:1',
	'users:2',
	'laravel:1',
	'foreign:value',
	'foreign:group:key',
	'2:options:other-site',
	'1.0:options:decimal-prefix',
	'1e0:options:exponent-prefix',
	'+1:options:signed-prefix'
);

set_private_property( $ui, 'blog_ids', array( 1 ) );

assert_same(
	array(
		'1123' => array(
			'blog_id' => 1,
			'group'   => '123',
			'keys'    => array( 'numeric-group-key' ),
			'item'    => '1:123:numeric-group-key'
		),
		'1options' => array(
			'blog_id' => 1,
			'group'   => 'options',
			'keys'    => array( 'alloptions' ),
			'item'    => '1:options:alloptions'
		),
		'1users' => array(
			'blog_id' => 1,
			'group'   => 'users',
			'keys'    => array( '1', '2' ),
			'item'    => '1:users:1'
		)
	),
	get_keymaps( $ui ),
	'Single-site key maps should exclude incomplete and unrelated keys.'
);

$GLOBALS['wp_object_cache']->cache_key_salt = 'site :: production';
$ui->items = array(
	'site:production:1:options:alloptions',
	'other:production:1:options:foreign',
	'site:staging:1:options:foreign'
);

assert_same(
	array(
		'1options' => array(
			'blog_id' => 1,
			'group'   => 'options',
			'keys'    => array( 'alloptions' ),
			'item'    => 'site:production:1:options:alloptions'
		)
	),
	get_keymaps( $ui ),
	'Salted single-site keys should retain their group and key.'
);

$GLOBALS['is_multisite']                    = true;
$GLOBALS['wp_object_cache']->cache_key_salt = '';
$ui->items = array(
	'2:posts:last_changed',
	'2:posts',
	'users:1',
	'laravel:cache:foo',
	'foreign:value'
);

set_private_property( $ui, 'blog_ids', array( 0, 2 ) );

assert_same(
	array(
		'0users' => array(
			'blog_id' => 0,
			'group'   => 'users',
			'keys'    => array( '1' ),
			'item'    => 'users:1'
		),
		'2posts' => array(
			'blog_id' => 2,
			'group'   => 'posts',
			'keys'    => array( 'last_changed' ),
			'item'    => '2:posts:last_changed'
		)
	),
	get_keymaps( $ui ),
	'Multisite key maps should distinguish global and site keys.'
);

$GLOBALS['wp_object_cache']->cache_key_salt = 'network';
$ui->items = array(
	'network:2:posts:last_changed',
	'network:users:1',
	'other:2:posts:foreign'
);

assert_same(
	array(
		'0users' => array(
			'blog_id' => 0,
			'group'   => 'users',
			'keys'    => array( '1' ),
			'item'    => 'network:users:1'
		),
		'2posts' => array(
			'blog_id' => 2,
			'group'   => 'posts',
			'keys'    => array( 'last_changed' ),
			'item'    => 'network:2:posts:last_changed'
		)
	),
	get_keymaps( $ui ),
	'Salted multisite keys should retain global and site key ownership.'
);

restore_error_handler();

echo "Cache key-map tests passed.\n";
