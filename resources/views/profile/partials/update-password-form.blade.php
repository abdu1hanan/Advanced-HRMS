<section>

    <form method="post" action="{{ route('password.update') }}" class="mt-4" id="updatePasswordForm" novalidate>
        @csrf
        @method('put')

        <div class="mb-3">
            <label for="update_password_current_password" class="form-label small text-white opacity-75">Current Password <span class="text-danger">*</span></label>
            <input type="password" class="form-control bg-dark border-secondary text-white @error('current_password', 'updatePassword') is-invalid @enderror" id="update_password_current_password" name="current_password" autocomplete="current-password" placeholder="Current password">
            <div class="invalid-feedback">@error('current_password', 'updatePassword'){{ $message }}@enderror</div>
        </div>

        <div class="mb-3">
            <label for="update_password_password" class="form-label small text-white opacity-75">New Password <span class="text-danger">*</span></label>
            <input type="password" class="form-control bg-dark border-secondary text-white @error('password', 'updatePassword') is-invalid @enderror" id="update_password_password" name="password" autocomplete="new-password" placeholder="New password (min. 8 characters)">
            <div class="invalid-feedback">@error('password', 'updatePassword'){{ $message }}@enderror</div>
        </div>

        <div class="mb-3">
            <label for="update_password_password_confirmation" class="form-label small text-white opacity-75">Confirm Password <span class="text-danger">*</span></label>
            <input type="password" class="form-control bg-dark border-secondary text-white @error('password_confirmation', 'updatePassword') is-invalid @enderror" id="update_password_password_confirmation" name="password_confirmation" autocomplete="new-password" placeholder="Confirm new password">
            <div class="invalid-feedback">@error('password_confirmation', 'updatePassword'){{ $message }}@enderror</div>
        </div>

        <div class="d-flex align-items-center gap-3 mt-4 pt-3 border-top border-secondary border-opacity-25">
            <button type="submit" class="row-btn-blue">Save Password</button>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('updatePasswordForm');
            if (!form) return;

            const currentPwd = document.getElementById('update_password_current_password');
            const newPwd = document.getElementById('update_password_password');
            const confirmPwd = document.getElementById('update_password_password_confirmation');

            function setError(input, message) {
                input.classList.add('is-invalid');
                const feedback = input.parentElement.querySelector('.invalid-feedback');
                if (feedback) {
                    feedback.textContent = message;
                    feedback.style.display = 'block';
                }
            }

            function clearError(input) {
                input.classList.remove('is-invalid');
                const feedback = input.parentElement.querySelector('.invalid-feedback');
                if (feedback) {
                    feedback.textContent = '';
                    feedback.style.display = '';
                }
            }

            function validateCurrentPassword(showError = false) {
                const val = currentPwd.value.trim();
                if (!val) {
                    if (showError) setError(currentPwd, 'Current password cannot be empty.');
                    return false;
                }
                clearError(currentPwd);
                return true;
            }

            function validateNewPassword(showError = false) {
                const val = newPwd.value.trim();
                if (!val) {
                    if (showError) setError(newPwd, 'New password cannot be empty.');
                    return false;
                }
                if (val.length < 8) {
                    if (showError) setError(newPwd, 'New password must be at least 8 characters.');
                    return false;
                }
                clearError(newPwd);
                return true;
            }

            function validateConfirmPassword(showError = false) {
                const val = confirmPwd.value.trim();
                if (!val) {
                    if (showError) setError(confirmPwd, 'Please confirm your new password.');
                    return false;
                }
                if (newPwd.value.trim() && val !== newPwd.value.trim()) {
                    if (showError) setError(confirmPwd, 'Passwords do not match.');
                    return false;
                }
                clearError(confirmPwd);
                return true;
            }

            currentPwd.addEventListener('blur', function () { validateCurrentPassword(true); });
            newPwd.addEventListener('blur', function () {
                validateNewPassword(true);
                if (confirmPwd.value.trim()) validateConfirmPassword(true);
            });
            confirmPwd.addEventListener('blur', function () { validateConfirmPassword(true); });

            currentPwd.addEventListener('input', function () {
                if (currentPwd.classList.contains('is-invalid')) validateCurrentPassword(true);
            });
            newPwd.addEventListener('input', function () {
                if (newPwd.classList.contains('is-invalid')) validateNewPassword(true);
                if (confirmPwd.classList.contains('is-invalid') || confirmPwd.value.trim()) {
                    validateConfirmPassword(confirmPwd.classList.contains('is-invalid'));
                }
            });
            confirmPwd.addEventListener('input', function () {
                if (confirmPwd.classList.contains('is-invalid')) validateConfirmPassword(true);
            });

            form.addEventListener('submit', async function (e) {
                e.preventDefault();

                const vCurrent = validateCurrentPassword(true);
                const vNew = validateNewPassword(true);
                const vConfirm = validateConfirmPassword(true);

                if (!vCurrent || !vNew || !vConfirm) {
                    if (!vCurrent) currentPwd.focus();
                    else if (!vNew) newPwd.focus();
                    else confirmPwd.focus();
                    return;
                }

                const submitBtn = form.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn ? submitBtn.innerHTML : 'Save Password';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
                }

                try {
                    const formData = new FormData(form);
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
                        || form.querySelector('input[name="_token"]')?.value;

                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: formData,
                    });

                    const data = await response.json().catch(() => null);

                    if (response.status === 422 && data) {
                        if (data.errors) {
                            if (data.errors.current_password) {
                                setError(currentPwd, data.errors.current_password[0]);
                                currentPwd.focus();
                            }
                            if (data.errors.password) {
                                setError(newPwd, data.errors.password[0]);
                                if (!data.errors.current_password) newPwd.focus();
                            }
                            if (data.errors.password_confirmation) {
                                setError(confirmPwd, data.errors.password_confirmation[0]);
                            }
                        } else if (data.message) {
                            setError(currentPwd, data.message);
                            currentPwd.focus();
                        }
                    } else if (response.ok) {
                        currentPwd.value = '';
                        newPwd.value = '';
                        confirmPwd.value = '';
                        clearError(currentPwd);
                        clearError(newPwd);
                        clearError(confirmPwd);

                        if (window.showToast) {
                            window.showToast(data?.message || 'Password updated successfully.', 'success');
                        }
                    } else if (response.status === 419) {
                        setError(currentPwd, 'Session expired. Please refresh the page and try again.');
                        currentPwd.focus();
                    } else {
                        const msg = data?.message || 'Failed to update password. Please try again.';
                        setError(currentPwd, msg);
                        currentPwd.focus();
                    }
                } catch (err) {
                    console.error('Password update error:', err);
                    setError(currentPwd, 'Network error. Please try again.');
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    }
                }
            });
        });
    </script>
</section>
