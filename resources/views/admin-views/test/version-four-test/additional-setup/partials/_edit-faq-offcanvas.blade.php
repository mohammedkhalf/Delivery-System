<form action="">
    <div id="edit_faq" class="custom-offcanvas d-flex flex-column justify-content-between"
        style="--offcanvas-width: 500px">
            <div>
                <form id="filterForm" action="" method="GET">
                <div class="custom-offcanvas-header bg-light d-flex justify-content-between align-items-center">
                    <div class="px-3 py-3 d-flex justify-content-between w-100">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <h3 class="mb-0 fs-18 text-title font-medium">{{ translate('Edit FAQ') }}</h3>
    
                        </div>
                        <button type="button"
                            class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
                            aria-label="Close">&times;
                        </button>
                    </div>
                </div>
                <div class="custom-offcanvas-body p-20">
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
                                <div class="form-group mb-3">
                                    <label class="input-label fw-400" for="default_title">{{ translate('messages.Question') }}
                                            ({{ translate('messages.Default') }})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" 
                                            data-title="{{ translate('Add question within 150 characters') }}">
                                                <i class="tio-info text-muted fs-16"></i>
                                            </span>
                                    </label>
                                    <input type="text" name="title[]" id="default_title" maxlength="150"
                                            class="form-control" placeholder="{{ translate('messages.Enjoy Fresh Food') }}" value=""
                                    >
                                    <div class="d-flex justify-content-end">
                                        <span class="text-right text-counting color-A7A7A7 d-block mt-1">0/150</span>
                                    </div>
                                </div>                                    
                                <div class="form-group mb-0">
                                    <label class="input-label fw-400" for="subtitle">{{ translate('messages.answer') }} ({{ translate('messages.default') }})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" 
                                    data-title="{{ translate('Add answer within 500 characters') }}">
                                                <i class="tio-info text-muted fs-16"></i>
                                            </span>
                                    </label>
                                    <textarea type="text" rows="5" name="subtitle[]" maxlength="500" placeholder="{{translate('messages.Write a clear and concise answer for this question.')}}" class="form-control"></textarea>
                                    <div class="d-flex justify-content-end">
                                        <span class="text-right text-counting color-A7A7A7 d-block mt-1">0/500</span>
                                    </div>
                                </div>                                                   
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div  class="align-items-center bg-white bottom-0 d-flex gap-3 justify-content-center offcanvas-footer p-3 position-sticky">
                <a href="{{ route('admin.users.customer.list') }}"
                    class="btn w-100 btn--reset offcanvas-close">{{ translate('Cancel') }}</a>
                <button type="submit" id="apply_filter" class="btn w-100 btn--primary">{{ translate('Update') }}</button>
            </form>
            </div>
    </div>
</form>
<div id="offcanvasOverlay" class="offcanvas-overlay"></div>