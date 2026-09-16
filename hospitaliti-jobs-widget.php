<?php
/**
 * Plugin Name:       Hospitaliti Jobs Widget
 * Plugin URI:        https://github.com/asadAli0051/hospitaliti-jobs-widget
 * Description:       Display live job listings from the Hospitaliti recruitment platform on any WordPress site via a widget, shortcode, or AJAX-powered pagination.
 * Version:           2.0.1
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Asad Ali
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hospitaliti-jobs
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

// ── Constants ─────────────────────────────────────────────────────────────────

define( 'HOSPITALITI_JOBS_VERSION',          '2.0.1' );
define( 'HOSPITALITI_JOBS_PLUGIN_DIR',       plugin_dir_path( __FILE__ ) );
define( 'HOSPITALITI_JOBS_PLUGIN_URL',       plugin_dir_url( __FILE__ ) );
define( 'HOSPITALITI_JOBS_DEFAULT_API_URL',  'https://app.hospitaliti.io' );
define( 'HOSPITALITI_JOBS_DEFAULT_PER_PAGE', 20 );

// ── Autoloader ────────────────────────────────────────────────────────────────
// Loads all classes under the Hospitaliti\Jobs\ namespace from src/.

require_once HOSPITALITI_JOBS_PLUGIN_DIR . 'src/Autoloader.php';

\Hospitaliti\Jobs\Autoloader::register();

// ── Lifecycle Hooks ───────────────────────────────────────────────────────────
// Must be registered before the singleton is created (WordPress requirement).

register_activation_hook( __FILE__,   [ \Hospitaliti\Jobs\Plugin::class, 'onActivate'   ] );
register_deactivation_hook( __FILE__, [ \Hospitaliti\Jobs\Plugin::class, 'onDeactivate' ] );

// ── Bootstrap ─────────────────────────────────────────────────────────────────

\Hospitaliti\Jobs\Plugin::getInstance();
