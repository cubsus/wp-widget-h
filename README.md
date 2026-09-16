# hospitaliti-jobs-widget
=== Hospitaliti Jobs Widget ===
Contributors: yourname
Tags: jobs, widget, hospitality, recruitment, job board
Requires at least: 5.9
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display live job listings from the Hospitaliti recruitment platform on any WordPress site.

== Description ==

The **Hospitaliti Jobs Widget** plugin fetches live job listings from your Hospitaliti
platform installation and displays them on any WordPress site using:

* A classic **sidebar/footer widget** (Appearance → Widgets)
* A **shortcode** you can drop into any post or page
* AJAX-powered **"Load More"** pagination — no page reload required

Each job card shows the company logo, job title, employment type, salary, and
post date. Clicking any card takes the visitor directly to the job detail page
on your Hospitaliti site.

= Features =

* No authentication required — uses the public Hospitaliti API
* Configurable cache (default 30 minutes) via WordPress transients
* Filter by employment type (full-time / part-time / contract)
* Keyword search filter on job title
* Optional "View All Jobs" link
* Per-widget toggle for salary, employment type, and post date
* Clean, theme-agnostic CSS — easy to override

== Installation ==

1. Upload the `hospitaliti-jobs-widget` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings → Hospitaliti Jobs** and enter your Hospitaliti API URL.
4. Add the **Hospitaliti Jobs** widget via **Appearance → Widgets**, or use
   the shortcode `[hospitaliti_jobs]` in any post or page.

== Configuration ==

Navigate to **Settings → Hospitaliti Jobs**:

* **Hospitaliti API URL** — Base URL of your Hospitaliti installation (e.g. `https://hospitaliti.test`).
* **Cache Duration** — Minutes to cache API responses (0 to disable).
* **SSL Verification** — Disable for local/dev environments with self-signed certificates.

== Shortcode Usage ==

    [hospitaliti_jobs]

Optional attributes:

| Attribute        | Default | Description                                              |
|------------------|---------|----------------------------------------------------------|
| `count`          | 5       | Jobs per page (max 50)                                   |
| `show_salary`    | true    | Show salary badge                                        |
| `show_type`      | true    | Show employment-type badge                               |
| `show_date`      | true    | Show post-date badge                                     |
| `employment_type`|         | Filter: `full-time`, `part-time`, `contract`             |
| `search`         |         | Keyword search on job title                              |
| `view_all_url`   |         | URL for "View All Jobs" link                             |

Examples:

    [hospitaliti_jobs count="10" show_salary="true" employment_type="full-time"]
    [hospitaliti_jobs search="chef" view_all_url="https://hospitaliti.test"]

== Frequently Asked Questions ==

= Does this plugin work without an active Hospitaliti account? =

No. You need a running Hospitaliti installation accessible via HTTP/HTTPS.

= Can I style the job cards? =

Yes. All CSS classes are namespaced with `hospitaliti-` so you can override them
in your theme's stylesheet without conflicts.

= How do I flush the jobs cache? =

Go to **Settings → Hospitaliti Jobs** and click the **Flush Jobs Cache** button.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
