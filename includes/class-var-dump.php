<?php

/**
 * Spider Cache Pretty Var Dump
 *
 * @package Plugins/Cache/Dump
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Visual variable dump, for wrapping output of cached values
 *
 * @since 0.1.0
 */
class WP_Spider_Cache_Var_Dump {

	/**
	 * Dumps information about multiple variables
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function dump_multi() {

		// get variables to dump
		$args = func_get_args();

		// loop through all items to output
		foreach ( $args as $arg ) {
			self::dump( $arg );
		}
	}

	/**
	 * Dump information about a variable
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $variable Variable to dump
	 *
	 * @return void
	 */
	public static function dump( $variable = false ) {

		// Check if var_dump() is available
		$disabled = (array) explode( ',', ini_get( 'disable_functions' ) );
		$no_dump  = in_array( 'var_dump', $disabled, true );

		// Bail early if var_dump is disabled
		if ( true === $no_dump ) {
			echo '<pre>' . esc_html( maybe_serialize( $variable ) ) . '</pre>';
			return;
		}

		// Start the output buffering
		ob_start();

		// Generate the output
		var_dump( $variable );

		// Get the output
		$output = ob_get_clean();
		$maps   = array(
			'string'    => '/(string\((?P<length>\d+)\)) (?P<value>\"(?<!\\\).*\")/i',
			'array'     => '/\[\"(?P<key>.+)\"(?:\:\"(?P<class>[a-z0-9_\\\]+)\")?(?:\:(?P<scope>public|protected|private))?\]=>/Ui',
			'countable' => '/(?P<type>array|int|string)\((?P<count>\d+)\)/',
			'resource'  => '/resource\((?P<count>\d+)\) of type \((?P<class>[a-z0-9_\\\]+)\)/',
			'bool'      => '/bool\((?P<value>true|false)\)/',
			'float'     => '/float\((?P<value>[0-9\.]+)\)/',
			'object'    => '/object\((?P<class>[a-z_\\\]+)\)\#(?P<id>\d+) \((?P<count>\d+)\)/i',
		);

		// Loop through maps & replace with callback
		foreach ( $maps as $function => $pattern ) {
			$output = preg_replace_callback( $pattern, array( 'self', '_process_' . $function ), $output );
		}

		// HTML - Do not escape here
		echo '<pre>' . $output . '</pre>';
	}

	/**
	 * Process strings
	 *
	 * @since 0.1.0
	 *
	 * @param array $matches Matches from preg_*
	 * @return string
	 */
	private static function process_string( array $matches ) {
		return '<span style="color: #0000FF;">string</span>(<span style="color: #1287DB;">' . esc_html( $matches[ 'length' ] ) . ')</span> <span style="color: #6B6E6E;">' . esc_html( $matches[ 'value' ] ) . '</span>';
	}

	/**
	 * Process arrays
	 *
	 * @since 0.1.0
	 *
	 * @param array $matches Matches from preg_*
	 * @return string
	 */
	private static function process_array( array $matches ) {

		// prepare the key name
		$key   = '<span style="color: #008000;">"' . esc_html( $matches[ 'key' ] ) . '"</span>';
		$class = '';
		$scope = '';

		// prepare the parent class name
		if ( isset( $matches[ 'class' ] ) && !empty( $matches[ 'class' ] ) ) {
			$class = ':<span style="color: #4D5D94;">"' .  esc_html( $matches[ 'class' ] ) . '"</span>';
		}

		// prepare the scope indicator
		if ( isset( $matches[ 'scope' ] ) && !empty( $matches[ 'scope' ] ) ) {
			$scope = ':<span style="color: #666666;">' .  esc_html( $matches[ 'scope' ] ) . '</span>';
		}

		// return the final string
		return '[' . $key . $class . $scope . ']=>';
	}

	/**
	 * Process countables
	 *
	 * @since 0.1.0
	 *
	 * @param array $matches Matches from preg_*
	 *
	 * @return string
	 */
	private static function process_countable( array $matches ) {
		$type  = '<span style="color: #0000FF;">' .  esc_html( $matches[ 'type' ] ) . '</span>';
		$count = '(<span style="color: #1287DB;">' .  esc_html( $matches[ 'count' ] ) . '</span>)';

		return $type . $count;
	}

	/**
	 * Process boolean values
	 *
	 * @since 0.1.0
	 *
	 * @param array $matches Matches from preg_*
	 * @return string
	 */
	private static function process_bool( array $matches ) {
		return '<span style="color: #0000FF;">bool</span>(<span style="color: #0000FF;">' .  esc_html( $matches[ 'value' ] ) . '</span>)';
	}

	/**
	 * Process floats
	 *
	 * @since 0.1.0
	 *
	 * @param array $matches Matches from preg_*
	 * @return string
	 */
	private static function process_float( array $matches ) {
		return '<span style="color: #0000FF;">float</span>(<span style="color: #1287DB;">' .  esc_html( $matches[ 'value' ] ) . '</span>)';
	}

	/**
	 * Process resources
	 *
	 * @since 0.1.0
	 *
	 * @param array $matches Matches from preg_*
	 * @return string
	 */
	private static function process_resource( array $matches ) {
		return '<span style="color: #0000FF;">resource</span>(<span style="color: #1287DB;">' .  esc_html( $matches[ 'count' ] ) . '</span>) of type (<span style="color: #4D5D94;">' . esc_html( $matches[ 'class' ] ) . '</span>)';
	}

	/**
	 * Process objects
	 *
	 * @since 0.1.0
	 *
	 * @param array $matches Matches from preg_*
	 * @return string
	 */
	private static function process_object( array $matches ) {
		return '<span style="color: #0000FF;">object</span>(<span style="color: #4D5D94;">' .  esc_html( $matches[ 'class' ] ) . '</span>)#' . esc_html( $matches[ 'id' ] ) . ' (<span style="color: #1287DB;">' . esc_html( $matches[ 'count' ] ) . '</span>)';
	}
}
