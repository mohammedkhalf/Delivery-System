<h3 class="mb-3 ">{{ translate('add FAQ') }}</h3>
<div class="card card-body mb-20">
    <div class="bg-light2 p-xl-20 p-3 rounded mb-20">
        <div class="lang_form" id="default-form">
            <div class="bg-light2 p-xl-20 p-3 rounded mb-20">
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
    <div class="btn--container justify-content-end">
        <button type="reset" class="btn btn--reset">{{translate('Reset')}}</button>
        <button type="submit"   class="btn btn--primary">{{translate('add')}}</button>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 p-20">
        <div class="search--button-wrapper">
            <h4 class="card-title d-flex align-items-center">{{translate('messages.FAQ List')}}</h4>
            <form class="search-form">
                <div class="input-group input--group">
                    <input id="datatableSearch_" type="search" name="search" value="{{ request()?->search ?? null }}" class="form-control"
                            placeholder="{{translate('Search_title')}}" aria-label="{{translate('messages.search_here')}}" >
                    <button type="submit" class="btn btn--secondary secondary-cmn"><i class="tio-search"></i></button>

                </div>
            </form>
        </div>
    </div>
    <div class="card-body p-20 pt-0">
        <div class="table-responsive datatable-custom py-0">
            <table class="table table-borderless table-thead-borderless table-align-middle table-nowrap card-table">
                <thead class="thead-light border-0">
                    <tr>
                        <th class="border-top-0">{{ translate('Sl') }}</th>
                        <th class="border-top-0">{{ translate('Question') }}</th>
                        <th class="border-top-0">{{ translate('Answer') }} </th>
                        <th class="border-top-0">{{ translate('Status') }}</th>
                        <th class="text-center border-top-0">{{ translate('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>
                            <div class="text--title word-break min-w-100px line-limit-2 max-w-220px text-wrap">
                                How do I place an order on the platform?
                            </div>
                        </td>
                        <td>
                            <div class="word-break min-w-176px line-limit-3 max-w-450px text-wrap">
                                Browse stores, add items to your cart, choose a delivery address, and confirm payment to place your order.
                            </div>
                        </td>
                        <td>
                            <label class="toggle-switch toggle-switch-sm">
                                <input type="checkbox" class="status toggle-switch-input" id="" checked="">
                                <span class="toggle-switch-label">
                                    <span class="toggle-switch-indicator"></span>
                                </span>
                            </label>
                        </td>
                        <td>
                            <div class="btn--container justify-content-center">
                                <a class="btn action-btn btn--primary btn-outline-primary offcanvas-trigger" 
                                    href="javascript:;" 
                                    data-target="#edit_faq">
                                    <i class="tio-edit"></i>
                                </a>
                                <a class="btn action-btn btn--danger btn-outline-danger" href="javascript:" title="">
                                    <i class="tio-delete-outlined"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
            <div class="empty--data">
            <img src="{{asset('public/assets/admin/img/no-data.png') }}" alt="public">
            <div class="text-muted fs-16">
                {{ translate('No FAQ List') }}
            </div>
        </div>
    </div>
</div>