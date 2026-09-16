<?php

/**
 * PSR-4-style autoloader for the Hospitaliti\Jobs namespace.
 *
 * Maps  Hospitaliti\Jobs\Foo\Bar  →  {plugin_dir}/src/Foo/Bar.php
 *
 * Registered via spl_autoload_register() in the main plugin file before any
 * class in this namespace is referenced.
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs;

defined( 'ABSPATH' ) || exit;

class Autoloader {

	private const PREFIX = 'Hospitaliti\\Jobs\\';

	/**
	 * Register this autoloader with the SPL stack.
	 */
	public static function register(): void {
		spl_autoload_register( [ static::class, 'load' ] );
	}

	/**
	 * Attempt to load a class belonging to our namespace.
	 *
	 * @param string $class Fully-qualified class name.
	 */
	public static function load( string $class ): void {
		if ( strpos( $class, self::PREFIX ) !== 0 ) {
			return; // Not our namespace.
		}

		$relative = substr( $class, strlen( self::PREFIX ) );
		$file     = HOSPITALITI_JOBS_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_file( $file ) ) {
			require $file;
		}
	}
}
