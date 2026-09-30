/**
 * Firebase Messaging Service Worker — TBBA ERP
 * 
 * PENTING: Fail ini MESTI berada di root direktori aplikasi
 * supaya ia mempunyai scope yang betul untuk intercept push events.
 * 
 * Fail ini handle push notifications bila:
 *   - Browser ditutup sepenuhnya
 *   - Tab TBBA ERP tidak aktif / di background
 * 
 * Nota: Bila app sedang dibuka (foreground), notification dihandle oleh
 * firebase-push.js dalam app itu sendiri (papar custom toast).
 */

// ─── 1. Import Firebase SDK (compat version untuk service worker) ──────────────
// Guna CDN compat — sesuai untuk classic service worker melalui importScripts.
importScripts('https://www.gstatic.com/firebasejs/12.18.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/12.18.0/firebase-messaging-compat.js');

// ─── 2. Firebase Configuration ────────────────────────────────────────────────
// IMPORTANT: Ganti nilai ini dengan credentials dari Firebase Console!
// Ini adalah PUBLIC keys — selamat untuk diletakkan dalam service worker.
// Pastikan ini SAMA dengan nilai dalam assets/js/firebase-push.js
firebase.initializeApp({
    apiKey: self.FIREBASE_CONFIG?.apiKey || "AIzaSyDmWWs5aVLA_sFQ1sq7eC9MiQJgQOIXtqk",
    authDomain: self.FIREBASE_CONFIG?.authDomain || "tbba-5e780.firebaseapp.com",
    projectId: self.FIREBASE_CONFIG?.projectId || "tbba-5e780",
    storageBucket: self.FIREBASE_CONFIG?.storageBucket || "tbba-5e780.firebasestorage.app",
    messagingSenderId: self.FIREBASE_CONFIG?.messagingSenderId || "140514356758",
    appId: self.FIREBASE_CONFIG?.appId || "1:140514356758:web:a67e36fae3f5fab72ecf37",
});

// ─── 3. Inisialisasi Firebase Messaging ───────────────────────────────────────
const messaging = firebase.messaging();

// ─── 4. Handle Background Push Notification ───────────────────────────────────
/**
 * `onBackgroundMessage` dipanggil bila:
 * - App tidak aktif (tab lain / browser diminimise / browser ditutup)
 * - Firebase SDK dalam app tidak dapat intercept notification
 */
messaging.onBackgroundMessage(function (payload) {
    console.log('[firebase-messaging-sw.js] Background message received:', payload);

    // Firebase already displays notification payloads in the background.
    // Only data-only messages need a second, custom showNotification call.
    if (payload.notification) return;

    // Extract data dari payload
    const notification = payload.notification || {};
    const data = payload.data || {};

    const title = notification.title || data.title || 'TBBA ERP Notification';
    const body = notification.body || data.body || 'You have a new notification.';
    const type = data.type || 'general';

    // Base path bergantung kepada scope service worker (berfungsi dengan ngrok & localhost)
    // self.registration.scope akan return URL penuh cth: https://xxxx.ngrok.io/Bridge/
    const basePath = self.registration.scope.replace(/\/$/, ''); // buang trailing slash

    // Resolve link — jika relative path, tambah basePath supaya berfungsi dengan ngrok
    const rawLink = data.link || payload.fcmOptions?.link || '';
    let link;
    if (!rawLink) {
        link = basePath + '/index.php?page=notifications';
    } else if (rawLink.startsWith('http')) {
        link = rawLink; // sudah absolute URL
    } else {
        // Relative path (cth: 'index.php?page=leave') — resolve against SW scope
        // supaya domain root dan pemasangan subfolder kedua-duanya berfungsi.
        link = new URL(rawLink.replace(/^\//, ''), self.registration.scope).href;
    }


    // Pilih icon berdasarkan jenis notification
    const appIcon = basePath + '/assets/images/app_icon_v2.png';
    const iconMap = {
        'leave_approved': appIcon,
        'leave_rejected': appIcon,
        'expense_approved': appIcon,
        'expense_rejected': appIcon,
        'announcement': appIcon,
        'calendar_reminder': appIcon,
        'default': appIcon,
    };

    const icon = iconMap[type] || iconMap['default'];
    const badge = appIcon;

    // Pilih warna badge/tag berdasarkan jenis notification
    const tagMap = {
        'leave_approved': 'tbba-leave',
        'leave_rejected': 'tbba-leave',
        'expense_approved': 'tbba-expense',
        'expense_rejected': 'tbba-expense',
        'announcement': 'tbba-announcement',
        'calendar_reminder': 'tbba-calendar',
        'general': 'tbba-general',
    };

    const tag = tagMap[type] || 'tbba-notification';

    // Options untuk browser notification
    const notificationOptions = {
        body: body,
        icon: icon,
        badge: badge,
        tag: tag,      // Groupkan notification sejenis (replace yang lama)
        renotify: true,     // Show again even when a notification has the same tag.
        requireInteraction: false,    // Auto-dismiss without requiring interaction.
        silent: false,    // Mainkan bunyi notification
        data: {
            url: link,
            type: type,
        },
        // Vibration pattern (mobile): vibrate 200ms, pause 100ms, vibrate 200ms
        vibrate: [200, 100, 200],
        // Action buttons (optional)
        actions: [
            {
                action: 'open',
                title: '📂 Open',
            },
            {
                action: 'dismiss',
                title: '✕ Close',
            }
        ],
    };

    // ✅ BETUL: return promise terus — `event` TIDAK wujud dalam onBackgroundMessage callback!
    // `event.waitUntil` hanya untuk raw 'push' event listener, BUKAN onBackgroundMessage.
    return self.registration.showNotification(title, notificationOptions);
});

// ─── 5. Handle Notification Click ─────────────────────────────────────────────
/**
 * Dipanggil bila user klik pada notification yang dipapar oleh service worker.
 * Navigate user ke link yang relevan dalam tab/window yang sedia ada.
 */
self.addEventListener('notificationclick', function (event) {
    console.log('[firebase-messaging-sw.js] Notification clicked:', event);

    // Tutup notification panel
    event.notification.close();

    // Dapatkan URL dari notification data
    const notifData = event.notification.data || {};
    const targetUrl = notifData.url || new URL('index.php?page=notifications', self.registration.scope).href;

    // Handle button actions
    if (event.action === 'dismiss') {
        // Close action: dismiss the notification without navigating.
        return;
    }

    // Untuk 'open' action atau klik pada notification body
    event.waitUntil(
        clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        }).then(function (clientList) {
            // Guna scope SW untuk match URL (berfungsi dengan ngrok & localhost)
            const swScope = self.registration.scope;
            for (const client of clientList) {
                if (client.url.startsWith(swScope) && 'focus' in client) {
                    client.focus();
                    return client.navigate(targetUrl);
                }
            }
            // Jika tiada tab TBBA terbuka, buka tab baru
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});

// ─── 6. Handle Push Event (Fallback) ──────────────────────────────────────────
/**
 * Push event handler sebagai fallback untuk notification yang mungkin
 * tidak diintercept oleh onBackgroundMessage.
 * Firebase SDK sepatutnya handle ini, tapi ini sebagai safety net.
 */
self.addEventListener('push', function (event) {
    // Firebase SDK akan handle push events yang berkaitan dengan FCM
    // Event ini hanya triggered untuk non-FCM push atau jika SDK gagal
    if (!event.data) return;

    try {
        const payload = event.data.json();
        // Biarkan Firebase SDK handle jika ini adalah FCM message
        // (Firebase SDK intercept ini secara automatik bila importScripts dipanggil)
        console.log('[firebase-messaging-sw.js] Raw push event (fallback):', payload);
    } catch (e) {
        console.log('[firebase-messaging-sw.js] Push event received with non-JSON data:', event.data.text());
    }
});

// ─── 7. Service Worker Lifecycle ──────────────────────────────────────────────
self.addEventListener('install', function (event) {
    console.log('[firebase-messaging-sw.js] Installed. Version: 1.0.0');
    self.skipWaiting(); // Activate immediately without waiting for older tabs to close.
});

self.addEventListener('activate', function (event) {
    console.log('[firebase-messaging-sw.js] Activated.');
    event.waitUntil(self.clients.claim()); // Take control of all tabs immediately.
});
