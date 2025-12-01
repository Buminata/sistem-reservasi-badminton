/**
 * Notification System untuk Admin
 * File ini berisi fungsi-fungsi untuk menampilkan notifikasi popup di halaman admin
 */

(function() {
  'use strict';
  
  let lastNotificationCheck = new Date().toISOString();
  let shownNotifications = new Set();
  
  function showNotification(notification) {
    // Skip jika sudah ditampilkan
    const notifKey = `${notification.type}_${notification.id}_${notification.created_at}`;
    if (shownNotifications.has(notifKey)) {
      return;
    }
    shownNotifications.add(notifKey);
    
    // Buat elemen notifikasi
    const notifDiv = document.createElement('div');
    notifDiv.className = 'notification-popup';
    notifDiv.style.cssText = `
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: #fff;
      padding: 1rem 1.5rem;
      border-radius: 12px;
      box-shadow: 0 8px 24px rgba(16, 185, 129, 0.4);
      margin-bottom: 1rem;
      animation: slideInRight 0.3s ease-out;
      cursor: pointer;
      position: relative;
      min-width: 300px;
    `;
    
    const icon = notification.type === 'reservasi' ? 'bi-calendar-check' : 'bi-star-fill';
    const iconColor = notification.type === 'reservasi' ? '#fff' : '#facc15';
    
    notifDiv.innerHTML = `
      <div style="display: flex; align-items: start; gap: 1rem;">
        <div style="font-size: 2rem; color: ${iconColor};">
          <i class="bi ${icon}"></i>
        </div>
        <div style="flex: 1;">
          <div style="font-weight: 700; font-size: 1.1rem; margin-bottom: 0.3rem;">
            ${notification.title}
          </div>
          <div style="font-size: 0.95rem; opacity: 0.95;">
            ${notification.message}
          </div>
          <div style="font-size: 0.8rem; opacity: 0.8; margin-top: 0.5rem;">
            ${new Date(notification.created_at).toLocaleString('id-ID')}
          </div>
        </div>
        <button class="btn-close-notif" style="background: none; border: none; color: #fff; font-size: 1.2rem; cursor: pointer; opacity: 0.7; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">
          <i class="bi bi-x"></i>
        </button>
      </div>
    `;
    
    // Pastikan container ada
    let container = document.getElementById('notificationContainer');
    if (!container) {
      container = document.createElement('div');
      container.id = 'notificationContainer';
      container.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 10000; max-width: 400px;';
      document.body.appendChild(container);
    }
    
    container.insertBefore(notifDiv, container.firstChild);
    
    // Auto remove setelah 8 detik
    const autoRemove = setTimeout(() => {
      if (notifDiv.parentNode) {
        notifDiv.style.animation = 'slideOutRight 0.3s ease-out';
        setTimeout(() => {
          if (notifDiv.parentNode) {
            notifDiv.parentNode.removeChild(notifDiv);
          }
        }, 300);
      }
    }, 8000);
    
    // Close button
    const closeBtn = notifDiv.querySelector('.btn-close-notif');
    closeBtn.onclick = function(e) {
      e.stopPropagation();
      clearTimeout(autoRemove);
      notifDiv.style.animation = 'slideOutRight 0.3s ease-out';
      setTimeout(() => {
        if (notifDiv.parentNode) {
          notifDiv.parentNode.removeChild(notifDiv);
        }
      }, 300);
    };
    
    // Click untuk redirect
    notifDiv.onclick = function() {
      if (notification.type === 'reservasi') {
        window.location.href = 'reservasi-admin.html';
      } else if (notification.type === 'membership') {
        window.location.href = 'membership-admin.html';
      }
    };
  }
  
  function checkNotifications() {
    fetch(`api/notifikasi.php?last_check=${encodeURIComponent(lastNotificationCheck)}`)
      .then(res => res.json())
      .then(data => {
        if (data.success && data.notifications && data.notifications.length > 0) {
          // Tampilkan notifikasi baru
          data.notifications.forEach(notif => {
            showNotification(notif);
          });
          
          // Update last check time
          lastNotificationCheck = data.last_check;
        }
      })
      .catch(error => {
        console.error('Error checking notifications:', error);
      });
  }
  
  // Initialize notification system
  function initNotificationSystem() {
    // Check notifikasi setiap 5 detik
    setInterval(checkNotifications, 5000);
    
    // Check notifikasi saat halaman load (delay 2 detik)
    setTimeout(checkNotifications, 2000);
    
    // CSS Animation
    if (!document.getElementById('notification-styles')) {
      const style = document.createElement('style');
      style.id = 'notification-styles';
      style.textContent = `
        @keyframes slideInRight {
          from {
            transform: translateX(100%);
            opacity: 0;
          }
          to {
            transform: translateX(0);
            opacity: 1;
          }
        }
        @keyframes slideOutRight {
          from {
            transform: translateX(0);
            opacity: 1;
          }
          to {
            transform: translateX(100%);
            opacity: 0;
          }
        }
        .notification-popup:hover {
          transform: translateX(-5px);
          box-shadow: 0 12px 32px rgba(16, 185, 129, 0.5) !important;
        }
      `;
      document.head.appendChild(style);
    }
  }
  
  // Export functions
  window.initNotificationSystem = initNotificationSystem;
  window.checkNotifications = checkNotifications;
  
  // Auto init jika DOM sudah ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNotificationSystem);
  } else {
    initNotificationSystem();
  }
})();

