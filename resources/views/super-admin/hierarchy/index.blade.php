<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <span>System Organization Hierarchy</span>
            <span class="row-badge-green cursor-pointer" id="org-header-active-badge" data-dept-id="all" title="Click to view full organization">
                Whole Organization
            </span>
        </div>
    </x-slot>

    @section('title', 'Organization Hierarchy — BeetleOps HRMS')

    <div class="container-fluid px-0">
        <!-- Filter & Toolbar Card (Separated with High z-index) -->
        <div class="spotify-card p-3 mb-3 d-flex flex-wrap align-items-center justify-content-between gap-3" style="position: relative; z-index: 1050 !important; overflow: visible !important;">
            <!-- Left: Department Dropdown (same as User Management) + Info Tooltip Button -->
            <div class="d-flex align-items-center gap-2" id="dept-filter-controls">
                <!-- Department Dropdown (identical to user management) -->
                <div class="dropdown department-dropdown" style="width: 215px; position: relative; z-index: 1060;">
                    <button class="form-select form-select-sm text-start text-white w-100" 
                            type="button" 
                            id="deptDropdownBtn" 
                            data-bs-toggle="dropdown" 
                            aria-expanded="false"
                            style="border-color: rgba(255, 255, 255, 0.15); background-color: rgba(255, 255, 255, 0.03); border-radius: 4px; height: 36px; font-size: 0.85rem; padding-right: 2rem; color: #ffffff !important;">
                        <span class="text-truncate d-inline-block w-100" id="selected-dept-label" style="max-width: 165px;">Whole Organization</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-dark p-0" aria-labelledby="deptDropdownBtn">
                        <li>
                            <a class="dropdown-item py-2 text-white active org-dept-item" 
                               href="#" 
                               data-dept-id="all" 
                               data-dept-name="Whole Organization">Whole Organization</a>
                        </li>
                        @foreach($departments as $dept)
                            <li>
                                <a class="dropdown-item py-2 text-white org-dept-item" 
                                   href="#" 
                                   data-dept-id="{{ $dept->id }}" 
                                   data-dept-name="{{ $dept->name }}">{{ $dept->name }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <!-- Info / Legend Tooltip Trigger (Clean Icon without background) -->
                <span class="d-inline-flex align-items-center justify-content-center cursor-pointer text-white-50 hover-text-white ms-1" 
                      id="org-legend-info-btn"
                      data-custom-tooltip="🟢 In Office / Active • 🔵 Remote Work&#10;🟡 On Leave • 🔴 Absent&#10;💡 Click any employee node to view details & actions"
                      data-tooltip-placement="right"
                      style="font-size: 1.15rem; transition: color 0.15s ease;">
                    <i class="bi bi-info-circle"></i>
                </span>
            </div>

            <!-- Right: Clean User Management Search + Circular Tool Buttons with Spacing -->
            <div class="d-flex flex-wrap align-items-center gap-3 ms-auto">
                <!-- Search Input (Identical to User Management) -->
                <div class="position-relative" style="width: 240px;">
                    <i class="bi bi-search position-absolute text-white-50" style="left: 14px; top: 50%; transform: translateY(-50%); font-size: 0.9rem;"></i>
                    <input type="text" id="org-search-input" class="form-control text-white" placeholder="Search by name..."
                           style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; font-size: 0.9rem; padding-left: 42px !important; height: 36px; border-radius: 50px !important;">
                    <button type="button" id="org-search-clear" class="btn btn-link position-absolute text-white-50 hover-text-white d-none p-0" style="right: 14px; top: 50%; transform: translateY(-50%); z-index: 5; text-decoration: none;">
                        <i class="bi bi-x-circle-fill" style="font-size: 1rem;"></i>
                    </button>
                </div>

                <!-- Circular Tool Buttons with Clean Spacing -->
                <div class="d-flex align-items-center gap-2">
                    <!-- Layout Mode Toggle (Vertical vs Horizontal) -->
                    <button type="button" class="btn btn-sm org-control-btn" id="btn-layout-vertical" data-custom-tooltip="Vertical Flow (Top-to-Bottom)" data-tooltip-placement="bottom">
                        <i class="bi bi-distribute-vertical"></i>
                    </button>
                    <button type="button" class="btn btn-sm org-control-btn active" id="btn-layout-horizontal" data-custom-tooltip="Horizontal Flow (Left-to-Right)" data-tooltip-placement="bottom">
                        <i class="bi bi-distribute-horizontal"></i>
                    </button>

                    <div class="mx-1" style="height: 30px; width: 1px; background-color: rgba(255, 255, 255, 0.15);"></div>

                    <!-- Expand / Collapse All -->
                    <button type="button" class="btn btn-sm org-control-btn" id="btn-expand-all" data-custom-tooltip="Expand All Branches" data-tooltip-placement="bottom">
                        <i class="bi bi-arrows-expand"></i>
                    </button>
                    <button type="button" class="btn btn-sm org-control-btn" id="btn-collapse-all" data-custom-tooltip="Collapse Sub-branches" data-tooltip-placement="bottom">
                        <i class="bi bi-arrows-collapse"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Chart Card (Hierarchy Viewport with Native Scroll) -->
        <div class="spotify-card p-0 mb-4 overflow-hidden" id="hierarchy-main-card" style="border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; position: relative; z-index: 1;">

            <!-- Org Chart Viewport with Native Scrollbars -->
            <div id="org-viewport" 
                 class="position-relative" 
                 data-tree-url="{{ route('super-admin.hierarchy.data') }}"
                 data-initial-dept="all"
                 style="min-height: 560px; height: 72vh; max-height: 820px; width: 100%; overflow: auto; background-color: #080808; background-image: radial-gradient(rgba(255, 255, 255, 0.07) 1px, transparent 1px); background-size: 24px 24px;">
                <!-- Loading Overlay -->
                <div id="org-loader" class="position-absolute top-50 start-50 translate-middle text-center" style="z-index: 50;">
                    <div class="spinner-border text-success" role="status" style="width: 2.8rem; height: 2.8rem;">
                        <span class="visually-hidden">Loading Organization Tree...</span>
                    </div>
                    <p class="text-white opacity-75 small mt-2 mb-0">Building Organization Tree...</p>
                </div>

                <!-- Empty State -->
                <div id="org-empty" class="position-absolute top-50 start-50 translate-middle text-center d-none" style="z-index: 50;">
                    <div class="rounded-circle d-inline-flex p-3 mb-2" style="background: rgba(255, 255, 255, 0.05);">
                        <i class="bi bi-diagram-2 text-white opacity-50" style="font-size: 2.5rem;"></i>
                    </div>
                    <h5 class="text-white fw-bold mb-1">No Hierarchy Nodes Found</h5>
                    <p class="text-white opacity-50 small mb-0">No active employees found matching the selected department.</p>
                </div>

                <!-- Native Canvas Container (Expands naturally to fit content) -->
                <div id="org-canvas" class="position-relative" style="min-width: 100%; min-height: 100%; display: inline-block;">
                    <!-- SVG Connector Lines Layer -->
                    <svg id="org-svg-layer" class="position-absolute top-0 start-0" style="pointer-events: none; overflow: visible; z-index: 1;">
                        <defs>
                            <linearGradient id="lineGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#10b981" stop-opacity="0.6"/>
                                <stop offset="100%" stop-color="#059669" stop-opacity="0.3"/>
                            </linearGradient>
                            <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
                                <feGaussianBlur stdDeviation="2" result="blur" />
                                <feComposite in="SourceGraphic" in2="blur" operator="over" />
                            </filter>
                        </defs>
                        <g id="org-lines-group"></g>
                    </svg>

                    <!-- HTML Nodes Layer -->
                    <div id="org-nodes-layer" class="position-relative" style="z-index: 2;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Node Detail Modal (Centered Profile Card matching Edit User Profile) -->
    <div id="orgNodeModalBackdrop" class="org-modal-backdrop d-none">
        <div class="org-modal-dialog" style="max-width: 440px;">
            <div class="org-modal-content spotify-card p-0 position-relative" style="background-color: #121212 !important; border: 1px solid rgba(255, 255, 255, 0.12) !important; border-radius: 18px !important;">

                <!-- Avatar & Header Info (Centered like edit user profile card) -->
                <div class="d-flex flex-column align-items-center text-center p-4 pb-3">
                    <div class="d-flex justify-content-center mb-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" 
                             id="modal-avatar"
                             style="width: 84px; height: 84px; min-width: 84px; min-height: 84px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); font-size: 1.85rem; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.25);">
                            JD
                        </div>
                    </div>

                    <h4 id="modal-name" class="fw-bold text-white mb-1">Jane Doe</h4>
                    <div class="d-flex align-items-center justify-content-center gap-2 mt-1">
                        <span id="modal-status-badge" class="badge px-3 py-1 rounded-pill small" 
                              style="background-color: rgba(30, 215, 96, 0.15); border: 1px solid rgba(30, 215, 96, 0.4); color: #1ed760; font-size: 0.75rem;">
                            In Office
                        </span>
                    </div>
                </div>

                <!-- Profile Field Rows (Matching profile-field-row in edit.blade.php) -->
                <div class="px-4 pb-3">
                    <div class="border-top border-secondary border-opacity-25 pt-2 text-start">
                        <div class="d-flex justify-content-between align-items-center profile-field-row">
                            <span class="text-white opacity-50 small">Department</span>
                            <span id="modal-dept" class="text-white fw-semibold small text-end">Engineering</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center profile-field-row">
                            <span class="text-white opacity-50 small">Designation</span>
                            <span id="modal-designation" class="text-white fw-semibold small text-end">Senior Full Stack Developer</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center profile-field-row">
                            <span class="text-white opacity-50 small">Reporting Line Manager</span>
                            <span id="modal-manager" class="text-white fw-semibold small text-end">CEO</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center profile-field-row">
                            <span class="text-white opacity-50 small">Email Address</span>
                            <span id="modal-email" class="text-white fw-semibold small text-break text-end" style="max-width: 65%;">jane@beetleops.com</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center profile-field-row">
                            <span class="text-white opacity-50 small">Phone Number</span>
                            <span id="modal-phone" class="text-white fw-semibold small text-end">+1 234 567 890</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center profile-field-row">
                            <span class="text-white opacity-50 small">Joined On & Tenure</span>
                            <span id="modal-tenure" class="text-white fw-semibold small text-end">2 yrs 4 mos</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center profile-field-row">
                            <span class="text-white opacity-50 small">Command Scope</span>
                            <span id="modal-reports" class="text-white fw-semibold small text-end">4 Direct • 12 Total</span>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer: Circular Icon Buttons on Left + Close on Right -->
                <div class="p-3 px-4 border-top border-secondary border-opacity-25 d-flex align-items-center justify-content-between" style="background: rgba(0, 0, 0, 0.2);">
                    <div class="d-flex align-items-center gap-2">
                        <!-- 1. Edit User Profile -->
                        <a href="#" id="modal-edit-btn" class="org-modal-circle-btn btn-edit" data-custom-tooltip="Edit User Profile" data-tooltip-placement="top">
                            <i class="bi bi-pencil-square"></i>
                        </a>

                        <!-- 2. View Track Record / Track Sheet -->
                        <a href="#" id="modal-track-btn" class="org-modal-circle-btn btn-track" data-custom-tooltip="View Track Sheet" data-tooltip-placement="top">
                            <i class="bi bi-calendar-check"></i>
                        </a>

                        <!-- 3. View Attendance Logs -->
                        <a href="#" id="modal-logs-btn" class="org-modal-circle-btn btn-logs" data-custom-tooltip="View Attendance Logs" data-tooltip-placement="top">
                            <i class="bi bi-clock-history"></i>
                        </a>
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-secondary px-4 rounded-pill text-white" id="modal-cancel-btn">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Custom Styles for Hierarchy View -->
    <style>
        /* Custom Scrollbar for Hierarchy Viewport */
        #org-viewport::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        #org-viewport::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.3);
        }
        #org-viewport::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 4px;
        }
        #org-viewport::-webkit-scrollbar-thumb:hover {
            background: rgba(30, 215, 96, 0.5);
        }

        .org-dept-pill {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.7);
            border-radius: 20px;
            padding: 5px 14px;
            font-size: 0.78rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .org-dept-pill:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.2);
        }
        .org-dept-pill.active {
            background: #1ed760 !important;
            color: #000000 !important;
            font-weight: 600 !important;
            border-color: #1ed760 !important;
        }
        .org-dept-pill.active .org-pill-count {
            background: rgba(0, 0, 0, 0.2) !important;
            color: #000000 !important;
        }
        /* Department Dropdown Styling (Matching User Management) */
        .department-dropdown .dropdown-menu {
            max-height: 200px !important;
            overflow-y: auto !important;
            width: 215px !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            background-color: #121212 !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5) !important;
            z-index: 1070 !important;
        }
        .department-dropdown .dropdown-item {
            white-space: normal !important;
            word-wrap: break-word !important;
            color: #ffffff !important;
            font-size: 0.8rem !important;
        }
        .department-dropdown .dropdown-item:hover,
        .department-dropdown .dropdown-item.active {
            background-color: rgba(255, 255, 255, 0.1) !important;
            color: #ffffff !important;
        }

        /* Circular Toolbar Control Buttons */
        .org-control-btn {
            width: 36px !important;
            height: 36px !important;
            min-width: 36px !important;
            min-height: 36px !important;
            border-radius: 50% !important;
            padding: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            transition: all 0.15s ease;
        }
        .org-control-btn:hover {
            background: rgba(255, 255, 255, 0.14);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.25);
            transform: scale(1.05);
        }
        .org-control-btn.active {
            background: rgba(30, 215, 96, 0.18) !important;
            color: #1ed760 !important;
            border-color: rgba(30, 215, 96, 0.5) !important;
        }

        /* Dynamic Org Node Card with Silky Smooth Transitions */
        .org-node-card {
            position: absolute;
            width: 230px;
            min-height: 86px;
            height: 86px;
            background: rgba(18, 18, 18, 0.92);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            padding: 10px 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            cursor: pointer;
            transition: left 0.38s cubic-bezier(0.16, 1, 0.3, 1), 
                        top 0.38s cubic-bezier(0.16, 1, 0.3, 1), 
                        opacity 0.32s cubic-bezier(0.16, 1, 0.3, 1), 
                        transform 0.38s cubic-bezier(0.16, 1, 0.3, 1), 
                        border-color 0.2s ease, 
                        box-shadow 0.2s ease;
            z-index: 10;
            display: flex;
            align-items: center;
            opacity: 1;
            transform: scale(1);
            will-change: left, top, opacity, transform;
        }
        .org-node-card.node-entering {
            opacity: 0 !important;
            transform: scale(0.65) !important;
            pointer-events: none !important;
        }
        .org-node-card.node-exiting {
            opacity: 0 !important;
            transform: scale(0.65) !important;
            pointer-events: none !important;
            z-index: 5 !important;
        }
        .org-node-card:hover {
            border-color: #1ed760 !important;
            box-shadow: 0 10px 30px rgba(30, 215, 96, 0.25) !important;
            transform: translateY(-2px) scale(1.02);
            z-index: 25 !important;
        }
        .org-node-card.highlighted {
            border-color: #1ed760 !important;
            box-shadow: 0 0 0 3px rgba(30, 215, 96, 0.45), 0 12px 32px rgba(30, 215, 96, 0.3) !important;
            animation: pulseGlow 2s infinite;
            z-index: 30 !important;
        }
        .org-node-card.self-node-highlight {
            border-color: #10b981 !important;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.5), 0 8px 24px rgba(0, 0, 0, 0.5) !important;
        }
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 0 3px rgba(30, 215, 96, 0.45), 0 12px 32px rgba(30, 215, 96, 0.3); }
            50% { box-shadow: 0 0 0 6px rgba(30, 215, 96, 0.7), 0 16px 40px rgba(30, 215, 96, 0.5); }
        }
        .org-node-avatar {
            position: relative;
            width: 48px;
            height: 48px;
            min-width: 48px;
            min-height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            font-weight: 700;
            font-size: 1.15rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
            flex-shrink: 0;
        }
        .org-status-indicator {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid #121212;
        }
        .org-node-name {
            color: #ffffff;
            font-weight: 700;
            font-size: 0.86rem;
            line-height: 1.18;
            margin-bottom: 2px;
            word-break: break-word;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .org-node-title {
            color: rgba(255, 255, 255, 0.65);
            font-size: 0.74rem;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .org-node-dept-badge {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.75);
            font-size: 0.65rem;
            padding: 2px 7px;
            border-radius: 10px;
            font-weight: 500;
        }
        .org-node-collapse-btn {
            position: absolute;
            bottom: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: #181818;
            border: 1px solid rgba(255, 255, 255, 0.22);
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            cursor: pointer;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.6);
            transition: all 0.15s ease;
            z-index: 15;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .org-node-collapse-btn:hover {
            background: #1ed760;
            color: #000000;
            border-color: #1ed760;
            transform: translateX(-50%) scale(1.08);
        }
        .org-node-collapse-btn.collapsed {
            background: rgba(30, 215, 96, 0.18);
            border-color: #1ed760;
            color: #1ed760;
        }
        .org-node-collapse-btn.collapsed:hover {
            background: #1ed760;
            color: #000000;
        }

        /* Horizontal Mode specifics */
        .org-canvas-horizontal .org-node-collapse-btn {
            bottom: auto;
            left: auto;
            top: 50%;
            right: -13px;
            transform: translateY(-50%);
        }
        .org-canvas-horizontal .org-node-collapse-btn:hover {
            transform: translateY(-50%) scale(1.08);
        }

        /* SVG Line styling */
        .org-connector-path {
            fill: none;
            stroke: rgba(255, 255, 255, 0.2);
            stroke-width: 2;
            transition: stroke 0.2s ease, stroke-width 0.2s ease;
        }
        .org-connector-path.highlighted-path {
            stroke: #1ed760;
            stroke-width: 3;
            filter: drop-shadow(0 0 6px rgba(30, 215, 96, 0.6));
        }

        /* Centered Backdrop-Blurred Detail Modal */
        .org-modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.78);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .org-modal-backdrop.active {
            opacity: 1;
            visibility: visible;
        }
        .org-modal-dialog {
            width: 100%;
            max-width: 520px;
            transform: scale(0.92);
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .org-modal-backdrop.active .org-modal-dialog {
            transform: scale(1);
        }
        .org-modal-content {
            background: #121212;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.9);
            overflow: hidden;
        }
        .org-modal-avatar {
            width: 52px;
            height: 52px;
            min-width: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-weight: 700;
            font-size: 1.25rem;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.5);
        }

        .profile-field-row {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
            padding-top: 8px;
            padding-bottom: 8px;
        }
        .profile-field-row:last-child {
            border-bottom: none !important;
        }

        /* Modal Footer Action Circle Buttons */
        .org-modal-circle-btn {
            width: 36px !important;
            height: 36px !important;
            min-width: 36px !important;
            min-height: 36px !important;
            border-radius: 50% !important;
            padding: 0 !important;
            display: inline-flex;
            align-items: center !important;
            justify-content: center !important;
            font-size: 0.95rem;
            text-decoration: none !important;
            transition: all 0.15s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
        }
        .org-modal-circle-btn.d-none {
            display: none !important;
        }
        .org-modal-circle-btn.btn-edit {
            background: rgba(30, 215, 96, 0.15);
            border: 1px solid rgba(30, 215, 96, 0.4);
            color: #1ed760;
        }
        .org-modal-circle-btn.btn-edit:hover {
            background: #1ed760;
            color: #000000;
            border-color: #1ed760;
            transform: scale(1.1);
        }
        .org-modal-circle-btn.btn-track {
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.4);
            color: #3b82f6;
        }
        .org-modal-circle-btn.btn-track:hover {
            background: #3b82f6;
            color: #ffffff;
            border-color: #3b82f6;
            transform: scale(1.1);
        }
        .org-modal-circle-btn.btn-logs {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.4);
            color: #f59e0b;
        }
        .org-modal-circle-btn.btn-logs:hover {
            background: #f59e0b;
            color: #000000;
            border-color: #f59e0b;
            transform: scale(1.1);
        }

        /* Modal Close and Cancel Buttons Outline Fix */
        #modal-close-btn {
            background: transparent !important;
            border: 1px solid transparent !important;
            border-radius: 50% !important;
            outline: none !important;
            box-shadow: none !important;
            color: rgba(255, 255, 255, 0.6) !important;
            transition: all 0.15s ease;
        }
        #modal-close-btn:hover {
            background: rgba(255, 255, 255, 0.1) !important;
            color: #ffffff !important;
        }
        #modal-close-btn:focus,
        #modal-close-btn:focus-visible,
        #modal-close-btn:active {
            outline: none !important;
            box-shadow: none !important;
            background: rgba(255, 255, 255, 0.15) !important;
            color: #ffffff !important;
        }

        #modal-cancel-btn {
            background: transparent !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            color: rgba(255, 255, 255, 0.85) !important;
            outline: none !important;
            box-shadow: none !important;
            transition: all 0.15s ease;
        }
        #modal-cancel-btn:hover {
            background: rgba(255, 255, 255, 0.1) !important;
            border-color: rgba(255, 255, 255, 0.4) !important;
            color: #ffffff !important;
        }
        #modal-cancel-btn:focus,
        #modal-cancel-btn:focus-visible,
        #modal-cancel-btn:active {
            outline: none !important;
            box-shadow: none !important;
            background: rgba(255, 255, 255, 0.12) !important;
            border-color: rgba(255, 255, 255, 0.5) !important;
            color: #ffffff !important;
        }
    </style>

    @push('scripts')
        <script src="{{ asset('js/org-hierarchy.js') }}?v={{ time() }}"></script>
    @endpush
</x-app-layout>
