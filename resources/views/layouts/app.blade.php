<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <script>
        // Immediately apply inverted mode if stored in localStorage to prevent flickering
        if (localStorage.getItem('inverted-bw') === 'true') {
            document.documentElement.classList.add('inverted-bw');
        }
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'BeetleOps HRMS'))</title>
    <link rel="icon" type="image/png" href="{{ asset('Logo-circle.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}?v={{ file_exists(public_path('css/custom.css')) ? filemtime(public_path('css/custom.css')) : time() }}">
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

        /* 🎵 Spotify Dark Theme custom switch toggle */
        .custom-switch-dark {
            background-color: rgba(255, 255, 255, 0.08) !important;
            border: 1px solid rgba(255, 255, 255, 0.25) !important;
            cursor: pointer;
        }
        .custom-switch-dark:checked {
            background-color: #1db954 !important; /* Spotify Premium Green */
            border-color: #1db954 !important;
        }
        .custom-switch-dark:focus {
            box-shadow: 0 0 0 0.25rem rgba(29, 185, 84, 0.25) !important;
            border-color: rgba(255, 255, 255, 0.4) !important;
        }

        /* Outlined box for comments history container */
        .chat-history-outline {
            border: 1px solid rgba(255, 255, 255, 0.12) !important;
            background-color: rgba(255, 255, 255, 0.02) !important;
            border-radius: 8px !important;
            padding: 1rem !important;
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
        @if(session('error'))
            <div class="sleek-toast sleek-toast-danger">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span class="small fw-semibold">{{ session('error') }}</span>
                </div>
                <button type="button" class="btn-close ms-3" style="font-size: 0.75rem;" onclick="this.parentElement.remove()"></button>
            </div>
        @endif
        @if(session('warning'))
            <div class="sleek-toast sleek-toast-warning">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span class="small fw-semibold">{{ session('warning') }}</span>
                </div>
                <button type="button" class="btn-close ms-3" style="font-size: 0.75rem;" onclick="this.parentElement.remove()"></button>
            </div>
        @endif
        @if(session('info'))
            <div class="sleek-toast sleek-toast-info">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-info-circle-fill"></i>
                    <span class="small fw-semibold">{{ session('info') }}</span>
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
                        @else
                            @include('layouts.nav-admin')
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
                        <h1 class="h3 mb-0 w-100">{{ $header }}</h1>
                    </div>
                @endisset

                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    </div>

    <!-- Step 1: Cancel Confirmation Modal Popup -->
    <div class="modal fade" id="cancelLeaveModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="background-color: #0c0c0c !important; border: 1px solid rgba(255, 255, 255, 0.08) !important;">
                <div class="modal-body p-4 text-center">
                    <div class="mb-3 text-danger" id="cancelModalIconContainer">
                        <i class="bi bi-x-circle-fill" id="cancelModalIcon" style="font-size: 3.5rem;"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-white d-inline-flex align-items-center justify-content-center gap-1" id="cancelModalTitleContainer" style="line-height: 1.3;">
                        <span id="cancelModalTitle">Cancel Leave Request?</span>
                        <i id="cancelModalTooltipIcon" class="bi bi-info-circle-fill text-warning cursor-pointer d-none" style="font-size: 1.05rem; margin-left: 4px; transform: translateY(1px);" data-custom-tooltip="Because this leave is fully approved and in the past, your cancellation request will be submitted to Super Admin for review." data-tooltip-placement="top"></i>
                    </h5>
                    <p class="text-white opacity-50 small mb-3" id="cancelModalDesc">
                        Are you sure you want to cancel this leave request?
                    </p>
                    <div id="cancelModalAlert" class="d-none"></div>
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 145px;">No, Keep It</button>
                        <button type="button" class="row-btn-red" id="btnProceedToReason" style="width: 175px;">Yes, Cancel Leave</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 2: Cancel Reason Input Modal Popup -->
    <div class="modal fade" id="cancelReasonModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="background-color: #0c0c0c !important; border: 1px solid rgba(255, 255, 255, 0.08) !important;">
                <div class="modal-body p-4 text-center">
                    <div class="mb-3 text-warning">
                        <i class="bi bi-chat-square-text-fill" style="font-size: 3.5rem;"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-white" id="cancelReasonModalTitle">Reason for Cancellation</h5>
                    <p class="text-white opacity-50 small mb-3">
                        Please provide a reason for cancelling this leave request.
                    </p>
                    <div class="text-start mb-3">
                        <textarea id="cancelLeaveReason" class="form-control text-white small" rows="3" placeholder="Type your reason here..." style="background-color: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.15) !important;"></textarea>
                        <div id="cancelReasonError" class="text-danger small mt-1 d-none" style="font-size: 0.78rem;">Please enter a reason for cancellation.</div>
                    </div>
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <button type="button" class="row-btn" id="btnBackToConfirmModal" style="width: 145px;">Back</button>
                        <button type="button" class="row-btn-red" id="confirmCancelBtn" style="width: 175px;">Confirm Cancellation</button>
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

            // Toggle Inverted Black & White Mode when clicking the HRMS navbar heading
            $('#toggle-color-switch').on('click', function() {
                var isCurrentlyInverted = $('html').toggleClass('inverted-bw').hasClass('inverted-bw');
                localStorage.setItem('inverted-bw', isCurrentlyInverted ? 'true' : 'false');
                
                // Dynamically swap the side nav logo source
                var logoUrl = isCurrentlyInverted ? "{{ asset('images/logo-dark.png') }}" : "{{ asset('images/logo.png') }}";
                $('#desktopSidebarToggle').attr('src', logoUrl);
            });

            // ==========================================
            // Global Searchable Select for Employee Dropdowns
            // Uses the same clean Bootstrap dropdown pattern as Attendance/Absents page
            // ==========================================
            function makeSearchableSelect(select) {
                if (select.data('searchable-initialized')) return;
                select.data('searchable-initialized', true);

                const isSmall = select.hasClass('form-select-sm') || select.attr('id') === 'employee-select';
                const inputHeight = isSmall ? '31px' : '37px';
                const borderRadius = select.hasClass('form-select-sm') ? '4px' : (select.css('border-radius') || (isSmall ? '50px' : '6px'));
                const placeholderText = select.find('option:first-child').text() || 'No Head';
                const showSearchIcon = !select.is('[name="manager_id"]') && !select.is('[name="head_id"]');

                select.hide();

                const wrapper = $('<div class="dropdown custom-employee-dropdown position-relative d-inline-block w-100"></div>');
                select.after(wrapper);
                wrapper.append(select);

                const paddingLeft = showSearchIcon ? '38px !important' : '10px !important';
                const input = $('<input type="text" class="form-control text-start text-white border-0 w-100" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-form-type="other" role="combobox" aria-autocomplete="list" aria-expanded="false">')
                    .attr('style', 'height:' + inputHeight + '; border-radius:' + borderRadius + '; font-size:' + (isSmall ? '0.8rem' : '0.9rem') + '; padding-left: ' + paddingLeft + '; padding-right: 24px !important; background-image: none !important; background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;')
                    .attr('placeholder', placeholderText);

                if (showSearchIcon) {
                    const searchIcon = $('<i class="bi bi-search position-absolute text-white-50" style="left: 12px; top: 50%; transform: translateY(-50%); font-size: 0.8rem; pointer-events: none; z-index: 5;"></i>');
                    wrapper.append(searchIcon);
                } else {
                    const chevronIcon = $('<i class="bi bi-chevron-down position-absolute text-white-50" style="right: 8px; top: 50%; transform: translateY(-50%); font-size: 0.65rem; pointer-events: none; z-index: 5;"></i>');
                    wrapper.append(chevronIcon);
                }

                const menu = $('<div class="dropdown-menu dropdown-menu-dark p-2 w-100 shadow border-0 position-absolute" style="background-color: #121212; border: 1px solid rgba(255, 255, 255, 0.12) !important; max-height: 220px; overflow-y: auto; z-index: 1070; top: 100%; left: 0; margin-top: 4px;"></div>');

                wrapper.append(input).append(menu);

                let options = [];

                function syncOptions() {
                    options = [];
                    select.find('option').each(function() {
                        options.push({ value: $(this).val(), text: $.trim($(this).text()) });
                    });
                    updateInputText();
                }

                function updateInputText() {
                    const currentVal = String(select.val() || '');
                    const selOpt = options.find(o => String(o.value) === currentVal);
                    if (selOpt && selOpt.value !== '') {
                        input.val(selOpt.text);
                    } else {
                        input.val('');
                    }
                }

                function tryMatchAndSelect(inputText) {
                    if (!inputText) return false;
                    const q = inputText.toLowerCase().trim();
                    if (!q) return false;

                    let match = options.find(o => o.text.toLowerCase().trim() === q && o.value !== '');
                    if (!match) {
                        match = options.find(o => o.text.toLowerCase().trim().startsWith(q) && o.value !== '');
                    }
                    if (!match && q.length >= 2) {
                        match = options.find(o => o.text.toLowerCase().trim().includes(q) && o.value !== '');
                    }

                    if (match) {
                        if (String(select.val()) !== String(match.value)) {
                            select.val(match.value).trigger('change');
                            if (select[0]) {
                                select[0].dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        }
                        input.val(match.text);
                        return true;
                    }
                    return false;
                }

                function renderMenu(filterText) {
                    menu.empty();
                    const query = (filterText || '').toLowerCase().trim();
                    const filtered = options.filter(o => o.text.toLowerCase().includes(query));

                    if (!filtered.length) {
                        menu.append('<div class="dropdown-item text-white-50 small py-1 px-2" style="cursor: default;">No employees found</div>');
                        return;
                    }

                    const currentVal = String(select.val() || '');

                    filtered.forEach(opt => {
                        const isSelected = String(opt.value) === currentVal;
                        const item = $('<div class="dropdown-item py-1 px-2 rounded small text-white employee-option-row" style="cursor: pointer; transition: background-color 0.15s;"></div>')
                            .text(opt.text)
                            .attr('data-value', opt.value);

                        if (isSelected) {
                            item.css('background-color', 'rgba(59, 130, 246, 0.25)');
                        }

                        item.on('mouseenter', function() {
                            if (String(opt.value) !== String(select.val() || '')) {
                                $(this).css('background-color', 'rgba(255, 255, 255, 0.08)');
                            }
                        }).on('mouseleave', function() {
                            if (String(opt.value) !== String(select.val() || '')) {
                                $(this).css('background-color', 'transparent');
                            }
                        });

                        item.on('mousedown click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            select.val(opt.value).trigger('change');
                            if (select[0]) {
                                select[0].dispatchEvent(new Event('change', { bubbles: true }));
                            }
                            updateInputText();
                            closeDropdowns();
                        });

                        menu.append(item);
                    });
                }

                function closeDropdowns() {
                    $('.custom-employee-dropdown .dropdown-menu').removeClass('show').hide();
                    $('tr.dropdown-active-row').css({ 'position': '', 'z-index': '' }).removeClass('dropdown-active-row');
                    $('td.dropdown-active-cell').css({ 'position': '', 'z-index': '' }).removeClass('dropdown-active-cell');
                    $('.table-responsive.dropdown-active-resp').css({ 'overflow': '' }).removeClass('dropdown-active-resp');
                }

                input.on('focus click', function(e) {
                    e.stopPropagation();
                    syncOptions();
                    renderMenu('');
                    closeDropdowns();
                    menu.addClass('show').show();
                    this.select();

                    const parentTr = wrapper.closest('tr');
                    const parentTd = wrapper.closest('td');
                    const parentResp = wrapper.closest('.table-responsive');

                    if (parentTr.length) {
                        parentTr.addClass('dropdown-active-row').css({ 'position': 'relative', 'z-index': '1065' });
                    }
                    if (parentTd.length) {
                        parentTd.addClass('dropdown-active-cell').css({ 'position': 'relative', 'z-index': '1065' });
                    }
                    if (parentResp.length) {
                        parentResp.addClass('dropdown-active-resp').css({ 'overflow': 'visible' });
                    }

                    // Raise parent card z-index so dropdown floats over content below
                    const parentCard = wrapper.closest('.spotify-card, .card, div[class*="card"]');
                    if (parentCard.length) {
                        parentCard.css('z-index', '1050');
                    }
                });

                input.on('input', function() {
                    renderMenu(input.val());
                    menu.addClass('show').show();
                });

                input.on('change', function() {
                    tryMatchAndSelect(input.val());
                });

                input.on('keydown', function(e) {
                    if (e.key === 'Escape') {
                        closeDropdowns();
                        updateInputText();
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        const firstItem = menu.find('.employee-option-row').first();
                        if (firstItem.length) {
                            firstItem.trigger('click');
                        } else {
                            tryMatchAndSelect(input.val());
                            closeDropdowns();
                        }
                    }
                });

                $(document).on('click.searchableSelect', function(e) {
                    if (!$(e.target).closest(wrapper).length) {
                        const typed = input.val();
                        if (typed && !tryMatchAndSelect(typed)) {
                            updateInputText();
                        } else if (!typed) {
                            updateInputText();
                        }
                        closeDropdowns();
                        const parentCard = wrapper.closest('.spotify-card, .card, div[class*="card"]');
                        if (parentCard.length) {
                            parentCard.css('z-index', '');
                        }
                    }
                });

                syncOptions();
                new MutationObserver(syncOptions).observe(select[0], { childList: true, subtree: true });
                select.on('change', updateInputText);
            }

            // Initialize on target selects
            $('select[name="user_id"], select[name="manager_id"], select#employee-select, select#filter-employee, select.searchable-select').each(function() {
                makeSearchableSelect($(this));
            });
        });
    </script>

    <!-- Real-time Phone Number Input Sanitizer -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const phoneInputs = document.querySelectorAll('input[type="tel"], input[name="phone"], input[name="emergency_number"]');

            phoneInputs.forEach(function (input) {
                // Real-time input/paste filtering
                input.addEventListener('input', function () {
                    let val = this.value;
                    const startsWithPlus = val.startsWith('+');

                    // Allow only digits, spaces, hyphens, and parentheses
                    let sanitized = val.replace(/[^0-9\s\-()]/g, '');

                    // Preserve leading + if present, but remove all other + symbols
                    if (startsWithPlus) {
                        sanitized = '+' + sanitized.replace(/\+/g, '');
                    } else {
                        sanitized = sanitized.replace(/\+/g, '');
                    }

                    if (this.value !== sanitized) {
                        this.value = sanitized;
                    }
                });

                // Prevent invalid keystrokes immediately
                input.addEventListener('keypress', function (e) {
                    // Allow control keys (backspace, enter, arrow keys, Ctrl shortcuts)
                    if (e.ctrlKey || e.metaKey || e.altKey || e.key.length > 1) {
                        return;
                    }

                    // Allow '+' only at the first position
                    if (e.key === '+') {
                        if (this.selectionStart !== 0 || this.value.includes('+')) {
                            e.preventDefault();
                        }
                        return;
                    }

                    // Allow digits, spaces, hyphens, and parentheses
                    if (!/[0-9\s\-()]/.test(e.key)) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
