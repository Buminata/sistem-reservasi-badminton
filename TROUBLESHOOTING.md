# Troubleshooting Guide

## Masalah: "Terjadi kesalahan jaringan" saat Login/Registrasi

### Solusi 1: Pastikan Server PHP Berjalan
1. Buka XAMPP Control Panel
2. Start **Apache** dan **MySQL**
3. Pastikan tidak ada error di XAMPP

### Solusi 2: Periksa Konfigurasi Database
1. Buka file `api/db.php`
2. Pastikan konfigurasi sesuai:
   ```php
   $host = 'localhost';
   $dbname = 'badminton';
   $username = 'root';
   $password = '';
   ```

### Solusi 3: Buat Database
1. Buka phpMyAdmin (http://localhost/phpmyadmin)
2. Buat database baru dengan nama `badminton`
3. Import struktur tabel sesuai README.md

### Solusi 4: Test Koneksi Database
1. Buka browser
2. Akses: `http://localhost/Sistem-Reservasi-Lapangan-Badminton-main/api/test-connection.php`
3. Jika muncul error, periksa konfigurasi database

### Solusi 5: Periksa Browser Console
1. Buka Developer Tools (F12)
2. Lihat tab Console untuk error detail
3. Lihat tab Network untuk melihat request/response

## Masalah: Jadwal Lapangan Tidak Muncul

### Solusi 1: Periksa Koneksi Database
- Pastikan database `badminton` sudah dibuat
- Pastikan tabel `reservasi` sudah ada

### Solusi 2: Periksa Browser Console
- Buka Developer Tools (F12)
- Lihat error di Console
- Periksa Network tab untuk melihat response dari API

### Solusi 3: Test API Langsung
- Akses: `http://localhost/Sistem-Reservasi-Lapangan-Badminton-main/api/jadwal.php?date=2025-01-15`
- Harus mengembalikan JSON data

## Struktur Database yang Diperlukan

```sql
CREATE DATABASE badminton;
USE badminton;

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    no_tlp VARCHAR(20),
    role VARCHAR(20) DEFAULT 'user'
);

CREATE TABLE reservasi (
    reservasi_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    lapangan ENUM('Lapangan 1', 'Lapangan 2', 'Lapangan 3', 'Lapangan 4') NOT NULL,
    tanggal DATE NOT NULL,
    jam TIME NOT NULL,
    nama_reservasi VARCHAR(255) NOT NULL,
    status VARCHAR(50) DEFAULT 'Menunggu Konfirmasi',
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE pembayaran (
    pembayaran_id INT AUTO_INCREMENT PRIMARY KEY,
    reservasi_id INT NOT NULL,
    bukti_pembayaran VARCHAR(255),
    total_bayar DECIMAL(10,2),
    FOREIGN KEY (reservasi_id) REFERENCES reservasi(reservasi_id)
);
```

