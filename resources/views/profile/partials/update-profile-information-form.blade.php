<section>
    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" id="profile-update-form">
        @csrf
        @method('patch')

        @if($user->isSuperAdmin())
            <div class="mb-3">
                <label for="name" class="form-label small text-white opacity-75">Name</label>
                <input type="text" class="form-control bg-dark border-secondary text-white @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="email" class="form-label small text-white opacity-75">Email Address</label>
                <input type="email" class="form-control bg-dark border-secondary text-white @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="username">
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <div class="mt-2">
                        <p class="small text-muted mb-1">Your email address is unverified.</p>
                        <button form="send-verification" class="btn btn-link btn-sm p-0 text-decoration-none">Click here to re-send the verification email.</button>
                        @if (session('status') === 'verification-link-sent')
                            <p class="text-success small mt-1">A new verification link has been sent to your email address.</p>
                        @endif
                    </div>
                @endif
            </div>
        @else
            <!-- Rich form for non-super admins with all contact fields -->
            <div class="row g-3">
                <!-- Full Name -->
                <div class="col-md-6">
                    <label for="name" class="form-label small text-white opacity-75">Full Name <span class="text-danger">*</span></label>
                    <div class="input-group gap-2">
                        <span class="input-group-text border-0 rounded" style="background-color: rgba(255,255,255,0.02); color: rgba(255,255,255,0.3); min-width: 46px; justify-content: center;"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control border-0 rounded text-white @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name" style="background-color: #121212 !important; border: 1px solid rgba(255, 255, 255, 0.12) !important; color: #ffffff;">
                    </div>
                    @error('name')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Login Email -->
                <div class="col-md-6">
                    <label for="email" class="form-label small text-white opacity-75">Login Email <span class="text-danger">*</span></label>
                    <div class="input-group gap-2">
                        <span class="input-group-text border-0 rounded" style="background-color: rgba(255,255,255,0.02); color: rgba(255,255,255,0.3); min-width: 46px; justify-content: center;"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control border-0 rounded text-white @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}" placeholder="name@company.com" pattern="^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$" title="Please enter a valid email address (e.g. name@company.com)" required autocomplete="username" style="background-color: #121212 !important; border: 1px solid rgba(255, 255, 255, 0.12) !important; color: #ffffff;">
                    </div>
                    @error('email')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                @if(!$user->isGuest())
                    <!-- Professional Communication Email -->
                    <div class="col-md-6">
                        <label for="salary_slip_email" class="form-label small text-white opacity-75 d-flex align-items-center">
                            Professional Email
                            <i class="bi bi-info-circle ms-1 text-primary opacity-75 cursor-pointer" data-custom-tooltip="Email address where official notices, salary slips, and professional correspondence will be sent. If left empty, communications will be delivered to your main login email." data-tooltip-placement="top"></i>
                        </label>
                        <div class="input-group gap-2">
                            <span class="input-group-text border-0 rounded" style="background-color: rgba(255,255,255,0.02); color: rgba(255,255,255,0.3); min-width: 46px; justify-content: center;"><i class="bi bi-briefcase"></i></span>
                            <input type="email" class="form-control border-0 rounded text-white @error('salary_slip_email') is-invalid @enderror" id="salary_slip_email" name="salary_slip_email" value="{{ old('salary_slip_email', $user->salary_slip_email) }}" placeholder="Fallback: {{ $user->email }}" pattern="^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$" title="Please enter a valid email address (e.g. name@company.com)" style="background-color: #121212 !important; border: 1px solid rgba(255, 255, 255, 0.12) !important; color: #ffffff;">
                        </div>
                        @error('salary_slip_email')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                @endif

                <!-- Phone Number -->
                <div class="col-md-6">
                    <label for="phone" class="form-label small text-white opacity-75">Phone Number <span class="text-danger">*</span></label>
                    <div class="input-group gap-2">
                        <span class="input-group-text border-0 rounded" style="background-color: rgba(255,255,255,0.02); color: rgba(255,255,255,0.3); min-width: 46px; justify-content: center;"><i class="bi bi-telephone"></i></span>
                        <input type="tel" id="phone" name="phone" inputmode="tel" maxlength="20" pattern="^[\+]?[0-9\s\-()]{7,20}$" title="Please enter a valid phone number (e.g. +92 300 1234567)" class="form-control border-0 rounded text-white @error('phone') is-invalid @enderror" value="{{ old('phone', $user->phone) }}" placeholder="e.g. +92 300 1234567" style="background-color: #121212 !important; border: 1px solid rgba(255, 255, 255, 0.12) !important; color: #ffffff;" required>
                    </div>
                    @error('phone')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Emergency Number -->
                <div class="col-md-6">
                    <label for="emergency_number" class="form-label small text-white opacity-75">
                        Emergency Number 
                        @if(!$user->isGuest())
                            <span class="text-danger">*</span>
                        @else
                            <span class="text-white opacity-50 small">(Optional)</span>
                        @endif
                    </label>
                    <div class="input-group gap-2">
                        <span class="input-group-text border-0 rounded" style="background-color: rgba(255,255,255,0.02); color: rgba(255,255,255,0.3); min-width: 46px; justify-content: center;"><i class="bi bi-telephone-plus"></i></span>
                        <input type="tel" id="emergency_number" name="emergency_number" inputmode="tel" maxlength="20" pattern="^[\+]?[0-9\s\-()]{7,20}$" title="Please enter a valid phone number (e.g. +92 300 7654321)" class="form-control border-0 rounded text-white @error('emergency_number') is-invalid @enderror" value="{{ old('emergency_number', $user->emergency_number) }}" placeholder="e.g. +92 300 7654321" style="background-color: #121212 !important; border: 1px solid rgba(255, 255, 255, 0.12) !important; color: #ffffff;" {{ $user->isGuest() ? '' : 'required' }}>
                    </div>
                    @error('emergency_number')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                @if(!$user->isGuest())
                    <!-- Department Dropdown -->
                    <div class="col-md-6">
                        <label for="department_id" class="form-label small text-white opacity-75">Department</label>
                        @php
                            $currentDeptId = old('department_id', $user->department_id);
                            $currentDept = isset($departments) ? $departments->firstWhere('id', $currentDeptId) : null;
                            $currentDeptLabel = $currentDept ? $currentDept->name : 'Select Department';
                        @endphp
                        <div class="dropdown department-dropdown w-100" style="position: relative; z-index: 1060;">
                            <button class="form-select text-start text-white w-100" 
                                    type="button" 
                                    id="deptDropdown" 
                                    data-bs-toggle="dropdown" 
                                    aria-expanded="false"
                                    style="border: 1px solid rgba(255, 255, 255, 0.12) !important; background-color: #121212 !important; border-radius: 4px; height: 37px; font-size: 0.85rem; padding-right: 2rem; color: #ffffff !important;">
                                <span id="deptDropdownLabel" class="text-truncate d-inline-block w-100" style="max-width: calc(100% - 15px);">{{ $currentDeptLabel }}</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-dark p-0 w-100" aria-labelledby="deptDropdown" style="max-height: 250px; overflow-y: auto; z-index: 99999 !important;">
                                <li>
                                    <a class="dropdown-item py-2 text-white @if(!$currentDeptId) active @endif" 
                                       href="#" 
                                       data-value=""
                                       onclick="selectProfileDept(event, '', 'None')">None</a>
                                </li>
                                @if(isset($departments))
                                    @foreach($departments as $dept)
                                        <li>
                                            <a class="dropdown-item py-2 text-white @if($currentDeptId == $dept->id) active @endif" 
                                               href="#" 
                                               data-value="{{ $dept->id }}"
                                               onclick="selectProfileDept(event, '{{ $dept->id }}', '{{ addslashes($dept->name) }}')">{{ $dept->name }}</a>
                                        </li>
                                    @endforeach
                                @endif
                            </ul>
                            <input type="hidden" name="department_id" id="department_id" value="{{ $currentDeptId }}">
                        </div>
                        @error('department_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                @endif

                <!-- Address -->
                <div class="col-12">
                    <label for="address" class="form-label small text-white opacity-75">Home Address</label>
                    <div class="input-group gap-2">
                        <span class="input-group-text border-0 rounded" style="background-color: rgba(255,255,255,0.02); color: rgba(255,255,255,0.3); min-width: 46px; justify-content: center;"><i class="bi bi-geo-alt"></i></span>
                        <input type="text" id="address" name="address" class="form-control border-0 rounded text-white @error('address') is-invalid @enderror" value="{{ old('address', $user->address) }}" placeholder="e.g. Street Address, City, Country" style="background-color: #121212 !important; border: 1px solid rgba(255, 255, 255, 0.12) !important; color: #ffffff;">
                    </div>
                    @error('address')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-3">
                    <p class="small text-muted mb-1">Your email address is unverified.</p>
                    <button form="send-verification" class="btn btn-link btn-sm p-0 text-decoration-none">Click here to re-send the verification email.</button>
                    @if (session('status') === 'verification-link-sent')
                        <p class="text-success small mt-1">A new verification link has been sent to your email address.</p>
                    @endif
                </div>
            @endif
        @endif

        <div class="d-flex align-items-center gap-3 mt-4 pt-3 border-top border-secondary border-opacity-25">
            <button type="submit" class="row-btn-blue">Save Changes</button>
        </div>
    </form>
</section>
