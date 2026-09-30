<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Forgot Password | TBBA</title>
    <link rel="icon" href="assets/images/logo.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha384-iw3OoTErCYJJB9mCa8LNS2hbsQ7M3C0EpIsO/H5+EGAkPGc6rk+V8i04oW/K5xq0" crossorigin="anonymous">
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;background:linear-gradient(135deg,#0f172a,#1e3a8a);font-family:Inter,Arial,sans-serif}
        .auth-card{width:min(430px,100%);box-sizing:border-box;padding:34px;border-radius:20px;background:#fff;box-shadow:0 24px 60px rgba(2,6,23,.35)}
        .auth-logo{height:48px}.auth-card h1{margin:22px 0 7px;color:#0f172a;font-size:24px}.auth-card p{margin:0 0 23px;color:#64748b;font-size:13px;line-height:1.6}
        .auth-card label{display:block;margin-bottom:7px;color:#334155;font-size:11px;font-weight:800}.auth-card input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #cbd5e1;border-radius:10px;font:inherit}
        .auth-card button{width:100%;margin-top:15px;padding:12px;border:0;border-radius:10px;background:#2563eb;color:#fff;font-weight:800;cursor:pointer}
        .auth-back{display:block;margin-top:18px;text-align:center;color:#2563eb;font-size:12px;font-weight:700;text-decoration:none}
        .auth-note{margin-top:18px!important;padding:12px;border-radius:9px;background:#eff6ff;color:#1e40af!important;font-size:10px!important}
    </style>
</head>
<body>
<main class="auth-card">
    <img class="auth-logo" src="assets/images/logo.png" alt="TBBA">
    <h1>Reset your password</h1>
    <p>Enter your registered company email. If the account exists, we will send a secure reset link valid for 30 minutes.</p>
    <form id="forgotForm">
        <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">
        <label for="resetEmail">Company Email</label>
        <input id="resetEmail" type="email" name="email" autocomplete="email" required placeholder="name@company.com">
        <button id="resetButton" type="submit"><i class="fa-solid fa-paper-plane"></i> Send Reset Link</button>
    </form>
    <p class="auth-note"><i class="fa-solid fa-shield-halved"></i> For security, the system does not reveal whether an email address is registered.</p>
    <a class="auth-back" href="index.php?page=login"><i class="fa-solid fa-arrow-left"></i> Back to sign in</a>
</main>
<div id="toast-container"></div>
<script src="assets/js/app.js"></script>
<script>
document.getElementById('forgotForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const result = await App.post('index.php?action=request_password_reset', new FormData(event.target), document.getElementById('resetButton'));
    if (result?.status === 'success') event.target.reset();
});
</script>
</body>
</html>
