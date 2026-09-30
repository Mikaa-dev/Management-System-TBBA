/**
 * Skrip Utama & Utiliti Antaramuka (Vanilla JavaScript)
 * Syarikat: The Bridge Business Alliance (TBBA)
 */

// Global CSRF Token Auto-Injector for Fetch API & AJAX
(function() {
    const _originalFetch = window.fetch;

    window.fetch = function(resource, init) {

        const requestMethod = (
            init?.method ||
            (resource instanceof Request ? resource.method : 'GET')
        ).toUpperCase();

        let requestUrl = null;

        try {

            requestUrl = new URL(
                resource instanceof Request
                    ? resource.url
                    : String(resource),
                window.location.href
            );

        } catch (error) {

            console.warn(
                '[CSRF] Unable to resolve fetch URL:',
                error
            );

        }


        /*
         * Jangan modify request Firebase,
         * Google API, CDN atau third party.
         *
         * CSRF hanya untuk PHP app TBBA.
         */
        if (
            requestMethod === 'POST' &&
            requestUrl?.origin === window.location.origin &&
            init
        ) {

            const csrfMeta =
                document.querySelector(
                    'meta[name="csrf-token"]'
                );

            const csrfVal =
                (
                    csrfMeta
                        ? csrfMeta.getAttribute('content')
                        : null
                )
                || window.csrfToken;


            if (csrfVal) {

                /*
                 * FormData
                 */
                if (init.body instanceof FormData) {

                    if (!init.body.has('csrf_token')) {

                        init.body.append(
                            'csrf_token',
                            csrfVal
                        );

                    }


                /*
                 * URLSearchParams
                 */
                } else if (
                    init.body instanceof URLSearchParams
                ) {

                    if (!init.body.has('csrf_token')) {

                        init.body.append(
                            'csrf_token',
                            csrfVal
                        );

                    }


                /*
                 * application/x-www-form-urlencoded
                 */
                } else if (
                    typeof init.body === 'string'
                ) {

                    const headers =
                        new Headers(
                            init.headers || {}
                        );

                    const contentType =
                        headers.get('Content-Type') || '';


                    if (
                        contentType.includes(
                            'application/x-www-form-urlencoded'
                        ) &&
                        init.body.indexOf(
                            'csrf_token='
                        ) === -1
                    ) {

                        init.body +=
                            (init.body ? '&' : '') +
                            'csrf_token=' +
                            encodeURIComponent(csrfVal);

                    }

                }

            }

        }


        return _originalFetch.call(
            this,
            resource,
            init
        );

    };

})();


const App = {

    /*
    |--------------------------------------------------------------------------
    | Toast Notification
    |--------------------------------------------------------------------------
    */
    showToast: function(type, message) {

        const container =
            document.getElementById(
                'toast-container'
            );

        if (!container) {
            return;
        }


        const allowedTypes = [
            'success',
            'warning',
            'error'
        ];


        /*
         * Support old accidental argument order
         */
        if (
            !allowedTypes.includes(type) &&
            allowedTypes.includes(message)
        ) {

            [type, message] =
                [message, type];

        }


        const toast =
            document.createElement('div');


        const safeType =
            allowedTypes.includes(type)
                ? type
                : 'error';


        toast.className =
            `toast ${safeType}`;


        const toastMeta = {

            success: [
                'fa-circle-check',
                '#10B981',
                'Success'
            ],

            warning: [
                'fa-triangle-exclamation',
                '#F59E0B',
                'Warning'
            ],

            error: [
                'fa-circle-exclamation',
                '#EF4444',
                'Notice / Error'
            ]

        }[safeType];


        const icon = `
            <i
                class="fa-solid ${toastMeta[0]}"
                style="
                    color:${toastMeta[1]};
                    font-size:20px;
                    flex-shrink:0;
                    margin-top:2px;
                "
            ></i>
        `;


        toast.innerHTML = `

            ${icon}

            <div
                style="
                    flex:1;
                    min-width:0;
                "
            >

                <strong
                    style="
                        display:block;
                        font-size:13px;
                        margin-bottom:4px;
                        color:#FFFFFF;
                    "
                >
                    ${toastMeta[2]}
                </strong>

                <span
                    data-toast-message
                    style="
                        font-size:12px;
                        line-height:1.5;
                        color:#E2E8F0;
                        display:block;
                    "
                ></span>

            </div>


            <button
                onclick="this.parentElement.remove()"
                style="
                    background:none;
                    border:none;
                    color:#94A3B8;
                    cursor:pointer;
                    font-size:20px;
                    line-height:1;
                    padding:0 4px;
                    flex-shrink:0;
                    align-self:flex-start;
                    transition:color .2s;
                "
                onmouseover="
                    this.style.color='#FFFFFF'
                "
                onmouseout="
                    this.style.color='#94A3B8'
                "
            >
                &times;
            </button>

        `;


        toast
            .querySelector(
                '[data-toast-message]'
            )
            .textContent =
                String(message ?? '');


        container.appendChild(toast);


        /*
         * Auto remove selepas 4.5 saat
         */
        setTimeout(() => {

            toast.style.animation =
                'fadeOut 0.3s forwards';


            setTimeout(() => {

                toast.remove();

            }, 300);

        }, 4500);

    },


    /*
    |--------------------------------------------------------------------------
    | Modal
    |--------------------------------------------------------------------------
    */
    openModal: function(modalId) {

        const modal =
            document.getElementById(modalId);

        if (modal) {

            modal.classList.add('active');

            modal.style.display =
                'flex';

            document.body.style.overflow =
                'hidden';

        }

    },


    closeModal: function(modalId) {

        const modal =
            document.getElementById(modalId);

        if (modal) {

            modal.classList.remove('active');

            modal.style.display =
                'none';

            document.body.style.overflow =
                '';

        }

    },


    /*
    |--------------------------------------------------------------------------
    | Universal Confirmation Modal
    |--------------------------------------------------------------------------
    */
    confirm: function(
        title,
        message,
        confirmBtnText = 'Confirm',
        cancelBtnText = 'Cancel'
    ) {

        return new Promise((resolve) => {

            let modal =
                document.getElementById(
                    'universalConfirmModal'
                );


            if (!modal) {

                modal =
                    document.createElement(
                        'div'
                    );


                modal.id =
                    'universalConfirmModal';


                modal.style.cssText = `
                    position:fixed;
                    inset:0;
                    background:rgba(15,23,42,.65);
                    backdrop-filter:blur(4px);
                    z-index:99999;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    opacity:0;
                    pointer-events:none;
                    transition:opacity .25s ease;
                    padding:20px;
                `;


                modal.innerHTML = `

                    <div
                        class="modal-box"
                        style="
                            background:#FFFFFF;
                            border-radius:20px;
                            width:100%;
                            max-width:440px;
                            overflow:hidden;
                            box-shadow:
                                0 25px 50px -12px
                                rgba(0,0,0,.25);
                            text-align:center;
                            padding:28px 24px;
                            transform:scale(.95);
                            transition:
                                transform .25s ease;
                        "
                    >

                        <div
                            style="
                                width:60px;
                                height:60px;
                                border-radius:50%;
                                background:#FEF2F2;
                                color:#DC2626;
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                font-size:28px;
                                margin:0 auto 16px;
                            "
                        >
                            <i
                                class="
                                    fa-solid
                                    fa-triangle-exclamation
                                "
                            ></i>
                        </div>


                        <h3
                            id="ucmTitle"
                            style="
                                font-size:18px;
                                font-weight:800;
                                color:#0F172A;
                                margin:0 0 10px;
                            "
                        ></h3>


                        <p
                            id="ucmMessage"
                            style="
                                font-size:13px;
                                color:#475569;
                                line-height:1.6;
                                margin:0 0 24px;
                            "
                        ></p>


                        <div
                            style="
                                display:flex;
                                gap:12px;
                                justify-content:center;
                            "
                        >

                            <button
                                type="button"
                                id="ucmCancelBtn"
                                style="
                                    flex:1;
                                    background:#F1F5F9;
                                    color:#475569;
                                    border:none;
                                    padding:12px 18px;
                                    border-radius:12px;
                                    font-size:13px;
                                    font-weight:700;
                                    cursor:pointer;
                                "
                            >
                                Cancel
                            </button>


                            <button
                                type="button"
                                id="ucmConfirmBtn"
                                style="
                                    flex:1;
                                    background:#DC2626;
                                    color:#FFFFFF;
                                    border:none;
                                    padding:12px 18px;
                                    border-radius:12px;
                                    font-size:13px;
                                    font-weight:700;
                                    cursor:pointer;
                                    box-shadow:
                                        0 4px 12px
                                        rgba(220,38,38,.3);
                                "
                            >
                                Confirm
                            </button>

                        </div>

                    </div>

                `;


                document.body.appendChild(
                    modal
                );

            }


            document
                .getElementById(
                    'ucmTitle'
                )
                .textContent =
                    title;


            document
                .getElementById(
                    'ucmMessage'
                )
                .textContent =
                    message;


            const cancelBtn =
                document.getElementById(
                    'ucmCancelBtn'
                );


            const confirmBtn =
                document.getElementById(
                    'ucmConfirmBtn'
                );


            cancelBtn.textContent =
                cancelBtnText;


            confirmBtn.textContent =
                confirmBtnText;


            const cleanup = () => {

                modal.style.opacity =
                    '0';

                modal.style.pointerEvents =
                    'none';


                const box =
                    modal.querySelector(
                        '.modal-box'
                    );


                if (box) {

                    box.style.transform =
                        'scale(.95)';

                }


                document.body.style.overflow =
                    '';

            };


            const onCancel = () => {

                cleanup();


                cancelBtn.removeEventListener(
                    'click',
                    onCancel
                );


                confirmBtn.removeEventListener(
                    'click',
                    onConfirm
                );


                resolve(false);

            };


            const onConfirm = () => {

                cleanup();


                cancelBtn.removeEventListener(
                    'click',
                    onCancel
                );


                confirmBtn.removeEventListener(
                    'click',
                    onConfirm
                );


                resolve(true);

            };


            cancelBtn.addEventListener(
                'click',
                onCancel
            );


            confirmBtn.addEventListener(
                'click',
                onConfirm
            );


            /*
             * Show modal
             */
            modal.style.opacity =
                '1';

            modal.style.pointerEvents =
                'auto';


            const box =
                modal.querySelector(
                    '.modal-box'
                );


            if (box) {

                box.style.transform =
                    'scale(1)';

            }


            document.body.style.overflow =
                'hidden';

        });

    },


    /*
    |--------------------------------------------------------------------------
    | Standard AJAX POST
    |--------------------------------------------------------------------------
    */
    post: async function(
        url,
        formData,
        btnElement = null
    ) {

        let originalText = '';


        if (btnElement) {

            btnElement.disabled =
                true;


            originalText =
                btnElement.innerHTML;


            btnElement.innerHTML = `
                <i
                    class="
                        fa-solid
                        fa-spinner
                        fa-spin
                    "
                ></i>
                Processing...
            `;

        }


        try {

            const response =
                await fetch(
                    url,
                    {

                        method: 'POST',

                        headers: {

                            'X-Requested-With':
                                'XMLHttpRequest'

                        },

                        body: formData

                    }
                );


            const result =
                await response.json();


            if (btnElement) {

                btnElement.disabled =
                    false;

                btnElement.innerHTML =
                    originalText;

            }


            if (
                result.status ===
                'success'
            ) {

                App.showToast(
                    'success',
                    result.message
                );

                return result;

            }


            if (
                result.status ===
                'out_of_range'
            ) {

                return result;

            }


            App.showToast(
                'error',
                result.message ||
                'An unknown error occurred.'
            );


            return null;


        } catch (error) {

            console.error(
                'AJAX Error:',
                error
            );


            if (btnElement) {

                btnElement.disabled =
                    false;

                btnElement.innerHTML =
                    originalText;

            }


            App.showToast(
                'error',
                'Failed to connect to the server. Please check your internet connection or server logs.'
            );


            return null;

        }

    },


    /*
    |--------------------------------------------------------------------------
    | Live Clock
    |--------------------------------------------------------------------------
    */
    initLiveClock: function() {

        const clockEl =
            document.getElementById(
                'liveClockDisplay'
            );


        if (!clockEl) {
            return;
        }


        const updateClock = () => {

            const now =
                new Date();


            const options = {

                day: '2-digit',

                month: 'short',

                year: 'numeric'

            };


            const dateStr =
                now.toLocaleDateString(
                    'en-GB',
                    options
                );


            const timeStr =
                now.toLocaleTimeString(
                    'en-US',
                    {

                        hour: '2-digit',

                        minute: '2-digit',

                        hour12: true

                    }
                );


            if (
                window.innerWidth <=
                768
            ) {

                clockEl.textContent =
                    `${timeStr}`;

            } else {

                clockEl.textContent =
                    `${dateStr}, ${timeStr}`;

            }

        };


        updateClock();


        setInterval(
            updateClock,
            1000
        );

    },


    /*
    |--------------------------------------------------------------------------
    | Sidebar Toggle
    |--------------------------------------------------------------------------
    */
    initSidebar: function() {

        const toggleBtn =
            document.getElementById(
                'sidebarToggle'
            );


        const sidebar =
            document.getElementById(
                'appSidebar'
            );


        if (
            toggleBtn &&
            sidebar
        ) {

            /*
             * Show mobile button
             */
            if (
                window.innerWidth <=
                992
            ) {

                toggleBtn.style.display =
                    'inline-flex';

            }


            toggleBtn.addEventListener(
                'click',
                () => {

                    sidebar.classList.toggle(
                        'open'
                    );

                }
            );


            window.addEventListener(
                'resize',
                () => {

                    if (
                        window.innerWidth <=
                        992
                    ) {

                        toggleBtn.style.display =
                            'inline-flex';

                    } else {

                        toggleBtn.style.display =
                            'none';


                        sidebar.classList.remove(
                            'open'
                        );

                    }

                }
            );

        }

    },


    /*
    |--------------------------------------------------------------------------
    | Service Worker & PWA
    |--------------------------------------------------------------------------
    */
    initPWA: function() {

        /*
         * Service Worker
         */
        if (
            'serviceWorker'
            in navigator
        ) {

            window.addEventListener(
                'load',
                async () => {

                    try {

                        const registration =
                            await navigator
                                .serviceWorker
                                .register(
                                    '/sw.js',
                                    {

                                        scope: '/',

                                        updateViaCache:
                                            'none'

                                    }
                                );


                        console.log(
                            '[PWA] Service Worker registered:',
                            registration.scope
                        );


                        /*
                         * Check versi Service Worker terbaru.
                         */
                        registration
                            .update()
                            .catch(
                                (error) => {

                                    console.warn(
                                        '[PWA] Service Worker update check failed:',
                                        error
                                    );

                                }
                            );


                        /*
                         * Detect update Service Worker.
                         *
                         * Jangan auto-refresh sebab staff
                         * mungkin tengah isi form.
                         */
                        registration.addEventListener(
                            'updatefound',
                            () => {

                                const installingWorker =
                                    registration.installing;


                                if (
                                    !installingWorker
                                ) {

                                    return;

                                }


                                installingWorker
                                    .addEventListener(
                                        'statechange',
                                        () => {

                                            if (
                                                installingWorker.state ===
                                                    'installed'
                                                &&
                                                navigator
                                                    .serviceWorker
                                                    .controller
                                            ) {

                                                console.log(
                                                    '[PWA] New TBBA app assets are ready and will be used on the next navigation/reload.'
                                                );

                                            }

                                        }
                                    );

                            }
                        );


                    } catch (error) {

                        console.error(
                            '[PWA] Service Worker registration failed:',
                            error
                        );

                    }

                }
            );

        }


        /*
         * Chrome / Android
         * Add To Home Screen
         */
        window.addEventListener(
            'beforeinstallprompt',
            (e) => {

                e.preventDefault();


                window.deferredPrompt =
                    e;


                document
                    .querySelectorAll(
                        '.pwa-install-trigger'
                    )
                    .forEach(
                        btn => {

                            btn.style.display =
                                'inline-flex';

                        }
                    );


                const banner =
                    document.getElementById(
                        'pwaInstallBanner'
                    );


                if (
                    banner &&
                    !localStorage.getItem(
                        'pwa_banner_dismissed'
                    )
                ) {

                    banner.style.display =
                        'flex';

                }

            }
        );


        /*
         * Detect iOS
         */
        const isIOS =

            /iPad|iPhone|iPod/
                .test(
                    navigator.userAgent
                )

            && !window.MSStream;


        /*
         * Detect installed PWA
         */
        const isStandalone =

            window.matchMedia(
                '(display-mode: standalone)'
            ).matches

            ||

            window.navigator
                .standalone === true;


        /*
         * Not installed
         */
        if (!isStandalone) {

            /*
             * iPhone / iPad
             */
            if (isIOS) {

                document
                    .querySelectorAll(
                        '.pwa-install-trigger'
                    )
                    .forEach(
                        btn => {

                            btn.style.display =
                                'inline-flex';

                        }
                    );


                const banner =
                    document.getElementById(
                        'pwaInstallBanner'
                    );


                if (
                    banner &&
                    !localStorage.getItem(
                        'pwa_banner_dismissed'
                    )
                ) {

                    banner.style.display =
                        'flex';

                }

            }


        /*
         * Already installed
         */
        } else {

            document
                .querySelectorAll(
                    '.pwa-install-trigger'
                )
                .forEach(
                    btn => {

                        btn.style.display =
                            'none';

                    }
                );


            const banner =
                document.getElementById(
                    'pwaInstallBanner'
                );


            if (banner) {

                banner.style.display =
                    'none';

            }

        }

    },


    /*
    |--------------------------------------------------------------------------
    | Install PWA
    |--------------------------------------------------------------------------
    */
    installPWA: function() {

        const isIOS =

            /iPad|iPhone|iPod/
                .test(
                    navigator.userAgent
                )

            && !window.MSStream;


        /*
         * Chrome / Android prompt
         */
        if (
            window.deferredPrompt
        ) {

            window
                .deferredPrompt
                .prompt();


            window
                .deferredPrompt
                .userChoice
                .then(
                    (choiceResult) => {

                        if (
                            choiceResult.outcome ===
                            'accepted'
                        ) {

                            App.showToast(
                                'success',
                                'The TBBA ERP app is being installed to your home screen!'
                            );

                        }


                        window.deferredPrompt =
                            null;


                        const banner =
                            document.getElementById(
                                'pwaInstallBanner'
                            );


                        if (banner) {

                            banner.style.display =
                                'none';

                        }

                    }
                );


        /*
         * iOS installation guide
         */
        } else if (isIOS) {

            const modalContent =
                document.getElementById(
                    'pwaModalContent'
                );


            if (modalContent) {

                modalContent.innerHTML = `

                    <div
                        style="
                            text-align:center;
                            margin-bottom:16px;
                        "
                    >

                        <i
                            class="
                                fa-brands
                                fa-apple
                            "
                            style="
                                font-size:44px;
                                color:#1E3A8A;
                            "
                        ></i>


                        <h4
                            style="
                                font-size:16px;
                                margin-top:8px;
                                color:#0F172A;
                                font-weight:700;
                            "
                        >
                            Installation Guide for iPhone / iPad
                        </h4>

                    </div>


                    <ol
                        style="
                            margin-left:20px;
                            line-height:1.8;
                            color:#334155;
                            font-size:13px;
                        "
                    >

                        <li>
                            Tap the
                            <strong>
                                Share
                            </strong>
                            button
                            (
                            <i
                                class="
                                    fa-solid
                                    fa-arrow-up-from-bracket
                                "
                                style="
                                    color:#2563EB;
                                "
                            ></i>
                            )
                            at the bottom of your Safari browser bar.
                        </li>


                        <li>
                            Scroll down and select
                            <strong>
                                "Add to Home Screen"
                            </strong>
                            (
                            <i
                                class="
                                    fa-regular
                                    fa-square-plus
                                "
                                style="
                                    color:#2563EB;
                                "
                            ></i>
                            ).
                        </li>


                        <li>
                            Tap
                            <strong>
                                "Add"
                            </strong>
                            in the top right corner of your screen.
                        </li>

                    </ol>


                    <div
                        style="
                            margin-top:16px;
                            background:#EFF6FF;
                            border:1px solid #BFDBFE;
                            padding:12px;
                            border-radius:8px;
                            font-size:12px;
                            color:#1E40AF;
                            line-height:1.5;
                        "
                    >

                        <i
                            class="
                                fa-solid
                                fa-circle-info
                            "
                        ></i>

                        The TBBA ERP app will appear as a clean icon
                        on your home screen and run in full-screen mode
                        without a browser bar!

                    </div>

                `;

            }


            App.openModal(
                'pwaInstallModal'
            );


        /*
         * Android manual fallback
         */
        } else {

            const modalContent =
                document.getElementById(
                    'pwaModalContent'
                );


            if (modalContent) {

                modalContent.innerHTML = `

                    <div
                        style="
                            text-align:center;
                            margin-bottom:16px;
                        "
                    >

                        <i
                            class="
                                fa-brands
                                fa-android
                            "
                            style="
                                font-size:44px;
                                color:#10B981;
                            "
                        ></i>


                        <h4
                            style="
                                font-size:16px;
                                margin-top:8px;
                                color:#0F172A;
                                font-weight:700;
                            "
                        >
                            Manual Installation Guide
                        </h4>

                    </div>


                    <p
                        style="
                            margin-bottom:12px;
                            font-size:13px;
                            color:#475569;
                        "
                    >

                        To install the system directly to your mobile device:

                    </p>


                    <ol
                        style="
                            margin-left:20px;
                            line-height:1.8;
                            color:#334155;
                            font-size:13px;
                        "
                    >

                        <li>
                            Tap the
                            <strong>
                                3 dots menu (⋮)
                            </strong>
                            in the top right corner of your
                            Chrome/Edge browser.
                        </li>


                        <li>
                            Select
                            <strong>
                                "Add to Home Screen"
                            </strong>
                            or
                            <strong>
                                "Install app"
                            </strong>.
                        </li>


                        <li>
                            Confirm by tapping the
                            <strong>
                                Install / Add
                            </strong>
                            button.
                        </li>

                    </ol>

                `;

            }


            App.openModal(
                'pwaInstallModal'
            );

        }

    },


    /*
    |--------------------------------------------------------------------------
    | Dismiss PWA Banner
    |--------------------------------------------------------------------------
    */
    dismissPWABanner: function() {

        const banner =
            document.getElementById(
                'pwaInstallBanner'
            );


        if (banner) {

            banner.style.display =
                'none';

        }


        localStorage.setItem(
            'pwa_banner_dismissed',
            'true'
        );

    }

};


/*
|--------------------------------------------------------------------------
| DOM Ready
|--------------------------------------------------------------------------
*/
document.addEventListener(
    'DOMContentLoaded',
    () => {

        App.initLiveClock();

        App.initSidebar();

        App.initPWA();


        /*
         * Auto inject CSRF hidden input
         * kepada semua form yang belum ada token.
         */
        const csrfMeta =
            document.querySelector(
                'meta[name="csrf-token"]'
            );


        const csrfVal =

            (
                csrfMeta
                    ? csrfMeta.getAttribute(
                        'content'
                    )
                    : null
            )

            || window.csrfToken;


        if (csrfVal) {

            document
                .querySelectorAll(
                    'form'
                )
                .forEach(
                    form => {

                        if (
                            !form.querySelector(
                                'input[name="csrf_token"]'
                            )
                        ) {

                            const input =
                                document.createElement(
                                    'input'
                                );


                            input.type =
                                'hidden';


                            input.name =
                                'csrf_token';


                            input.value =
                                csrfVal;


                            form.appendChild(
                                input
                            );

                        }

                    }
                );

        }

    }
);


/*
|--------------------------------------------------------------------------
| Back Forward Cache Security
|--------------------------------------------------------------------------
|
| Elakkan browser paparkan page authenticated lama selepas user logout.
|
*/
window.addEventListener(
    'pageshow',
    (event) => {

        if (
            event.persisted
        ) {

            window.location.reload();

        }

    }
);