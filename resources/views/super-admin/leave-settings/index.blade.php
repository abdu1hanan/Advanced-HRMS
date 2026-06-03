<x-app-layout>
    <x-slot name="header">Leave Settings</x-slot>
    <div class="spotify-card mb-4">
        <div class="card-header-area px-4 pt-4">
            <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;">Global Defaults ({{ $year }})</h6>
            <div class="grid-separator"></div>
        </div>
        <div class="px-4 pb-4">
            <div class="row g-3">
                @foreach($leaveTypes as $type)
                    <div class="col-md-3">
                        <div class="p-3 bg-dark border border-secondary rounded">
                            <div class="text-white fw-semibold mb-1">{{ $type->name }}</div>
                            <div class="text-white opacity-50 small">{{ $type->settings->first()?->default_entitled ?? $type->max_days_per_year }} days default</div>
                        </div>
                    </div>
                @endforeach
            </div>
            
            <div class="mt-4 pt-3 border-top border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                <p class="small text-white opacity-50 mb-0">Edit defaults per type on the Leave Types page.</p>
                <button type="button" class="row-btn-outline-warning" data-bs-toggle="modal" data-bs-target="#applyDefaultsModal">
                    <i class="bi bi-arrow-repeat me-1"></i> Apply Defaults to All Employees
                </button>
            </div>
        </div>
    </div>
    
    <div class="spotify-card mb-4">
        <div class="card-header-area px-4 pt-4">
            <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;">Per-Employee Overrides</h6>
            <div class="grid-separator"></div>
        </div>
        <div class="px-4 pb-4 d-flex justify-content-between align-items-center">
            <p class="text-white opacity-75 mb-0">Set custom leave entitlements for individual employees.</p>
            <a href="{{ route('super-admin.leave-settings.overrides') }}" class="row-btn-outline-blue text-decoration-none">Manage Overrides</a>
        </div>
    </div>

    @push('modals')
    <!-- Reconfirmation Modal Popup -->
    <div class="modal fade" id="applyDefaultsModal" tabindex="-1" aria-labelledby="applyDefaultsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-white fs-6" id="applyDefaultsModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i> Reconfirm Action
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4 text-center">
                    <div class="mb-3 text-warning">
                        <i class="bi bi-shield-slash-fill" style="font-size: 3rem;"></i>
                    </div>
                    <h4 class="fw-bold mb-2 text-white">Are you absolutely sure?</h4>
                    <p class="text-white opacity-50 mb-0 small">
                        Applying defaults to all will <strong>permanently delete all manual overrides</strong> for the year <strong>{{ $year }}</strong>. This action is final and <strong>cannot be restored</strong>.
                    </p>
                </div>
                <div class="modal-footer d-flex justify-content-end gap-2">
                    <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 145px;">Cancel</button>
                    <form method="POST" action="{{ route('super-admin.leave-settings.apply-all') }}" class="m-0">
                        @csrf
                        <input type="hidden" name="year" value="{{ $year }}">
                        <button type="submit" class="row-btn-outline-warning" style="width: 165px;">Yes, Apply Defaults</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endpush
</x-app-layout>
