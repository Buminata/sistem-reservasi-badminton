# Panduan Instalasi Otomatis - Daddies Arena Badminton

## Instalasi Cepat (Auto-Setup)

Sistem ini sekarang memiliki **auto-setup** yang akan membuat database dan tabel secara otomatis!

### Langkah 1: Pastikan XAMPP/WAMP Berjalan
1. Buka XAMPP Control Panel
2. **Start Apache** dan **MySQL**
3. Pastikan tidak ada error

### Langkah 2: Akses Aplikasi
1. Buka browser
2. Akses: `http://localhost/Sistem-Reservasi-Lapangan-Badminton-main/`
3. Sistem akan **otomatis membuat database dan tabel** saat pertama kali diakses!

### Langkah 3: Setup Manual (Opsional)
Jika ingin setup manual, akses:
- `http://localhost/Sistem-Reservasi-Lapangan-Badminton-main/setup.html`

## Default Admin Account

Setelah setup, admin account otomatis dibuat:
- **Email:** admin@daddiesarena.com
- **Password:** admin123

**⚠️ PENTING:** Ganti password admin setelah pertama kali login!

## Struktur Database yang Dibuat Otomatis

### Tabel `users`
- user_id (INT, AUTO_INCREMENT)
- username (VARCHAR)
- email (VARCHAR, UNIQUE)
- password (VARCHAR, hashed)
- no_tlp (VARCHAR, optional)
- role (VARCHAR, default: 'user')
- created_at (TIMESTAMP)

### Tabel `reservasi`
- reservasi_id (INT, AUTO_INCREMENT)
- user_id (INT, FOREIGN KEY)
- lapangan (ENUM: 'Lapangan 1-4')
- tanggal (DATE)
- jam (TIME)
- nama_reservasi (VARCHAR)
- status (VARCHAR, default: 'Menunggu Konfirmasi')
- created_at (TIMESTAMP)

### Tabel `pembayaran`
- pembayaran_id (INT, AUTO_INCREMENT)
- reservasi_id (INT, FOREIGN KEY)
- bukti_pembayaran (VARCHAR)
- total_bayar (DECIMAL)
- created_at (TIMESTAMP)

## Troubleshooting

### Error: "Database connection failed"
1. Pastikan MySQL berjalan di XAMPP
2. Periksa konfigurasi di `api/db.php`:
   - host: localhost
   - username: root
   - password: (kosong untuk XAMPP default)

### Error: "Cannot connect to server"
1. Pastikan Apache berjalan
2. Pastikan file berada di folder `htdocs` (XAMPP) atau `www` (WAMP)
3. Cek URL: `http://localhost/Sistem-Reservasi-Lapangan-Badminton-main/`

### Database Tidak Terbuat Otomatis
1. Akses manual: `http://localhost/Sistem-Reservasi-Lapangan-Badminton-main/setup.html`
2. Atau buat manual di phpMyAdmin:
   - Database: `badminton`
   - Import struktur dari README.md

## Fitur Auto-Setup

✅ Auto-create database jika belum ada  
✅ Auto-create semua tabel yang diperlukan  
✅ Auto-create admin user default  
✅ Error handling yang graceful  
✅ Tidak perlu konfigurasi manual  

## Catatan

- Sistem akan otomatis membuat database saat pertama kali diakses
- Jika database sudah ada, sistem akan menggunakan yang sudah ada
- Semua tabel dibuat dengan `IF NOT EXISTS`, jadi aman untuk dijalankan berkali-kali
- Admin user hanya dibuat jika belum ada user dengan role 'admin'

