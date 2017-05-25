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

		// var_dump is disabled
		$disabled = (array) explode( ',', ini_get( 'disable_functions' ) );
		$can_dump = ! in_array( 'var_dump', $disabled, true );

		// No dumping, so use plain serialized output
		$format = ( true === $can_dump )
			? ! ( ini_get( 'xdebug.overload_var_dump' ) && ini_get( 'html_errors' ) )
			: false;

		// Always trim each variable item of excess whitespace
		$variable = map_deep( $variable, array( __CLASS__, 'maybe_trim' ) );

		// Already using pretty var_dump()
		if ( true === $format ) {
			$variable = map_deep( $variable, array( __CLASS__, 'maybe_escape' ) );
		}

		// Format output
		$output = ( true === $can_dump )
			? self::format( $variable, $format )
			: esc_html( $variable );

		// Leave unescaped
		echo "<pre>{$output}</pre>";
	}

	/**
	 * Internally format the variable being dumped.
	 *
	 * @since 0.1.0
	 *
	 * @param string  $var
	 * @param boolean $format
	 *
	 * @return string
	 */
	private static function format( $var = '', $format = false ) {

		// Start the output buffering
		ob_start();

		// Generate the output
		var_dump( $var );

		// Get var_dump output from the buffer
		$buffer = ob_get_clean();

		// No new lines after array/object items
		$output = str_replace( "=>\n", '=>', $buffer );

		// No stray whitespace before type castings
		$output = preg_replace( '/=>\s+/', '=> ', $output );

		// Handle formatting on our own
		if ( true === $format ) {

			// Regex to wrap words with HTML
			$maps = array(
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
				$output = preg_replace_callback( $pattern, array( __CLASS__, 'process_' . $function ), $output );
			}
		}

		return $output;
	}

	/**
	 * Maybe trim spaces off of a value.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public static function maybe_trim( $value ) {
		return is_string( $value )
			? trim( $value )
			: $value;
	}

	/**
	 * Maybe escape a value.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public static function maybe_escape( $value ) {
		return is_int( $value )
			? (int) $value
			: esc_html( $value );
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

		// Lengthy string protection
		if ( strlen( $matches[ 'value' ] ) > 10000 ) {
			$matches[ 'value' ] = 'Too Long';
		}

		$length = '<span style="color: #AA0000;">string</span>(<span style="color: #1287DB;">' . esc_html( $matches[ 'length' ] ) . '</span>)';
		$value  = '<span style="color: #6B6EBE;">' . esc_html( $matches[ 'value' ] ) . '</span>';
		return $length . ' ' . $value;
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

		// Prepare the parent class name
		if ( ! empty( $matches[ 'class' ] ) ) {
			$class = ':<span style="color: #4D5D94;">"' . esc_html( $matches[ 'class' ] ) . '"</span>';
		}

		// Prepare the scope indicator
		if ( ! empty( $matches[ 'scope' ] ) ) {
			$scope = ':<span style="color: #666666;">' . esc_html( $matches[ 'scope' ] ) . '</span>';
		}

		// return the final string
		return '[' . $key . $class . $scope . '] ' . esc_html( '=>' );
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
		$type  = '<span style="color: #AA0000;">' . esc_html( $matches[ 'type' ] ) . '</span>';
		$count = '(<span style="color: #1287DB;">' . esc_html( $matches[ 'count' ] ) . '</span>)';

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
		return '<span style="color: #AA0000;">bool</span>(<span style="color: #AA0000;">' . esc_html( $matches[ 'value' ] ) . '</span>)';
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
		return '<span style="color: #AA0000;">float</span>(<span style="color: #1287DB;">' . esc_html( $matches[ 'value' ] ) . '</span>)';
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
		$count = '<span style="color: #AA0000;">resource</span>(<span style="color: #1287DB;">' . esc_html( $matches[ 'count' ] ) . '</span>)';
		$type  = ' of type (<span style="color: #4D5D94;">' . esc_html( $matches[ 'class' ] ) . '</span>)';
		return $count . $type;
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
		$class = '<span style="color: #AA0000;">object</span>(<span style="color: #4D5D94;">' . esc_html( $matches[ 'class' ] ) . '</span>)#' . esc_html( $matches[ 'id' ] );
		$count = '(<span style="color: #1287DB;">' . esc_html( $matches[ 'count' ] ) . '</span>)';
		return $class . $count;
	}
}
