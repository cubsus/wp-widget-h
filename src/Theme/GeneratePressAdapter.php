<?php

/**
 * Theme adapter for GeneratePress (free + Premium).
 *
 * GeneratePress Premium uses CSS custom properties in the form:
 *   --contrast, --contrast-2, --accent, --base-2, etc.
 *
 * Free GeneratePress does NOT expose CSS custom properties; the generic
 * WordPress Global Styles fallback handles it acceptably.
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs\Theme;

defined( 'ABSPATH' ) || exit;

class GeneratePressAdapter extends GenericThemeAdapter {

	public function getContainerClass(): string {
		// GP wraps content in .inside-article / .container, but most shortcode
		// contexts already sit inside a GP container, so we return empty.
		return '';
	}

	public function getButtonClass(): string {
		return 'button';
	}

	public function getCssVariables(): array {
		return array_merge( parent::getCssVariables(), [
			// GP Premium exposes these; free GP falls through to WP globals.
			'--job-primary'    => 'var(--accent, var(--wp--preset--color--primary, #1e73be))',
			'--job-on-primary' => '#ffffff',
			'--job-bg'         => 'var(--base-2, var(--wp--preset--color--base, #ffffff))',
			'--job-text'       => 'var(--contrast, var(--wp--preset--color--contrast, #222222))',
			'--job-text-muted' => 'var(--contrast-2, #6b7280)',
			'--job-border'     => 'var(--base-3, #e5e7eb)',
			'--job-radius'     => 'var(--wp--custom--border-radius, 2px)',
			'--job-font'       => 'inherit',
			'--job-header-bg'  => 'var(--contrast, var(--wp--preset--color--contrast, #222222))',
			'--job-header-text' => '#ffffff',
		] );
	}
}
