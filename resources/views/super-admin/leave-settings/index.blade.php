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

    <div class="spotify-card mb-4" style="position: relative; z-index: 10; overflow: visible !important;">
        <div class="card-header-area px-4 pt-4">
            <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75 d-inline-flex align-items-center" style="letter-spacing: 0.05em;">
                Approval Chain Settings
                <i class="bi bi-info-circle text-white opacity-50 ms-2 cursor-pointer" 
                   style="font-size: 0.85rem;" 
                   data-custom-tooltip="This setting applies to all new leave requests from this point forward." 
                   data-tooltip-placement="top"></i>
            </h6>
            <div class="grid-separator" style="margin-bottom: 1.15rem !important;"></div>
        </div>
        <div class="px-4 pb-4 pt-1">
            <form method="POST" id="approval-settings-form" action="{{ route('super-admin.settings.approval-levels') }}" class="d-flex justify-content-between align-items-center gap-3 flex-wrap flex-md-nowrap">
                @csrf
                <p class="text-white opacity-75 small mb-0">Configure level of approval required for approval chain.</p>
                
                <div class="d-flex align-items-center gap-2">
                    <div class="dropdown level-dropdown position-relative" style="width: 230px; z-index: 1060;">
                        <button class="form-select form-select-sm text-start text-white w-100" 
                                type="button" 
                                id="approvalLevelsDropdown" 
                                data-bs-toggle="dropdown" 
                                aria-expanded="false" 
                                style="border-color: rgba(255, 255, 255, 0.15); background-color: rgba(255, 255, 255, 0.03); border-radius: 4px; height: 31px; font-size: 0.85rem; padding-right: 2rem; color: #ffffff !important;">
                            <span class="text-truncate d-inline-block w-100" id="selected-level-text" style="max-width: 180px;">
                                {{ $currentApprovalLevels }} {{ $currentApprovalLevels == 1 ? 'Level of Manager Approval' : 'Levels of Manager Approval' }}
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark p-0" aria-labelledby="approvalLevelsDropdown" style="max-height: 200px; overflow-y: auto; width: 230px; border: 1px solid rgba(255, 255, 255, 0.15); background-color: #121212; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5); z-index: 1070 !important;">
                            @for ($i = 1; $i <= 10; $i++)
                                <li>
                                    <a class="dropdown-item py-2 text-white @if($currentApprovalLevels == $i) active @endif" 
                                       href="#" 
                                       data-value="{{ $i }}" 
                                       onclick="selectApprovalLevel(event, '{{ $i }}', '{{ $i }} {{ $i == 1 ? 'Level of Manager Approval' : 'Levels of Manager Approval' }}')">
                                       {{ $i }} {{ $i == 1 ? 'Level of Manager Approval' : 'Levels of Manager Approval' }}
                                    </a>
                                </li>
                            @endfor
                        </ul>
                        <input type="hidden" name="approval_levels" id="approval-levels-input" form="approval-settings-form" value="{{ $currentApprovalLevels }}">
                    </div>

                    <button type="submit" class="row-btn-outline-blue" style="height: 31px; line-height: 1; padding: 0 16px;">
                        Save Setting
                    </button>
                </div>
            </form>
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

    @push('scripts')
    <script>
        function selectApprovalLevel(e, val, text) {
            e.preventDefault();
            document.getElementById('approval-levels-input').value = val;
            document.getElementById('selected-level-text').textContent = text;
            const menu = e.target.closest('.dropdown-menu');
            if (menu) {
                menu.querySelectorAll('.dropdown-item').forEach(item => item.classList.remove('active'));
                e.target.classList.add('active');
            }
        }
    </script>
    @endpush
</x-app-layout>
