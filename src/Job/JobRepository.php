<?php

/**
 * JobRepository — data access with transient caching.
 *
 * Sits between the API client and the service layer.
 * Only responsibility: fetch raw API arrays, cache them, and return them.
 * No business logic, no mapping.
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs\Job;

use Hospitaliti\Jobs\Api\JobApiClient;

defined( 'ABSPATH' ) || exit;

class JobRepository {

	private JobApiClient $client;
	private int          $cacheTtl; // minutes; 0 = disabled.

	public function __construct( JobApiClient $client, int $cacheTtl = 30 ) {
		$this->client   = $client;
		$this->cacheTtl = $cacheTtl;
	}

	/**
	 * Fetch a paginated list of jobs (raw API array).
	 *
	 * @param  array  $params  Filters: per_page, page, employment_type, search, experience.
	 * @param  string $encid   Optional company enc ID for scoped results.
	 * @return array|\WP_Error Laravel-style paginator array or WP_Error.
	 */
	public function findPage( array $params = [], string $encid = '' ) {
		$key = 'hospitaliti_jobs_' . md5( $encid . wp_json_encode( $params ) );

		$cached = $this->getCached( $key );
		if ( $cached !== false ) {
			return $cached;
		}

		$data = $this->client->fetchJobs( $params, $encid );

		if ( ! is_wp_error( $data ) ) {
			$this->setCache( $key, $data );
		}

		return $data;
	}

	/**
	 * Fetch a single job by slug (raw API array).
	 *
	 * @param  string $slug
	 * @return array|\WP_Error
	 */
	public function findBySlug( string $slug ) {
		$key = 'hospitaliti_job_' . md5( $slug );

		$cached = $this->getCached( $key );
		if ( $cached !== false ) {
			return $cached;
		}

		$data = $this->client->fetchJob( $slug );

		if ( ! is_wp_error( $data ) ) {
			$this->setCache( $key, $data );
		}

		return $data;
	}

	/**
	 * Flush all plugin transients from the database.
	 * Called by Settings and Plugin on deactivation.
	 */
	public static function flushAll(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_hospitaliti_%'
			    OR option_name LIKE '_transient_timeout_hospitaliti_%'"
		);
	}

	// ── Cache helpers ────────────────────────────────────────────────────────

	/** @return mixed Cached value or false when disabled / missing. */
	private function getCached( string $key ) {
		if ( $this->cacheTtl <= 0 ) {
			return false;
		}
		return get_transient( $key );
	}

	private function setCache( string $key, $data ): void {
		if ( $this->cacheTtl <= 0 ) {
			return;
		}
		set_transient( $key, $data, $this->cacheTtl * MINUTE_IN_SECONDS );
	}
}
