<?php
/**
 * Template: Jobs Bubbles
 *
 * Renders a "Work For Us" section with:
 *   1. An optional section heading.
 *   2. Organisation filter badges (All + one per org).
 *   3. A wrapping grid of circular job bubbles.
 *   4. An optional CTA block at the bottom.
 *
 * Expected variables (set by JobsBubblesShortcode::render()):
 *   $jobs          array   Array of job stdClass objects.
 *   $orgColourMap  array   org_name => hex colour string.
 *   $orgCounts     array   org_name => job count int.
 *   $title         string  Section heading text.
 *   $showTitle     bool    Whether to render the heading.
 *   $showCta       bool    Whether to render the CTA block.
 *   $ctaText       string  CTA button label.
 *   $ctaUrl        string  CTA button href.
 *   $theme         string  'default' | 'minimal'.
 *   $careers_slug  string  WP page slug for career detail URLs.
 *   $api_base_url  string  Hospitaliti API base URL (no trailing slash).
 *
 * @package Hospitaliti_Jobs
 */

defined( 'ABSPATH' ) || exit;

// ── Safe defaults ─────────────────────────────────────────────────────────────
$theme        = ( isset( $theme ) && in_array( $theme, [ 'default', 'minimal' ], true ) ) ? $theme : 'default';
$title        = $title        ?? __( 'Work For Us', 'hospitaliti-jobs' );
$showTitle    = $showTitle    ?? true;
$showCta      = $showCta      ?? true;
$ctaText      = $ctaText      ?? __( 'Submit your application', 'hospitaliti-jobs' );
$ctaUrl       = $ctaUrl       ?? '#';
$jobs         = $jobs         ?? [];
$orgColourMap = $orgColourMap ?? [];
$orgCounts    = $orgCounts    ?? [];
$careers_slug = $careers_slug ?? '';
$api_base_url = isset( $api_base_url )
	? rtrim( $api_base_url, '/' )
	: rtrim( get_option( 'hospitaliti_jobs_api_url', HOSPITALITI_JOBS_DEFAULT_API_URL ), '/' );

$totalJobs = count( $jobs );
?>

<div class="hj-bubbles-section hospitaliti-theme-<?php echo esc_attr( $theme ); ?>">

	<?php if ( $showTitle && ! empty( $title ) ) : ?>
	<!-- ── Section heading ─────────────────────────────────────────────────── -->
	<div class="hj-bubbles-heading-wrap">
		<h2 class="hj-bubbles-heading"><?php echo esc_html( $title ); ?></h2>
	</div>
	<?php endif; ?>

	<?php if ( ! empty( $orgColourMap ) ) : ?>
	<!-- ── Organisation filter badges ──────────────────────────────────────── -->
	<div class="hj-filter-badges" role="group"
		aria-label="<?php esc_attr_e( 'Filter jobs by organisation', 'hospitaliti-jobs' ); ?>">

		<button class="hj-filter-badge is-active" type="button"
			data-org="all"
			aria-pressed="true">
			<?php esc_html_e( 'All', 'hospitaliti-jobs' ); ?>
			<span class="hj-filter-badge-count">
				<?php echo esc_html( $totalJobs ); ?>
			</span>
		</button>

		<?php
		// Badges are listed alphabetically; colours keep their first-appearance order.
		$_badge_orgs = $orgColourMap;
		uksort( $_badge_orgs, 'strnatcasecmp' );
		foreach ( $_badge_orgs as $orgName => $colour ) :
		?>
			<button class="hj-filter-badge" type="button"
				data-org="<?php echo esc_attr( $orgName ); ?>"
				aria-pressed="false">
				<span class="hj-filter-badge-dot"
					style="background-color: <?php echo esc_attr( $colour ); ?>;"
					aria-hidden="true"></span>
				<?php echo esc_html( $orgName ); ?>
				<span class="hj-filter-badge-count">
					<?php echo esc_html( $orgCounts[ $orgName ] ?? 0 ); ?>
				</span>
			</button>
		<?php endforeach; ?>

	</div><!-- .hj-filter-badges -->
	<?php endif; ?>

	<!-- ── Bubble grid ──────────────────────────────────────────────────────── -->
	<div class="hj-bubbles-wrap">

		<?php if ( empty( $jobs ) ) : ?>
			<p class="hj-bubbles-empty">
				<?php esc_html_e( 'No positions available at the moment.', 'hospitaliti-jobs' ); ?>
			</p>
		<?php else : ?>
			<?php foreach ( $jobs as $job ) :
				// Resolve the org name and colour for this job.
			$_org_data   = is_array( $job->organization ?? null )
				? $job->organization
				: (array) ( $job->organization ?? [] );
			$_org_name   = $_org_data['name'] ?? __( 'Other', 'hospitaliti-jobs' );
			$_org_colour = $orgColourMap[ $_org_name ] ?? ( $palette[0] ?? '#143f2b' );
				include __DIR__ . '/job-bubble.php';
			endforeach; ?>
		<?php endif; ?>

	</div><!-- .hj-bubbles-wrap -->

	<?php if ( $showCta && ! empty( $ctaUrl ) ) : ?>
	<!-- ── Bottom CTA ──────────────────────────────────────────────────────── -->
	<div class="hj-cta-section">
		<p class="hj-cta-label">
			<?php esc_html_e( 'Or you can send us your application', 'hospitaliti-jobs' ); ?>
		</p>
		<a class="hj-cta-btn" href="<?php echo esc_url( $ctaUrl ); ?>">
			<?php echo esc_html( $ctaText ); ?>
		</a>
	</div><!-- .hj-cta-section -->
	<?php endif; ?>

</div><!-- .hj-bubbles-section -->
