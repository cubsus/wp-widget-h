<?php

/**
 * Theme adapter for Kadence (free + Pro).
 *
 * Kadence registers its palette as --global-palette{1…9}.
 * Palette roles (Kadence default):
 *   1 = Brand / Accent primary
 *   2 = Brand accent 2
 *   3 = Headings
 *   4 = Body text
 *   5 = Muted text
 *   6 = Subtle border / divider
 *   7 = Body background
 *   8 = Off-white surface
 *   9 = Header bg
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs\Theme;

defined( 'ABSPATH' ) || exit;

class KadenceAdapter extends GenericThemeAdapter {

	public function getContainerClass(): string {
		return 'container-inner';
	}

	public function getButtonClass(): string {
		// Kadence uses .kadence-button or inherits from .wp-block-button__link.
		return 'wp-block-button__link';
	}

	public function getCssVariables(): array {
		return array_merge( parent::getCssVariables(), [
			'--job-primary'    => 'var(--global-palette1, var(--wp--preset--color--primary, #3182ce))',
			'--job-on-primary' => '#ffffff',
			'--job-bg'         => 'var(--global-palette7, var(--wp--preset--color--base, #ffffff))',
			'--job-text'       => 'var(--global-palette4, var(--wp--preset--color--contrast, #1a202c))',
			'--job-text-muted' => 'var(--global-palette5, #6b7280)',
			'--job-border'     => 'var(--global-palette6, #e2e8f0)',
			'--job-radius'     => 'var(--global-radius, var(--wp--custom--border-radius, 4px))',
			'--job-font'       => 'var(--global-body-font-family, inherit)',
			'--job-header-bg'  => 'var(--global-palette9, var(--wp--preset--color--contrast, #111111))',
			'--job-header-text' => '#ffffff',
		] );
	}
}
