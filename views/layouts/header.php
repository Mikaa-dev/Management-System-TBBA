<?php
if (!function_exists('tbba_asset_version')) {
    function tbba_asset_version(string $relativePath): string {
        $fullPath = dirname(__DIR__, 2) . '/' . ltrim($relativePath, '/');
        $modified = @filemtime($fullPath);
        return $modified === false ? '1' : (string)$modified;
    }
}
/**
 * Templat Header & Meta Tags HTML5
 * Syarikat: The Bridge Business Alliance (TBBA)
 */
if (!isset($pageTitle)) {
    $pageTitle = "TBBA ERP System";
}
// Load firebase config constants (dari .env)
require_once __DIR__ . '/../../config/firebase.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="description" content="Corporate Mini ERP System - The Bridge Business Alliance (TBBA)">
    <meta name="theme-color" content="#1E3A8A">
    <meta name="csrf-token" content="<?= Helper::csrfToken() ?>">
    <title><?= htmlspecialchars($pageTitle) ?> | TBBA</title>
    
    <!-- PWA Manifest & Favicon / iOS Touch Icon -->
    <link rel="manifest" href="manifest.json?v=4">
    <link rel="icon" type="image/png" href="assets/images/app_icon_v2.png">
    <link rel="apple-touch-icon" href="assets/images/app_icon_v2.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="TBBA ERP">
    
    <!-- Ikon Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha384-iw3OoTErCYJJB9mCa8LNS2hbsQ7M3C0EpIsO/H5+EGAkPGc6rk+V8i04oW/K5xq0" crossorigin="anonymous">
    
    <!-- DataTables CSS Plugin -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" integrity="sha384-w9ufcIOKS67vY4KePhJtmWDp4+Ai5DMaHvqqF85VvjaGYSW2AhIbqorgKYqIJopv" crossorigin="anonymous">
    
    <!-- Utama CSS (Vanilla CSS3) -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= tbba_asset_version('assets/css/style.css') ?>">
    
    <!-- E-Sign CSS -->
    <link rel="stylesheet" href="assets/css/esign.css?v=<?= tbba_asset_version('assets/css/esign.css') ?>">

    <!-- Core JavaScript Dependencies (Loaded early for immediate availability across all views) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs" crossorigin="anonymous"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="assets/js/app.js?v=<?= tbba_asset_version('assets/js/app.js') ?>"></script>

    <!-- Firebase SDK compat — required for FCM push notifications -->
    <script src="https://www.gstatic.com/firebasejs/12.18.0/firebase-app-compat.js" integrity="sha384-3dQMr5IYbX54CRsE04108MMs7jeEV/IWSMASoZPvju7G87xGB1a2oHweoRc6Fc4z" crossorigin="anonymous"></script>
    <script src="https://www.gstatic.com/firebasejs/12.18.0/firebase-messaging-compat.js" integrity="sha384-Rab5/1ixag3DHS8jtiL3IP3GImyxY/N7O2o1pD3PCKHY8GjCAhWkZrMJ0+twTiv7" crossorigin="anonymous"></script>

    <!-- Firebase Config — diinject dari PHP .env (public keys, selamat untuk frontend) -->
    <script>
        window.FIREBASE_CONFIG = {
            apiKey:            "<?= FCM_API_KEY ?>",
            authDomain:        "<?= FCM_AUTH_DOMAIN ?>",
            projectId:         "<?= FCM_PROJECT_ID ?>",
            storageBucket:     "<?= FCM_STORAGE_BUCKET ?>",
            messagingSenderId: "<?= FCM_MESSAGING_SENDER_ID ?>",
            appId:             "<?= FCM_APP_ID ?>",
            vapidKey:          "<?= FCM_VAPID_KEY ?>",
        };
        window.CSRF_TOKEN = "<?= Helper::csrfToken() ?>";
        // Browser actions must stay on the exact host currently in use. This
        // avoids losing the login session when APP_URL and www/non-www differ.
        window.APP_BASE_URL = new URL('index.php', window.location.href).href;
    </script>
</head>
<body>
    <div class="app-wrapper">
