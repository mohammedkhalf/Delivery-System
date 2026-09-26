{{--
  Renders the currently-enabled pro-customer benefits inside the plan modal.
  Required: $benefitItems — array of ['title' => string, 'subtitle' => string] built by the controller.
--}}
@if (!empty($benefitItems))
    <div class="d-flex flex-column gap-20px p-20">
        @foreach ($benefitItems as $item)
            <div class="d-flex gap-2">
                <img width="20" src="{{ asset('public/assets/admin/img/check-circle.svg') }}" alt="">
                <div class="cont">
                    <h3 class="mb-1 fs-16 font-weight-light lh-1">{{ $item['title'] }}</h3>
                    <p class="mb-0 fs-12">{{ $item['subtitle'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
@endif
