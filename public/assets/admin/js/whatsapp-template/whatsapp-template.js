
    let editorInstance;
    $(document).ready(function () {

        /* ===============================
        CKEDITOR INIT
        =============================== */
        editorInstance = CKEDITOR.replace('editor-mail_type', {
            height: 200
        });

        /* ===============================
        BODY LIVE PREVIEW
        =============================== */
        editorInstance.on('change', function () {
            const data = editorInstance.getData();
            $('.view-mail-body').html(data || '');
        });

        /* ===============================
        HEADER TYPE TOGGLE
        =============================== */
        function toggleHeaderType() {
            const value = $('.edit__elect-imge-content').val();

            const $text = $('.tab-showing_text');
            const $img = $('.tab-showing_img');

            $text.addClass('d-none').removeClass('d-block');
            $img.addClass('d-none').removeClass('d-block');

            if (value === 'text') {
                $text.removeClass('d-none').addClass('d-block');

                $('.image__banner .preview-banner').remove();

                $('.view-mail-title').text($('.view-mail-title_main').val() || 'Your ride has been confirmed.');
            }
            else if (value === 'bannerImage') {
                $img.removeClass('d-none').addClass('d-block');
                $('.view-mail-title').text('');
            } 
            else {
                $('.image__banner .preview-banner').remove();

                $('.view-mail-title').text('');
            }
        }

        $('.edit__elect-imge-content').on('change', toggleHeaderType);
        toggleHeaderType();

        /* ===============================
        HEADER TEXT LIVE
        =============================== */
        $(document).on('input', '.view-mail-title_main', function () {
            $('.view-mail-title').text($(this).val());
        });

        /* ===============================
        FOOTER TEXT LIVE
        =============================== */
        $(document).on('input', '.footer-mail-title_main', function () {
            $('.view-copyright-text')
                .text($(this).val() || 'Thank you for choosing us!');
        });

        /* ===============================
        BUTTON TOGGLE
        =============================== */
        function toggleCTAButton() {
            const isChecked = $('.callto-action-switcher .switcher_input').is(':checked');

            if (isChecked) {
                $('.view-btn_edit').removeClass('d-none').addClass('d-flex');
            } else {
                $('.view-btn_edit').removeClass('d-flex').addClass('d-none');
            }
        }

        $(document).on('change', '.callto-action-switcher .switcher_input', function () {
            toggleCTAButton();
        });

        toggleCTAButton();

        /* ===============================
        BUTTON TEXT
        =============================== */
        $(document).on('input', '.footer__text-name', function () {
            $('.view-btn-text').text($(this).val() || 'Track your ride');
        });

        /* ===============================
        BUTTON URL
        =============================== */
        $(document).on('input', '.footer__text-url', function () {
            $('.view-btn_edit').attr('href', $(this).val() || '#');
        });

        /* ===============================
        IMAGE PREVIEW
        =============================== */
        $(document).on('change', '.single_file_input', function (e) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (e) {
                if ($('.preview-banner').length === 0) {
                    $('.bg-editor .card .image__banner').prepend(`
                        <img class="preview-banner w-100 rounded" style="height:150px; object-fit:cover;" />
                    `);
                }

                $('.preview-banner').attr('src', e.target.result);
            };
            reader.readAsDataURL(file);
        });

        /* ===============================
        VARIABLE INSERT (CKEDITOR FIX)
        =============================== */
        $(document).on('click', '.whatsapp-template-editor .dropdown-item', function (e) {
            e.preventDefault();

            const variable = $(this).find('.drop-data').text().trim();
            if (!variable || !editorInstance) return;

            editorInstance.focus();

            // Insert at cursor position (IMPORTANT)
            editorInstance.insertHtml(variable + ' ');
        });
    });