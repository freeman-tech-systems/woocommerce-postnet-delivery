<?php
/**
 * Autoloader registration.
 *
 * @package Woocommerce_Postnet_Delivery
 */

namespace Woocommerce_Postnet_Delivery;

/**
 * Locates the path to a requested class name.
 *
 * @param string $class_name The class name to locate.
 *
 * @return void
 */
function locate_class_file( string $class_name ): void {

	if ( false === strpos( $class_name, __NAMESPACE__ ) ) {
		return;
	}
	$path  = str_replace( __NAMESPACE__, WCPD_PATH . 'classes', $class_name );
	$path  = str_replace( '\\', DIRECTORY_SEPARATOR, strtolower( $path ) );
	$path  = str_replace( '_', '-', $path );
	$parts = explode( DIRECTORY_SEPARATOR, $path );
	$file  = array_pop( $parts ) . '.php';
	$path  = implode( DIRECTORY_SEPARATOR, $parts );
	$types = [
		'class',
		'trait',
		'interface',
	];

	foreach ( $types as $type ) {
		$type_file = 'class' === $type ? $type . '-' . $file : $type . '/' . $type . '-' . $file;
		$test_file = $path . DIRECTORY_SEPARATOR . $type_file;
		if ( file_exists( $test_file ) && is_readable( $test_file ) ) {
			include_once $test_file;
			break;
		}
	}
}

// Register Autoloader.
spl_autoload_register( 'Woocommerce_Postnet_Delivery\locate_class_file' );
