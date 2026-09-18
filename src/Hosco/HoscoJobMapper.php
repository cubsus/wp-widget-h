<?php

/**
 * HoscoJobMapper — transforms raw hosco.com API job arrays into the same
 * stdClass shape that JobMapper produces, so hosco jobs can reuse the
 * existing job-card.php and jobs-list.php templates without modification.
 *
 * Guest (unauthenticated) hosco response fields used here:
 *   id, slug, title, avatar, cover_public_path,
 *   company.name (or owner.name), displayed_location.address_display,
 *   excerpt, pay_range, posted_date, start_date, types[], url
 *
 * @package Hospitaliti_Jobs
 */

namespace Hospitaliti\Jobs\Hosco;

defined( 'ABSPATH' ) || exit;

class HoscoJobMapper {

	/**
	 * Map a collection of raw hosco result items.
	 *
	 * @param  array  $results Raw items from hosco { "results": [...] }.
	 * @param  string $locale  Locale for the hosco.com detail page URL (e.g. 'en').
	 * @return \stdClass[]
	 */
	public function mapCollection( array $results, string $locale = 'en' ): array {
		return array_map( fn( $r ) => $this->mapSingle( $r, $locale ), $results );
	}

	/**
	 * Map a single raw hosco job array to a structured stdClass.
	 *
	 * All properties mirror the shape of JobMapper::mapSingle() so templates
	 * can access $job->title, $job->organization, etc. without any changes.
	 *
	 * @param  array  $raw
	 * @param  string $locale  Locale for the hosco.com detail page URL.
	 * @return \stdClass
	 */
	public function mapSingle( array $raw, string $locale = 'en' ): \stdClass {
		$org = is_array( $raw['company'] ?? null ) ? $raw['company']
			: ( is_array( $raw['owner'] ?? null ) ? $raw['owner'] : [] );

		$orgName   = (string) ( $org['name']            ?? '' );
		$avatarUrl = (string) ( $raw['avatar'] ?? '' );
		$bannerUrl = (string) ( $raw['cover_public_path'] ?? $org['cover_path'] ?? '' );

		$types      = is_array( $raw['types'] ?? null ) ? $raw['types'] : [];
		$firstType  = $types[0] ?? '';
		$rawType    = is_array( $firstType )
			? (string) ( $firstType['slug'] ?? $firstType['code'] ?? $firstType['id'] ?? reset( $firstType ) ?? '' )
			: (string) $firstType;
		$empType = $this->normaliseType( $rawType );

		$loc         = is_array( $raw['displayed_location'] ?? null ) ? $raw['displayed_location'] : [];
		$locationStr = (string) ( $loc['address_display'] ?? '' );

		$salary = $this->extractNumericSalary( (string) ( $raw['pay_range'] ?? '' ) );

		$applyLink = 'https://www.hosco.com/' . $locale . '/job/' . ltrim( (string) ( $raw['slug'] ?? '' ), '/' );

		return (object) [
			'slug'              => trim( (string) ( $raw['slug'] ?? '' ), '-' ),
			'title'             => (string) ( $raw['title']      ?? '' ),
			'description'       => (string) ( $raw['excerpt']    ?? '' ),
			'experience'        => '',
			'skills'            => [],
			'benefits'          => [],
			'working_schedule'  => [],
			'employment_type'   => $empType,
			'salary_display'    => $salary,
			'salary'            => $salary,
			'salary_currency'   => '',
			'salary_period'     => '',
			'salary_negotiable' => false,
			'location_str'      => $locationStr,
			'location'          => $locationStr !== '' ? [ [ 'name' => $locationStr ] ] : [],
			'post_date'         => (string) ( $raw['posted_date'] ?? '' ),
			'starting_date'     => (string) ( $raw['start_date']  ?? '' ),
			'applied_candidates' => null,
			'applications_count' => null,
			'organization' => [
				'name'        => $orgName,
				'avatarImage' => $avatarUrl !== '' ? [ 'url' => $avatarUrl ] : [],
				'bannerImage' => $bannerUrl !== '' ? [ 'url' => $bannerUrl ] : [],
			],
			'apply_link' => $applyLink,
		];
	}

	/**
	 * Convert a hosco employment type slug to the dash-separated format used
	 * by the plugin's templates (e.g. 'full_time' → 'full-time').
	 *
	 * @param  string $hoscoType
	 * @return string
	 */
	private function normaliseType( string $hoscoType ): string {
		$map = [
			'full_time'       => 'full-time',
			'part_time'       => 'part-time',
			'internship'      => 'internship',
			'seasonal'        => 'seasonal',
			'freelance'       => 'contract',
			'volunteer'       => 'contract',
			'fulltime_job'    => 'full-time',
			'parttime_job'    => 'part-time',
			'internship_job'  => 'internship',
			'seasonal_job'    => 'seasonal',
			'freelance_job'   => 'contract',
		];
		return $map[ $hoscoType ] ?? str_replace( '_', '-', $hoscoType );
	}

	/**
	 * Return the pay_range value only when it is safe for job-card.php's
	 * number_format() parsing (digits, dash, dot, comma, apostrophe only).
	 *
	 * If the string contains currency symbols or letters (e.g. "€30,000"),
	 * we return '' so the template shows no salary rather than "0".
	 *
	 * @param  string $payRange
	 * @return string  Numeric-safe range string, or ''.
	 */
	private function extractNumericSalary( string $payRange ): string {
		if ( $payRange === '' ) {
			return '';
		}
		$stripped = str_replace( [ "'", ',' ], '', $payRange );
		if ( preg_match( '/^[\d.\s\-]+$/', trim( $stripped ) ) ) {
			return $payRange;
		}
		return '';
	}
}
