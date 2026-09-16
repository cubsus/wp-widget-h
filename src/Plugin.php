<?php

/**
 * Main plugin orchestrator — singleton bootstrap.
 *
 * Instantiates all services, wires WordPress hooks, and handles the virtual
 * job-detail page template redirect.  Business logic lives in the service
 * classes; this class only wires things together.
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs;

use Hospitaliti\Jobs\Admin\SettingsPage;
use Hospitaliti\Jobs\Ajax\LoadMoreAjax;
use Hospitaliti\Jobs\Api\JobApiClient;
use Hospitaliti\Jobs\Job\JobMapper;
use Hospitaliti\Jobs\Job\JobRepository;
use Hospitaliti\Jobs\Job\JobService;
use Hospitaliti\Jobs\Shortcode\JobsShortcode;
use Hospitaliti\Jobs\Shortcode\JobsBubblesShortcode;
use Hospitaliti\Jobs\Theme\ThemeAdapterInterface;
use Hospitaliti\Jobs\Theme\ThemeDetector;
use Hospitaliti\Jobs\UI\AssetManager;
use Hospitaliti\Jobs\Widget\JobsWidget;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** @var self|null */
	private static ?self $instance = null;

	// ── Shared service instances ─────────────────────────────────────────────
	private JobService            $service;
	private AssetManager          $assetManager;
	private ThemeAdapterInterface $themeAdapter;

	// ── Singleton ────────────────────────────────────────────────────────────

	public static function getInstance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __clone() {}

	private function __construct() {
		$this->bootServices();
		$this->registerHooks();
	}

	// ── Lifecycle ────────────────────────────────────────────────────────────

	/**
	 * Called on plugin activation.
	 *
	 * Seeds all default option values (add_option is a no-op when the key
	 * already exists, so existing installs are never overwritten).
	 *
	 * Migration: per-widget colour options are seeded from the old global
	 * values so existing installs keep their brand colour after the upgrade.
	 */
	public static function onActivate(): void {
		// ── Global / API options ──────────────────────────────────────────
		add_option( 'hospitaliti_jobs_api_url',        HOSPITALITI_JOBS_DEFAULT_API_URL );
		add_option( 'hospitaliti_jobs_cache_duration', 30 );
		add_option( 'hospitaliti_jobs_sslverify',      0 );
		add_option( 'hospitaliti_company_encid',       '' );
		add_option( 'hospitaliti_careers_page_id',     0 );

		// ── Legacy global options (kept as fallback for existing installs) ──
		add_option( 'hospitaliti_widget_title',    'Work For Us' );
		add_option( 'hospitaliti_theme',           'default' );
		add_option( 'hospitaliti_inherit_theme',   0 );
		add_option( 'hospitaliti_primary_color',   '#523d3f' );
		add_option( 'hospitaliti_accent_color',    '' );
		add_option( 'hospitaliti_bg_color',        '' );
		add_option( 'hospitaliti_custom_css',      '' );

		// ── Migration: read old global colour as the per-widget default ────
		$oldPrimary = (string) get_option( 'hospitaliti_primary_color', '#523d3f' );
		$oldAccent  = (string) get_option( 'hospitaliti_accent_color',  '' );
		$oldBg      = (string) get_option( 'hospitaliti_bg_color',      '' );
		$oldTheme   = (string) get_option( 'hospitaliti_theme',         'default' );

		// ── Job Listing widget options ─────────────────────────────────────
		add_option( 'hospitaliti_listing_title',         'Work For Us' );
		add_option( 'hospitaliti_listing_per_page',      10 );
		add_option( 'hospitaliti_listing_show_search',   1 );
		add_option( 'hospitaliti_listing_show_filter',   1 );
		add_option( 'hospitaliti_listing_pagination',    'paged' );
		add_option( 'hospitaliti_listing_theme',         $oldTheme ?: 'default' );
		add_option( 'hospitaliti_listing_inherit_theme', 0 );
		add_option( 'hospitaliti_listing_primary_color', $oldPrimary ?: '#523d3f' );
		add_option( 'hospitaliti_listing_accent_color',  $oldAccent );
		add_option( 'hospitaliti_listing_bg_color',      $oldBg );
		add_option( 'hospitaliti_listing_custom_css',    '' );
		add_option( 'hospitaliti_listing_intro_text',    '' );

		// ── Bubble Listing widget options ──────────────────────────────────
		add_option( 'hospitaliti_bubbles_title',       'Work For Us' );
		add_option( 'hospitaliti_bubbles_show_cta',    1 );
		add_option( 'hospitaliti_bubbles_cta_text',    'Submit your application' );
		add_option( 'hospitaliti_bubbles_cta_url',     '' );
		add_option( 'hospitaliti_bubbles_primary_color', $oldPrimary ?: '#523d3f' );
		add_option( 'hospitaliti_bubbles_bg_color',    '#f0ede8' );
		// Migration: consolidate legacy per-slot options into a single array.
		if ( false !== get_option( 'hospitaliti_bubbles_color_1' ) ) {
			$_migrated = [];
			for ( $i = 1; $i <= 6; $i++ ) {
				$_c = get_option( "hospitaliti_bubbles_color_{$i}", '' );
				if ( $_c ) { $_migrated[] = $_c; }
				delete_option( "hospitaliti_bubbles_color_{$i}" );
			}
			if ( $_migrated ) { update_option( 'hospitaliti_bubbles_colors', $_migrated ); }
		}
		add_option( 'hospitaliti_bubbles_colors', [ '#7a8c5a', '#b8a898', '#4a7060', '#8b4040', '#6b8060', '#9a7850' ] );
		add_option( 'hospitaliti_bubbles_back_url',    '' );
		add_option( 'hospitaliti_bubbles_custom_css',  '' );

		// ── Job Detail Page options ────────────────────────────────────────
		add_option( 'hospitaliti_detail_theme',         $oldTheme ?: 'default' );
		add_option( 'hospitaliti_detail_primary_color', $oldPrimary ?: '#523d3f' );
		add_option( 'hospitaliti_detail_accent_color',  $oldAccent );
		add_option( 'hospitaliti_detail_bg_color',      $oldBg );
		add_option( 'hospitaliti_detail_inherit_theme', 0 );
		add_option( 'hospitaliti_detail_back_url',      '' ); // empty = use careers page URL
		add_option( 'hospitaliti_detail_custom_css',    '' );

		self::registerRewriteRules();
		flush_rewrite_rules();
	}

	/**
	 * Called on plugin deactivation.
	 */
	public static function onDeactivate(): void {
		JobRepository::flushAll();
		flush_rewrite_rules();
	}

	// ── Public accessors (for Widget, Shortcode, etc.) ───────────────────────

	public function getService(): JobService {
		return $this->service;
	}

	public function getAssetManager(): AssetManager {
		return $this->assetManager;
	}

	/**
	 * Resolve the theme for a specific widget context.
	 *
	 * @param  string $context  'listing' | 'bubbles' | 'form' | 'detail' | '' (legacy global)
	 * @return string           'default' | 'minimal'
	 */
	public function resolveTheme( string $context = '' ): string {
		// Per-widget option key map.
		$key = match( $context ) {
			'listing' => 'hospitaliti_listing_theme',
			'detail'  => 'hospitaliti_detail_theme',
			default   => 'hospitaliti_theme', // legacy global fallback
		};
		$theme = get_option( $key, 'default' );
		return in_array( $theme, [ 'default', 'minimal' ], true ) ? $theme : 'default';
	}

	// ── Boot ─────────────────────────────────────────────────────────────────

	/**
	 * Instantiate and wire the service graph.
	 */
	private function bootServices(): void {
		$apiUrl    = get_option( 'hospitaliti_jobs_api_url', HOSPITALITI_JOBS_DEFAULT_API_URL );
		$sslVerify = (bool) get_option( 'hospitaliti_jobs_sslverify', 0 );
		$cacheTtl  = (int) get_option( 'hospitaliti_jobs_cache_duration', 30 );

		$apiClient  = new JobApiClient( $apiUrl, $sslVerify );
		$repo       = new JobRepository( $apiClient, $cacheTtl );
		$mapper     = new JobMapper();

		$this->service      = new JobService( $repo, $mapper );
		$this->themeAdapter = ThemeDetector::detect();
		$this->assetManager = new AssetManager( $this->themeAdapter );
	}

	/**
	 * Wire all subsystems to their WordPress hooks.
	 */
	private function registerHooks(): void {
		add_action( 'init',              [ $this, 'init' ] );
		add_filter( 'query_vars',        [ $this, 'addQueryVars' ] );
		add_action( 'template_redirect', [ $this, 'handleJobDetail' ] );
		add_action( 'widgets_init',      [ $this, 'registerWidget' ] );

		$shortcode = new JobsShortcode( $this->service, $this->assetManager );
		$shortcode->register();

		( new JobsBubblesShortcode( $this->service, $this->assetManager ) )->register();

		( new LoadMoreAjax( $this->service ) )->register();

		( new SettingsPage() )->register();

		add_action( 'wp_head', [ $this, 'outputCustomCss' ] );
	}

	// ── WordPress integration hooks ───────────────────────────────────────────

	public function init(): void {
		load_plugin_textdomain(
			'hospitaliti-jobs',
			false,
			dirname( plugin_basename( HOSPITALITI_JOBS_PLUGIN_DIR . 'hospitaliti-jobs-widget.php' ) ) . '/languages'
		);
		self::registerRewriteRules();
	}

	/**
	 * @param  array $vars
	 * @return array
	 */
	public function addQueryVars( array $vars ): array {
		$vars[] = 'hospitaliti_job';
		return $vars;
	}

	public function registerWidget(): void {
		register_widget( JobsWidget::class );
	}

	/**
	 * Output legacy global custom CSS in <head>.
	 *
	 * Per-widget custom CSS (hospitaliti_{listing|bubbles|form|detail}_custom_css)
	 * is now attached directly via wp_add_inline_style() in AssetManager, so it
	 * outputs immediately after the widget's <link> tag regardless of timing.
	 * This method only handles the legacy global option for backward compatibility.
	 */
	public function outputCustomCss(): void {
		$globalCss = trim( wp_strip_all_tags( (string) get_option( 'hospitaliti_custom_css', '' ) ) );
		if ( $globalCss === '' ) {
			return;
		}

		if (
			wp_style_is( 'hospitaliti-jobs',    'enqueued' ) ||
			wp_style_is( 'hospitaliti-careers', 'enqueued' ) ||
			wp_style_is( 'hospitaliti-jobs-bubbles', 'enqueued' )
		) {
			echo '<style id="hospitaliti-custom-css-global">' . $globalCss . '</style>' . "\n";
		}
	}

	// ── Rewrite rules ─────────────────────────────────────────────────────────

	/**
	 * Previously registered the rewrite rule for /{careers-slug}/{job-slug}/ virtual pages.
	 *
	 * Job detail pages are now served at /{job-slug}/ directly — detected via
	 * template_redirect on WordPress 404 responses.  No rewrite rule is needed.
	 * The method is kept (called on activation/init) so existing flush triggers
	 * continue to work; calling it simply clears the old rule from the DB after
	 * the next flush_rewrite_rules().
	 */
	public static function registerRewriteRules(): void {
		// No rule needed — URL-based detection in handleJobDetail() handles routing.
	}

	// ── Job detail virtual page ───────────────────────────────────────────────

	/**
	 * Serve job detail pages at /{job-slug}/ without a prefix slug.
	 *
	 * Detection strategy:
	 *   Legacy  — query var `hospitaliti_job` set by the old rewrite rule
	 *             (/{careers-slug}/{job-slug}/).  Kept so existing links keep
	 *             working until the user flushes rewrite rules.
	 *   New     — WordPress returns a 404 for an unknown single-segment path;
	 *             we intercept that and try to load it as a job before WP
	 *             renders the 404 template.  Existing WP pages/posts are never
	 *             affected because they produce a 200, not a 404.
	 */
	public function handleJobDetail(): void {
		// ── Detect the job slug ───────────────────────────────────────────────
		// Legacy: query var set by the old /{careers-slug}/{job-slug}/ rewrite rule.
		$isLegacy = (bool) get_query_var( 'hospitaliti_job' );
		$slug      = (string) get_query_var( 'hospitaliti_job' );

		// New: single-segment URL — only intercept when WordPress itself is 404.
		if ( ! $slug && is_404() ) {
			$requestPath = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
			$wpBasePath  = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
			$localPath   = '/' . ltrim( substr( $requestPath, strlen( $wpBasePath ) ), '/' );
			$segments    = array_values( array_filter( explode( '/', trim( $localPath, '/' ) ) ) );

			if ( count( $segments ) === 1 ) {
				$candidate = rawurldecode( $segments[0] );
				// Skip anything that looks like a static file (has a dot in the name).
				if ( ! str_contains( $candidate, '.' ) ) {
					$slug = $candidate;
				}
			}
		}

		if ( ! $slug ) {
			return;
		}

		$careersBase = $this->service->getCareersBase();

		// ── Resolve the "Back to Careers" URL ─────────────────────────────────
		// Priority (highest wins):
		//   1. ?hj_back= query parameter  — set by the Bubble Listing when
		//      hospitaliti_bubbles_back_url is configured; lets each listing
		//      surface provide its own "back" destination dynamically.
		//   2. hospitaliti_detail_back_url — admin-configured static override
		//      (Job Listing settings tab).
		//   3. Auto-detected careers page  — permalink of the page set as the
		//      Careers Base Page in API & Connection settings.

		// 1. From bubble link query param (validated to same host for security).
		$hj_back = '';
		if ( ! empty( $_GET['hj_back'] ) ) {
			$candidate = rawurldecode( sanitize_url( wp_unslash( $_GET['hj_back'] ) ) );
			// Only allow URLs on the same host as the WordPress site.
			if ( wp_validate_redirect( $candidate, '' ) ) {
				$hj_back = $candidate;
			}
		}

		// 2. Admin static override.
		$customBackUrl = esc_url_raw( (string) get_option( 'hospitaliti_detail_back_url', '' ) );

		// 3. Auto-detected.
		$careers_url = $hj_back ?: ( $customBackUrl ?: $this->service->getCareersUrl() );

		$job = $this->service->getJob( $slug );

		if ( is_wp_error( $job ) || empty( $job ) ) {
			if ( $isLegacy ) {
				// Old URL scheme: redirect to careers listing as before.
				wp_safe_redirect( $careers_url, 302 );
				exit;
			}
			// New URL scheme: WordPress already prepared a 404 — just let it render.
			return;
		}

		// Filter document title.
		$jobTitle = $job->title ?? '';
		$orgName  = $job->organization['name'] ?? '';

		add_filter( 'document_title_parts', static function ( array $parts ) use ( $jobTitle, $orgName ): array {
			$parts['title'] = $jobTitle;
			if ( $orgName ) {
				$parts['site'] = $orgName;
			}
			return $parts;
		} );

		$this->assetManager->enqueueDetailAssets();

		$theme        = $this->resolveTheme( 'detail' );
		$api_base_url = get_option( 'hospitaliti_jobs_api_url', HOSPITALITI_JOBS_DEFAULT_API_URL );
		$careers_base = $careersBase;
		$apply_link   = $job->apply_link ?? null;

		include HOSPITALITI_JOBS_PLUGIN_DIR . 'templates/job-detail.php';
		exit;
	}
}
