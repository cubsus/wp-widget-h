<?php

/**
 * AJAX handler for the "Load More Jobs" button.
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs\Ajax;

use Hospitaliti\Jobs\Job\JobService;

defined( 'ABSPATH' ) || exit;

class LoadMoreAjax {

	private JobService $service;

	public function __construct( JobService $service ) {
		$this->service = $service;
	}

	/**
	 * Register wp_ajax hooks — called once from Plugin.
	 */
	public function register(): void {
		add_action( 'wp_ajax_nopriv_hospitaliti_load_more', [ $this, 'handle' ] );
		add_action( 'wp_ajax_hospitaliti_load_more',        [ $this, 'handle' ] );
	}

	/**
	 * Handle the AJAX request.
	 *
	 * Success payload: { html, has_more, page, last_page, page_info, has_results }
	 */
	public function handle(): void {
		check_ajax_referer( 'hospitaliti_jobs_nonce', 'nonce' );

		$page            = max( 1, absint( $_POST['page']            ?? 2 ) );
		$perPage         = min( absint( $_POST['per_page'] ?? HOSPITALITI_JOBS_DEFAULT_PER_PAGE ), 50 );
		$employmentType  = sanitize_text_field( $_POST['employment_type']  ?? '' );
		$search          = sanitize_text_field( $_POST['search']           ?? '' );
		$experience      = sanitize_text_field( $_POST['experience']       ?? '' );
		$organizationIds = sanitize_text_field( $_POST['organization_ids'] ?? '' );
		$showSalary      = filter_var( $_POST['show_salary'] ?? '1', FILTER_VALIDATE_BOOLEAN );
		$showType        = filter_var( $_POST['show_type']   ?? '1', FILTER_VALIDATE_BOOLEAN );
		$showDate        = filter_var( $_POST['show_date']   ?? '1', FILTER_VALIDATE_BOOLEAN );

		$encid   = (string) get_option( 'hospitaliti_company_encid', '' );
		$apiUrl  = get_option( 'hospitaliti_jobs_api_url', HOSPITALITI_JOBS_DEFAULT_API_URL );

		$data = $this->service->getJobsPage(
			array_filter(
				[
					'per_page'         => $perPage,
					'page'             => $page,
					'employment_type'  => $employmentType,
					'search'           => $search,
					'experience'       => $experience,
					'organization_ids' => $organizationIds,
				],
				static fn( $v ) => $v !== '' && $v !== 0 && $v !== 'all'
			),
			$encid
		);

		if ( is_wp_error( $data ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not load more jobs.', 'hospitaliti-jobs' ) ] );
		}

		$jobs     = $data['data']         ?? [];
		$hasMore  = ( $data['current_page'] ?? 1 ) < ( $data['last_page'] ?? 1 );
		$lastPage = (int) ( $data['last_page'] ?? 1 );

		$api_base_url = rtrim( $apiUrl, '/' );
		$show_salary  = $showSalary;
		$show_type    = $showType;
		$show_date    = $showDate;

		ob_start();
		if ( empty( $jobs ) ) {
			?>
			<div class="hospitaliti-empty-state" role="status">
				<span class="hospitaliti-empty-icon" aria-hidden="true">
					<svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<circle cx="11" cy="11" r="7.5" stroke="#d1d5db" stroke-width="1.5"/>
						<path d="M17 17L21 21" stroke="#d1d5db" stroke-width="1.5" stroke-linecap="round"/>
					</svg>
				</span>
				<p class="hospitaliti-empty-title"><?php esc_html_e( 'No jobs found', 'hospitaliti-jobs' ); ?></p>
				<p class="hospitaliti-empty-subtitle"><?php esc_html_e( 'Try adjusting your search or filters.', 'hospitaliti-jobs' ); ?></p>
			</div>
			<?php
		} else {
			foreach ( $jobs as $job ) {
				include HOSPITALITI_JOBS_PLUGIN_DIR . 'templates/job-card.php';
			}
		}
		$html = ob_get_clean();

		wp_send_json_success( [
			'html'        => $html,
			'has_results' => ! empty( $jobs ),
			'has_more'    => $hasMore,
			'page'        => $page,
			'last_page'   => $lastPage,
			'page_info'   => sprintf(
				/* translators: %1$d current page, %2$d total pages */
				__( 'Page %1$d of %2$d', 'hospitaliti-jobs' ),
				$page,
				$lastPage
			),
		] );
	}
}
