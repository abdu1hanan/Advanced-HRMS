$(function () {
    $(document).on('click', '.btn-approve-leave', function () {
        const id = $(this).data('id');
        const comment = $(`#comment-${id}`).val();
        const row = $(`#request-row-${id}`);

        $.post(`/admin/leave-requests/${id}/approve`, { admin_comment: comment }, function (res) {
            row.fadeOut(300, function () { $(this).remove(); });
            window.showToast(res.message, 'success');
        }).fail(function (xhr) {
            window.showToast(xhr.responseJSON?.message || 'Error', 'danger');
        });
    });

    $(document).on('click', '.btn-reject-leave', function () {
        const id = $(this).data('id');
        const comment = $(`#comment-${id}`).val();

        if (!comment || !comment.trim()) {
            window.showToast('A comment is required when rejecting.', 'danger');
            return;
        }

        const row = $(`#request-row-${id}`);

        $.post(`/admin/leave-requests/${id}/reject`, { admin_comment: comment }, function (res) {
            row.fadeOut(300, function () { $(this).remove(); });
            window.showToast(res.message, 'success');
        }).fail(function (xhr) {
            window.showToast(xhr.responseJSON?.message || 'Error', 'danger');
        });
    });


});
