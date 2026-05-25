<ul class="nav flex-column px-2">
    <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('employee.dashboard') ? 'active bg-secondary rounded' : '' }}" href="{{ route('employee.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>My Dashboard</a></li>
</ul>
