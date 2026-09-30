/**
 * Firebase Push Notification — TBBA ERP Frontend Module
 * ======================================================
 * Fail ini menguruskan semua aspek push notification di bahagian frontend:
 *   1. Initialize Firebase App
 *   2. Request permission dari user
 *   3. Generate & simpan FCM token ke backend
 *   4. Handle foreground notification (custom toast bila app dibuka)
 *   5. Handle token refresh
 *
 * Cara include dalam HTML:
 *   1. Tambah Firebase SDK scripts dalam <head> atau sebelum </body>:
 *      <script src="https://www.gstatic.com/firebasejs/12.18.0/firebase-app-compat.js"></script>
 *      <script src="https://www.gstatic.com/firebasejs/12.18.0/firebase-messaging-compat.js"></script>
 *   2. Include fail ini selepas:
 *      <script src="assets/js/firebase-push.js"></script>
 *   3. Panggil init selepas page load:
 *      FirebasePush.init();
 */

const FirebasePush = (function() {
    'use strict';

    // ─── Configuration ────────────────────────────────────────────────────────
    // Nilai ini akan di-replace oleh PHP secara dinamik.
    // Lihat cara include dalam views/layouts/header.php (contoh diberi dalam setup guide).
    const fc = window.FIREBASE_CONFIG || {};
    const CONFIG = {
        apiKey:            fc.apiKey            || '',
        authDomain:        fc.authDomain        || '',
        projectId:         fc.projectId         || '',
        storageBucket:     fc.storageBucket     || '',
        messagingSenderId: fc.messagingSenderId || '',
        appId:             fc.appId             || '',
        vapidKey:          fc.vapidKey          || '',
    };

    // Keep authenticated requests on the exact current origin. Using APP_URL
    // here can cross from www to non-www and silently lose the PHP session.
    const BASE_URL = new URL('index.php', window.location.href).href;
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const CSRF_TOKEN = window.CSRF_TOKEN || (csrfMeta ? csrfMeta.content : '') || '';

    // State
    let messagingInstance = null;
    let currentToken = null;
    let isInitialized = false;
    let initializationPromise = null;
    let subscriptionSaved = false;
    let notificationFacade = null;
    let lastErrorMessage = '';

    // Storage is only a convenience; a browser storage restriction must not
    // turn a successfully saved server subscription into a failed one.
    function readLocalSetting(key) {
        try { return localStorage.getItem(key); } catch (error) { return null; }
    }

    function writeLocalSetting(key, value) {
        try {
            if (value === null) localStorage.removeItem(key);
            else localStorage.setItem(key, value);
        } catch (error) {
            console.warn('[FirebasePush] Local subscription storage is unavailable.');
        }
    }

    async function readServerResponse(response) {
        if (response.redirected) {
            throw new Error('Your login session has expired. Sign in again and retry.');
        }
        const contentType = response.headers.get('Content-Type') || '';
        if (!contentType.includes('application/json')) {
            throw new Error(`The push endpoint returned HTTP ${response.status} without JSON. Reload the app and sign in again.`);
        }
        const data = await response.json();
        if (!response.ok || data.status !== 'success') {
            throw new Error(data.message || `The push request failed (HTTP ${response.status}).`);
        }
        return data;
    }

    function setPushStatus(message) {
        const statusEl = document.getElementById('tbba-push-status');
        if (statusEl) statusEl.textContent = message;
    }

    function promiseWithTimeout(promise, timeoutMs, message) {
        let timeoutId;
        const timeout = new Promise((resolve, reject) => {
            timeoutId = window.setTimeout(() => reject(new Error(message)), timeoutMs);
        });
        return Promise.race([promise, timeout]).finally(() => window.clearTimeout(timeoutId));
    }

    function decodeVapidKey(value) {
        const padding = '='.repeat((4 - value.length % 4) % 4);
        const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(base64);
        return Uint8Array.from(raw, char => char.charCodeAt(0));
    }

    function hasNativeNotificationApi() {
        return 'Notification' in window
            && typeof window.Notification.requestPermission === 'function'
            && window.Notification.__tbbaFacade !== true;
    }

    function hasStandardPushApi() {
        return 'serviceWorker' in navigator && 'PushManager' in window;
    }

    // Recent iPhone Home Screen web apps expose standards-based PushManager,
    // but some WebKit contexts omit the Window Notification facade. Firebase's
    // compatibility SDK checks for that facade even though PushManager works.
    function installNotificationFacade(permission, force = false) {
        if (hasNativeNotificationApi() && !force) return true;
        if (!notificationFacade) {
            notificationFacade = {
                permission,
                requestPermission: async () => notificationFacade.permission,
                __tbbaFacade: true,
            };
            try {
                Object.defineProperty(window, 'Notification', {
                    configurable: true,
                    value: notificationFacade,
                });
            } catch (error) {
                console.warn('[FirebasePush] Unable to install Notification compatibility facade:', error);
                return false;
            }
        }
        notificationFacade.permission = permission;
        return window.Notification === notificationFacade;
    }

    async function recoverPermissionFromPushManager(swRegistration) {
        if (!swRegistration?.pushManager) return 'unsupported';
        const options = {
            userVisibleOnly: true,
            applicationServerKey: decodeVapidKey(CONFIG.vapidKey),
        };
        let subscription = await swRegistration.pushManager.getSubscription();
        if (!subscription) {
            subscription = await swRegistration.pushManager.subscribe(options);
        }
        if (!subscription) return 'default';

        // A successful PushManager subscription is the authoritative signal
        // that the installed PWA has OS-level notification permission. Some
        // iPhone PWA contexts still report Notification.permission as default.
        return installNotificationFacade('granted', true) ? 'granted' : 'default';
    }

    async function prepareNotificationPermission(swRegistration, requestPermission) {
        if (hasNativeNotificationApi()) {
            let permission = window.Notification.permission;
            if (requestPermission && permission === 'default') {
                permission = await window.Notification.requestPermission();
            }
            return permission;
        }

        if (!hasStandardPushApi() || !swRegistration?.pushManager) return 'unsupported';

        const options = {
            userVisibleOnly: true,
            applicationServerKey: decodeVapidKey(CONFIG.vapidKey),
        };
        let subscription = await swRegistration.pushManager.getSubscription();
        if (!subscription && requestPermission) {
            subscription = await swRegistration.pushManager.subscribe(options);
        }

        let permission = subscription ? 'granted' : 'default';
        if (!subscription && typeof swRegistration.pushManager.permissionState === 'function') {
            try {
                const state = await swRegistration.pushManager.permissionState(options);
                permission = state === 'prompt' ? 'default' : state;
            } catch (error) {
                console.warn('[FirebasePush] Push permission state is unavailable:', error);
            }
        }
        installNotificationFacade(permission);
        return permission;
    }

    function showPushError(error) {
        const detail = error?.message || String(error || 'Unknown push notification error.');
        const message = error?.name === 'NotAllowedError' || detail.includes('messaging/permission-blocked')
            ? 'Notification permission was not granted for this app.'
            : `Push notification setup failed: ${detail}`;
        lastErrorMessage = message;
        setPushStatus(message);
        if (window.App && typeof window.App.showToast === 'function') {
            window.App.showToast('error', message);
        }
        console.error('[FirebasePush]', error);
    }

    // ─── Toast Notification UI ─────────────────────────────────────────────────
    /**
     * Cipta dan papar toast notification custom bila app sedang dibuka (foreground).
     * @param {string} title   - Tajuk notification
     * @param {string} body    - Isi notification
     * @param {string} link    - URL bila diklik (optional)
     * @param {string} type    - Jenis notification untuk icon/warna
     */
    function showToast(title, body, link = '', type = 'general') {
        // Inject CSS jika belum ada
        if (!document.getElementById('tbba-push-toast-style')) {
            const style = document.createElement('style');
            style.id = 'tbba-push-toast-style';
            style.textContent = `
                #tbba-toast-container {
                    position: fixed;
                    bottom: 24px;
                    right: 24px;
                    z-index: 99999;
                    display: flex;
                    flex-direction: column;
                    gap: 12px;
                    pointer-events: none;
                }
                .tbba-push-toast {
                    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
                    border: 1px solid rgba(255,255,255,0.12);
                    border-left: 4px solid #3b82f6;
                    border-radius: 12px;
                    padding: 16px 20px;
                    min-width: 320px;
                    max-width: 420px;
                    display: flex;
                    align-items: flex-start;
                    gap: 14px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.05);
                    pointer-events: all;
                    cursor: pointer;
                    animation: tbbaToastSlideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
                    backdrop-filter: blur(12px);
                    -webkit-backdrop-filter: blur(12px);
                    transform: translateX(120%);
                    opacity: 0;
                }
                .tbba-push-toast.tbba-toast-leave    { border-left-color: #10b981; }
                .tbba-push-toast.tbba-toast-expense  { border-left-color: #f59e0b; }
                .tbba-push-toast.tbba-toast-announcement { border-left-color: #8b5cf6; }
                .tbba-push-toast.tbba-toast-calendar { border-left-color: #06b6d4; }
                .tbba-push-toast.tbba-toast-dismiss  {
                    animation: tbbaToastSlideOut 0.3s ease-in forwards;
                }
                .tbba-toast-icon {
                    width: 40px;
                    height: 40px;
                    border-radius: 10px;
                    background: rgba(59,130,246,0.2);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                    font-size: 18px;
                }
                .tbba-toast-content { flex: 1; min-width: 0; }
                .tbba-toast-title {
                    font-weight: 700;
                    font-size: 14px;
                    color: #f1f5f9;
                    margin-bottom: 4px;
                    line-height: 1.3;
                }
                .tbba-toast-body {
                    font-size: 13px;
                    color: #94a3b8;
                    line-height: 1.5;
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                }
                .tbba-toast-close {
                    width: 24px;
                    height: 24px;
                    border: none;
                    background: rgba(255,255,255,0.1);
                    border-radius: 6px;
                    color: #64748b;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 12px;
                    flex-shrink: 0;
                    transition: background 0.2s;
                }
                .tbba-toast-close:hover { background: rgba(255,255,255,0.2); color: #f1f5f9; }
                .tbba-toast-progress {
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    height: 3px;
                    background: rgba(59,130,246,0.6);
                    border-radius: 0 0 0 12px;
                    animation: tbbaToastProgress 5s linear forwards;
                }
                .tbba-push-toast { position: relative; overflow: hidden; }
                @keyframes tbbaToastSlideIn {
                    from { transform: translateX(120%); opacity: 0; }
                    to   { transform: translateX(0);    opacity: 1; }
                }
                @keyframes tbbaToastSlideOut {
                    from { transform: translateX(0);    opacity: 1; max-height: 120px; }
                    to   { transform: translateX(120%); opacity: 0; max-height: 0; padding: 0; margin: 0; }
                }
                @keyframes tbbaToastProgress {
                    from { width: 100%; }
                    to   { width: 0%; }
                }
                .tbba-push-badge {
                    position: fixed;
                    top: 12px;
                    right: 12px;
                    background: #ef4444;
                    color: white;
                    border-radius: 50%;
                    width: 20px;
                    height: 20px;
                    font-size: 11px;
                    font-weight: 700;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 99998;
                    animation: tbbaToastSlideIn 0.3s ease forwards;
                }
                @media (max-width: 576px) {
                    #tbba-toast-container {
                        right: 10px;
                        bottom: max(10px, env(safe-area-inset-bottom, 0px));
                        left: 10px;
                    }
                    .tbba-push-toast {
                        width: 100%;
                        min-width: 0;
                        max-width: none;
                    }
                }
            `;
            document.head.appendChild(style);
        }

        // Cipta atau guna container
        let container = document.getElementById('tbba-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'tbba-toast-container';
            document.body.appendChild(container);
        }

        // Icon berdasarkan jenis
        const iconMap = {
            'leave_approved':    '✅',
            'leave_rejected':    '❌',
            'expense_approved':  '💰',
            'expense_rejected':  '💸',
            'announcement':      '📢',
            'calendar_reminder': '📅',
            'test':              '🔔',
            'general':           '🔔',
        };

        const typeClassMap = {
            'leave_approved':    'tbba-toast-leave',
            'leave_rejected':    'tbba-toast-leave',
            'expense_approved':  'tbba-toast-expense',
            'expense_rejected':  'tbba-toast-expense',
            'announcement':      'tbba-toast-announcement',
            'calendar_reminder': 'tbba-toast-calendar',
        };

        const icon      = iconMap[type]      || '🔔';
        const typeClass = typeClassMap[type] || '';

        // Bina toast element
        const toast = document.createElement('div');
        toast.className = `tbba-push-toast ${typeClass}`;
        toast.innerHTML = `
            <div class="tbba-toast-icon">${icon}</div>
            <div class="tbba-toast-content">
                <div class="tbba-toast-title">${escapeHtml(title)}</div>
                <div class="tbba-toast-body">${escapeHtml(body)}</div>
            </div>
            <button class="tbba-toast-close" aria-label="Close">✕</button>
            <div class="tbba-toast-progress"></div>
        `;

        // Navigate bila klik pada toast body
        toast.addEventListener('click', function(e) {
            if (!e.target.classList.contains('tbba-toast-close')) {
                if (link) {
                    window.location.href = link;
                } else {
                    window.location.href = `${BASE_URL}?page=notifications`;
                }
                dismissToast(toast);
            }
        });

        // Close button
        toast.querySelector('.tbba-toast-close').addEventListener('click', function(e) {
            e.stopPropagation();
            dismissToast(toast);
        });

        container.appendChild(toast);

        // Update navbar bell count
        updateBellCount();

        // Auto-dismiss selepas 6 saat
        const autoTimer = setTimeout(() => dismissToast(toast), 6000);

        // Cancel auto-timer bila user hover (nak baca dulu)
        toast.addEventListener('mouseenter', () => clearTimeout(autoTimer));
        toast.addEventListener('mouseleave', () => setTimeout(() => dismissToast(toast), 3000));
    }

    /** Animate dismiss dan remove toast dari DOM */
    function dismissToast(toastEl) {
        if (!toastEl || toastEl.classList.contains('tbba-toast-dismiss')) return;
        toastEl.classList.add('tbba-toast-dismiss');
        setTimeout(() => toastEl.remove(), 300);
    }

    /** Escape HTML untuk prevent XSS dalam toast */
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

    /** Update unread count pada navbar bell icon */
    function updateBellCount() {
        const bellBadge = document.querySelector('.notification-badge, #notification-count, [data-notif-badge]');
        if (bellBadge) {
            const current = parseInt(bellBadge.textContent) || 0;
            bellBadge.textContent = current + 1;
            bellBadge.style.display = 'inline-flex';
        }
    }

    // ─── FCM Token Management ─────────────────────────────────────────────────

    /** Hantar FCM token ke backend untuk disimpan */
    async function saveTokenToServer(token, deviceInfo = '') {
        if (!token || !CSRF_TOKEN) {
            showPushError(new Error('The push token or login session token is missing. Please reload the app and try again.'));
            return false;
        }

        try {
            const response = await fetch(`${BASE_URL}?action=save_fcm_token`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    csrf_token:  CSRF_TOKEN,
                    fcm_token:   token,
                    device_info: deviceInfo,
                }),
            });

            await readServerResponse(response);
            subscriptionSaved = true;
            lastErrorMessage = '';
            writeLocalSetting('tbba_fcm_token', token);
            writeLocalSetting('tbba_push_subscribed', '1');
            writeLocalSetting('tbba_push_opted_out', null);
            return true;
        } catch (err) {
            showPushError(err);
            return false;
        }
    }

    /** Buang FCM token dari backend (unsubscribe) */
    async function removeTokenFromServer(token = '') {
        if (!CSRF_TOKEN) return false;

        try {
            const response = await fetch(`${BASE_URL}?action=remove_fcm_token`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    csrf_token: CSRF_TOKEN,
                    fcm_token:  token,
                }),
            });

            await readServerResponse(response);
            subscriptionSaved = false;
            writeLocalSetting('tbba_fcm_token', null);
            writeLocalSetting('tbba_push_subscribed', null);
            writeLocalSetting('tbba_push_opted_out', '1');
            return true;
        } catch (err) {
            showPushError(err);
            return false;
        }
    }

    /** Dapatkan device info string untuk debugging */
    function getDeviceInfo() {
        const ua = navigator.userAgent;
        let browser = 'Unknown Browser';
        let os = 'Unknown OS';

        if (ua.includes('Edg/'))    browser = 'Microsoft Edge';
        else if (ua.includes('OPR/')) browser = 'Opera';
        else if (ua.includes('Chrome')) browser = 'Chrome';
        else if (ua.includes('Firefox')) browser = 'Firefox';
        else if (ua.includes('Safari')) browser = 'Safari';

        if (ua.includes('Windows')) os = 'Windows';
        else if (ua.includes('Mac')) os = 'macOS';
        else if (ua.includes('Android')) os = 'Android';
        else if (ua.includes('iPhone') || ua.includes('iPad')) os = 'iOS';
        else if (ua.includes('Linux')) os = 'Linux';

        return `${browser} on ${os}`;
    }

    // ─── Main Functions ───────────────────────────────────────────────────────

    function getServiceWorkerLocation() {
        const appUrl = new URL(BASE_URL, window.location.href);
        const scopePath = appUrl.pathname.replace(/[^/]*$/, '');
        return {
            scriptUrl: `${scopePath}sw.js`,
            scopePath,
        };
    }

    async function waitForActiveServiceWorker(registration) {
        const worker = registration.installing || registration.waiting;
        if (!worker || worker.state === 'activated') return registration;
        await new Promise((resolve, reject) => {
            const timeout = window.setTimeout(() => reject(new Error('Service worker activation timed out.')), 10000);
            worker.addEventListener('statechange', () => {
                if (worker.state === 'activated') {
                    window.clearTimeout(timeout);
                    resolve();
                } else if (worker.state === 'redundant') {
                    window.clearTimeout(timeout);
                    reject(new Error('Service worker installation failed.'));
                }
            });
        });
        return registration;
    }

    async function ensureServiceWorkerRegistration() {
        const { scriptUrl, scopePath } = getServiceWorkerLocation();
        const existing = await navigator.serviceWorker.getRegistration(scopePath);
        const expectedScriptUrl = new URL(scriptUrl, window.location.origin).href;
        const registeredWorkers = existing
            ? [existing.installing, existing.waiting, existing.active].filter(Boolean)
            : [];
        if (existing && existing.updateViaCache === 'none'
            && registeredWorkers.some(worker => worker.scriptURL === expectedScriptUrl)) {
            await existing.update();
            return waitForActiveServiceWorker(existing);
        }
        const registration = await navigator.serviceWorker.register(scriptUrl, {
            scope: scopePath,
            updateViaCache: 'none',
        });
        return waitForActiveServiceWorker(registration);
    }

    /**
     * Initialize Firebase dan request notification permission.
     * Panggil fungsi ini selepas user login dan page selesai load.
     */
    async function init() {
        if (isInitialized) return Boolean(messagingInstance);
        if (initializationPromise) return initializationPromise;
        initializationPromise = initialize().finally(() => { initializationPromise = null; });
        return initializationPromise;
    }

    async function initialize() {
        if (!window.isSecureContext) {
            showPushError(new Error('Push notifications require HTTPS. Open the secure app URL and try again.'));
            return false;
        }

        if (!hasStandardPushApi()) {
            showPushError(new Error('This browser context does not expose Service Worker and PushManager APIs.'));
            return false;
        }

        const requiredConfig = [
            CONFIG.apiKey,
            CONFIG.projectId,
            CONFIG.messagingSenderId,
            CONFIG.appId,
            CONFIG.vapidKey,
        ];
        if (requiredConfig.some(value => !value || value.includes('CHANGE_ME') || value.includes('REPLACE_WITH_'))) {
            showPushError(new Error('Firebase web configuration is incomplete in the hosting .env file.'));
            return false;
        }

        try {
            // Initialize Firebase App (elak double-init)
            if (typeof firebase === 'undefined') {
                showPushError(new Error('The Firebase SDK could not be loaded. Check the internet connection and reopen the app.'));
                return false;
            }

            if (!firebase.apps.length) {
                firebase.initializeApp({
                    apiKey:            CONFIG.apiKey,
                    authDomain:        CONFIG.authDomain,
                    projectId:         CONFIG.projectId,
                    storageBucket:     CONFIG.storageBucket,
                    messagingSenderId: CONFIG.messagingSenderId,
                    appId:             CONFIG.appId,
                });
            }

            // Reuse the combined PWA + Firebase worker. A second worker at the
            // same scope would replace the PWA worker and prevent stable tokens.
            const swReg = await ensureServiceWorkerRegistration();
            console.log('[FirebasePush] Firebase SW didaftarkan:', swReg.scope);

            const permission = await prepareNotificationPermission(swReg, false);
            const firebaseSupported = typeof firebase.messaging.isSupported !== 'function'
                || await firebase.messaging.isSupported();
            if (!firebaseSupported) {
                throw new Error('Firebase Messaging cannot use the available Push APIs on this device.');
            }

            messagingInstance = firebase.messaging();

            // Do not trigger a browser permission prompt automatically. Browsers
            // expect this prompt to follow the user's Enable Notifications click.
            if (permission === 'granted' && readLocalSetting('tbba_push_opted_out') !== '1') {
                if (!await generateAndSaveToken(swReg)) return false;
            } else {
                updateSubscribeButton(false, permission === 'denied');
            }

            // Setup foreground message handler
            setupForegroundHandler();

            isInitialized = true;
            return true;

        } catch (err) {
            showPushError(err);
            return false;
        }
    }

    /**
     * Request notification permission dari user dan generate FCM token.
     * UI permission prompt akan muncul secara automatik oleh browser.
     */
    async function requestPermissionAndGetToken(swRegistration) {
        let permission;
        try {
            permission = await prepareNotificationPermission(swRegistration, true);
        } catch (error) {
            updateSubscribeButton(false, error?.name === 'NotAllowedError');
            showPushError(error);
            return false;
        }

        if (permission === 'denied') {
            console.warn('[FirebasePush] Notification permission was blocked. Enable it in your browser settings.');
            updateSubscribeButton(false, true); // blocked state
            showPushError(new Error('Notifications are blocked for this app. Allow notifications in the browser or device settings, then retry.'));
            return false;
        }

        if (permission !== 'granted') {
            try {
                permission = await recoverPermissionFromPushManager(swRegistration);
            } catch (error) {
                updateSubscribeButton(false, error?.name === 'NotAllowedError');
                showPushError(error);
                return false;
            }
        }

        if (permission !== 'granted') {
            updateSubscribeButton(false, false);
            showPushError(new Error(`The installed app returned notification permission state "${permission}". Open iPhone Settings > Notifications > TBBA ERP, enable Allow Notifications, then reopen the app.`));
            return false;
        }

        // Permission granted — generate FCM token
        return generateAndSaveToken(swRegistration);
    }

    /**
     * Generate FCM token dan simpan ke backend.
     * @param {ServiceWorkerRegistration} swRegistration
     */
    async function generateAndSaveToken(swRegistration) {
        subscriptionSaved = false;
        try {
            setPushStatus('Creating a secure push subscription for this device...');
            const token = await promiseWithTimeout(
                messagingInstance.getToken({
                    vapidKey:            CONFIG.vapidKey,
                    serviceWorkerRegistration: swRegistration,
                }),
                25000,
                'Firebase did not return a device token within 25 seconds. Reopen the installed app and try once more.'
            );

            if (token) {
                currentToken = token;
                console.log('[FirebasePush] FCM token generated successfully.');

                // Simpan ke backend
                setPushStatus('Saving this device subscription to the server...');
                const deviceInfo = getDeviceInfo();
                const saved = await saveTokenToServer(token, deviceInfo);
                if (saved) {
                    updateSubscribeButton(true, false);
                } else {
                    const errorMessage = lastErrorMessage;
                    updateSubscribeButton(false, false);
                    if (errorMessage) setPushStatus(errorMessage);
                }
                return saved;
            } else {
                console.warn('[FirebasePush] Failed to generate a token. Permission may not have been granted.');
                updateSubscribeButton(false, false);
                showPushError(new Error('Firebase did not provide a registration token for this device.'));
                return false;
            }
        } catch (err) {
            currentToken = null;
            updateSubscribeButton(false, false);
            showPushError(err);
            return false;
        }
    }

    /**
     * Setup handler untuk foreground messages (bila app sedang dibuka).
     * Firebase TIDAK papar native notification secara automatik bila foreground —
     * kita perlu handle sendiri dan papar custom toast.
     */
    function setupForegroundHandler() {
        if (!messagingInstance) return;

        messagingInstance.onMessage(function(payload) {
            console.log('[FirebasePush] Foreground message received:', payload);

            const notification = payload.notification || {};
            const data         = payload.data         || {};

            const title = notification.title || data.title || 'TBBA ERP';
            const body  = notification.body  || data.body  || '';
            const link  = data.link || (payload.fcmOptions ? payload.fcmOptions.link : '') || '';
            const type  = data.type          || 'general';

            // Papar custom toast (bukan native browser notification)
            showToast(title, body, link, type);
        });
    }

    /**
     * Update UI button subscribe/unsubscribe jika ada dalam page.
     * Button perlu ada id="tbba-push-toggle-btn"
     */
    function updateSubscribeButton(isSubscribed, isBlocked = false) {
        const btn = document.getElementById('tbba-push-toggle-btn');
        const statusEl = document.getElementById('tbba-push-status');

        if (!btn) return;
        btn.disabled = isBlocked;
        btn.classList.remove('btn-secondary');

        if (isBlocked) {
            btn.setAttribute('data-subscribed', '0');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-ban"></i> Notifications Blocked';
            btn.classList.add('btn-secondary');
            if (statusEl) statusEl.textContent = 'Notifications are blocked in this browser. Update the site permission first.';
        } else if (isSubscribed) {
            btn.setAttribute('data-subscribed', '1');
            btn.innerHTML = '<i class="fa-solid fa-bell"></i> Notifications Active';
            btn.classList.remove('btn-outline-primary');
            btn.classList.add('btn-success');
            if (statusEl) statusEl.textContent = 'Push notifications are active on this device.';
        } else {
            btn.setAttribute('data-subscribed', '0');
            btn.innerHTML = '<i class="fa-solid fa-bell"></i> Enable Notifications';
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-primary');
            if (statusEl) statusEl.textContent = 'Push notifications are not enabled on this device.';
        }
    }

    async function subscribe() {
        lastErrorMessage = '';
        setPushStatus('Checking notification permission on this device...');

        try {
            // On iPhone the permission request must be invoked immediately from
            // the user's tap. Do this before waiting for Firebase or SW updates.
            if (hasNativeNotificationApi() && window.Notification.permission === 'default') {
                const permission = await window.Notification.requestPermission();
                if (permission !== 'granted') {
                    updateSubscribeButton(false, permission === 'denied');
                    showPushError(new Error('Notification permission was not granted for this app.'));
                    return false;
                }
            }

            if (!isInitialized) {
                const initialized = await init();
                if (!initialized) return false;
                if (subscriptionSaved) return true;
            }
            if (!messagingInstance) {
                showPushError(new Error('Firebase Messaging is not ready on this device.'));
                return false;
            }
            const swReg = await ensureServiceWorkerRegistration();
            return requestPermissionAndGetToken(swReg);
        } catch (error) {
            showPushError(error);
            return false;
        }
    }

    // ─── Public API ───────────────────────────────────────────────────────────
    return {
        /**
         * Initialize Firebase Push Notification.
         * Panggil selepas page load dan user sudah login.
         * Contoh: <script>FirebasePush.init();</script>
         */
        init,

        /**
         * Subscribe semula (bila user klik button enable notification).
         */
        subscribe,

        /**
         * Unsubscribe dari push notification.
         * Token akan dibuang dari backend.
         */
        async unsubscribe() {
            if (messagingInstance && currentToken) {
                try {
                    await messagingInstance.deleteToken();
                } catch (error) {
                    showPushError(error);
                    return false;
                }
                if (!await removeTokenFromServer(currentToken)) return false;
                currentToken = null;
                lastErrorMessage = '';
                updateSubscribeButton(false, false);
                console.log('[FirebasePush] Unsubscribed successfully.');
                return true;
            }
            return false;
        },

        /**
         * Toggle subscribe/unsubscribe.
         * Guna pada onclick button.
         */
        async toggleSubscription() {
            const isCurrentlySubscribed = subscriptionSaved && Boolean(currentToken);
            if (isCurrentlySubscribed) {
                return this.unsubscribe();
            } else {
                return subscribe();
            }
        },

        /**
         * Hantar test notification untuk semak setup (dipanggil dari admin panel).
         */
        async sendTest() {
            if (!CSRF_TOKEN) {
                showPushError(new Error('The login session token is missing. Sign in again and retry.'));
                return false;
            }
            try {
                setPushStatus('Registering this device for push notifications...');
                // Re-generate and save this device token first. This repairs a
                // missing server subscription before asking the server to test it.
                const subscribed = await subscribe();
                if (!subscribed) return false;
                setPushStatus('Device registered. Sending the test notification...');
                const res = await fetch(`${BASE_URL}?action=send_test_push`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ csrf_token: CSRF_TOKEN, fcm_token: currentToken }),
                });
                const data = await readServerResponse(res);
                lastErrorMessage = '';
                setPushStatus('Test notification sent. The device subscription is active.');
                if (window.App && typeof window.App.showToast === 'function') {
                    window.App.showToast(data.status === 'success' ? 'success' : 'error', data.message || 'Test push request completed.');
                }
                return data.status === 'success';
            } catch(e) {
                showPushError(e);
                return false;
            }
        },

        /** Dapatkan FCM token semasa (untuk debugging) */
        getToken() { return currentToken; },

        /** Ralat push terakhir untuk dipaparkan pada UI profil. */
        getLastError() { return lastErrorMessage; },

        /** Semak adakah push notification diaktifkan */
        isSubscribed() { return subscriptionSaved && Boolean(currentToken); },

        /** Expose toast untuk kegunaan modul lain */
        showToast,
    };
})();
