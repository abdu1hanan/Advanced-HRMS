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
    <style>
        .custom-sidebar-tooltip {
            position: absolute;
            background-color: #1a1a1a;
            color: #ffffff;
            font-size: 0.76rem;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            pointer-events: none;
            opacity: 0;
            transform: scale(0.92);
            transition: opacity 0.08s ease-out, transform 0.08s ease-out;
            z-index: 9999;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.55);
            white-space: pre-wrap;
            max-width: 450px;
        }
        .custom-sidebar-tooltip.show {
            opacity: 1;
            transform: scale(1);
        }
        .custom-sidebar-tooltip.arrow-left::before {
            content: '';
            position: absolute;
            left: -5px;
            top: 50%;
            transform: translateY(-50%) rotate(45deg);
            width: 8px;
            height: 8px;
            background-color: #1a1a1a;
            border-left: 1px solid rgba(255, 255, 255, 0.12);
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }
        .custom-sidebar-tooltip.arrow-bottom::before {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%) rotate(45deg);
            width: 8px;
            height: 8px;
            background-color: #1a1a1a;
            border-right: 1px solid rgba(255, 255, 255, 0.12);
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }
        .custom-sidebar-tooltip.arrow-right::before {
            content: '';
            position: absolute;
            right: -5px;
            top: 50%;
            transform: translateY(-50%) rotate(45deg);
            width: 8px;
            height: 8px;
            background-color: #1a1a1a;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            border-right: 1px solid rgba(255, 255, 255, 0.12);
        }
        .custom-sidebar-tooltip.arrow-top::before {
            content: '';
            position: absolute;
            top: -5px;
            left: 50%;
            transform: translateX(-50%) rotate(45deg);
            width: 8px;
            height: 8px;
            background-color: #1a1a1a;
            border-left: 1px solid rgba(255, 255, 255, 0.12);
            border-top: 1px solid rgba(255, 255, 255, 0.12);
        }
    </style>
    @stack('styles')
</head>
<body class="d-flex flex-column vh-100 overflow-hidden">
    @auth
        @include('layouts.navbar')
    @endauth

    <!-- Sleek floating top-centered popup alerts (standard for entire system) -->
    <div class="sleek-toast-container">
        @if(session('success'))
            <div class="sleek-toast sleek-toast-success">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill"></i>
                    <span class="small fw-semibold">{{ session('success') }}</span>
                </div>
                <button type="button" class="btn-close ms-3" style="font-size: 0.75rem;" onclick="this.parentElement.remove()"></button>
            </div>
        @endif
        @if($errors->any())
            @foreach($errors->all() as $error)
                <div class="sleek-toast sleek-toast-danger">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <span class="small fw-semibold">{{ $error }}</span>
                    </div>
                    <button type="button" class="btn-close ms-3" style="font-size: 0.75rem;" onclick="this.parentElement.remove()"></button>
                </div>
            @endforeach
        @endif
    </div>

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
                            <li class="nav-item mt-1 logout-item">
                                <form method="POST" action="{{ route('logout') }}" id="sidebar-logout-form">
                                    @csrf
                                    <a class="nav-link text-white d-flex align-items-center" href="#" onclick="event.preventDefault(); document.getElementById('sidebar-logout-form').submit();">
                                        <i class="bi bi-box-arrow-right me-2 logout-icon" data-custom-tooltip="Logout" data-tooltip-placement="right"></i><span class="logout-text">Logout</span>
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
    <div class="modal fade" id="cancelLeaveModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="background-color: #0c0c0c !important; border: 1px solid rgba(255, 255, 255, 0.08) !important;">
                <div class="modal-body p-4 text-center">
                    <div class="mb-3 text-danger">
                        <i class="bi bi-x-circle-fill" style="font-size: 3.5rem;"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-white">Are you sure you want to cancel this leave request?</h5>
                    <p class="text-white opacity-50 small mb-4">
                        This action will cancel your pending leave request and return your leave balance immediately.
                    </p>
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 145px;">No, Keep It</button>
                        <button type="button" class="row-btn-red" id="confirmCancelBtn" style="width: 175px;">Yes, Cancel Request</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Beautiful Cancel Success Modal Popup -->
    <div class="modal fade" id="cancelSuccessModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="background-color: #212529 !important;">
                <div class="modal-body p-4 text-center">
                    <div class="mb-3 text-success">
                        <i class="bi bi-check-circle-fill" style="font-size: 3.5rem;"></i>
                    </div>
                    <h4 class="fw-bold mb-2 text-white">Cancelled Successfully</h4>
                    <p class="text-white opacity-50 small mb-4" id="cancelSuccessMsg"></p>
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <button type="button" class="row-btn-green" data-bs-dismiss="modal" style="width: 145px;">OK</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @stack('modals')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    @auth
        <script src="{{ asset('js/notifications.js') }}"></script>
    @endauth
    <script>
        $(document).ready(function() {
            var sidebar = $('#sidebarMenu');

            // Custom Tooltip Implementation
            var customTooltip = $('<div class="custom-sidebar-tooltip"></div>').appendTo('body');

            function showTooltip(target, text, placement) {
                if (!text) return;
                customTooltip.text(text);
                
                var offset = target.offset();
                var targetWidth = target.outerWidth();
                var targetHeight = target.outerHeight();
                
                // Temporarily show to calculate outer width/height correctly
                customTooltip.css({ top: '-9999px', left: '-9999px' }).addClass('show');
                var tooltipWidth = customTooltip.outerWidth();
                var tooltipHeight = customTooltip.outerHeight();
                customTooltip.removeClass('show');

                customTooltip.removeClass('arrow-left arrow-bottom arrow-right arrow-top');

                var top, left;
                if (placement === 'right') {
                    top = offset.top + (targetHeight - tooltipHeight) / 2;
                    left = offset.left + targetWidth + 10;
                    customTooltip.addClass('arrow-left');
                } else if (placement === 'top') {
                    top = offset.top - tooltipHeight - 10;
                    left = offset.left + (targetWidth - tooltipWidth) / 2;
                    customTooltip.addClass('arrow-bottom');
                } else if (placement === 'left') {
                    top = offset.top + (targetHeight - tooltipHeight) / 2;
                    left = offset.left - tooltipWidth - 10;
                    customTooltip.addClass('arrow-right');
                } else if (placement === 'bottom') {
                    top = offset.top + targetHeight + 10;
                    left = offset.left + (targetWidth - tooltipWidth) / 2;
                    customTooltip.addClass('arrow-top');
                }
                
                customTooltip.css({
                    top: top + 'px',
                    left: left + 'px'
                }).addClass('show');
            }

            function hideTooltip() {
                customTooltip.removeClass('show arrow-left arrow-bottom arrow-right arrow-top');
            }

            function updateSidebarTooltips() {
                if (sidebar.hasClass('collapsed')) {
                    $('#desktopSidebarToggle').attr('data-custom-tooltip', 'Open sidebar');
                } else {
                    $('#desktopSidebarToggle').attr('data-custom-tooltip', 'Close sidebar');
                }
            }

            updateSidebarTooltips();

            // Hover trigger listeners for data-custom-tooltip
            $(document).on('mouseenter', '[data-custom-tooltip]', function() {
                var target = $(this);
                // For logout icon, only show tooltip if the sidebar is closed
                if (target.hasClass('logout-icon') && !sidebar.hasClass('collapsed')) {
                    return;
                }
                var text = target.attr('data-custom-tooltip');
                var placement = target.data('tooltip-placement') || 'right';
                showTooltip(target, text, placement);
            });

            $(document).on('mouseleave click', '[data-custom-tooltip]', function() {
                hideTooltip();
            });

            $('#desktopSidebarToggle').on('click', function() {
                var main = $('main');

                sidebar.toggleClass('collapsed');
                main.toggleClass('expanded');

                if (sidebar.hasClass('collapsed')) {
                    // Remove standard column classes on collapse to allow custom width transitions
                    sidebar.removeClass('col-md-3 col-lg-2').css('width', '85px');
                    main.removeClass('col-md-9 col-lg-10').css('width', 'calc(100% - 85px)');
                } else {
                    // Restore standard bootstrap grid columns
                    sidebar.addClass('col-md-3 col-lg-2').css('width', '');
                    main.addClass('col-md-9 col-lg-10').css('width', '');
                }

                updateSidebarTooltips();
            });

            // Automatically slide up and dismiss sleek toast notification bars after 4 seconds
            setTimeout(function() {
                $('.sleek-toast').animate({ opacity: 0, transform: 'translate(-50%, -20px)' }, 400, function() {
                    $(this).slideUp(300, function() {
                        $(this).remove();
                    });
                });
            }, 4500);
        });
    </script>
    @stack('scripts')
</body>
</html>
