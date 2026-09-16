<?php

/**
 * JobService — business logic for job retrieval.
 *
 * Orchestrates JobRepository (data) + JobMapper (shape) and contains any
 * domain decisions: slug normalisation, fallback strategies, careers URL
 * resolution, etc.
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs\Job;

defined( 'ABSPATH' ) || exit;

class JobService {

	private JobRepository $repo;
	private JobMapper     $mapper;

	public function __construct( JobRepository $repo, JobMapper $mapper ) {
		$this->repo   = $repo;
		$this->mapper = $mapper;
	}

	// ── Listing ──────────────────────────────────────────────────────────────

	/**
	 * Return a mapped, paginated set of jobs ready for templates.
	 *
	 * @param  array  $params  Accepted: per_page, page, employment_type, search, experience.
	 * @param  string $encid   Optional company enc ID.
	 * @return array|\WP_Error [
	 *   'data'         => \stdClass[],
	 *   'current_page' => int,
	 *   'last_page'    => int,
	 *   'per_page'     => int,
	 *   'total'        => int,
	 * ]
	 */
	public function getJobsPage( array $params = [], string $encid = '' ) {
		$raw = $this->repo->findPage( $params, $encid );

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		return [
			'data'          => $this->mapper->mapCollection( $raw['data']          ?? [] ),
			'current_page'  => (int) ( $raw['current_page']  ?? 1 ),
			'last_page'     => (int) ( $raw['last_page']     ?? 1 ),
			'per_page'      => (int) ( $raw['per_page']      ?? HOSPITALITI_JOBS_DEFAULT_PER_PAGE ),
			'total'         => (int) ( $raw['total']         ?? 0 ),
			'organizations' => is_array( $raw['organizations'] ?? null ) ? $raw['organizations'] : [],
		];
	}

	// ── Detail ───────────────────────────────────────────────────────────────

	/**
	 * Return a single mapped job, trying slug variations used by the API.
	 *
	 * The Hospitaliti API sometimes prefixes slugs with a dash when the org
	 * prefix is absent (e.g. '-job-st-moritz').  We try the clean slug first
	 * and fall back to the dash-prefixed version if it is not found.
	 *
	 * @param  string $slug  Raw slug from the URL (may include leading dash).
	 * @return \stdClass|\WP_Error
	 */
	public function getJob( string $slug ) {
		$clean = trim( sanitize_text_field( $slug ), '-' );

		$raw = $this->repo->findBySlug( $clean );

		// Fallback: try with leading dash when the clean variant is not found.
		if ( ( is_wp_error( $raw ) || empty( $raw ) ) && $clean !== '' ) {
			$dashed = $this->repo->findBySlug( '-' . $clean );
			if ( ! is_wp_error( $dashed ) && ! empty( $dashed ) ) {
				$raw = $dashed;
			}
		}

		if ( is_wp_error( $raw ) || empty( $raw ) ) {
			return $raw instanceof \WP_Error
				? $raw
				: new \WP_Error( 'hospitaliti_not_found', __( 'Job not found.', 'hospitaliti-jobs' ) );
		}

		return $this->mapper->mapSingle( $raw );
	}

	// ── URL helpers ──────────────────────────────────────────────────────────

	/**
	 * Return the slug of the WordPress page configured as the careers base.
	 * Falls back to 'careers' when nothing is configured.
	 */
	public function getCareersBase(): string {
		$pageId = (int) get_option( 'hospitaliti_careers_page_id', 0 );
		if ( $pageId > 0 ) {
			$slug = get_post_field( 'post_name', $pageId );
			if ( ! is_wp_error( $slug ) && $slug ) {
				return (string) $slug;
			}
		}
		return 'careers';
	}

	/**
	 * Return the full URL to the careers listing page.
	 */
	public function getCareersUrl(): string {
		$pageId = (int) get_option( 'hospitaliti_careers_page_id', 0 );
		if ( $pageId > 0 ) {
			$url = get_permalink( $pageId );
			if ( $url ) {
				return $url;
			}
		}
		return home_url( '/' . $this->getCareersBase() . '/' );
	}
}
