<?php
/** Modern account profile and preferences page. */
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';

$displayPosition = $user['position_title'] ?? $user['position'] ?? 'Position not assigned';
$employeeId = $user['employee_id'] ?: 'TBBA-' . str_pad((string)$user['id'], 4, '0', STR_PAD_LEFT);
$department = $user['department_name'] ?: 'Not assigned';
$branch = $user['branch_name'] ?: 'Not assigned';
$roleLabel = Auth::roleLabel($user['role'] ?? 'staff');
$nameParts = preg_split('/\s+/', trim($user['name']));
$initials = strtoupper(mb_substr($nameParts[0] ?? 'U', 0, 1) . mb_substr($nameParts[count($nameParts) - 1] ?? '', 0, 1));
$avatarFile = !empty($user['avatar']) ? basename($user['avatar']) : '';
$avatarPath = $avatarFile && is_file(__DIR__ . '/../../uploads/avatars/' . $avatarFile)
    ? 'uploads/avatars/' . rawurlencode($avatarFile)
    : '';
?>

<style>
.profile-page{padding:24px;max-width:1450px;margin:0 auto}.profile-heading{display:flex;justify-content:space-between;align-items:end;gap:18px;flex-wrap:wrap;margin-bottom:18px}.profile-heading h2{margin:0;color:var(--text-dark);font-size:24px}.profile-heading p{margin:5px 0 0;color:var(--text-muted);font-size:12px}.profile-id-pill{padding:7px 11px;border:1px solid var(--border-color);border-radius:999px;background:var(--bg-card);color:var(--text-muted);font-size:11px;font-weight:800}
.profile-layout{display:grid;grid-template-columns:310px minmax(0,1fr);gap:18px;align-items:start}.profile-identity{position:sticky;top:20px;overflow:hidden;border:1px solid var(--border-color);border-radius:20px;background:var(--bg-card);box-shadow:0 8px 24px rgba(15,23,42,.07)}.profile-cover{height:104px;background:linear-gradient(135deg,#172554,#1D4ED8 60%,#38BDF8);position:relative}.profile-cover:after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 80% 10%,rgba(255,255,255,.28),transparent 40%)}.profile-person{padding:0 22px 22px;text-align:center}.profile-avatar-wrap{position:relative;width:116px;height:116px;margin:-58px auto 13px}.profile-avatar{width:100%;height:100%;border-radius:50%;border:5px solid var(--bg-card);background:#DBEAFE;color:#1D4ED8;display:flex;align-items:center;justify-content:center;overflow:hidden;font-size:35px;font-weight:900;box-shadow:0 8px 20px rgba(15,23,42,.18)}.profile-avatar img{width:100%;height:100%;object-fit:cover}.profile-camera{position:absolute;right:3px;bottom:7px;width:34px;height:34px;border:3px solid var(--bg-card);border-radius:50%;background:#2563EB;color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 4px 10px rgba(37,99,235,.3)}.profile-camera:hover{background:#1D4ED8}.profile-person h3{margin:0;color:var(--text-dark);font-size:20px;line-height:1.3}.profile-position{margin:6px 0 10px;color:var(--text-muted);font-size:12px}.profile-role{display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border-radius:999px;background:#EEF2FF;color:#4338CA;font-size:10px;font-weight:800;text-transform:uppercase}.profile-upload-note{min-height:16px;margin-top:8px;color:#2563EB;font-size:10px;font-weight:700}
.profile-facts{margin-top:20px;border-top:1px solid var(--border-color);text-align:left}.profile-fact{display:grid;grid-template-columns:30px minmax(0,1fr);gap:9px;padding:11px 0;border-bottom:1px solid var(--border-color)}.profile-fact:last-child{border-bottom:0}.profile-fact i{width:28px;height:28px;border-radius:8px;background:var(--bg-primary);color:#2563EB;display:flex;align-items:center;justify-content:center}.profile-fact small{display:block;color:var(--text-muted);font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.4px}.profile-fact strong{display:block;margin-top:2px;color:var(--text-dark);font-size:12px;overflow-wrap:anywhere}.profile-health{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:14px}.profile-health span{padding:8px;border:1px solid #BBF7D0;border-radius:9px;background:#F0FDF4;color:#15803D;font-size:9px;font-weight:800}.profile-health span:last-child{border-color:#BFDBFE;background:#EFF6FF;color:#1D4ED8}
.profile-workspace{min-height:610px;border:1px solid var(--border-color);border-radius:20px;background:var(--bg-card);box-shadow:0 8px 24px rgba(15,23,42,.06);overflow:hidden}.profile-tabs{display:flex;gap:5px;padding:12px 16px;border-bottom:1px solid var(--border-color);background:var(--bg-primary);overflow-x:auto}.profile-tab{display:inline-flex;align-items:center;gap:7px;padding:9px 13px;border:0;border-radius:9px;background:transparent;color:var(--text-muted);font:inherit;font-size:12px;font-weight:800;white-space:nowrap;cursor:pointer}.profile-tab.active{background:var(--bg-card);color:#1D4ED8;box-shadow:0 2px 7px rgba(15,23,42,.08)}.profile-panel{display:none;padding:25px}.profile-panel.active{display:block}.profile-panel-head{margin-bottom:22px}.profile-panel-head h3{margin:0;color:var(--text-dark);font-size:18px}.profile-panel-head p{margin:5px 0 0;color:var(--text-muted);font-size:12px}.profile-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:17px}.profile-field.full{grid-column:1/-1}.profile-label{display:flex;justify-content:space-between;gap:10px;margin-bottom:7px;color:var(--text-dark);font-size:11px;font-weight:800}.profile-control{display:block;width:100%;box-sizing:border-box;min-height:44px;padding:10px 12px;border:1px solid #CBD5E1;border-radius:10px;background:var(--bg-card);color:var(--text-dark);font:inherit;font-size:13px;outline:none}.profile-control:focus{border-color:#2563EB;box-shadow:0 0 0 3px rgba(37,99,235,.1)}.profile-readonly{display:flex;align-items:center;gap:10px;min-height:44px;padding:9px 12px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-primary);color:var(--text-dark);font-size:13px;font-weight:600}.profile-readonly i{color:#94A3B8}.profile-help{display:block;margin-top:6px;color:var(--text-muted);font-size:10px;line-height:1.5}.profile-tag{padding:3px 7px;border-radius:999px;background:#F1F5F9;color:#64748B;font-size:9px;text-transform:uppercase}.profile-tag.editable{background:#EDE9FE;color:#7C3AED}.profile-password{position:relative}.profile-password .profile-control{padding-right:43px}.profile-eye{position:absolute;right:6px;top:50%;transform:translateY(-50%);width:34px;height:34px;border:0;border-radius:8px;background:transparent;color:#64748B;cursor:pointer}.profile-security-note{grid-column:1/-1;padding:13px 15px;border:1px solid #BFDBFE;border-radius:11px;background:#EFF6FF;color:#1E3A8A;font-size:11px;line-height:1.55}.profile-actions{display:flex;justify-content:flex-end;gap:9px;margin-top:25px;padding-top:18px;border-top:1px solid var(--border-color)}.profile-button{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:40px;padding:9px 16px;border-radius:9px;border:1px solid var(--border-color);background:var(--bg-card);color:var(--text-dark);font:inherit;font-size:11px;font-weight:800;cursor:pointer}.profile-button.primary{border-color:#2563EB;background:#2563EB;color:#fff;box-shadow:0 4px 10px rgba(37,99,235,.2)}.profile-button.primary:hover{background:#1D4ED8}.profile-button:disabled{opacity:.6;cursor:not-allowed}
.profile-button.btn-success{border-color:#16A34A;background:#16A34A;color:#fff}.profile-button.btn-secondary{background:#F1F5F9;color:#64748B}.profile-button.btn-outline-primary{border-color:#2563EB;color:#2563EB}
.profile-settings-list{display:grid;gap:12px}.profile-setting{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:16px;border:1px solid var(--border-color);border-radius:13px;background:var(--bg-card)}.profile-setting-icon{display:flex;align-items:center;gap:12px}.profile-setting-icon>i{width:38px;height:38px;border-radius:11px;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;font-size:16px}.profile-setting h4{margin:0;color:var(--text-dark);font-size:13px}.profile-setting p{margin:4px 0 0;color:var(--text-muted);font-size:10px}.profile-setting-status{padding:5px 9px;border-radius:999px;background:#F1F5F9;color:#64748B;font-size:9px;font-weight:800;text-transform:uppercase}.profile-setting-status.on{background:#DCFCE7;color:#15803D}
@media(max-width:1000px){.profile-layout{grid-template-columns:260px minmax(0,1fr)}}@media(max-width:780px){.profile-layout{grid-template-columns:1fr}.profile-identity{position:static}.profile-form-grid{grid-template-columns:1fr}.profile-field.full,.profile-security-note{grid-column:auto}.profile-workspace{min-height:0}}@media(max-width:520px){.profile-page{padding:14px}.profile-panel{padding:18px}.profile-setting{align-items:flex-start;flex-direction:column}.profile-setting .profile-button{width:100%}.profile-actions{flex-direction:column-reverse}.profile-actions .profile-button{width:100%}}
</style>

<div class="page-content profile-page">
    <div class="profile-heading">
        <div><h2>My Profile</h2><p>Manage your personal identity, account security and notification preferences.</p></div>
        <span class="profile-id-pill"><i class="fa-solid fa-id-badge"></i> <?= htmlspecialchars($employeeId) ?></span>
    </div>

    <form id="profileForm" class="profile-layout" onsubmit="submitProfileUpdate(event)" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">
        <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/gif,image/webp" hidden onchange="previewProfileAvatar(this)">

        <aside class="profile-identity">
            <div class="profile-cover"></div>
            <div class="profile-person">
                <div class="profile-avatar-wrap">
                    <div class="profile-avatar">
                        <img id="avatarPreviewImage" src="<?= $avatarPath ? htmlspecialchars($avatarPath) : '' ?>" data-original="<?= $avatarPath ? htmlspecialchars($avatarPath) : '' ?>" alt="Profile picture" style="<?= $avatarPath ? '' : 'display:none;' ?>">
                        <span id="avatarInitials" style="<?= $avatarPath ? 'display:none;' : '' ?>"><?= htmlspecialchars($initials) ?></span>
                    </div>
                    <label class="profile-camera" for="avatarInput" title="Change profile picture"><i class="fa-solid fa-camera"></i></label>
                </div>
                <h3><?= htmlspecialchars($user['name']) ?></h3>
                <div class="profile-position"><?= htmlspecialchars($displayPosition) ?></div>
                <span class="profile-role"><i class="fa-solid fa-shield-halved"></i><?= htmlspecialchars($roleLabel) ?></span>
                <div class="profile-upload-note" id="avatarUploadNote">Click the camera to change your picture</div>

                <div class="profile-facts">
                    <div class="profile-fact"><i class="fa-solid fa-building-user"></i><div><small>Department</small><strong><?= htmlspecialchars($department) ?></strong></div></div>
                    <div class="profile-fact"><i class="fa-solid fa-code-branch"></i><div><small>Branch</small><strong><?= htmlspecialchars($branch) ?></strong></div></div>
                    <div class="profile-fact"><i class="fa-solid fa-envelope"></i><div><small>Company Email</small><strong><?= htmlspecialchars($user['email']) ?></strong></div></div>
                </div>
                <div class="profile-health">
                    <span><i class="fa-solid fa-circle-check"></i> Account Active</span>
                    <span><i class="fa-solid fa-lock"></i> Email Protected</span>
                </div>
            </div>
        </aside>

        <section class="profile-workspace">
            <nav class="profile-tabs" aria-label="Profile settings">
                <button type="button" class="profile-tab active" data-panel="profilePersonal" onclick="openProfilePanel(this)"><i class="fa-solid fa-user"></i> Personal Details</button>
                <button type="button" class="profile-tab" data-panel="profileSecurity" onclick="openProfilePanel(this)"><i class="fa-solid fa-key"></i> Security</button>
                <button type="button" class="profile-tab" data-panel="profileNotifications" onclick="openProfilePanel(this)"><i class="fa-solid fa-bell"></i> Notifications</button>
            </nav>

            <div class="profile-panel active" id="profilePersonal">
                <div class="profile-panel-head"><h3>Personal Details</h3><p>Keep your display name and account information accurate.</p></div>
                <div class="profile-form-grid">
                    <div class="profile-field full">
                        <label class="profile-label" for="nameInput"><span>Full Name *</span><span class="profile-tag editable">Editable</span></label>
                        <input class="profile-control" type="text" name="name" id="nameInput" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                    <div class="profile-field full">
                        <label class="profile-label"><span>Official Company Email</span><span class="profile-tag <?= Auth::isSuperAdmin() ? 'editable' : '' ?>"><?= Auth::isSuperAdmin() ? 'Super Admin Editable' : 'Protected' ?></span></label>
                        <?php if (Auth::isSuperAdmin()): ?>
                        <input class="profile-control" type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        <small class="profile-help">This email is used to sign in. Confirm the address carefully before saving.</small>
                        <?php else: ?>
                        <div class="profile-readonly"><i class="fa-solid fa-lock"></i><?= htmlspecialchars($user['email']) ?></div>
                        <small class="profile-help">Only a Super Administrator can change the official login email.</small>
                        <?php endif; ?>
                    </div>
                    <div class="profile-field">
                        <label class="profile-label"><span>Position / Designation</span><span class="profile-tag">Managed by HR</span></label>
                        <div class="profile-readonly"><i class="fa-solid fa-briefcase"></i><?= htmlspecialchars($displayPosition) ?></div>
                    </div>
                    <div class="profile-field">
                        <label class="profile-label"><span>Employee ID</span><span class="profile-tag">System Record</span></label>
                        <div class="profile-readonly"><i class="fa-solid fa-id-card"></i><?= htmlspecialchars($employeeId) ?></div>
                    </div>
                    <div class="profile-field">
                        <label class="profile-label"><span>Department</span></label>
                        <div class="profile-readonly"><i class="fa-solid fa-building"></i><?= htmlspecialchars($department) ?></div>
                    </div>
                    <div class="profile-field">
                        <label class="profile-label"><span>Branch</span></label>
                        <div class="profile-readonly"><i class="fa-solid fa-location-dot"></i><?= htmlspecialchars($branch) ?></div>
                    </div>
                </div>
                <div class="profile-actions"><button type="button" class="profile-button" onclick="resetProfileForm()"><i class="fa-solid fa-rotate-left"></i> Reset</button><button type="submit" class="profile-button primary"><i class="fa-solid fa-check"></i> Save Personal Details</button></div>
            </div>

            <div class="profile-panel" id="profileSecurity">
                <div class="profile-panel-head"><h3>Account Security</h3><p>Manage your password, authenticator and active device sessions.</p></div>
                <div class="profile-form-grid">
                    <?php if(!empty($_GET['expired'])): ?><div class="profile-security-note" style="border-color:#FCA5A5;background:#FEF2F2;color:#991B1B;"><i class="fa-solid fa-triangle-exclamation"></i> Your password has expired. Set a new password before continuing to other modules.</div><?php else: ?><div class="profile-security-note"><i class="fa-solid fa-shield-halved"></i> Changing your password will automatically sign out your other devices.</div><?php endif; ?>
                    <div class="profile-field full">
                        <label class="profile-label" for="currentPassInput"><span>Current Password</span></label>
                        <div class="profile-password"><input class="profile-control" type="password" name="current_password" id="currentPassInput" autocomplete="current-password" placeholder="Required when changing password"><button type="button" class="profile-eye" onclick="togglePasswordVisibility('currentPassInput', this)" aria-label="Show password"><i class="fa-solid fa-eye"></i></button></div>
                    </div>
                    <div class="profile-field">
                        <label class="profile-label" for="passInput"><span>New Password</span></label>
                        <div class="profile-password"><input class="profile-control" type="password" name="password" id="passInput" minlength="6" autocomplete="new-password" placeholder="Minimum 6 characters"><button type="button" class="profile-eye" onclick="togglePasswordVisibility('passInput', this)" aria-label="Show password"><i class="fa-solid fa-eye"></i></button></div>
                    </div>
                    <div class="profile-field">
                        <label class="profile-label" for="passConfirmInput"><span>Confirm New Password</span></label>
                        <div class="profile-password"><input class="profile-control" type="password" name="password_confirm" id="passConfirmInput" minlength="6" autocomplete="new-password" placeholder="Repeat the new password"><button type="button" class="profile-eye" onclick="togglePasswordVisibility('passConfirmInput', this)" aria-label="Show password"><i class="fa-solid fa-eye"></i></button></div>
                    </div>
                    <div class="profile-field full"><small class="profile-help"><i class="fa-solid fa-circle-info"></i> Use at least 6 characters containing uppercase, lowercase, a number and a symbol.</small></div>
                </div>
                <div class="profile-actions"><button type="button" class="profile-button" onclick="clearPasswordFields()"><i class="fa-solid fa-xmark"></i> Clear</button><button type="submit" class="profile-button primary"><i class="fa-solid fa-key"></i> Update Password</button></div>

                <div class="profile-panel-head" style="margin-top:28px;padding-top:23px;border-top:1px solid var(--border-color);"><h3>Two-Factor Authentication</h3><p>Require a rotating code from an authenticator app after your password.</p></div>
                <div class="profile-setting">
                    <div class="profile-setting-icon"><i class="fa-solid fa-mobile-screen-button"></i><div><h4>Authenticator App</h4><p><?= !empty($user['two_factor_enabled']) ? 'Two-factor authentication is protecting this account.' : 'Compatible with Google Authenticator, Microsoft Authenticator and similar apps.' ?></p></div></div>
                    <?php if(!empty($user['two_factor_enabled'])): ?><button type="button" class="profile-button" onclick="openDisableTwoFactor()"><i class="fa-solid fa-lock-open"></i> Disable 2FA</button><?php else: ?><button type="button" class="profile-button primary" onclick="beginTwoFactor(this)"><i class="fa-solid fa-shield-halved"></i> Set Up 2FA</button><?php endif; ?>
                </div>
                <?php if(!empty($user['two_factor_enabled'])): ?>
                <div class="profile-setting">
                    <div class="profile-setting-icon"><i class="fa-solid fa-laptop-file"></i><div><h4>Remembered Browsers <span class="profile-setting-status <?= $trustedDeviceCount > 0 ? 'on' : '' ?>"><?= (int)$trustedDeviceCount ?></span></h4><p>Browsers remembered after a successful 2FA check can skip the code for up to 90 days.</p></div></div>
                    <?php if($trustedDeviceCount > 0): ?><button type="button" class="profile-button" onclick="revokeTrustedDevices(this)"><i class="fa-solid fa-trash-can"></i> Forget All</button><?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="profile-panel-head" style="margin-top:28px;padding-top:23px;border-top:1px solid var(--border-color);"><h3>Active Device Sessions</h3><p>Review where your account is signed in and remove devices you no longer use.</p></div>
                <div class="profile-settings-list">
                    <?php foreach($activeSessions as $session): ?><div class="profile-setting" id="security-session-<?= (int)$session['id'] ?>"><div class="profile-setting-icon"><i class="fa-solid <?= str_contains($session['device_name'],'iOS')||str_contains($session['device_name'],'Android')?'fa-mobile-screen':'fa-laptop' ?>"></i><div><h4><?= htmlspecialchars($session['device_name']) ?> <?= $session['is_current']?'<span class="profile-setting-status on">Current</span>':'' ?></h4><p><?= htmlspecialchars($session['ip_address']?:'Unknown IP') ?> · Last active <?= date('d M Y, h:i A',strtotime($session['last_seen_at'])) ?></p></div></div><?php if(!$session['is_current']): ?><button type="button" class="profile-button" onclick="revokeDeviceSession(<?= (int)$session['id'] ?>,this)">Sign Out</button><?php endif; ?></div><?php endforeach; ?>
                </div>
                <?php if(count($activeSessions)>1): ?><div class="profile-actions"><button type="button" class="profile-button" onclick="revokeOtherSessions(this)"><i class="fa-solid fa-right-from-bracket"></i> Sign Out All Other Devices</button></div><?php endif; ?>
            </div>

            <div class="profile-panel" id="profileNotifications">
                <div class="profile-panel-head"><h3>Notification Preferences</h3><p>Choose how this device receives system updates.</p></div>
                <div class="profile-settings-list">
                    <div class="profile-setting">
                        <div class="profile-setting-icon"><i class="fa-solid fa-bell"></i><div><h4>In-App Notifications</h4><p>Updates remain available from the bell icon in the top navigation.</p></div></div>
                        <span class="profile-setting-status on">Always Active</span>
                    </div>
                    <div class="profile-setting">
                        <div class="profile-setting-icon"><i class="fa-solid fa-mobile-screen-button"></i><div><h4>Browser Push Notifications</h4><p id="tbba-push-status">Checking notification status on this device...</p></div></div>
                        <button type="button" id="tbba-push-toggle-btn" class="profile-button" onclick="toggleProfilePush()"><i class="fa-solid fa-bell"></i> Enable Notifications</button>
                    </div>
                    <div class="profile-setting">
                        <div class="profile-setting-icon"><i class="fa-solid fa-paper-plane"></i><div><h4>Test Notification</h4><p>Send a sample notification to confirm that this device is connected.</p></div></div>
                        <button type="button" class="profile-button" id="profileTestPushButton" onclick="sendProfileTestPush(this)"><i class="fa-solid fa-paper-plane"></i> Send Test</button>
                    </div>
                </div>
                <div style="margin-top:16px;padding:13px 15px;border:1px solid #FDE68A;border-radius:11px;background:#FFFBEB;color:#92400E;font-size:10px;line-height:1.55;"><i class="fa-solid fa-circle-info"></i> Notification permission is stored per browser and device. Enable it again when using a new phone or computer.</div>
            </div>
        </section>
    </form>
</div>

<div class="modal-overlay module-clean-modal" id="twoFactorSetupModal"><div class="modal-box" style="max-width:520px;"><div class="modal-header"><h3 class="modal-title">Set Up Authenticator</h3><button class="modal-close" onclick="App.closeModal('twoFactorSetupModal')">&times;</button></div><form id="twoFactorSetupForm" onsubmit="enableTwoFactor(event)"><div class="modal-body" style="padding:20px;"><p style="font-size:12px;color:var(--text-muted);line-height:1.6;">Open your authenticator app, add an account using the setup key below, then enter the generated 6-digit code.</p><label class="profile-label">Setup Key</label><div id="twoFactorSecret" style="padding:13px;border:1px dashed #2563EB;border-radius:9px;background:#EFF6FF;color:#1E3A8A;font:700 17px monospace;letter-spacing:2px;text-align:center;word-break:break-all;"></div><input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>"><label class="profile-label" style="margin-top:16px;">Authenticator Code</label><input class="profile-control" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required placeholder="000000" style="text-align:center;font-size:18px;letter-spacing:7px;"></div><div class="modal-footer"><button type="button" class="profile-button" onclick="App.closeModal('twoFactorSetupModal')">Cancel</button><button type="submit" class="profile-button primary">Verify and Enable</button></div></form></div></div>

<div class="modal-overlay module-clean-modal" id="twoFactorDisableModal"><div class="modal-box" style="max-width:500px;"><div class="modal-header"><h3 class="modal-title">Disable Two-Factor Authentication</h3><button class="modal-close" onclick="App.closeModal('twoFactorDisableModal')">&times;</button></div><form onsubmit="disableTwoFactor(event)"><div class="modal-body" style="padding:20px;"><div style="padding:12px;border:1px solid #FDE68A;border-radius:9px;background:#FFFBEB;color:#92400E;font-size:11px;">This reduces account protection. Confirm with both your password and authenticator code.</div><input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>"><label class="profile-label" style="margin-top:15px;">Current Password</label><input class="profile-control" type="password" name="current_password" required autocomplete="current-password"><label class="profile-label" style="margin-top:15px;">Authenticator Code</label><input class="profile-control" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required></div><div class="modal-footer"><button type="submit" class="profile-button" style="border-color:#DC2626;color:#B91C1C;">Disable 2FA</button></div></form></div></div>

<script>
function openProfilePanel(button) {
    document.querySelectorAll('.profile-tab').forEach(tab => tab.classList.toggle('active', tab === button));
    document.querySelectorAll('.profile-panel').forEach(panel => panel.classList.toggle('active', panel.id === button.dataset.panel));
}

function previewProfileAvatar(input) {
    const file = input.files?.[0];
    if (!file) return;
    if (file.type && !file.type.startsWith('image/')) {
        App.showToast('error', 'Please select a valid image file (JPG, PNG). HEIC is not supported.'); input.value = ''; return;
    }
    if (file.size > 5 * 1024 * 1024) {
        App.showToast('error', 'Profile picture must not exceed 5 MB.'); input.value = ''; return;
    }
    const image = document.getElementById('avatarPreviewImage');
    const url = URL.createObjectURL(file);
    image.onload = () => URL.revokeObjectURL(url);
    image.src = url; image.style.display = '';
    document.getElementById('avatarInitials').style.display = 'none';
    document.getElementById('avatarUploadNote').textContent = file.name + ' selected';
}

function resetProfileForm() {
    const form = document.getElementById('profileForm');
    form.reset();
    const image = document.getElementById('avatarPreviewImage');
    const original = image.dataset.original;
    image.src = original; image.style.display = original ? '' : 'none';
    document.getElementById('avatarInitials').style.display = original ? 'none' : '';
    document.getElementById('avatarUploadNote').textContent = 'Click the camera to change your picture';
}

function clearPasswordFields() {
    document.getElementById('currentPassInput').value = '';
    document.getElementById('passInput').value = '';
    document.getElementById('passConfirmInput').value = '';
}

function togglePasswordVisibility(inputId, button) {
    const input = document.getElementById(inputId);
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.innerHTML = `<i class="fa-solid ${show ? 'fa-eye-slash' : 'fa-eye'}"></i>`;
    button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
}

async function toggleProfilePush() {
    if (typeof FirebasePush === 'undefined') {
        App.showToast('error', 'Push notification service is not available.'); return;
    }
    const changed = await FirebasePush.toggleSubscription();
    if (changed !== false) updateProfileNotificationPermission();
}

function updateProfileNotificationPermission() {
    const status = document.getElementById('tbba-push-status');
    const button = document.getElementById('tbba-push-toggle-btn');
    if (!status) return;
    const push = typeof FirebasePush !== 'undefined' ? FirebasePush : null;
    if (push?.getLastError()) {
        status.textContent = push.getLastError();
        return;
    }
    const nativeNotifications = 'Notification' in window;
    const standardPush = 'serviceWorker' in navigator && 'PushManager' in window;
    if (!nativeNotifications && !standardPush) {
        status.textContent = 'This app context does not provide the required push APIs.';
        if (button) button.disabled = true;
        return;
    }
    if (nativeNotifications && Notification.permission === 'denied') status.textContent = 'Notifications are blocked in this browser. Update the site permission first.';
    else if (push?.isSubscribed()) status.textContent = 'Push notifications are active on this device.';
    else status.textContent = 'Push notifications are not enabled on this device.';
}

async function sendProfileTestPush(button) {
    if (typeof FirebasePush === 'undefined') {
        App.showToast('error', 'Push notification service is not available.');
        return;
    }
    const originalText = button?.innerHTML || '';
    const status = document.getElementById('tbba-push-status');
    if (status) status.textContent = 'Starting push notification test...';
    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Registering...';
    }
    try {
        const sent = await FirebasePush.sendTest();
        if (!sent) {
            const message = FirebasePush.getLastError?.() || 'The device could not be registered for push notifications.';
            if (status) status.textContent = message;
        }
        if (sent) updateProfileNotificationPermission();
    } finally {
        if (button) {
            button.disabled = false;
            button.innerHTML = originalText;
        }
    }
}

async function submitProfileUpdate(event) {
    event.preventDefault();
    const form = document.getElementById('profileForm');
    const button = event.submitter;
    const originalText = button?.innerHTML || '';
    const pass = document.getElementById('passInput').value.trim();
    const confirmation = document.getElementById('passConfirmInput').value.trim();
    const securityPanelActive = document.getElementById('profileSecurity').classList.contains('active');
    if (securityPanelActive && pass === '' && confirmation === '') {
        App.showToast('error', 'Enter a new password before updating security.'); return;
    }
    if (pass !== '' || confirmation !== '') {
        if (pass.length < 6) { App.showToast('error', 'New password must be at least 6 characters long.'); return; }
        if (pass !== confirmation) { App.showToast('error', 'New password and confirmation do not match.'); return; }
    }
    if (button) { button.disabled = true; button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...'; }
    try {
        const response = await fetch('index.php?action=update_profile', { method: 'POST', body: new FormData(form) });
        const data = await response.json();
        if (data?.status === 'success') { App.showToast('success', data.message); setTimeout(() => location.reload(), 900); }
        else App.showToast('error', data?.message || 'Failed to update profile.');
    } catch (error) {
        console.error('Profile update error:', error);
        App.showToast('error', 'Network error occurred while updating profile.');
    } finally {
        if (button) { button.disabled = false; button.innerHTML = originalText; }
    }
}

async function beginTwoFactor(button) {
    const fd = new FormData(); fd.append('csrf_token', window.CSRF_TOKEN || '');
    const result = await App.post('index.php?action=begin_two_factor', fd, button);
    if (result?.status === 'success') { document.getElementById('twoFactorSecret').textContent = result.data.secret; App.openModal('twoFactorSetupModal'); }
}
async function enableTwoFactor(event) { event.preventDefault(); const result=await App.post('index.php?action=enable_two_factor',new FormData(event.target),event.submitter); if(result?.status==='success')setTimeout(()=>location.reload(),700); }
function openDisableTwoFactor(){App.openModal('twoFactorDisableModal');}
async function disableTwoFactor(event){event.preventDefault();const result=await App.post('index.php?action=disable_two_factor',new FormData(event.target),event.submitter);if(result?.status==='success')setTimeout(()=>location.reload(),700);}
async function revokeDeviceSession(id,button){const fd=new FormData();fd.append('csrf_token',window.CSRF_TOKEN||'');fd.append('session_id',id);const result=await App.post('index.php?action=revoke_session',fd,button);if(result?.status==='success')document.getElementById('security-session-'+id)?.remove();}
async function revokeOtherSessions(button){const fd=new FormData();fd.append('csrf_token',window.CSRF_TOKEN||'');const result=await App.post('index.php?action=revoke_other_sessions',fd,button);if(result?.status==='success')setTimeout(()=>location.reload(),600);}
async function revokeTrustedDevices(button){if(!confirm('Forget every remembered browser for this account?'))return;const fd=new FormData();fd.append('csrf_token',window.CSRF_TOKEN||'');const result=await App.post('index.php?action=revoke_trusted_devices',fd,button);if(result?.status==='success')setTimeout(()=>location.reload(),600);}

document.addEventListener('DOMContentLoaded', () => {
    updateProfileNotificationPermission();
    const params = new URLSearchParams(location.search);
    if (params.get('tab') === 'security' || params.has('expired')) {
        const securityTab = document.querySelector('.profile-tab[data-panel="profileSecurity"]');
        if (securityTab) openProfilePanel(securityTab);
    }
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
