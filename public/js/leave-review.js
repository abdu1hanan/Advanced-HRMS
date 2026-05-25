$(function () {
    $(document).on('click', '.btn-approve-leave', function () {
        const id = $(this).data('id');
        const comment = $(`#comment-${id}`).val();
        const row = $(`#request-row-${id}`);

        $.post(`/admin/leave-requests/${id}/approve`, { admin_comment: comment }, function (res) {
            row.fadeOut(300, function () { $(this).remove(); });
            showToast(res.message, 'success');
        }).fail(function (xhr) {
            showToast(xhr.responseJSON?.message || 'Error', 'danger');
        });
    });

    $(document).on('click', '.btn-reject-leave', function () {
        const id = $(this).data('id');
        const comment = $(`#comment-${id}`).val();

        if (!comment || !comment.trim()) {
            showToast('A comment is required when rejecting.', 'warning');
            return;
        }

        const row = $(`#request-row-${id}`);

        $.post(`/admin/leave-requests/${id}/reject`, { admin_comment: comment }, function (res) {
            row.fadeOut(300, function () { $(this).remove(); });
            showToast(res.message, 'success');
        }).fail(function (xhr) {
            showToast(xhr.responseJSON?.message || 'Error', 'danger');
        });
    });

    function showToast(message, type) {
        const toast = $(`<div class="alert alert-${type} position-fixed top-0 end-0 m-3" style="z-index: 9999;">${message}</div>`);
        $('body').append(toast);
        setTimeout(() => toast.fadeOut(300, function () { $(this).remove(); }), 3000);
    }
});
