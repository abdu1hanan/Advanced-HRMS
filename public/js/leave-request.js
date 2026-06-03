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
                window.showToast(res.message, 'success');
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
                window.showToast(msg, 'danger');
            },
        });
    });

    let cancelRequestId = null;

    $(document).on('click', '.btn-cancel-leave', function () {
        cancelRequestId = $(this).data('id');
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('cancelLeaveModal'));
        modal.show();
    });

    $(document).on('click', '#confirmCancelBtn', function () {
        if (!cancelRequestId) return;
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('cancelLeaveModal'));
        if (modal) modal.hide();

        $.post(window.leaveCancelUrl + '/' + cancelRequestId + '/cancel', function (res) {
            const id = cancelRequestId;
            const badgeEl = $('#leave-row-' + id).find('.row-badge-warning, .row-badge-blue, .row-badge-green, .row-badge-red, .row-badge-gray, .badge');
            badgeEl.removeClass()
                .addClass('row-badge-gray')
                .html('<i class="bi bi-x-circle me-1"></i> Cancelled');
            $('#leave-row-' + id).find('.btn-cancel-leave').remove();
            updateBalanceCards(res.balances);
            
            // Show Success Snackbar
            window.showToast(res.message, 'success');

            setTimeout(function () { location.reload(); }, 1200);
        }).fail(function (xhr) {
            window.showToast(xhr.responseJSON?.message || 'Could not cancel request.', 'danger');
        });
    });
});
