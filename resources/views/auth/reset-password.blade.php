<x-guest-layout title="Reset Password" subtitle="Choose a strong new password for your account">
    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <!-- Email Address -->
        <div class="mb-3">
            <label for="email" class="form-label small text-white opacity-75">Work Email</label>
            <input type="email" 
                   class="form-control bg-dark border-secondary text-white @error('email') is-invalid @enderror" 
                   id="email" 
                   name="email" 
                   value="{{ old('email', $request->email) }}" 
                   placeholder="name@company.com"
                   pattern="^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$"
                   title="Please enter a valid email address (e.g. name@company.com)"
                   required 
                   autofocus 
                   autocomplete="username">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Password -->
        <div class="mb-3">
            <label for="password" class="form-label small text-white opacity-75">New Password</label>
            <input type="password" 
                   class="form-control bg-dark border-secondary text-white @error('password') is-invalid @enderror" 
                   id="password" 
                   name="password" 
                   placeholder="At least 8 characters"
                   required 
                   autocomplete="new-password">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Confirm Password -->
        <div class="mb-3">
            <label for="password_confirmation" class="form-label small text-white opacity-75">Confirm New Password</label>
            <input type="password" 
                   class="form-control bg-dark border-secondary text-white @error('password_confirmation') is-invalid @enderror" 
                   id="password_confirmation" 
                   name="password_confirmation" 
                   placeholder="Repeat new password"
                   required 
                   autocomplete="new-password">
            @error('password_confirmation')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mt-4 pt-2 border-top border-secondary border-opacity-25">
            <button type="submit" class="row-btn-blue w-100 justify-content-center" style="height: 40px !important;">
                <i class="bi bi-shield-lock me-2"></i> Update Password & Sign In
            </button>
        </div>

        <div class="text-center mt-3 pt-2 border-top border-secondary border-opacity-10 small">
            <a href="{{ route('login') }}" class="text-white opacity-50 text-decoration-none hover-white" style="font-size: 0.8rem;">
                <i class="bi bi-arrow-left me-1"></i> Return to login
            </a>
        </div>
    </form>
</x-guest-layout>
