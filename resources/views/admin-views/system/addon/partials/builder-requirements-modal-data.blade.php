<div class="modal-header border-0 pb-0 d-flex justify-content-end">
    <button
        type="button"
        class="btn-close border-0"
        data-dismiss="modal"
        aria-label="Close"
    ><i class="tio-clear"></i></button>
</div>
<div class="modal-body px-4 px-sm-5">
    <div class="mb-3 text-center">
        <img width="64" src="{{ asset('public/assets/admin/img/warning.png') }}" alt=""
             onerror="this.style.display='none'">
    </div>
    <h3 class="text-center mb-2">
        {{ translate('Server_requirements_not_met') }}
    </h3>
    <p class="text-center text-muted mb-4">
        {{ translate('Fix the items below before activating') }} {{ $addon_name ?? 'Builder' }}.
    </p>

    <ul class="list-group mb-4">
        @foreach($issues as $issue)
            <li class="list-group-item border-0 px-0">
                <div class="d-flex align-items-start gap-3">
                    <span class="text-danger flex-shrink-0" style="font-size:18px;line-height:1">●</span>
                    <div class="flex-grow-1">
                        <div class="font-weight-bold text-dark">{{ $issue['message'] }}</div>
                        @if(!empty($issue['fix']))
                            <div class="text-muted fs-12 mt-1">{{ $issue['fix'] }}</div>
                        @endif
                    </div>
                </div>
            </li>
        @endforeach
    </ul>

    <div class="btn--container justify-content-center gap-3 mb-3">
        <button type="button" class="fs-16 btn btn--primary flex-grow-1" data-dismiss="modal">
            {{ translate('Got_it') }}
        </button>
    </div>
</div>
