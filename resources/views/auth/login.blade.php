<x-guest-layout title="Sign In" subtitle="Welcome back! Enter your details to continue">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label small text-white opacity-75">Email</label>
            <input type="email" class="form-control bg-dark border-secondary text-white @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" placeholder="name@company.com" pattern="^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$" title="Please enter a valid email address (e.g. name@company.com)" required autofocus>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="password" class="form-label small text-white opacity-75">Password</label>
            <input type="password" class="form-control bg-dark border-secondary text-white @error('password') is-invalid @enderror" id="password" name="password" required>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <div class="form-check mb-0">
                <input type="checkbox" class="form-check-input bg-dark border-secondary" id="remember_me" name="remember">
                <label class="form-check-label small text-white opacity-75" for="remember_me">Remember me</label>
            </div>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="small text-white opacity-50 text-decoration-none hover-white" style="font-size: 0.78rem;">Forgot password?</a>
            @endif
        </div>
        <div class="mt-4 pt-2 border-top border-secondary border-opacity-25">
            <button type="submit" class="row-btn-blue w-100 justify-content-center" style="height: 40px !important;">Log in</button>
        </div>
        <div class="text-center mt-3 pt-2 border-top border-secondary border-opacity-10 small">
            <div class="text-white opacity-50 mb-1" style="font-size: 0.8rem;">
                Don't have an account? <a href="{{ route('register') }}" class="text-white text-decoration-underline opacity-100 fw-medium">Register</a>
            </div>
            <div style="font-size: 0.76rem;">
                <a href="{{ route('register.guest') }}" class="text-white opacity-40 text-decoration-none hover-white">Guest supervisor registration &rarr;</a>
            </div>
        </div>
    </form>
</x-guest-layout>
