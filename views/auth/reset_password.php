<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Set New Password | TBBA</title>
    <link rel="icon" href="assets/images/logo.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha384-iw3OoTErCYJJB9mCa8LNS2hbsQ7M3C0EpIsO/H5+EGAkPGc6rk+V8i04oW/K5xq0" crossorigin="anonymous">
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;background:linear-gradient(135deg,#0f172a,#1e3a8a);font-family:Inter,Arial,sans-serif}
        .auth-card{width:min(450px,100%);box-sizing:border-box;padding:34px;border-radius:20px;background:#fff;box-shadow:0 24px 60px rgba(2,6,23,.35)}
        .auth-logo{height:48px}
        .auth-card h1{margin:22px 0 7px;color:#0f172a;font-size:24px}
        .auth-card p{margin:0 0 20px;color:#64748b;font-size:12px;line-height:1.6}
        .auth-card label{display:block;margin:13px 0 7px;color:#334155;font-size:11px;font-weight:800}
        .auth-card input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #cbd5e1;border-radius:10px;font:inherit}
        .auth-card button{width:100%;margin-top:18px;padding:12px;border:0;border-radius:10px;background:#2563eb;color:#fff;font-weight:800;cursor:pointer}
        .requirements{padding:12px;border-radius:9px;background:#f8fafc;color:#475569!important;font-size:10px!important}
        .invalid{padding:18px;border:1px solid #fecaca;border-radius:10px;background:#fef2f2;color:#991b1b}
        .auth-back{display:block;margin-top:18px;text-align:center;color:#2563eb;font-size:12px;font-weight:700;text-decoration:none}
    </style>
</head>
<body>
    <main class="auth-card">
        <img class="auth-logo" src="assets/images/logo.png" alt="TBBA">
        <?php if (!$resetUser): ?>
            <h1>Reset link unavailable</h1>
            <div class="invalid">This password reset link is invalid, expired, or has already been used.</div>
            <a class="auth-back" href="index.php?page=forgot_password">Request a new reset link</a>
        <?php else: ?>
            <h1>Choose a new password</h1>
            <p>Set a strong password for <?= htmlspecialchars($resetUser['email']) ?>.</p>
            <form id="resetPasswordForm">
                <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($resetToken) ?>">
                <label for="newPassword">New Password</label>
                <input id="newPassword" type="password" name="password" autocomplete="new-password" minlength="6" required>
                <label for="confirmPassword">Confirm New Password</label>
                <input id="confirmPassword" type="password" name="password_confirm" autocomplete="new-password" minlength="6" required>
                <p class="requirements">Use at least 6 characters with uppercase, lowercase, a number and a symbol.</p>
                <button id="savePasswordButton" type="submit"><i class="fa-solid fa-key"></i> Set New Password</button>
            </form>
        <?php endif; ?>
        <a class="auth-back" href="index.php?page=login"><i class="fa-solid fa-arrow-left"></i> Back to sign in</a>
    </main>
    <div id="toast-container"></div>
    <script src="assets/js/app.js"></script>
    <?php if ($resetUser): ?>
        <script>
            document.getElementById('resetPasswordForm').addEventListener('submit', async event => {
                event.preventDefault();
                const password = document.getElementById('newPassword').value;
                const confirmation = document.getElementById('confirmPassword').value;
                if (password !== confirmation) {
                    App.showToast('error', 'Passwords do not match.');
                    return;
                }
                const result = await App.post(
                    'index.php?action=reset_password',
                    new FormData(event.target),
                    document.getElementById('savePasswordButton')
                );
                if (result?.status === 'success') {
                    setTimeout(() => location.href = result.data?.redirect || 'index.php?page=login', 900);
                }
            });
        </script>
    <?php endif; ?>
</body>
</html>
