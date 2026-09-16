<?php

/**
 * Classic WordPress widget for the jobs listing.
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs\Widget;

use Hospitaliti\Jobs\Plugin;

defined( 'ABSPATH' ) || exit;

class JobsWidget extends \WP_Widget {

	public function __construct() {
		parent::__construct(
			'hospitaliti_jobs_widget',
			__( 'Hospitaliti Jobs', 'hospitaliti-jobs' ),
			[
				'description'                 => __( 'Display live job listings from the Hospitaliti recruitment platform.', 'hospitaliti-jobs' ),
				'customize_selective_refresh' => true,
			]
		);
	}

	/* ── Front-end rendering ─────────────────────────────────────────────── */

	/** @param array $args     Widget area wrapper tags. */
	/** @param array $instance Saved widget settings.   */
	public function widget( $args, $instance ): void {
		$title          = ! empty( $instance['title'] )           ? $instance['title']                      : __( 'Latest Jobs', 'hospitaliti-jobs' );
		$perPage        = ! empty( $instance['count'] )           ? min( absint( $instance['count'] ), 50 ) : HOSPITALITI_JOBS_DEFAULT_PER_PAGE;
		$showSalary     = isset( $instance['show_salary'] )       ? (bool) $instance['show_salary']         : true;
		$showType       = isset( $instance['show_type'] )         ? (bool) $instance['show_type']           : true;
		$showDate       = isset( $instance['show_date'] )         ? (bool) $instance['show_date']           : true;
		$employmentType = ! empty( $instance['employment_type'] ) ? $instance['employment_type']            : '';

		$plugin      = Plugin::getInstance();
		$service     = $plugin->getService();
		$assets      = $plugin->getAssetManager();
		$apiUrl      = get_option( 'hospitaliti_jobs_api_url', HOSPITALITI_JOBS_DEFAULT_API_URL );
		$encid       = (string) get_option( 'hospitaliti_company_encid', '' );
		$theme       = $plugin->resolveTheme();

		$data = $service->getJobsPage(
			array_filter(
				[ 'per_page' => $perPage, 'employment_type' => $employmentType ],
				static fn( $v ) => $v !== ''
			),
			$encid
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['before_widget'];

		if ( ! empty( $title ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $title ) ) . $args['after_title'];
		}

		if ( is_wp_error( $data ) ) {
			echo '<p class="hospitaliti-error">'
				. esc_html__( 'Could not load jobs at this time.', 'hospitaliti-jobs' )
				. '</p>';
		} else {
			$jobs            = $data['data']  ?? [];
			$has_more        = ( $data['current_page'] ?? 1 ) < ( $data['last_page'] ?? 1 );
			$api_base_url    = rtrim( $apiUrl, '/' );
			$per_page        = $data['per_page'] ?? $perPage;
			$show_salary     = $showSalary;
			$show_type       = $showType;
			$show_date       = $showDate;
			$show_search     = false;
			$show_filter     = false;
			$employment_type = $employmentType;
			$pagination_mode = 'paged';

			$assets->enqueueListingAssets();

			include HOSPITALITI_JOBS_PLUGIN_DIR . 'templates/jobs-list.php';
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['after_widget'];
	}

	/* ── Settings form ───────────────────────────────────────────────────── */

	public function form( $instance ): void {
		$title          = $instance['title']           ?? __( 'Latest Jobs', 'hospitaliti-jobs' );
		$count          = $instance['count']           ?? HOSPITALITI_JOBS_DEFAULT_PER_PAGE;
		$showSalary     = $instance['show_salary']     ?? 1;
		$showType       = $instance['show_type']       ?? 1;
		$showDate       = $instance['show_date']       ?? 1;
		$employmentType = $instance['employment_type'] ?? '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'hospitaliti-jobs' ); ?></label>
			<input class="widefat" type="text"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( 'Number of jobs:', 'hospitaliti-jobs' ); ?></label>
			<input class="tiny-text" type="number" min="1" max="50"
				id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>"
				value="<?php echo absint( $count ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'employment_type' ) ); ?>"><?php esc_html_e( 'Employment type:', 'hospitaliti-jobs' ); ?></label>
			<select class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'employment_type' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'employment_type' ) ); ?>">
				<option value="" <?php selected( $employmentType, '' ); ?>><?php esc_html_e( 'All types', 'hospitaliti-jobs' ); ?></option>
				<option value="full-time"  <?php selected( $employmentType, 'full-time' ); ?>><?php esc_html_e( 'Full Time',  'hospitaliti-jobs' ); ?></option>
				<option value="part-time"  <?php selected( $employmentType, 'part-time' ); ?>><?php esc_html_e( 'Part Time',  'hospitaliti-jobs' ); ?></option>
				<option value="contract"   <?php selected( $employmentType, 'contract' );  ?>><?php esc_html_e( 'Contract',   'hospitaliti-jobs' ); ?></option>
			</select>
		</p>
		<p>
			<input type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_salary' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_salary' ) ); ?>" value="1" <?php checked( 1, $showSalary ); ?> />
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_salary' ) ); ?>"><?php esc_html_e( 'Show salary', 'hospitaliti-jobs' ); ?></label>
		</p>
		<p>
			<input type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_type' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_type' ) ); ?>" value="1" <?php checked( 1, $showType ); ?> />
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_type' ) ); ?>"><?php esc_html_e( 'Show employment type', 'hospitaliti-jobs' ); ?></label>
		</p>
		<p>
			<input type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_date' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_date' ) ); ?>" value="1" <?php checked( 1, $showDate ); ?> />
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_date' ) ); ?>"><?php esc_html_e( 'Show post date', 'hospitaliti-jobs' ); ?></label>
		</p>
		<?php
	}

	/* ── Save settings ───────────────────────────────────────────────────── */

	public function update( $new_instance, $old_instance ): array {
		return [
			'title'           => sanitize_text_field( $new_instance['title']           ?? '' ),
			'count'           => min( absint( $new_instance['count'] ?? HOSPITALITI_JOBS_DEFAULT_PER_PAGE ), 50 ),
			'show_salary'     => ! empty( $new_instance['show_salary'] ) ? 1 : 0,
			'show_type'       => ! empty( $new_instance['show_type'] )   ? 1 : 0,
			'show_date'       => ! empty( $new_instance['show_date'] )   ? 1 : 0,
			'employment_type' => sanitize_text_field( $new_instance['employment_type'] ?? '' ),
		];
	}
}
