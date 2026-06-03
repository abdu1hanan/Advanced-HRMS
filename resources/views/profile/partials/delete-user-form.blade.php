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
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="document.getElementById('confirmUserDeletionModal').classList.remove('show', 'd-block')"></button>
                </div>
                <form method="post" action="{{ route('profile.destroy') }}">
                    @csrf
                    @method('delete')
                    
                    <div class="modal-body p-4">
                        <p class="text-white opacity-75 small mb-4">Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.</p>

                        <div class="mb-3">
                            <label for="password" class="form-label small text-white opacity-75 sr-only">Password</label>
                            <input type="password" class="form-control text-white @error('password', 'userDeletion') is-invalid @enderror" id="password" name="password" placeholder="Password"
                                   style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important;">
                            @error('password', 'userDeletion')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="modal-footer border-top border-secondary border-opacity-25">
                        <button type="button" class="row-btn" data-bs-dismiss="modal" onclick="document.getElementById('confirmUserDeletionModal').classList.remove('show', 'd-block')" style="width: 145px;">Cancel</button>
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
            }
        });
    </script>
</section>
