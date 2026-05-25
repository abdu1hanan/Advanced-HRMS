<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="{{ route('dashboard') }}">BeetleOps HRMS</a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <div class="dropdown">
                <button class="btn btn-outline-light btn-sm position-relative border-0" type="button" id="notificationDropdown" data-bs-toggle="dropdown">
                    <i class="bi bi-bell fs-5"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="notification-badge">0</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width: 320px;" id="notification-list">
                    <li class="dropdown-header d-flex justify-content-between align-items-center">
                        Notifications
                        <button type="button" class="btn btn-link btn-sm p-0" id="mark-all-read">Mark all read</button>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li class="px-3 py-2 text-muted small" id="notification-empty">No notifications</li>
                </ul>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="text-white fw-medium d-none d-md-inline">{{ auth()->user()->name }}</span>
            </div>
        </div>
    </div>
</nav>
