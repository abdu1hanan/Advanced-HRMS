function updateBalanceCards(balances) {
    if (!balances) return;
    balances.forEach(function (b) {
        const card = $('.balance-card[data-type="' + b.leave_type + '"]');
        card.find('.balance-used').text(b.used);
        card.find('.balance-pending').text(b.pending);
        card.find('.balance-remaining').text(b.remaining);
    });
}

function showLeaveAlert(message, type) {
    const alertBox = $('#leave-form-alert');
    alertBox.removeClass('d-none alert-success alert-danger').addClass('alert-' + type).text(message);
}

$(function () {
    const form = $('#leave-request-form');

    form.on('submit', function (e) {
        e.preventDefault();
        $('#leave-form-alert').addClass('d-none');

        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: form.serialize(),
            success: function (res) {
                showLeaveAlert(res.message, 'success');
                form[0].reset();
                updateBalanceCards(res.balances);
                if (typeof refreshNotificationCount === 'function') {
                    refreshNotificationCount();
                }
                setTimeout(function () { location.reload(); }, 800);
            },
            error: function (xhr) {
                let msg = xhr.responseJSON?.message || 'Failed to submit leave request.';
                if (xhr.responseJSON?.errors) {
                    const errs = Object.values(xhr.responseJSON.errors).flat();
                    if (errs.length) msg = errs.join(' ');
                }
                showLeaveAlert(msg, 'danger');
            },
        });
    });

    $(document).on('click', '.btn-cancel-leave', function () {
        const id = $(this).data('id');
        if (!confirm('Cancel this pending leave request?')) return;

        $.post(window.leaveCancelUrl + '/' + id + '/cancel', function (res) {
            showLeaveAlert(res.message, 'success');
            $('#leave-row-' + id).find('.badge')
                .removeClass('bg-warning bg-warning-subtle text-dark-emphasis bg-info-subtle text-info-emphasis border-info-subtle text-dark')
                .addClass('bg-secondary text-white')
                .html('<i class="bi bi-dash-circle-fill me-1"></i> Cancelled');
            $('#leave-row-' + id).find('.btn-cancel-leave').remove();
            updateBalanceCards(res.balances);
            setTimeout(function () { location.reload(); }, 800);
        }).fail(function (xhr) {
            showLeaveAlert(xhr.responseJSON?.message || 'Could not cancel request.', 'danger');
        });
    });
});
