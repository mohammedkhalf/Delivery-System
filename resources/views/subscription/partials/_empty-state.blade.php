<div class="card">
    <div class="card-body text-center py-5">
        <div class="max-w-542 mx-auto py-sm-5 py-4">
            <img class="mb-4" src="{{asset('/public/assets/admin/img/empty-subscription.svg')}}" alt="img">
            <h4 class="mb-3">{{translate('Chose Subscription Plan')}}</h4>
            <p class="mb-4">
                {{ $isServiceModule ? translate('Chose a subscription packages from the list. So that Providers get more options to join the business for the growth and success.') : translate('Chose a subscription packages from the list. So that Stores get more options to join the business for the growth and success.') }}<br>
            </p>
            <button type="button" data-toggle="modal" data-target="#plan-modal" class="btn btn--primary">{{ translate('Chose Subscription Plan') }}</button>
        </div>
    </div>
</div>
