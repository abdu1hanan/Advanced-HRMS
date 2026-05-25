<nav class="navbar navbar-expand-lg navbar-dark navbar-glass py-2.5">
    <div class="container-fluid d-flex align-items-center justify-content-between">
        
        <!-- Left Side: Brand Logo & Desktop Sidebar Toggle Trigger -->
        <div class="d-flex align-items-center gap-3">
            <!-- Company Logo Icon Upload Placeholder -->
            <div class="company-logo-container d-flex align-items-center justify-content-center bg-white rounded-circle shadow-sm" style="width: 34px; height: 34px; overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.15);">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="w-100 h-100 object-fit-cover" onerror="this.onerror=null; this.src='https://placehold.co/34x34/ffffff/000000?text=C';">
            </div>
            
            <button class="sidebar-toggle-btn d-none d-md-flex" id="desktopSidebarToggle" type="button" title="Toggle Sidebar">
                <i class="bi bi-chevron-left" id="toggleIcon"></i>
            </button>
        </div>

        <!-- Center: Centered Title -> HRMS Only -->
        <div class="navbar-center-title text-center text-white fw-bold mb-0 text-uppercase d-flex align-items-center justify-content-center">
            HRMS
        </div>

        <!-- Right Side: Circular Bell Notification and User Avatar Badge -->
        <div class="d-flex align-items-center gap-3">
            <!-- Sleek Circular Bell Dropdown with Glow Dot badge -->
            <div class="dropdown">
                <button class="navbar-circle-btn position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-bell-fill fs-6"></i>
                    <!-- Clean notification Dot instead of numbers -->
                    <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle d-none" id="notification-badge" style="width: 8px; height: 8px;"></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border border-secondary bg-dark text-white p-2" style="min-width: 320px; max-height: 400px; overflow-y: auto;" id="notification-list">
                    <li class="dropdown-header d-flex justify-content-between align-items-center text-white opacity-75">
                        Notifications
                        <button type="button" class="btn btn-link btn-sm p-0 text-white-50 text-decoration-none" id="mark-all-read">Mark all read</button>
                    </li>
                    <li><hr class="dropdown-divider border-secondary"></li>
                    <li class="px-3 py-2 text-white-50 small text-center" id="notification-empty">No notifications</li>
                </ul>
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
