<form action="{{ route('vendor.store-category.update', [$category['id']]) }}" method="post" enctype="multipart/form-data" class="d-flex flex-column h-100">
    @csrf
    <div>
        <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
            <h3 class="mb-0">{{ translate('messages.Edit_My_Category') }}</h3>
            <button type="button" class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary text-dark offcanvas-close fz-15px p-0" aria-label="Close">&times;</button>
        </div>
        <div class="custom-offcanvas-body p-20">

            <div class="bg--secondary rounded p-20 mb-20">
                @if ($language)
                    <ul class="nav nav-tabs mb-4 border-0">
                        <li class="nav-item">
                            <a class="nav-link text-nowrap lang_link1 active" href="#" id="default-link">{{ translate('messages.default') }}</a>
                        </li>
                        @foreach ($language as $lang)
                            <li class="nav-item">
                                <a class="nav-link text-nowrap lang_link1" href="#" id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($language)
                    <div class="form-group lang_form1" id="default-form1">
                        <label class="input-label">
                            {{ translate('messages.Category_Name') }} ({{ translate('messages.default') }})
                            <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name[]" value="{{ $category?->getRawOriginal('name') }}" class="form-control" placeholder="{{ translate('messages.Type_Category_Name') }}" maxlength="255">
                    </div>
                    <input type="hidden" name="lang[]" value="default">
                    @foreach ($language as $key => $lang)
                        <?php
                            $translate = [];
                            if (count($category['translations'] ?? [])) {
                                foreach ($category['translations'] as $t) {
                                    if ($t->locale == $lang && $t->key == 'name') {
                                        $translate[$lang]['name'] = $t->value;
                                    }
                                }
                            }
                        ?>
                        <div class="form-group d-none lang_form1" id="{{ $lang }}-form1">
                            <label class="input-label">
                                {{ translate('messages.Category_Name') }} ({{ strtoupper($lang) }})
                            </label>
                            <input type="text" name="name[]" value="{{ $translate[$lang]['name'] ?? '' }}" class="form-control" placeholder="{{ translate('messages.Type_Category_Name') }}" maxlength="191">
                        </div>
                        <input type="hidden" name="lang[]" value="{{ $lang }}">
                    @endforeach
                @else
                    <div class="form-group">
                        <label class="input-label">{{ translate('messages.Category_Name') }}</label>
                        <input type="text" name="name[]" class="form-control" value="{{ $category?->getRawOriginal('name') }}" maxlength="191">
                    </div>
                    <input type="hidden" name="lang[]" value="default">
                @endif

                <div class="form-group mb-0">
                    <label class="input-label">{{ translate('messages.Priority') }}</label>
                    <select required name="priority" class="custom-select">
                        <option {{ $category->priority == 0 ? 'selected' : '' }} value="0">{{ translate('messages.Normal') }}</option>
                        <option {{ $category->priority == 1 ? 'selected' : '' }} value="1">{{ translate('messages.Medium') }}</option>
                        <option {{ $category->priority == 2 ? 'selected' : '' }} value="2">{{ translate('messages.High') }}</option>
                    </select>
                </div>
            </div>

            <div class="bg--secondary rounded p-20 mb-20">
                <div class="text-center py-1">
                    <div class="mx-auto text-center">
                        <div class="mb-4">
                            <h5 class="mb-1">{{ translate('messages.Category_Image') }}
                                @if (empty($category['image_full_url']))
                                    <span class="text-danger">*</span>
                                @endif
                            </h5>
                            <p class="mb-0 fs-12 gray-dark">{{ translate('messages.Upload_image') }}</p>
                        </div>
                        @include('admin-views.partials._image-uploader', [
                            'id' => 'store-category-image-input-' . $category['id'],
                            'name' => 'image',
                            'ratio' => '1:1',
                            'isRequired' => empty($category['image_full_url']),
                            'existingImage' => $category['image_full_url'] ?? '',
                            'imageExtension' => IMAGE_EXTENSION,
                            'imageFormat' => IMAGE_FORMAT,
                            'maxSize' => MAX_FILE_SIZE,
                            'textPosition' => 'bottom',
                            'show_clear_button' => false,
                        ])
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="align-items-center bg-white bottom-0 d-flex gap-3 justify-content-center mt-auto offcanvas-footer p-3 position-sticky">
        <button type="button" class="btn w-100 btn--reset offcanvas-close h--40px">{{ translate('Cancel') }}</button>
        <button type="submit" class="btn w-100 btn--primary h--40px">{{ translate('Update') }}</button>
    </div>
</form>
