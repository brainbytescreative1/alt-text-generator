/* global bbaiatAdmin, wp */
(function ($) {
    'use strict';
    var states = Object.create(null);
    var messages = bbaiatAdmin.i18n;

    function widgets(id) {
        return $('.bbaiat-widget[data-id="' + id + '"]');
    }

    function nativeAlt($widget) {
        var $edit = $('#attachment_alt');
        if ($edit.length && $('body').hasClass('post-type-attachment')) {
            return $edit;
        }
        return $widget.closest('.media-modal').find('textarea[data-setting="alt"], input[data-setting="alt"], [data-setting="alt"] textarea, [data-setting="alt"] input');
    }

    function mount($widget) {
        // WordPress supplies custom fields at the bottom of the details panel.
        // Put our control directly below the native alt field in media popups.
        if (!$widget.closest('.media-modal').length) { return; }
        var $setting = nativeAlt($widget).first().closest('.setting');
        if (!$setting.length) { return; }
        var $description = $setting.next('p.description');
        var $anchor = $description.length ? $description : $setting;
        if ($anchor.next()[0] === $widget[0]) { return; }
        var $oldRow = $widget.closest('tr.compat-field-bbaiat_generate');
        $anchor.next('.bbaiat-widget').remove();
        $widget.insertAfter($anchor);
        $oldRow.remove();
    }

    function render(id) {
        var state = states[id];
        if (!state) { return; }
        widgets(id).each(function () {
            var $widget = $(this);
            $widget.attr('aria-busy', state.busy ? 'true' : 'false');
            $widget.find('.bbaiat-generate').prop('disabled', state.busy);
            $widget.find('.bbaiat-status').text(state.message || '').toggleClass('bbaiat-error', !!state.error);
            $widget.find('.bbaiat-review').prop('hidden', !state.review);
            $widget.find('.bbaiat-suggestion').val(state.text || '').prop('disabled', state.busy);
            $widget.find('.bbaiat-use, .bbaiat-dismiss').prop('disabled', state.busy);
        });
    }

    function request(action, data) {
        return $.ajax({
            url: bbaiatAdmin.ajaxUrl,
            type: 'POST', dataType: 'json', timeout: 75000,
            data: $.extend({action: action, nonce: bbaiatAdmin.nonce}, data)
        });
    }

    function fail(state, xhr, status) {
        var data = xhr.responseJSON && xhr.responseJSON.data;
        state.message = data && typeof data.message === 'string' ? data.message :
            (status === 'timeout' ? messages.timeout : messages.error);
        state.error = true;
    }

    $(document).on('click', '.bbaiat-generate', function (event) {
        event.preventDefault();
        var $widget = $(this).closest('.bbaiat-widget');
        var id = Number($widget.data('id'));
        if (states[id] && states[id].busy) { return; }
        var state = states[id] = {
            busy: true, review: false, text: '', error: false,
            message: messages.generating, localOriginal: nativeAlt($widget).val()
        };
        render(id);
        request('bbaiat_generate', {id: id}).done(function (response) {
            if (!response || !response.success) {
                fail(state, {responseJSON: response});
                return;
            }
            state.text = response.data.suggestion;
            state.original = response.data.original;
            state.review = true;
            state.message = messages.review;
        }).fail(function (xhr, status) {
            fail(state, xhr, status);
        }).always(function () {
            state.busy = false;
            render(id);
        });
    });

    // These fields belong to the prototype, not WordPress's compat autosave form.
    $(document).on('input change', '.bbaiat-suggestion', function (event) {
        event.stopPropagation();
        var id = Number($(this).closest('.bbaiat-widget').data('id'));
        if (states[id]) { states[id].text = $(this).val(); }
    });

    $(document).on('click', '.bbaiat-use', function (event) {
        event.preventDefault();
        var $widget = $(this).closest('.bbaiat-widget');
        var id = Number($widget.data('id'));
        var state = states[id];
        if (!state || state.busy || !state.review) { return; }
        var $native = nativeAlt($widget);
        if (state.localOriginal !== undefined && $native.val() !== state.localOriginal) {
            state.message = messages.localChange;
            state.error = true;
            render(id);
            return;
        }
        state.text = $widget.find('.bbaiat-suggestion').val();
        state.busy = true;
        state.error = false;
        state.message = messages.saving;
        render(id);
        request('bbaiat_save', {id: id, alt: state.text, original: state.original}).done(function (response) {
            if (!response || !response.success) {
                fail(state, {responseJSON: response});
                return;
            }
            var alt = response.data.alt;
            // Update the shared attachment model used when inserting the image.
            if (window.wp && wp.media && wp.media.attachment) {
                wp.media.attachment(id).set('alt', alt);
            }
            $native.val(alt);
            widgets(id).closest('tr').find('.bbaiat-current').text(alt || 'No alt text');
            state.original = alt;
            state.localOriginal = alt;
            state.review = false;
            state.message = messages.saved;
        }).fail(function (xhr, status) {
            fail(state, xhr, status);
        }).always(function () {
            state.busy = false;
            render(id);
        });
    });

    $(document).on('click', '.bbaiat-dismiss', function (event) {
        event.preventDefault();
        var id = Number($(this).closest('.bbaiat-widget').data('id'));
        if (!states[id] || states[id].busy) { return; }
        states[id].review = false;
        states[id].message = '';
        render(id);
    });

    $(function () {
        $('.bbaiat-widget').each(function () { mount($(this)); });
        // Media views may rebuild the compat panel; preserve an in-flight result.
        new MutationObserver(function (records) {
            records.forEach(function (record) {
                $(record.addedNodes).filter(function () { return this.nodeType === 1; }).each(function () {
                    $(this).find('.bbaiat-widget').addBack('.bbaiat-widget').each(function () {
                        mount($(this));
                        render(Number($(this).data('id')));
                    });
                });
            });
        }).observe(document.body, {childList: true, subtree: true});
    });
})(jQuery);
