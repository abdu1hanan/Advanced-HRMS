<nav class="navbar navbar-expand-lg navbar-dark navbar-glass py-1">
    <div class="container-fluid d-flex align-items-center justify-content-between">
        
        <!-- Left Side: Brand Logo (acts as menu toggle trigger) -->
        <div class="d-flex align-items-center" style="width: 65px; justify-content: center;">
            <!-- Company Logo Icon (triggers toggle menu directly) -->
            <img src="{{ asset('images/logo.png') }}" id="desktopSidebarToggle" alt="Logo" style="height: 65px; width: 65px; object-fit: contain; cursor: pointer; user-select: none;" onerror="this.onerror=null; this.src='https://placehold.co/65x48/ffffff/000000?text=C';">
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
                    <li><hr class="dropdown-divider border-secondary my-1.5"></li>
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
