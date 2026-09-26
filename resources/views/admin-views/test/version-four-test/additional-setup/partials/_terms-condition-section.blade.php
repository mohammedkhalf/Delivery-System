<form action="">
    <div class="card card-body">
        <div class="bg-light2 p-xl-20 p-3 rounded mb-20">
            <div class="row g-3 align-items-center">
                <div class="col-xxl-9 col-lg-8 col-md-7 col-sm-6">
                    <div>
                        <h3 class="mb-1" id="combined_payment_section">
                            {{ translate('messages.Availability') }}
                        </h3>
                        <p class="mb-0 fs-12">
                            {{ translate('messages.If you turn of the availability status, this page will not show in the Subscription Plan') }}
                        </p>
                    </div>
                </div>
                <div class="col-xxl-3 col-lg-4 col-md-5 col-sm-6">
                    <div class="form-group mb-0">
                        <label class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                            <span class="pr-1 d-flex align-items-center switch--label">
                                <span class="line--limit-1">
                                    {{ translate('messages.Status') }}
                                </span>
                            </span>
                            <input type="checkbox" data-id=""
                                class="status toggle-switch-input" 
                                value="1" 
                                name="" 
                                id="" checked="">
                            <span class="toggle-switch-label text">
                                <span class="toggle-switch-indicator"></span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="mb-20">
            <h5 class="font-medium mb-3">
                {{ translate('Title Background Image') }}
            </h5>
            <div class="bg-light2 p-xl-4 p-4 rounded">
                <div class="text-center">
                    @include('admin-views.partials._image-uploader', [
                            'id' => 'image-input',
                            'name' => 'title-bg-image',
                            'ratio' => '7:1',
                            'isRequired' => false,
                            'existingImage' => null,
                            'imageExtension' => IMAGE_EXTENSION,
                            'imageFormat' => IMAGE_FORMAT,
                            'maxSize' => MAX_FILE_SIZE,
                            'textPosition' => 'bottom',
                        ]
                    )
                </div>
            </div>
        </div>
        <div class="bg-light2 p-xl-20 p-3 rounded mb-20">
            <div class="card-body p-0">
                <div class="js-nav-scroller hs-nav-scroller-horizontal">
                    <ul class="nav nav-tabs mb-4">
                        <li class="nav-item">
                            <a class="nav-link lang_link active" href="#"id="default-link">{{ translate('Default') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link lang_link"href="#" id="">English(EN)</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link lang_link"href="#" id="">Bengali - বাংলা(BN)</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link lang_link"href="#" id="">Arabic - العربية(AR)</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link lang_link"href="#" id="">Spanish - español(ES)</a>
                        </li>
                    </ul>
                </div>
                <div class="lang_form" id="default-form">
                    <div class="row g-1">
                        <div class="col-md-12">
                            <div class="form-group mb-0">
                                <label class="input-label fw-400" for="default_title">{{ translate('messages.page title') }}
                                        ({{ translate('messages.Default') }})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" 
                                            data-original-title="{{ translate('messages.Type page title within 100 characters') }}">
                                            <i class="tio-info text-muted fs-16"></i>
                                        </span>
                                </label>
                                <input type="text" name="title[]" id="default_title" maxlength="100"
                                        class="form-control" placeholder="{{ translate('messages.type page title') }}" value=""
                                >
                                <div class="d-flex justify-content-end">
                                    <span class="text-right text-counting color-A7A7A7 d-block mt-1">0/100</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group mb-0">
                                <label class="input-label fw-400" for="">{{ translate('messages.page description') }}</label>
                                <input type="hidden" name="lang[]" value="default">     
                               <textarea id="term_condition" class="ckeditor form-control" name="term_condition[]"></textarea>                        
                            </div>
                        </div>
                    </div>                                        
                </div>
            </div>
        </div>
        <div class="btn--container justify-content-end">
            <button type="reset" class="btn min-w-120 btn--reset">{{translate('Reset')}}</button>
            <button type="submit"   class="btn min-w-120 btn--primary">
                <i class="tio-save"></i>
                {{translate('save information')}}
            </button>
        </div>
    </div>
</form>