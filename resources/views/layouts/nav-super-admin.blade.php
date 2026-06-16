<ul class="nav flex-column px-2">
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('super-admin.dashboard') ? 'active' : '' }}" href="{{ route('super-admin.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('super-admin.users.*') ? 'active' : '' }}" href="{{ route('super-admin.users.index') }}"><i class="bi bi-people me-2"></i>User Management</a></li>
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('super-admin.departments.*') ? 'active' : '' }}" href="{{ route('super-admin.departments.index') }}"><i class="bi bi-building me-2"></i>Departments</a></li>
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('super-admin.leave-types.*') ? 'active' : '' }}" href="{{ route('super-admin.leave-types.index') }}"><i class="bi bi-calendar3 me-2"></i>Leave Types</a></li>
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('super-admin.leave-settings.*') ? 'active' : '' }}" href="{{ route('super-admin.leave-settings.index') }}"><i class="bi bi-sliders me-2"></i>Leave Settings</a></li>
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('super-admin.leave-requests.index') ? 'active' : '' }}" href="{{ route('super-admin.leave-requests.index') }}"><i class="bi bi-chat-left-text me-2"></i>All Leave Requests</a></li>
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('super-admin.leave-requests.history') ? 'active' : '' }}" href="{{ route('super-admin.leave-requests.history') }}"><i class="bi bi-clock-history me-2"></i>Leave History</a></li>
    <li class="nav-item">
        <a class="nav-link text-white d-flex justify-content-between align-items-center {{ request()->routeIs('super-admin.session-management.*') ? 'active' : '' }}" 
           data-bs-toggle="collapse" 
           href="#sessionSubmenu" 
           role="button" 
           aria-expanded="{{ request()->routeIs('super-admin.session-management.*') ? 'true' : 'false' }}" 
           aria-controls="sessionSubmenu">
            <span><i class="bi bi-cpu me-2"></i>Session Management</span>
        </a>
        <div class="collapse {{ request()->routeIs('super-admin.session-management.*') ? 'show' : '' }}" id="sessionSubmenu">
            <ul class="nav flex-column session-tree-submenu mt-1">
                <li class="nav-item" style="border: none !important;">
                    <a class="nav-link py-1 text-white opacity-75 {{ request()->routeIs('super-admin.session-management.overrides') ? 'active fw-bold' : '' }}" href="{{ route('super-admin.session-management.overrides') }}">
                        <i class="bi bi-chat-quote me-2"></i>Beetle Sync
                    </a>
                </li>
                <li class="nav-item" style="border: none !important;">
                    <a class="nav-link py-1 text-white opacity-75 {{ request()->routeIs('super-admin.session-management.mappings') ? 'active fw-bold' : '' }}" href="{{ route('super-admin.session-management.mappings') }}">
                        <i class="bi bi-person-bounding-box me-2"></i>Slack User Mapping
                    </a>
                </li>
                <li class="nav-item" style="border: none !important;">
                    <a class="nav-link py-1 text-white opacity-75 {{ request()->routeIs('super-admin.session-management.track-record') ? 'active fw-bold' : '' }}" href="{{ route('super-admin.session-management.track-record') }}">
                        <i class="bi bi-calendar-check me-2"></i>Employee Track Record
                    </a>
                </li>
            </ul>
        </div>
    </li>
</ul>
