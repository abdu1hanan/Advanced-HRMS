<ul class="nav flex-column px-2">
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.dashboard') ? 'active bg-secondary rounded' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.leaves.apply') ? 'active bg-secondary rounded' : '' }}" href="{{ route('admin.leaves.apply') }}"><i class="bi bi-pencil-square me-2"></i>Apply for Leave</a></li>
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.team.directory') ? 'active bg-secondary rounded' : '' }}" href="{{ route('admin.team.directory') }}"><i class="bi bi-people-fill me-2"></i>Team Directory</a></li>
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.team.leaves') ? 'active bg-secondary rounded' : '' }}" href="{{ route('admin.team.leaves') }}"><i class="bi bi-inbox me-2"></i>Pending Leaves</a></li>
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.team.leaves.history') ? 'active bg-secondary rounded' : '' }}" href="{{ route('admin.team.leaves.history') }}"><i class="bi bi-clock-history me-2"></i>Leave History</a></li>
</ul>
