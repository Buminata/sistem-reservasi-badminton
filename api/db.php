<?php
// CORS Headers - Production ready
$allowedOrigins = [
    'http://localhost',
    'http://127.0.0.1',
    'https://daddiesarena.com',
    'https://www.daddiesarena.com',
    'https://sistem-reservasi-badminton-daddies.vercel.app',
    'https://sistem-reservasi-badminton.vercel.app',
    'https://*.vercel.app' // Allow all Vercel subdomains
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if (in_array($origin, $allowedOrigins) || 
    strpos($origin, 'localhost') !== false || 
    strpos($origin, '127.0.0.1') !== false ||
    strpos($origin, '.vercel.app') !== false) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    // Default untuk development - di production sebaiknya spesifik
    if (getenv('APP_ENV') !== 'production') {
        header('Access-Control-Allow-Origin: *');
    } else {
        // Di production, hanya allow origins yang terdaftar
        header('Access-Control-Allow-Origin: ' . (isset($allowedOrigins[0]) ? $allowedOrigins[0] : '*'));
    }
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// db.php - Database connection script dengan auto-setup
// Support environment variables untuk Vercel/Production
$host = getenv('DB_HOST') ?: 'localhost';      // Database host
$dbname = getenv('DB_NAME') ?: 'badminton';    // Your database name
$username = getenv('DB_USER') ?: 'root';       // Your database username
$password = getenv('DB_PASS') ?: '';           // Your database password

// Create a PDO instance to connect to the database
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Jika database tidak ada, coba auto-setup
    if (strpos($e->getMessage(), "Unknown database") !== false || 
        strpos($e->getMessage(), "1049") !== false) {
        
        // Coba koneksi ke MySQL tanpa database untuk setup
        try {
            $setupPdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
            $setupPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Buat database
            $setupPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $setupPdo->exec("USE `$dbname`");
            
            // Buat tabel users
            $setupPdo->exec("CREATE TABLE IF NOT EXISTS `users` (
                `user_id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(255) NOT NULL,
                `email` VARCHAR(255) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `no_tlp` VARCHAR(20) DEFAULT NULL,
                `role` VARCHAR(20) DEFAULT 'user',
                `is_member` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Buat tabel membership
            $setupPdo->exec("CREATE TABLE IF NOT EXISTS `membership` (
                `membership_id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `tanggal_mulai` DATE NOT NULL,
                `tanggal_berakhir` DATE NOT NULL,
                `status` ENUM('pending', 'active', 'expired', 'cancelled') DEFAULT 'pending',
                `bukti_pembayaran` VARCHAR(255) DEFAULT NULL,
                `hari` ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu') DEFAULT NULL,
                `jam` TIME DEFAULT NULL,
                `lapangan` ENUM('Lapangan 1', 'Lapangan 2', 'Lapangan 3', 'Lapangan 4', 'Lapangan 5', 'Lapangan 6', 'Lapangan 7', 'Lapangan 8') DEFAULT NULL,
                `harga_membership` DECIMAL(10,2) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_user` (`user_id`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Update kolom status jika belum ada 'pending'
            try {
                $checkStatus = $setupPdo->query("SHOW COLUMNS FROM `membership` LIKE 'status'");
                if ($checkStatus->rowCount() > 0) {
                    $statusCol = $checkStatus->fetch(PDO::FETCH_ASSOC);
                    if (strpos($statusCol['Type'], 'pending') === false) {
                        $setupPdo->exec("ALTER TABLE `membership` MODIFY `status` ENUM('pending', 'active', 'expired', 'cancelled') DEFAULT 'pending'");
                    }
                }
            } catch (PDOException $e) {
                // Ignore jika error
            }
            
            // Tambahkan kolom baru jika belum ada
            try {
                $columns = $setupPdo->query("SHOW COLUMNS FROM `membership`")->fetchAll(PDO::FETCH_COLUMN);
                
                if (!in_array('bukti_pembayaran', $columns)) {
                    $setupPdo->exec("ALTER TABLE `membership` ADD COLUMN `bukti_pembayaran` VARCHAR(255) DEFAULT NULL AFTER `status`");
                }
                
                if (!in_array('hari', $columns)) {
                    $setupPdo->exec("ALTER TABLE `membership` ADD COLUMN `hari` ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu') DEFAULT NULL AFTER `bukti_pembayaran`");
                }
                
                if (!in_array('jam', $columns)) {
                    $setupPdo->exec("ALTER TABLE `membership` ADD COLUMN `jam` TIME DEFAULT NULL AFTER `hari`");
                }
                
                if (!in_array('lapangan', $columns)) {
                    $setupPdo->exec("ALTER TABLE `membership` ADD COLUMN `lapangan` ENUM('Lapangan 1', 'Lapangan 2', 'Lapangan 3', 'Lapangan 4', 'Lapangan 5', 'Lapangan 6', 'Lapangan 7', 'Lapangan 8') DEFAULT NULL AFTER `jam`");
                }
            } catch (PDOException $e) {
                // Ignore jika error (kolom mungkin sudah ada)
            }
            
            // Buat tabel membership_schedule
            $setupPdo->exec("CREATE TABLE IF NOT EXISTS `membership_schedule` (
                `schedule_id` INT AUTO_INCREMENT PRIMARY KEY,
                `membership_id` INT NOT NULL,
                `user_id` INT NOT NULL,
                `hari` ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu') NOT NULL,
                `jam` TIME NOT NULL,
                `lapangan` ENUM('Lapangan 1', 'Lapangan 2', 'Lapangan 3', 'Lapangan 4', 'Lapangan 5', 'Lapangan 6', 'Lapangan 7', 'Lapangan 8') NOT NULL,
                `bulan_ke` INT NOT NULL,
                `tahun` INT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_membership` (`membership_id`),
                INDEX `idx_user` (`user_id`),
                INDEX `idx_bulan_tahun` (`bulan_ke`, `tahun`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Buat tabel reservasi (tanpa foreign key dulu untuk menghindari error)
            $setupPdo->exec("CREATE TABLE IF NOT EXISTS `reservasi` (
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
            
            // Buat tabel pembayaran
            $setupPdo->exec("CREATE TABLE IF NOT EXISTS `pembayaran` (
                `pembayaran_id` INT AUTO_INCREMENT PRIMARY KEY,
                `reservasi_id` INT NOT NULL,
                `bukti_pembayaran` VARCHAR(255) DEFAULT NULL,
                `total_bayar` DECIMAL(10,2) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_reservasi` (`reservasi_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Cek dan buat admin user jika belum ada
            $stmt = $setupPdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
            $adminCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($adminCount == 0) {
                $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
                $setupPdo->exec("INSERT INTO users (username, email, password, role) 
                                VALUES ('admin', 'admin@daddiesarena.com', '$adminPassword', 'admin')");
            }
            
            // Sekarang coba koneksi lagi
            $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
        } catch (PDOException $setupError) {
            // Jika setup gagal, return error
            // Pastikan header JSON sudah di-set di awal file
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            http_response_code(500);
            echo json_encode([
                'error' => 'Database setup failed',
                'message' => 'Unable to create database. Please check MySQL is running and you have proper permissions.',
                'detail' => (getenv('APP_ENV') === 'development' ? $setupError->getMessage() : 'Database connection error')
            ]);
            exit;
        }
    } else {
        // Error lain selain database tidak ada
        // Pastikan header JSON sudah di-set di awal file
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        http_response_code(500);
        echo json_encode([
            'error' => 'Database connection failed',
            'message' => 'Unable to connect to database. Please check your configuration.',
            'detail' => (getenv('APP_ENV') === 'development' ? $e->getMessage() : 'Database connection error')
        ]);
        exit;
    }
}
?>
