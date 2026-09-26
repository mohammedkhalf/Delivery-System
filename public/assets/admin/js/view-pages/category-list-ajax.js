"use strict";

/**
 * Swaps the category list (main or sub) via AJAX when a status tab, the search form, pagination,
 * or the search-reset button is used, instead of a full page navigation.
 *
 * Why this exists: the list sits on the same page as the "Add New" category form, which carries
 * its own unsaved state (the Default/EN/AR language tab selection and whatever was typed into the
 * name field). A full page reload for a list-only action wiped that out and caused a visible
 * flash of the whole page. Swapping only the wrapper's contents leaves the Add form untouched.
 *
 * A few row-level behaviors in this list (priority auto-submit, the Edit offcanvas open, the
 * delete confirmation, and the search-reset button) are bound directly to elements at page load
 * by other scripts (category-index.js / sub-category-index.js / offcanvas.js /
 * layouts/admin/app.blade.php) rather than delegated from a stable ancestor. Those bindings don't
 * reach elements injected later by innerHTML replacement, so this file re-applies the same
 * behavior to freshly-swapped content after every AJAX load.
 */
function initCategoryListAjax(wrapperId, translations) {
    const wrapper = document.getElementById(wrapperId);
    if (!wrapper) return;

    function bindRowHandlers() {
        wrapper.querySelectorAll('.priority-form').forEach(function (form) {
            const select = form.querySelector('.priority-select');
            if (select) {
                select.addEventListener('change', function () {
                    form.submit();
                });
            }
        });

        $(wrapper).find('.offcanvas-trigger').on('click', function (e) {
            e.preventDefault();
            const target = $(this).data('target');
            $(target).addClass('open');
            $('body').addClass('modal-open');
            $('#offcanvasOverlay').addClass('show');
        });

        $(wrapper).find('.form-alert').on('click', function () {
            let title = $(this).data('title');
            let message = $(this).data('message');
            let image = $(this).data('image-url');
            let cancel = $(this).data('cancel-btn');
            let confirm = $(this).data('confirm-btn');
            const id = $(this).data('id');

            if (!title) title = translations.areYouSure;
            if (!cancel) cancel = translations.no;
            if (!confirm) confirm = translations.yes;
            if (!image) image = translations.offDangerImage;

            Swal.fire({
                title: title,
                imageUrl: image,
                imageWidth: 80,
                imageHeight: 80,
                imageAlt: 'Custom icon',
                text: message,
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#FC6A57',
                cancelButtonText: cancel,
                confirmButtonText: confirm,
                reverseButtons: true
            }).then(function (result) {
                if (result.value) {
                    $('#' + id).submit();
                }
            });
        });

        $(wrapper).find('.location-reload-to-category').on('click', function (e) {
            e.preventDefault();
            const nurl = new URL($(this).data('url'), window.location.origin);
            nurl.searchParams.delete('search');
            loadList(nurl.toString());
        });
    }

    function loadList(url) {
        wrapper.style.opacity = '0.6';
        $.get(url)
            .done(function (html) {
                wrapper.innerHTML = html;
                bindRowHandlers();
            })
            .always(function () {
                wrapper.style.opacity = '';
            });
    }

    $(document).on('click', '#' + wrapperId + ' .nav-link, #' + wrapperId + ' .pagination a', function (e) {
        e.preventDefault();
        loadList(this.href);
    });

    $(document).on('submit', '#' + wrapperId + ' .search-form', function (e) {
        e.preventDefault();
        loadList(this.action.split('?')[0] + '?' + $(this).serialize());
    });
}
