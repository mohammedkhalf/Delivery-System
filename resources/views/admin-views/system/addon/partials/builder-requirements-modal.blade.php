<div class="modal fade" id="builderRequirementsModal" tabindex="-1" role="dialog"
     aria-labelledby="builderRequirementsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" id="builderRequirementsData">
            {{-- Content injected via JS (publish-addon ajax response) OR rendered
                 inline below when an activation()-redirect-back flash carries
                 issues from a failed license activation. --}}
            @if(session('builder_requirements_issues'))
                @include('admin-views.system.addon.partials.builder-requirements-modal-data', [
                    'issues'     => session('builder_requirements_issues'),
                    'addon_name' => session('builder_requirements_addon', 'Builder'),
                ])
            @endif
        </div>
    </div>
</div>
