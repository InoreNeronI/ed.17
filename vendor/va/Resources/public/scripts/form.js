"use strict";

let _window = $(window),
    bgResize = function(_window) {
        $('body').css('height', _window.height());
        $('.form-bg').css('background-position', 'center bottom');
    };
_window.on('debouncedresize', function() {
    bgResize($(this));
    //console.log( 'Debounce event fired:'+ (+new Date()) );
});
_window.on('throttledresize', function() {
    bgResize($(this));
    //console.log( 'Throttle event fired:'+ (+new Date()) );
});
bgResize(_window);

$('form').on('submit', function() {
    $(this).find('button').each(function() { this.disabled = true; });
});