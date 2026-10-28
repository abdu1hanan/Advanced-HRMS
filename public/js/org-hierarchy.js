/**
 * 🌟 BeetleOps HRMS - Dynamic Organization Hierarchy Engine
 * Native scrollable hierarchy view with vertical/horizontal branching,
 * branch collapse/expand, live search, and centered backdrop-blurred detail modal on click.
 */

(function () {
    'use strict';

    // Node & Layout Constants
    const NODE_WIDTH = 230;
    const NODE_HEIGHT = 86;
    const VERTICAL_H_GAP = 36;
    const VERTICAL_V_GAP = 68;
    const HORIZONTAL_H_GAP = 70;
    const HORIZONTAL_V_GAP = 28;

    // State
    let treeRoots = [];
    let collapsedNodes = new Set();
    let layoutMode = 'horizontal'; // 'vertical' or 'horizontal' (horizontal default)
    let currentDeptId = 'all';
    let searchQuery = '';

    // DOM Elements
    const viewport = document.getElementById('org-viewport');
    const canvas = document.getElementById('org-canvas');
    const nodesLayer = document.getElementById('org-nodes-layer');
    const svgLayer = document.getElementById('org-svg-layer');
    const linesGroup = document.getElementById('org-lines-group');
    const loader = document.getElementById('org-loader');
    const emptyState = document.getElementById('org-empty');
    const searchInput = document.getElementById('org-search-input');
    const searchClear = document.getElementById('org-search-clear');
    const matchesCount = document.getElementById('search-matches-count');
    const modalBackdrop = document.getElementById('orgNodeModalBackdrop');

    // Color helpers for avatars based on name hash
    const gradientThemes = [
        'linear-gradient(135deg, #10b981 0%, #059669 100%)', // Emerald
        'linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%)', // Blue
        'linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%)', // Purple
        'linear-gradient(135deg, #ec4899 0%, #be185d 100%)', // Pink
        'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)', // Amber
        'linear-gradient(135deg, #06b6d4 0%, #0e7490 100%)', // Cyan
        'linear-gradient(135deg, #14b8a6 0%, #0f766e 100%)', // Teal
    ];

    function getAvatarGradient(str) {
        if (!str) return gradientThemes[0];
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        const index = Math.abs(hash) % gradientThemes.length;
        return gradientThemes[index];
    }

    // Dynamic Config from Viewport Attributes
    const treeEndpoint = viewport ? (viewport.getAttribute('data-tree-url') || '/super-admin/hierarchy/data') : '/super-admin/hierarchy/data';
    const initialDept = viewport ? (viewport.getAttribute('data-initial-dept') || 'all') : 'all';
    const currentUserId = viewport ? viewport.getAttribute('data-current-user-id') : null;

    // -------------------------------------------------------------
    // 1. Data Fetching
    // -------------------------------------------------------------
    function loadHierarchy(deptId = currentDeptId) {
        currentDeptId = deptId;
        if (loader) loader.classList.remove('d-none');
        if (emptyState) emptyState.classList.add('d-none');

        const url = `${treeEndpoint}?department_id=${encodeURIComponent(deptId)}`;

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
            .then(res => {
                if (!res.ok) throw new Error('Network error loading hierarchy');
                return res.json();
            })
            .then(data => {
                if (loader) loader.classList.add('d-none');
                treeRoots = data.roots || [];

                if (treeRoots.length === 0) {
                    if (emptyState) emptyState.classList.remove('d-none');
                    clearRender();
                    return;
                }

                renderTree();
                centerScroll();
            })
            .catch(err => {
                console.error('Error fetching hierarchy data:', err);
                if (loader) loader.classList.add('d-none');
                if (emptyState) emptyState.classList.remove('d-none');
            });
    }

    // -------------------------------------------------------------
    // 2. Tree Layout Algorithm (Dynamic Branching)
    // -------------------------------------------------------------
    function calculateLayoutVertical(roots) {
        const layoutNodes = [];
        const layoutLinks = [];

        // Measure recursive tree bounds
        function measure(node) {
            const hasVisibleChildren = node.children && node.children.length > 0 && !collapsedNodes.has(node.id);
            if (!hasVisibleChildren) {
                node._treeWidth = NODE_WIDTH;
                node._treeHeight = NODE_HEIGHT;
                return;
            }

            let totalChildWidth = 0;
            node.children.forEach((child, i) => {
                measure(child);
                totalChildWidth += child._treeWidth;
                if (i > 0) totalChildWidth += VERTICAL_H_GAP;
            });

            node._treeWidth = Math.max(NODE_WIDTH, totalChildWidth);
            node._treeHeight = NODE_HEIGHT;
        }

        // Position nodes top-to-bottom
        function position(node, xOffset, depth) {
            const y = 40 + depth * (NODE_HEIGHT + VERTICAL_V_GAP);
            const hasVisibleChildren = node.children && node.children.length > 0 && !collapsedNodes.has(node.id);

            let nodeX;
            if (!hasVisibleChildren) {
                nodeX = xOffset;
            } else {
                let currentChildX = xOffset;
                const childMidpoints = [];

                node.children.forEach((child, i) => {
                    if (i > 0) currentChildX += VERTICAL_H_GAP;
                    position(child, currentChildX, depth + 1);
                    childMidpoints.push(child._x + NODE_WIDTH / 2);
                    currentChildX += child._treeWidth;
                });

                // Center parent directly between the leftmost and rightmost children
                const firstChildMid = childMidpoints[0];
                const lastChildMid = childMidpoints[childMidpoints.length - 1];
                const midPoint = (firstChildMid + lastChildMid) / 2;
                nodeX = midPoint - NODE_WIDTH / 2;
            }

            node._x = nodeX;
            node._y = y;

            layoutNodes.push(node);

            // Connect curves if children are visible
            if (hasVisibleChildren) {
                node.children.forEach(child => {
                    layoutLinks.push({
                        from: node,
                        to: child,
                        x1: node._x + NODE_WIDTH / 2,
                        y1: node._y + NODE_HEIGHT,
                        x2: child._x + NODE_WIDTH / 2,
                        y2: child._y
                    });
                });
            }
        }

        let totalSpan = 0;
        roots.forEach((root, i) => {
            measure(root);
            totalSpan += root._treeWidth + (i > 0 ? VERTICAL_H_GAP * 2 : 0);
        });

        const vpWidth = viewport ? viewport.clientWidth : 1000;
        const startX = Math.max(40, (vpWidth - totalSpan) / 2);
        let curX = startX;

        roots.forEach((root, i) => {
            if (i > 0) curX += VERTICAL_H_GAP * 2;
            position(root, curX, 0);
            curX += root._treeWidth;
        });

        return { nodes: layoutNodes, links: layoutLinks };
    }

    function calculateLayoutHorizontal(roots) {
        const layoutNodes = [];
        const layoutLinks = [];

        function measure(node) {
            const hasVisibleChildren = node.children && node.children.length > 0 && !collapsedNodes.has(node.id);
            if (!hasVisibleChildren) {
                node._treeHeight = NODE_HEIGHT;
                return;
            }

            let totalChildHeight = 0;
            node.children.forEach((child, i) => {
                measure(child);
                totalChildHeight += child._treeHeight;
                if (i > 0) totalChildHeight += HORIZONTAL_V_GAP;
            });

            node._treeHeight = Math.max(NODE_HEIGHT, totalChildHeight);
        }

        function position(node, depth, yOffset) {
            const x = 40 + depth * (NODE_WIDTH + HORIZONTAL_H_GAP);
            const hasVisibleChildren = node.children && node.children.length > 0 && !collapsedNodes.has(node.id);

            let nodeY;
            if (!hasVisibleChildren) {
                nodeY = yOffset;
            } else {
                let currentChildY = yOffset;
                const childMidpoints = [];

                node.children.forEach((child, i) => {
                    if (i > 0) currentChildY += HORIZONTAL_V_GAP;
                    position(child, depth + 1, currentChildY);
                    childMidpoints.push(child._y + NODE_HEIGHT / 2);
                    currentChildY += child._treeHeight;
                });

                const firstChildMid = childMidpoints[0];
                const lastChildMid = childMidpoints[childMidpoints.length - 1];
                const midPoint = (firstChildMid + lastChildMid) / 2;
                nodeY = midPoint - NODE_HEIGHT / 2;
            }

            node._x = x;
            node._y = nodeY;

            layoutNodes.push(node);

            if (hasVisibleChildren) {
                node.children.forEach(child => {
                    layoutLinks.push({
                        from: node,
                        to: child,
                        x1: node._x + NODE_WIDTH,
                        y1: node._y + NODE_HEIGHT / 2,
                        x2: child._x,
                        y2: child._y + NODE_HEIGHT / 2
                    });
                });
            }
        }

        let totalSpan = 0;
        roots.forEach((root, i) => {
            measure(root);
            totalSpan += root._treeHeight + (i > 0 ? HORIZONTAL_V_GAP * 2 : 0);
        });

        const vpHeight = viewport ? viewport.clientHeight : 600;
        const startY = Math.max(40, (vpHeight - totalSpan) / 2);
        let curY = startY;

        roots.forEach((root, i) => {
            if (i > 0) curY += HORIZONTAL_V_GAP * 2;
            position(root, 0, curY);
            curY += root._treeHeight;
        });

        return { nodes: layoutNodes, links: layoutLinks };
    }

    // -------------------------------------------------------------
    // 3. Rendering Engine (Smooth Animated HTML Cards + SVG Curves)
    // -------------------------------------------------------------
    const renderedCards = new Map(); // Map<nodeId, { el, node, x, y }>
    const prevNodePositions = new Map(); // Map<nodeId, { x, y }>
    let linkAnimTimer = null;

    function clearRender() {
        if (nodesLayer) nodesLayer.innerHTML = '';
        if (linesGroup) linesGroup.innerHTML = '';
        renderedCards.clear();
        prevNodePositions.clear();
        if (linkAnimTimer) {
            cancelAnimationFrame(linkAnimTimer);
            linkAnimTimer = null;
        }
    }

    function createCardElement(node, avatarGrad, isSuperAdmin, hasReports, isCollapsed) {
        const card = document.createElement('div');
        card.className = 'org-node-card';
        card.id = `node-${node.id}`;

        if (isSuperAdmin) {
            card.style.cursor = 'default';
            card.innerHTML = `
                <div class="d-flex align-items-center gap-3 w-100 h-100">
                    <div class="org-node-avatar" style="background: ${avatarGrad};">
                        ${escapeHtml(node.initials || 'SA')}
                        <span class="org-status-indicator" style="background-color: ${node.status_color};" title="${escapeHtml(node.status_label)}"></span>
                    </div>
                    <div class="d-flex flex-column justify-content-center overflow-hidden flex-grow-1" style="min-width: 0;">
                        <div class="org-node-name text-truncate" title="${escapeHtml(node.name)}" style="font-size: 0.95rem; font-weight: 700;">${escapeHtml(node.name)}</div>
                        <div class="org-node-title text-truncate text-white-50" style="font-size: 0.75rem; font-weight: 500;" title="${escapeHtml(node.designation_name)}">${escapeHtml(node.designation_name)}</div>
                        <div class="d-flex align-items-center mt-1">
                            <span class="org-node-dept-badge" style="background: rgba(29, 185, 84, 0.15); color: #1db954; border-color: rgba(29, 185, 84, 0.3);">${escapeHtml(node.special_order === 1 ? 'CEO' : (node.special_order === 2 ? 'CTO' : (node.special_order === 3 ? 'HR' : 'ADMIN')))}</span>
                        </div>
                    </div>
                </div>
                ${hasReports ? `
                    <button type="button" class="org-node-collapse-btn ${isCollapsed ? 'collapsed' : ''}" data-toggle-id="${node.id}" title="${isCollapsed ? 'Expand Team (' + node.direct_reports_count + ' subordinates)' : 'Collapse Team (' + node.direct_reports_count + ' subordinates)'}">
                        <i class="bi bi-people-fill"></i><span>${node.direct_reports_count}</span>
                    </button>
                ` : ''}
            `;
        } else {
            card.innerHTML = `
                <div class="d-flex align-items-center gap-3 w-100 h-100">
                    <div class="org-node-avatar" style="background: ${avatarGrad};">
                        ${escapeHtml(node.initials)}
                        <span class="org-status-indicator" style="background-color: ${node.status_color};" title="${escapeHtml(node.status_label)}"></span>
                    </div>
                    <div class="d-flex flex-column justify-content-center overflow-hidden flex-grow-1" style="min-width: 0;">
                        <div class="org-node-name" title="${escapeHtml(node.name)}">${escapeHtml(node.name)}</div>
                        <div class="org-node-title text-truncate" title="${escapeHtml(node.designation_name)}">${escapeHtml(node.designation_name)}</div>
                        <div class="d-flex align-items-center mt-1">
                            <span class="org-node-dept-badge">${escapeHtml(node.department_code || node.department_name)}</span>
                        </div>
                    </div>
                </div>
                ${hasReports ? `
                    <button type="button" class="org-node-collapse-btn ${isCollapsed ? 'collapsed' : ''}" data-toggle-id="${node.id}" title="${isCollapsed ? 'Expand Team (' + node.direct_reports_count + ' subordinates)' : 'Collapse Team (' + node.direct_reports_count + ' subordinates)'}">
                        <i class="bi bi-people-fill"></i><span>${node.direct_reports_count}</span>
                    </button>
                ` : ''}
            `;
        }

        // Node Click Event -> Open Centered Blurred Detail Modal (Ignored for Super Admin)
        card.addEventListener('click', (e) => {
            if (e.target.closest('.org-node-collapse-btn')) return;
            if (isSuperAdmin) return;
            openNodeDetailModal(node);
        });

        // Collapse Toggle Button listener
        const collapseBtn = card.querySelector('.org-node-collapse-btn');
        if (collapseBtn) {
            collapseBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleCollapse(node.id);
            });
        }

        return card;
    }

    function updateCardElement(card, node, hasReports, isCollapsed, isMatch) {
        if (isMatch) {
            card.classList.add('highlighted');
        } else {
            card.classList.remove('highlighted');
        }

        const btn = card.querySelector('.org-node-collapse-btn');
        if (btn) {
            if (isCollapsed) {
                btn.classList.add('collapsed');
                btn.title = `Expand Team (${node.direct_reports_count} subordinates)`;
            } else {
                btn.classList.remove('collapsed');
                btn.title = `Collapse Team (${node.direct_reports_count} subordinates)`;
            }
        }
    }

    function renderSvgPaths(links, progressFn = null) {
        let svgHtml = '';
        links.forEach(link => {
            let x1 = link.x1;
            let y1 = link.y1;
            let x2 = link.x2;
            let y2 = link.y2;

            if (progressFn) {
                const live = progressFn(link);
                x1 = live.x1;
                y1 = live.y1;
                x2 = live.x2;
                y2 = live.y2;
            }

            let pathD = '';
            if (layoutMode === 'vertical') {
                const midY = (y1 + y2) / 2;
                pathD = `M ${x1} ${y1} C ${x1} ${midY}, ${x2} ${midY}, ${x2} ${y2}`;
            } else {
                const midX = (x1 + x2) / 2;
                pathD = `M ${x1} ${y1} C ${midX} ${y1}, ${midX} ${y2}, ${x2} ${y2}`;
            }

            const isHighlighted = isNodeMatch(link.from) && isNodeMatch(link.to);
            const pathClass = isHighlighted ? 'org-connector-path highlighted-path' : 'org-connector-path';
            svgHtml += `<path d="${pathD}" class="${pathClass}" data-from="${link.from.id}" data-to="${link.to.id}"/>`;
        });
        if (linesGroup) linesGroup.innerHTML = svgHtml;
    }

    function renderTree() {
        if (!treeRoots || treeRoots.length === 0) {
            clearRender();
            return;
        }

        const layout = layoutMode === 'vertical'
            ? calculateLayoutVertical(treeRoots)
            : calculateLayoutHorizontal(treeRoots);

        if (layoutMode === 'horizontal') {
            canvas.classList.add('org-canvas-horizontal');
        } else {
            canvas.classList.remove('org-canvas-horizontal');
        }

        // Calculate dynamic dimensions for native scroll container
        let maxX = 0;
        let maxY = 0;
        layout.nodes.forEach(node => {
            if (node._x + NODE_WIDTH > maxX) maxX = node._x + NODE_WIDTH;
            if (node._y + NODE_HEIGHT > maxY) maxY = node._y + NODE_HEIGHT;
        });

        const padX = 60;
        const padY = 60;
        const vpWidth = viewport ? viewport.clientWidth : 800;
        const vpHeight = viewport ? viewport.clientHeight : 560;

        const totalW = Math.max(vpWidth, maxX + padX);
        const totalH = Math.max(vpHeight, maxY + padY);

        if (canvas) {
            canvas.style.width = `${totalW}px`;
            canvas.style.height = `${totalH}px`;
        }

        if (svgLayer) {
            svgLayer.setAttribute('width', totalW);
            svgLayer.setAttribute('height', totalH);
            svgLayer.style.width = `${totalW}px`;
            svgLayer.style.height = `${totalH}px`;
        }

        const currentNodesMap = new Map(layout.nodes.map(n => [n.id, n]));
        const parentLookup = new Map();
        layout.links.forEach(l => parentLookup.set(l.to.id, l.from.id));

        // 1. Handle Exiting Nodes (Smoothly slide towards parent and fade out)
        renderedCards.forEach((item, nodeId) => {
            if (!currentNodesMap.has(nodeId)) {
                const parentId = item.node.manager_id;
                let targetX = item.x;
                let targetY = item.y;

                if (parentId && currentNodesMap.has(parentId)) {
                    const parentNode = currentNodesMap.get(parentId);
                    targetX = parentNode._x;
                    targetY = parentNode._y;
                } else if (prevNodePositions.has(nodeId)) {
                    const prev = prevNodePositions.get(nodeId);
                    targetX = prev.x;
                    targetY = prev.y;
                }

                item.el.classList.add('node-exiting');
                item.el.style.left = `${targetX}px`;
                item.el.style.top = `${targetY}px`;

                setTimeout(() => {
                    if (item.el && item.el.parentNode) {
                        item.el.parentNode.removeChild(item.el);
                    }
                }, 380);

                renderedCards.delete(nodeId);
            }
        });

        // 2. Handle Entering & Updating Nodes
        let matchCount = 0;
        layout.nodes.forEach(node => {
            const hasReports = node.children && node.children.length > 0;
            const isCollapsed = collapsedNodes.has(node.id);
            const isMatch = searchQuery && isNodeMatch(node);
            if (isMatch) matchCount++;

            if (renderedCards.has(node.id)) {
                // Existing Card: Smoothly translate to new coordinates
                const item = renderedCards.get(node.id);
                updateCardElement(item.el, node, hasReports, isCollapsed, isMatch);
                item.el.classList.remove('node-entering', 'node-exiting');
                item.el.style.left = `${node._x}px`;
                item.el.style.top = `${node._y}px`;
                item.node = node;
                item.x = node._x;
                item.y = node._y;
            } else {
                // New Card: Spawn smoothly from parent position
                const avatarGrad = getAvatarGradient(node.name);
                const isSuperAdmin = !!node.is_super_admin;
                const card = createCardElement(node, avatarGrad, isSuperAdmin, hasReports, isCollapsed);
                if (isMatch) card.classList.add('highlighted');

                const parentId = parentLookup.get(node.id);
                let startX = node._x;
                let startY = node._y;

                if (parentId && prevNodePositions.has(parentId)) {
                    const startPos = prevNodePositions.get(parentId);
                    startX = startPos.x;
                    startY = startPos.y;
                }

                card.style.left = `${startX}px`;
                card.style.top = `${startY}px`;
                card.classList.add('node-entering');

                nodesLayer.appendChild(card);
                renderedCards.set(node.id, { el: card, node, x: node._x, y: node._y });

                // Animate smoothly to destination on next frame
                requestAnimationFrame(() => {
                    card.classList.remove('node-entering');
                    card.style.left = `${node._x}px`;
                    card.style.top = `${node._y}px`;
                });
            }
        });

        // 3. Smooth SVG Line Interpolation Animation
        if (linkAnimTimer) {
            cancelAnimationFrame(linkAnimTimer);
            linkAnimTimer = null;
        }

        const animStartTime = performance.now();
        const animDuration = 380; // ms

        function stepLinkAnimation(now) {
            const elapsed = now - animStartTime;
            const t = Math.min(1, elapsed / animDuration);
            // Ease out cubic
            const ease = 1 - Math.pow(1 - t, 3);

            renderSvgPaths(layout.links, (link) => {
                const fromStart = prevNodePositions.get(link.from.id) || { x: link.from._x, y: link.from._y };
                const toStart = prevNodePositions.get(link.to.id) || fromStart;

                let fx1, fy1, tx2, ty2;
                if (layoutMode === 'vertical') {
                    const startX1 = fromStart.x + NODE_WIDTH / 2;
                    const startY1 = fromStart.y + NODE_HEIGHT;
                    const startX2 = toStart.x + NODE_WIDTH / 2;
                    const startY2 = toStart.y;

                    fx1 = startX1 + (link.x1 - startX1) * ease;
                    fy1 = startY1 + (link.y1 - startY1) * ease;
                    tx2 = startX2 + (link.x2 - startX2) * ease;
                    ty2 = startY2 + (link.y2 - startY2) * ease;
                } else {
                    const startX1 = fromStart.x + NODE_WIDTH;
                    const startY1 = fromStart.y + NODE_HEIGHT / 2;
                    const startX2 = toStart.x;
                    const startY2 = toStart.y + NODE_HEIGHT / 2;

                    fx1 = startX1 + (link.x1 - startX1) * ease;
                    fy1 = startY1 + (link.y1 - startY1) * ease;
                    tx2 = startX2 + (link.x2 - startX2) * ease;
                    ty2 = startY2 + (link.y2 - startY2) * ease;
                }

                return { x1: fx1, y1: fy1, x2: tx2, y2: ty2 };
            });

            if (t < 1) {
                linkAnimTimer = requestAnimationFrame(stepLinkAnimation);
            } else {
                renderSvgPaths(layout.links);
                linkAnimTimer = null;
            }
        }

        linkAnimTimer = requestAnimationFrame(stepLinkAnimation);

        // Update previous positions cache
        prevNodePositions.clear();
        layout.nodes.forEach(n => prevNodePositions.set(n.id, { x: n._x, y: n._y }));

        // Update search matches feedback
        if (matchesCount) {
            if (searchQuery) {
                matchesCount.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-search me-1"></i> ${matchCount} matching personnel found</span>`;
            } else {
                matchesCount.innerHTML = `<i class="bi bi-info-circle me-1"></i> Click any node to open full details`;
            }
        }
    }

    function isNodeMatch(node) {
        if (!searchQuery) return false;
        const q = searchQuery.toLowerCase();
        return (
            (node.name && node.name.toLowerCase().includes(q)) ||
            (node.employee_code && node.employee_code.toLowerCase().includes(q)) ||
            (node.designation_name && node.designation_name.toLowerCase().includes(q)) ||
            (node.email && node.email.toLowerCase().includes(q))
        );
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // -------------------------------------------------------------
    // 4. Branch Collapse & Expand
    // -------------------------------------------------------------
    function toggleCollapse(nodeId) {
        if (collapsedNodes.has(nodeId)) {
            collapsedNodes.delete(nodeId);
        } else {
            collapsedNodes.add(nodeId);
        }
        renderTree();
    }

    function expandAll() {
        collapsedNodes.clear();
        renderTree();
    }

    function collapseAll() {
        function collectManagerIds(nodes) {
            nodes.forEach(node => {
                if (node.children && node.children.length > 0) {
                    collapsedNodes.add(node.id);
                    collectManagerIds(node.children);
                }
            });
        }
        collectManagerIds(treeRoots);
        renderTree();
    }

    // -------------------------------------------------------------
    // 5. Centered Blurred Detail Modal (Opened On Node Click)
    // -------------------------------------------------------------
    function openNodeDetailModal(node) {
        if (!modalBackdrop) return;

        const modalAvatar = document.getElementById('modal-avatar');
        const modalName = document.getElementById('modal-name');
        const modalDesig = document.getElementById('modal-designation');
        const modalStatusBadge = document.getElementById('modal-status-badge');
        const modalDept = document.getElementById('modal-dept');
        const modalManager = document.getElementById('modal-manager');
        const modalEmail = document.getElementById('modal-email');
        const modalPhone = document.getElementById('modal-phone');
        const modalTenure = document.getElementById('modal-tenure');
        const modalReports = document.getElementById('modal-reports');
        const modalEditBtn = document.getElementById('modal-edit-btn');
        const modalTrackBtn = document.getElementById('modal-track-btn');
        const modalLogsBtn = document.getElementById('modal-logs-btn');

        if (modalAvatar) {
            modalAvatar.style.background = getAvatarGradient(node.name);
            modalAvatar.innerText = node.initials;
        }
        if (modalName) modalName.innerText = node.name;
        if (modalDesig) modalDesig.innerText = node.designation_name;
        if (modalStatusBadge) {
            modalStatusBadge.innerText = node.status_label;
            modalStatusBadge.style.color = node.status_color;
            modalStatusBadge.style.borderColor = node.status_color;
            modalStatusBadge.style.background = `${node.status_color}22`;
        }
        if (modalDept) modalDept.innerText = node.department_name || '—';
        if (modalManager) modalManager.innerText = node.manager_name ? `${node.manager_name} (${node.manager_designation || 'Manager'})` : '—';
        if (modalEmail) modalEmail.innerText = node.email || '—';
        if (modalPhone) modalPhone.innerText = node.phone || '—';
        if (modalTenure) modalTenure.innerText = `${node.tenure} (${node.join_date_formatted || '—'})`;
        if (modalReports) modalReports.innerText = `${node.direct_reports_count} Direct • ${node.total_subordinates_count} Total Subordinates`;

        const isGuest = Boolean(node.is_guest || node.role_name === 'guest' || node.role_label === 'Guest' || node.role_name === 'Guest');

        // Configure Action URLs
        if (modalEditBtn) modalEditBtn.href = `/super-admin/users/${node.id}/edit`;
        if (modalTrackBtn) {
            modalTrackBtn.href = `/super-admin/session-management/track-record?user_id=${node.id}`;
            modalTrackBtn.classList.toggle('d-none', isGuest);
            modalTrackBtn.style.setProperty('display', isGuest ? 'none' : 'inline-flex', 'important');
        }
        if (modalLogsBtn) {
            modalLogsBtn.href = `/super-admin/attendance-holidays/absents/logs?user_id=${node.id}`;
            modalLogsBtn.classList.toggle('d-none', isGuest);
            modalLogsBtn.style.setProperty('display', isGuest ? 'none' : 'inline-flex', 'important');
        }

        modalBackdrop.classList.remove('d-none');
        requestAnimationFrame(() => {
            modalBackdrop.classList.add('active');
        });
    }

    function closeNodeDetailModal() {
        if (!modalBackdrop) return;
        modalBackdrop.classList.remove('active');
        setTimeout(() => {
            modalBackdrop.classList.add('d-none');
        }, 220);
    }

    // -------------------------------------------------------------
    // 6. Viewport Center Scrolling
    // -------------------------------------------------------------
    function centerScroll() {
        if (!viewport) return;
        if (layoutMode === 'vertical') {
            viewport.scrollTop = 0;
            viewport.scrollLeft = Math.max(0, (viewport.scrollWidth - viewport.clientWidth) / 2);
        } else {
            viewport.scrollLeft = 0;
            viewport.scrollTop = Math.max(0, (viewport.scrollHeight - viewport.clientHeight) / 2);
        }
    }

    // -------------------------------------------------------------
    // 7. Search & Smooth Focus
    // -------------------------------------------------------------
    function performSearch(query) {
        searchQuery = (query || '').trim();
        if (searchClear) {
            searchClear.classList.toggle('d-none', !searchQuery);
        }

        if (searchQuery) {
            // Auto expand all ancestors for matches
            function expandAncestors(nodes) {
                let foundMatch = false;
                nodes.forEach(node => {
                    const selfMatch = isNodeMatch(node);
                    let childMatch = false;
                    if (node.children && node.children.length > 0) {
                        childMatch = expandAncestors(node.children);
                    }
                    if (childMatch) {
                        collapsedNodes.delete(node.id);
                        foundMatch = true;
                    }
                    if (selfMatch) foundMatch = true;
                });
                return foundMatch;
            }

            expandAncestors(treeRoots);
        }

        renderTree();

        // Smooth scroll to first matching node
        if (searchQuery) {
            const firstMatch = document.querySelector('.org-node-card.highlighted');
            if (firstMatch) {
                firstMatch.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
            }
        }
    }

    // -------------------------------------------------------------
    // 8. Department Selection
    // -------------------------------------------------------------
    function selectDepartment(deptId, deptName) {
        currentDeptId = deptId;

        // Update dropdown menu items active state
        document.querySelectorAll('.org-dept-item').forEach(item => {
            const id = item.getAttribute('data-dept-id');
            if (id == deptId) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });

        // Update dropdown button text
        const selectedDeptLabel = document.getElementById('selected-dept-label');
        if (selectedDeptLabel) {
            selectedDeptLabel.innerText = deptName;
        }

        // Update the green badge in header on far right
        const headerBadge = document.getElementById('org-header-active-badge');
        if (headerBadge) {
            headerBadge.setAttribute('data-dept-id', deptId);
            headerBadge.innerText = deptName;
        }

        // Fetch and render hierarchy
        loadHierarchy(deptId);
    }

    // -------------------------------------------------------------
    // 9. Event Handlers & Initialization
    // -------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', () => {
        // Initial tree fetch (honors preapplied department filter if specified)
        loadHierarchy(initialDept || 'all');

        // Department dropdown item clicks
        document.querySelectorAll('.org-dept-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const deptId = item.getAttribute('data-dept-id');
                const deptName = item.getAttribute('data-dept-name') || item.innerText.trim();
                selectDepartment(deptId, deptName);
            });
        });

        // Header Green Badge click (resets to Whole Organization if department selected)
        const headerBadge = document.getElementById('org-header-active-badge');
        if (headerBadge) {
            headerBadge.addEventListener('click', () => {
                const currentId = headerBadge.getAttribute('data-dept-id');
                if (currentId !== 'all') {
                    selectDepartment('all', 'Whole Organization');
                } else {
                    centerScroll();
                }
            });
        }

        // Layout Switches (Vertical / Horizontal)
        const btnVert = document.getElementById('btn-layout-vertical');
        const btnHoriz = document.getElementById('btn-layout-horizontal');

        if (btnVert && btnHoriz) {
            btnVert.addEventListener('click', () => {
                btnVert.classList.add('active');
                btnHoriz.classList.remove('active');
                layoutMode = 'vertical';
                renderTree();
                centerScroll();
            });

            btnHoriz.addEventListener('click', () => {
                btnHoriz.classList.add('active');
                btnVert.classList.remove('active');
                layoutMode = 'horizontal';
                renderTree();
                centerScroll();
            });
        }

        // Expand / Collapse All
        const btnExp = document.getElementById('btn-expand-all');
        const btnCol = document.getElementById('btn-collapse-all');
        if (btnExp) btnExp.addEventListener('click', expandAll);
        if (btnCol) btnCol.addEventListener('click', collapseAll);

        // Search Input
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                performSearch(e.target.value);
            });
        }
        if (searchClear) {
            searchClear.addEventListener('click', () => {
                if (searchInput) searchInput.value = '';
                performSearch('');
            });
        }

        // Modal Close Event Listeners
        const modalCancelBtn = document.getElementById('modal-cancel-btn');
        if (modalCancelBtn) modalCancelBtn.addEventListener('click', closeNodeDetailModal);

        if (modalBackdrop) {
            modalBackdrop.addEventListener('click', (e) => {
                if (e.target === modalBackdrop) {
                    closeNodeDetailModal();
                }
            });
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modalBackdrop && modalBackdrop.classList.contains('active')) {
                closeNodeDetailModal();
            }
        });
    });

})();
