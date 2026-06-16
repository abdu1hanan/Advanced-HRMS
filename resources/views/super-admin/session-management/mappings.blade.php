<x-app-layout>
    <x-slot name="header">Slack User Mapping</x-slot>

    <!-- Main Table Card -->
    <div class="spotify-card mb-4 mt-3">
        <div class="card-header-area px-4 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center">
                    <h6 class="text-uppercase text-white fw-semibold small mb-0 opacity-75" style="letter-spacing: 0.05em;">User Assignments</h6>
                </div>
                <div class="d-flex align-items-center gap-3 px-3 py-1" style="background: rgba(255, 255, 255, 0.05); border-radius: 50px; border: 1px solid rgba(255, 255, 255, 0.1);">
                    <i class="bi bi-info-circle text-white opacity-75 cursor-pointer" 
                       data-custom-tooltip="Auto-assign users by posting this function in Slack: &#10;{auto_connect_user_profile:&quot;Employee Name&quot;}" 
                       data-tooltip-placement="top" 
                       style="font-size: 0.85rem;"></i>
                    <span class="text-white small opacity-75" style="user-select: none; font-size: 0.82rem;">Auto-Connect Function</span>
                    <div class="form-check form-switch mb-0" style="padding-left: 0;">
                        <input class="form-check-input bg-dark border-secondary cursor-pointer" type="checkbox" id="toggle-auto-connect" {{ $autoConnectEnabled ? 'checked' : '' }} style="width: 2.2em; height: 1.1em; margin-left: 0;">
                    </div>
                </div>
            </div>
            <div class="grid-separator"></div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4" style="width: 15%;">Slack User ID</th>
                        <th style="width: 25%;">Custom Reference Name</th>
                        <th style="width: 25%;">Last Activity Hint</th>
                        <th style="width: 20%;">HRMS Employee Account</th>
                        <th style="width: 10%;">Status</th>
                        <th class="pe-4 text-end" style="width: 5%;">Action</th>
                    </tr>
                </thead>
                <tbody id="mapping-table-body">
                    @forelse($userMappings as $map)
                        <tr data-slack-id="{{ $map['slack_user_id'] }}">
                            <!-- Slack User ID -->
                            <td class="ps-4">
                                <code class="text-white opacity-75 px-3 py-1 rounded-pill bg-dark border border-secondary border-opacity-25" style="border-radius: 50px !important; font-size: 0.8rem; display: inline-block;">{{ $map['slack_user_id'] }}</code>
                            </td>
                            
                            <!-- Custom Reference Name -->
                            <td>
                                <input type="text" 
                                       class="form-control form-control-sm text-white" 
                                       value="{{ $map['slack_username'] }}" 
                                       placeholder="e.g. John's Personal Slack"
                                       data-field="slack_username"
                                       style="height: 32px !important; border-radius: 50px !important; padding: 0 1rem !important; font-size: 0.85rem !important;">
                            </td>
 
                            <!-- Last Activity Hint -->
                            <td>
                                @if($map['last_message_text'])
                                    <div class="text-white font-monospace text-wrap mb-1" style="font-size: 0.8rem; word-break: break-word;">
                                        "{{ $map['last_message_text'] }}"
                                    </div>
                                    <div class="text-white opacity-75" style="font-size: 0.72rem;">
                                        {{ $map['last_message_at']->timezone('Asia/Karachi')->format('M d, Y h:i A') }}
                                    </div>
                                @else
                                    <span class="text-white opacity-50 small italic" style="font-size: 0.8rem;">No activity captured</span>
                                @endif
                            </td>
 
                            <!-- HRMS Employee Account -->
                            <td>
                                <select class="form-select form-select-sm text-white" 
                                        data-field="user_id"
                                        style="height: 32px !important; border-radius: 50px !important; padding: 0 1rem !important; font-size: 0.85rem !important;">
                                    <option value="" class="text-muted">-- Unmapped --</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ $map['user_id'] == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }} ({{ $user->email }})
                                        </option>
                                    @endforeach
                                </select>
                            </td>
 
                            <!-- Status -->
                            <td>
                                <span class="status-badge-container">
                                    @if($map['user'])
                                        <span class="row-badge-green">Mapped</span>
                                    @else
                                        <span class="row-badge-red">Unmapped</span>
                                    @endif
                                </span>
                            </td>
 
                            <!-- Action -->
                            <td class="pe-4 text-end">
                                <button type="button" class="row-btn-outline-blue save-mapping-btn">Save</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-white opacity-50 py-5">
                                <div class="mb-2"><i class="bi bi-person-x fs-3"></i></div>
                                No Slack user activity or mapping records found. Send a sync message from Slack to capture an ID!
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
        <script>
            $(function() {
                function showToast(message, isError = false) {
                    if (window.showToast) {
                        window.showToast(message, isError ? 'danger' : 'success');
                    } else {
                        alert(message);
                    }
                }

                // Initialize Bootstrap Tooltips
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });

                // Toggle Auto-Connect switch handler
                $('#toggle-auto-connect').on('change', function() {
                    const isChecked = $(this).is(':checked');
                    const toggleSwitch = $(this);
                    toggleSwitch.prop('disabled', true);

                    $.ajax({
                        url: "{{ route('super-admin.session-management.toggle-auto-connect') }}",
                        method: "POST",
                        data: {
                            _token: "{{ csrf_token() }}",
                            enabled: isChecked ? 1 : 0
                        },
                        success: function(response) {
                            toggleSwitch.prop('disabled', false);
                            showToast(response.message);
                        },
                        error: function(err) {
                            toggleSwitch.prop('disabled', false);
                            toggleSwitch.prop('checked', !isChecked); // revert state
                            showToast('Failed to update setting.', true);
                        }
                    });
                });

                // Handle save button trigger
                $('.save-mapping-btn').on('click', function() {
                    const row = $(this).closest('tr');
                    const slackId = row.data('slack-id');
                    const slackUsername = row.find('[data-field="slack_username"]').val();
                    const userId = row.find('[data-field="user_id"]').val();
                    const saveBtn = $(this);

                    // Visual feedback during save
                    saveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');

                    $.ajax({
                        url: "{{ route('super-admin.session-management.save-mapping') }}",
                        method: "POST",
                        data: {
                            _token: "{{ csrf_token() }}",
                            slack_user_id: slackId,
                            slack_username: slackUsername,
                            user_id: userId
                        },
                        success: function(response) {
                            saveBtn.prop('disabled', false).html('Save');

                            if (response.status === 'success') {
                                showToast(response.message);
                                
                                // Update badge dynamic state
                                const badgeContainer = row.find('.status-badge-container');
                                if (response.mapping.user_id) {
                                    badgeContainer.html('<span class="row-badge-green">Mapped</span>');
                                } else {
                                    badgeContainer.html('<span class="row-badge-red">Unmapped</span>');
                                }
                            } else {
                                showToast('Failed to save mapping.', true);
                            }
                        },
                        error: function(err) {
                            saveBtn.prop('disabled', false).html('Save');

                            let errorMsg = 'An error occurred while saving the mapping.';
                            if (err.responseJSON && err.responseJSON.errors) {
                                errorMsg = Object.values(err.responseJSON.errors)[0][0];
                            }
                            showToast(errorMsg, true);
                        }
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
