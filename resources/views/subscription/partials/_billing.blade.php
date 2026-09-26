<div class="card mb-20">
    <div class="card-header border-0 align-items-center">
        <h4 class="card-title align-items-center gap-2">
            <span class="card-header-icon">
                <img src="{{asset('public/assets/admin/img/billing.png')}}" alt="">
            </span>
            <span class="text-title">{{ translate('Billing') }}</span>
        </h4>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-sm-6 col-lg-4">
                <a class="__card-2 __bg-1 flex-row align-items-center gap-4" href="#">
                    <img src="{{asset('public/assets/admin/img/expiring.png')}}" alt="report/new" class="w-60px">
                    <div class="w-0 flex-grow-1 py-md-3">
                        <span class="text-body">{{ translate('Expire Date') }}</span>
                        <h4 class="title m-0">{{  \App\CentralLogics\Helpers::date_format($store?->store_sub_update_application?->expiry_date_parsed) }}</h4>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-4">
                <a class="__card-2 __bg-8 flex-row align-items-center gap-4" href="#">
                    <img src="{{asset('public/assets/admin/img/total-bill.png')}}" alt="report/new" class="w-60px">
                    <div class="w-0 flex-grow-1 py-md-3">
                        <span class="text-body">{{ translate('Total_Bill') }}</span>
                        <h4 class="title m-0">{{  \App\CentralLogics\Helpers::format_currency($store?->store_sub_update_application?->package?->price * ($store?->store_sub_update_application?->total_package_renewed + 1) ) }}</h4>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-4">
                <a class="__card-2 __bg-4 flex-row align-items-center gap-4" href="#">
                    <img src="{{asset('public/assets/admin/img/number.png')}}" alt="report/new" class="w-60px">
                    <div class="w-0 flex-grow-1 py-md-3">
                        <span class="text-body">{{ translate('Number of Uses') }}</span>
                        <h4 class="title m-0">{{ $store?->store_sub_update_application?->total_package_renewed + 1 }}</h4>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
