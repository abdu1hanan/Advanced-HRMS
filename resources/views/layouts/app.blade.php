<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'BeetleOps HRMS'))</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    @stack('styles')
</head>
<body class="d-flex flex-column vh-100 overflow-hidden">
    @auth
        @include('layouts.navbar')
    @endauth

    <div class="container-fluid flex-grow-1 overflow-hidden">
        <div class="row h-100">
            @auth
                <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-flex flex-column sidebar collapse h-100 pt-3 overflow-y-auto">
                    <div class="collapsed-vertical-brand">BeetleOps</div>
                    <div class="flex-grow-1">
                        @if(auth()->user()->isSuperAdmin())
                            @include('layouts.nav-super-admin')
                        @elseif(auth()->user()->isAdmin())
                            @include('layouts.nav-admin')
                        @else
                            @include('layouts.nav-employee')
                        @endif
                    </div>
                    
                    <div class="mt-auto pb-4 px-2">
                        <ul class="nav flex-column border-top border-secondary pt-2">
                            <li class="nav-item">
                                <a class="nav-link text-white {{ request()->routeIs('profile.edit') ? 'active' : '' }}" href="{{ route('profile.edit') }}">
                                    <i class="bi bi-person-circle me-2"></i>Profile
                                </a>
                            </li>
                            <li class="nav-item mt-1">
                                <form method="POST" action="{{ route('logout') }}" id="sidebar-logout-form">
                                    @csrf
                                    <a class="nav-link text-white" href="#" onclick="event.preventDefault(); document.getElementById('sidebar-logout-form').submit();">
                                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                                    </a>
                                </form>
                            </li>
                        </ul>
                    </div>
                </nav>
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 h-100 overflow-y-auto animated-page main-content-area">
            @else
                <main class="col-12 py-4 h-100 overflow-y-auto animated-page main-content-area">
            @endauth

                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @isset($header)
                    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                        <h1 class="h3 mb-0">{{ $header }}</h1>
                    </div>
                @endisset

                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    </div>

    <!-- Beautiful Cancel Confirmation Modal Popup -->
    <div class="modal fade" id="cancelLeaveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-body p-4 text-center">
                    <div class="mb-3 text-danger">
                        <i class="bi bi-x-circle-fill" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-dark">Are you sure you want to cancel this leave request?</h5>
                    <p class="text-muted mb-0">
                        This action will cancel your pending leave request and return your leave balance immediately.
                    </p>
                </div>
                <div class="modal-footer bg-light border-0 py-3 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">No, Keep It</button>
                    <button type="button" class="btn btn-danger px-4 shadow-sm" id="confirmCancelBtn">Yes, Cancel Request</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Beautiful Cancel Success Modal Popup -->
    <div class="modal fade" id="cancelSuccessModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-body p-4 text-center">
                    <div class="mb-3 text-success">
                        <i class="bi bi-check-circle-fill" style="font-size: 3.5rem;"></i>
                    </div>
                    <h4 class="fw-bold mb-2 text-dark">Cancelled Successfully</h4>
                    <p class="text-muted mb-3" id="cancelSuccessMsg"></p>
                    <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    @auth
        <script src="{{ asset('js/notifications.js') }}"></script>
    @endauth
    <script>
        $(document).ready(function() {
            $('#desktopSidebarToggle').on('click', function() {
                var sidebar = $('#sidebarMenu');
                var main = $('main');
                var icon = $('#toggleIcon');

                sidebar.toggleClass('collapsed');
                main.toggleClass('expanded');

                if (sidebar.hasClass('collapsed')) {
                    icon.removeClass('bi-chevron-left').addClass('bi-chevron-right');
                    // Remove standard column classes on collapse to allow custom width transitions
                    sidebar.removeClass('col-md-3 col-lg-2').css('width', '60px');
                    main.removeClass('col-md-9 col-lg-10').css('width', 'calc(100% - 60px)');
                } else {
                    icon.removeClass('bi-chevron-right').addClass('bi-chevron-left');
                    // Restore standard bootstrap grid columns
                    sidebar.addClass('col-md-3 col-lg-2').css('width', '');
                    main.addClass('col-md-9 col-lg-10').css('width', '');
                }
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
