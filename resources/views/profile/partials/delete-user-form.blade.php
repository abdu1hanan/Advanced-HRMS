<section>

    <p class="text-white opacity-50 small mb-4">Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.</p>
    <button type="button" class="row-btn-outline-red mt-3" data-bs-toggle="modal" data-bs-target="#confirmUserDeletionModal">
        Delete Account
    </button>

    <!-- Delete Account Modal -->
    <div class="modal fade {{ $errors->userDeletion->isNotEmpty() ? 'show d-block' : '' }}" id="confirmUserDeletionModal" tabindex="-1" aria-labelledby="confirmUserDeletionModalLabel" aria-hidden="true" {{ $errors->userDeletion->isNotEmpty() ? 'style="background-color: rgba(0,0,0,0.5);"' : '' }}>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="background-color: #0c0c0c !important; border: 1px solid rgba(255, 255, 255, 0.08) !important;">
                <div class="modal-header border-0 border-bottom border-secondary border-opacity-25">
                    <h5 class="modal-title fw-bold text-danger fs-6" id="confirmUserDeletionModalLabel"><i class="bi bi-exclamation-triangle-fill me-2"></i> Delete Account Confirmation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="const m = document.getElementById('confirmUserDeletionModal'); m.classList.remove('show', 'd-block'); m.style.display='none'; m.style.backgroundColor='';"></button>
                </div>
                <form method="post" action="{{ route('profile.destroy') }}" id="deleteAccountForm" novalidate>
                    @csrf
                    @method('delete')
                    
                    <div class="modal-body p-4">
                        <p class="text-white opacity-75 small mb-4">Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.</p>

                        <div class="mb-3">
                            <label for="delete_account_password" class="form-label small text-white opacity-75 sr-only">Password</label>
                            <input type="password" class="form-control text-white @error('password', 'userDeletion') is-invalid @enderror" id="delete_account_password" name="password" placeholder="Password"
                                   style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important;" autocomplete="current-password">
                            <div class="invalid-feedback">@error('password', 'userDeletion'){{ $message }}@enderror</div>
                        </div>
                    </div>
                    
                    <div class="modal-footer border-top border-secondary border-opacity-25">
                        <button type="button" class="row-btn" data-bs-dismiss="modal" onclick="const m = document.getElementById('confirmUserDeletionModal'); m.classList.remove('show', 'd-block'); m.style.display='none'; m.style.backgroundColor='';" style="width: 145px;">Cancel</button>
                        <button type="submit" class="row-btn-red" style="width: 145px;">Delete Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const deleteModal = document.getElementById('confirmUserDeletionModal');
            if (deleteModal) {
                document.body.appendChild(deleteModal);

                const deleteForm = document.getElementById('deleteAccountForm');
                const deletePasswordInput = document.getElementById('delete_account_password');
                const feedback = deletePasswordInput ? deletePasswordInput.parentElement.querySelector('.invalid-feedback') : null;

                function validateDeletePassword(showError = false) {
                    if (!deletePasswordInput.value.trim()) {
                        if (showError) {
                            deletePasswordInput.classList.add('is-invalid');
                            if (feedback) {
                                feedback.textContent = 'Password cannot be empty.';
                                feedback.style.display = 'block';
                            }
                        }
                        return false;
                    }
                    deletePasswordInput.classList.remove('is-invalid');
                    if (feedback) {
                        feedback.textContent = '';
                        feedback.style.display = '';
                    }
                    return true;
                }

                if (deletePasswordInput) {
                    deletePasswordInput.addEventListener('blur', function() {
                        validateDeletePassword(true);
                    });
                    deletePasswordInput.addEventListener('input', function() {
                        if (deletePasswordInput.classList.contains('is-invalid')) {
                            validateDeletePassword(true);
                        }
                    });
                }

                if (deleteForm) {
                    deleteForm.addEventListener('submit', async function(e) {
                        e.preventDefault();

                        if (!validateDeletePassword(true)) {
                            deletePasswordInput.focus();
                            return;
                        }

                        const submitBtn = deleteForm.querySelector('button[type="submit"]');
                        const originalBtnText = submitBtn ? submitBtn.innerHTML : 'Delete Account';
                        if (submitBtn) {
                            submitBtn.disabled = true;
                            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Deleting...';
                        }

                        try {
                            const formData = new FormData(deleteForm);
                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                                || deleteForm.querySelector('input[name="_token"]')?.value;

                            const response = await fetch(deleteForm.action, {
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
                                const errorMsg = data.errors?.password?.[0] || data.message || 'Incorrect password.';
                                deletePasswordInput.classList.add('is-invalid');
                                if (feedback) {
                                    feedback.textContent = errorMsg;
                                    feedback.style.display = 'block';
                                }
                                deletePasswordInput.focus();
                            } else if (response.ok) {
                                const targetUrl = data?.redirect || '{{ url("/") }}';
                                window.location.href = targetUrl;
                                return;
                            } else if (response.status === 419) {
                                deletePasswordInput.classList.add('is-invalid');
                                if (feedback) {
                                    feedback.textContent = 'Session expired. Please refresh the page.';
                                    feedback.style.display = 'block';
                                }
                                deletePasswordInput.focus();
                            } else {
                                const errorMsg = data?.message || 'Failed to delete account. Please try again.';
                                deletePasswordInput.classList.add('is-invalid');
                                if (feedback) {
                                    feedback.textContent = errorMsg;
                                    feedback.style.display = 'block';
                                }
                                deletePasswordInput.focus();
                            }
                        } catch (err) {
                            console.error('Account deletion error:', err);
                            deletePasswordInput.classList.add('is-invalid');
                            if (feedback) {
                                feedback.textContent = 'A network error occurred. Please try again.';
                                feedback.style.display = 'block';
                            }
                        } finally {
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalBtnText;
                            }
                        }
                    });
                }

                deleteModal.addEventListener('hidden.bs.modal', function () {
                    if (deletePasswordInput) {
                        deletePasswordInput.value = '';
                        deletePasswordInput.classList.remove('is-invalid');
                    }
                    if (feedback) {
                        feedback.textContent = '';
                        feedback.style.display = '';
                    }
                });
            }
        });
    </script>
</section>
