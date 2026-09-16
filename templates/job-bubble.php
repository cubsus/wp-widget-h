<?php
/**
 * Template: Single Job Bubble
 *
 * Renders one circular bubble for a job listing.  The bubble shows the
 * organisation name (small, uppercase) above the job title (main text).
 * Clicking anywhere on the bubble navigates to the job detail page.
 *
 * Expected variables (injected by the loop in jobs-bubbles.php):
 *   $job               stdClass  Job object from the API response.
 *   $_org_name         string    Organisation name (pre-resolved by parent template).
 *   $_org_colour       string    Hex colour assigned to this organisation.
 *   $careers_slug      string    WP page slug (e.g. "careers") — empty = external link.
 *   $api_base_url      string    Hospitaliti API base URL (no trailing slash).
 *   $bubbles_back_url  string    Optional URL to embed as ?hj_back= so the detail
 *                                page's "Back to Careers" link returns here.
 *
 * @package Hospitaliti_Jobs
 */

defined( 'ABSPATH' ) || exit;

// ── Job URL ───────────────────────────────────────────────────────────────────
// Trim leading dashes the API sometimes returns.
$_bubble_slug = trim( $job->slug ?? '', '-' );

$_bubble_external = empty( $careers_slug );
$_bubble_url      = $_bubble_external
	? $api_base_url . '/job/' . rawurlencode( $_bubble_slug )
	: home_url( '/' . rawurlencode( $_bubble_slug ) . '/' );

// If a bubble-specific "Back to Careers" URL is configured, embed it as a
// query parameter so the detail page can use it for the back link.
$_bubbles_back = $bubbles_back_url ?? '';
if ( ! $_bubble_external && $_bubbles_back ) {
	$_bubble_url = add_query_arg( 'hj_back', rawurlencode( $_bubbles_back ), $_bubble_url );
}

// ── Inline style — only the background colour ─────────────────────────────────
// Text colour is always white (set in CSS); the colour itself is per-org.
$_bubble_style = 'background-color: ' . esc_attr( $_org_colour ) . ';';

// ── Data attribute used by JS for filtering ───────────────────────────────────
$_bubble_org_attr = esc_attr( $_org_name );
?>

<a class="hj-bubble"
	href="<?php echo esc_url( $_bubble_url ); ?>"
	data-org="<?php echo $_bubble_org_attr; ?>"
	style="<?php echo $_bubble_style; ?>"
	<?php if ( $_bubble_external ) : ?>
		target="_blank"
		rel="noopener noreferrer"
	<?php endif; ?>
	aria-label="<?php
		echo esc_attr(
			sprintf(
				/* translators: 1: job title, 2: organisation name */
				__( '%1$s at %2$s', 'hospitaliti-jobs' ),
				$job->title ?? '',
				$_org_name
			)
		);
	?>">

	<span class="hj-bubble-org"><?php echo esc_html( $_org_name ); ?></span>

	<span class="hj-bubble-title"><?php echo esc_html( $job->title ?? '' ); ?></span>

</a>
