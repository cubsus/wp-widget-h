/**
 * Hospitaliti Jobs – Admin Settings
 *
 * 1. Activates wp-color-picker on all colour inputs.
 * 2. Copy-to-clipboard for shortcode reference cards.
 */
(function ($) {
    'use strict';

    $(function () {

        // ── Colour pickers ────────────────────────────────────────────────
        $('.hospitaliti-color-picker').wpColorPicker();

        // ── Connection test ───────────────────────────────────────────────
        $('#hj-test-connection').on('click', function () {
            var $btn    = $(this);
            var $result = $('#hj-test-result');
            $btn.prop('disabled', true).text('Testing\u2026');
            $result.hide();
            $.post(hospitalitiAdmin.ajaxUrl, {
                action:      'hospitaliti_test_connection',
                _ajax_nonce: hospitalitiAdmin.nonce
            })
            .done(function (resp) {
                $result.text(resp.data.message)
                       .css('color', resp.success ? '#2a7a2a' : '#cc1818')
                       .show();
            })
            .fail(function () {
                $result.text('Request failed — check your browser console.').css('color', '#cc1818').show();
            })
            .always(function () {
                $btn.prop('disabled', false).text('Test API Connection');
            });
        });

        // ── Dynamic palette editor ────────────────────────────────────────
        $('#hj-add-color').on('click', function () {
            var $input = $('<input type="text" name="hospitaliti_bubbles_colors[]" value="#143f2b" />');
            var $btn   = $('<button type="button" class="button hj-remove-color" style="margin-top:4px;display:block;width:100%">Remove</button>');
            var $item  = $('<div class="hj-palette-item" style="text-align:center"></div>').append($input).append($btn);
            $('#hj-palette-wrap').append($item);
            $input.wpColorPicker();
        });

        $(document).on('click', '.hj-remove-color', function () {
            var $wrap = $('#hj-palette-wrap');
            if ($wrap.find('.hj-palette-item').length <= 1) { return; }
            var $item = $(this).closest('.hj-palette-item');
            try { $item.find('input').wpColorPicker('destroy'); } catch (e) { /* picker not yet init */ }
            $item.remove();
        });

        // ── Shortcode copy buttons ────────────────────────────────────────
        $(document).on('click', '.hj-sc-copy-btn', function () {
            var $btn       = $(this);
            var shortcode  = $btn.data('target') || '';

            if ( ! shortcode ) { return; }

            var originalLabel = $btn.text();

            function onCopied() {
                $btn.text('Copied!');
                setTimeout(function () {
                    $btn.text(originalLabel);
                }, 2000);
            }

            // Modern clipboard API.
            if ( navigator.clipboard && navigator.clipboard.writeText ) {
                navigator.clipboard.writeText(shortcode).then(onCopied).catch(function () {
                    fallbackCopy(shortcode, onCopied);
                });
            } else {
                fallbackCopy(shortcode, onCopied);
            }
        });

        /**
         * Fallback for browsers without navigator.clipboard.
         *
         * @param {string}   text
         * @param {Function} callback
         */
        function fallbackCopy(text, callback) {
            var $temp = $('<textarea>')
                .css({ position: 'fixed', top: '-9999px', left: '-9999px' })
                .val(text)
                .appendTo('body');
            $temp[0].focus();
            $temp[0].select();
            try {
                document.execCommand('copy');
                callback();
            } catch (e) {
                /* silent — browser may not support execCommand */
            }
            $temp.remove();
        }

    });
}(jQuery));
