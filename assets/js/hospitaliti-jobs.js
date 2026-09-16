/**
 * Hospitaliti Jobs Widget — AJAX Pagination, Load More, Per-Page Selector & Filters
 *
 * Handles four interaction modes that share a common AJAX helper:
 *
 *   1. SEARCH BAR  (.hospitaliti-search-input)
 *      Debounced input: fires AJAX 400 ms after the user stops typing.
 *      Clear (×) button instantly empties the field and reloads.
 *
 *   2. EMPLOYMENT TYPE FILTER  (.hospitaliti-filter-type checkboxes)
 *      Reloads page 1 with the selected employment types; updates badge count.
 *
 *   3. PER-PAGE DROPDOWN  (.hospitaliti-per-page)
 *      Reloads page 1 with the new page size; rebuilds numbered pagination.
 *
 *   3. NUMBERED PAGINATION  (.hospitaliti-prev-page / .hospitaliti-page-btn / .hospitaliti-next-page)
 *      Replaces the card list with the target page and rebuilds pagination buttons.
 *
 *   4. LOAD MORE  (.hospitaliti-load-more)
 *      Appends the next page of cards (legacy fallback mode).
 *
 * State is stored as data-* attributes on .hospitaliti-jobs-list.
 */

(function ($) {
    'use strict';

    /* =========================================================================
       Shared AJAX helper
       ========================================================================= */

    /**
     * Fetch a page of jobs via admin-ajax.php.
     *
     * @param {jQuery}   $wrap      The .hospitaliti-jobs-list container.
     * @param {number}   page       Target page number.
     * @param {Function} onSuccess  Called with the server response data object.
     * @param {Function} onError    Called on network / server error.
     */
    function fetchPage($wrap, page, onSuccess, onError) {
        // Build a clean payload — only include non-empty optional fields.
        var payload = {
            action:      'hospitaliti_load_more',
            nonce:       hospitaliti_ajax.nonce,
            page:        page,
            per_page:    parseInt($wrap.data('per-page'), 10) || hospitaliti_ajax.default_per_page,
            show_salary: $wrap.data('show-salary'),
            show_type:   $wrap.data('show-type'),
            show_date:   $wrap.data('show-date'),
        };

        // Only add filter fields if they have a non-empty / non-default value.
        var empType    = $wrap.data('employment-type');
        var search     = $wrap.data('search');
        var experience = $wrap.data('experience');
        var orgIds     = $wrap.data('organization-ids');
        if (empType)                            { payload.employment_type  = empType;  }
        if (search)                             { payload.search           = search;   }
        if (experience && experience !== 'all') { payload.experience       = experience; }
        if (orgIds)                             { payload.organization_ids = orgIds;   }

        $.ajax({
            url:  hospitaliti_ajax.ajax_url,
            type: 'POST',
            data: payload,
            success: function (response) {
                if (response.success && response.data) {
                    onSuccess(response.data);
                } else {
                    onError();
                }
            },
            error: onError,
        });
    }

    /* =========================================================================
       Pagination rebuild helpers
       ========================================================================= */

    /**
     * Build the sliding window of page numbers for the given current/last page.
     * Always includes page 1, up to `delta` pages either side of current, and page N.
     * Gaps wider than 1 are represented by the string 'ellipsis'.
     *
     * @param  {number} currentPage
     * @param  {number} lastPage
     * @returns {Array}  Mixed array of page numbers and 'ellipsis' strings.
     */
    function pageWindow(currentPage, lastPage) {
        var delta = 2;
        var pages = [1];
        var p;

        for (p = Math.max(2, currentPage - delta); p <= Math.min(lastPage - 1, currentPage + delta); p++) {
            pages.push(p);
        }
        if (lastPage > 1) { pages.push(lastPage); }

        // Deduplicate + sort.
        pages = pages.filter(function (v, i, a) { return a.indexOf(v) === i; });
        pages.sort(function (a, b) { return a - b; });

        // Insert ellipsis markers where the gap between consecutive pages > 1.
        var result = [];
        var prevP = 0;
        pages.forEach(function (pg) {
            if (prevP && pg - prevP > 1) { result.push('ellipsis'); }
            result.push(pg);
            prevP = pg;
        });
        return result;
    }

    /**
     * Rebuild the numbered page buttons inside the pagination <nav>.
     * Removes existing .hospitaliti-page-btn / .hospitaliti-page-ellipsis nodes,
     * then inserts fresh ones between Prev and Next.
     *
     * @param {jQuery} $nav         The .hospitaliti-pagination element.
     * @param {number} currentPage
     * @param {number} lastPage
     */
    function buildPageButtons($nav, currentPage, lastPage) {
        // Clear current buttons (but keep Prev and Next).
        $nav.find('.hospitaliti-page-btn, .hospitaliti-page-ellipsis').remove();

        var $nextBtn = $nav.find('.hospitaliti-next-page');
        var window   = pageWindow(currentPage, lastPage);

        window.forEach(function (entry) {
            if (entry === 'ellipsis') {
                $('<span>')
                    .addClass('hospitaliti-page-ellipsis')
                    .attr('aria-hidden', 'true')
                    .html('&hellip;')
                    .insertBefore($nextBtn);
            } else {
                var $btn = $('<button>')
                    .addClass('hospitaliti-page-btn')
                    .attr('type', 'button')
                    .attr('data-page', entry)
                    .attr('aria-label', 'Page ' + entry)
                    .text(entry);

                if (entry === currentPage) {
                    $btn.addClass('is-active').attr('aria-current', 'page');
                }
                $btn.insertBefore($nextBtn);
            }
        });
    }

    /**
     * Sync the entire pagination <nav> state after any page or per-page change.
     * Shows/hides the nav, rebuilds page buttons, and sets Prev/Next disabled state.
     *
     * @param {jQuery} $wrap   The .hospitaliti-jobs-list container.
     * @param {object} data    Server response: { page, last_page }.
     */
    function syncPagination($wrap, data) {
        var $nav = $wrap.find('.hospitaliti-pagination');
        if (!$nav.length) { return; }

        if (data.last_page > 1) {
            $nav.removeClass('hospitaliti-hidden');
            buildPageButtons($nav, data.page, data.last_page);
            $nav.find('.hospitaliti-prev-page').prop('disabled', data.page <= 1);
            $nav.find('.hospitaliti-next-page').prop('disabled', data.page >= data.last_page);
        } else {
            $nav.addClass('hospitaliti-hidden');
        }
    }

    /**
     * Show or hide the table footer (per-page + pagination) based on
     * whether the current result set has any jobs.
     *
     * @param {jQuery}  $wrap       The .hospitaliti-jobs-list container.
     * @param {boolean} hasResults  True when the API returned at least one job.
     */
    function syncFooter($wrap, hasResults) {
        var $footer = $wrap.find('.hospitaliti-table-footer');
        if (hasResults) {
            $footer.removeClass('hospitaliti-hidden');
        } else {
            $footer.addClass('hospitaliti-hidden');
        }
    }

    /**
     * Show the loading overlay over the jobs column.
     * Adds .is-fetching to .hospitaliti-jobs-column, which CSS uses to:
     *   • reveal the spinner overlay
     *   • dim the existing card list
     *   • block pointer events on the cards area
     *
     * @param {jQuery} $wrap  The .hospitaliti-jobs-list container.
     */
    function showCardsLoader($wrap) {
        $wrap.find('.hospitaliti-jobs-column').addClass('is-fetching');
    }

    /**
     * Hide the loading overlay and restore the cards area.
     *
     * @param {jQuery} $wrap  The .hospitaliti-jobs-list container.
     */
    function hideCardsLoader($wrap) {
        $wrap.find('.hospitaliti-jobs-column').removeClass('is-fetching');
    }

    /* =========================================================================
       Mode 1 — Search bar (debounced)
       ========================================================================= */

    var searchTimer;
    var SEARCH_DEBOUNCE = 400; // ms — tweak here to adjust typing delay

    /**
     * Vue-like reactive helper: show or remove the clear (×) button
     * immediately on every keystroke, without waiting for the AJAX call.
     *
     * @param {jQuery} $wrap   The .hospitaliti-jobs-list container.
     * @param {string} term    Current (trimmed) input value.
     */
    function syncClearButton($wrap, term) {
        var $searchWrap = $wrap.find('.hospitaliti-search-wrap');
        var $clear      = $searchWrap.find('.hospitaliti-search-clear');
        if (term && !$clear.length) {
            $searchWrap.append(
                $('<button>').addClass('hospitaliti-search-clear')
                    .attr('type', 'button')
                    .attr('aria-label', 'Clear search')
                    .text('\u00d7') // ×
            );
        } else if (!term) {
            $clear.remove();
        }
    }

    /**
     * Reload the job list with the current search term, resetting to page 1.
     * Called immediately by the clear button and after the debounce delay on input.
     *
     * @param {jQuery} $wrap   The .hospitaliti-jobs-list container.
     * @param {string} term    Trimmed search string (may be empty).
     */
    function applySearch($wrap, term) {
        var $searchWrap = $wrap.find('.hospitaliti-search-wrap');

        $wrap.data('search', term).attr('data-search', term);
        $wrap.data('page', 1).attr('data-page', 1);

        // Enter loading state — search icon spinner + cards overlay.
        $searchWrap.addClass('is-searching');
        showCardsLoader($wrap);

        $wrap.find('.hospitaliti-search-input').prop('disabled', true);
        $wrap.find('.hospitaliti-pagination button').prop('disabled', true);

        fetchPage(
            $wrap,
            1,
            function (data) {
                $wrap.find('.hospitaliti-jobs-cards').html(data.html);
                $wrap.data('last-page', data.last_page).attr('data-last-page', data.last_page);
                syncPagination($wrap, data);
                syncFooter($wrap, data.has_results);
                $wrap.find('.hospitaliti-search-input').prop('disabled', false);
                $searchWrap.removeClass('is-searching');
                hideCardsLoader($wrap);
            },
            function () {
                $wrap.find('.hospitaliti-search-input').prop('disabled', false);
                $searchWrap.removeClass('is-searching');
                hideCardsLoader($wrap);
            }
        );
    }

    // Reactive + debounced input handler — mirrors Vue's v-model + debounced watcher.
    //
    // Phase 1 (immediate):  sync the clear button and mark the wrap as "debouncing"
    //                       so the user gets instant visual feedback on every keystroke.
    // Phase 2 (debounced):  fire the AJAX call only after the user pauses typing.
    $(document).on('input', '.hospitaliti-search-input', function () {
        var $input      = $(this);
        var $wrap       = $input.closest('.hospitaliti-jobs-list');
        var $searchWrap = $input.closest('.hospitaliti-search-wrap');
        var term        = $input.val().trim();

        // — Immediate (reactive) —
        syncClearButton($wrap, term);
        $searchWrap.addClass('is-debouncing');

        // — Debounced (expensive) —
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            $searchWrap.removeClass('is-debouncing');
            applySearch($wrap, term);
        }, SEARCH_DEBOUNCE);
    });

    // Clear (×) button — reactive + immediate, no debounce needed.
    $(document).on('click', '.hospitaliti-search-clear', function () {
        var $wrap  = $(this).closest('.hospitaliti-jobs-list');
        var $input = $wrap.find('.hospitaliti-search-input');
        $input.val('').focus();
        clearTimeout(searchTimer);
        $wrap.find('.hospitaliti-search-wrap').removeClass('is-debouncing');
        syncClearButton($wrap, '');
        applySearch($wrap, '');
    });

    /* =========================================================================
       Mode 2 — Employment type filter checkboxes
       ========================================================================= */

    /**
     * Update the filter badge count shown on the mobile toggle button.
     *
     * @param {jQuery} $wrap   The .hospitaliti-jobs-list container.
     * @param {number} count   Number of active filters.
     */
    function updateFilterBadge($wrap, count) {
        var $toggle = $wrap.find('.hospitaliti-filter-toggle');
        var $badge  = $toggle.find('.hospitaliti-filter-badge');
        if (count > 0) {
            if ($badge.length) {
                $badge.text(count);
            } else {
                $toggle.append($('<span>').addClass('hospitaliti-filter-badge').text(count));
            }
        } else {
            $badge.remove();
        }

        // Show the reset icon when any filter is active; hide it when none are.
        var $clearBtn = $wrap.find('.hospitaliti-filter-clear');
        if (count > 0) {
            $clearBtn.removeClass('hospitaliti-hidden');
        } else {
            $clearBtn.addClass('hospitaliti-hidden');
        }
    }

    $(document).on('change', '.hospitaliti-filter-type', function () {
        var $wrap = $(this).closest('.hospitaliti-jobs-list');

        // Collect all checked employment type values.
        var checked = [];
        $wrap.find('.hospitaliti-filter-type:checked').each(function () {
            checked.push($(this).val());
        });
        var empType = checked.join(',');

        // Sync onto the container so fetchPage picks up the new value.
        $wrap.data('employment-type', empType).attr('data-employment-type', empType);
        $wrap.data('page', 1).attr('data-page', 1);

        // Update badge: type + experience + company counts.
        var curExp     = String($wrap.data('experience') || '');
        var expCount   = curExp ? 1 : 0;
        var compCount  = $wrap.find('.hospitaliti-filter-company:checked').length;
        updateFilterBadge($wrap, checked.length + expCount + compCount);

        // Disable all checkboxes and pagination while loading; show overlay.
        $wrap.find('.hospitaliti-filter-type, .hospitaliti-pagination button').prop('disabled', true);
        showCardsLoader($wrap);

        fetchPage(
            $wrap,
            1,
            function (data) {
                $wrap.find('.hospitaliti-jobs-cards').html(data.html);
                $wrap.data('last-page', data.last_page).attr('data-last-page', data.last_page);
                syncPagination($wrap, data);
                syncFooter($wrap, data.has_results);
                $wrap.find('.hospitaliti-filter-type').prop('disabled', false);
                hideCardsLoader($wrap);
            },
            function () {
                $wrap.find('.hospitaliti-filter-type').prop('disabled', false);
                hideCardsLoader($wrap);
            }
        );
    });

    // Experience Level radio buttons.
    $(document).on('change', '.hospitaliti-filter-experience', function () {
        var $wrap      = $(this).closest('.hospitaliti-jobs-list');
        var experience = $(this).val();

        // Sync onto the container.
        $wrap.data('experience', experience).attr('data-experience', experience);
        $wrap.data('page', 1).attr('data-page', 1);

        // Update badge: type + experience + company counts.
        var typeCount  = $wrap.find('.hospitaliti-filter-type:checked').length;
        var expActive  = experience ? 1 : 0;
        var compCount  = $wrap.find('.hospitaliti-filter-company:checked').length;
        updateFilterBadge($wrap, typeCount + expActive + compCount);

        // Disable controls while loading; show overlay.
        $wrap.find('.hospitaliti-filter-experience, .hospitaliti-filter-type, .hospitaliti-pagination button').prop('disabled', true);
        showCardsLoader($wrap);

        fetchPage(
            $wrap,
            1,
            function (data) {
                $wrap.find('.hospitaliti-jobs-cards').html(data.html);
                $wrap.data('last-page', data.last_page).attr('data-last-page', data.last_page);
                syncPagination($wrap, data);
                syncFooter($wrap, data.has_results);
                $wrap.find('.hospitaliti-filter-experience, .hospitaliti-filter-type').prop('disabled', false);
                hideCardsLoader($wrap);
            },
            function () {
                $wrap.find('.hospitaliti-filter-experience, .hospitaliti-filter-type').prop('disabled', false);
                hideCardsLoader($wrap);
            }
        );
    });

    // Company filter checkboxes.
    $(document).on('change', '.hospitaliti-filter-company', function () {
        var $wrap   = $(this).closest('.hospitaliti-jobs-list');
        var checked = [];
        $wrap.find('.hospitaliti-filter-company:checked').each(function () {
            checked.push($(this).val());
        });
        var orgIds = checked.join(',');

        $wrap.data('organization-ids', orgIds).attr('data-organization-ids', orgIds);
        $wrap.data('page', 1).attr('data-page', 1);

        // Update badge: type + experience + company counts.
        var typeCount = $wrap.find('.hospitaliti-filter-type:checked').length;
        var curExp    = String($wrap.data('experience') || '');
        var expCount  = curExp ? 1 : 0;
        updateFilterBadge($wrap, typeCount + expCount + checked.length);

        $wrap.find('.hospitaliti-filter-company, .hospitaliti-filter-type, .hospitaliti-pagination button').prop('disabled', true);
        showCardsLoader($wrap);

        fetchPage(
            $wrap,
            1,
            function (data) {
                $wrap.find('.hospitaliti-jobs-cards').html(data.html);
                $wrap.data('last-page', data.last_page).attr('data-last-page', data.last_page);
                syncPagination($wrap, data);
                syncFooter($wrap, data.has_results);
                $wrap.find('.hospitaliti-filter-company, .hospitaliti-filter-type').prop('disabled', false);
                hideCardsLoader($wrap);
            },
            function () {
                $wrap.find('.hospitaliti-filter-company, .hospitaliti-filter-type').prop('disabled', false);
                hideCardsLoader($wrap);
            }
        );
    });

    // Clear all filters button — resets employment types, experience, company, and fires one AJAX reload.
    $(document).on('click', '.hospitaliti-filter-clear', function () {
        var $wrap = $(this).closest('.hospitaliti-jobs-list');

        // Uncheck all employment type checkboxes.
        $wrap.find('.hospitaliti-filter-type').prop('checked', false);

        // Uncheck all experience radios (back to initial "nothing selected" state)...
        $wrap.find('.hospitaliti-filter-experience').prop('checked', false);
        // ...and clear the data attribute so fetchPage sends no experience filter.
        $wrap.data('experience', '').attr('data-experience', '');

        // Uncheck all company checkboxes and clear the data attribute.
        $wrap.find('.hospitaliti-filter-company').prop('checked', false);
        $wrap.data('organization-ids', '').attr('data-organization-ids', '');

        // One AJAX call via the type-checkbox handler (which will call fetchPage).
        $wrap.find('.hospitaliti-filter-type').first().trigger('change');
    });

    // Mobile: toggle filter sidebar open/closed.
    $(document).on('click', '.hospitaliti-filter-toggle', function () {
        var $btn      = $(this);
        var $wrap     = $btn.closest('.hospitaliti-jobs-list');
        var $sidebar  = $wrap.find('.hospitaliti-filter-sidebar');
        var isOpen    = $sidebar.hasClass('is-open');

        $sidebar.toggleClass('is-open', !isOpen);
        $btn.attr('aria-expanded', !isOpen ? 'true' : 'false');
    });

    /* =========================================================================
       Mode 3 — Per-page custom dropdown
       ========================================================================= */

    // Toggle open / closed.
    $(document).on('click', '.hospitaliti-per-page-btn', function (e) {
        e.stopPropagation();
        var $dropdown = $(this).closest('.hospitaliti-per-page-dropdown');
        var isOpen    = $dropdown.hasClass('is-open');

        // Close any other open dropdowns first.
        $('.hospitaliti-per-page-dropdown.is-open').not($dropdown)
            .removeClass('is-open')
            .find('.hospitaliti-per-page-btn').attr('aria-expanded', 'false');

        $dropdown.toggleClass('is-open', !isOpen);
        $(this).attr('aria-expanded', !isOpen ? 'true' : 'false');
    });

    // Close when clicking outside.
    $(document).on('click', function () {
        $('.hospitaliti-per-page-dropdown.is-open')
            .removeClass('is-open')
            .find('.hospitaliti-per-page-btn').attr('aria-expanded', 'false');
    });

    // Option selected.
    $(document).on('click', '.hospitaliti-per-page-option', function (e) {
        e.stopPropagation();
        var $option    = $(this);
        var $dropdown  = $option.closest('.hospitaliti-per-page-dropdown');
        var $wrap      = $dropdown.closest('.hospitaliti-jobs-list');
        var newPerPage = parseInt($option.data('value'), 10) || hospitaliti_ajax.default_per_page;

        // Skip if already the active option.
        if ($option.hasClass('is-active')) {
            $dropdown.removeClass('is-open')
                .find('.hospitaliti-per-page-btn').attr('aria-expanded', 'false');
            return;
        }

        // Update button label + active state.
        $dropdown.find('.hospitaliti-per-page-value').text(newPerPage);
        $dropdown.find('.hospitaliti-per-page-option')
            .removeClass('is-active').attr('aria-selected', 'false');
        $option.addClass('is-active').attr('aria-selected', 'true');
        $dropdown.removeClass('is-open')
            .find('.hospitaliti-per-page-btn').attr('aria-expanded', 'false');

        // Persist new page size.
        $wrap.data('per-page', newPerPage).attr('data-per-page', newPerPage);

        // Disable dropdown button and pagination while loading; show overlay.
        $dropdown.find('.hospitaliti-per-page-btn').prop('disabled', true);
        $wrap.find('.hospitaliti-pagination button').prop('disabled', true);
        showCardsLoader($wrap);

        fetchPage(
            $wrap,
            1,
            function (data) {
                $wrap.find('.hospitaliti-jobs-cards').html(data.html);
                $wrap.data('page', 1).attr('data-page', 1);
                $wrap.data('last-page', data.last_page).attr('data-last-page', data.last_page);
                syncPagination($wrap, data);
                syncFooter($wrap, data.has_results);
                $dropdown.find('.hospitaliti-per-page-btn').prop('disabled', false);
                hideCardsLoader($wrap);
            },
            function () {
                $dropdown.find('.hospitaliti-per-page-btn').prop('disabled', false);
                var cp = parseInt($wrap.data('page'), 10) || 1;
                var lp = parseInt($wrap.data('last-page'), 10) || 1;
                syncPagination($wrap, { page: cp, last_page: lp });
                hideCardsLoader($wrap);
            }
        );
    });

    /* =========================================================================
       Mode 3a — Numbered page button click
       ========================================================================= */

    $(document).on('click', '.hospitaliti-page-btn', function () {
        var $btn = $(this);

        // Ignore clicks on the already-active page.
        if ($btn.hasClass('is-active')) { return; }

        var $wrap      = $btn.closest('.hospitaliti-jobs-list');
        var $nav       = $wrap.find('.hospitaliti-pagination');
        var targetPage = parseInt($btn.data('page'), 10);

        // Disable all nav buttons while loading; show overlay.
        $nav.find('button').prop('disabled', true);
        showCardsLoader($wrap);

        fetchPage(
            $wrap,
            targetPage,
            function (data) {
                $wrap.find('.hospitaliti-jobs-cards').html(data.html);
                $wrap.data('page', data.page).attr('data-page', data.page);
                $wrap.data('last-page', data.last_page).attr('data-last-page', data.last_page);
                syncPagination($wrap, data);
                syncFooter($wrap, data.has_results);
                hideCardsLoader($wrap);
                // Scroll back to the top of the widget so the user sees the new page.
                $('html, body').animate(
                    { scrollTop: $wrap.offset().top - 20 },
                    300
                );
            },
            function () {
                // Restore on error.
                var cp = parseInt($wrap.data('page'), 10) || 1;
                var lp = parseInt($wrap.data('last-page'), 10) || 1;
                syncPagination($wrap, { page: cp, last_page: lp });
                hideCardsLoader($wrap);
            }
        );
    });

    /* =========================================================================
       Mode 3b — Prev / Next button click
       ========================================================================= */

    $(document).on('click', '.hospitaliti-prev-page, .hospitaliti-next-page', function () {
        var $btn        = $(this);
        var $wrap       = $btn.closest('.hospitaliti-jobs-list');
        var $nav        = $wrap.find('.hospitaliti-pagination');
        var currentPage = parseInt($wrap.data('page'),      10) || 1;
        var lastPage    = parseInt($wrap.data('last-page'), 10) || 1;
        var targetPage  = $btn.hasClass('hospitaliti-prev-page')
            ? currentPage - 1
            : currentPage + 1;

        // Boundary guard.
        if (targetPage < 1 || targetPage > lastPage) { return; }

        $nav.find('button').prop('disabled', true);
        showCardsLoader($wrap);

        fetchPage(
            $wrap,
            targetPage,
            function (data) {
                $wrap.find('.hospitaliti-jobs-cards').html(data.html);
                $wrap.data('page', data.page).attr('data-page', data.page);
                $wrap.data('last-page', data.last_page).attr('data-last-page', data.last_page);
                syncPagination($wrap, data);
                syncFooter($wrap, data.has_results);
                hideCardsLoader($wrap);
                // Scroll back to the top of the widget so the user sees the new page.
                $('html, body').animate(
                    { scrollTop: $wrap.offset().top - 20 },
                    300
                );
            },
            function () {
                var cp = parseInt($wrap.data('page'), 10) || 1;
                var lp = parseInt($wrap.data('last-page'), 10) || 1;
                syncPagination($wrap, { page: cp, last_page: lp });
                hideCardsLoader($wrap);
            }
        );
    });

    /* =========================================================================
       Mode 4 — Load More  (legacy fallback)
       ========================================================================= */

    $(document).on('click', '.hospitaliti-load-more', function () {
        var $btn   = $(this);
        var $label = $btn.find('.hospitaliti-load-more-text');
        var $wrap  = $btn.closest('.hospitaliti-jobs-list');
        var $cards = $wrap.find('.hospitaliti-jobs-cards');

        var currentPage = parseInt($wrap.data('page'),      10) || 1;
        var lastPage    = parseInt($wrap.data('last-page'), 10) || 1;
        var nextPage    = currentPage + 1;

        if (nextPage > lastPage) {
            $label.text(hospitaliti_ajax.i18n.no_more);
            $btn.prop('disabled', true);
            return;
        }

        // Enter loading state — swap arrow for spinner, update label.
        $btn.addClass('is-loading').prop('disabled', true);
        $label.text(hospitaliti_ajax.i18n.loading);

        fetchPage(
            $wrap,
            nextPage,
            function (data) {
                $cards.append(data.html);
                $wrap.data('page', data.page).attr('data-page', data.page);

                if (data.has_more) {
                    // Back to idle — restore arrow icon and original label.
                    $btn.removeClass('is-loading').prop('disabled', false);
                    $label.text(hospitaliti_ajax.i18n.load_more);
                } else {
                    // All jobs loaded — fade out the whole wrap.
                    $btn.closest('.hospitaliti-load-more-wrap').fadeOut(300);
                }
            },
            function () {
                // Error — restore arrow icon, show error text.
                $btn.removeClass('is-loading').prop('disabled', false);
                $label.text(hospitaliti_ajax.i18n.error);
            }
        );
    });

}(jQuery));
