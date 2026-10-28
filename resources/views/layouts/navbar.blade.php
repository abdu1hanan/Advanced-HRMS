<nav class="navbar navbar-expand-lg navbar-dark navbar-glass py-1">
    <div class="container-fluid d-flex align-items-center justify-content-between">
        
        <!-- Left Side: Brand Logo (acts as menu toggle trigger) -->
        <div class="d-flex align-items-center" style="width: 65px; justify-content: center;">
            <!-- Company Logo Icon (triggers toggle menu directly) -->
            <img src="{{ asset('images/logo.png') }}" id="desktopSidebarToggle" alt="Logo" data-custom-tooltip="Close sidebar" data-tooltip-placement="right" style="height: 65px; width: 65px; object-fit: contain; cursor: pointer; user-select: none;" onerror="this.onerror=null; this.src='https://placehold.co/65x48/ffffff/000000?text=C';">
            <script>
                if (localStorage.getItem('inverted-bw') === 'true') {
                    document.getElementById('desktopSidebarToggle').src = "{{ asset('images/logo-dark.png') }}";
                }
            </script>
        </div>

        <!-- Center: Centered Title -> HRMS Only -->
        <div id="toggle-color-switch" class="navbar-center-title text-center text-white fw-bold mb-0 text-uppercase d-flex align-items-center justify-content-center" style="cursor: pointer; user-select: none;">
            HRMS
        </div>

        <!-- Right Side: Circular Bell Notification and User Avatar Badge -->
        <div class="d-flex align-items-center gap-3">
            <!-- Sleek Circular Bell Dropdown with Glow Dot badge -->
            <div class="dropdown">
                <button class="navbar-circle-btn position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false" data-custom-tooltip="Notifications" data-tooltip-placement="left">
                    <i class="bi bi-bell-fill fs-6"></i>
                    <!-- Clean notification Dot instead of numbers -->
                    <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle d-none" id="notification-badge" style="width: 8px; height: 8px;"></span>
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow border border-secondary bg-dark text-white p-3 custom-notification-dropdown" style="width: 380px; max-height: 480px; overflow: hidden; border-radius: 12px;" id="notification-container">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="m-0 fw-bold text-white fs-6">Notifications</h5>
                        <button type="button" class="btn-circle-gray" id="mark-all-read" data-custom-tooltip="Mark all read" data-tooltip-placement="top">
                            <i class="bi bi-check-all" style="font-size: 1.1rem; line-height: 1;"></i>
                        </button>
                    </div>

                    <!-- Filter pills -->
                    <div class="d-flex gap-2 mb-2">
                        <button type="button" class="filter-pill active" id="filter-all-btn">All</button>
                        <button type="button" class="filter-pill" id="filter-unread-btn">Unread</button>
                    </div>

                    <div class="dropdown-divider border-secondary my-2"></div>

                    <!-- New / See all section -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold text-white-50" style="font-size: 0.78rem; margin-left: 0.35rem; line-height: 1;">New</span>
                        <a href="{{ route('activity-log') }}" id="see-all-notifications" style="line-height: 1;">See all</a>
                    </div>

                    <!-- Notifications List container with custom scrollbar -->
                    <div class="custom-notification-list-scroll" style="max-height: 280px; overflow-y: auto; margin-left: -0.3rem; margin-right: -0.3rem;">
                        <ul class="list-unstyled mb-0" id="notification-list">
                            <!-- JS will populate notifications here -->
                            <li class="py-4 text-white-50 small text-center" id="notification-empty">No notifications</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Sleek Circular User Badge -->
            <div class="navbar-user-badge gap-2 shadow-sm">
                <div class="user-avatar-circle shadow-sm">
                    {{ substr(auth()->user()->name, 0, 1) }}
                </div>
                <span class="text-white d-none d-md-inline">{{ auth()->user()->name }}</span>
            </div>
        </div>

    </div>
</nav>
