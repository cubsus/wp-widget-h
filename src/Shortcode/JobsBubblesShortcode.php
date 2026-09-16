<?php

/**
 * Registers and renders the [hospitaliti_jobs_bubbles] shortcode.
 *
 * Displays jobs as coloured circular bubbles with organisation-based
 * filter badges at the top.  All filtering is client-side (no AJAX
 * pagination) because all jobs are loaded in a single API request.
 *
 * Shortcode attributes:
 *   title       string   Section heading (default: "Work For Us").
 *   show_title  bool     Show/hide the section heading (default: true).
 *   count       int      Max jobs to fetch, 1–200 (default: 200).
 *   show_cta    bool     Show the bottom CTA block (default: true).
 *   cta_text    string   CTA button label (default: "Submit your application").
 *   cta_url     string   CTA button href  (default: WP privacy-policy URL or #).
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs\Shortcode;

use Hospitaliti\Jobs\Job\JobService;
use Hospitaliti\Jobs\UI\AssetManager;

defined( 'ABSPATH' ) || exit;

class JobsBubblesShortcode {

	private JobService   $service;
	private AssetManager $assets;

	// ── Fallback palette (used when DB options are not yet seeded) ──────────
	private const COLOUR_PALETTE_DEFAULTS = [
		'#143f2b', // deep green
		'#9ca998', // sage grey
		'#c6baab', // warm beige
		'#9baa65', // olive green
		'#da291c', // signal red
		'#523d3f', // brand brown
	];

	/** @return string[] */
	private function loadPalette(): array {
		$palette = (array) get_option( 'hospitaliti_bubbles_colors', [] );
		return $palette ?: self::COLOUR_PALETTE_DEFAULTS;
	}

	public function __construct( JobService $service, AssetManager $assets ) {
		$this->service = $service;
		$this->assets  = $assets;
	}

	/**
	 * Register hooks — called once from Plugin.
	 */
	public function register(): void {
		add_shortcode( 'hospitaliti_jobs_bubbles', [ $this, 'render' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'maybeEnqueue' ] );
	}

	// ── Shortcode rendering ──────────────────────────────────────────────────

	/**
	 * Render the [hospitaliti_jobs_bubbles] shortcode.
	 *
	 * @param  array|string $atts Raw shortcode attributes.
	 * @return string             Buffered HTML.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			[
				'title'      => get_option( 'hospitaliti_bubbles_title' )
					?: get_option( 'hospitaliti_widget_title' ) // legacy fallback
					?: __( 'Work For Us', 'hospitaliti-jobs' ),
				'show_title' => 'true',
				'count'      => 200,
				'show_cta'   => get_option( 'hospitaliti_bubbles_show_cta', 1 ) ? 'true' : 'false',
				'cta_text'   => get_option( 'hospitaliti_bubbles_cta_text' )
					?: __( 'Submit your application', 'hospitaliti-jobs' ),
				'cta_url'    => get_option( 'hospitaliti_bubbles_cta_url', '' ),
			],
			$atts,
			'hospitaliti_jobs_bubbles'
		);

		$title     = sanitize_text_field( $atts['title'] );
		$showTitle = filter_var( $atts['show_title'], FILTER_VALIDATE_BOOLEAN );
		$count     = min( absint( $atts['count'] ), 200 );
		$showCta   = filter_var( $atts['show_cta'], FILTER_VALIDATE_BOOLEAN );
		$ctaText   = sanitize_text_field( $atts['cta_text'] );
		$ctaUrl    = esc_url_raw( $atts['cta_url'] ?: get_privacy_policy_url() ?: '#' );
		$theme     = $this->resolveTheme();

		// ── API call ─────────────────────────────────────────────────────────
		$encid  = (string) get_option( 'hospitaliti_company_encid', '' );
		$apiUrl = get_option( 'hospitaliti_jobs_api_url', HOSPITALITI_JOBS_DEFAULT_API_URL );
		$data   = $this->service->getJobsPage(
			[ 'per_page' => $count ],
			$encid
		);

		// ── Enqueue assets ───────────────────────────────────────────────────
		$this->assets->enqueueBubbleListing();

		ob_start();

		if ( is_wp_error( $data ) ) {
			echo '<p class="hospitaliti-error">'
				. esc_html__( 'Could not load jobs at this time. Please try again later.', 'hospitaliti-jobs' )
				. '</p>';
			return ob_get_clean();
		}

		$jobs = $data['data'] ?? [];

		// ── Build organisation → colour map ───────────────────────────────────
		// Walk through jobs in order; first time we see an org name it gets the
		// next colour slot.  Order is stable and deterministic.
		$orgColourMap = []; // org_name => colour hex
		$orgCounts    = []; // org_name => job count
		$palette      = $this->loadPalette(); // reads from DB settings
		$paletteSize  = count( $palette );
		$paletteIndex = 0;

		foreach ( $jobs as $job ) {
			$orgData = is_array( $job->organization ?? null )
				? $job->organization
				: (array) ( $job->organization ?? [] );
			$orgName = $orgData['name'] ?? __( 'Other', 'hospitaliti-jobs' );

			if ( ! isset( $orgColourMap[ $orgName ] ) ) {
				$orgColourMap[ $orgName ] = $palette[ $paletteIndex % $paletteSize ];
				$paletteIndex++;
			}
			$orgCounts[ $orgName ] = ( $orgCounts[ $orgName ] ?? 0 ) + 1;
		}

		// ── Resolve careers slug for job links ────────────────────────────────
		$careersPageId     = (int) get_option( 'hospitaliti_careers_page_id', 0 );
		$careers_slug      = $careersPageId > 0 ? get_post_field( 'post_name', $careersPageId ) : '';
		$api_base_url      = rtrim( $apiUrl, '/' );

		// "Back to Careers" URL embedded into each bubble link so the detail page
		// knows to return here instead of the standard careers listing page.
		$bubbles_back_url  = esc_url_raw( (string) get_option( 'hospitaliti_bubbles_back_url', '' ) );

		// ── Template variables ────────────────────────────────────────────────
		// (used by jobs-bubbles.php and job-bubble.php)
		include HOSPITALITI_JOBS_PLUGIN_DIR . 'templates/jobs-bubbles.php';

		return ob_get_clean();
	}

	// ── Conditional asset enqueue ────────────────────────────────────────────

	/**
	 * Enqueue bubble assets on pages that contain the shortcode.
	 * Hooked to wp_enqueue_scripts.
	 */
	public function maybeEnqueue(): void {
		global $post;

		if (
			is_singular()
			&& $post instanceof \WP_Post
			&& has_shortcode( $post->post_content, 'hospitaliti_jobs_bubbles' )
		) {
			$this->assets->enqueueBubbleListing();
		}
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	private function resolveTheme(): string {
		// Bubble listing uses the global theme since it has no separate theme option.
		$theme = get_option( 'hospitaliti_theme', 'default' );
		return in_array( $theme, [ 'default', 'minimal' ], true ) ? $theme : 'default';
	}
}
