<?php
/**
 * Halaman Log Masuk Korporat (Modern UI)
 * Syarikat: The Bridge Business Alliance (TBBA)
 */
require_once __DIR__ . '/../../core/Helper.php';
require_once __DIR__ . '/../../core/Auth.php';

// Jika sudah log masuk, terus alihkan ke dashboard
if (Auth::check()) {
    Helper::redirect('index.php?page=dashboard');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>TBBA Staff Portal | Login</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha384-iw3OoTErCYJJB9mCa8LNS2hbsQ7M3C0EpIsO/H5+EGAkPGc6rk+V8i04oW/K5xq0" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body.login-page {
            background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 50%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-wrapper {
            width: 100%;
            max-width: 900px;
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            display: flex;
            overflow: hidden;
            min-height: 520px;
        }
        .login-banner {
            flex: 1.1;
            background: linear-gradient(180deg, #1E3A8A 0%, #0F172A 100%);
            color: #FFFFFF;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        .login-banner::after {
            content: '';
            position: absolute;
            bottom: -50px;
            right: -50px;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(59,130,246,0.2) 0%, rgba(0,0,0,0) 70%);
            border-radius: 50%;
        }
        .banner-header img {
            width: auto;
            max-width: 240px;
            height: auto;
            max-height: 70px;
            object-fit: contain;
            background: #FFFFFF;
            padding: 10px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 8px 20px -4px rgba(0, 0, 0, 0.25);
            display: block;
        }
        .banner-header h2 {
            font-size: 26px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 12px;
            color: #FFFFFF;
        }
        .banner-header p {
            font-size: 14px;
            color: #CBD5E1;
            line-height: 1.6;
        }
        .banner-footer {
            font-size: 12px;
            color: #94A3B8;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 15px;
        }
        .login-form-area {
            flex: 1;
            padding: 45px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-title {
            font-size: 22px;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 6px;
        }
        .login-subtitle {
            font-size: 13px;
            color: #64748B;
            margin-bottom: 28px;
        }
        .input-group-icon {
            position: relative;
        }
        .input-group-icon i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            transition: color 0.2s ease;
        }
        .input-group-icon .form-control {
            padding-left: 42px;
        }
        .input-group-icon .form-control:focus + i {
            color: #2563EB;
        }
        .input-group-icon .toggle-password {
            position: absolute;
            right: 14px;
            left: auto;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            cursor: pointer;
            transition: color 0.2s ease;
            z-index: 10;
        }
        .input-group-icon .toggle-password:hover {
            color: #2563EB;
        }
        .input-group-icon #passwordInput {
            padding-right: 42px;
        }
        .quick-fill-box {
            margin-top: 25px;
            padding: 14px;
            background: #F8FAFC;
            border: 1px dashed #CBD5E1;
            border-radius: 8px;
        }
        .quick-fill-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #475569;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .quick-btn {
            padding: 5px 10px;
            font-size: 11px;
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            color: #1E3A8A;
            transition: all 0.2s ease;
        }
        .quick-btn:hover {
            background: #1E3A8A;
            color: #FFFFFF;
            border-color: #1E3A8A;
        }
        @media (max-width: 768px) {
            .login-wrapper { flex-direction: column; }
            .login-banner { padding: 30px; }
            .banner-header img { max-width: 180px; max-height: 55px; margin-bottom: 16px; }
        }
    </style>
</head>
<body class="login-page">

<div class="login-wrapper">
    <!-- Banner Kiri Korporat -->
    <div class="login-banner">
        <div class="banner-header">
            <img src="assets/images/logo.png" alt="TBBA Logo">
            <h2>The Bridge Business Alliance</h2>
            <p>Bridging Businesses. Building the Future</p>
        </div>
        <div class="banner-footer">
            &copy; 2026 TBBA. All Rights Reserved.
        </div>
    </div>

    <!-- Right-side login form -->
    <div class="login-form-area">
        <h3 class="login-title">TBBA Portal Login</h3>
        <p class="login-subtitle">Please enter your email and password.</p>

        <?php if (!empty($_SESSION['login_error'])): ?><div style="margin-bottom:16px;padding:11px 13px;border:1px solid #FECACA;border-radius:9px;background:#FEF2F2;color:#B91C1C;font-size:11px;"><?= htmlspecialchars($_SESSION['login_error']); unset($_SESSION['login_error']); ?></div><?php endif; ?>

        <form id="loginForm" method="POST" action="index.php?action=login" onsubmit="return false;" style="<?= !empty($_SESSION['preauth_user_id']) ? 'display:none' : '' ?>">
            <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">
            
            <div class="form-group">
                <label class="form-label">Email</label>
                <div class="input-group-icon">
                    <input type="email" name="email" id="emailInput" class="form-control" placeholder="example@tbba.com" required autocomplete="email">
                    <i class="fa-solid fa-envelope"></i>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-group-icon">
                    <input type="password" name="password" id="passwordInput" class="form-control" placeholder="••••••••" required autocomplete="current-password" style="padding-right: 42px;">
                    <i class="fa-solid fa-lock"></i>
                    <i class="fa-solid fa-eye toggle-password" id="togglePasswordBtn" onclick="togglePasswordVisibility()" title="Show/Hide Password"></i>
                </div>
                <div style="margin-top:8px;text-align:right;"><a href="index.php?page=forgot_password" style="color:#2563EB;font-size:11px;font-weight:700;text-decoration:none;">Forgot password?</a></div>
            </div>

            <button type="submit" id="btnLoginSubmit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 14px; margin-top: 10px;">
                <i class="fa-solid fa-right-to-bracket" id="loginBtnIcon"></i>
                <span id="loginBtnText">Sign In Now</span>
            </button>
        </form>

        <form id="twoFactorForm" style="display:<?= !empty($_SESSION['preauth_user_id']) ? 'block' : 'none' ?>;margin-top:18px;padding:18px;border:1px solid #BFDBFE;border-radius:12px;background:#EFF6FF;">
            <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;color:#1E3A8A;"><i class="fa-solid fa-shield-halved" style="font-size:20px;"></i><div><strong style="display:block;font-size:13px;">Two-Factor Verification</strong><span style="font-size:10px;">Enter the 6-digit code from your authenticator app.</span></div></div>
            <input type="text" name="code" id="twoFactorCode" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" style="box-sizing:border-box;width:100%;padding:12px;border:1px solid #93C5FD;border-radius:9px;text-align:center;font-size:20px;letter-spacing:8px;" required>
            <label style="display:flex;align-items:flex-start;gap:9px;margin-top:12px;color:#334155;font-size:11px;line-height:1.45;cursor:pointer;">
                <input type="checkbox" name="remember_device" value="1" style="margin-top:2px;accent-color:#2563EB;">
                <span><strong style="display:block;">Remember this device for 90 days</strong>Only use this on a private phone or computer.</span>
            </label>
            <button type="submit" id="twoFactorButton" class="btn btn-primary" style="width:100%;margin-top:10px;padding:11px;"><i class="fa-solid fa-check"></i> Verify and Sign In</button>
        </form>

        <!-- Divider -->
        <div style="display: flex; align-items: center; margin: 20px 0; color: #94A3B8; font-size: 11px; font-weight: 700; text-transform: uppercase;">
            <span style="flex: 1; height: 1px; background: #E2E8F0;"></span>
            <span style="padding: 0 12px;">OR CONTINUE WITH</span>
            <span style="flex: 1; height: 1px; background: #E2E8F0;"></span>
        </div>

        <!-- Google Sign-In Button -->
        <a href="index.php?action=google_login" class="btn" style="display: flex; align-items: center; justify-content: center; gap: 12px; width: 100%; padding: 12px; background: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 12px; color: #1E293B; font-weight: 700; font-size: 14px; text-decoration: none; box-shadow: 0 2px 4px rgba(0,0,0,0.04); transition: all 0.2s;" onmouseover="this.style.background='#F8FAFC'; this.style.borderColor='#94A3B8';" onmouseout="this.style.background='#FFFFFF'; this.style.borderColor='#CBD5E1';">
            <svg width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
            </svg>
            <span>Sign in with Google</span>
        </a>

    </div>
</div>

<div id="toast-container"></div>
<script src="assets/js/app.js"></script>
<script src="assets/js/auth.js"></script>
<script>
    function togglePasswordVisibility() {
        const passInput = document.getElementById('passwordInput');
        const toggleIcon = document.getElementById('togglePasswordBtn');
        if (passInput.type === 'password') {
            passInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }
</script>
</body>
</html>
