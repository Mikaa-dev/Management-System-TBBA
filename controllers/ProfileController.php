<?php
/**
 * Controller Pengurusan Profil Pengguna (Profile Management)
 * Syarikat: The Bridge Business Alliance (TBBA)
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/AccountSecurity.php';
require_once __DIR__ . '/../models/AuditLog.php';

class ProfileController {
    // Paparkan halaman profil kakitangan / admin
    public static function index() {
        Auth::requireLogin();
        $sessionUser = Auth::user();
        
        // Dapatkan maklumat terkini dari database
        $user = User::findByIdWithDetails($sessionUser['id']);
        if (!$user) {
            Helper::redirect('index.php?page=dashboard');
            exit;
        }

        $pageTitle = "My Profile";
        $activeSessions = AccountSecurity::sessionsForUser((int)$sessionUser['id']);
        $trustedDeviceCount = AccountSecurity::trustedDeviceCount((int)$sessionUser['id']);
        include __DIR__ . '/../views/profile/index.php';
    }

    // Kemas kini profil pengguna (AJAX POST)
    public static function update() {
        Auth::requireLogin();
        $sessionUser = Auth::user();
        $id = (int)$sessionUser['id'];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
            return;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) {
            Helper::json('error', 'Invalid CSRF token security.');
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $password_confirm = trim($_POST['password_confirm'] ?? '');
        $currentPassword = (string)($_POST['current_password'] ?? '');

        if (empty($name)) {
            Helper::json('error', 'Full Name is required.');
            return;
        }

        // Dapatkan data sedia ada
        $existingUser = User::findById($id);
        if (!$existingUser) {
            Helper::json('error', 'User record not found.');
            return;
        }

        $email = $existingUser['email']; // Default kekalkan e-mel syarikat sedia ada
        // Hanya super admin yang boleh tukar email
        if (Auth::isSuperAdmin()) {
            $newEmail = trim($_POST['email'] ?? '');
            if (!empty($newEmail) && $newEmail !== $email) {
                if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                    Helper::json('error', 'Invalid email address format.');
                    return;
                }
                if (User::isEmailTaken($newEmail, $id)) {
                    Helper::json('error', 'This email address is already registered to another user.');
                    return;
                }
                $email = $newEmail;
            }
        }

        // Validate password BEFORE saving profile (prevent partial save on error)
        if (!empty($password)) {
            if (!password_verify($currentPassword, (string)$existingUser['password'])) {
                Helper::json('error', 'Current password is incorrect.');
                return;
            }
            if ($password !== $password_confirm) {
                Helper::json('error', 'New password and confirmation do not match.');
                return;
            }
            $passwordErrors = AccountSecurity::validatePassword($password);
            if ($passwordErrors) {
                Helper::json('error', 'New password must contain ' . implode(', ', $passwordErrors) . '.');
                return;
            }
            if (password_verify($password, (string)$existingUser['password'])) {
                Helper::json('error', 'Choose a new password that is different from your current password.');
                return;
            }
        }

        // Validate avatar before changing any profile data.
        $avatarUpload = null;
        if (isset($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                Helper::json('error', 'Profile picture upload failed. Please try a different image.');
            }
            if ((int)$_FILES['avatar']['size'] > 5 * 1024 * 1024) {
                Helper::json('error', 'Profile picture must not exceed 5 MB.');
            }
            $mimeToExtension = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            ];
            $fileType = mime_content_type($_FILES['avatar']['tmp_name']);
            if (!isset($mimeToExtension[$fileType])) {
                Helper::json('error', 'Invalid image format. Please upload JPG, PNG, GIF, or WEBP.');
            }
            $avatarUpload = [
                'tmp_name' => $_FILES['avatar']['tmp_name'],
                'extension' => $mimeToExtension[$fileType],
            ];
        }

        // Only update employee-owned fields. HR-owned organizational assignments
        // must remain unchanged when personal details are saved.
        User::updatePersonalDetails($id, $name, $email);

        // Jika pengguna ingin menukar kata laluan
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            AccountSecurity::updatePassword($id, $hashed);
            AccountSecurity::revokeOtherSessions($id);
            $_SESSION['password_expired'] = 0;
            AccountSecurity::event($id, 'password_changed', 'info', 'Password changed from My Profile.');
            AuditLog::record('PASSWORD_CHANGED', 'Changed account password and signed out other devices');
        }

        // Store avatar with a server-derived extension, never the original filename.
        if ($avatarUpload !== null) {
            $fileName = 'user_' . $id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $avatarUpload['extension'];
            $uploadDir = __DIR__ . '/../uploads/avatars/';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                Helper::json('error', 'Unable to prepare the profile picture folder.');
            }
            if (!move_uploaded_file($avatarUpload['tmp_name'], $uploadDir . $fileName)) {
                Helper::json('error', 'Unable to save the profile picture. Please try again.');
            }
            User::updateAvatar($id, $fileName);
            $_SESSION['user_avatar'] = $fileName;
        }

        // Kemas kini sesi semasa
        $_SESSION['user_name']  = $name;
        if (Auth::isSuperAdmin()) {
            $_SESSION['user_email'] = $email;
        }

        Helper::json('success', 'Profile updated successfully.');
    }
}
?>
