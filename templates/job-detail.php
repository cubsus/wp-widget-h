<?php

/**
 * Template: Job Detail Page
 *
 * Loaded by template_redirect when the hospitaliti_job query var is set.
 * Wraps content in the active theme's get_header() / get_footer() so it
 * inherits the site's navigation, header, and footer.
 *
 * Expected variables (set by the template_redirect hook in class-hospitaliti-jobs.php):
 *   $job           array   Full job data from GET /api/job/{slug}.
 *   $api_base_url  string  Base URL of the Hospitaliti installation.
 *   $careers_base  string  WP page slug for the careers listing (e.g. "careers").
 *
 * @package Hospitaliti_Jobs
 */

defined('ABSPATH') || exit;

// ── Cast to object for consistent property access ─────────────────────────────
$job = (object) ($job ?? []);

// ── Theme ──────────────────────────────────────────────────────────────────────
$theme = (isset($theme) && in_array($theme, ['default', 'minimal'], true))
	? $theme
	: 'default';

// ── Organisation ──────────────────────────────────────────────────────────────
$org_data    = is_array($job->organization ?? null)
	? $job->organization
	: (array) ($job->organization ?? []);
$org_name    = $org_data['name'] ?? '';

$avatar_data     = is_array($org_data['avatarImage'] ?? null)
	? $org_data['avatarImage']
	: (array) ($org_data['avatarImage'] ?? []);
$banner_data     = is_array($org_data['bannerImage'] ?? null)
	? $org_data['bannerImage']
	: (array) ($org_data['bannerImage'] ?? []);

$api_base_url = isset($api_base_url)
	? rtrim($api_base_url, '/')
	: rtrim(get_option('hospitaliti_jobs_api_url', HOSPITALITI_JOBS_DEFAULT_API_URL), '/');

// Resolve relative URLs.
$resolve = function ($url) use ($api_base_url): string {
	if (empty($url)) {
		return '';
	}
	return preg_match('#^https?://#i', $url) ? $url : $api_base_url . '/' . ltrim($url, '/');
};

$org_logo_url   = $resolve($avatar_data['url'] ?? '');
$org_banner_url = $resolve($banner_data['url'] ?? '');

// ── Employment type ───────────────────────────────────────────────────────────
$type_labels = [
	'full-time'      => __('Full Time',      'hospitaliti-jobs'),
	'part-time'      => __('Part Time',      'hospitaliti-jobs'),
	'contract'       => __('Contract',       'hospitaliti-jobs'),
	'internship'     => __('Internship',     'hospitaliti-jobs'),
	'graduate'       => __('Graduate',       'hospitaliti-jobs'),
	'seasonal'       => __('Seasonal',       'hospitaliti-jobs'),
	'hourly'         => __('Hourly',         'hospitaliti-jobs'),
	'apprenticeship' => __('Apprenticeship', 'hospitaliti-jobs'),
];
$emp_type   = $job->employment_type ?? '';
$type_label = $type_labels[$emp_type] ?? ucwords(str_replace('-', ' ', $emp_type));

// ── Salary ────────────────────────────────────────────────────────────────────
$salary_display = '';
if (! empty($job->salary)) {
	if (! empty($job->salary_negotiable)) {
		$salary_display = __('Negotiable', 'hospitaliti-jobs');
	} else {
		$currency_symbols = ['GBP' => '£', 'EUR' => '€', 'CHF' => 'CHF ', 'USD' => '$'];
		$currency  = $job->salary_currency ?? '';
		$symbol    = $currency_symbols[$currency] ?? ($currency ? $currency . ' ' : '');
		$period_labels = ['annually' => '/ yr', 'monthly' => '/ mo', 'hourly' => '/ hr'];
		$period    = $period_labels[$job->salary_period ?? ''] ?? '';
		$raw       = str_replace(["'", ','], '', (string) $job->salary);
		if (strpos($raw, '-') !== false) {
			[$min, $max] = array_map('trim', explode('-', $raw, 2));
			$salary_display = $symbol . number_format((float) $min) . ' – ' . $symbol . number_format((float) $max) . ' ' . $period;
		} else {
			$salary_display = $symbol . number_format((float) $raw) . ' ' . $period;
		}
		$salary_display = trim($salary_display);
	}
}

// ── Location string ───────────────────────────────────────────────────────────
$location_str = '';
if (! empty($job->location) && is_array($job->location)) {
	$location_parts = array_map(function ($loc) {
		return is_array($loc) ? ($loc['name'] ?? '') : (string) $loc;
	}, $job->location);
	$location_str = implode(', ', array_filter($location_parts));
}

// ── Dates ─────────────────────────────────────────────────────────────────────
$post_date_display     = $job->post_date     ?? '';
$starting_date_display = $job->starting_date ?? '';

// ── Careers list URL ──────────────────────────────────────────────────────────
// $careers_base and $careers_url are pre-computed in Plugin::handleJobDetail()
// and passed via include scope. The fallbacks below handle direct template includes.
if (empty($careers_base)) {
	$_careers_pgid = (int) get_option('hospitaliti_careers_page_id', 0);
	$careers_base  = 'careers';
	if ($_careers_pgid > 0) {
		$_slug = get_post_field('post_name', $_careers_pgid);
		if (! is_wp_error($_slug) && $_slug) {
			$careers_base = $_slug;
		}
	}
}
if (empty($careers_url)) {
	$_careers_pgid = (int) get_option('hospitaliti_careers_page_id', 0);
	$careers_url   = $_careers_pgid > 0
		? (get_permalink($_careers_pgid) ?: home_url('/' . $careers_base . '/'))
		: home_url('/' . $careers_base . '/');
}

// ── Apply ─────────────────────────────────────────────────────────────────────
// Use the direct apply_link from the API (links straight to Hosco login/form).
$apply_link = (string) ($job->apply_link ?? '');
$platform_apply = rtrim( $api_base_url, '/' ) . '/job/' . rawurlencode( trim( $job->slug ?? '', '-' ) ) . '/apply';
get_header();

// ── Scroll-reveal helper — only output the attribute in minimal theme ──────────
$reveal = 'minimal' === $theme ? 'data-hj-reveal' : '';

// ── Resolve WordPress site logo for the page loader ───────────────────────────
$hj_loader_img = '';
if ('minimal' === $theme) {
	$logo_id = get_theme_mod('custom_logo');
	if ($logo_id) {
		$hj_loader_img = wp_get_attachment_image(
			$logo_id,
			[80, 80],
			false,
			['alt' => esc_attr(get_bloginfo('name'))]
		);
	}
	if (! $hj_loader_img) {
		$icon_url = get_site_icon_url(80);
		if ($icon_url) {
			$hj_loader_img = '<img src="' . esc_url($icon_url) . '" width="80" height="80" alt="' . esc_attr(get_bloginfo('name')) . '">';
		}
	}
	if (! $hj_loader_img) {
		$hj_loader_img = '<div class="hospitaliti-loader-initials">'
			. esc_html(mb_strtoupper(mb_substr(get_bloginfo('name'), 0, 2)))
			. '</div>';
	}
}
?>

<?php if ('minimal' === $theme) : ?>
	<!-- ── Page loader ─────────────────────────────────────────────────────────── -->
	<div id="hospitaliti-page-loader" class="hospitaliti-page-loader" aria-hidden="true" role="status">
		<div class="hospitaliti-loader-logo">
			<div class="hospitaliti-loader-logo-ring">
				<?php echo $hj_loader_img; // pre-escaped above 
				?>
			</div>
		</div>
	</div>
<?php endif; ?>

<article class="hospitaliti-job-detail hospitaliti-theme-<?php echo esc_attr($theme); ?>" itemscope itemtype="https://schema.org/JobPosting">

	<?php if ('minimal' !== $theme) : ?>
		<!-- ── Banner ────────────────────────────────────────────────────────────── -->
		<div class="hospitaliti-detail-banner"
			<?php if ($org_banner_url) : ?>
			style="background-image: url('<?php echo esc_url($org_banner_url); ?>')"
			<?php endif; ?>>
			<div class="hospitaliti-detail-banner-overlay"></div>

			<div class="hospitaliti-detail-banner-inner">
				<?php if ($org_logo_url) : ?>
					<div class="hospitaliti-detail-banner-logo">
						<img src="<?php echo esc_url($org_logo_url); ?>"
							alt="<?php echo esc_attr($org_name); ?>"
							width="72" height="72"
							loading="eager" />
					</div>
				<?php endif; ?>
				<?php if ($org_name) : ?>
					<span class="hospitaliti-detail-banner-org"><?php echo esc_html($org_name); ?></span>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; // 'minimal' !== $theme 
	?>

	<!-- ═══════════════════════════════════════════════════════════════════════
	     SECTION 1 — Job information (transparent / theme background)
	     ═══════════════════════════════════════════════════════════════════════ -->
	<section class="hospitaliti-detail-section-info">
		<div class="hospitaliti-detail-container">

			<!-- ── Breadcrumb ─────────────────────────────────────────────────── -->
			<nav class="hospitaliti-detail-breadcrumb" <?php echo $reveal; ?> aria-label="<?php esc_attr_e('Breadcrumb', 'hospitaliti-jobs'); ?>">
				<a href="<?php echo esc_url($careers_url); ?>">
					<svg width="14" height="14" viewBox="0 0 16 16" fill="none"
						xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="1.6"
							stroke-linecap="round" stroke-linejoin="round" />
					</svg>
					<?php esc_html_e('Back to Careers', 'hospitaliti-jobs'); ?>
				</a>
			</nav>

			<!-- ── Job header ─────────────────────────────────────────────────── -->
			<header class="hospitaliti-detail-header" <?php echo $reveal; ?> data-hj-delay="1">

				<?php if ($org_name) : ?>
					<span class="hospitaliti-detail-org" itemprop="hiringOrganization"
						itemscope itemtype="https://schema.org/Organization">
						<span itemprop="name"><?php echo esc_html($org_name); ?></span>
					</span>
				<?php endif; ?>

				<h1 class="hospitaliti-detail-title" itemprop="title">
					<?php echo esc_html($job->title ?? ''); ?>
				</h1>

				<!-- Meta badges ──────────────────────────────────────────────── -->
				<div class="hospitaliti-detail-meta">

					<?php if ($type_label) : ?>
						<span class="hospitaliti-detail-badge hospitaliti-detail-badge--type">
							<svg width="13" height="13" viewBox="0 0 16 16" fill="none"
								xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<rect x="2" y="5" width="12" height="9" rx="1.5"
									stroke="currentColor" stroke-width="1.5" />
								<path d="M5 5V4a3 3 0 0 1 6 0v1"
									stroke="currentColor" stroke-width="1.5"
									stroke-linecap="round" />
							</svg>
							<?php echo esc_html($type_label); ?>
						</span>
					<?php endif; ?>

					<?php if ($location_str) : ?>
						<span class="hospitaliti-detail-badge hospitaliti-detail-badge--location"
							itemprop="jobLocation">
							<svg width="13" height="13" viewBox="0 0 16 16" fill="none"
								xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<path d="M8 2a4.5 4.5 0 0 1 4.5 4.5c0 3-4.5 7.5-4.5 7.5S3.5 9.5 3.5 6.5A4.5 4.5 0 0 1 8 2Z"
									stroke="currentColor" stroke-width="1.5" />
								<circle cx="8" cy="6.5" r="1.5" stroke="currentColor" stroke-width="1.5" />
							</svg>
							<?php echo esc_html($location_str); ?>
						</span>
					<?php endif; ?>

					<?php if ($salary_display) : ?>
						<span class="hospitaliti-detail-badge hospitaliti-detail-badge--salary"
							itemprop="baseSalary">
							<svg width="13" height="13" viewBox="0 0 16 16" fill="none"
								xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.5" />
								<path d="M8 4.5v7M5.5 6.5c0-1.1.9-2 2.5-2s2.5.9 2.5 2-2.5 2-2.5 2-2.5.9-2.5 2 .9 2 2.5 2 2.5-.9 2.5-2"
									stroke="currentColor" stroke-width="1.3" stroke-linecap="round" />
							</svg>
							<?php echo esc_html($salary_display); ?>
						</span>
					<?php endif; ?>

					<?php if ($starting_date_display) : ?>
						<span class="hospitaliti-detail-badge hospitaliti-detail-badge--date"
							itemprop="jobStartDate">
							<svg width="13" height="13" viewBox="0 0 16 16" fill="none"
								xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<rect x="2" y="3" width="12" height="11" rx="1.5"
									stroke="currentColor" stroke-width="1.5" />
								<path d="M5 2v2M11 2v2M2 7h12"
									stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
							</svg>
							<?php
							/* translators: %s: starting date */
							printf(esc_html__('Starting %s', 'hospitaliti-jobs'), esc_html($starting_date_display));
							?>
						</span>
					<?php endif; ?>

					<?php if (isset($job->applied_candidates)  && $job->applied_candidates  > 0) : ?>
						<span class="hospitaliti-detail-badge hospitaliti-detail-badge--candidates">
							<svg width="13" height="13" viewBox="0 0 16 16" fill="none"
								xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<circle cx="6" cy="5" r="2.5" stroke="currentColor" stroke-width="1.5" />
								<path d="M1.5 14c0-2.485 2.015-4.5 4.5-4.5s4.5 2.015 4.5 4.5"
									stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
								<circle cx="11.5" cy="5" r="2" stroke="currentColor" stroke-width="1.5" />
								<path d="M13.5 13c0-1.93-1.12-3.6-2.75-4.38"
									stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
							</svg>
							<?php
							/* translators: %d: number of candidates */
							printf(esc_html(_n('%d Candidate', '%d Candidates', (int) $job->applied_candidates, 'hospitaliti-jobs')), (int) $job->applied_candidates);
							?>
						</span>
					<?php endif; ?>

				</div><!-- .hospitaliti-detail-meta -->

			</header><!-- .hospitaliti-detail-header -->

			<!-- ── Job content ────────────────────────────────────────────────── -->
			<div class="hospitaliti-detail-content" <?php echo $reveal; ?> data-hj-delay="2">

				<?php if (! empty($job->description)) : ?>
					<div class="hospitaliti-detail-description" itemprop="description">
						<?php echo wp_kses_post($job->description); ?>
					</div>
				<?php endif; ?>

				<?php if (! empty($job->experience)) : ?>
					<div class="hospitaliti-detail-section">
						<h2 class="hospitaliti-detail-section-title">
							<?php esc_html_e('Experience', 'hospitaliti-jobs'); ?>
						</h2>
						<p><?php echo esc_html($job->experience); ?></p>
					</div>
				<?php endif; ?>

				<?php if (! empty($job->skills) && is_array($job->skills)) : ?>
					<div class="hospitaliti-detail-section">
						<h2 class="hospitaliti-detail-section-title">
							<?php esc_html_e('Skills', 'hospitaliti-jobs'); ?>
						</h2>
						<div class="hospitaliti-skill-tags">
							<?php foreach ($job->skills as $skill) :
								$skill_name = is_array($skill) ? ($skill['name'] ?? '') : (string) $skill;
								if (! $skill_name) {
									continue;
								}
							?>
								<span class="hospitaliti-skill-tag"><?php echo esc_html($skill_name); ?></span>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php if (! empty($job->working_schedule) && is_array($job->working_schedule)) : ?>
					<div class="hospitaliti-detail-section">
						<h2 class="hospitaliti-detail-section-title">
							<?php esc_html_e('Working Schedule', 'hospitaliti-jobs'); ?>
						</h2>
						<ul class="hospitaliti-detail-list">
							<?php foreach ($job->working_schedule as $item) :
								$item_text = is_array($item) ? ($item['name'] ?? '') : (string) $item;
								if (! $item_text) {
									continue;
								}
							?>
								<li><?php echo esc_html($item_text); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if (! empty($job->benefits) && is_array($job->benefits)) : ?>
					<div class="hospitaliti-detail-section">
						<h2 class="hospitaliti-detail-section-title">
							<?php esc_html_e('Benefits', 'hospitaliti-jobs'); ?>
						</h2>
						<ul class="hospitaliti-detail-list">
							<?php foreach ($job->benefits as $benefit) :
								$benefit_text = is_array($benefit) ? ($benefit['name'] ?? '') : (string) $benefit;
								if (! $benefit_text) {
									continue;
								}
							?>
								<li><?php echo esc_html($benefit_text); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

			</div><!-- .hospitaliti-detail-content -->

		</div><!-- .hospitaliti-detail-container -->
	</section><!-- .hospitaliti-detail-section-info -->

	<!-- ═══════════════════════════════════════════════════════════════════════
	     SECTION 2 — Apply form (primary-tinted background, top border)
	     ═══════════════════════════════════════════════════════════════════════ -->
	<section class="hospitaliti-detail-section-apply">
		<div class="hospitaliti-detail-container">

			<?php if ($apply_link) : ?>
				<!-- ── Direct apply link from API (Hosco login/form) ─────────────── -->
				<div class="hospitaliti-apply-section" <?php echo $reveal; ?>>
					<a href="<?php echo esc_url($apply_link); ?>"
						class="hospitaliti-apply-btn"
						target="_blank"
						rel="noopener noreferrer">
						<?php esc_html_e('Apply Now', 'hospitaliti-jobs'); ?>
						<svg width="14" height="14" viewBox="0 0 16 16" fill="none"
							xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
							<path d="M6 3h7v7M13 3L3 13"
								stroke="currentColor" stroke-width="1.6"
								stroke-linecap="round" stroke-linejoin="round" />
						</svg>
					</a>
				</div>
			<?php else : ?>
				<!-- ── Checkbox checked: redirect to Hospitaliti platform ─────────── -->
				<div class="hospitaliti-apply-section" <?php echo $reveal; ?>>
					<a href="<?php echo esc_url($platform_apply); ?>"
						class="hospitaliti-apply-btn"
						target="_blank"
						rel="noopener noreferrer">
						<?php esc_html_e('Apply Now', 'hospitaliti-jobs'); ?>
						<svg width="14" height="14" viewBox="0 0 16 16" fill="none"
							xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
							<path d="M6 3h7v7M13 3L3 13"
								stroke="currentColor" stroke-width="1.6"
								stroke-linecap="round" stroke-linejoin="round" />
						</svg>
					</a>
				</div> 
		<?php endif; ?>

			<!-- ── Bottom back link ───────────────────────────────────────────── -->
			<div class="hospitaliti-detail-back" <?php echo $reveal; ?> data-hj-delay="1">
				<a href="<?php echo esc_url($careers_url); ?>">
					&larr; <?php esc_html_e('Back to Careers', 'hospitaliti-jobs'); ?>
				</a>
			</div>

		</div><!-- .hospitaliti-detail-container -->
	</section><!-- .hospitaliti-detail-section-apply -->

</article><!-- .hospitaliti-job-detail -->

<?php get_footer(); ?>