<?php

/**
 * Admin settings page — Settings → Hospitaliti Jobs.
 *
 * Restructured into 6 tabs so each widget has its own settings:
 *   api       — API & Connection  (global, shared by all widgets)
 *   listing   — Job Listing       ([hospitaliti_jobs] shortcode + sidebar widget)
 *   bubbles   — Bubble Listing    ([hospitaliti_jobs_bubbles] shortcode)
 *   form      — Application Form  ([hospitaliti_apply_form] shortcode)
 *   detail    — Job Detail Page   (virtual /careers/{slug}/ page)
 *   tools     — Tools & Shortcodes
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs\Admin;

use Hospitaliti\Jobs\Job\JobRepository;
use Hospitaliti\Jobs\Theme\ThemeDetector;

defined( 'ABSPATH' ) || exit;

class SettingsPage {

	/** Valid tab slugs */
	private const TABS = [ 'api', 'listing', 'bubbles', 'detail', 'tools' ];

	public function register(): void {
		add_action( 'admin_menu',                          [ $this, 'addPage' ] );
		add_action( 'admin_init',                          [ $this, 'registerSettings' ] );
		add_action( 'admin_enqueue_scripts',               [ $this, 'enqueueColorPicker' ] );
		add_action( 'wp_ajax_hospitaliti_test_connection', [ $this, 'ajaxTestConnection' ] );
	}

	/* ── Menu ────────────────────────────────────────────────────────────── */

	public function addPage(): void {
		add_options_page(
			__( 'Hospitaliti Jobs', 'hospitaliti-jobs' ),
			__( 'Hospitaliti Jobs', 'hospitaliti-jobs' ),
			'manage_options',
			'hospitaliti-jobs',
			[ $this, 'renderPage' ]
		);
	}

	public function enqueueColorPicker( string $hook ): void {
		if ( 'settings_page_hospitaliti-jobs' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script(
			'hospitaliti-settings',
			HOSPITALITI_JOBS_PLUGIN_URL . 'assets/js/hospitaliti-settings.js',
			[ 'wp-color-picker' ],
			HOSPITALITI_JOBS_VERSION,
			true
		);
		wp_localize_script( 'hospitaliti-settings', 'hospitalitiAdmin', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'hospitaliti_test_api' ),
		] );
	}

	/* ── Settings registration ───────────────────────────────────────────── */

	public function registerSettings(): void {
		// ── GROUP 1: API & Connection ─────────────────────────────────────
		$api = 'hospitaliti_api';
		register_setting( $api, 'hospitaliti_jobs_api_url',          [ 'sanitize_callback' => 'esc_url_raw',          'default' => HOSPITALITI_JOBS_DEFAULT_API_URL ] );
		register_setting( $api, 'hospitaliti_jobs_cache_duration',   [ 'sanitize_callback' => 'absint',               'default' => 30 ] );
		register_setting( $api, 'hospitaliti_jobs_sslverify',        [ 'sanitize_callback' => static fn($v) => $v ? 1 : 0, 'default' => 0 ] );
		register_setting( $api, 'hospitaliti_company_encid',         [ 'sanitize_callback' => 'sanitize_text_field',  'default' => '' ] );
		add_action( 'update_option_hospitaliti_careers_page_id', 'flush_rewrite_rules' );
		register_setting( $api, 'hospitaliti_careers_page_id',       [ 'sanitize_callback' => 'absint',               'default' => 0 ] );

		// ── GROUP 2: Job Listing ──────────────────────────────────────────
		$listing = 'hospitaliti_listing';
		register_setting( $listing, 'hospitaliti_listing_title',         [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'Work For Us' ] );
		register_setting( $listing, 'hospitaliti_listing_per_page',      [ 'sanitize_callback' => static fn($v) => max( 1, min( (int) $v, 50 ) ), 'default' => 10 ] );
		register_setting( $listing, 'hospitaliti_listing_show_search',   [ 'sanitize_callback' => static fn($v) => $v ? 1 : 0, 'default' => 1 ] );
		register_setting( $listing, 'hospitaliti_listing_show_filter',   [ 'sanitize_callback' => static fn($v) => $v ? 1 : 0, 'default' => 1 ] );
		register_setting( $listing, 'hospitaliti_listing_pagination',    [ 'sanitize_callback' => static fn($v) => in_array( $v, [ 'paged', 'load_more' ], true ) ? $v : 'paged', 'default' => 'paged' ] );
		register_setting( $listing, 'hospitaliti_listing_theme',         [ 'sanitize_callback' => static fn($v) => in_array( $v, [ 'default', 'minimal' ], true ) ? $v : 'default', 'default' => 'default' ] );
		register_setting( $listing, 'hospitaliti_listing_inherit_theme', [ 'sanitize_callback' => static fn($v) => $v ? 1 : 0, 'default' => 0 ] );
		register_setting( $listing, 'hospitaliti_listing_primary_color', [ 'sanitize_callback' => 'sanitize_hex_color', 'default' => '#523d3f' ] );
		register_setting( $listing, 'hospitaliti_listing_accent_color',  [ 'sanitize_callback' => 'sanitize_hex_color', 'default' => '' ] );
		register_setting( $listing, 'hospitaliti_listing_bg_color',      [ 'sanitize_callback' => 'sanitize_hex_color', 'default' => '' ] );
		register_setting( $listing, 'hospitaliti_listing_custom_css',    [ 'sanitize_callback' => 'wp_strip_all_tags',  'default' => '' ] );
		register_setting( $listing, 'hospitaliti_listing_intro_text',   [ 'sanitize_callback' => 'wp_kses_post',       'default' => '' ] );
		register_setting( $listing, 'hospitaliti_detail_back_url',      [ 'sanitize_callback' => 'esc_url_raw',        'default' => '' ] );

		// ── GROUP 3: Bubble Listing ───────────────────────────────────────
		$bubbles = 'hospitaliti_bubbles';
		register_setting( $bubbles, 'hospitaliti_bubbles_title',       [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'Work For Us' ] );
		register_setting( $bubbles, 'hospitaliti_bubbles_show_cta',    [ 'sanitize_callback' => static fn($v) => $v ? 1 : 0, 'default' => 1 ] );
		register_setting( $bubbles, 'hospitaliti_bubbles_cta_text',    [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'Submit your application' ] );
		register_setting( $bubbles, 'hospitaliti_bubbles_cta_url',     [ 'sanitize_callback' => 'esc_url_raw',         'default' => '' ] );
		register_setting( $bubbles, 'hospitaliti_bubbles_primary_color', [ 'sanitize_callback' => 'sanitize_hex_color', 'default' => '#523d3f' ] );
		register_setting( $bubbles, 'hospitaliti_bubbles_bg_color',    [ 'sanitize_callback' => 'sanitize_hex_color', 'default' => '#e7e5e1' ] );
		register_setting( $bubbles, 'hospitaliti_bubbles_colors', [
			'sanitize_callback' => static function( $val ) {
				$colors = array_values( array_filter( array_map( 'sanitize_hex_color', (array) $val ) ) );
				return $colors ?: [ '#143f2b' ];
			},
		] );
		register_setting( $bubbles, 'hospitaliti_bubbles_back_url',    [ 'sanitize_callback' => 'esc_url_raw',        'default' => '' ] );
		register_setting( $bubbles, 'hospitaliti_bubbles_custom_css',  [ 'sanitize_callback' => 'wp_strip_all_tags',  'default' => '' ] );

		// ── GROUP 4: Job Detail Page ──────────────────────────────────────
		$detail = 'hospitaliti_detail';
		register_setting( $detail, 'hospitaliti_detail_theme',         [ 'sanitize_callback' => static fn($v) => in_array( $v, [ 'default', 'minimal' ], true ) ? $v : 'default', 'default' => 'default' ] );
		register_setting( $detail, 'hospitaliti_detail_primary_color', [ 'sanitize_callback' => 'sanitize_hex_color', 'default' => '#523d3f' ] );
		register_setting( $detail, 'hospitaliti_detail_accent_color',  [ 'sanitize_callback' => 'sanitize_hex_color', 'default' => '' ] );
		register_setting( $detail, 'hospitaliti_detail_bg_color',      [ 'sanitize_callback' => 'sanitize_hex_color', 'default' => '' ] );
		register_setting( $detail, 'hospitaliti_detail_inherit_theme', [ 'sanitize_callback' => static fn($v) => $v ? 1 : 0, 'default' => 0 ] );
		register_setting( $detail, 'hospitaliti_detail_custom_css',    [ 'sanitize_callback' => 'wp_strip_all_tags',  'default' => '' ] );
	}

	/* ── Page render ─────────────────────────────────────────────────────── */

	public function renderPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$currentTab = isset( $_GET['tab'] ) && in_array( $_GET['tab'], self::TABS, true )
			? $_GET['tab']
			: 'api';

		// ── Tool actions ──────────────────────────────────────────────────
		if ( isset( $_POST['hospitaliti_flush_cache'] ) && check_admin_referer( 'hospitaliti_flush_cache' ) ) {
			JobRepository::flushAll();
			add_settings_error( 'hospitaliti_tools', 'cache_flushed', __( 'Jobs cache flushed successfully.', 'hospitaliti-jobs' ), 'updated' );
		}
		if ( isset( $_POST['hospitaliti_flush_rewrites'] ) && check_admin_referer( 'hospitaliti_flush_rewrites' ) ) {
			flush_rewrite_rules();
			add_settings_error( 'hospitaliti_tools', 'rewrites_flushed', __( 'Rewrite rules flushed.', 'hospitaliti-jobs' ), 'updated' );
		}
		settings_errors();

		$activeTheme  = ThemeDetector::getActiveThemeSlug();
		$adapterClass = get_class( ThemeDetector::detect() );
		$adapterShort = substr( $adapterClass, strrpos( $adapterClass, '\\' ) + 1 );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Hospitaliti Jobs Settings', 'hospitaliti-jobs' ); ?></h1>

			<!-- ── Tab navigation ──────────────────────────────────────── -->
			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings sections', 'hospitaliti-jobs' ); ?>">
				<?php
		$tabs = [
				'api'     => __( 'API & Connection',   'hospitaliti-jobs' ),
				'listing' => __( 'Job Listing',         'hospitaliti-jobs' ),
				'bubbles' => __( 'Bubble Listing',      'hospitaliti-jobs' ),
				'detail'  => __( 'Job Detail Page',     'hospitaliti-jobs' ),
				'tools'   => __( 'Tools & Shortcodes',  'hospitaliti-jobs' ),
			];
				foreach ( $tabs as $slug => $label ) :
					$url = admin_url( 'options-general.php?page=hospitaliti-jobs&tab=' . $slug );
					$class = 'nav-tab' . ( $currentTab === $slug ? ' nav-tab-active' : '' );
				?>
				<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>">
					<?php echo esc_html( $label ); ?>
				</a>
				<?php endforeach; ?>
			</nav>

			<!-- ── Tab content ─────────────────────────────────────────── -->
			<?php
			switch ( $currentTab ) {
				case 'api':     $this->renderTabApi();     break;
				case 'listing': $this->renderTabListing(); break;
				case 'bubbles': $this->renderTabBubbles(); break;
				case 'detail':  $this->renderTabDetail();  break;
				case 'tools':   $this->renderTabTools( $adapterShort, $activeTheme ); break;
			}
			?>

		</div><!-- .wrap -->
		<?php
	}

	/* ── Tab: API & Connection ───────────────────────────────────────────── */

	private function renderTabApi(): void {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'hospitaliti_api' ); ?>

			<h2><?php esc_html_e( 'API & Connection', 'hospitaliti-jobs' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="hospitaliti_jobs_api_url"><?php esc_html_e( 'Hospitaliti API URL', 'hospitaliti-jobs' ); ?></label></th>
					<td>
						<input type="url" id="hospitaliti_jobs_api_url" name="hospitaliti_jobs_api_url"
							value="<?php echo esc_attr( get_option( 'hospitaliti_jobs_api_url', HOSPITALITI_JOBS_DEFAULT_API_URL ) ); ?>"
							class="regular-text" />
						<p class="description"><?php esc_html_e( 'Base URL of your Hospitaliti installation (no trailing slash).', 'hospitaliti-jobs' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="hospitaliti_jobs_cache_duration"><?php esc_html_e( 'Cache Duration (minutes)', 'hospitaliti-jobs' ); ?></label></th>
					<td>
						<input type="number" id="hospitaliti_jobs_cache_duration" name="hospitaliti_jobs_cache_duration"
							value="<?php echo esc_attr( get_option( 'hospitaliti_jobs_cache_duration', 30 ) ); ?>"
							min="0" max="1440" class="small-text" />
						<p class="description"><?php esc_html_e( 'Set to 0 to disable caching.', 'hospitaliti-jobs' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'SSL Verification', 'hospitaliti-jobs' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hospitaliti_jobs_sslverify" value="1"
								<?php checked( 1, get_option( 'hospitaliti_jobs_sslverify', 0 ) ); ?> />
							<?php esc_html_e( 'Enable SSL certificate verification', 'hospitaliti-jobs' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Disable on local/dev environments. Enable in production.', 'hospitaliti-jobs' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="hospitaliti_company_encid"><?php esc_html_e( 'Company / Group Enc ID', 'hospitaliti-jobs' ); ?></label></th>
					<td>
						<input type="text" id="hospitaliti_company_encid" name="hospitaliti_company_encid"
							value="<?php echo esc_attr( get_option( 'hospitaliti_company_encid', '' ) ); ?>"
							class="regular-text" placeholder="e.g. abc123xyz" />
					</td>
				</tr>
				<tr>
					<th><label for="hospitaliti_careers_page_id"><?php esc_html_e( 'Careers Base Page', 'hospitaliti-jobs' ); ?></label></th>
					<td>
						<?php wp_dropdown_pages( [
							'name'              => 'hospitaliti_careers_page_id',
							'id'                => 'hospitaliti_careers_page_id',
							'selected'          => get_option( 'hospitaliti_careers_page_id', 0 ),
							'show_option_none'  => __( '— Select a page —', 'hospitaliti-jobs' ),
							'option_none_value' => '0',
						] ); ?>
						<p class="description"><?php esc_html_e( 'Job detail pages served at /{slug}/{job-slug}/. Click "Flush Rewrite Rules" in Tools after changing.', 'hospitaliti-jobs' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save API Settings', 'hospitaliti-jobs' ) ); ?>
		</form>

		<hr style="margin:24px 0 20px" />
		<h3 style="margin:0 0 6px"><?php esc_html_e( 'Connection Test', 'hospitaliti-jobs' ); ?></h3>
		<p class="description" style="margin-bottom:10px"><?php esc_html_e( 'Verify the current API URL and Enc ID can reach the Hospitaliti API.', 'hospitaliti-jobs' ); ?></p>
		<button type="button" id="hj-test-connection" class="button button-secondary">
			<?php esc_html_e( 'Test API Connection', 'hospitaliti-jobs' ); ?>
		</button>
		<span id="hj-test-result" style="margin-left:12px;line-height:30px;display:none"></span>
		<?php
	}

	/* ── Tab: Job Listing ────────────────────────────────────────────────── */

	private function renderTabListing(): void {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'hospitaliti_listing' ); ?>

			<h2><?php esc_html_e( 'Job Listing Widget', 'hospitaliti-jobs' ); ?></h2>
			<p><?php esc_html_e( 'Settings for the [hospitaliti_jobs] shortcode and the classic sidebar widget.', 'hospitaliti-jobs' ); ?></p>

			<h3><?php esc_html_e( 'Display', 'hospitaliti-jobs' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="hospitaliti_listing_title"><?php esc_html_e( 'Section Title', 'hospitaliti-jobs' ); ?></label></th>
					<td><input type="text" id="hospitaliti_listing_title" name="hospitaliti_listing_title"
						value="<?php echo esc_attr( get_option( 'hospitaliti_listing_title', 'Work For Us' ) ); ?>"
						class="regular-text" /></td>
				</tr>
				<tr>
					<th><label for="hospitaliti_listing_per_page"><?php esc_html_e( 'Jobs Per Page', 'hospitaliti-jobs' ); ?></label></th>
					<td><input type="number" id="hospitaliti_listing_per_page" name="hospitaliti_listing_per_page"
						value="<?php echo esc_attr( get_option( 'hospitaliti_listing_per_page', 10 ) ); ?>"
						min="1" max="50" class="small-text" /></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Show Search Bar', 'hospitaliti-jobs' ); ?></th>
					<td><label><input type="checkbox" name="hospitaliti_listing_show_search" value="1"
						<?php checked( 1, get_option( 'hospitaliti_listing_show_search', 1 ) ); ?> />
						<?php esc_html_e( 'Display keyword search bar', 'hospitaliti-jobs' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Show Filter Sidebar', 'hospitaliti-jobs' ); ?></th>
					<td><label><input type="checkbox" name="hospitaliti_listing_show_filter" value="1"
						<?php checked( 1, get_option( 'hospitaliti_listing_show_filter', 1 ) ); ?> />
						<?php esc_html_e( 'Display employment type and experience filters', 'hospitaliti-jobs' ); ?></label></td>
				</tr>
				<tr>
					<th><label for="hospitaliti_listing_pagination"><?php esc_html_e( 'Pagination Style', 'hospitaliti-jobs' ); ?></label></th>
					<td>
						<select id="hospitaliti_listing_pagination" name="hospitaliti_listing_pagination">
							<option value="paged"     <?php selected( get_option( 'hospitaliti_listing_pagination', 'paged' ), 'paged' ); ?>><?php esc_html_e( 'Prev / Next pages', 'hospitaliti-jobs' ); ?></option>
							<option value="load_more" <?php selected( get_option( 'hospitaliti_listing_pagination', 'paged' ), 'load_more' ); ?>><?php esc_html_e( 'Load More button', 'hospitaliti-jobs' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="hospitaliti_detail_back_url"><?php esc_html_e( '"Back to Careers" URL', 'hospitaliti-jobs' ); ?></label></th>
					<td>
						<input type="url" id="hospitaliti_detail_back_url" name="hospitaliti_detail_back_url"
							value="<?php echo esc_attr( get_option( 'hospitaliti_detail_back_url', '' ) ); ?>"
							class="regular-text"
							placeholder="https://yoursite.com/careers/" />
						<p class="description">
							<?php esc_html_e( 'Custom URL for the "← Back to Careers" link shown at the top and bottom of every job detail page.', 'hospitaliti-jobs' ); ?>
							<?php esc_html_e( 'Leave blank to use the Careers Base Page set in the API & Connection tab.', 'hospitaliti-jobs' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Appearance', 'hospitaliti-jobs' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="hospitaliti_listing_theme"><?php esc_html_e( 'Theme', 'hospitaliti-jobs' ); ?></label></th>
					<td>
						<select id="hospitaliti_listing_theme" name="hospitaliti_listing_theme">
							<option value="default" <?php selected( get_option( 'hospitaliti_listing_theme', 'default' ), 'default' ); ?>><?php esc_html_e( 'Default (Dark header)', 'hospitaliti-jobs' ); ?></option>
							<option value="minimal" <?php selected( get_option( 'hospitaliti_listing_theme', 'default' ), 'minimal' ); ?>><?php esc_html_e( 'Minimal (Light)', 'hospitaliti-jobs' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Inherit Theme Styles', 'hospitaliti-jobs' ); ?></th>
					<td><label>
						<input type="checkbox" name="hospitaliti_listing_inherit_theme" value="1"
							<?php checked( 1, get_option( 'hospitaliti_listing_inherit_theme', 0 ) ); ?> />
						<?php esc_html_e( 'Use active theme colour palette automatically (disables colour pickers below)', 'hospitaliti-jobs' ); ?>
					</label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Primary Color', 'hospitaliti-jobs' ); ?></th>
					<td><input type="text" name="hospitaliti_listing_primary_color"
						value="<?php echo esc_attr( get_option( 'hospitaliti_listing_primary_color', '#523d3f' ) ); ?>"
						class="hospitaliti-color-picker" /></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Accent / Text on Primary', 'hospitaliti-jobs' ); ?></th>
					<td><input type="text" name="hospitaliti_listing_accent_color"
						value="<?php echo esc_attr( get_option( 'hospitaliti_listing_accent_color', '' ) ); ?>"
						class="hospitaliti-color-picker" /></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Header Background Color', 'hospitaliti-jobs' ); ?></th>
					<td><input type="text" name="hospitaliti_listing_bg_color"
						value="<?php echo esc_attr( get_option( 'hospitaliti_listing_bg_color', '' ) ); ?>"
						class="hospitaliti-color-picker" /></td>
				</tr>
				<tr>
			<th><label for="hospitaliti_listing_intro_text"><?php esc_html_e( 'Intro Text', 'hospitaliti-jobs' ); ?></label></th>
				<td>
					<textarea id="hospitaliti_listing_intro_text" name="hospitaliti_listing_intro_text"
						rows="4" class="large-text"><?php echo esc_textarea( get_option( 'hospitaliti_listing_intro_text', '' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Optional text shown above the job listing. Basic HTML allowed (links, bold, italic).', 'hospitaliti-jobs' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="hospitaliti_listing_custom_css"><?php esc_html_e( 'Custom CSS', 'hospitaliti-jobs' ); ?></label></th>
				<td>
					<textarea id="hospitaliti_listing_custom_css" name="hospitaliti_listing_custom_css"
						rows="6" class="large-text code"><?php echo esc_textarea( get_option( 'hospitaliti_listing_custom_css', '' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Additional CSS scoped to this widget only.', 'hospitaliti-jobs' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Save Listing Settings', 'hospitaliti-jobs' ) ); ?>
	</form>
		<?php
	}

	/* ── Tab: Bubble Listing ─────────────────────────────────────────────── */

	private function renderTabBubbles(): void {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'hospitaliti_bubbles' ); ?>

			<h2><?php esc_html_e( 'Bubble Listing Widget', 'hospitaliti-jobs' ); ?></h2>
			<p><?php esc_html_e( 'Settings for the [hospitaliti_jobs_bubbles] shortcode.', 'hospitaliti-jobs' ); ?></p>

			<h3><?php esc_html_e( 'Display', 'hospitaliti-jobs' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="hospitaliti_bubbles_title"><?php esc_html_e( 'Section Title', 'hospitaliti-jobs' ); ?></label></th>
					<td><input type="text" id="hospitaliti_bubbles_title" name="hospitaliti_bubbles_title"
						value="<?php echo esc_attr( get_option( 'hospitaliti_bubbles_title', 'Work For Us' ) ); ?>"
						class="regular-text" /></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Show CTA Section', 'hospitaliti-jobs' ); ?></th>
					<td><label><input type="checkbox" name="hospitaliti_bubbles_show_cta" value="1"
						<?php checked( 1, get_option( 'hospitaliti_bubbles_show_cta', 1 ) ); ?> />
						<?php esc_html_e( 'Show "Or you can send us your application" block below the bubbles', 'hospitaliti-jobs' ); ?></label></td>
				</tr>
				<tr>
					<th><label for="hospitaliti_bubbles_cta_text"><?php esc_html_e( 'CTA Button Label', 'hospitaliti-jobs' ); ?></label></th>
					<td><input type="text" id="hospitaliti_bubbles_cta_text" name="hospitaliti_bubbles_cta_text"
						value="<?php echo esc_attr( get_option( 'hospitaliti_bubbles_cta_text', 'Submit your application' ) ); ?>"
						class="regular-text" /></td>
				</tr>
				<tr>
					<th><label for="hospitaliti_bubbles_cta_url"><?php esc_html_e( 'CTA Button URL', 'hospitaliti-jobs' ); ?></label></th>
					<td>
						<input type="url" id="hospitaliti_bubbles_cta_url" name="hospitaliti_bubbles_cta_url"
							value="<?php echo esc_attr( get_option( 'hospitaliti_bubbles_cta_url', '' ) ); ?>"
							class="regular-text" placeholder="https://yoursite.com/apply/" />
						<p class="description"><?php esc_html_e( 'URL for the CTA button below the bubbles.', 'hospitaliti-jobs' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="hospitaliti_bubbles_back_url"><?php esc_html_e( '"Back to Careers" URL', 'hospitaliti-jobs' ); ?></label></th>
					<td>
						<input type="url" id="hospitaliti_bubbles_back_url" name="hospitaliti_bubbles_back_url"
							value="<?php echo esc_attr( get_option( 'hospitaliti_bubbles_back_url', '' ) ); ?>"
							class="regular-text"
							placeholder="https://yoursite.com/" />
						<p class="description">
							<?php esc_html_e( 'When a visitor clicks a bubble and views a job detail page, the "← Back to Careers" link will point here — typically the page that contains this bubble listing.', 'hospitaliti-jobs' ); ?>
							<br>
							<?php esc_html_e( 'Leave blank to fall back to the standard Careers page URL.', 'hospitaliti-jobs' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Appearance', 'hospitaliti-jobs' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Primary / Badge Color', 'hospitaliti-jobs' ); ?></th>
					<td>
						<input type="text" name="hospitaliti_bubbles_primary_color"
							value="<?php echo esc_attr( get_option( 'hospitaliti_bubbles_primary_color', '#523d3f' ) ); ?>"
							class="hospitaliti-color-picker" />
						<p class="description"><?php esc_html_e( 'Used for the active filter badge and the CTA button.', 'hospitaliti-jobs' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Section Background Color', 'hospitaliti-jobs' ); ?></th>
					<td><input type="text" name="hospitaliti_bubbles_bg_color"
						value="<?php echo esc_attr( get_option( 'hospitaliti_bubbles_bg_color', '#e7e5e1' ) ); ?>"
						class="hospitaliti-color-picker" /></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Bubble Color Palette', 'hospitaliti-jobs' ); ?></th>
					<td>
						<p class="description" style="margin-bottom:8px"><?php esc_html_e( 'Colors assigned to organisations in order (cycles when there are more orgs than colors).', 'hospitaliti-jobs' ); ?></p>
					<?php
					$_pal_defaults = [ '#143f2b', '#9ca998', '#c6baab', '#9baa65', '#da291c', '#523d3f' ];
					$_pal_saved    = (array) get_option( 'hospitaliti_bubbles_colors', $_pal_defaults );
					if ( empty( $_pal_saved ) ) { $_pal_saved = $_pal_defaults; }
					?>
					<div id="hj-palette-wrap" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start">
						<?php foreach ( $_pal_saved as $_pal_hex ) : ?>
						<div class="hj-palette-item" style="text-align:center">
							<input type="text" name="hospitaliti_bubbles_colors[]"
								value="<?php echo esc_attr( $_pal_hex ); ?>"
								class="hospitaliti-color-picker" />
							<button type="button" class="button hj-remove-color" style="margin-top:4px;display:block;width:100%">
								<?php esc_html_e( 'Remove', 'hospitaliti-jobs' ); ?>
							</button>
						</div>
						<?php endforeach; ?>
					</div>
					<button type="button" id="hj-add-color" class="button" style="margin-top:12px">
						<?php esc_html_e( '+ Add Color', 'hospitaliti-jobs' ); ?>
					</button>
					</td>
				</tr>
				<tr>
					<th><label for="hospitaliti_bubbles_custom_css"><?php esc_html_e( 'Custom CSS', 'hospitaliti-jobs' ); ?></label></th>
					<td>
						<textarea id="hospitaliti_bubbles_custom_css" name="hospitaliti_bubbles_custom_css"
							rows="6" class="large-text code"><?php echo esc_textarea( get_option( 'hospitaliti_bubbles_custom_css', '' ) ); ?></textarea>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save Bubble Settings', 'hospitaliti-jobs' ) ); ?>
		</form>
		<?php
	}

	/* ── Tab: Job Detail Page ────────────────────────────────────────────── */

	private function renderTabDetail(): void {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'hospitaliti_detail' ); ?>

		<h2><?php esc_html_e( 'Job Detail Page', 'hospitaliti-jobs' ); ?></h2>
		<p>
			<?php esc_html_e( 'Settings for the virtual job detail page. This page shows full job information and an Apply Now button linking directly to the Hospitaliti platform.', 'hospitaliti-jobs' ); ?>
		</p>

		<h3><?php esc_html_e( 'Appearance', 'hospitaliti-jobs' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="hospitaliti_detail_theme"><?php esc_html_e( 'Theme', 'hospitaliti-jobs' ); ?></label></th>
					<td>
						<select id="hospitaliti_detail_theme" name="hospitaliti_detail_theme">
							<option value="default" <?php selected( get_option( 'hospitaliti_detail_theme', 'default' ), 'default' ); ?>><?php esc_html_e( 'Default (Dark banner)', 'hospitaliti-jobs' ); ?></option>
							<option value="minimal" <?php selected( get_option( 'hospitaliti_detail_theme', 'default' ), 'minimal' ); ?>><?php esc_html_e( 'Minimal (Light, no banner)', 'hospitaliti-jobs' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Inherit Theme Styles', 'hospitaliti-jobs' ); ?></th>
					<td><label>
						<input type="checkbox" name="hospitaliti_detail_inherit_theme" value="1"
							<?php checked( 1, get_option( 'hospitaliti_detail_inherit_theme', 0 ) ); ?> />
						<?php esc_html_e( 'Use active theme colour palette automatically', 'hospitaliti-jobs' ); ?>
					</label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Primary Color', 'hospitaliti-jobs' ); ?></th>
					<td>
						<input type="text" name="hospitaliti_detail_primary_color"
							value="<?php echo esc_attr( get_option( 'hospitaliti_detail_primary_color', '#523d3f' ) ); ?>"
							class="hospitaliti-color-picker" />
						<p class="description"><?php esc_html_e( 'Used for badges, section titles, breadcrumb links, and the apply button.', 'hospitaliti-jobs' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Accent / Text on Primary', 'hospitaliti-jobs' ); ?></th>
					<td><input type="text" name="hospitaliti_detail_accent_color"
						value="<?php echo esc_attr( get_option( 'hospitaliti_detail_accent_color', '' ) ); ?>"
						class="hospitaliti-color-picker" /></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Banner Background Color', 'hospitaliti-jobs' ); ?></th>
					<td>
						<input type="text" name="hospitaliti_detail_bg_color"
							value="<?php echo esc_attr( get_option( 'hospitaliti_detail_bg_color', '' ) ); ?>"
							class="hospitaliti-color-picker" />
						<p class="description"><?php esc_html_e( 'Fallback color for the organisation banner when no banner image is set.', 'hospitaliti-jobs' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="hospitaliti_detail_custom_css"><?php esc_html_e( 'Custom CSS', 'hospitaliti-jobs' ); ?></label></th>
					<td>
						<textarea id="hospitaliti_detail_custom_css" name="hospitaliti_detail_custom_css"
							rows="6" class="large-text code"><?php echo esc_textarea( get_option( 'hospitaliti_detail_custom_css', '' ) ); ?></textarea>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save Detail Page Settings', 'hospitaliti-jobs' ) ); ?>
		</form>
		<?php
	}

	/* ── Tab: Tools & Shortcodes ─────────────────────────────────────────── */

	private function renderTabTools( string $adapterShort, string $activeTheme ): void {
		?>
		<!-- ── Shortcode reference ─────────────────────────────────────── -->
		<h2><?php esc_html_e( 'Shortcodes', 'hospitaliti-jobs' ); ?></h2>
		<p><?php esc_html_e( 'Paste either shortcode into any page, post, or widget text area.', 'hospitaliti-jobs' ); ?></p>

		<style>
			.hj-sc-cards { display:flex; gap:1.5rem; flex-wrap:wrap; margin-bottom:1.5rem; }
			.hj-sc-card  { flex:1 1 340px; border:1px solid #c3c4c7; border-radius:4px; padding:1.25rem 1.5rem; background:#fff; }
			.hj-sc-card h3 { margin:0 0 .35rem; font-size:.95rem; color:#1d2327; }
			.hj-sc-card p  { margin:0 0 1rem; color:#646970; font-size:.85rem; }
			.hj-sc-copy-row { display:flex; align-items:center; gap:.5rem; margin-bottom:1rem; }
			.hj-sc-code { flex:1; background:#f6f7f7; border:1px solid #dcdcde; border-radius:3px; padding:.45em .75em; font-family:monospace; font-size:.82rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
			.hj-sc-copy-btn { white-space:nowrap; }
			.hj-sc-table { width:100%; border-collapse:collapse; font-size:.82rem; }
			.hj-sc-table th { text-align:left; padding:.3em .5em; color:#50575e; font-weight:600; border-bottom:1px solid #dcdcde; }
			.hj-sc-table td { padding:.3em .5em; vertical-align:top; border-bottom:1px solid #f0f0f1; }
			.hj-sc-table td code { font-size:.8rem; background:#f6f7f7; padding:.1em .3em; border-radius:2px; }
			.hj-sc-table tr:last-child td { border-bottom:none; }
		</style>

		<div class="hj-sc-cards">
			<!-- Card 1 — Standard listing -->
			<div class="hj-sc-card">
				<h3><?php esc_html_e( 'Standard Job Listing', 'hospitaliti-jobs' ); ?></h3>
				<p><?php esc_html_e( 'Card layout with search bar, filter sidebar and pagination.', 'hospitaliti-jobs' ); ?></p>
				<div class="hj-sc-copy-row">
					<span class="hj-sc-code">[hospitaliti_jobs]</span>
					<button type="button" class="button hj-sc-copy-btn" data-target="[hospitaliti_jobs]"><?php esc_html_e( 'Copy', 'hospitaliti-jobs' ); ?></button>
				</div>
				<table class="hj-sc-table"><thead><tr><th><?php esc_html_e( 'Attribute', 'hospitaliti-jobs' ); ?></th><th><?php esc_html_e( 'Default', 'hospitaliti-jobs' ); ?></th><th><?php esc_html_e( 'Description', 'hospitaliti-jobs' ); ?></th></tr></thead><tbody>
					<tr><td><code>count</code></td><td><?php echo esc_html( get_option( 'hospitaliti_listing_per_page', 10 ) ); ?></td><td><?php esc_html_e( 'Jobs per page (max 50)', 'hospitaliti-jobs' ); ?></td></tr>
					<tr><td><code>show_search</code></td><td>true</td><td><?php esc_html_e( 'Show keyword search bar', 'hospitaliti-jobs' ); ?></td></tr>
					<tr><td><code>show_filter</code></td><td>true</td><td><?php esc_html_e( 'Show filter sidebar', 'hospitaliti-jobs' ); ?></td></tr>
					<tr><td><code>pagination</code></td><td>true</td><td><?php esc_html_e( 'true=pages, false=load-more', 'hospitaliti-jobs' ); ?></td></tr>
				</tbody></table>
			</div>

			<!-- Card 2 — Bubble listing -->
			<div class="hj-sc-card">
				<h3><?php esc_html_e( 'Bubble Job Listing', 'hospitaliti-jobs' ); ?></h3>
				<p><?php esc_html_e( 'Circular bubbles grouped by organisation with badge filters.', 'hospitaliti-jobs' ); ?></p>
				<div class="hj-sc-copy-row">
					<span class="hj-sc-code">[hospitaliti_jobs_bubbles]</span>
					<button type="button" class="button hj-sc-copy-btn" data-target="[hospitaliti_jobs_bubbles]"><?php esc_html_e( 'Copy', 'hospitaliti-jobs' ); ?></button>
				</div>
				<table class="hj-sc-table"><thead><tr><th><?php esc_html_e( 'Attribute', 'hospitaliti-jobs' ); ?></th><th><?php esc_html_e( 'Default (from settings)', 'hospitaliti-jobs' ); ?></th><th><?php esc_html_e( 'Description', 'hospitaliti-jobs' ); ?></th></tr></thead><tbody>
					<tr><td><code>title</code></td><td><?php echo esc_html( get_option( 'hospitaliti_bubbles_title', 'Work For Us' ) ); ?></td><td><?php esc_html_e( 'Section heading', 'hospitaliti-jobs' ); ?></td></tr>
					<tr><td><code>count</code></td><td>200</td><td><?php esc_html_e( 'Max jobs to load', 'hospitaliti-jobs' ); ?></td></tr>
					<tr><td><code>show_cta</code></td><td>true</td><td><?php esc_html_e( 'Show CTA below bubbles', 'hospitaliti-jobs' ); ?></td></tr>
					<tr><td><code>cta_text</code></td><td><?php echo esc_html( get_option( 'hospitaliti_bubbles_cta_text', 'Submit your application' ) ); ?></td><td><?php esc_html_e( 'CTA button label', 'hospitaliti-jobs' ); ?></td></tr>
					<tr><td><code>cta_url</code></td><td><?php esc_html_e( '(from settings)', 'hospitaliti-jobs' ); ?></td><td><?php esc_html_e( 'CTA button URL', 'hospitaliti-jobs' ); ?></td></tr>
				</tbody></table>
			</div>

	</div><!-- .hj-sc-cards -->

		<hr />

		<!-- ── Theme info ──────────────────────────────────────────────── -->
		<h2><?php esc_html_e( 'Active Theme Adapter', 'hospitaliti-jobs' ); ?></h2>
		<p>
			<?php printf(
				/* translators: %1$s theme slug, %2$s adapter class name */
				esc_html__( 'Detected theme: %1$s — adapter: %2$s', 'hospitaliti-jobs' ),
				'<strong>' . esc_html( $activeTheme ) . '</strong>',
				'<code>' . esc_html( $adapterShort ) . '</code>'
			); ?>
		</p>

		<hr />

		<hr />

		<!-- ── Cache ───────────────────────────────────────────────────── -->
		<h2><?php esc_html_e( 'Cache Management', 'hospitaliti-jobs' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'hospitaliti_flush_cache' ); ?>
			<input type="hidden" name="hospitaliti_flush_cache" value="1" />
			<?php submit_button( __( 'Flush Jobs Cache', 'hospitaliti-jobs' ), 'secondary' ); ?>
		</form>

		<hr />

		<!-- ── Rewrite rules ───────────────────────────────────────────── -->
		<h2><?php esc_html_e( 'Rewrite Rules', 'hospitaliti-jobs' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'hospitaliti_flush_rewrites' ); ?>
			<input type="hidden" name="hospitaliti_flush_rewrites" value="1" />
			<?php submit_button( __( 'Flush Rewrite Rules', 'hospitaliti-jobs' ), 'secondary' ); ?>
		</form>
		<?php
	}

	/* ── API connection test ─────────────────────────────────────────────── */

	public function ajaxTestConnection(): void {
		check_ajax_referer( 'hospitaliti_test_api' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'hospitaliti-jobs' ) ] );
		}
		$result = $this->testApiConnection();
		$result['ok'] ? wp_send_json_success( $result ) : wp_send_json_error( $result );
	}

	private function testApiConnection(): array {
		$base  = rtrim( (string) get_option( 'hospitaliti_jobs_api_url', HOSPITALITI_JOBS_DEFAULT_API_URL ), '/' );
		$encid = trim( (string) get_option( 'hospitaliti_company_encid', '' ) );
		$ssl   = (bool) get_option( 'hospitaliti_jobs_sslverify', 0 );

		if ( $base === '' ) {
			return [ 'ok' => false, 'message' => __( 'No API URL configured. Please set the Hospitaliti API URL in the API & Connection tab.', 'hospitaliti-jobs' ) ];
		}

		if ( $encid === '' ) {
			return [ 'ok' => false, 'message' => __( 'No Company / Group Enc ID configured. Please set it in the API & Connection tab.', 'hospitaliti-jobs' ) ];
		}

		$url  = add_query_arg( [ 'per_page' => 1 ], $base . '/api/jobs/' . rawurlencode( $encid ) );
		$resp = wp_remote_get( $url, [ 'timeout' => 15, 'sslverify' => $ssl ] );

		if ( is_wp_error( $resp ) ) {
			return [ 'ok' => false, 'message' => $resp->get_error_message() ];
		}

		$status = (int) wp_remote_retrieve_response_code( $resp );

		if ( 200 === $status ) {
			return [ 'ok' => true, 'message' => __( 'Connection successful — API is reachable.', 'hospitaliti-jobs' ) ];
		}

		return [
			'ok'      => false,
			'message' => sprintf(
				/* translators: 1: HTTP status code, 2: URL tested */
				__( 'API returned HTTP %1$d. Check the URL and Enc ID. (Tested: %2$s)', 'hospitaliti-jobs' ),
				$status,
				$url
			),
		];
	}
}
