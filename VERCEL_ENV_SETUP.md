# 🔧 Setup Environment Variables di Vercel

## Masalah yang Terjadi

1. **Error Database**: "Server error. Periksa konfigurasi database di api/db.php"
2. **Error Jadwal**: "Unexpected token '<'" - API mengembalikan HTML instead of JSON

## Solusi

### 1. Setup Database External

Vercel tidak menyediakan MySQL. Anda perlu menggunakan database external:

**Opsi A: PlanetScale (Recommended - Free tier)**
1. Daftar di https://planetscale.com
2. Buat database baru
3. Dapatkan connection string

**Opsi B: Railway (Free tier)**
1. Daftar di https://railway.app
2. Create new project → Add MySQL
3. Dapatkan connection details

**Opsi C: Free MySQL Hosting**
- https://www.freemysqlhosting.net
- https://www.db4free.net

### 2. Set Environment Variables di Vercel

Masuk ke Vercel Dashboard → Project Settings → Environment Variables:

```
DB_HOST=your-database-host
DB_NAME=your-database-name
DB_USER=your-database-username
DB_PASS=your-database-password
APP_ENV=production
```

**Contoh untuk PlanetScale:**
```
DB_HOST=aws.connect.psdb.cloud
DB_NAME=your-db-name
DB_USER=your-username
DB_PASS=your-password
APP_ENV=production
```

### 3. Import Database Schema

Setelah database dibuat, import struktur database:

1. Export database dari local (phpMyAdmin)
2. Import ke database external
3. Atau jalankan setup script di `api/setup.php`

### 4. Update Allowed Origins

Edit `api/db.php` dan tambahkan domain Vercel Anda:

```php
$allowedOrigins = [
    'http://localhost',
    'http://127.0.0.1',
    'https://sistem-reservasi-badminton-daddies.vercel.app',
    'https://your-custom-domain.vercel.app'
];
```

## Testing

Setelah setup:
1. Redeploy di Vercel
2. Test login: `https://your-app.vercel.app/login.html`
3. Test API: `https://your-app.vercel.app/api/check.php`
4. Test jadwal: `https://your-app.vercel.app/api/jadwal.php`

## Troubleshooting

### Error: "Database connection failed"
- Pastikan environment variables sudah di-set di Vercel
- Pastikan database accessible dari internet
- Cek firewall/whitelist IP (jika ada)

### Error: "Unexpected token '<'"
- Pastikan API selalu return JSON
- Cek browser console untuk error detail
- Pastikan routing di `index.php` benar

### Error: CORS
- Update `allowedOrigins` di `api/db.php`
- Pastikan domain Vercel sudah ditambahkan

