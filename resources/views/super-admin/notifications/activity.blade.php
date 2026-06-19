<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <span>System Activity Log</span>
            <a href="#" onclick="history.back(); return false;" class="row-btn text-decoration-none d-inline-flex align-items-center gap-2" style="font-size: 0.85rem; font-weight: normal; text-transform: none; line-height: 24px; padding: 6px 16px;">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </x-slot>

    <!-- Custom CSS styles to make the header span 100% width -->
    <style>
        .main-content-area h1.h3 {
            width: 100%;
        }
    </style>

    <div class="py-4">
        <div class="container-fluid">
            <!-- Filter & Search Controls Card -->
            <div class="spotify-card mb-4">
                <div class="p-4">
                    <form method="GET" action="{{ route('activity-log') }}" id="filter-form" class="row g-3 align-items-end">
                        <!-- Status Filter -->
                        <div class="col-md-2">
                            <label class="form-label text-white opacity-50 small mb-1">Status Filter</label>
                            <select name="filter" class="form-select border-0 shadow-sm" onchange="document.getElementById('filter-form').submit()"
                                    style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important; height: 38px;">
                                <option value="all" @selected(!request('filter') || request('filter') === 'all') class="bg-dark text-white">All Statuses</option>
                                <option value="unread" @selected(request('filter') === 'unread') class="bg-dark text-white">Unread Only</option>
                                <option value="marked" @selected(request('filter') === 'marked') class="bg-dark text-white">Marked Only</option>
                            </select>
                        </div>

                        <!-- Bulk Action Select -->
                        <div class="col-md-2">
                            <label class="form-label text-white opacity-50 small mb-1">Bulk Action</label>
                            <select class="form-select border-0 shadow-sm cursor-pointer" id="bulkActionSelect"
                                    style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important; height: 38px; font-size: 0.85rem;">
                                <option value="" class="bg-dark text-white">Choose action</option>
                                <option value="read" class="bg-dark text-white">Mark Read</option>
                                <option value="unread" class="bg-dark text-white">Mark Unread</option>
                                <option value="mark" class="bg-dark text-white">Mark Highlighted</option>
                                <option value="unmark" class="bg-dark text-white">Unmark Highlighted</option>
                            </select>
                        </div>

                        <!-- Apply Action Button -->
                        <div class="col-md-1">
                            <button type="button" class="row-btn-outline-blue w-100 justify-content-center" id="applyBulkActionButton" style="height: 38px !important; display: inline-flex; align-items: center; gap: 6px; padding: 0;">Apply</button>
                        </div>

                        <!-- Search Input with Left Search Icon -->
                        <div class="col-md-5">
                            <label class="form-label text-white opacity-50 small mb-1">Search Keyword</label>
                            <div class="position-relative">
                                <i class="bi bi-search position-absolute text-white-50" style="left: 14px; top: 50%; transform: translateY(-50%); font-size: 0.9rem;"></i>
                                <input type="text" name="search" class="form-control border-0 shadow-sm" placeholder="Search and press Enter..." value="{{ request('search') }}"
                                       style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important; padding-left: 42px !important; height: 38px;">
                            </div>
                        </div>

                        <!-- Clear Filters Button (Always Visible in White) -->
                        <div class="col-md-2">
                            <a href="{{ route('activity-log') }}" class="row-btn w-100 text-decoration-none justify-content-center" style="height: 38px !important; display: inline-flex; align-items: center; gap: 6px;"><i class="bi bi-x-circle"></i> Clear Filters</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Bulk Operations & Notification List -->
            <div class="spotify-card mb-4">
                <div class="ps-3 pe-3 pt-4 pb-3 d-flex flex-wrap justify-content-between align-items-center gap-3" style="border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-check m-0 d-flex align-items-center">
                            <input class="form-check-input bg-dark border-secondary cursor-pointer" type="checkbox" id="selectAllNotifications" style="width: 1.15rem; height: 1.15rem; background-color: #121212;">
                            <label class="form-check-label text-white-50 fw-semibold ms-2 cursor-pointer" for="selectAllNotifications" style="font-size: 0.82rem;">Select All</label>
                        </div>
                    </div>

                    <div class="text-white-50 small">
                        Showing {{ $notifications->firstItem() ?? 0 }} - {{ $notifications->lastItem() ?? 0 }} of {{ $notifications->total() }} events
                    </div>
                </div>

                <div class="p-0">
                    <div class="activity-list">
                        @forelse ($notifications as $n)
                            @php
                                $msg = $n->data['message'] ?? '';
                                $prefix = 'Alert';
                                $username = 'System';
                                $content = $msg;
                                
                                // 1. Try to parse new standard format: [Prefix] Username: "Message"
                                if (preg_match('/^\[([^\]]+)\]\s*([^:]+):\s*"([^"]+)"/', $msg, $matches)) {
                                    $prefix = $matches[1];
                                    $username = trim($matches[2]);
                                    $content = $matches[3];
                                }
                                // 2. Try to parse old override requests: "username requested a session override: "text"."
                                elseif (preg_match('/^(.+?)\s+requested\s+a\s+session\s+override:\s*"(.+?)"/', $msg, $matches)) {
                                    $prefix = 'Session Override';
                                    $username = trim($matches[1]);
                                    $content = 'Requested override: ' . $matches[2];
                                }
                                // 3. Try to parse old forgot sign out: "username forgot to sign out on date. An auto-appended sign-out has been pending at time."
                                elseif (preg_match('/^(.+?)\s+forgot\s+to\s+sign\s+out\s+on\s+([^.]+?)\.\s+An\s+auto-appended/', $msg, $matches)) {
                                    $prefix = 'Forgot Sign Out';
                                    $username = trim($matches[1]);
                                    $content = 'Forgot to sign out on ' . $matches[2] . '.';
                                }
                                // 4. Try to parse old break exceeded: "username exceeded their break at time (came back at time, duration: duration mins)..."
                                elseif (preg_match('/^(.+?)\s+exceeded\s+their\s+break\s+at\s+([^(]+?)\s+\(came\s+back\s+at\s+([^,]+?),\s+duration:\s*([^)]+?)\)/', $msg, $matches)) {
                                    $prefix = 'Break Exceeded';
                                    $username = trim($matches[1]);
                                    $content = 'Exceeded break starting at ' . trim($matches[2]) . ' (back at ' . trim($matches[3]) . ')';
                                }

                                $initial = strtoupper(substr($username, 0, 1));
                                $isRead = !is_null($n->read_at);
                                $isMarked = (bool)($n->is_marked ?? false);
                            @endphp
                            <div class="activity-row d-flex align-items-center p-3 {{ $isRead ? 'opacity-75' : 'fw-bold' }}" 
                                 id="activity-row-{{ $n->id }}"
                                 style="transition: all 0.2s ease; background-color: {{ $isRead ? 'transparent' : 'rgba(255,255,255,0.015)' }};">
                                
                                <!-- Selection Checkbox -->
                                <div class="me-3">
                                    <input class="form-check-input notification-checkbox bg-dark border-secondary cursor-pointer" 
                                           type="checkbox" 
                                           value="{{ $n->id }}" 
                                           style="width: 1.15rem; height: 1.15rem;">
                                </div>

                                <!-- Avatar -->
                                <div class="custom-notification-avatar flex-shrink-0 me-3" style="width: 38px; height: 38px; font-size: 0.9rem; margin-top: -3px !important;">
                                    {{ $initial }}
                                </div>

                                <!-- Message and Bottom Details -->
                                <div class="flex-grow-1 min-width-0 pe-4">
                                    <div class="text-white mb-1" style="font-size: 0.88rem; line-height: 1.45;">
                                        @if ($n->data['url'] && $n->data['url'] !== '#')
                                            <a href="{{ $n->data['url'] }}" class="text-decoration-none text-white hover-underline">
                                                <strong>[{{ $prefix }}] {{ $username }}:</strong> "{{ $content }}"
                                            </a>
                                        @else
                                            <strong>[{{ $prefix }}] {{ $username }}:</strong> "{{ $content }}"
                                        @endif
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-1" style="height: 16px; min-height: 16px;">
                                        <div>
                                            @if(!$isRead)
                                                <span class="badge bg-primary rounded-pill me-2" style="font-size: 0.65rem; padding: 2px 6px; line-height: 1;">New</span>
                                            @endif
                                            @if($isMarked)
                                                <span class="badge bg-info text-dark rounded-pill" style="font-size: 0.65rem; padding: 2px 6px; line-height: 1;" id="badge-marked-{{ $n->id }}">
                                                    <i class="bi bi-bookmark-fill me-0.5" style="font-size: 0.6rem;"></i> Marked
                                                </span>
                                            @endif
                                        </div>
                                        <span class="text-white-50" style="font-size: 0.72rem;">{{ $n->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>

                                <!-- Row Action Buttons -->
                                <div class="d-flex align-items-center gap-2 flex-shrink-0 ps-3" style="border-left: 1px solid rgba(255, 255, 255, 0.12); height: 28px;">
                                    <!-- Toggle Read/Unread -->
                                    <button type="button" 
                                            class="btn-circle-gray action-toggle-read" 
                                            data-id="{{ $n->id }}" 
                                            data-custom-tooltip="{{ $isRead ? 'Mark as Unread' : 'Mark as Read' }}">
                                        @if($isRead)
                                            <i class="bi bi-envelope text-success"></i>
                                        @else
                                            <i class="bi bi-envelope-open-fill text-danger"></i>
                                        @endif
                                    </button>

                                    <!-- Toggle Mark/Unmark -->
                                    <button type="button" 
                                            class="btn-circle-gray action-toggle-mark" 
                                            data-id="{{ $n->id }}" 
                                            data-custom-tooltip="{{ $isMarked ? 'Unmark Highlighted' : 'Mark Highlighted' }}"
                                            id="btn-mark-{{ $n->id }}">
                                        @if($isMarked)
                                            <i class="bi bi-bookmark-fill" style="color: #3b82f6 !important;"></i>
                                        @else
                                            <i class="bi bi-bookmark text-white-50"></i>
                                        @endif
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="py-5 text-center text-white-50">
                                <i class="bi bi-bell-slash fs-2 mb-2 d-block"></i>
                                No system activities found matching the filters.
                            </div>
                        @endforelse
                    </div>

                    <!-- Pagination -->
                    @if($notifications->hasPages())
                        <div class="px-4 py-3 border-top" style="border-color: rgba(255, 255, 255, 0.08) !important; background-color: rgba(0, 0, 0, 0.2);">
                            {{ $notifications->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Style block to support modern design rules -->
    <style>
        .activity-row {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
        }
        .activity-row:last-child {
            border-bottom: none !important;
        }
        .activity-row:hover {
            background-color: rgba(255, 255, 255, 0.04) !important;
        }
        .hover-underline:hover {
            text-decoration: underline !important;
        }
        /* Style standard checkboxes for our dark theme */
        .form-check-input:checked {
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
        }
    </style>

    @push('scripts')
    <!-- Script block for interactive events & AJAX bulk/item management -->
    <script>
        $(function() {
            // Setup CSRF header
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Master checkbox Selection logic
            $('#selectAllNotifications').on('change', function() {
                const checked = this.checked;
                $('.notification-checkbox').prop('checked', checked);
            });

            // If some subcheckbox is unchecked, uncheck selectAll
            $('.notification-checkbox').on('change', function() {
                const total = $('.notification-checkbox').length;
                const checked = $('.notification-checkbox:checked').length;
                $('#selectAllNotifications').prop('checked', total === checked);
            });

            // Single item - Toggle Read/Unread
            $(document).on('click', '.action-toggle-read', function() {
                const btn = $(this);
                const id = btn.attr('data-id');
                const row = $('#activity-row-' + id);
                
                $.post(`/notifications/${id}/toggle-read`, { _token: '{{ csrf_token() }}' })
                    .done(function(data) {
                        if (data.is_read) {
                            row.addClass('opacity-75').removeClass('fw-bold');
                            btn.find('i').removeClass('bi-envelope-open-fill text-danger').addClass('bi-envelope text-success');
                            btn.attr('data-custom-tooltip', 'Mark as Unread');
                        } else {
                            row.removeClass('opacity-75').addClass('fw-bold');
                            btn.find('i').removeClass('bi-envelope text-success').addClass('bi-envelope-open-fill text-danger');
                            btn.attr('data-custom-tooltip', 'Mark as Read');
                        }
                        // Refresh navbar notification counts
                        if (window.refreshNotificationCount) {
                            window.refreshNotificationCount();
                        }
                    })
                    .fail(function() {
                        window.showToast('Failed to update status.', 'danger');
                    });
            });

            // Single item - Toggle Mark/Unmark
            $(document).on('click', '.action-toggle-mark', function() {
                const btn = $(this);
                const id = btn.attr('data-id');
                const row = $('#activity-row-' + id);
                
                $.post(`/notifications/${id}/toggle-mark`, { _token: '{{ csrf_token() }}' })
                    .done(function(data) {
                        let badge = $(`#badge-marked-${id}`);
                        if (data.is_marked) {
                            btn.find('i').removeClass('bi-bookmark text-white-50').addClass('bi-bookmark-fill').css('color', '#3b82f6');
                            btn.attr('data-custom-tooltip', 'Unmark Highlighted');
                            if (!badge.length) {
                                row.find('.d-flex.justify-content-between.align-items-center.mt-1 div')
                                   .append(`<span class="badge bg-info text-dark rounded-pill" style="font-size: 0.65rem; padding: 2px 6px; line-height: 1;" id="badge-marked-${id}"><i class="bi bi-bookmark-fill me-0.5" style="font-size: 0.6rem;"></i> Marked</span>`);
                            }
                        } else {
                            btn.find('i').removeClass('bi-bookmark-fill').addClass('bi-bookmark text-white-50').css('color', '');
                            btn.attr('data-custom-tooltip', 'Mark Highlighted');
                            if (badge.length) {
                                badge.remove();
                            }
                        }
                    })
                    .fail(function() {
                        window.showToast('Failed to update bookmark.', 'danger');
                    });
            });

            // Bulk actions implementation
            $('#applyBulkActionButton').on('click', function() {
                const action = $('#bulkActionSelect').val();
                if (!action) {
                    alert('Please select a bulk action.');
                    return;
                }

                const selectedIds = [];
                $('.notification-checkbox:checked').each(function() {
                    selectedIds.push($(this).val());
                });

                if (selectedIds.length === 0) {
                    alert('No items selected.');
                    return;
                }

                $.post('/notifications/bulk', {
                    _token: '{{ csrf_token() }}',
                    ids: selectedIds,
                    action: action
                })
                .done(function() {
                    window.showToast('Bulk action applied successfully.', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                })
                .fail(function() {
                    window.showToast('Bulk action failed.', 'danger');
                });
            });
        });
    </script>
    @endpush
</x-app-layout>
