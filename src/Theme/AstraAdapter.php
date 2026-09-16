<?php

/**
 * Theme adapter for Astra (free + Pro).
 *
 * Astra exposes its palette as --ast-global-color-{0…8} and typography via
 * --ast-font-family-body / --ast-font-family-heading.
 * Button styles live on .ast-button.
 *
 * Colour palette indices (Astra default):
 *   0 = Accent / CTA     (#006AEF typical)
 *   1 = Accent hover
 *   2 = Heading
 *   3 = Body text
 *   4 = Muted text
 *   5 = Border / divider
 *   6 = Background
 *   7 = Surface / card
 *   8 = Header / footer bg
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs\Theme;

defined( 'ABSPATH' ) || exit;

class AstraAdapter extends GenericThemeAdapter {

	public function getContainerClass(): string {
		return 'ast-container';
	}

	public function getButtonClass(): string {
		return 'ast-button';
	}

	public function getCssVariables(): array {
		return array_merge( parent::getCssVariables(), [
			'--job-primary'    => 'var(--ast-global-color-0, var(--wp--preset--color--primary, #006aef))',
			'--job-on-primary' => '#ffffff',
			'--job-bg'         => 'var(--ast-global-color-6, var(--wp--preset--color--base, #ffffff))',
			'--job-text'       => 'var(--ast-global-color-3, var(--wp--preset--color--contrast, #3a3a3a))',
			'--job-text-muted' => 'var(--ast-global-color-4, #6b7280)',
			'--job-border'     => 'var(--ast-global-color-5, #e5e7eb)',
			'--job-radius'     => 'var(--ast-border-radius-btn, var(--wp--custom--border-radius, 4px))',
			'--job-font'       => 'var(--ast-font-family-body, inherit)',
			'--job-header-bg'  => 'var(--ast-global-color-8, var(--wp--preset--color--contrast, #111111))',
			'--job-header-text' => '#ffffff',
		] );
	}
}
