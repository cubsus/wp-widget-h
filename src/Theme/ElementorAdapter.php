<?php

/**
 * Theme adapter for Elementor (free + Pro) page-builder sites.
 *
 * Elementor registers its global colours as --e-global-color-{key} and
 * typography as --e-global-typography-{key}-font-family.
 *
 * Default Elementor global colour keys:
 *   primary   = Primary     (CTA blue, #6EC1E4 default)
 *   secondary = Secondary
 *   text      = Text
 *   accent    = Accent
 *
 * This adapter is applied when Elementor is active regardless of the active
 * WordPress theme, so it combines with whatever theme adapter would otherwise
 * be selected.  ThemeDetector gives it priority when ELEMENTOR_VERSION is
 * defined, falling back to the theme adapter for everything else.
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs\Theme;

defined( 'ABSPATH' ) || exit;

class ElementorAdapter extends GenericThemeAdapter {

	public function getContainerClass(): string {
		return 'elementor-container';
	}

	public function getButtonClass(): string {
		return 'elementor-button elementor-button-default';
	}

	public function getCssVariables(): array {
		return array_merge( parent::getCssVariables(), [
			'--job-primary'    => 'var(--e-global-color-primary, var(--wp--preset--color--primary, #6ec1e4))',
			'--job-on-primary' => '#ffffff',
			'--job-bg'         => 'var(--wp--preset--color--base, #ffffff)',
			'--job-text'       => 'var(--e-global-color-text, var(--wp--preset--color--contrast, #7a7a7a))',
			'--job-text-muted' => '#6b7280',
			'--job-border'     => '#e5e7eb',
			'--job-radius'     => 'var(--e-global-border-radius, var(--wp--custom--border-radius, 4px))',
			'--job-font'       => 'var(--e-global-typography-primary-font-family, inherit)',
			'--job-header-bg'  => 'var(--e-global-color-secondary, #54595f)',
			'--job-header-text' => '#ffffff',
		] );
	}
}
