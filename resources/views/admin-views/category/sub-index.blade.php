@extends('layouts.admin.app')

@section('title',translate('messages.Add new main sub category'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/edit.png')}}" class="w--20" alt="">
                </span>
                <span>
                    {{translate('messages.add_new_main_sub_category')}}
                </span>
            </h1>
        </div>
        <!-- End Page Header -->
        <div class="card">
            <div class="card-body">
                <form action="{{route('admin.category.store')}}" method="post">
                @csrf
                    <div class="row">
                    @if($language)
                        @php($defaultLang = $language[0])
                        <div class="col-sm-12">
                            <ul class="nav nav-tabs mb-4">
                                <li class="nav-item">
                                    <a class="nav-link lang_link active"
                                    href="#"
                                    id="default-link">{{translate('messages.default')}}</a>
                                </li>
                                @foreach ($language as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link lang_link"
                                            href="#"
                                            id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="form-group lang_form col-sm-6" id="default-form">
                            <label class="input-label" for="exampleFormControlInput1">{{translate('messages.name')}} ({{ translate('messages.default') }}) <span class="form-label-secondary text-danger"
                                data-toggle="tooltip" data-placement="right"
                                data-original-title="{{ translate('messages.Required.')}}"> *
                                </span>
                            </label>
                            <input type="text" name="name[]" class="form-control" placeholder="{{translate('messages.new_main_sub_category')}}" maxlength="191"  >
                        </div>
                        <input type="hidden" name="lang[]" value="default">
                        @foreach($language as $lang)
                            <div class="form-group d-none lang_form col-sm-6" id="{{$lang}}-form">
                                <label class="input-label" for="exampleFormControlInput1">{{translate('messages.name')}} ({{strtoupper($lang)}})</label>
                                <input type="text" name="name[]" class="form-control" placeholder="{{translate('messages.new_main_sub_category')}}" maxlength="191"  >
                            </div>
                            <input type="hidden" name="lang[]" value="{{$lang}}">
                        @endforeach
                    @else
                        <div class="form-group col-sm-6">
                            <label class="input-label" for="exampleFormControlInput1">{{translate('messages.name')}}</label>
                            <input type="text" name="name" class="form-control" placeholder="{{translate('messages.new_main_sub_category')}}" value="{{old('name')}}" maxlength="191">
                        </div>
                        <input type="hidden" name="lang[]" value="default">
                    @endif
                        <div class="form-group col-sm-6">
                            <label class="input-label"
                                for="exampleFormControlSelect1">{{translate('messages.main_category')}}
                                <span class="input-label-secondary">*</span></label>
                            <select id="exampleFormControlSelect1" name="parent_id" class="form-control js-select2-custom" required>
                                <option value="" selected disabled>{{translate('Select Main Category')}}</option>
                                @foreach($mainCategories as $category)
                                    <option value="{{$category['id']}}" >{{$category['name']}} ({{Str::limit($category->module->module_name, 15, '...')}})</option>
                                @endforeach
                            </select>
                        </div>
                        <input name="position" value="1" hidden>

                          <div class="form-group col-sm-6">
                                <label class="input-label" for="">
                                    {{ translate('messages.Priority') }}
                                </label>
                                <select required name="priority"
                                    data-original-title="{{ translate('messages.Select_Priority') }}"
                                    class="custom-select">
                                    <option value="0">{{ translate('messages.Normal') }}</option>
                                    <option value="1">{{ translate('messages.Medium') }}</option>
                                    <option value="2">{{ translate('messages.High') }}</option>
                                </select>
                            </div>

                        <div class="col-sm-12">
                            <div class="btn--container justify-content-end">
                                <button type="reset" id="reset_btn" class="btn btn--reset">{{translate('messages.reset')}}</button>
                                <button type="submit" class="btn btn--primary">{{translate('messages.add')}}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div id="subcategory-list-wrapper">
            @include('admin-views.category.partials._list-sub', compact('categories', 'status'))
        </div>
    </div>
        <div id="offcanvas__categoryBtn" class="custom-offcanvas d-flex flex-column justify-content-between">
         <div id="data-view" class="h-100">
        </div>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>



@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin')}}/js/view-pages/sub-category-index.js"></script>
    <script src="{{asset('public/assets/admin')}}/js/view-pages/category-list-ajax.js"></script>
    <script>
        "use strict";

        initCategoryListAjax('subcategory-list-wrapper', {
            areYouSure: '{{ translate('messages.Are you sure?') }}',
            no: '{{ translate('messages.no') }}',
            yes: '{{ translate('messages.Yes') }}',
            offDangerImage: '{{ asset('public/assets/admin/img/off-danger.png') }}'
        });

        // Same fix as the main category page: the All/Active/Inactive tabs (and search/pagination)
        // are plain links that fully reload this page, and the language tab markup hardcodes
        // "Default" as active on every reload, making it look like switching a list tab reset the
        // selected EN/AR language tab. Remember and restore the selected tab across the reload.
        (function() {
            const STORAGE_KEY = 'admin_category_lang_tab_sub';

            document.addEventListener('DOMContentLoaded', function() {
                const langLinks = document.querySelectorAll('.lang_link');
                if (!langLinks.length) return;

                langLinks.forEach(function(link) {
                    link.addEventListener('click', function() {
                        sessionStorage.setItem(STORAGE_KEY, this.id);
                    });
                });

                const savedId = sessionStorage.getItem(STORAGE_KEY);
                if (!savedId || savedId === 'default-link') return;

                const savedLink = document.getElementById(savedId);
                if (!savedLink) return;

                langLinks.forEach(function(link) {
                    link.classList.remove('active');
                });
                savedLink.classList.add('active');

                document.querySelectorAll('.lang_form').forEach(function(form) {
                    form.classList.add('d-none');
                });

                const lang = savedId.split('-link')[0];
                const form = document.getElementById(lang + '-form');
                if (form) {
                    form.classList.remove('d-none');
                }
            });
        })();
    </script>
@endpush
