# Daddies Arena Badminton 🏸

Sistem manajemen reservasi lapangan badminton berbasis web yang memungkinkan pengguna untuk melihat jadwal lapangan dan melakukan reservasi secara online.

## 📋 Deskripsi

Aplikasi web ini dikembangkan untuk memudahkan pengelolaan reservasi lapangan badminton dengan fitur-fitur lengkap untuk admin dan pengguna. Sistem ini menyediakan antarmuka yang user-friendly untuk melihat ketersediaan lapangan dan melakukan booking.

## ✨ Fitur Utama

### Untuk Pengguna:
- 🔐 **Sistem Login/Logout** - Autentikasi pengguna yang aman
- 📅 **Lihat Jadwal Lapangan** - Melihat ketersediaan lapangan berdasarkan tanggal
- 🏸 **Reservasi Lapangan** - Booking lapangan untuk waktu yang diinginkan
- ℹ️ **Halaman Tentang** - Informasi tentang sistem dan layanan

### Untuk Admin:
- 📊 **Dashboard Admin** - Panel khusus untuk mengelola sistem
- 📋 **Manajemen Reservasi** - Melihat dan mengelola semua reservasi
- 🕐 **Navigasi Jadwal** - Melihat jadwal dengan navigasi tanggal yang mudah

## 🛠️ Teknologi yang Digunakan

- **Frontend**: HTML (87.7%), CSS, JavaScript
- **Backend**: PHP (12.3%)
- **Database**: MySQL
- **Framework CSS**: Bootstrap 5.3.3
- **Icons**: Bootstrap Icons
- **Server**: Apache/Nginx dengan PHP

## 📁 Struktur Project

```
Daddies-Arena-Badminton/
├── api/
│   ├── db.php              # Konfigurasi koneksi database
│   ├── check.php           # Validasi session login
│   ├── jadwal.php          # API untuk mengelola jadwal
│   └── reservasi.php       # API untuk mengelola reservasi
├── index.html              # Halaman utama
├── login.html              # Halaman login
├── about.html              # Halaman tentang kami
├── jadwal.html             # Halaman jadwal untuk pengguna
├── jadwal-admin.html       # Halaman jadwal untuk admin
├── logout.php              # Script logout
└── README.md               # Dokumentasi project
```

## ⚙️ Instalasi dan Setup

### Prasyarat
- XAMPP/WAMP/LAMP (Apache, MySQL, PHP)
- Browser modern (Chrome, Firefox, Safari, Edge)

### 🚀 Instalasi Cepat (Auto-Setup)

**Sistem ini memiliki AUTO-SETUP!** Database dan tabel akan dibuat otomatis saat pertama kali diakses.

1. **Start XAMPP/WAMP**
   - Buka XAMPP Control Panel
   - **Start Apache** dan **MySQL**

2. **Akses Aplikasi**
   - Buka browser
   - Akses: `http://localhost/Sistem-Reservasi-Lapangan-Badminton-main/`
   - **Database dan tabel akan dibuat otomatis!**

3. **Login dengan Admin Default**
   - Email: `admin@daddiesarena.com`
   - Password: `admin123`
   - ⚠️ **Ganti password setelah login pertama!**

### 📝 Setup Manual (Opsional)

Jika ingin setup manual atau ada masalah dengan auto-setup:

1. **Akses Halaman Setup**
   - Buka: `http://localhost/Sistem-Reservasi-Lapangan-Badminton-main/setup.html`
   - Klik tombol "Jalankan Setup"

2. **Atau Setup Manual di phpMyAdmin**
   - Buka phpMyAdmin: `http://localhost/phpmyadmin`
   - Buat database `badminton`
   - Import struktur tabel (lihat INSTALL.md)

### ⚙️ Konfigurasi Database (Jika Perlu)

Edit file `api/db.php` jika konfigurasi berbeda:
```php
$host = 'localhost';
$dbname = 'badminton';
$username = 'root';
$password = '';
```

### 📁 Deploy ke Server
- Pindahkan semua file ke folder `htdocs` (XAMPP) atau `www` (WAMP)
- Akses melalui `http://localhost/Sistem-Reservasi-Lapangan-Badminton-main`

## 🚀 Cara Penggunaan

### Untuk Pengguna:
1. Buka aplikasi di browser
2. Login dengan akun yang telah terdaftar
3. Pilih menu "Jadwal" untuk melihat ketersediaan lapangan
4. Pilih tanggal, lapangan, dan jam yang diinginkan
5. Klik reservasi untuk melakukan booking

### Untuk Admin:
1. Login dengan akun admin
2. Akses dashboard admin untuk melihat semua reservasi
3. Gunakan fitur navigasi tanggal untuk melihat jadwal per hari
4. Kelola reservasi sesuai kebutuhan

## 🏸 Fitur Lapangan

Sistem mendukung **4 lapangan badminton** dengan jam operasional:
- **Jam Buka**: 10:00 WIB
- **Jam Tutup**: 23:00 WIB
- **Durasi Booking**: Per jam

## 🔧 API Endpoints

- `GET /api/jadwal.php?date=YYYY-MM-DD` - Mengambil jadwal berdasarkan tanggal
- `GET /api/reservasi.php` - Mengambil semua reservasi
- `POST /api/reservasi.php` - Membuat reservasi baru
- `GET /api/check.php` - Mengecek status login

## 🎨 Tampilan

Aplikasi menggunakan desain modern dengan:
- **Skema Warna**: Gradien biru (#3399ff) dan navy (#003366)
- **Responsive Design**: Kompatibel dengan desktop dan mobile
- **Bootstrap Framework**: Untuk tampilan yang konsisten
- **User Experience**: Interface yang intuitif dan mudah digunakan

## 🤝 Kontribusi

Untuk berkontribusi pada project ini:
1. Fork repository
2. Buat branch fitur baru (`git checkout -b fitur-baru`)
3. Commit perubahan (`git commit -m 'Menambah fitur baru'`)
4. Push ke branch (`git push origin fitur-baru`)
5. Buat Pull Request

## 📄 Lisensi

Project ini adalah open source dan tersedia di bawah lisensi MIT.

## 👨‍💻 Developer

Dikembangkan oleh **DzakaAl**

- GitHub: [@DzakaAl](https://github.com/DzakaAl)
- Repository: [Daddies-Arena-Badminton](https://github.com/DzakaAl/Daddies-Arena-Badminton)

## 📞 Support

Jika Anda mengalami masalah atau memiliki pertanyaan, silakan buat issue di repository GitHub atau hubungi developer.

---

⭐ **Jangan lupa untuk memberikan star pada repository ini jika bermanfaat!**
