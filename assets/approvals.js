document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('approval-confirm-dialog');
    const summary = dialog?.querySelector('[data-approval-summary]');
    const confirmButton = dialog?.querySelector('[data-approval-confirm-submit]');

    if (!(dialog instanceof HTMLDialogElement) || !(summary instanceof HTMLElement) || !(confirmButton instanceof HTMLButtonElement)) {
        return;
    }

    let pendingForm = null;
    let pendingButton = null;

    document.querySelectorAll('[data-approval-confirm]').forEach((button) => {
        button.addEventListener('click', (event) => {
            const form = button.closest('form');
            const card = button.closest('.approval-card');
            if (!(form instanceof HTMLFormElement) || !card) {
                return;
            }

            event.preventDefault();
            pendingForm = form;
            pendingButton = button;

            const requestType = card.querySelector('.request-title h2')?.textContent?.trim();
            const employeeName = card.querySelector('.employee-summary strong')?.textContent?.trim();
            const requestCode = card.querySelector('.request-title div > span')?.textContent?.trim();
            const requestSummary = [requestType, employeeName, requestCode].filter(Boolean).join(' - ');
            summary.textContent = requestSummary
                ? `Anda akan menyetujui ${requestSummary}.`
                : 'Pastikan informasi pengajuan sudah diperiksa sebelum melanjutkan.';
            dialog.showModal();
        });
    });

    dialog.querySelectorAll('[data-dialog-close]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    confirmButton.addEventListener('click', () => {
        if (pendingForm instanceof HTMLFormElement && pendingButton instanceof HTMLButtonElement) {
            pendingForm.requestSubmit(pendingButton);
        }
        dialog.close();
        pendingForm = null;
        pendingButton = null;
    });
});
