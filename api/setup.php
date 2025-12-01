<?php
/**
 * Auto Setup Database - Otomatis membuat database dan tabel jika belum ada
 * File ini akan dipanggil otomatis jika database belum ada
 */

header('Content-Type: application/json');

// Konfigurasi database
$host = 'localhost';
$dbname = 'badminton';
$username = 'root';
$password = '';

try {
    // Koneksi ke MySQL tanpa database
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Cek apakah database sudah ada
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
    $databaseExists = $stmt->rowCount() > 0;
    
    if (!$databaseExists) {
        // Buat database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
    
    // Koneksi ke database
    $pdo->exec("USE `$dbname`");
    
    // Buat tabel users jika belum ada
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `user_id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `no_tlp` VARCHAR(20) DEFAULT NULL,
        `role` VARCHAR(20) DEFAULT 'user',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Buat tabel reservasi jika belum ada (tanpa foreign key untuk menghindari error)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `reservasi` (
        `reservasi_id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
                `lapangan` ENUM('Lapangan 1', 'Lapangan 2', 'Lapangan 3', 'Lapangan 4', 'Lapangan 5', 'Lapangan 6', 'Lapangan 7', 'Lapangan 8') NOT NULL,
        `tanggal` DATE NOT NULL,
        `jam` TIME NOT NULL,
        `nama_reservasi` VARCHAR(255) NOT NULL,
        `status` VARCHAR(50) DEFAULT 'Menunggu Konfirmasi',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_user` (`user_id`),
        INDEX `idx_tanggal` (`tanggal`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Buat tabel pembayaran jika belum ada (tanpa foreign key untuk menghindari error)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `pembayaran` (
        `pembayaran_id` INT AUTO_INCREMENT PRIMARY KEY,
        `reservasi_id` INT NOT NULL,
        `bukti_pembayaran` VARCHAR(255) DEFAULT NULL,
        `total_bayar` DECIMAL(10,2) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_reservasi` (`reservasi_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Cek apakah ada admin user, jika tidak buat default admin
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    $adminCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($adminCount == 0) {
        // Buat default admin user (password: admin123)
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, email, password, role) 
                    VALUES ('admin', 'admin@daddiesarena.com', '$adminPassword', 'admin')");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Database setup completed successfully',
        'database_created' => !$databaseExists,
        'admin_created' => $adminCount == 0
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Setup failed: ' . $e->getMessage()
    ]);
}
?>

