<section>
    <header>
        <h5 class="fw-bold text-dark">Profile Information</h5>
        <p class="text-muted small">Update your account's profile information and email address.</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-4">
        @csrf
        @method('patch')

        <div class="mb-3">
            <label for="name" class="form-label small fw-medium">Name</label>
            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="email" class="form-label small fw-medium">Email Address</label>
            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="username">
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

        <div class="d-flex align-items-center gap-3 mt-4">
            <button type="submit" class="btn btn-primary">Save Changes</button>

            @if (session('status') === 'profile-updated')
                <span class="text-success small fw-medium">Saved successfully.</span>
            @endif
        </div>
    </form>
</section>
