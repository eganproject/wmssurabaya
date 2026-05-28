<!DOCTYPE html>
<html lang="en">

<head>
    <base href="">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="login-url" content="{{ route('login') }}" />
    <meta name="page-expired-url" content="{{ route('page-expired') }}" />
    <title>@yield('title', config('app.name'))</title>
    <link rel="shortcut icon" href="{{ asset('metronic/media/logos/favicon.png') }}" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
		<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
    <link href="{{ asset('metronic/css/style.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('metronic/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('metronic/plugins/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
    <style>
        .select2-container .select2-selection--single {
            height: 42px;
            padding: 0.5rem 1rem;
            border-radius: 0.475rem;
            border: 1px solid #e4e6ef;
        }

        .form-select.form-select-solid+.select2-container .select2-selection--single {
            background-color: #f5f8fa;
            border-color: #f5f8fa;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 30px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
            right: 12px;
        }

        .select2-container .select2-selection--multiple {
            min-height: 42px;
            border-radius: 0.475rem;
            border: 1px solid #e4e6ef;
        }

        .modal {
            --bs-modal-margin: 0.75rem;
        }

        .modal-dialog {
            max-width: min(calc(100% - 1.5rem), var(--bs-modal-width, 500px));
        }

        .modal-dialog.modal-lg {
            --bs-modal-width: 1100px;
        }

        .modal-dialog.modal-xl {
            --bs-modal-width: 1400px;
            width: min(96vw, 1400px);
            max-width: calc(100vw - 1.5rem) !important;
        }

        .modal-dialog.mw-500px {
            --bs-modal-width: 640px;
            width: min(94vw, 640px);
            max-width: calc(100vw - 1.5rem) !important;
        }

        .modal-dialog.mw-650px {
            --bs-modal-width: 820px;
            width: min(94vw, 820px);
            max-width: calc(100vw - 1.5rem) !important;
        }

        .modal-dialog.mw-700px {
            --bs-modal-width: 920px;
            width: min(94vw, 920px);
            max-width: calc(100vw - 1.5rem) !important;
        }

        .modal-dialog.mw-900px {
            --bs-modal-width: 1200px;
            width: min(96vw, 1200px);
            max-width: calc(100vw - 1.5rem) !important;
        }

        .modal .modal-content,
        .modal-dialog-scrollable .modal-content {
            max-height: calc(100dvh - 1.5rem);
        }

        .modal .modal-body {
            overflow-y: auto;
        }

        .modal-dialog.mw-900px .modal-body,
        .modal-dialog.modal-lg .modal-body,
        .modal-dialog.modal-xl .modal-body {
            padding-left: clamp(1rem, 2vw, 2.25rem) !important;
            padding-right: clamp(1rem, 2vw, 2.25rem) !important;
        }

        .modal-dialog-scrollable .modal-body,
        .modal-body.scroll-y {
            overscroll-behavior: contain;
        }

        .modal .select2-container {
            max-width: 100%;
        }

        .modal .select2-container--open,
        .select2-container--open {
            z-index: 1065;
        }

        .select2-dropdown {
            z-index: 1066;
        }

        .flatpickr-calendar {
            z-index: 1067 !important;
        }

        .flatpickr-apply-footer {
            border-top: 1px solid #eff2f5;
            display: flex;
            justify-content: flex-end;
            padding: 0.65rem;
        }

        @media (max-width: 575.98px) {
            .modal-dialog,
            .modal-dialog.modal-lg,
            .modal-dialog.modal-xl,
            .modal-dialog.mw-500px,
            .modal-dialog.mw-650px,
            .modal-dialog.mw-700px,
            .modal-dialog.mw-900px {
                width: calc(100% - 1rem);
                max-width: calc(100% - 1rem);
                margin: 0.5rem auto;
            }

            .modal-content {
                max-height: calc(100dvh - 1rem);
            }

            .modal-body {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
                overflow-x: hidden;
            }

            .modal-footer {
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .modal-footer .btn {
                flex: 1 1 auto;
            }
        }
    </style>
    @stack('styles')
    @yield('styles')
    <meta name="description" content="{{ config('app.name') }} dashboard" />
    <meta name="keywords" content="import analytics,dashboard,analytics" />
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="website" />
</head>

<body id="kt_body" class="header-fixed header-tablet-and-mobile-fixed toolbar-enabled">
    <div class="d-flex flex-column flex-root">
        <!--begin::Page-->
        <div class="page d-flex flex-row flex-column-fluid">
            <div class="wrapper d-flex flex-column flex-row-fluid" id="kt_wrapper">
                @include('layouts.partials.header')
                @include('layouts.partials.toolbar')

                {{-- <div class="toolbar py-5 py-lg-5" id="kt_toolbar">
                    <div id="kt_toolbar_container" class="container-xxl d-flex flex-stack flex-wrap">
                        <div class="page-title d-flex flex-column me-3">
                            <h1 class="d-flex text-dark fw-bolder my-1 fs-3">@yield('page_title', 'Dashboard')</h1>
                            <ul class="breadcrumb breadcrumb-dot fw-bold text-gray-600 fs-7 my-1">
                                @yield('page_breadcrumbs')
                            </ul>
                        </div>
                        <div class="d-flex align-items-center gap-2 my-1">
                            @yield('page_actions')
                        </div>
                    </div>
                </div> --}}

                <div id="kt_content_container" class="d-flex flex-column-fluid align-items-start container-xxl">
                    <!--begin::Post-->
                    <div class="content flex-row-fluid" id="kt_content">
                        @yield('content')

                    </div>
                </div>

                @include('layouts.partials.footer')
            </div>
        </div>
    </div>

    <script src="{{ asset('metronic/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('metronic/js/scripts.bundle.js') }}"></script>
    <script src="{{ asset('metronic/plugins/custom/datatables/datatables.bundle.js') }}"></script>
    <script>
        (function () {
            const applyModalStaticBackdrop = (modalEl) => {
                if (!modalEl || !modalEl.setAttribute) return;
                modalEl.setAttribute('data-bs-backdrop', 'static');
            };

            const pageExpiredUrl = document.querySelector('meta[name="page-expired-url"]')?.getAttribute('content') || '/page-expired';
            const isScanOutDesktopPage = () => window.location.pathname.startsWith('/admin/outbound/scan-out');

            const redirectToPageExpired = () => {
                window.location.href = pageExpiredUrl;
            };

            const isInsideModal = (el) => el?.closest?.('.modal') || null;

            const activeScrollableModalBody = (modalEl) => {
                if (!modalEl) return null;
                return modalEl.querySelector('.modal-body.scroll-y, .modal-dialog-scrollable .modal-body, .modal-body');
            };

            const patchSelect2ForModals = () => {
                if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2 || jQuery.fn.select2.__modalResponsivePatched) {
                    return;
                }

                const originalSelect2 = jQuery.fn.select2;
                jQuery.fn.select2 = function (...args) {
                    if (args.length === 0 || (args[0] && typeof args[0] === 'object' && !Array.isArray(args[0]))) {
                        const incoming = args[0] || {};
                        // Gunakan document.body agar posisi dropdown dihitung dari viewport,
                        // tidak terpengaruh oleh scroll pada elemen .modal itu sendiri.
                        const insideModal = this.toArray().some(el => isInsideModal(el));
                        if (insideModal && !incoming.dropdownParent) {
                            args[0] = { ...incoming, dropdownParent: jQuery(document.body) };
                        }
                    }

                    return originalSelect2.apply(this, args);
                };

                Object.keys(originalSelect2).forEach((key) => {
                    jQuery.fn.select2[key] = originalSelect2[key];
                });
                jQuery.fn.select2.__modalResponsivePatched = true;

                // Bootstrap 5 modal mempunyai focus-trap: ketika fokus berpindah ke elemen
                // di luar modal (termasuk dropdown select2 yang di-render di document.body),
                // Bootstrap memanggil element.focus() kembali ke modal sehingga dropdown tertutup.
                // Fix: intercept focusin di capture phase (sebelum Bootstrap handler berjalan)
                // dan hentikan propagasi jika targetnya adalah bagian dari select2 dropdown.
                if (!window.__select2FocusTrapFixApplied) {
                    document.addEventListener('focusin', function (e) {
                        if (e.target && e.target.closest &&
                            e.target.closest('.select2-container, .select2-dropdown, .select2-search, .select2-results')) {
                            e.stopImmediatePropagation();
                        }
                    }, { capture: true });
                    window.__select2FocusTrapFixApplied = true;
                }
            };

            const patchFlatpickrForModals = () => {
                if (!window.flatpickr || window.flatpickr.__modalResponsivePatched) {
                    return;
                }

                const originalFlatpickr = window.flatpickr;
                const asFlatpickrHooks = (hook) => Array.isArray(hook) ? hook : (hook ? [hook] : []);
                const addFlatpickrApplyButton = (instance) => {
                    const calendar = instance?.calendarContainer;
                    if (!calendar || calendar.querySelector('.flatpickr-apply-footer')) return;

                    const footer = document.createElement('div');
                    footer.className = 'flatpickr-apply-footer';
                    footer.innerHTML = '<button type="button" class="btn btn-sm btn-primary">Apply</button>';
                    footer.querySelector('button')?.addEventListener('click', () => instance.close());
                    calendar.appendChild(footer);
                };
                const repositionFlatpickr = (instance) => {
                    requestAnimationFrame(() => {
                        if (instance?.isOpen) {
                            instance._positionCalendar?.();
                        }
                    });
                };
                const bindFlatpickrToModal = (instance) => {
                    const modalEl = isInsideModal(instance?.input);
                    if (!modalEl || instance.__modalResponsiveBound) return;

                    const reposition = () => repositionFlatpickr(instance);
                    const modalBody = activeScrollableModalBody(modalEl);

                    modalBody?.addEventListener('scroll', reposition, { passive: true });
                    modalEl.addEventListener('scroll', reposition, true);
                    modalEl.addEventListener('shown.bs.modal', reposition);
                    instance.input?.addEventListener('focus', reposition);
                    instance.input?.addEventListener('click', reposition);
                    window.addEventListener('resize', reposition);

                    instance.__modalResponsiveBound = true;
                };
                const patchedFlatpickr = function (selector, config = {}) {
                    if (!config || typeof config !== 'object' || Array.isArray(config)) {
                        return originalFlatpickr(selector, config);
                    }

                    const elements = typeof selector === 'string'
                        ? Array.from(document.querySelectorAll(selector))
                        : (selector instanceof Element ? [selector] : Array.from(selector || []));

                    const shouldPatch = elements.some((el) => isInsideModal(el));
                    config = {
                        ...config,
                        closeOnSelect: config.closeOnSelect ?? false,
                        onReady: [
                            ...asFlatpickrHooks(config.onReady),
                            (_selectedDates, _dateStr, instance) => addFlatpickrApplyButton(instance),
                        ],
                    };

                    if (shouldPatch) {
                        config = {
                            ...config,
                            // Gunakan document.body agar kalender diposisikan dari viewport,
                            // tidak terpengaruh oleh scroll pada elemen .modal itu sendiri.
                            ...(!config.appendTo ? { appendTo: document.body } : {}),
                            onReady: [
                                ...asFlatpickrHooks(config.onReady),
                                (_selectedDates, _dateStr, instance) => {
                                    bindFlatpickrToModal(instance);
                                    addFlatpickrApplyButton(instance);
                                    instance.calendarContainer?.addEventListener('mousedown', (event) => event.stopPropagation());
                                    instance.calendarContainer?.addEventListener('click', (event) => event.stopPropagation());
                                },
                            ],
                            onOpen: [
                                ...asFlatpickrHooks(config.onOpen),
                                (_selectedDates, _dateStr, instance) => {
                                    bindFlatpickrToModal(instance);
                                    repositionFlatpickr(instance);
                                },
                            ],
                            onMonthChange: [
                                ...asFlatpickrHooks(config.onMonthChange),
                                (_selectedDates, _dateStr, instance) => repositionFlatpickr(instance),
                            ],
                            onYearChange: [
                                ...asFlatpickrHooks(config.onYearChange),
                                (_selectedDates, _dateStr, instance) => repositionFlatpickr(instance),
                            ],
                        };
                    }

                    return originalFlatpickr(selector, config);
                };

                Object.keys(originalFlatpickr).forEach((key) => {
                    patchedFlatpickr[key] = originalFlatpickr[key];
                });
                patchedFlatpickr.__modalResponsivePatched = true;
                window.flatpickr = patchedFlatpickr;
            };

            const repositionOpenFlatpickrs = (modalEl) => {
                if (!modalEl) return;
                modalEl.querySelectorAll('input.flatpickr-input').forEach((input) => {
                    input._flatpickr?._positionCalendar?.();
                });
            };

            const bindModalResponsiveEvents = (modalEl) => {
                if (!modalEl || modalEl.__responsiveEventsBound) return;
                const modalBody = activeScrollableModalBody(modalEl);
                const onScroll = () => {
                    repositionOpenFlatpickrs(modalEl);
                    window.jQuery?.('.select2-hidden-accessible').select2?.('close');
                };
                // Tangani scroll di modal-body (form punya scrollbar sendiri)
                modalBody?.addEventListener('scroll', onScroll, { passive: true });
                // Tangani scroll di .modal itu sendiri (terjadi saat dialog lebih tinggi dari viewport)
                modalEl.addEventListener('scroll', onScroll, { passive: true });
                modalEl.addEventListener('shown.bs.modal', () => {
                    repositionOpenFlatpickrs(modalEl);
                });
                modalEl.__responsiveEventsBound = true;
            };

            const enforceModalBackdrops = () => {
                document.querySelectorAll('.modal').forEach((modalEl) => {
                    applyModalStaticBackdrop(modalEl);
                    bindModalResponsiveEvents(modalEl);
                });
            };

            const enforceSweetalertBackdrop = () => {
                if (!window.Swal || window.__swalNoOutsideClickApplied) {
                    return;
                }
                const originalFire = window.Swal.fire.bind(window.Swal);
                window.Swal.fire = function (...args) {
                    if (args.length === 1 && args[0] && typeof args[0] === 'object' && !Array.isArray(args[0])) {
                        const options = { ...args[0], allowOutsideClick: false };
                        return originalFire(options);
                    }
                    const options = {
                        title: args[0],
                        text: args[1],
                        icon: args[2],
                        allowOutsideClick: false,
                    };
                    return originalFire(options);
                };
                window.__swalNoOutsideClickApplied = true;
            };

            enforceModalBackdrops();
            enforceSweetalertBackdrop();
            patchSelect2ForModals();
            patchFlatpickrForModals();

            if (!window.__pageExpiredRedirectPatched) {
                const originalFetch = window.fetch?.bind(window);
                if (originalFetch) {
                    window.fetch = async (...args) => {
                        const response = await originalFetch(...args);
                        if (response.status === 419) {
                            if (isScanOutDesktopPage()) {
                                return response;
                            }
                            redirectToPageExpired();
                        }
                        return response;
                    };
                }

                if (window.jQuery) {
                    jQuery(document).ajaxError((_event, xhr) => {
                        if (xhr?.status === 419) {
                            if (isScanOutDesktopPage()) {
                                return;
                            }
                            redirectToPageExpired();
                        }
                    });
                }
                window.__pageExpiredRedirectPatched = true;
            }

            if (!window.AppSwal) {
                window.AppSwal = {
                    confirm: (title, options = {}) => {
                        if (!window.Swal) {
                            return Promise.resolve(window.confirm(title));
                        }
                        const confirmButtonText = options.confirmButtonText || 'Ya';
                        const cancelButtonText = options.cancelButtonText || 'Batal';
                        const confirmButtonType = options.confirmButtonType || 'primary';
                        return window.Swal.fire({
                            title,
                            icon: options.icon || 'warning',
                            showCancelButton: true,
                            confirmButtonText,
                            cancelButtonText,
                            buttonsStyling: false,
                            customClass: {
                                confirmButton: `btn btn-${confirmButtonType}`,
                                cancelButton: 'btn btn-light',
                            },
                        }).then((result) => result.isConfirmed === true);
                    },
                    error: (message, title = 'Error') => {
                        if (window.Swal) {
                            return window.Swal.fire(title, message || 'Terjadi kesalahan', 'error');
                        }
                        alert(message || 'Terjadi kesalahan');
                    },
                };
            }

            document.addEventListener('DOMContentLoaded', () => {
                patchSelect2ForModals();
                patchFlatpickrForModals();
                enforceModalBackdrops();
                if (document.body && !window.__modalBackdropObserver) {
                    const observer = new MutationObserver((mutations) => {
                        mutations.forEach((mutation) => {
                            mutation.addedNodes.forEach((node) => {
                                if (!node || node.nodeType !== 1) return;
                                if (node.classList?.contains('modal')) {
                                    applyModalStaticBackdrop(node);
                                    bindModalResponsiveEvents(node);
                                }
                                node.querySelectorAll?.('.modal')?.forEach((modalEl) => {
                                    applyModalStaticBackdrop(modalEl);
                                    bindModalResponsiveEvents(modalEl);
                                });
                            });
                        });
                    });
                    observer.observe(document.body, { childList: true, subtree: true });
                    window.__modalBackdropObserver = observer;
                }
            });
        })();
    </script>
    @stack('scripts')
    @yield('scripts')
</body>

</html>
