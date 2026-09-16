<?php

/**
 * Contract every theme adapter must fulfil.
 *
 * An adapter answers two questions:
 *   1. Which host-theme CSS classes should the plugin's elements carry?
 *   2. Which CSS custom-property values should be injected so the plugin
 *      automatically picks up the theme's palette, radius, font, etc.?
 *
 * Implementing a new adapter for a theme is as simple as extending
 * GenericThemeAdapter and overriding only what differs.
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs\Theme;

defined( 'ABSPATH' ) || exit;

interface ThemeAdapterInterface {

	/**
	 * CSS class(es) the theme uses for its main content container.
	 * Applied to the plugin's outer wrapper so widths match the site layout.
	 * Return an empty string when the theme does not use a named container.
	 *
	 * @return string e.g. 'container' | 'ast-container' | ''
	 */
	public function getContainerClass(): string;

	/**
	 * CSS class(es) the theme uses for its primary call-to-action button.
	 * Added to the plugin's "Apply" and "Load More" buttons so they inherit
	 * the site's button styles (border-radius, padding, hover effects, etc.).
	 * Return an empty string to let the plugin use its own button styling.
	 *
	 * @return string e.g. 'wp-block-button__link' | 'ast-button' | ''
	 */
	public function getButtonClass(): string;

	/**
	 * CSS custom-property declarations to inject into the plugin's root scope.
	 *
	 * Return a flat associative array of property → value pairs.
	 * Values SHOULD use var() chains so the theme's own custom properties are
	 * tried first, with a sensible generic fallback at the end.
	 *
	 * Example:
	 *   '--job-primary' => 'var(--ast-global-color-0, var(--wp--preset--color--primary, #016a6d))'
	 *
	 * The AssetManager merges these with any admin colour-picker overrides
	 * (admin values win) and injects them as inline CSS.
	 *
	 * @return array<string,string>
	 */
	public function getCssVariables(): array;
}
