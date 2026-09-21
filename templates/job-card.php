<?php

/**
 * Template: Single Job Card
 *
 * Design matches the Hospitaliti platform card UI:
 *   Top row  — logo (32 × 32, rounded, shadow) + title / company
 *   Bottom row — candidates count (left)  |  employment type + date (right)
 *
 * Expected variables (injected by the loop, AJAX handler, or jobs-list.php):
 *   $job          stdClass  Job object from the API response.
 *   $api_base_url string    Base URL of the Hospitaliti installation (no trailing slash).
 *   $show_salary  bool      Display salary value in the meta row.
 *   $show_type    bool      Display employment type in the meta row.
 *   $show_date    bool      Display post date in the meta row.
 *
 * @package Hospitaliti_Jobs
 */

defined('ABSPATH') || exit;

// ── Organisation ─────────────────────────────────────────────────────────────
$org_data    = is_array($job->organization ?? null)
	? $job->organization
	: (array) ($job->organization ?? []);

$org_name    = $org_data['name'] ?? '';
$avatar_data = is_array($org_data['avatarImage'] ?? null)
	? $org_data['avatarImage']
	: (array) ($org_data['avatarImage'] ?? []);

$org_logo_raw = $avatar_data['url'] ?? '';

// Resolve relative logo paths returned by the API.
$api_base_url = isset($api_base_url)
	? rtrim($api_base_url, '/')
	: rtrim(get_option('hospitaliti_jobs_api_url', HOSPITALITI_JOBS_DEFAULT_API_URL), '/');

// Resolve relative logo paths; fall back to the platform's default logo when absent.
$org_logo_url = $org_logo_raw && ! preg_match( '#^https?://#i', $org_logo_raw )
	? $api_base_url . '/' . ltrim( $org_logo_raw, '/' )
	: $org_logo_raw;

if ( empty( $org_logo_url ) ) {
	$org_logo_url = $api_base_url . '/images/ApplicationLogo.svg';
}

// ── Job URL ───────────────────────────────────────────────────────────────────
// If a Careers base page is configured in plugin settings, link to the internal
// job detail page ( /{job-slug}/ ) so the visitor stays on the site.
// Otherwise fall back to the external Hospitaliti platform URL.
$_careers_page_id  = (int) get_option( 'hospitaliti_careers_page_id', 0 );
$job_is_external   = ( $_careers_page_id <= 0 ) || ! empty( $force_external );

// Trim leading/trailing dashes that the API may produce when the org prefix
// is empty (e.g. '-job-st-moritz' → 'job-st-moritz').
$_job_slug = trim( $job->slug ?? '', '-' );

// When no internal detail page is configured, link directly to the apply URL
// (bypasses the intermediate Hosco job-description page).
$job_url = $job_is_external
	? ( ! empty( $job->apply_link ) ? $job->apply_link : $api_base_url . '/job/' . rawurlencode( $_job_slug ) )
	: home_url( '/' . rawurlencode( $_job_slug ) . '/' );

// ── Employment type ───────────────────────────────────────────────────────────
$type_labels = [
	'full-time' => __('Full Time', 'hospitaliti-jobs'),
	'part-time' => __('Part Time', 'hospitaliti-jobs'),
	'contract'  => __('Contract',  'hospitaliti-jobs'),
];
$emp_type   = $job->employment_type ?? '';
$type_label = $type_labels[$emp_type] ?? ucwords(str_replace('-', ' ', $emp_type));

// ── Salary ────────────────────────────────────────────────────────────────────
$salary_display = '';
if (! empty($job->salary)) {
	$currency_symbols = [
		'GBP' => '£',
		'EUR' => '€',
		'CHF' => 'CHF ',
		'USD' => '$',
	];
	$currency = $job->salary_currency ?? '';
	$symbol   = $currency_symbols[$currency] ?? ($currency ? $currency . ' ' : '');

	$period_labels = [
		'annually' => __('/ yr', 'hospitaliti-jobs'),
		'monthly'  => __('/ mo', 'hospitaliti-jobs'),
		'hourly'   => __('/ hr', 'hospitaliti-jobs'),
	];
	$period = $period_labels[$job->salary_period ?? ''] ?? '';

	// Normalise: strip apostrophe/comma thousand separators before parsing.
	// The API returns values like "31'000" or "30,000-40,000".
	$salary_raw = str_replace( [ "'", ',' ], '', (string) $job->salary );

	// Salary may be a range like "30000-45000".
	if ( strpos( $salary_raw, '-' ) !== false ) {
		[ $min, $max ] = array_map( 'trim', explode( '-', $salary_raw, 2 ) );
		$salary_display = $symbol . number_format( (float) $min ) . ' – ' . $symbol . number_format( (float) $max ) . ' ' . $period;
	} else {
		$salary_display = $symbol . number_format( (float) $salary_raw ) . ' ' . $period;
	}
	$salary_display = trim( $salary_display );
}

// ── Candidates count ──────────────────────────────────────────────────────────
// API returns applied_candidates as an INTEGER (e.g. 0, 5, 12).
// applications_count is used as the preferred field when present.
$candidates_count = null;

if ( isset( $job->applications_count ) && $job->applications_count !== null ) {
	// Preferred: dedicated applications count field.
	$candidates_count = (int) $job->applications_count;
} elseif ( isset( $job->applied_candidates ) ) {
	if ( is_array( $job->applied_candidates ) ) {
		// Fallback A: array of candidate objects — use length.
		$candidates_count = count( $job->applied_candidates );
	} elseif ( is_numeric( $job->applied_candidates ) ) {
		// Fallback B: integer directly from API (confirmed by live response).
		$candidates_count = (int) $job->applied_candidates;
	}
}

// ── Relative date  ("3 months ago") ──────────────────────────────────────────
// Uses WordPress human_time_diff() which is already locale-aware.
// Supports ISO 8601 strings (2024-01-15T10:30:00Z) and plain dates (2024-01-15).
$date_display = '';
if (! empty($job->post_date)) {
	$timestamp = strtotime($job->post_date);
	if ($timestamp) {
		$date_display = sprintf(
			/* translators: %s: human-readable time difference, e.g. "3 months" */
			__('%s ago', 'hospitaliti-jobs'),
			human_time_diff($timestamp, current_time('timestamp'))
		);
	}
}
?>

<article class="hospitaliti-job-card" itemscope itemtype="https://schema.org/JobPosting">

	<!-- ── Top row: logo + title / company ──────────────────────────────── -->
	<div class="hospitaliti-job-top">

		<div class="hospitaliti-job-top-left">

			<div class="hospitaliti-job-logo" aria-hidden="true">
				<img src="<?php echo esc_url( $org_logo_url ); ?>"
					alt="<?php echo esc_attr( $org_name ); ?>"
					loading="lazy"
					width="32"
					height="32" />
			</div>

			<div class="hospitaliti-job-info">
				<!-- Only the title is the link — not the entire card -->
				<a class="hospitaliti-job-link"
					href="<?php echo esc_url( $job_url ); ?>"
					<?php if ( $job_is_external ) : ?>
					target="_blank"
					rel="noopener noreferrer"
					<?php endif; ?>
					aria-label="<?php echo esc_attr( sprintf( __( 'View job: %s', 'hospitaliti-jobs' ), $job->title ?? '' ) ); ?>">
					<h3 class="hospitaliti-job-title" itemprop="title">
						<?php echo esc_html( $job->title ?? '' ); ?>
					</h3>
				</a>

				<?php if ( ! empty( $org_name ) ) : ?>
					<p class="hospitaliti-job-company"
						itemprop="hiringOrganization"
						itemscope
						itemtype="https://schema.org/Organization">
						<span itemprop="name"><?php echo esc_html( $org_name ); ?></span>
					</p>
				<?php endif; ?>
			</div>

		</div><!-- .hospitaliti-job-top-left -->



	</div><!-- .hospitaliti-job-top -->

	<!-- ── Bottom row: candidates (left)  |  type · salary · date (right) ── -->
	<div class="hospitaliti-job-footer">

		<!-- Left: candidates count — shown even when 0 to match platform UI -->
		<div class="hospitaliti-job-candidates">
			<?php if ( $candidates_count > 0 ) : ?>
				<span class="hospitaliti-candidates-count">
					<?php echo esc_html( number_format( $candidates_count ?? 0 ) ); ?>
				</span>
				<span><?php esc_html_e( 'Candidates', 'hospitaliti-jobs' ); ?></span>
			<?php endif; ?>
		</div>
		<!-- Right: employment type · salary · date -->
		<div class="hospitaliti-job-meta">

			<?php if ( ! empty( $show_type ) && ! empty( $type_label ) ) : ?>
				<span class="hospitaliti-job-type">
					<?php echo esc_html( $type_label ); ?>
				</span>
			<?php endif; ?>

			<?php if ( ! empty( $show_salary ) && ! empty( $salary_display ) ) : ?>
				<span class="hospitaliti-job-salary">
					<?php echo esc_html( $salary_display ); ?>
				</span>
			<?php endif; ?>

			<?php if ( ! empty( $show_date ) && ! empty( $date_display ) ) : ?>
				<span class="hospitaliti-job-date"
					title="<?php echo esc_attr( $job->post_date ); ?>">
					<?php echo esc_html( $date_display ); ?>
				</span>
			<?php endif; ?>

		</div><!-- .hospitaliti-job-meta -->

	</div><!-- .hospitaliti-job-footer -->

</article>