<ul class="nav flex-column px-2">
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.dashboard') ? 'active bg-secondary rounded' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>My Dashboard</a></li>
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.leaves.record') ? 'active bg-secondary rounded' : '' }}" href="{{ route('admin.leaves.record') }}"><i class="bi bi-journal-text me-2"></i>My Attendance &amp; Logs</a></li>
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.track-record') ? 'active bg-secondary rounded' : '' }}" href="{{ route('admin.track-record') }}"><i class="bi bi-file-earmark-bar-graph me-2"></i>My Track Record</a></li>
</ul>

