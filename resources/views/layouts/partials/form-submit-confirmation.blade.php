@push('scripts')
<script>
    (function () {
        const confirmationText = {
            title: 'Apakah Anda yakin?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, lanjutkan',
            cancelButtonText: 'Batal',
            allowOutsideClick: false,
            allowEscapeKey: false,
            allowEnterKey: false,
            focusConfirm: false,
        };

        const loadingConfig = {
            title: 'Memproses...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            allowEnterKey: false,
            didOpen: () => {
                Swal.showLoading();
            },
        };

        const isFormValid = (form) => form.noValidate
            || typeof form.checkValidity !== 'function'
            || form.checkValidity();

        const triggerSubmit = (form) => {
            // requestSubmit() dibatalkan diam-diam jika ada field yang tidak lolos
            // validasi bawaan browser, sehingga dialog "Memproses..." tidak pernah tertutup.
            if (!isFormValid(form)) {
                Swal.close();
                if (typeof form.reportValidity === 'function') form.reportValidity();
                return;
            }
            if (typeof form.requestSubmit === 'function') {
                form.dataset.swalBypass = 'true';
                form.requestSubmit();
            } else {
                const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
                form.dataset.swalBypass = 'true';
                form.dispatchEvent(submitEvent);
                if (!submitEvent.defaultPrevented) {
                    form.submit();
                }
            }
        };

        const bindConfirmation = (form) => {
            if (!form || form.dataset.swalConfirmBound === '1') {
                return;
            }
            form.dataset.swalConfirmBound = '1';
            form.addEventListener('submit', function (event) {
                if (form.dataset.swalBypass === 'true') {
                    form.dataset.swalBypass = 'false';
                    return;
                }
                event.preventDefault();
                event.stopImmediatePropagation();
                if (!isFormValid(form)) {
                    if (typeof form.reportValidity === 'function') form.reportValidity();
                    return;
                }
                Swal.fire(confirmationText).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire(loadingConfig);
                        setTimeout(() => {
                            triggerSubmit(form);
                        }, 1500);
                    }
                });
            }, true);
        };

        const observeForms = () => {
            document.querySelectorAll('form').forEach(bindConfirmation);
        };

        document.addEventListener('DOMContentLoaded', () => {
            observeForms();
            const observer = new MutationObserver(observeForms);
            observer.observe(document.body, { childList: true, subtree: true });
        });
    })();
</script>
@endpush
