<x-guest-layout>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label small text-white opacity-75">Email</label>
            <input type="email" class="form-control bg-dark border-secondary text-white @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="password" class="form-label small text-white opacity-75">Password</label>
            <input type="password" class="form-control bg-dark border-secondary text-white @error('password') is-invalid @enderror" id="password" name="password" required>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input bg-dark border-secondary" id="remember_me" name="remember">
            <label class="form-check-label small text-white opacity-75" for="remember_me">Remember me</label>
        </div>
        <div class="mt-4 pt-2 border-top border-secondary border-opacity-25">
            <button type="submit" class="row-btn-blue w-100 justify-content-center" style="height: 40px !important;">Log in</button>
        </div>
        <p class="text-center mt-3 mb-0 small">
            <a href="{{ route('register') }}" class="text-white opacity-50 text-decoration-none">Create an account</a>
        </p>
    </form>
</x-guest-layout>
