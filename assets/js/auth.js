/**
 * Skrip AJAX Log Masuk (Auth Handler)
 * Syarikat: The Bridge Business Alliance (TBBA)
 */

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const submitBtn = document.getElementById('btnLoginSubmit');

    if (!loginForm) return;

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(loginForm);
        
        const result = await App.post('index.php?action=login', formData, submitBtn);

        if (result && result.status === 'success') {
            if (result.data?.requires_2fa) {
                loginForm.style.display = 'none';
                const twoFactorForm = document.getElementById('twoFactorForm');
                twoFactorForm.style.display = 'block';
                document.getElementById('twoFactorCode')?.focus();
                return;
            }
            // Tukar warna butang kepada hijau untuk maklum balas visual
            submitBtn.style.background = '#059669';
            submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> Redirecting...';
            
            setTimeout(() => {
                window.location.href = result.data.redirect || 'index.php?page=dashboard';
            }, 1000);
        }
    });

    const twoFactorForm = document.getElementById('twoFactorForm');
    if (twoFactorForm) {
        twoFactorForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = document.getElementById('twoFactorButton');
            const result = await App.post('index.php?action=verify_two_factor', new FormData(twoFactorForm), button);
            if (result?.status === 'success') {
                button.innerHTML = '<i class="fa-solid fa-check"></i> Verified';
                setTimeout(() => { window.location.href = result.data?.redirect || 'index.php?page=dashboard'; }, 700);
            }
        });
    }
});
