@extends('layouts.admin.app')

@section('title', translate('messages.subscription'))

@section('content')


<div class="content container-fluid">
   <div class="card h-calc-vh py-5 w-100">
        <div class="card-body d-center">
            <div class="text-center max-w-490 mx-auto">
                <div class="mx-auto mb-5 pb-xxl-2 max-w-320">
                    <img src="{{asset('public/assets/admin/img/wellcome-maintainance.png')}}" alt="icon" class="w-100">
                </div>
                <h3 class="mb-3 fs-20">
                    {{ translate('We re Cooking Up Something Special!') }}
                </h3>
                <p class="mb-0 gray-dark fs-14">
                    {{ translate('Our system is currently undergoing maintenance to bring you an even tastier experience. Hang tight while we make the dishes.') }} 
                </p>
                <div class="border-bottom-dashed-cus my-4 py-1"></div>
                <p class="mb-2 gray-dark fs-14">
                    {{ translate('Any query? Feel free to call or mail Us') }} 
                </p>
                <a href="#0" class="d-block mb-0">
                    <span class="d-block mb-1 fs-16 text--primary text-underline">
                       +880 12345 67890
                    </span>
                </a>
                <a href="#0" class="d-block mb-1">
                    <span class="d-block mb-1 fs-16 text--primary text-underline">
                      example@email.com
                    </span>
                </a>
            </div>
        </div>
    </div>
</div>


@endsection

@push('script_2')

@endpush