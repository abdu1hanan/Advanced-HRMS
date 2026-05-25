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
            $('#leave-row-' + id).find('.badge')
                .removeClass('bg-warning bg-warning-subtle text-dark-emphasis bg-info-subtle text-info-emphasis border-info-subtle text-dark')
                .addClass('bg-secondary text-white')
                .html('<i class="bi bi-dash-circle-fill me-1"></i> Cancelled');
            $('#leave-row-' + id).find('.btn-cancel-leave').remove();
            updateBalanceCards(res.balances);
            
            // Show Success Modal
            $('#cancelSuccessMsg').text(res.message);
            const successModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('cancelSuccessModal'));
            successModal.show();

            setTimeout(function () { location.reload(); }, 1200);
        }).fail(function (xhr) {
            alert(xhr.responseJSON?.message || 'Could not cancel request.');
        });
    });
});
