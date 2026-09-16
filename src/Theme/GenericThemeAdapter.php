<?php

/**
 * Generic / fallback theme adapter.
 *
 * Used when no specific adapter exists for the active theme.
 * CSS variables use the WordPress Global Styles preset variables first
 * (available in WP 5.9+ block themes) so the plugin automatically picks up
 * the site's colour palette.  Classic themes that do not expose these vars
 * fall through to the hardcoded defaults.
 *
 * All theme-specific adapters extend this class and override only what differs.
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs\Theme;

defined( 'ABSPATH' ) || exit;

class GenericThemeAdapter implements ThemeAdapterInterface {

	/**
	 * No theme-specific container class in the generic case.
	 * The plugin relies on its own max-width container.
	 */
	public function getContainerClass(): string {
		return '';
	}

	/**
	 * Use WordPress's own button block class so the plugin's buttons
	 * automatically inherit block-theme button styles (padding, radius, etc.).
	 */
	public function getButtonClass(): string {
		return 'wp-block-button__link';
	}

	/**
	 * CSS custom properties for the plugin's root scope (.hospitaliti-jobs).
	 *
	 * The var() chains try (in order):
	 *   1. WordPress Global Styles preset variables  (block themes, WP 5.9+)
	 *   2. A safe hardcoded default
	 *
	 * This means the plugin already looks "native" on any block theme that
	 * exposes a colour palette via theme.json without any adapter at all.
	 */
	public function getCssVariables(): array {
		return [
			// Primary / brand colour — buttons, links, active pills.
			// Default: #523d3f (Francescana Family brownish-maroon).
			// Overridable via Settings → Hospitaliti Jobs → Primary Color.
			'--job-primary'    => 'var(--wp--preset--color--primary, #523d3f)',

			// Text colour on top of the primary (button labels, pill text).
			'--job-on-primary' => 'var(--wp--preset--color--base, #ffffff)',

			// Page / card background.
			'--job-bg'         => 'var(--wp--preset--color--base, #ffffff)',

			// Body text.
			'--job-text'       => 'var(--wp--preset--color--contrast, #111827)',

			// Muted / secondary text (meta, dates, candidate count).
			'--job-text-muted' => '#6b7280',

			// Card and input borders.
			'--job-border'     => '#e5e7eb',

			// Border radius — inputs, cards, buttons.
			'--job-radius'     => 'var(--wp--custom--border-radius, 8px)',

			// Font family — inherit from the theme automatically.
			'--job-font'       => 'inherit',

			// Section header background (dark band with the listing title).
			'--job-header-bg'  => 'var(--wp--preset--color--contrast, #111111)',

			// Text on the section header.
			'--job-header-text' => 'var(--wp--preset--color--base, #ffffff)',
		];
	}
}
