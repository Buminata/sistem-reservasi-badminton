/**
 * Optimization Utilities untuk Production
 * - Error handling improvements
 * - Performance optimizations
 * - Security enhancements
 */

(function() {
    'use strict';

    // Global error handler
    window.addEventListener('error', function(e) {
        console.error('Global error:', e.error);
        // Jangan tampilkan error detail ke user di production
        if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
            // Production mode - log error tanpa expose detail
            if (typeof console !== 'undefined' && console.error) {
                console.error('An error occurred. Please contact support.');
            }
        }
    });

    // Unhandled promise rejection handler
    window.addEventListener('unhandledrejection', function(e) {
        console.error('Unhandled promise rejection:', e.reason);
        e.preventDefault(); // Prevent default browser error handling
    });

    // Optimize fetch requests with timeout
    const originalFetch = window.fetch;
    window.fetch = function(url, options = {}) {
        const timeout = options.timeout || 30000; // 30 seconds default
        
        return Promise.race([
            originalFetch(url, options),
            new Promise((_, reject) => {
                setTimeout(() => reject(new Error('Request timeout')), timeout);
            })
        ]).catch(error => {
            if (error.message === 'Request timeout') {
                throw new Error('Request timeout. Please check your connection.');
            }
            throw error;
        });
    };

    // Debounce function untuk optimasi performance
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Throttle function untuk optimasi performance
    function throttle(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    // Expose utilities
    window.optimizeUtils = {
        debounce,
        throttle
    };
})();


