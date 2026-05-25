<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'BeetleOps HRMS'))</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @stack('styles')
</head>
<body class="bg-light d-flex flex-column vh-100 overflow-hidden">
    @auth
        @include('layouts.navbar')
    @endauth

    <div class="container-fluid flex-grow-1 overflow-hidden">
        @auth
        <button class="btn btn-dark d-md-none m-2 position-absolute" style="z-index: 1050;" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
            <i class="bi bi-list"></i> Menu
        </button>
        @endauth
        <div class="row h-100">
            @auth
                <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-flex flex-column bg-dark sidebar collapse h-100 pt-3 overflow-y-auto">
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
                                <a class="nav-link text-white {{ request()->routeIs('profile.edit') ? 'active bg-secondary rounded' : '' }}" href="{{ route('profile.edit') }}">
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
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 h-100 overflow-y-auto">
            @else
                <main class="col-12 py-4 h-100 overflow-y-auto">
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
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    @auth
        <script src="{{ asset('js/notifications.js') }}"></script>
    @endauth
    @stack('scripts')
</body>
</html>
