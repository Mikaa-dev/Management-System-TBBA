# PWA push notification update

## Changes

- Firebase initialization is shared between page startup and the Enable button,
  avoiding simultaneous token creation during startup.
- Profile retains errors from token creation, token saving and test delivery.
  Subscription status uses the current server-confirmed registration, rather
  than an old localStorage flag. Storage restrictions do not invalidate a
  successful registration.
- Send Test Push targets the token submitted by the current device and verifies
  that the token belongs to the signed-in user.
- Firebase request errors preserve device tokens. Only a typed FCM
  `UNREGISTERED` response triggers automatic removal.
- HTTP/relative click URLs are not sent as FCM HTTPS click options. Production
  requests use the configured absolute HTTPS destination.
- Firebase notification payloads are displayed once; the custom background
  handler still displays data-only messages.
- Service worker registration checks imported scripts without the browser HTTP
  cache, and the PWA cache version is updated. A deliberate unsubscribe remains
  disabled after reload when browser storage is available.

Firebase documents the distinction between invalid request parameters and
expired registrations in [FCM error codes](https://firebase.google.com/docs/cloud-messaging/error-codes).
Its [web message guide](https://firebase.google.com/docs/cloud-messaging/web/receive-messages)
describes automatic background display and HTTPS click URLs.

## Upload

Extract the newly built `tbba-directadmin-code-*.zip` directly inside the domain's
`public_html`. It contains the application code and static assets, including
`sw.js`, `firebase-messaging-sw.js`, `manifest.json`, `sitemap.xml` and `robots.txt`.

The archive is code only. Restore your production `.env` and the service-account
file referenced by `FIREBASE_CREDENTIALS_PATH`; these private files and existing
database/uploads are not included. Use the production settings, including:

```env
APP_ENV=production
APP_URL=https://www.thebridgebusiness.com
```

Keep the existing Firebase project settings. No database migration is required
for these code changes. The full hosting procedure is in
`DIRECTADMIN_DEPLOYMENT.md`.

## Verify after hosting is restored

1. Confirm `/sw.js` and `/firebase-messaging-sw.js` return JavaScript with HTTP
   200, and `/manifest.json` returns JSON.
2. Open the HTTPS site, reload to load the updated scripts, and sign in.
3. Open Profile, press Enable Notifications and allow the browser prompt.
4. Press Send Test Push. With the app visible, the foreground notification
   appears inside the app. To check background delivery, switch away from the
   app immediately after starting the test.
5. If a step fails, retain the complete message now shown in Profile. It
   distinguishes permission, worker/SDK, token-save, session and server errors.

## Local verification

```sh
node --test scripts/test_push_frontend.mjs
php scripts/test_push_backend.php
```

These checks use browser/Firebase doubles and offline payload/error checks.
They do not send real push notifications. PHP/JavaScript syntax checks also pass.
Final delivery and operating-system permissions still require a test on the
actual device after the hosting files are restored.
