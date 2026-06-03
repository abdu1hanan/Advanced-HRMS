<section>

    <form method="post" action="{{ route('password.update') }}" class="mt-4">
        @csrf
        @method('put')

        <div class="mb-3">
            <label for="update_password_current_password" class="form-label small text-white opacity-75">Current Password</label>
            <input type="password" class="form-control bg-dark border-secondary text-white @error('current_password', 'updatePassword') is-invalid @enderror" id="update_password_current_password" name="current_password" autocomplete="current-password">
            @error('current_password', 'updatePassword')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="update_password_password" class="form-label small text-white opacity-75">New Password</label>
            <input type="password" class="form-control bg-dark border-secondary text-white @error('password', 'updatePassword') is-invalid @enderror" id="update_password_password" name="password" autocomplete="new-password">
            @error('password', 'updatePassword')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="update_password_password_confirmation" class="form-label small text-white opacity-75">Confirm Password</label>
            <input type="password" class="form-control bg-dark border-secondary text-white @error('password_confirmation', 'updatePassword') is-invalid @enderror" id="update_password_password_confirmation" name="password_confirmation" autocomplete="new-password">
            @error('password_confirmation', 'updatePassword')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex align-items-center gap-3 mt-4 pt-3 border-top border-secondary border-opacity-25">
            <button type="submit" class="row-btn-blue">Save Password</button>

            @if (session('status') === 'password-updated')
                <span class="text-success small opacity-75">Saved successfully.</span>
            @endif
        </div>
    </form>
</section>
