/**
 * Dark Mode System dengan Auto-Switch berdasarkan waktu
 * Mode gelap: 18:00 - 06:59
 * Mode terang: 07:00 - 17:59
 */
(function() {
    'use strict';

    const DARK_MODE_START = 18; // 18:00
    const LIGHT_MODE_START = 7; // 07:00

    // Cek apakah dark mode aktif berdasarkan waktu
    function shouldBeDarkMode() {
        const now = new Date();
        const hour = now.getHours();
        return hour >= DARK_MODE_START || hour < LIGHT_MODE_START;
    }

    // Apply dark mode
    function applyDarkMode(isDark) {
        const html = document.documentElement;
        const body = document.body;
        
        if (isDark) {
            html.classList.add('dark-mode');
            body.classList.add('dark-mode');
            localStorage.setItem('darkMode', 'true');
        } else {
            html.classList.remove('dark-mode');
            body.classList.remove('dark-mode');
            localStorage.setItem('darkMode', 'false');
        }
        
        // Trigger event untuk komponen lain
        window.dispatchEvent(new CustomEvent('darkModeChange', { detail: { isDark } }));
    }

    // Initialize dark mode
    function initDarkMode() {
        const isDark = shouldBeDarkMode();
        applyDarkMode(isDark);
        
        // Update setiap menit untuk auto-switch
        setInterval(() => {
            const shouldDark = shouldBeDarkMode();
            const currentDark = document.documentElement.classList.contains('dark-mode');
            
            if (shouldDark !== currentDark) {
                applyDarkMode(shouldDark);
            }
        }, 60000); // Check every minute
    }

    // Manual toggle (optional, untuk user override)
    function toggleDarkMode() {
        const currentDark = document.documentElement.classList.contains('dark-mode');
        const shouldDark = shouldBeDarkMode();
        
        // Jika user toggle manual, simpan preference
        if (currentDark === shouldDark) {
            // User override
            const manualOverride = localStorage.getItem('darkModeManual');
            if (manualOverride === 'true') {
                localStorage.removeItem('darkModeManual');
                initDarkMode(); // Kembali ke auto
            } else {
                localStorage.setItem('darkModeManual', 'true');
                applyDarkMode(!currentDark);
            }
        } else {
            // Apply manual override
            localStorage.setItem('darkModeManual', 'true');
            applyDarkMode(!currentDark);
        }
    }

    // Expose to global
    window.initDarkMode = initDarkMode;
    window.toggleDarkMode = toggleDarkMode;
    window.shouldBeDarkMode = shouldBeDarkMode;

    // Auto-init saat DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDarkMode);
    } else {
        initDarkMode();
    }
})();

