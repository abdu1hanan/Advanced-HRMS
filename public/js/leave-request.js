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
                if (res.row_html && $('#leave-requests-table').length) {
                    const tbody = $('#leave-requests-table tbody');
                    tbody.find('tr:has(td[colspan])').remove();
                    tbody.prepend(res.row_html);
                }
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
    let cancelIsFuture = '1';

    $(document).on('click', '.btn-cancel-leave', function () {
        cancelRequestId = $(this).data('id');
        const reqApproval = $(this).data('requires-approval');
        if (reqApproval !== undefined) {
            cancelIsFuture = (String(reqApproval) === '1') ? '0' : '1';
        } else {
            cancelIsFuture = $(this).data('future') !== undefined ? String($(this).data('future')) : '1';
        }

        const reasonInput = $('#cancelLeaveReason');
        reasonInput.val('').attr('style', 'background-color: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.15) !important;');
        $('#cancelReasonError').addClass('d-none');

        if (cancelIsFuture === '1') {
            $('#cancelModalTitle').text('Cancel Leave Request?');
            $('#cancelModalDesc').text('Are you sure you want to cancel this leave request? Your balance will be refunded immediately.');
            $('#cancelModalIconContainer').removeClass('text-warning').addClass('text-danger');
            $('#cancelModalIcon').removeClass('bi-exclamation-triangle-fill').addClass('bi-x-circle-fill');
            $('#cancelModalAlert').addClass('d-none');
            $('#cancelModalTooltipIcon').addClass('d-none');
            $('#btnProceedToReason').text('Yes, Cancel Leave').removeClass('row-btn-warning').addClass('row-btn-red');
        } else {
            $('#cancelModalTitle').text('Request Leave Cancellation?');
            $('#cancelModalDesc').text('Are you sure you want to request cancellation for this past fully approved leave?');
            $('#cancelModalIconContainer').removeClass('text-danger').addClass('text-warning');
            $('#cancelModalIcon').removeClass('bi-x-circle-fill').addClass('bi-exclamation-triangle-fill');
            $('#cancelModalAlert').addClass('d-none');
            $('#cancelModalTooltipIcon').removeClass('d-none');
            $('#btnProceedToReason').text('Yes, Request').removeClass('row-btn-red').addClass('row-btn-warning');
        }

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('cancelLeaveModal'));
        modal.show();
    });

    $(document).on('click', '#btnProceedToReason', function () {
        const step1Modal = bootstrap.Modal.getInstance(document.getElementById('cancelLeaveModal'));
        if (step1Modal) step1Modal.hide();

        if (cancelIsFuture === '1') {
            $('#confirmCancelBtn').text('Confirm Cancellation').removeClass('row-btn-warning').addClass('row-btn-red');
        } else {
            $('#confirmCancelBtn').text('Submit Request').removeClass('row-btn-red').addClass('row-btn-warning');
        }

        const step2Modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('cancelReasonModal'));
        step2Modal.show();
        setTimeout(() => $('#cancelLeaveReason').focus(), 300);
    });

    $(document).on('click', '#btnBackToConfirmModal', function () {
        const step2Modal = bootstrap.Modal.getInstance(document.getElementById('cancelReasonModal'));
        if (step2Modal) step2Modal.hide();

        const step1Modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('cancelLeaveModal'));
        step1Modal.show();
    });

    $(document).on('input', '#cancelLeaveReason', function () {
        if ($(this).val().trim().length > 0) {
            $(this).attr('style', 'background-color: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.15) !important;');
            $('#cancelReasonError').addClass('d-none');
        }
    });

    $(document).on('click', '#confirmCancelBtn', function () {
        if (!cancelRequestId) return;
        
        const reasonInput = $('#cancelLeaveReason');
        const reason = reasonInput.val().trim();
        if (!reason) {
            reasonInput.attr('style', 'background-color: rgba(255, 255, 255, 0.04); border: 1px solid #ef4444 !important; box-shadow: 0 0 0 1px #ef4444 !important;');
            $('#cancelReasonError').removeClass('d-none');
            reasonInput.focus();
            return;
        }
        reasonInput.attr('style', 'background-color: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.15) !important;');
        $('#cancelReasonError').addClass('d-none');

        const btn = $(this);
        const originalHtml = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Processing...');

        const token = $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val();
        const cancelBaseUrl = window.leaveCancelUrl || '/admin/leave-requests';

        $.ajax({
            url: cancelBaseUrl + '/' + cancelRequestId + '/cancel',
            method: 'POST',
            data: {
                _token: token,
                reason: reason
            },
            success: function (res) {
                btn.prop('disabled', false).html(originalHtml);
                const modal = bootstrap.Modal.getInstance(document.getElementById('cancelReasonModal'));
                if (modal) modal.hide();

                const id = cancelRequestId;

                if (res.row_html) {
                    const existingRow = $('#leave-row-' + id);
                    const existingData = $('#request-data-' + id);
                    if (existingRow.length) {
                        const tempDiv = document.createElement('tbody');
                        tempDiv.innerHTML = res.row_html;

                        const newRow = tempDiv.querySelector('#leave-row-' + id);
                        const newData = tempDiv.querySelector('#request-data-' + id);

                        if (newRow) existingRow[0].replaceWith(newRow);
                        if (newData) {
                            if (existingData.length) existingData[0].replaceWith(newData);
                            else if (newRow) newRow.after(newData);
                        }
                    }
                } else {
                    const row = $('#leave-row-' + id);
                    const dataDiv = document.getElementById('request-data-' + id);

                    if (dataDiv) {
                        const statusEl = dataDiv.querySelector('.d-status');
                        const cancelReasonEl = dataDiv.querySelector('.d-cancel-reason');
                        const cancelBannerEl = dataDiv.querySelector('.d-cancel-banner-text');
                        const isCancelReqEl = dataDiv.querySelector('.d-is-cancel-req');
                        const hasOrigEl = dataDiv.querySelector('.d-has-orig-snapshot');
                        const hasChangeEl = dataDiv.querySelector('.d-has-change');

                        if (statusEl) statusEl.textContent = res.instant ? 'Cancelled' : statusEl.textContent;
                        if (cancelReasonEl) cancelReasonEl.textContent = res.cancel_reason || reason;
                        if (cancelBannerEl) cancelBannerEl.textContent = res.cancel_banner_text || '';
                        if (isCancelReqEl) isCancelReqEl.textContent = res.instant ? '0' : '1';
                        if (res.instant) {
                            if (hasOrigEl) hasOrigEl.textContent = '0';
                            if (hasChangeEl) hasChangeEl.textContent = '0';
                        }

                        const chainDataEl = dataDiv.querySelector('.d-chain-data');
                        if (chainDataEl && res.instant) {
                            chainDataEl.dataset.status = 'cancelled';
                        }
                    }

                    if (res.instant) {
                        const statusTd = row.find('td:nth-child(5)');
                        const badgeEl = statusTd.find('.row-badge-warning, .row-badge-blue, .row-badge-green, .row-badge-red, .row-badge-gray, .row-badge');
                        if (badgeEl.length) {
                            badgeEl.removeClass()
                                .addClass('row-badge-gray')
                                .html('<i class="bi bi-x-circle me-1"></i> Cancelled');
                        } else {
                            statusTd.html('<span class="row-badge-gray"><i class="bi bi-x-circle me-1"></i> Cancelled</span>');
                        }
                        row.find('.btn-cancel-leave').remove();
                        row.find('.btn-circle-gray').remove();
                        row.find('.row-badge-purple').closest('.mt-1').remove();
                    } else {
                        // Past cancellation requested
                        row.find('.btn-cancel-leave').remove();
                        const statusTd = row.find('td:nth-child(5)');
                        if (statusTd.length) {
                            const newBadgeDiv = $('<div class="mt-1"><span class="row-badge-purple"><i class="bi bi-exclamation-triangle-fill me-1"></i> Cancellation Requested</span></div>');
                            statusTd.append(newBadgeDiv);
                        }
                    }
                }

                // If requestModal is open for this request, refresh its contents instantly
                const reqModal = document.getElementById('requestModal');
                if (reqModal && typeof window.openRequestModal === 'function') {
                    window.openRequestModal(id);
                }

                if (res.balances) {
                    updateBalanceCards(res.balances);
                }

                window.showToast(res.message, 'success');
            },
            error: function (xhr) {
                btn.prop('disabled', false).html(originalHtml);
                let msg = xhr.responseJSON?.message || 'Could not process cancellation.';
                window.showToast(msg, 'danger');
            }
        });
    });
});
