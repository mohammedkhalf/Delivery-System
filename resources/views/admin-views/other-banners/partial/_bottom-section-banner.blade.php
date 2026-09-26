<div class="row g-3">
    <div class="col-lg-12 mx-auto mb-3 mb-lg-2">
        <div class="card h-100">
            <form action="{{ route('admin.promotional-banner.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="text" name="key" value="bottom_section_banner" hidden>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 d-flex justify-content-between">
                            <span class="d-flex g-1">
                                <img src="{{asset('public/assets/admin/img/other-banner.png')}}" class="h-85" alt="">
                                <h3 class="form-label d-block mb-2">
                                    {{translate('Bottom_Section_Banner')}}
                                </h3>
                            </span>
                        </div>
                        <div class="col-12">
                            <div id="bottom_section_banner_uploader"
                                data-showing-saved-image="{{ isset($bottom_section_banner?->value) ? 1 : 0 }}">
                                @include('admin-views.partials._image-uploader', [
                                    'id' => 'image-input',
                                    'name' => 'image',
                                    'ratio' => '5:1',
                                    'boxRatio' => '7:1',
                                    'isRequired' => false,
                                    'existingImage' => $bottom_section_banner?->value ?\App\CentralLogics\Helpers::get_full_url('promotional_banner', $bottom_section_banner?->value ?? '', $bottom_section_banner?->storage[0]?->value ?? 'public', ): null,
                                    'imageExtension' => IMAGE_EXTENSION,
                                    'imageFormat' => IMAGE_FORMAT,
                                    'maxSize' => MAX_FILE_SIZE,
                                    'textPosition' => 'bottom',
                                    ])
                            </div>
                        </div>
                    </div>
                    <div class="btn--container justify-content-end mt-20">
                        <button type="submit" class="btn btn--primary mb-2">{{translate('Submit')}}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<form id="bottom_section_banner_form" action="{{ route('admin.remove_image') }}" method="post">
    @csrf
    <input type="hidden" name="id" value="{{ $bottom_section_banner?->id }}">
    <input type="hidden" name="model_name" value="ModuleWiseBanner">
    <input type="hidden" name="image_path" value="promotional_banner">
    <input type="hidden" name="field_name" value="value">
</form>

@push('script_2')
    <script>
        (function () {
            var wrapper = document.getElementById('bottom_section_banner_uploader');
            if (!wrapper) return;

            var input = wrapper.querySelector('.single_file_input');
            input?.addEventListener('change', function () {
                if (input.files && input.files[0]) {
                    wrapper.setAttribute('data-showing-saved-image', '0');
                }
            });

            document.addEventListener('click', function (e) {
                var btn = e.target.closest('#bottom_section_banner_uploader .remove_btn');
                if (!btn || wrapper.getAttribute('data-showing-saved-image') !== '1') return;

                e.preventDefault();
                e.stopImmediatePropagation();

                $('#toggle-status-title').empty().append(@json(translate('Warning!')));
                $('#toggle-status-message').empty().append(@json('<p>'.translate('Are_you_sure_you_want_to_remove_this_image').'</p>'));
                $('#toggle-status-image').attr('src', '{{asset('public/assets/admin/img/modal')}}/mail-warning.png');
                $('#toggle-status-ok-button').attr('toggle-ok-button', 'bottom_section_banner');
                $('#toggle-status-modal').modal('show');
            }, true);
        })();
    </script>
@endpush
