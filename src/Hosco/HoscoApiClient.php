<?php

/**
 * Thin HTTP client for the Hosco external-alien jobs API.
 *
 * Calls POST /jobs/search with an X-Auth-Secret API key.
 * Locale is passed as the Accept-Language header, not a body field.
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs\Hosco;

defined( 'ABSPATH' ) || exit;

class HoscoApiClient {

	/** @var string Base URL, no trailing slash. */
	private string $baseUrl;

	/** @var string X-Auth-Secret API key. */
	private string $apiSecret;

	public function __construct( string $baseUrl = '', string $apiSecret = '' ) {
		$this->baseUrl   = rtrim( $baseUrl, '/' );
		$this->apiSecret = $apiSecret;
	}

	/**
	 * Return the configured base URL (used by templates to resolve logo paths).
	 */
	public function getBaseUrl(): string {
		return $this->baseUrl;
	}

	/**
	 * Search jobs via the Hosco external-alien API.
	 *
	 * Sends POST /jobs/search authenticated with X-Auth-Secret.
	 * Locale is passed as the Accept-Language header per the API spec.
	 *
	 * @param  array  $filters  Filter fields (limit, owner, departments, keywords…).
	 * @param  int    $page     1-based page number.
	 * @param  string $locale   Accept-Language value — 'en', 'fr', 'es', 'it'.
	 * @return array|\WP_Error  Decoded body: { total: {value:N}, results: [...] }
	 */
	public function searchJobs( array $filters = [], int $page = 1, string $locale = 'en' ) {
		$body = wp_json_encode( [
			'filters' => array_merge( [ 'limit' => 10 ], $filters ),
			'page'    => max( 1, $page ),
		] );

		$response = wp_remote_post( $this->baseUrl . '/jobs/search', [
			'headers' => [
				'Content-Type'    => 'application/json',
				'Accept'          => 'application/json',
				'Accept-Language' => $locale,
				'X-Auth-Secret'   => $this->apiSecret,
			],
			'body'    => $body,
			'timeout' => 15,
		] );

		return $this->parse( $response );
	}

	/**
	 * Parse a WP HTTP API response into a decoded array or WP_Error.
	 *
	 * @param  array|\WP_Error $response
	 * @return array|\WP_Error
	 */
	private function parse( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status ) {
			return new \WP_Error(
				'hosco_http_error',
				sprintf( __( 'Hosco API returned HTTP %d.', 'hospitaliti-jobs' ), $status )
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return new \WP_Error(
				'hosco_invalid_json',
				__( 'Invalid JSON response from Hosco API.', 'hospitaliti-jobs' )
			);
		}

		return $data;
	}
}
