<?php
/**
 * Template: Jobs List
 *
 * Expected variables (set by shortcode or widget):
 *   $jobs            array    Array of job stdClass objects from the API.
 *   $data            array    Full paginated API response.
 *   $has_more        bool     Whether more pages are available.
 *   $per_page        int      Items per page (default 20, user-selectable via dropdown).
 *   $api_base_url    string   Base URL of the Hospitaliti installation (no trailing slash).
 *   $pagination_mode string   'load_more' or 'paged'.
 *   $show_salary     bool     Display salary badge.
 *   $show_type       bool     Display employment-type badge.
 *   $show_date       bool     Display post-date badge.
 *   $show_search     bool     Display the search bar           (default true).
 *   $show_filter     bool     Display the filter sidebar       (default true).
 *   $employment_type string   Active employment-type filter (comma-separated, may be empty).
 *   $experience      string   Active experience filter value (0,1,2,3 or 'all', may be empty).
 *   $search          string   Active keyword filter (may be empty).
 *
 * @package Hospitaliti_Jobs
 */

defined( 'ABSPATH' ) || exit;

// ── Safe defaults ─────────────────────────────────────────────────────────────
$theme           = ( isset( $theme ) && in_array( $theme, [ 'default', 'minimal' ], true ) ) ? $theme : 'default';
$title           = $title           ?? '';
$jobs            = $jobs            ?? [];
$data            = $data            ?? [];
$has_more        = $has_more        ?? false;
$per_page        = $per_page        ?? HOSPITALITI_JOBS_DEFAULT_PER_PAGE;
$api_base_url    = isset( $api_base_url )
	? rtrim( $api_base_url, '/' )
	: rtrim( get_option( 'hospitaliti_jobs_api_url', HOSPITALITI_JOBS_DEFAULT_API_URL ), '/' );
$pagination_mode = $pagination_mode ?? 'load_more';
$current_page    = (int) ( $data['current_page'] ?? 1 );
$last_page       = (int) ( $data['last_page']    ?? 1 );
$show_salary     = $show_salary     ?? true;
$show_type       = $show_type       ?? true;
$show_date       = $show_date       ?? true;
$show_search     = $show_search     ?? true;
$show_filter     = $show_filter     ?? true;
$employment_type  = $employment_type  ?? '';
$experience       = $experience       ?? '';
$search           = $search           ?? '';
$organizations    = is_array( $organizations    ?? null ) ? $organizations    : [];
$organization_ids = $organization_ids ?? '';
$active_org_ids   = $organization_ids
	? array_map( 'intval', array_filter( array_map( 'trim', explode( ',', $organization_ids ) ) ) )
	: [];


// Sync $per_page from the API's actual response value.
if ( ! empty( $data['per_page'] ) ) {
	$per_page = (int) $data['per_page'];
}

// ── Per-page dropdown options ─────────────────────────────────────────────────
$per_page_options = [ 10, 20, 25, 50 ];
if ( ! in_array( $per_page, $per_page_options, true ) ) {
	$per_page_options[] = $per_page;
	sort( $per_page_options );
}

// ── Employment type filter options ────────────────────────────────────────────
$employment_type_options = [
	'full-time'      => __( 'Full Time',      'hospitaliti-jobs' ),
	'part-time'      => __( 'Part Time',      'hospitaliti-jobs' ),
	'internship'     => __( 'Internship',     'hospitaliti-jobs' ),
	'graduate'       => __( 'Graduate',       'hospitaliti-jobs' ),
	'contract'       => __( 'Contract',       'hospitaliti-jobs' ),
	'seasonal'       => __( 'Seasonal',       'hospitaliti-jobs' ),
	'hourly'         => __( 'Hourly',         'hospitaliti-jobs' ),
	'apprenticeship' => __( 'Apprenticeship', 'hospitaliti-jobs' ),
];

// Parse the active employment types into an array for checkbox pre-selection.
$active_types = $employment_type
	? array_map( 'trim', explode( ',', $employment_type ) )
	: [];

// ── Experience Level filter options ──────────────────────────────────────────
$experience_options = [
	'0'   => __( 'No experience', 'hospitaliti-jobs' ),
	'1'   => __( '1 Year',        'hospitaliti-jobs' ),
	'2'   => __( '2 Years',       'hospitaliti-jobs' ),
	'3'   => __( '> 3 Years',     'hospitaliti-jobs' ),
	'all' => __( 'All',           'hospitaliti-jobs' ),
];
?>

<?php if ( ! empty( $title ) ) : ?>
<div class="hospitaliti-jobs-header hospitaliti-theme-<?php echo esc_attr( $theme ); ?>">
	<h2 class="hospitaliti-jobs-title"><?php echo esc_html( $title ); ?></h2>
</div>
<?php endif; ?>

<?php
$_intro_text = get_option( 'hospitaliti_listing_intro_text', '' );
if ( ! empty( $_intro_text ) ) : ?>
<div class="hospitaliti-listing-intro"><?php echo wp_kses_post( $_intro_text ); ?></div>
<?php endif; ?>

<div class="hospitaliti-jobs-list hospitaliti-theme-<?php echo esc_attr( $theme ); ?>"
	data-page="<?php echo esc_attr( $current_page ); ?>"
	data-last-page="<?php echo esc_attr( $last_page ); ?>"
	data-per-page="<?php echo esc_attr( $per_page ); ?>"
	data-employment-type="<?php echo esc_attr( $employment_type ); ?>"
	data-search="<?php echo esc_attr( $search ); ?>"
	data-show-salary="<?php echo esc_attr( $show_salary ? '1' : '0' ); ?>"
	data-show-type="<?php echo esc_attr( $show_type ? '1' : '0' ); ?>"
	data-show-date="<?php echo esc_attr( $show_date ? '1' : '0' ); ?>"
	data-experience="<?php echo esc_attr( $experience ); ?>"
	data-organization-ids="<?php echo esc_attr( $organization_ids ); ?>">

	<?php if ( $show_search ) : ?>
	<!-- ── Search bar ─────────────────────────────────────────────────────── -->
	<div class="hospitaliti-search-wrap">
		<span class="hospitaliti-search-icon" aria-hidden="true">
			<svg width="16" height="16" viewBox="0 0 20 20" fill="none"
				xmlns="http://www.w3.org/2000/svg">
				<circle cx="9" cy="9" r="6.5" stroke="currentColor" stroke-width="1.5"/>
				<path d="M14 14L18 18" stroke="currentColor" stroke-width="1.5"
					stroke-linecap="round"/>
			</svg>
		</span>
		<input type="search"
			class="hospitaliti-search-input"
			value="<?php echo esc_attr( $search ); ?>"
			placeholder="<?php esc_attr_e( 'Search jobs by title or company…', 'hospitaliti-jobs' ); ?>"
			aria-label="<?php esc_attr_e( 'Search jobs', 'hospitaliti-jobs' ); ?>" />
		<?php if ( ! empty( $search ) ) : ?>
			<button class="hospitaliti-search-clear" type="button"
				aria-label="<?php esc_attr_e( 'Clear search', 'hospitaliti-jobs' ); ?>">
				&times;
			</button>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php if ( $show_filter ) : ?>
	<!-- ── Mobile filter toggle ────────────────────────────────────────────── -->
	<button class="hospitaliti-filter-toggle" type="button"
		aria-expanded="false"
		aria-controls="hospitaliti-filter-sidebar">
		<svg width="14" height="14" viewBox="0 0 16 16" fill="none"
			xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
			<path d="M2 4h12M4 8h8M6 12h4"
				stroke="currentColor" stroke-width="1.5"
				stroke-linecap="round" stroke-linejoin="round" />
		</svg>
		<?php esc_html_e( 'Filters', 'hospitaliti-jobs' ); ?>
		<?php
			$badge_count = count( $active_types ) + ( ! empty( $experience ) && $experience !== 'all' ? 1 : 0 );
			if ( $badge_count > 0 ) :
			?>
			<span class="hospitaliti-filter-badge">
				<?php echo esc_html( $badge_count ); ?>
			</span>
		<?php endif; ?>
	</button>
	<?php endif; ?>

	<!-- ── 2-column layout: filter sidebar (left) + jobs column (right) ───── -->
	<div class="hospitaliti-layout<?php echo ! $show_filter ? ' hospitaliti-layout--no-filter' : ''; ?>">

		<?php if ( $show_filter ) : ?>
		<!-- ── Filter sidebar ──────────────────────────────────────────────── -->
		<aside class="hospitaliti-filter-sidebar"
			id="hospitaliti-filter-sidebar"
			aria-label="<?php esc_attr_e( 'Job filters', 'hospitaliti-jobs' ); ?>">

			<?php $has_active_filters = ! empty( $active_types ) || ! empty( $experience ) || ! empty( $active_org_ids ); ?>
			<div class="hospitaliti-filter-header">
				<h6 class="hospitaliti-filter-title">
					<?php esc_html_e( 'Filters', 'hospitaliti-jobs' ); ?>
				</h6>
				<!-- Always in DOM; JS toggles hospitaliti-hidden as filters are applied/cleared -->
				<button class="hospitaliti-filter-clear<?php echo $has_active_filters ? '' : ' hospitaliti-hidden'; ?>"
					type="button"
					aria-label="<?php esc_attr_e( 'Clear filters', 'hospitaliti-jobs' ); ?>">
					<svg width="14" height="14" viewBox="0 0 16 16" fill="none"
						xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M13.5 8A5.5 5.5 0 1 1 8 2.5"
							stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
						<path d="M8.5 1.5L8.5 4.5L11.5 3"
							stroke="currentColor" stroke-width="1.5"
							stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>
			</div>

			<div class="hospitaliti-filter-section">
				<h6 class="hospitaliti-filter-section-title">
					<?php esc_html_e( 'Employment Types', 'hospitaliti-jobs' ); ?>
				</h6>
				<ul class="hospitaliti-filter-list">
					<?php foreach ( $employment_type_options as $value => $label ) : ?>
						<li class="hospitaliti-filter-item">
							<label class="hospitaliti-filter-label">
								<input type="checkbox"
									class="hospitaliti-filter-type"
									value="<?php echo esc_attr( $value ); ?>"
									<?php checked( in_array( $value, $active_types, true ) ); ?> />
								<span class="hospitaliti-filter-checkmark"></span>
								<span class="hospitaliti-filter-text">
									<?php echo esc_html( $label ); ?>
								</span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="hospitaliti-filter-section">
				<h6 class="hospitaliti-filter-section-title">
					<?php esc_html_e( 'Experience Level', 'hospitaliti-jobs' ); ?>
				</h6>
				<ul class="hospitaliti-filter-list">
					<?php foreach ( $experience_options as $value => $label ) : ?>
						<li class="hospitaliti-filter-item">
							<label class="hospitaliti-filter-label">
								<input type="radio"
									class="hospitaliti-filter-experience"
									name="hospitaliti_experience"
									value="<?php echo esc_attr( $value ); ?>"
									<?php checked( $experience, $value ); ?> />
								<span class="hospitaliti-filter-radiomark"></span>
								<span class="hospitaliti-filter-text">
									<?php echo esc_html( $label ); ?>
								</span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<?php if ( count( $organizations ) > 1 ) : ?>
			<div class="hospitaliti-filter-section">
				<h6 class="hospitaliti-filter-section-title">
					<?php esc_html_e( 'Company', 'hospitaliti-jobs' ); ?>
				</h6>
				<ul class="hospitaliti-filter-list">
					<?php foreach ( $organizations as $org ) : ?>
						<li class="hospitaliti-filter-item">
							<label class="hospitaliti-filter-label">
								<input type="checkbox"
									class="hospitaliti-filter-company"
									value="<?php echo esc_attr( $org['id'] ); ?>"
									<?php checked( in_array( (int) $org['id'], $active_org_ids, true ) ); ?> />
								<span class="hospitaliti-filter-checkmark"></span>
								<span class="hospitaliti-filter-text">
									<?php echo esc_html( $org['name'] ); ?>
								</span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endif; ?>

		</aside><!-- .hospitaliti-filter-sidebar -->
		<?php endif; // $show_filter ?>

		<!-- ── Jobs column ─────────────────────────────────────────────────── -->
		<div class="hospitaliti-jobs-column">

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
							<?php esc_html_e( 'Try adjusting your search or filters.', 'hospitaliti-jobs' ); ?>
						</p>
					</div>
				<?php else : ?>
					<?php foreach ( $jobs as $job ) : ?>
						<?php include __DIR__ . '/job-card.php'; ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</div><!-- .hospitaliti-jobs-cards -->

			<!-- Cards loading overlay — revealed by JS adding .is-fetching to .hospitaliti-jobs-column -->
			<div class="hospitaliti-cards-loader" aria-hidden="true" role="status">
				<span class="hospitaliti-cards-loader-ring"></span>
			</div>

			<!-- Table footer: per-page (left) + pagination (right)
			     Hidden when no results so the dropdown doesn't show for empty state. -->
			<?php if ( 'paged' === $pagination_mode ) : ?>
				<div class="hospitaliti-table-footer<?php echo empty( $jobs ) ? ' hospitaliti-hidden' : ''; ?>">

					<!-- Left: per-page custom dropdown -->
					<div class="hospitaliti-per-page-wrap">
						<span class="hospitaliti-per-page-label"><?php esc_html_e( 'Show:', 'hospitaliti-jobs' ); ?></span>

						<div class="hospitaliti-per-page-dropdown"
							data-current="<?php echo esc_attr( $per_page ); ?>"
							role="listbox"
							aria-label="<?php esc_attr_e( 'Jobs per page', 'hospitaliti-jobs' ); ?>">

							<button class="hospitaliti-per-page-btn" type="button"
								aria-haspopup="listbox"
								aria-expanded="false">
								<span class="hospitaliti-per-page-value"><?php echo esc_html( $per_page ); ?></span>
								<svg class="hospitaliti-per-page-chevron" width="10" height="10"
									viewBox="0 0 10 10" fill="none"
									xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
									<path d="M2 3.5L5 6.5L8 3.5" stroke="currentColor"
										stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
							</button>

							<ul class="hospitaliti-per-page-menu" role="listbox">
								<?php foreach ( $per_page_options as $option ) : ?>
									<li class="hospitaliti-per-page-option<?php echo $option === $per_page ? ' is-active' : ''; ?>"
										data-value="<?php echo esc_attr( $option ); ?>"
										role="option"
										aria-selected="<?php echo $option === $per_page ? 'true' : 'false'; ?>">
										<?php echo esc_html( $option ); ?>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>

						<span class="hospitaliti-per-page-label"><?php esc_html_e( 'per page', 'hospitaliti-jobs' ); ?></span>
					</div>

					<!-- Right: numbered pagination -->
					<nav class="hospitaliti-pagination<?php echo $last_page <= 1 ? ' hospitaliti-hidden' : ''; ?>"
						aria-label="<?php esc_attr_e( 'Jobs pagination', 'hospitaliti-jobs' ); ?>">

						<button class="hospitaliti-prev-page" type="button"
							<?php disabled( true, $current_page <= 1 ); ?>
							aria-label="<?php esc_attr_e( 'Previous page', 'hospitaliti-jobs' ); ?>">
							&laquo; <?php esc_html_e( 'Previous', 'hospitaliti-jobs' ); ?>
						</button>

						<?php
						$window   = [ 1 ];
						$delta    = 2;
						for ( $p = max( 2, $current_page - $delta ); $p <= min( $last_page - 1, $current_page + $delta ); $p++ ) {
							$window[] = $p;
						}
						if ( $last_page > 1 ) {
							$window[] = $last_page;
						}
						$window   = array_values( array_unique( $window ) );
						$prev_num = 0;

						foreach ( $window as $p ) :
							if ( $prev_num && $p - $prev_num > 1 ) :
						?>
							<span class="hospitaliti-page-ellipsis" aria-hidden="true">&hellip;</span>
						<?php
							endif;
						?>
							<button class="hospitaliti-page-btn<?php echo $p === $current_page ? ' is-active' : ''; ?>"
								type="button"
								data-page="<?php echo esc_attr( $p ); ?>"
								<?php echo $p === $current_page ? 'aria-current="page"' : ''; ?>
								aria-label="<?php printf( esc_attr__( 'Page %d', 'hospitaliti-jobs' ), $p ); ?>">
								<?php echo esc_html( $p ); ?>
							</button>
						<?php
							$prev_num = $p;
						endforeach;
						?>

						<button class="hospitaliti-next-page" type="button"
							<?php disabled( true, $current_page >= $last_page ); ?>
							aria-label="<?php esc_attr_e( 'Next page', 'hospitaliti-jobs' ); ?>">
							<?php esc_html_e( 'Next', 'hospitaliti-jobs' ); ?> &raquo;
						</button>

					</nav>

				</div><!-- .hospitaliti-table-footer -->

			<?php elseif ( 'load_more' === $pagination_mode && $has_more ) : ?>
				<div class="hospitaliti-load-more-wrap">
					<button class="hospitaliti-load-more" type="button">

						<!-- Spinner — visible only while loading (CSS-controlled) -->
						<span class="hospitaliti-load-more-spinner" aria-hidden="true">
							<svg width="15" height="15" viewBox="0 0 16 16" fill="none"
								xmlns="http://www.w3.org/2000/svg">
								<circle cx="8" cy="8" r="6"
									stroke="currentColor" stroke-width="2"
									stroke-dasharray="28" stroke-dashoffset="10"
									stroke-linecap="round"/>
							</svg>
						</span>

						<!-- Arrow-down icon — hidden while loading -->
						<span class="hospitaliti-load-more-icon" aria-hidden="true">
							<svg width="15" height="15" viewBox="0 0 16 16" fill="none"
								xmlns="http://www.w3.org/2000/svg">
								<path d="M8 3v9M3.5 7.5L8 12l4.5-4.5"
									stroke="currentColor" stroke-width="1.6"
									stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</span>

						<span class="hospitaliti-load-more-text">
							<?php esc_html_e( 'Load More Jobs', 'hospitaliti-jobs' ); ?>
						</span>

					</button>
				</div>
			<?php endif; ?>



		</div><!-- .hospitaliti-jobs-column -->

	</div><!-- .hospitaliti-layout -->

</div><!-- .hospitaliti-jobs-list -->
