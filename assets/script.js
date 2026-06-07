/* Order Supply List — script.js
   License: GPLv2 or later */
(function ($) {
    'use strict';

    function initJalaliDatepicker() {
        var root = document.getElementById('osl-root');
        if (!root || root.getAttribute('data-calendar') !== 'jalali') {
            return;
        }
        if (typeof window.jalaliDatepicker === 'undefined') {
            return;
        }

        window.jalaliDatepicker.startWatch({
            selector: '#osl-root input[data-jdp]',
            separatorChars: {
                date: '/',
                between: ' ',
                time: ':'
            },
            persianDigits: false,
            autoHide: true,
            hideAfterChange: true,
            showTodayBtn: true,
            showEmptyBtn: true,
            showCloseBtn: true,
            autoReadOnlyInput: false,
            zIndex: 999999
        });
    }

    $(document).ready(function () {
        $('#osl-refresh').on('click', function () {
            location.reload();
        });

        initJalaliDatepicker();
    });
})(jQuery);
