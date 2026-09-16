<?php

/**
 * Registers and renders the [hospitaliti_jobs] shortcode.
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs\Shortcode;

use Hospitaliti\Jobs\Job\JobService;
use Hospitaliti\Jobs\UI\AssetManager;

defined( 'ABSPATH' ) || exit;

class JobsShortcode {

	private JobService   $service;
	private AssetManager $assets;

	public function __construct( JobService $service, AssetManager $assets ) {
		$this->service = $service;
		$this->assets  = $assets;
	}

	/**
	 * Register hooks — called once from Plugin.
	 */
	public function register(): void {
		add_shortcode( 'hospitaliti_jobs',  [ $this, 'render' ] );
		add_action( 'wp_enqueue_scripts',   [ $this, 'maybeEnqueue' ] );
	}

	// ── Shortcode rendering ──────────────────────────────────────────────────

	/**
	 * Render the [hospitaliti_jobs] shortcode.
	 *
	 * @param  array|string $atts Raw shortcode attributes.
	 * @return string             Buffered HTML.
	 */
	public function render( $atts ): string {
		$defaultPerPage   = max( 1, min( (int) get_option( 'hospitaliti_listing_per_page', HOSPITALITI_JOBS_DEFAULT_PER_PAGE ), 50 ) );
		$defaultSearch    = get_option( 'hospitaliti_listing_show_search', 1 ) ? 'true' : 'false';
		$defaultFilter    = get_option( 'hospitaliti_listing_show_filter', 1 ) ? 'true' : 'false';
		$defaultPagination = get_option( 'hospitaliti_listing_pagination', 'paged' ) === 'paged' ? 'true' : 'false';

		$atts = shortcode_atts(
			[
				'title'           => '',
				'count'           => $defaultPerPage,
				'show_salary'     => 'true',
				'show_type'       => 'true',
				'show_date'       => 'true',
				'show_search'     => $defaultSearch,
				'show_filter'     => $defaultFilter,
				'pagination'      => $defaultPagination,
				'employment_type' => '',
				'search'          => '',
			],
			$atts,
			'hospitaliti_jobs'
		);

		$title           = ! empty( $atts['title'] )
			? sanitize_text_field( $atts['title'] )
			: ( get_option( 'hospitaliti_listing_title' )
				?: get_option( 'hospitaliti_widget_title' ) // legacy fallback
				?: __( 'Work For Us', 'hospitaliti-jobs' ) );
		$perPage         = min( absint( $atts['count'] ), 50 );
		$showSalary      = filter_var( $atts['show_salary'],  FILTER_VALIDATE_BOOLEAN );
		$showType        = filter_var( $atts['show_type'],    FILTER_VALIDATE_BOOLEAN );
		$showDate        = filter_var( $atts['show_date'],    FILTER_VALIDATE_BOOLEAN );
		$showSearch      = filter_var( $atts['show_search'],  FILTER_VALIDATE_BOOLEAN );
		$showFilter      = filter_var( $atts['show_filter'],  FILTER_VALIDATE_BOOLEAN );
		$employmentType  = sanitize_text_field( $atts['employment_type'] );
		$search          = sanitize_text_field( $atts['search'] );
		$paginationMode  = filter_var( $atts['pagination'], FILTER_VALIDATE_BOOLEAN ) ? 'paged' : 'load_more';
		$theme           = $this->resolveTheme();

		$encid  = (string) get_option( 'hospitaliti_company_encid', '' );
		$apiUrl = get_option( 'hospitaliti_jobs_api_url', HOSPITALITI_JOBS_DEFAULT_API_URL );
		$data   = $this->service->getJobsPage(
			array_filter(
				[
					'per_page'        => $perPage,
					'employment_type' => $employmentType,
					'search'          => $search,
				],
				static fn( $v ) => $v !== '' && $v !== 0
			),
			$encid
		);

		$this->assets->enqueueListingAssets();

		ob_start();

		if ( is_wp_error( $data ) ) {
			echo '<p class="hospitaliti-error">'
				. esc_html__( 'Could not load jobs at this time. Please try again later.', 'hospitaliti-jobs' )
				. '</p>';
		} else {
			// Variables expected by templates/jobs-list.php
			$jobs            = $data['data']         ?? [];
			$has_more        = ( $data['current_page'] ?? 1 ) < ( $data['last_page'] ?? 1 );
			$api_base_url    = rtrim( $apiUrl, '/' );
			$per_page        = $data['per_page']     ?? $perPage;
			$show_salary     = $showSalary;
			$show_type       = $showType;
			$show_date       = $showDate;
			$show_search     = $showSearch;
			$show_filter     = $showFilter;
			$employment_type  = $employmentType;
			$organization_ids = '';
			$organizations    = $data['organizations'] ?? [];
			$pagination_mode  = $paginationMode;

			include HOSPITALITI_JOBS_PLUGIN_DIR . 'templates/jobs-list.php';
		}

		return ob_get_clean();
	}

	// ── Conditional asset enqueue ────────────────────────────────────────────

	/**
	 * Enqueue assets only on pages that actually use the shortcode or widget.
	 * Hooked to wp_enqueue_scripts.
	 */
	public function maybeEnqueue(): void {
		global $post;

		if ( is_singular() && $post instanceof \WP_Post && has_shortcode( $post->post_content, 'hospitaliti_jobs' ) ) {
			$this->assets->enqueueListingAssets();
			return;
		}

		if ( is_active_widget( false, false, 'hospitaliti_jobs_widget', true ) ) {
			$this->assets->enqueueListingAssets();
		}
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	private function resolveTheme(): string {
		$theme = get_option( 'hospitaliti_listing_theme', '' )
			?: get_option( 'hospitaliti_theme', 'default' ); // legacy fallback
		return in_array( $theme, [ 'default', 'minimal' ], true ) ? $theme : 'default';
	}
}
