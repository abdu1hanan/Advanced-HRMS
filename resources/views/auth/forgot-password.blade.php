<x-guest-layout title="Forgot Password" subtitle="Enter your registered work email to receive a password reset link">
    @if (session('status'))
        <div class="alert alert-success d-flex align-items-center mb-4 py-2 px-3 small border-0" style="background-color: rgba(16, 185, 129, 0.15); color: #34d399; border-radius: 8px;">
            <i class="bi bi-check-circle-fill me-2"></i>
            <div>{{ session('status') }}</div>
        </div>
    @endif

    <div class="mb-4 text-white opacity-60 small" style="font-size: 0.82rem; line-height: 1.5;">
        Forgot your password? No problem. Enter your registered email address and we'll send you a password reset link to choose a new one.
    </div>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div class="mb-3">
            <label for="email" class="form-label small text-white opacity-75">Work Email</label>
            <input type="email" 
                   class="form-control bg-dark border-secondary text-white @error('email') is-invalid @enderror" 
                   id="email" 
                   name="email" 
                   value="{{ old('email') }}" 
                   placeholder="name@company.com"
                   pattern="^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$"
                   title="Please enter a valid email address (e.g. name@company.com)"
                   required 
                   autofocus>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mt-4 pt-2 border-top border-secondary border-opacity-25">
            <button type="submit" class="row-btn-blue w-100 justify-content-center" style="height: 40px !important;">
                <i class="bi bi-envelope-paper me-2"></i> Send Password Reset Link
            </button>
        </div>

        <div class="text-center mt-3 pt-2 border-top border-secondary border-opacity-10 small">
            <a href="{{ route('login') }}" class="text-white opacity-50 text-decoration-none hover-white" style="font-size: 0.8rem;">
                <i class="bi bi-arrow-left me-1"></i> Back to login
            </a>
        </div>
    </form>
</x-guest-layout>
