<?php
/**
 * Plugin Uninstall
 *
 * Runs when the plugin is deleted via the WordPress admin (Plugins → Delete).
 * Removes ALL plugin options and cached API transients from the database.
 *
 * @package Hospitaliti_Jobs
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// ── Remove all plugin options ──────────────────────────────────────────────────

$options = [
	// ── Global / API ──────────────────────────────────────────────────────────
	'hospitaliti_jobs_api_url',
	'hospitaliti_jobs_cache_duration',
	'hospitaliti_jobs_sslverify',
	'hospitaliti_company_encid',
	'hospitaliti_careers_page_id',

	// ── Legacy global options (kept for migration fallback, now removed) ───────
	'hospitaliti_widget_title',
	'hospitaliti_theme',
	'hospitaliti_inherit_theme',
	'hospitaliti_primary_color',
	'hospitaliti_accent_color',
	'hospitaliti_bg_color',
	'hospitaliti_custom_css',

	// ── Job Listing widget ─────────────────────────────────────────────────────
	'hospitaliti_listing_title',
	'hospitaliti_listing_per_page',
	'hospitaliti_listing_show_search',
	'hospitaliti_listing_show_filter',
	'hospitaliti_listing_pagination',
	'hospitaliti_listing_theme',
	'hospitaliti_listing_inherit_theme',
	'hospitaliti_listing_primary_color',
	'hospitaliti_listing_accent_color',
	'hospitaliti_listing_bg_color',
	'hospitaliti_listing_custom_css',

	// ── Bubble Listing widget ──────────────────────────────────────────────────
	'hospitaliti_bubbles_title',
	'hospitaliti_bubbles_show_cta',
	'hospitaliti_bubbles_cta_text',
	'hospitaliti_bubbles_cta_url',
	'hospitaliti_bubbles_primary_color',
	'hospitaliti_bubbles_bg_color',
	'hospitaliti_bubbles_colors',
	// Legacy per-slot options (pre-consolidation):
	'hospitaliti_bubbles_color_1',
	'hospitaliti_bubbles_color_2',
	'hospitaliti_bubbles_color_3',
	'hospitaliti_bubbles_color_4',
	'hospitaliti_bubbles_color_5',
	'hospitaliti_bubbles_color_6',
	'hospitaliti_bubbles_back_url',
	'hospitaliti_bubbles_custom_css',

	// ── Application Form widget ────────────────────────────────────────────────
	'hospitaliti_form_title',
	'hospitaliti_form_subtitle',
	'hospitaliti_general_apply_endpoint',
	'hospitaliti_form_primary_color',
	'hospitaliti_form_bg_color',
	'hospitaliti_form_custom_css',

	// ── Job Detail Page ────────────────────────────────────────────────────────
	'hospitaliti_detail_theme',
	'hospitaliti_detail_primary_color',
	'hospitaliti_detail_accent_color',
	'hospitaliti_detail_bg_color',
	'hospitaliti_detail_inherit_theme',
	'hospitaliti_detail_back_url',
	'hospitaliti_detail_custom_css',
];

foreach ( $options as $option ) {
	delete_option( $option );
}

// ── Remove all cached API transients ──────────────────────────────────────────

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_hospitaliti_%'
	    OR option_name LIKE '_transient_timeout_hospitaliti_%'"
);
