<?php

/**
 * Template: Hosco Jobs List
 *
 * Minimal wrapper around job-card.php for hosco.com job results.
 * Pagination is URL-based (?hosco_page=N) — no AJAX, no Hospitaliti API dependency.
 * Uses the same CSS classes as jobs-list.php so existing styles apply unchanged.
 *
 * Expected variables (set by HoscoJobsShortcode::render()):
 *   $jobs         \stdClass[]  Mapped job objects from HoscoJobMapper.
 *   $current_page int          Current page number.
 *   $last_page    int          Total number of pages.
 *   $api_base_url string       'https://www.hosco.com' (no trailing slash).
 *   $show_salary  bool         Display salary badge.
 *   $show_type    bool         Display employment-type badge.
 *   $show_date    bool         Display post-date badge.
 *   $title        string       Optional section heading.
 *   $theme        string       'default' | 'minimal'
 *
 * @package Hospitaliti_Jobs
 */

defined( 'ABSPATH' ) || exit;

// ── Safe defaults ─────────────────────────────────────────────────────────────
$theme        = isset( $theme ) && $theme === 'minimal' ? 'minimal' : 'default';
$title        = $title        ?? '';
$jobs         = $jobs         ?? [];
$current_page = max( 1, (int) ( $current_page ?? 1 ) );
$last_page    = max( 1, (int) ( $last_page    ?? 1 ) );
$api_base_url = isset( $api_base_url ) ? rtrim( $api_base_url, '/' ) : 'https://www.hosco.com';
$show_salary  = $show_salary  ?? true;
$show_type    = $show_type    ?? true;
$show_date    = $show_date    ?? true;

// Force hosco job card links to always open externally on hosco.com,
// regardless of whether hospitaliti_careers_page_id is configured.
$force_external = true;
?>

<?php if ( ! empty( $title ) ) : ?>
<div class="hospitaliti-jobs-header hospitaliti-theme-<?php echo esc_attr( $theme ); ?>">
	<h2 class="hospitaliti-jobs-title"><?php echo esc_html( $title ); ?></h2>
</div>
<?php endif; ?>

<div class="hospitaliti-jobs-list hospitaliti-theme-<?php echo esc_attr( $theme ); ?>">
	<div class="hospitaliti-layout hospitaliti-layout--no-filter">
		<div class="hospitaliti-jobs-column">

			<!-- ── Job cards ─────────────────────────────────────────────────── -->
			<div class="hospitaliti-jobs-cards">
				<?php if ( empty( $jobs ) ) : ?>
					<div class="hospitaliti-empty-state" role="status">
						<span class="hospitaliti-empty-icon" aria-hidden="true">
							<svg width="48" height="48" viewBox="0 0 24 24" fill="none"
								xmlns="http://www.w3.org/2000/svg">
								<circle cx="11" cy="11" r="7.5" stroke="#d1d5db" stroke-width="1.5"/>
								<path d="M17 17L21 21" stroke="#d1d5db" stroke-width="1.5"
									stroke-linecap="round"/>
								<path d="M8 11h6M11 8v6" stroke="#d1d5db" stroke-width="1.5"
									stroke-linecap="round"/>
							</svg>
						</span>
						<p class="hospitaliti-empty-title">
							<?php esc_html_e( 'No jobs found', 'hospitaliti-jobs' ); ?>
						</p>
						<p class="hospitaliti-empty-subtitle">
							<?php esc_html_e( 'Please check back later.', 'hospitaliti-jobs' ); ?>
						</p>
					</div>
				<?php else : ?>
					<?php foreach ( $jobs as $job ) : ?>
						<?php include __DIR__ . '/job-card.php'; ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</div><!-- .hospitaliti-jobs-cards -->

			<!-- ── URL-based pagination ──────────────────────────────────────── -->
			<?php if ( $last_page > 1 ) : ?>
				<?php
				// Build base URL without any existing hosco_page param so links are clean.
				$_base_url = remove_query_arg( 'hosco_page' );
				$_prev_url = $current_page > 1
					? add_query_arg( 'hosco_page', $current_page - 1, $_base_url )
					: '';
				$_next_url = $current_page < $last_page
					? add_query_arg( 'hosco_page', $current_page + 1, $_base_url )
					: '';

				// Sliding window: always show first, last, and ±2 pages around current.
				$_window   = [ 1 ];
				$_delta    = 2;
				for ( $_p = max( 2, $current_page - $_delta ); $_p <= min( $last_page - 1, $current_page + $_delta ); $_p++ ) {
					$_window[] = $_p;
				}
				if ( $last_page > 1 ) {
					$_window[] = $last_page;
				}
				$_window = array_values( array_unique( $_window ) );
				?>
				<div class="hospitaliti-table-footer">
					<div><!-- spacer (left side of the flex row) --></div>

					<nav class="hospitaliti-pagination"
						aria-label="<?php esc_attr_e( 'Hosco jobs pagination', 'hospitaliti-jobs' ); ?>">

						<?php if ( $_prev_url ) : ?>
							<a href="<?php echo esc_url( $_prev_url ); ?>"
								class="hospitaliti-prev-page"
								aria-label="<?php esc_attr_e( 'Previous page', 'hospitaliti-jobs' ); ?>">
								&laquo; <?php esc_html_e( 'Previous', 'hospitaliti-jobs' ); ?>
							</a>
						<?php else : ?>
							<span class="hospitaliti-prev-page" aria-disabled="true">
								&laquo; <?php esc_html_e( 'Previous', 'hospitaliti-jobs' ); ?>
							</span>
						<?php endif; ?>

						<?php
						$_prev_num = 0;
						foreach ( $_window as $_p ) :
							if ( $_prev_num && $_p - $_prev_num > 1 ) :
						?>
							<span class="hospitaliti-page-ellipsis" aria-hidden="true">&hellip;</span>
						<?php endif; ?>
							<a href="<?php echo esc_url( add_query_arg( 'hosco_page', $_p, $_base_url ) ); ?>"
								class="hospitaliti-page-btn<?php echo $_p === $current_page ? ' is-active' : ''; ?>"
								aria-label="<?php printf( esc_attr__( 'Page %d', 'hospitaliti-jobs' ), $_p ); ?>"
								<?php echo $_p === $current_page ? 'aria-current="page"' : ''; ?>>
								<?php echo esc_html( $_p ); ?>
							</a>
						<?php
							$_prev_num = $_p;
						endforeach;
						?>

						<?php if ( $_next_url ) : ?>
							<a href="<?php echo esc_url( $_next_url ); ?>"
								class="hospitaliti-next-page"
								aria-label="<?php esc_attr_e( 'Next page', 'hospitaliti-jobs' ); ?>">
								<?php esc_html_e( 'Next', 'hospitaliti-jobs' ); ?> &raquo;
							</a>
						<?php else : ?>
							<span class="hospitaliti-next-page" aria-disabled="true">
								<?php esc_html_e( 'Next', 'hospitaliti-jobs' ); ?> &raquo;
							</span>
						<?php endif; ?>

					</nav>
				</div><!-- .hospitaliti-table-footer -->
			<?php endif; ?>

		</div><!-- .hospitaliti-jobs-column -->
	</div><!-- .hospitaliti-layout -->
</div><!-- .hospitaliti-jobs-list -->
