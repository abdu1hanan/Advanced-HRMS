function refreshNotificationCount() {
    $.get('/notifications/unread-count')
        .done(function (data) {
            const badge = $('#notification-badge');
            if (data.count > 0) {
                badge.removeClass('d-none');
            } else {
                badge.addClass('d-none');
            }
        })
        .fail(function () {
            console.warn('Could not load notification count.');
        });
}

let localNotifications = [];
let currentFilter = 'all';

function parseMessage(msg) {
    // Standard format: [Prefix] Username: "message text"
    const regex = /^\[([^\]]+)\]\s*([^:]+):\s*"([^"]+)"/;
    const match = msg.match(regex);
    if (match) {
        return {
            prefix: match[1],
            username: match[2].trim(),
            content: match[3],
            raw: msg
        };
    }

    // Try to parse old override requests: "username requested a session override: "text"."
    const overrideRegex = /^(.+?)\s+requested\s+a\s+session\s+override:\s*"(.+?)"/;
    const overrideMatch = msg.match(overrideRegex);
    if (overrideMatch) {
        return {
            prefix: 'Session Override',
            username: overrideMatch[1].trim(),
            content: 'Requested override: ' + overrideMatch[2],
            raw: msg
        };
    }

    // Try to parse old forgot sign out: "username forgot to sign out on date. An auto-appended sign-out has been pending at time."
    const signOutRegex = /^(.+?)\s+forgot\s+to\s+sign\s+out\s+on\s+([^.]+?)\.\s+An\s+auto-appended/;
    const signOutMatch = msg.match(signOutRegex);
    if (signOutMatch) {
        return {
            prefix: 'Forgot Sign Out',
            username: signOutMatch[1].trim(),
            content: 'Forgot to sign out on ' + signOutMatch[2] + '.',
            raw: msg
        };
    }

    // Try to parse old break exceeded: "username exceeded their break at time (came back at time, duration: duration mins)..."
    const breakRegex = /^(.+?)\s+exceeded\s+their\s+break\s+at\s+([^(]+?)\s+\(came\s+back\s+at\s+([^,]+?),\s+duration:\s*([^)]+?)\)/;
    const breakMatch = msg.match(breakRegex);
    if (breakMatch) {
        return {
            prefix: 'Break Exceeded',
            username: breakMatch[1].trim(),
            content: 'Exceeded break starting at ' + breakMatch[2].trim() + ' (back at ' + breakMatch[3].trim() + ')',
            raw: msg
        };
    }

    // Fallback if not matching the new structured format
    return {
        prefix: 'Alert',
        username: 'System',
        content: msg,
        raw: msg
    };
}

function loadNotifications() {
    $.get('/notifications')
        .done(function (data) {
            localNotifications = data.notifications;
            renderFilteredNotifications();
        })
        .fail(function () {
            $('#notification-empty').text('Could not load notifications.').show();
        });
}

function renderFilteredNotifications() {
    const list = $('#notification-list');
    list.find('.custom-notification-item').remove();

    let displayList = localNotifications;
    if (currentFilter === 'unread') {
        displayList = localNotifications.filter(n => !n.read_at);
    }

    if (!displayList.length) {
        $('#notification-empty').show();
        return;
    }

    $('#notification-empty').hide();
    displayList.forEach(function (n) {
        const parsed = parseMessage(n.message);
        const initial = parsed.username ? parsed.username.charAt(0).toUpperCase() : 'S';
        const unreadDot = n.read_at ? '' : '<div class="custom-notification-unread-dot flex-shrink-0"></div>';
        
        const item = $(
            '<li class="custom-notification-item d-flex align-items-start gap-2" data-id="' + n.id + '" data-url="' + n.url + '">' +
                '<div class="custom-notification-avatar flex-shrink-0">' + initial + '</div>' +
                '<div class="flex-grow-1 min-width-0">' +
                    '<div class="custom-notification-message">' +
                        '<strong>[' + parsed.prefix + '] ' + parsed.username + ':</strong> "' + parsed.content + '"' +
                    '</div>' +
                    '<div class="d-flex justify-content-between align-items-center mt-1">' +
                        '<div></div>' +
                        '<span class="custom-notification-time">' + n.created_at + '</span>' +
                    '</div>' +
                '</div>' +
                unreadDot +
            '</li>'
        );
        list.append(item);
    });
}

$(function () {
    // CSRF Setup for AJAX
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    refreshNotificationCount();
    setInterval(refreshNotificationCount, 60000);

    // Fetch notifications on dropdown open
    $('#notificationDropdown').on('show.bs.dropdown', function() {
        loadNotifications();
    });

    // Handle Local Filter Pill switching
    $('#filter-all-btn').on('click', function (e) {
        e.stopPropagation();
        $('#filter-all-btn').addClass('active');
        $('#filter-unread-btn').removeClass('active');
        currentFilter = 'all';
        renderFilteredNotifications();
    });

    $('#filter-unread-btn').on('click', function (e) {
        e.stopPropagation();
        $('#filter-unread-btn').addClass('active');
        $('#filter-all-btn').removeClass('active');
        currentFilter = 'unread';
        renderFilteredNotifications();
    });

    // Handle clicking a notification item
    $(document).on('click', '.custom-notification-item', function (e) {
        const id = $(this).data('id');
        const url = $(this).data('url');
        
        $.post('/notifications/' + id + '/read')
            .done(function() {
                refreshNotificationCount();
                if (url && url !== '#') {
                    window.location.href = url;
                } else {
                    loadNotifications();
                }
            })
            .fail(function() {
                if (url && url !== '#') {
                    window.location.href = url;
                }
            });
    });

    // Mark all read
    $('#mark-all-read').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $.post('/notifications/read-all')
            .done(function () {
                refreshNotificationCount();
                loadNotifications();
            })
            .fail(function () {
                if (window.showToast) {
                    window.showToast('Could not mark notifications as read.', 'danger');
                } else {
                    alert('Could not mark notifications as read.');
                }
            });
    });
});
