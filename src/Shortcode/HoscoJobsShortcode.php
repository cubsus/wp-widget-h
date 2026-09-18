<?php

/**
 * Registers and renders the [hosco_jobs] shortcode.
 *
 * Fetches jobs from hosco.com's public search API and renders them using the
 * existing listing CSS and job-card.php template.  Pagination is URL-based
 * (hosco_page query param) — no AJAX, no dependency on the Hospitaliti API.
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs\Shortcode;

use Hospitaliti\Jobs\Hosco\HoscoJobRepository;
use Hospitaliti\Jobs\Hosco\HoscoJobMapper;
use Hospitaliti\Jobs\UI\AssetManager;

defined( 'ABSPATH' ) || exit;

class HoscoJobsShortcode {

	private HoscoJobRepository $repo;
	private HoscoJobMapper     $mapper;
	private AssetManager       $assets;

	public function __construct( HoscoJobRepository $repo, HoscoJobMapper $mapper, AssetManager $assets ) {
		$this->repo   = $repo;
		$this->mapper = $mapper;
		$this->assets = $assets;
	}

	/**
	 * Register hooks — called once from Plugin.
	 */
	public function register(): void {
		add_shortcode( 'hosco_jobs', [ $this, 'render' ] );
		add_action( 'wp_enqueue_scripts',        [ $this, 'maybeEnqueue' ] );
	}

	/**
	 * Render the [hosco_jobs] shortcode.
	 *
	 * Supported attributes:
	 *   title        — section heading (default: from settings or empty)
	 *   count        — jobs per page, max 50 (default: hosco_per_page option)
	 *   show_salary  — show salary badge (default: true)
	 *   show_type    — show employment-type badge (default: true)
	 *   show_date    — show posted-date badge (default: true)
	 *
	 * @param  array|string $atts Raw shortcode attributes.
	 * @return string             Buffered HTML.
	 */
	public function render( $atts ): string {
		if ( ! get_option( 'hosco_enabled', 0 ) ) {
			return '';
		}

		$defaultPerPage = max( 1, min( (int) get_option( 'hosco_per_page', 10 ), 50 ) );
		$defaultLocale  = sanitize_key( (string) get_option( 'hosco_locale', 'en' ) );

		$atts = shortcode_atts(
			[
				'title'       => '',
				'count'       => $defaultPerPage,
				'show_salary' => 'true',
				'show_type'   => 'true',
				'show_date'   => 'true',
			],
			$atts,
			'hosco_jobs'
		);

		$title      = ! empty( $atts['title'] ) ? sanitize_text_field( $atts['title'] ) : '';
		$perPage    = min( absint( $atts['count'] ), 50 );
		$showSalary = filter_var( $atts['show_salary'], FILTER_VALIDATE_BOOLEAN );
		$showType   = filter_var( $atts['show_type'],   FILTER_VALIDATE_BOOLEAN );
		$showDate   = filter_var( $atts['show_date'],   FILTER_VALIDATE_BOOLEAN );

		$page  = max( 1, absint( $_GET['hosco_page'] ?? 1 ) );
		$theme = $this->resolveTheme();

		$ownerSlug = trim( (string) get_option( 'hosco_group_id', '' ) );
		$filters   = [ 'limit' => $perPage ];
		if ( $ownerSlug !== '' ) {
			$filters['owner'] = $ownerSlug;
		}
		$result = $this->repo->search( $filters, $page, $defaultLocale );

		$this->assets->enqueueListingAssets();

		ob_start();

		if ( is_wp_error( $result ) ) {
			printf(
				'<p class="hospitaliti-error">%s</p>',
				esc_html__( 'Could not load Hosco jobs at this time. Please try again later.', 'hospitaliti-jobs' )
			);
		} else {
			$totalRaw = $result['total'] ?? 0;
			$total    = is_array( $totalRaw ) ? (int) ( $totalRaw['value'] ?? 0 ) : (int) $totalRaw;
			$jobs     = $this->mapper->mapCollection( $result['results'] ?? [], $defaultLocale );
			$lastPage = $perPage > 0 ? (int) ceil( $total / $perPage ) : 1;

			$current_page = $page;
			$last_page    = max( 1, $lastPage );
			$api_base_url = rtrim( (string) get_option( 'hosco_base_url', 'https://www.hosco.com' ), '/' );
			$show_salary  = $showSalary;
			$show_type    = $showType;
			$show_date    = $showDate;

			include HOSPITALITI_JOBS_PLUGIN_DIR . 'templates/hosco-jobs-list.php';
		}

		return ob_get_clean();
	}

	/**
	 * Enqueue listing assets on pages that use the hosco shortcode.
	 * Hooked to wp_enqueue_scripts.
	 */
	public function maybeEnqueue(): void {
		global $post;

		if (
			is_singular() &&
			$post instanceof \WP_Post &&
			has_shortcode( $post->post_content, 'hosco_jobs' )
		) {
			$this->assets->enqueueListingAssets();
		}
	}

	/**
	 * Resolve the theme slug for the hosco listing.
	 * Reuses the same listing theme option as the standard shortcode.
	 */
	private function resolveTheme(): string {
		$theme = get_option( 'hospitaliti_listing_theme', '' )
			?: get_option( 'hospitaliti_theme', 'default' );
		return in_array( $theme, [ 'default', 'minimal' ], true ) ? $theme : 'default';
	}
}
