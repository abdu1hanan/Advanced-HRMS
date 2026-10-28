<x-app-layout>
    <x-slot name="header">Profile Settings</x-slot>

    <style>
        .profile-field-row {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
            padding-top: 9px;
            padding-bottom: 9px;
        }
        .profile-field-row:last-child {
            border-bottom: none !important;
        }
        .department-dropdown {
            position: relative !important;
        }
        .department-dropdown .dropdown-menu {
            max-height: 220px !important;
            overflow-y: auto !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            background-color: #121212 !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.8) !important;
            z-index: 99999 !important;
        }
        .department-dropdown .dropdown-item {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.8) !important;
            transition: all 0.2s ease;
        }
        .department-dropdown .dropdown-item:hover,
        .department-dropdown .dropdown-item.active {
            background-color: rgba(255, 255, 255, 0.1) !important;
            color: #ffffff !important;
        }
    </style>

    @if($user->isSuperAdmin())
        <!-- Simple Layout for System Administrator -->
        <div class="row g-4 align-items-stretch" style="margin-bottom: 2rem;">
            <div class="col-md-6">
                <div class="spotify-card h-100 mb-0">
                    <div class="card-header-area px-4 pt-4">
                        <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;">Profile Information</h6>
                        <div class="grid-separator"></div>
                    </div>
                    <div class="px-4 pb-4 pt-2 h-100">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="spotify-card h-100 mb-0">
                    <div class="card-header-area px-4 pt-4">
                        <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;">Update Password</h6>
                        <div class="grid-separator"></div>
                    </div>
                    <div class="px-4 pb-4 pt-2 h-100">
                        @include('profile.partials.update-password-form')
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- Rich Layout for Other Users -->
        <!-- Top Section: Left Profile Summary Card + Right (Personal Details Card + Update Password Card) -->
        <div class="row g-4 align-items-stretch mb-4">
            <!-- Left Side: Live Profile Summary Card (Matches combined height of right column) -->
            <div class="col-lg-4 d-flex flex-column">
                <div class="spotify-card p-4 flex-grow-1 d-flex flex-column justify-content-between">
                    <div class="d-flex flex-column justify-content-between flex-grow-1">
                        <!-- Avatar Circle -->
                        <div class="d-flex flex-column align-items-center text-center mb-3">
                            <div class="d-flex justify-content-center mb-2">
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" 
                                     style="width: 90px; height: 90px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); font-size: 2rem; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.25);">
                                    {{ collect(explode(' ', $user->name))->map(fn($n) => mb_substr($n, 0, 1))->take(2)->join('') }}
                                </div>
                            </div>

                            <h4 id="card-header-name" class="fw-bold text-white mb-1">{{ $user->name }}</h4>
                            <span id="card-header-role-badge" class="badge px-3 py-1.5 rounded-pill small" 
                                  style="background-color: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.12); color: #ffffff;">
                                {{ $user->role->label }}
                            </span>
                        </div>

                        <!-- Complete list of fields -->
                        <div class="border-top border-secondary pt-2 text-start d-flex flex-column justify-content-between flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center profile-field-row">
                                <span class="text-white opacity-50 small">Full Name</span>
                                <span id="card-name" class="text-white fw-semibold small text-end">{{ $user->name }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center profile-field-row">
                                <span class="text-white opacity-50 small">Login Email</span>
                                <span id="card-email" class="text-white fw-semibold small text-break text-end" style="max-width: 65%;">{{ $user->email }}</span>
                            </div>
                            @if(!$user->isGuest())
                                <div class="d-flex justify-content-between align-items-center profile-field-row">
                                    <span class="text-white opacity-50 small">Professional Email</span>
                                    <span id="card-salary-slip-email" class="text-white fw-semibold small text-break text-end" style="max-width: 65%;">{{ $user->salary_slip_email ?? '—' }}</span>
                                </div>
                            @endif
                            <div class="d-flex justify-content-between align-items-center profile-field-row">
                                <span class="text-white opacity-50 small">Phone Number</span>
                                <span id="card-phone" class="text-white fw-semibold small text-end">{{ $user->phone ?? '—' }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center profile-field-row">
                                <span class="text-white opacity-50 small">Emergency Number</span>
                                <span id="card-emergency-number" class="text-white fw-semibold small text-end">{{ $user->emergency_number ?? '—' }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center profile-field-row">
                                <span class="text-white opacity-50 small">Address</span>
                                <span id="card-address" class="text-white fw-semibold small text-break text-end" style="max-width: 65%;">{{ $user->address ?? '—' }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center profile-field-row">
                                <span class="text-white opacity-50 small">Department</span>
                                <span id="card-department" class="text-white fw-semibold small text-end">
                                    {{ $user->department?->name ?? ($user->isGuest() ? 'External' : 'None') }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center profile-field-row">
                                <span class="text-white opacity-50 small">Designation</span>
                                <span id="card-designation" class="text-white fw-semibold small text-end">{{ $user->designation?->name ?? ($user->isGuest() ? 'Guest Supervisor' : '—') }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center profile-field-row">
                                <span class="text-white opacity-50 small">Joined On</span>
                                <span id="card-join-date" class="text-white fw-semibold small text-end">
                                    {{ $user->join_date ? \Carbon\Carbon::parse($user->join_date)->format('M d, Y') : ($user->created_at ? $user->created_at->format('M d, Y') : '—') }}
                                </span>
                            </div>
                            @if($user->manager)
                                <div class="d-flex justify-content-between align-items-center profile-field-row">
                                    <span class="text-white opacity-50 small">Line Manager</span>
                                    <span id="card-manager" class="text-white fw-semibold small text-end">{{ $user->manager->name }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Edit Form Cards (Personal Details + Update Password) -->
            <div class="col-lg-8 d-flex flex-column gap-4" style="overflow: visible !important;">
                <!-- Card 1: Personal & Contact Details -->
                <div class="spotify-card p-4 position-relative" style="z-index: 20 !important; overflow: visible !important;">
                    <div class="card-header-area pb-3 mb-3 border-bottom border-secondary">
                        <h5 class="fw-bold text-white mb-0" style="font-size: 1.05rem;">
                            <i class="bi bi-person me-2 text-primary"></i> Personal & Contact Details
                        </h5>
                    </div>
                    <div>
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>

                <!-- Card 2: Account Security / Update Password -->
                <div class="spotify-card p-4 position-relative" style="z-index: 10 !important;">
                    <div class="card-header-area pb-3 mb-3 border-bottom border-secondary">
                        <h5 class="fw-bold text-white mb-0" style="font-size: 1.05rem;">
                            <i class="bi bi-shield-lock me-2 text-primary"></i> Update Password
                        </h5>
                    </div>
                    <div>
                        @include('profile.partials.update-password-form')
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Section: Delete Account (Takes 100% full width across bottom) -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="spotify-card border border-danger border-opacity-25 p-4">
                    <div class="card-header-area pb-3 mb-3 border-bottom border-danger border-opacity-25">
                        <h5 class="fw-bold text-danger mb-0" style="font-size: 1.05rem;">
                            <i class="bi bi-exclamation-triangle me-2 text-danger"></i> Delete Account
                        </h5>
                    </div>
                    <div>
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>
            </div>
        </div>
    @endif

    @push('scripts')
        <script>
            function selectProfileDept(event, id, name) {
                event.preventDefault();
                $('#department_id').val(id);
                $('#deptDropdownLabel').text(name);
                $('.department-dropdown .dropdown-item').removeClass('active');
                $(event.target).addClass('active');
                $('#card-department').text(name);
            }

            $(document).ready(function() {
                // Trigger top snackbar/toast on successful updates
                @if (session('status') === 'profile-updated')
                    if (window.showToast) {
                        window.showToast('Profile updated successfully.', 'success');
                    }
                @endif

                @if (session('status') === 'password-updated')
                    if (window.showToast) {
                        window.showToast('Password updated successfully.', 'success');
                    }
                @endif

                // Initialize Bootstrap tooltips
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });

                // Live updates for card on left from inputs on right
                $('input[name="name"]').on('input change', function() {
                    const val = $(this).val();
                    $('#card-name').text(val ? val : '—');
                    $('#card-header-name').text(val ? val : '—');
                });

                $('input[name="email"]').on('input change', function() {
                    const val = $(this).val();
                    $('#card-email').text(val ? val : '—');
                    if (!$('input[name="salary_slip_email"]').val() || !$('input[name="salary_slip_email"]').val().trim()) {
                        $('input[name="salary_slip_email"]').attr('placeholder', 'Fallback: ' + (val ? val : ''));
                    }
                });

                $('input[name="salary_slip_email"]').on('input change', function() {
                    const val = $(this).val();
                    $('#card-salary-slip-email').text(val ? val : '—');
                });

                $('input[name="phone"]').on('input change', function() {
                    const val = $(this).val();
                    $('#card-phone').text(val ? val : '—');
                });

                $('input[name="emergency_number"]').on('input change', function() {
                    const val = $(this).val();
                    $('#card-emergency-number').text(val ? val : '—');
                });

                $('input[name="address"]').on('input change', function() {
                    const val = $(this).val();
                    $('#card-address').text(val ? val : '—');
                });
            });
        </script>
    @endpush
</x-app-layout>
