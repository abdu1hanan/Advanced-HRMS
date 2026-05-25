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

function loadNotifications() {
    $.get('/notifications')
        .done(function (data) {
            const list = $('#notification-list');
            list.find('.notification-item').remove();

            if (!data.notifications.length) {
                $('#notification-empty').show();
                return;
            }

            $('#notification-empty').hide();
            data.notifications.forEach(function (n) {
                const item = $(
                    '<li class="notification-item"><a class="dropdown-item ' +
                        (n.read_at ? '' : 'fw-bold') +
                        '" href="' +
                        n.url +
                        '" data-id="' +
                        n.id +
                        '"><div class="small">' +
                        n.message +
                        '</div><div class="text-muted" style="font-size:0.75rem;">' +
                        n.created_at +
                        '</div></a></li>'
                );
                list.append(item);
            });
        })
        .fail(function () {
            $('#notification-empty').text('Could not load notifications.').show();
        });
}

$(function () {
    refreshNotificationCount();
    setInterval(refreshNotificationCount, 60000);

    $('#notificationDropdown').on('show.bs.dropdown', loadNotifications);

    $(document).on('click', '.notification-item a', function () {
        const id = $(this).data('id');
        $.post('/notifications/' + id + '/read').always(refreshNotificationCount);
    });

    $('#mark-all-read').on('click', function (e) {
        e.preventDefault();
        $.post('/notifications/read-all')
            .done(function () {
                refreshNotificationCount();
                loadNotifications();
            })
            .fail(function () {
                alert('Could not mark notifications as read.');
            });
    });
});
