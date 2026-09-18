<?php

/**
 * Hosco job data access with transient caching.
 *
 * Sits between HoscoApiClient and the shortcode layer.
 * Only responsibility: search raw hosco API results and cache them.
 * No business logic, no mapping.
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs\Hosco;

defined( 'ABSPATH' ) || exit;

class HoscoJobRepository {

	private HoscoApiClient $client;
	private int            $cacheTtl;

	public function __construct( HoscoApiClient $client, int $cacheTtl = 30 ) {
		$this->client   = $client;
		$this->cacheTtl = $cacheTtl;
	}

	/**
	 * Search hosco jobs (with caching).
	 *
	 * @param  array  $filters  Hosco filter fields passed to HoscoApiClient.
	 * @param  int    $page     1-based page number.
	 * @param  string $locale   API locale.
	 * @return array|\WP_Error  Raw hosco response or WP_Error.
	 */
	public function search( array $filters = [], int $page = 1, string $locale = 'en' ) {
		$key = 'hosco_search_' . md5( wp_json_encode( [ $filters, $page, $locale ] ) );

		$cached = $this->getCached( $key );
		if ( false !== $cached ) {
			return $cached;
		}

		$data = $this->client->searchJobs( $filters, $page, $locale );

		if ( ! is_wp_error( $data ) ) {
			$this->setCache( $key, $data );
		}

		return $data;
	}

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
