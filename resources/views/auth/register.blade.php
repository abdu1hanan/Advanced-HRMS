<x-guest-layout title="Employee Registration" subtitle="Create your staff account to access portal">
    <div class="sleek-toast-container"></div>

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="mb-3">
            <label for="name" class="form-label small text-white opacity-75">Full Name</label>
            <input type="text" class="form-control bg-dark border-secondary text-white @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" placeholder="Full Name" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="email" class="form-label small text-white opacity-75">Email</label>
            <input type="email" class="form-control bg-dark border-secondary text-white @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" placeholder="name@company.com" pattern="^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$" title="Please enter a valid email address (e.g. name@company.com)" required>
            <div class="invalid-feedback">@error('email'){{ $message }}@enderror</div>
        </div>
        <div class="mb-3">
            <label for="department_id" class="form-label small text-white opacity-75">Department <span class="text-danger">*</span></label>
            <select class="form-select bg-dark border-secondary text-white @error('department_id') is-invalid @enderror" id="department_id" name="department_id" required>
                <option value="" disabled @selected(!old('department_id'))>Select Department *</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="phone" class="form-label small text-white opacity-75">Phone Number <span class="text-danger">*</span></label>
            <input type="tel" inputmode="tel" maxlength="20" pattern="^[\+]?[0-9\s\-()]{7,20}$" title="Please enter a valid phone number (e.g. +92 300 1234567)" class="form-control bg-dark border-secondary text-white @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" placeholder="e.g. +92 300 1234567" required>
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="emergency_number" class="form-label small text-white opacity-75">Emergency Number <span class="text-danger">*</span></label>
            <input type="tel" inputmode="tel" maxlength="20" pattern="^[\+]?[0-9\s\-()]{7,20}$" title="Please enter a valid phone number (e.g. +92 300 7654321)" class="form-control bg-dark border-secondary text-white @error('emergency_number') is-invalid @enderror" id="emergency_number" name="emergency_number" value="{{ old('emergency_number') }}" placeholder="e.g. +92 300 7654321" required>
            @error('emergency_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="password" class="form-label small text-white opacity-75">Password</label>
            <input type="password" class="form-control bg-dark border-secondary text-white @error('password') is-invalid @enderror" id="password" name="password" required>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="password_confirmation" class="form-label small text-white opacity-75">Confirm Password</label>
            <input type="password" class="form-control bg-dark border-secondary text-white" id="password_confirmation" name="password_confirmation" required>
        </div>
        <div class="mt-4 pt-2 border-top border-secondary border-opacity-25">
            <button type="submit" class="row-btn-blue w-100 justify-content-center" style="height: 40px !important;">Register</button>
        </div>
        <div class="text-center mt-3 pt-2 border-top border-secondary border-opacity-10 small">
            <div class="text-white opacity-50 mb-1" style="font-size: 0.8rem;">
                Already registered? <a href="{{ route('login') }}" class="text-white text-decoration-underline opacity-100 fw-medium">Log in</a>
            </div>
            <div style="font-size: 0.76rem;">
                <a href="{{ route('register.guest') }}" class="text-white opacity-40 text-decoration-none hover-white">Need a guest supervisor account? Register as Guest &rarr;</a>
            </div>
        </div>
    </form>
</x-guest-layout>
