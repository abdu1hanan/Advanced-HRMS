<x-app-layout>
    <x-slot name="header">Leave Settings</x-slot>
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header">Global Defaults ({{ $year }})</div>
                <ul class="list-group list-group-flush">
                    @foreach($leaveTypes as $type)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $type->name }}</span>
                            <span class="badge bg-primary">{{ $type->settings->first()?->default_entitled ?? $type->max_days_per_year }} days</span>
                        </li>
                    @endforeach
                </ul>
                <div class="card-body">
                    <p class="small text-muted">Edit defaults per type on the Leave Types page.</p>
                    <!-- Trigger Button for Popup Modal -->
                    <button type="button" class="btn btn-warning shadow-sm" data-bs-toggle="modal" data-bs-target="#applyDefaultsModal">
                        <i class="bi bi-arrow-repeat me-1"></i> Apply Defaults to All Employees
                    </button>

                    <!-- Reconfirmation Modal Popup -->
                    <div class="modal fade" id="applyDefaultsModal" tabindex="-1" aria-labelledby="applyDefaultsModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow-lg">
                                <div class="modal-header bg-warning text-dark border-0 py-3">
                                    <h5 class="modal-title d-flex align-items-center fw-bold" id="applyDefaultsModalLabel">
                                        <i class="bi bi-exclamation-triangle-fill me-2 fs-4"></i> Reconfirm Action
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-4 text-center">
                                    <div class="mb-3 text-warning">
                                        <i class="bi bi-shield-slash-fill" style="font-size: 3rem;"></i>
                                    </div>
                                    <h4 class="fw-bold mb-2 text-dark">Are you absolutely sure?</h4>
                                    <p class="text-muted mb-0">
                                        Applying defaults to all will <strong>permanently delete all manual overrides</strong> for the year <strong>{{ $year }}</strong>. This action is final and <strong>cannot be restored</strong>.
                                    </p>
                                </div>
                                <div class="modal-footer bg-light border-0 py-3 d-flex justify-content-between">
                                    <button type="button" class="btn btn-outline-secondary px-4 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                                    <form method="POST" action="{{ route('super-admin.leave-settings.apply-all') }}" class="m-0">
                                        @csrf
                                        <input type="hidden" name="year" value="{{ $year }}">
                                        <button type="submit" class="btn btn-warning px-4 fw-semibold shadow-sm">Yes, Apply Defaults</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5>Per-Employee Overrides</h5>
                    <p class="text-muted">Set custom leave entitlements for individual employees.</p>
                    <a href="{{ route('super-admin.leave-settings.overrides') }}" class="btn btn-outline-primary">Manage Overrides</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
