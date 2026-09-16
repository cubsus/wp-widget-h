<?php

/**
 * ThemeDetector — selects the correct adapter for the active WordPress theme.
 *
 * Detection order:
 *   1. Elementor (page builder, checked via constant — takes priority because
 *      Elementor overrides every theme's colours with its own global palette).
 *   2. Child theme stylesheet slug.
 *   3. Parent theme template slug.
 *   4. GenericThemeAdapter fallback.
 *
 * To add support for a new theme, register its stylesheet slug in $map below
 * and create a matching adapter class in the Theme\ namespace.
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs\Theme;

defined( 'ABSPATH' ) || exit;

class ThemeDetector {

	/**
	 * Map of theme stylesheet / template slugs to adapter class names.
	 *
	 * Both the child-theme stylesheet and the parent-theme template slug are
	 * checked, so a child theme of Astra still gets the Astra adapter.
	 *
	 * @var array<string,string>
	 */
	private static array $map = [
		// Astra (free + Pro).
		'astra'              => AstraAdapter::class,

		// Kadence (free + Pro).
		'kadence'            => KadenceAdapter::class,
		'kadence-child'      => KadenceAdapter::class,

		// GeneratePress (free + Premium).
		'generatepress'      => GeneratePressAdapter::class,
		'generatepress-child' => GeneratePressAdapter::class,
	];

	/**
	 * Return the best adapter for the currently active theme + page builder.
	 *
	 * Called once per request inside Plugin::__construct().
	 */
	public static function detect(): ThemeAdapterInterface {
		// 1. Elementor check (page builder overrides theme colours).
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			return new ElementorAdapter();
		}

		// 2. Theme slug detection.
		$theme      = wp_get_theme();
		$stylesheet = $theme->get_stylesheet(); // Child-theme slug.
		$template   = $theme->get_template();   // Parent-theme slug.

		foreach ( [ $stylesheet, $template ] as $slug ) {
			if ( isset( self::$map[ $slug ] ) ) {
				$class = self::$map[ $slug ];
				return new $class();
			}
		}

		// 3. Generic fallback (tries WordPress Global Styles vars).
		return new GenericThemeAdapter();
	}

	/**
	 * Return the name of the active theme (stylesheet) — useful for debugging
	 * and for the admin settings page to show which adapter is active.
	 */
	public static function getActiveThemeSlug(): string {
		return wp_get_theme()->get_stylesheet();
	}
}
