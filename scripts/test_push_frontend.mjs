// Run with: node --test scripts/test_push_frontend.mjs
// Browser/Firebase/HTTP doubles: no real tokens, messages or database writes.
import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../assets/js/firebase-push.js', import.meta.url), 'utf8');
const profileSource = readFileSync(new URL('../views/profile/index.php', import.meta.url), 'utf8');
const workerSource = readFileSync(new URL('../firebase-messaging-sw.js', import.meta.url), 'utf8');
const token = 'test-device-token-' + 'a'.repeat(140);
function response(data = { status: 'success' }, extra = {}) {
    return { ok: true, status: 200, redirected: false,
        headers: { get: () => 'application/json' }, json: async () => data, ...extra };
}

function harness(options = {}) {
    const storage = options.storage || new Map();
    const status = { textContent: '' };
    const button = { disabled: false, innerHTML: '', classList: { add() {}, remove() {} }, setAttribute() {} };
    const calls = { tokens: 0, requests: [], registrations: [], handlers: 0, prompts: 0 };
    const registration = { scope: 'https://example.test/', updateViaCache: 'none',
        active: { scriptURL: 'https://example.test/sw.js', state: 'activated' }, update: async () => {}, pushManager: {} };
    const messaging = { getToken: async () => {
        calls.tokens++;
        if (options.tokenError) throw new Error(options.tokenError);
        return token;
    }, onMessage: () => { calls.handlers++; }, deleteToken: async () => true };
    const firebase = { apps: [], initializeApp() { this.apps.push({}); }, messaging: () => messaging };
    firebase.messaging.isSupported = async () => true;
    const window = { isSecureContext: options.secure !== false,
        location: new URL(options.url || 'https://example.test/index.php?page=profile'),
        FIREBASE_CONFIG: { apiKey: 'test-key', projectId: 'test-project', messagingSenderId: '123', appId: 'test-app', vapidKey: 'test-public-key' },
        CSRF_TOKEN: 'test-csrf', setTimeout, clearTimeout, PushManager: function() {},
        Notification: { permission: options.permission || 'granted', requestPermission: async () => {
            calls.prompts++; window.Notification.permission = 'granted'; return 'granted';
        } }, App: { showToast() {} } };
    const context = vm.createContext({ window, Notification: window.Notification,
        navigator: { userAgent: 'Chrome test', serviceWorker: {
            getRegistration: async () => options.registrationMissing ? null : registration,
            register: async (url, config) => { calls.registrations.push({ url, ...config }); return registration; },
        } },
        document: { querySelector: () => null,
            getElementById: id => id === 'tbba-push-status' ? status : id === 'tbba-push-toggle-btn' ? button : null },
        localStorage: { getItem: key => storage.get(key) ?? null,
            setItem: (key, value) => { if (options.storageBlocked) throw new Error('Storage denied'); storage.set(key, value); },
            removeItem: key => storage.delete(key) },
        fetch: async (url, config) => {
            calls.requests.push({ url, config });
            return options.respond ? options.respond(url, config) : response();
        }, firebase, URL, URLSearchParams, setTimeout, clearTimeout,
        console: { log() {}, warn() {}, error() {} },
    });
    vm.runInContext(source + '\nglobalThis.push = FirebasePush;', context);
    const start = profileSource.indexOf('function updateProfileNotificationPermission()');
    const end = profileSource.indexOf('async function sendProfileTestPush', start);
    vm.runInContext(profileSource.slice(start, end), context);
    return { push: context.push, context, window, calls, status, button, storage };
}

test('concurrent auto-init and Enable click save one token and attach one foreground handler', async () => {
    const h = harness();
    assert.deepEqual(await Promise.all([h.push.init(), h.push.subscribe(), h.push.init()]), [true, true, true]);
    assert.equal(h.calls.tokens, 1);
    assert.equal(h.calls.handlers, 1);
    assert.equal(h.calls.requests.length, 1);
    assert.equal(h.push.isSubscribed(), true);
});

test('permission prompt runs immediately from the user action, never during automatic init', async () => {
    const h = harness({ permission: 'default' });
    await h.push.init();
    assert.equal(h.calls.prompts, 0);
    const subscribing = h.push.subscribe();
    assert.equal(h.calls.prompts, 1);
    assert.equal(await subscribing, true);
});

test('token creation failure remains visible after Profile refresh and does not report initialized success', async () => {
    const h = harness({ tokenError: 'FCM Registration API is disabled' });
    assert.equal(await h.push.init(), false);
    h.context.updateProfileNotificationPermission();
    assert.match(h.status.textContent, /FCM Registration API is disabled/);
    assert.equal(h.push.isSubscribed(), false);
    assert.equal(h.calls.requests.length, 0);
});

test('server token-save failure survives UI refresh and can be retried', async () => {
    let failing = true;
    const h = harness({ respond: () => response(failing ? { status: 'error', message: 'Token table unavailable' } : { status: 'success' }) });
    assert.equal(await h.push.init(), false);
    h.context.updateProfileNotificationPermission();
    assert.match(h.status.textContent, /Token table unavailable/);
    failing = false;
    assert.equal(await h.push.subscribe(), true);
    assert.equal(h.push.getLastError(), '');
    assert.equal(h.push.isSubscribed(), true);
});

test('test-push server rejection is retained, and the request targets this exact device', async () => {
    const h = harness({ respond: url => response(url.includes('send_test_push')
        ? { status: 'error', message: 'Firebase rejected the server credentials' } : { status: 'success' }) });
    assert.equal(await h.push.sendTest(), false);
    assert.match(h.push.getLastError(), /Firebase rejected the server credentials/);
    h.context.updateProfileNotificationPermission();
    assert.match(h.status.textContent, /Firebase rejected the server credentials/);
    const request = h.calls.requests.find(call => call.url.includes('send_test_push'));
    assert.equal(request.config.body.get('fcm_token'), token);
    assert.equal(request.config.body.get('csrf_token'), 'test-csrf');
});

test('denied permission gives an actionable error without sending a test', async () => {
    const h = harness({ permission: 'denied' });
    assert.equal(await h.push.sendTest(), false);
    assert.match(h.push.getLastError(), /blocked/);
    assert.equal(h.calls.requests.length, 0);
});

test('session redirect and non-JSON hosting failures are explained', async () => {
    for (const [reply, expected] of [
        [response({}, { redirected: true }), /session has expired/],
        [response({}, { status: 404, ok: false, headers: { get: () => 'text/html' } }), /HTTP 404 without JSON/],
    ]) {
        const h = harness({ respond: () => reply });
        assert.equal(await h.push.init(), false);
        assert.match(h.push.getLastError(), expected);
    }
});

test('blocked localStorage does not undo a server-confirmed subscription', async () => {
    const h = harness({ storageBlocked: true });
    assert.equal(await h.push.init(), true);
    h.context.updateProfileNotificationPermission();
    assert.match(h.status.textContent, /active on this device/);
    assert.equal(h.push.isSubscribed(), true);
});

test('stale localStorage flag cannot claim this device is subscribed', () => {
    const h = harness({ storage: new Map([['tbba_push_subscribed', '1']]) });
    assert.equal(h.push.isSubscribed(), false);
});

test('unsubscribe persists across reload, and explicit Enable restores registration', async () => {
    const h = harness();
    await h.push.init();
    assert.equal(await h.push.unsubscribe(), true);
    assert.equal(h.push.isSubscribed(), false);
    const reloaded = harness({ storage: h.storage });
    assert.equal(await reloaded.push.init(), true);
    assert.equal(reloaded.calls.tokens, 0);
    assert.equal(await reloaded.push.subscribe(), true);
    assert.equal(reloaded.push.isSubscribed(), true);
});

test('worker registration bypasses cached imports and token requests keep the current www host', async () => {
    const h = harness({ registrationMissing: true, url: 'https://www.example.test/Bridge/index.php?page=profile' });
    assert.equal(await h.push.init(), true);
    assert.equal(h.calls.registrations[0].updateViaCache, 'none');
    assert.equal(h.calls.registrations[0].url, '/Bridge/sw.js');
    assert.equal(h.calls.requests[0].url, 'https://www.example.test/Bridge/index.php?action=save_fcm_token');
});

test('insecure origin fails before worker registration', async () => {
    const h = harness({ secure: false });
    assert.equal(await h.push.init(), false);
    assert.match(h.push.getLastError(), /require HTTPS/);
    assert.equal(h.calls.tokens, 0);
});

test('worker avoids duplicate Firebase notifications and still displays data-only messages', async () => {
    let background;
    const notifications = [];
    const context = vm.createContext({
        importScripts() {},
        firebase: { initializeApp() {}, messaging: () => ({ onBackgroundMessage(callback) { background = callback; } }) },
        self: { registration: { scope: 'https://example.test/Bridge/',
            showNotification: async (...args) => { notifications.push(args); } }, addEventListener() {} },
        console: { log() {} }, URL,
    });
    vm.runInContext(workerSource, context);
    await background({ notification: { title: 'Already displayed by Firebase' }, data: {} });
    assert.equal(notifications.length, 0);
    await background({ data: { title: 'Approval', body: 'Approved', link: 'index.php?page=leave' } });
    assert.equal(notifications.length, 1);
    assert.equal(notifications[0][1].data.url, 'https://example.test/Bridge/index.php?page=leave');
});
