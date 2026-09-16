/**
 * Hospitaliti Jobs — Bubble Listing Widget
 *
 * Handles client-side organisation filtering for the [hospitaliti_jobs_bubbles]
 * shortcode.  All bubbles are present in the DOM on page load; this script
 * shows/hides them based on which filter badge is active.
 *
 * No AJAX is used — all filtering is pure DOM manipulation.
 *
 * @package Hospitaliti_Jobs
 */

( function () {
	'use strict';

	/**
	 * Initialise one bubble section wrapper.
	 *
	 * @param {HTMLElement} section  .hj-bubbles-section element.
	 */
	function initSection( section ) {
		var badges  = section.querySelectorAll( '.hj-filter-badge' );
		var bubbles = section.querySelectorAll( '.hj-bubble' );

		if ( ! badges.length || ! bubbles.length ) {
			return;
		}

		/**
		 * Apply a filter.
		 *
		 * @param {string} org  Organisation name or 'all'.
		 */
		function applyFilter( org ) {
			// Update badge states.
			badges.forEach( function ( badge ) {
				var isActive = badge.getAttribute( 'data-org' ) === org;
				badge.classList.toggle( 'is-active', isActive );
				badge.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
			} );

			// Show/hide bubbles.
			bubbles.forEach( function ( bubble ) {
				if ( org === 'all' ) {
					bubble.classList.remove( 'is-hidden' );
				} else {
					var match = bubble.getAttribute( 'data-org' ) === org;
					bubble.classList.toggle( 'is-hidden', ! match );
				}
			} );
		}

		// Bind click handlers to badges.
		badges.forEach( function ( badge ) {
			badge.addEventListener( 'click', function () {
				var org = badge.getAttribute( 'data-org' ) || 'all';
				applyFilter( org );
			} );

			// Keyboard: Enter / Space trigger click.
			badge.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					badge.click();
				}
			} );
		} );
	}

	/**
	 * Initialise all bubble sections on the page.
	 */
	function init() {
		var sections = document.querySelectorAll( '.hj-bubbles-section' );
		sections.forEach( initSection );
	}

	// Run after DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
