<?php

/**
 * JobMapper — transforms raw API arrays into structured stdClass objects.
 *
 * Keeps all "what does an API array look like" knowledge in one place so
 * templates and services never touch raw array keys directly.
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs\Job;

defined( 'ABSPATH' ) || exit;

class JobMapper {

	/**
	 * Map a collection of raw job arrays.
	 *
	 * @param  array $items Raw items from the API paginator's `data` key.
	 * @return \stdClass[]
	 */
	public function mapCollection( array $items ): array {
		return array_map( [ $this, 'mapSingle' ], $items );
	}

	/**
	 * Map a single raw job array to a structured stdClass.
	 *
	 * All keys are normalised and given safe defaults so templates can use
	 * $job->title instead of ($raw['title'] ?? '').
	 *
	 * @param  array $raw
	 * @return \stdClass
	 */
	public function mapSingle( array $raw ): \stdClass {
		// ── Organisation ──────────────────────────────────────────────────────
		$org = is_array( $raw['organization'] ?? null )
			? $raw['organization']
			: [];

		$avatar = is_array( $org['avatarImage'] ?? null ) ? $org['avatarImage'] : [];
		$banner = is_array( $org['bannerImage']  ?? null ) ? $org['bannerImage']  : [];

		// ── Candidates count ──────────────────────────────────────────────────
		$candidates = null;
		if ( isset( $raw['applications_count'] ) && $raw['applications_count'] !== null ) {
			$candidates = (int) $raw['applications_count'];
		} elseif ( isset( $raw['applied_candidates'] ) ) {
			$candidates = is_array( $raw['applied_candidates'] )
				? count( $raw['applied_candidates'] )
				: (int) $raw['applied_candidates'];
		}

		// ── Salary ────────────────────────────────────────────────────────────
		$salary = $this->formatSalary(
			$raw['salary']            ?? '',
			$raw['salary_currency']   ?? '',
			$raw['salary_period']     ?? '',
			(bool) ( $raw['salary_negotiable'] ?? false )
		);

		// ── Location ─────────────────────────────────────────────────────────
		$locationParts = [];
		if ( ! empty( $raw['location'] ) && is_array( $raw['location'] ) ) {
			foreach ( $raw['location'] as $loc ) {
				$name = is_array( $loc ) ? ( $loc['name'] ?? '' ) : (string) $loc;
				if ( $name !== '' ) {
					$locationParts[] = $name;
				}
			}
		}

		return (object) [
			// Identity
			'slug'              => trim( (string) ( $raw['slug'] ?? '' ), '-' ),
			'title'             => (string) ( $raw['title']       ?? '' ),

			// Content
			'description'       => (string) ( $raw['description'] ?? '' ),
			'experience'        => (string) ( $raw['experience']  ?? '' ),
			'skills'            => is_array( $raw['skills']            ?? null ) ? $raw['skills']            : [],
			'benefits'          => is_array( $raw['benefits']          ?? null ) ? $raw['benefits']          : [],
			'working_schedule'  => is_array( $raw['working_schedule']  ?? null ) ? $raw['working_schedule']  : [],

			// Meta
			'employment_type'   => (string) ( $raw['employment_type']  ?? '' ),
			'salary_display'    => $salary,
			// Keep raw salary fields for templates that need custom formatting.
			'salary'            => $raw['salary']           ?? '',
			'salary_currency'   => $raw['salary_currency']  ?? '',
			'salary_period'     => $raw['salary_period']    ?? '',
			'salary_negotiable' => (bool) ( $raw['salary_negotiable'] ?? false ),
			'location_str'      => implode( ', ', $locationParts ),
			'location'          => $raw['location']         ?? [],

			// Dates
			'post_date'         => (string) ( $raw['post_date']      ?? '' ),
			'starting_date'     => (string) ( $raw['starting_date']  ?? '' ),

			// Stats
			'applied_candidates' => $candidates,
			'applications_count' => isset( $raw['applications_count'] ) ? (int) $raw['applications_count'] : null,

			// Organisation
			'organization' => [
				'name'        => (string) ( $org['name'] ?? '' ),
				'avatarImage' => $avatar,
				'bannerImage' => $banner,
			],

			// Application
			'apply_link' => (string) ( $raw['apply_link'] ?? '' ),
		];
	}

	// ── Private helpers ─────────────────────────────────────────────────────

	/**
	 * Format a salary value into a human-readable display string.
	 *
	 * @param  mixed  $salary
	 * @param  string $currency
	 * @param  string $period
	 * @param  bool   $negotiable
	 * @return string  Empty string when no salary info is available.
	 */
	private function formatSalary( $salary, string $currency, string $period, bool $negotiable ): string {
		if ( empty( $salary ) ) {
			return '';
		}

		if ( $negotiable ) {
			return __( 'Negotiable', 'hospitaliti-jobs' );
		}

		$symbols = [ 'GBP' => '£', 'EUR' => '€', 'CHF' => 'CHF ', 'USD' => '$' ];
		$sym     = $symbols[ $currency ] ?? ( $currency ? $currency . ' ' : '' );

		$periodLabels = [
			'annually' => __( '/ yr', 'hospitaliti-jobs' ),
			'monthly'  => __( '/ mo', 'hospitaliti-jobs' ),
			'hourly'   => __( '/ hr', 'hospitaliti-jobs' ),
		];
		$per = $periodLabels[ $period ] ?? '';

		$raw = str_replace( [ "'", ',' ], '', (string) $salary );

		if ( strpos( $raw, '-' ) !== false ) {
			[ $min, $max ] = array_map( 'trim', explode( '-', $raw, 2 ) );
			return trim( $sym . number_format( (float) $min ) . ' – ' . $sym . number_format( (float) $max ) . ' ' . $per );
		}

		return trim( $sym . number_format( (float) $raw ) . ' ' . $per );
	}
}
