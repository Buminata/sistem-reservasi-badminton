# Optimasi untuk Production Hosting

## Fitur yang Telah Dioptimalkan

### 1. Dark Mode Auto-Switch
- **Mode Gelap**: Otomatis aktif dari jam 18:00 - 06:59
- **Mode Terang**: Otomatis aktif dari jam 07:00 - 17:59
- Auto-update setiap menit untuk switch otomatis
- Support manual override (opsional)

### 2. Error Handling
- Global error handler untuk catch semua error
- Unhandled promise rejection handler
- Timeout untuk fetch requests (30 detik)
- Error logging tanpa expose detail ke user di production

### 3. Security Headers
- CORS configuration untuk production
- X-Content-Type-Options: nosniff
- X-Frame-Options: SAMEORIGIN
- X-XSS-Protection enabled

### 4. Performance Optimizations
- Debounce dan throttle utilities
- Request timeout handling
- Optimized database queries dengan prepared statements

### 5. Database Auto-Setup
- Auto-create database jika tidak ada
- Auto-create tables dengan kolom yang diperlukan
- Auto-add missing columns untuk backward compatibility

## Cara Mengintegrasikan Dark Mode ke Semua File

Untuk setiap file HTML, tambahkan:

**Di `<head>` (setelah Bootstrap Icons):**
```html
<!-- Dark Mode CSS -->
<link rel="stylesheet" href="css/dark-mode.css" />
```

**Sebelum `</body>`:**
```html
<!-- Dark Mode Script -->
<script src="js/dark-mode.js"></script>
<!-- Optimization Script -->
<script src="js/optimize.js"></script>
```

## File yang Perlu Diupdate

1. index.html ✅
2. login.html (perlu update)
3. register.html (perlu update)
4. dashboard.html (perlu update)
5. membership.html (perlu update)
6. reservasi.html (perlu update)
7. jadwal.html (perlu update)
8. riwayat.html (perlu update)
9. about.html (perlu update)
10. membership-admin.html (perlu update)
11. jadwal-admin.html (perlu update)
12. reservasi-admin.html (perlu update)
13. user-admin.html (perlu update)

## Konfigurasi untuk Production

### 1. Update CORS di `api/db.php`
Ganti `$allowedOrigins` dengan domain production Anda:
```php
$allowedOrigins = [
    'https://yourdomain.com',
    'https://www.yourdomain.com'
];
```

### 2. Database Configuration
Pastikan konfigurasi database di `api/db.php` sesuai dengan hosting:
```php
$host = 'localhost'; // atau IP database server
$dbname = 'badminton';
$username = 'your_db_user';
$password = 'your_db_password';
```

### 3. File Permissions
Pastikan folder `api/uploads/` memiliki permission write:
```bash
chmod 755 api/uploads/
```

## Testing Checklist

- [ ] Dark mode aktif otomatis setelah jam 18:00
- [ ] Dark mode nonaktif otomatis setelah jam 07:00
- [ ] Semua halaman memiliki dark mode
- [ ] Error handling bekerja dengan baik
- [ ] Database auto-setup bekerja
- [ ] Upload file bekerja
- [ ] Semua fitur membership bekerja
- [ ] Export report bekerja
- [ ] Notifikasi admin bekerja

