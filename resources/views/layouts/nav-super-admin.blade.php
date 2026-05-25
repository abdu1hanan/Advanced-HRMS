<ul class="nav flex-column px-2">
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('super-admin.dashboard') ? 'active bg-secondary rounded' : '' }}" href="{{ route('super-admin.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('super-admin.users.*') ? 'active bg-secondary rounded' : '' }}" href="{{ route('super-admin.users.index') }}"><i class="bi bi-people me-2"></i>User Management</a></li>
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('super-admin.departments.*') ? 'active bg-secondary rounded' : '' }}" href="{{ route('super-admin.departments.index') }}"><i class="bi bi-building me-2"></i>Departments</a></li>
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('super-admin.leave-types.*') ? 'active bg-secondary rounded' : '' }}" href="{{ route('super-admin.leave-types.index') }}"><i class="bi bi-calendar3 me-2"></i>Leave Types</a></li>
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('super-admin.leave-settings.*') ? 'active bg-secondary rounded' : '' }}" href="{{ route('super-admin.leave-settings.index') }}"><i class="bi bi-sliders me-2"></i>Leave Settings</a></li>
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('super-admin.leave-requests.*') ? 'active bg-secondary rounded' : '' }}" href="{{ route('super-admin.leave-requests.index') }}"><i class="bi bi-clock-history me-2"></i>All Leave Requests</a></li>
</ul>
