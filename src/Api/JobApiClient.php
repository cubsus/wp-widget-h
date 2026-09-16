<?php

/**
 * Thin HTTP client for the Hospitaliti Jobs API.
 *
 * Single responsibility: send HTTP requests and return decoded JSON arrays
 * or WP_Error. No caching, no business logic — those belong in JobRepository
 * and JobService respectively.
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs\Api;

defined( 'ABSPATH' ) || exit;

class JobApiClient {

	/** @var string Base URL, no trailing slash. */
	private string $baseUrl;

	/** @var bool Whether to verify SSL certificates. */
	private bool $sslVerify;

	public function __construct( string $baseUrl, bool $sslVerify = false ) {
		$this->baseUrl   = rtrim( $baseUrl, '/' );
		$this->sslVerify = $sslVerify;
	}

	// ── Public API ──────────────────────────────────────────────────────────

	/**
	 * Fetch a paginated list of jobs.
	 *
	 * @param  array  $params  Query params (per_page, page, search, employment_type, experience).
	 * @param  string $encid   Optional company/group enc ID to scope the request.
	 * @return array|\WP_Error Decoded JSON array or WP_Error.
	 */
	public function fetchJobs( array $params = [], string $encid = '' ) {
		$path = $encid !== '' ? '/api/jobs/' . rawurlencode( $encid ) : '/api/jobs';
		return $this->get( $path, $params );
	}

	/**
	 * Fetch full details for a single job by slug.
	 *
	 * @param  string $slug    Job slug.
	 * @return array|\WP_Error
	 */
	public function fetchJob( string $slug ) {
		return $this->get( '/api/job/' . rawurlencode( $slug ) );
	}

	/**
	 * Return the configured base URL (used by templates to resolve relative media URLs).
	 */
	public function getBaseUrl(): string {
		return $this->baseUrl;
	}

	// ── Private helpers ─────────────────────────────────────────────────────

	/**
	 * Send a GET request and return the parsed JSON body.
	 *
	 * @param  string $path
	 * @param  array  $params
	 * @return array|\WP_Error
	 */
	private function get( string $path, array $params = [] ) {
		$clean = array_filter(
			$params,
			static fn( $v ) => $v !== '' && $v !== null && $v !== 0 && $v !== 'all'
		);

		$url      = add_query_arg( $clean, $this->baseUrl . $path );
		$response = wp_remote_get( $url, [
			'timeout'   => 15,
			'sslverify' => $this->sslVerify,
			'headers'   => [ 'Accept' => 'application/json' ],
		] );

		return $this->parseResponse( $response );
	}

	/**
	 * Parse a WP HTTP API response into a decoded array or WP_Error.
	 *
	 * @param  array|\WP_Error $response
	 * @return array|\WP_Error
	 */
	private function parseResponse( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( 404 === $status ) {
			return new \WP_Error(
				'hospitaliti_not_found',
				__( 'Job not found.', 'hospitaliti-jobs' )
			);
		}

		if ( 200 !== $status ) {
			return new \WP_Error(
				'hospitaliti_http_error',
				/* translators: %d: HTTP status code */
				sprintf( __( 'Hospitaliti API returned HTTP %d.', 'hospitaliti-jobs' ), $status )
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return new \WP_Error(
				'hospitaliti_invalid_json',
				__( 'Invalid JSON response from Hospitaliti API.', 'hospitaliti-jobs' )
			);
		}

		return $data;
	}
}
