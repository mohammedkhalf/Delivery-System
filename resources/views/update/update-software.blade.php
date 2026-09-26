@extends('layouts.blank')

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="card mt-3">
                <div class="card-body">
                    <div class="mar-ver pad-btm text-center mb-4">
                        <h1 class="h3">
                            Software Update
                        </h1>
                    </div>


                    <form method="POST" action="{{route('update-system')}}">
                        @csrf
                        <div class="bg-light p-4 rounded mb-4">
                            <div class="px-xl-2 pb-sm-3">
                                <div class="row gy-4">
                                    <div class="col-md-6">
                                        <div class="from-group">
                                            <label for="username" class="d-flex align-items-center gap-2 mb-2">
                                                <span class="fw-medium">Username</span>
                                                <span class="cursor-pointer" data-bs-toggle="tooltip"
                                                      data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                                      data-bs-html="true"
                                                      data-bs-title="The username of your codecanyon account">
                                                      <img src="{{asset('public/assets/installation')}}/assets/img/svg-icons/info2.svg" class="svg" alt="">
                                                </span>
                                            </label>
                                            <input type="text" id="username" class="form-control" name="username"
                                                   value="{{ $buyerUsername }}"
                                                   placeholder="Ex: John Doe" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="from-group">
                                            <label for="purchase_key" class="mb-2">Purchase Code</label>
                                            <input type="text" id="purchase_key" class="form-control" name="purchase_key"
                                                   value="{{ $purchaseCode }}"
                                                   placeholder="Ex: 19xxxxxx-ca5c-49c2-83f6-696a738b0000" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-4 mt-1">
                                    @if ($phpVersion < 8.3)
                                        <div class="col-md-6">
                                            <div class="d-flex gap-3 align-items-center">
                                                <img
                                                    src="{{asset('public/assets/installation')}}/assets/img/svg-icons/php-version.svg"
                                                    alt="">
                                                <div
                                                    class="d-flex align-items-center gap-2 text-danger justify-content-between flex-grow-1">
                                                    PHP Version 8.3 +

                                                    <span class="cursor-pointer" data-bs-toggle="tooltip"
                                                          data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                                          data-bs-html="true" data-bs-delay='{"hide":1000}'
                                                          data-bs-title="Your php version in server is lower than 8.3 version
                                                               <a href='https://support.cpanel.net/hc/en-us/articles/360052624713-How-to-change-the-PHP-version-for-a-domain-in-cPanel-or-WHM'
                                                               class='d-block' target='_blank'>See how to update</a> ">
                                                        <img
                                                            src="{{asset('public/assets/installation')}}/assets/img/svg-icons/info.svg"
                                                            class="svg text-danger" alt="">
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    @foreach($permission as $key => $item)
                                        @if (!array_key_exists($key, $fileChecks) && !$item)
                                            <div class="col-md-6">
                                                <div class="d-flex gap-3 align-items-center">
                                                    <img src="{{ asset('public/assets/installation') }}/assets/img/svg-icons/curl-enabled.svg" alt="">
                                                    <div class="d-flex align-items-center gap-2 text-danger justify-content-between flex-grow-1">
                                                        {{ translate($key) . ' ' . translate('Enabled') }}

                                                        <span class="cursor-pointer" data-bs-toggle="tooltip"
                                                              data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                                              data-bs-html="true" data-bs-delay='{"hide":1000}'
                                                              data-bs-title="{{ translate($key) }} extension is not enabled in your server. To enable go to PHP version > extensions and select {{ translate($key) }}.">
                                                            <img src="{{ asset('public/assets/installation') }}/assets/img/svg-icons/info.svg"
                                                                 class="svg text-danger" alt="">
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach

                                    @foreach($fileChecks as $key => $file)
                                        @if (!$permission[$key])
                                            <div class="col-md-6">
                                                <div class="d-flex gap-3 align-items-center">
                                                    <img
                                                        src="{{asset('public/assets/installation')}}/assets/img/svg-icons/route-service.svg"
                                                        alt="">
                                                    <div
                                                        class="d-flex align-items-center gap-2 text-danger justify-content-between flex-grow-1">
                                                        {{ $file['label'] }}

                                                        <span class="cursor-pointer" data-bs-toggle="tooltip"
                                                              data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                                              data-bs-html="true" data-bs-delay='{"hide":1000}'
                                                              data-bs-title="Write permission is required for: <br> {{ $file['path'] }}">
                                                            <img
                                                                src="{{asset('public/assets/installation')}}/assets/img/svg-icons/info.svg"
                                                                class="svg text-danger" alt="">
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="text-center">
                            <button type="submit" class="btn btn-dark px-sm-5" {{ $phpVersion >= 8.3 && !in_array(false, $permission, true) ? '' : 'disabled' }}>Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
