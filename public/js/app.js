$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        'X-Requested-With': 'XMLHttpRequest',
    },
});

window.showToast = function(message, type = 'danger') {
    const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill';
    const toastHtml = `
        <div class="sleek-toast sleek-toast-${type}">
            <div class="d-flex align-items-center gap-2">
                <i class="bi ${icon}"></i>
                <span class="small fw-semibold">${message}</span>
            </div>
            <button type="button" class="btn-close ms-3" style="font-size: 0.75rem;" onclick="this.parentElement.remove()"></button>
        </div>
    `;
    
    $('.sleek-toast-container').append(toastHtml);
    
    const $toast = $('.sleek-toast-container .sleek-toast').last();
    setTimeout(function() {
        $toast.animate({ opacity: 0, transform: 'translate(-50%, -20px)' }, 400, function() {
            $(this).slideUp(300, function() {
                $(this).remove();
            });
        });
    }, 4500);
};
