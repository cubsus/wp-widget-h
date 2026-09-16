<?php

/**
 * AssetManager — enqueues plugin CSS/JS and injects per-widget CSS variables.
 *
 * Each widget reads its own option group so colours, themes, and custom CSS
 * are independent:
 *
 *   listing  →  hospitaliti_listing_*    ([hospitaliti_jobs] + sidebar widget)
 *   bubbles  →  hospitaliti_bubbles_*    ([hospitaliti_jobs_bubbles])
 *   detail   →  hospitaliti_detail_*     (virtual /careers/{slug}/ page)
 *
 * Fallback chain: per-widget option → old global option → adapter default.
 * This ensures existing installs continue working after the option split.
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs\UI;

use Hospitaliti\Jobs\Theme\ThemeAdapterInterface;

defined( 'ABSPATH' ) || exit;

class AssetManager {

	private ThemeAdapterInterface $adapter;

	public function __construct( ThemeAdapterInterface $adapter ) {
		$this->adapter = $adapter;
	}

	// ── Job Listing assets ───────────────────────────────────────────────

	public function enqueueListingAssets(): void {
		if ( ! wp_style_is( 'hospitaliti-jobs', 'enqueued' ) ) {
			wp_enqueue_style(
				'hospitaliti-jobs',
				HOSPITALITI_JOBS_PLUGIN_URL . 'assets/css/hospitaliti-jobs.css',
				[],
				HOSPITALITI_JOBS_VERSION
			);
			wp_add_inline_style( 'hospitaliti-jobs', $this->buildListingCss() );
			// Attach custom CSS directly to the handle so it outputs regardless
			// of when this method is called relative to wp_head.
			$this->attachCustomCss( 'hospitaliti-jobs', 'hospitaliti_listing_custom_css' );
		}

		if ( ! wp_script_is( 'hospitaliti-jobs', 'enqueued' ) ) {
			wp_enqueue_script(
				'hospitaliti-jobs',
				HOSPITALITI_JOBS_PLUGIN_URL . 'assets/js/hospitaliti-jobs.js',
				[ 'jquery' ],
				HOSPITALITI_JOBS_VERSION,
				true
			);
			wp_localize_script( 'hospitaliti-jobs', 'hospitaliti_ajax', $this->listingScriptData() );
		}
	}

	// ── Bubble Listing assets ────────────────────────────────────────────

	public function enqueueBubbleListing(): void {
		if ( ! wp_style_is( 'hospitaliti-jobs-bubbles', 'enqueued' ) ) {
			wp_enqueue_style(
				'hospitaliti-jobs-bubbles',
				HOSPITALITI_JOBS_PLUGIN_URL . 'assets/css/hospitaliti-jobs-bubbles.css',
				[],
				HOSPITALITI_JOBS_VERSION
			);
			wp_add_inline_style( 'hospitaliti-jobs-bubbles', $this->buildBubbleCss() );
			$this->attachCustomCss( 'hospitaliti-jobs-bubbles', 'hospitaliti_bubbles_custom_css' );
		}

		if ( ! wp_script_is( 'hospitaliti-jobs-bubbles', 'enqueued' ) ) {
			wp_enqueue_script(
				'hospitaliti-jobs-bubbles',
				HOSPITALITI_JOBS_PLUGIN_URL . 'assets/js/hospitaliti-jobs-bubbles.js',
				[],
				HOSPITALITI_JOBS_VERSION,
				true
			);
		}
	}

	// ── Job Detail assets ────────────────────────────────────────────────

	public function enqueueDetailAssets(): void {
		if ( ! wp_style_is( 'hospitaliti-careers', 'enqueued' ) ) {
			wp_enqueue_style(
				'hospitaliti-careers',
				HOSPITALITI_JOBS_PLUGIN_URL . 'assets/css/hospitaliti-careers.css',
				[],
				HOSPITALITI_JOBS_VERSION
			);
			wp_add_inline_style( 'hospitaliti-careers', $this->buildDetailCss() );
			$this->attachCustomCss( 'hospitaliti-careers', 'hospitaliti_detail_custom_css' );
		}

		if ( ! wp_script_is( 'hospitaliti-careers', 'enqueued' ) ) {
			wp_enqueue_script(
				'hospitaliti-careers',
				HOSPITALITI_JOBS_PLUGIN_URL . 'assets/js/hospitaliti-careers.js',
				[ 'jquery' ],
				HOSPITALITI_JOBS_VERSION,
				true
			);
			wp_localize_script( 'hospitaliti-careers', 'hospitaliti_careers', $this->detailScriptData() );
		}
	}

	// ── CSS builders ─────────────────────────────────────────────────────

	private function buildListingCss(): string {
		$vars = $this->resolvedVars( 'listing' );

		$css  = ':root {';
		$css .= '--hj-primary: ' . $vars['--job-primary']    . '; ';
		$css .= '--hj-bg: '      . $vars['--job-header-bg']  . '; ';
		$css .= '--hj-accent: '  . $vars['--job-on-primary'] . '; ';
		$css .= '}';

		$css .= ' .hospitaliti-jobs-list, .hospitaliti-jobs-header {';
		foreach ( $vars as $prop => $value ) {
			$css .= esc_attr( $prop ) . ': ' . $value . '; ';
		}
		$css .= 'font-family: var(--job-font, inherit); }';

		return $css;
	}

	private function buildBubbleCss(): string {
		$vars = $this->resolvedVars( 'bubbles' );

		// Primary colour — used for active badges and the CTA button.
		$primary   = $vars['--job-primary']    ?? '#523d3f';
		$onPrimary = $vars['--job-on-primary'] ?? '#ffffff';

		// Section background — read directly from the per-widget option.
		// Falls back to the old global bg option, then to the earthy default.
		$sectionBg = sanitize_hex_color( (string) get_option( 'hospitaliti_bubbles_bg_color', '' ) )
			?: sanitize_hex_color( (string) get_option( 'hospitaliti_bg_color', '' ) )
			?: '#f0ede8';

		$css  = '.hj-bubbles-section {';
		$css .= '--hjb-primary: '       . $primary   . '; ';
		$css .= '--hjb-bg: '            . $sectionBg . '; ';
		$css .= '--hjb-heading-color: ' . $primary   . '; '; // heading/label text uses primary brand color
		$css .= '--hjb-cta-bg: '        . $primary   . '; '; // CTA uses primary, not section bg
		$css .= '--hjb-cta-color: '     . $onPrimary . '; ';
		$css .= '--hjb-badge-active: '  . $primary   . '; '; // Active badge uses primary
		// Set background-color directly so theme overrides cannot win via
		// CSS variable inheritance order issues.
		$css .= 'background-color: '    . $sectionBg . '; ';
		$css .= 'font-family: var(--job-font, inherit); ';
		$css .= '}';

		return $css;
	}

	private function buildDetailCss(): string {
		$vars = $this->resolvedVars( 'detail' );

		$css  = ':root {';
		$css .= '--hc-primary: ' . $vars['--job-primary']    . '; ';
		$css .= '--hc-accent: '  . $vars['--job-on-primary'] . '; ';
		$css .= '--hc-bg: '      . $vars['--job-header-bg']  . '; ';
		$css .= '}';

		$css .= ' .hospitaliti-job-detail {';
		foreach ( $vars as $prop => $value ) {
			$css .= esc_attr( $prop ) . ': ' . $value . '; ';
		}
		$css .= 'font-family: var(--job-font, inherit); }';

		return $css;
	}

	// ── Custom CSS helper ────────────────────────────────────────────────

	/**
	 * Attach a per-widget custom CSS option value as an inline style block on
	 * the given stylesheet handle.
	 *
	 * Using wp_add_inline_style() instead of wp_head ensures the CSS is output
	 * immediately after the <link> for the handle, regardless of whether this
	 * method is called before or after wp_head has already fired (the latter
	 * happens when a shortcode renders mid-page rather than being pre-detected
	 * by has_shortcode() during wp_enqueue_scripts).
	 *
	 * @param string $handle     WordPress style handle.
	 * @param string $optionKey  Option name containing the raw CSS.
	 */
	private function attachCustomCss( string $handle, string $optionKey ): void {
		$css = trim( wp_strip_all_tags( (string) get_option( $optionKey, '' ) ) );
		if ( $css !== '' ) {
			wp_add_inline_style( $handle, $css );
		}
	}

	// ── Per-widget resolved CSS variables ────────────────────────────────

	/**
	 * Resolve CSS custom properties for a specific widget context.
	 *
	 * Priority (highest wins):
	 *   1. Per-widget option  (hospitaliti_{context}_primary_color, etc.)
	 *   2. Old global option  (hospitaliti_primary_color, etc.)  ← migration fallback
	 *   3. Theme adapter default
	 *
	 * @param  string $context  'listing' | 'bubbles' | 'form' | 'detail'
	 * @return array<string,string>
	 */
	private function resolvedVars( string $context ): array {
		$vars = $this->adapter->getCssVariables();

		// ── Per-widget "inherit theme" toggle ─────────────────────────────
		$inheritKey = match( $context ) {
			'listing' => 'hospitaliti_listing_inherit_theme',
			'detail'  => 'hospitaliti_detail_inherit_theme',
			default   => '', // form/bubbles don't have an inherit toggle
		};
		if ( $inheritKey && (bool) get_option( $inheritKey, false ) ) {
			return $vars; // adapter values only, no admin overrides
		}

		// ── Read per-widget colors (fallback → global → adapter) ──────────
		$primary   = $this->resolveColor( $context, 'primary_color',  '--job-primary',    $vars );
		$accent    = $this->resolveColor( $context, 'accent_color',   '--job-on-primary', $vars );
		$headerBg  = $this->resolveColor( $context, 'bg_color',       '--job-header-bg',  $vars );

		if ( $primary )  { $vars['--job-primary']    = $primary; }
		if ( $accent )   { $vars['--job-on-primary'] = $accent; }
		if ( $headerBg ) { $vars['--job-header-bg']  = $headerBg; }

		return $vars;
	}

	/**
	 * Resolve a single colour value:
	 *   1. Per-widget option (e.g. hospitaliti_listing_primary_color)
	 *   2. Old global option (e.g. hospitaliti_primary_color) — migration fallback
	 *   3. Empty string (caller uses adapter default)
	 *
	 * @param  string $context   'listing' | 'bubbles' | 'form' | 'detail'
	 * @param  string $suffix    Option key suffix (e.g. 'primary_color')
	 * @param  string $varName   CSS custom property name (used for old-global fallback mapping)
	 * @param  array  $vars      Current adapter vars array (unused here, kept for signature symmetry)
	 * @return string            Hex colour or empty string
	 */
	private function resolveColor( string $context, string $suffix, string $varName, array $vars ): string {
		// 1. Per-widget option.
		$perWidget = sanitize_hex_color( (string) get_option( "hospitaliti_{$context}_{$suffix}", '' ) );
		if ( $perWidget ) {
			return $perWidget;
		}

		// 2. Old global option (migration fallback — present on sites upgraded from v2.x).
		$globalMap = [
			'primary_color' => 'hospitaliti_primary_color',
			'accent_color'  => 'hospitaliti_accent_color',
			'bg_color'      => 'hospitaliti_bg_color',
		];
		if ( isset( $globalMap[ $suffix ] ) ) {
			$global = sanitize_hex_color( (string) get_option( $globalMap[ $suffix ], '' ) );
			if ( $global ) {
				return $global;
			}
		}

		return '';
	}

	// ── Script data ──────────────────────────────────────────────────────

	private function listingScriptData(): array {
		return [
			'ajax_url'         => admin_url( 'admin-ajax.php' ),
			'nonce'            => wp_create_nonce( 'hospitaliti_jobs_nonce' ),
			'default_per_page' => HOSPITALITI_JOBS_DEFAULT_PER_PAGE,
			'i18n'             => [
				'loading'   => __( 'Loading…',                                   'hospitaliti-jobs' ),
				'load_more' => __( 'Load More Jobs',                             'hospitaliti-jobs' ),
				'error'     => __( 'Could not load more jobs. Please try again.', 'hospitaliti-jobs' ),
				'no_more'   => __( 'No more jobs to load.',                      'hospitaliti-jobs' ),
			],
		];
	}

	private function detailScriptData(): array {
		return [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'hospitaliti_careers_nonce' ),
			'i18n'     => [
				'loading'  => __( 'Loading…',                                'hospitaliti-jobs' ),
				'load_more' => __( 'Load More Jobs',                         'hospitaliti-jobs' ),
				'no_more'  => __( 'No more jobs.',                           'hospitaliti-jobs' ),
				'error'    => __( 'Something went wrong. Please try again.', 'hospitaliti-jobs' ),
			],
		];
	}
}
