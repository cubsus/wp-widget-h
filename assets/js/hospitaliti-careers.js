/**
 * Hospitaliti Jobs – Careers JS
 *
 * Handles:
 *   1. Page loader  (Minimal theme)
 *   2. Scroll reveal (data-hj-reveal)
 */

(function ($) {
    'use strict';

    /* =========================================================================
       1. Page loader  (Minimal theme — #hospitaliti-page-loader rendered in PHP)
       ========================================================================= */

    var $loader = $('#hospitaliti-page-loader');

    if ($loader.length) {
        // NOTE: use a var expression, NOT a function declaration inside a block.
        // Function declarations inside if-blocks are disallowed by ES5 strict mode
        // and cause the entire script to fail silently in some environments.
        var loaderDone = false;
        var hideLoader = function () {
            if (loaderDone) { return; }
            loaderDone = true;
            $loader.addClass('is-hidden');
            // Remove from DOM after CSS transition completes (0.55s defined in CSS).
            setTimeout(function () { $loader.remove(); }, 600);
        };

        // Primary trigger: the script loads in the footer so window.load has
        // usually already fired. Check readyState first.
        if (document.readyState === 'complete') {
            setTimeout(hideLoader, 500);
        } else {
            $(window).on('load', function () { setTimeout(hideLoader, 500); });
        }

        // Failsafe: never block the user longer than 2 s regardless.
        setTimeout(hideLoader, 2000);
    }

    /* =========================================================================
       2. Scroll reveal  (data-hj-reveal — only added to DOM in Minimal theme)
       ========================================================================= */

    var revealEls = document.querySelectorAll('[data-hj-reveal]');

    if (revealEls.length) {
        var prefersReduced = window.matchMedia
            ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
            : false;

        if (prefersReduced) {
            // Reduced-motion: show everything immediately, skip all animation.
            revealEls.forEach(function (el) { el.classList.add('hj-revealed'); });
        } else {

            // ── Step 1: Reveal above-fold elements after the loader hides ────────
            // Elements already in the viewport animate in after ~500 ms (when the
            // loader fades). CSS transition-delay on [data-hj-delay] staggers them.
            // We re-check positions at reveal time so the split is accurate.
            setTimeout(function () {
                revealEls.forEach(function (el) {
                    var rect = el.getBoundingClientRect();
                    if (rect.top < window.innerHeight && rect.bottom > 0) {
                        el.classList.add('hj-revealed');
                    }
                });
            }, 520);

            // ── Step 2: Reveal below-fold elements as user scrolls ───────────────
            // IntersectionObserver fires for each element individually when it
            // enters the viewport — this is what creates the per-section scroll
            // reveal effect.
            if ('IntersectionObserver' in window) {
                var revealObserver = new IntersectionObserver(
                    function (entries) {
                        entries.forEach(function (entry) {
                            if (entry.isIntersecting) {
                                entry.target.classList.add('hj-revealed');
                                revealObserver.unobserve(entry.target);
                            }
                        });
                    },
                    {
                        threshold:  0,          // Fire as soon as 1 px enters viewport.
                        rootMargin: '0px',      // Full viewport — no shrinking.
                    }
                );

                // Observe ALL elements; above-fold ones get hj-revealed via
                // Step 1 first — the observer will then ignore them (already revealed).
                revealEls.forEach(function (el) { revealObserver.observe(el); });
            }

            // ── Step 3: Safety net ───────────────────────────────────────────────
            // After 5 s, force-reveal any element that still hasn't been revealed
            // (e.g. user hasn't scrolled to it yet and JS somehow missed it).
            // Each element still gets its own transition — they don't all fire at once.
            setTimeout(function () {
                revealEls.forEach(function (el) {
                    if (!el.classList.contains('hj-revealed')) {
                        el.classList.add('hj-revealed');
                    }
                });
            }, 5000);
        }
    }

}(jQuery));
