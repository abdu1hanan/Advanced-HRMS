$(function () {
    let pendingForm = null;
    const modal = $('#replaceHeadModal');
    const modalName = $('#replace-head-name');

    function needsHeadCheck($form) {
        const roleSelect = $form.find('[name="role_id"]');
        if (roleSelect.length) {
            const roleText = roleSelect.find('option:selected').text().toLowerCase();
            if (!roleText.includes('admin')) {
                return false;
            }
        }

        const deptId = $form.find('[name="department_id"]').val() || $form.data('department-id');
        const headId = $form.find('[name="head_user_id"]').val() || $form.data('user-id');

        if (!deptId || !headId) {
            return false;
        }

        return { department_id: deptId, user_id: headId };
    }

    $(document).on('submit', '.assign-head-form, .user-update-form', function (e) {
        const $form = $(this);
        const confirmInput = $form.find('.confirm-head-replace-input');

        if (confirmInput.val() === '1') {
            return true;
        }

        const params = needsHeadCheck($form);
        if (!params) {
            return true;
        }

        e.preventDefault();
        pendingForm = $form;

        $.get('/super-admin/users/check-head-replace', params)
            .done(function (data) {
                if (data.needs_confirmation) {
                    modalName.text(data.previous_head_name);
                    modal.modal('show');
                } else {
                    $form.find('.confirm-head-replace-input').val('1');
                    $form.off('submit').submit();
                }
            })
            .fail(function () {
                window.showToast('Could not verify department head. Please try again.', 'danger');
            });
    });

    $('#confirm-replace-head').on('click', function () {
        if (!pendingForm) {
            return;
        }
        pendingForm.find('.confirm-head-replace-input').val('1');
        modal.modal('hide');
        pendingForm.off('submit').submit();
        pendingForm = null;
    });

    modal.on('hidden.bs.modal', function () {
        pendingForm = null;
    });
});
