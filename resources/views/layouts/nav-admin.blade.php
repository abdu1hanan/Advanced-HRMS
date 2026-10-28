<ul class="nav flex-column px-2">
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.dashboard') ? 'active bg-secondary rounded' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
    @if(auth()->user()->isApproved())
        @if(!auth()->user()->isGuest())
            <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.leaves.record') ? 'active bg-secondary rounded' : '' }}" href="{{ route('admin.leaves.record') }}"><i class="bi bi-journal-text me-2"></i>My Attendance &amp; Logs</a></li>
            <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.track-record') ? 'active bg-secondary rounded' : '' }}" href="{{ route('admin.track-record') }}"><i class="bi bi-file-earmark-bar-graph me-2"></i>My Track Record</a></li>
        @endif
        <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.hierarchy.*') ? 'active bg-secondary rounded' : '' }}" href="{{ route('admin.hierarchy.index') }}"><i class="bi bi-diagram-3 me-2"></i>System Hierarchy</a></li>

        @if(auth()->user()->hasSubordinates())
            <li class="nav-item">
                <a class="nav-link text-white {{ request()->routeIs('admin.team.*') ? 'active' : '' }}" 
                   data-bs-toggle="collapse" 
                   href="#teamSubmenu" 
                   role="button" 
                   aria-expanded="{{ request()->routeIs('admin.team.*') ? 'true' : 'false' }}" 
                   aria-controls="teamSubmenu">
                    <i class="bi bi-people-fill me-2"></i>Team Management
                </a>
                <div class="collapse {{ request()->routeIs('admin.team.*') ? 'show' : '' }}" id="teamSubmenu">
                    <ul class="nav flex-column session-tree-submenu mt-1">
                        <li class="nav-item" style="border: none !important;">
                            <a class="nav-link py-1 text-white opacity-75 {{ (request()->routeIs('admin.team.directory') || request()->routeIs('admin.team.attendance-logs') || request()->routeIs('admin.team.directory.logs')) ? 'active fw-bold' : '' }}" href="{{ route('admin.team.directory') }}">
                                <i class="bi bi-people me-2"></i>Team Directory &amp; Logs
                            </a>
                        </li>
                        <li class="nav-item" style="border: none !important;">
                            <a class="nav-link py-1 text-white opacity-75 {{ request()->routeIs('admin.team.track-record') ? 'active fw-bold' : '' }}" href="{{ route('admin.team.track-record') }}">
                                <i class="bi bi-calendar-check me-2"></i>Track Records
                            </a>
                        </li>
                        <li class="nav-item" style="border: none !important;">
                            <a class="nav-link py-1 text-white opacity-75 {{ request()->routeIs('admin.team.leaves') ? 'active fw-bold' : '' }}" href="{{ route('admin.team.leaves') }}">
                                <i class="bi bi-inbox me-2"></i>Pending Leaves
                            </a>
                        </li>
                        <li class="nav-item" style="border: none !important;">
                            <a class="nav-link py-1 text-white opacity-75 {{ request()->routeIs('admin.team.leaves.history') ? 'active fw-bold' : '' }}" href="{{ route('admin.team.leaves.history') }}">
                                <i class="bi bi-clock-history me-2"></i>Leave History
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
        @endif
    @endif
</ul>
