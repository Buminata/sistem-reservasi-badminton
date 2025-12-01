<?php
/**
 * Helper function untuk memastikan semua tabel ada
 * Dipanggil otomatis jika tabel tidak ditemukan
 */
function ensureTablesExist($conn) {
    try {
        // Buat tabel users jika belum ada
        $conn->exec("CREATE TABLE IF NOT EXISTS `users` (
            `user_id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `no_tlp` VARCHAR(20) DEFAULT NULL,
            `role` VARCHAR(20) DEFAULT 'user',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Tambahkan kolom membership jika belum ada
        try {
            $conn->exec("ALTER TABLE `users` ADD COLUMN `is_member` TINYINT(1) DEFAULT 0");
        } catch (PDOException $e) {
            // Kolom sudah ada, skip
        }
        
        // Buat tabel membership untuk tracking membership
        $conn->exec("CREATE TABLE IF NOT EXISTS `membership` (
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
            $checkStatus = $conn->query("SHOW COLUMNS FROM `membership` LIKE 'status'");
            if ($checkStatus->rowCount() > 0) {
                $statusCol = $checkStatus->fetch(PDO::FETCH_ASSOC);
                if (strpos($statusCol['Type'], 'pending') === false) {
                    $conn->exec("ALTER TABLE `membership` MODIFY `status` ENUM('pending', 'active', 'expired', 'cancelled') DEFAULT 'pending'");
                }
            }
        } catch (PDOException $e) {
            // Ignore jika error
        }
        
        // Tambahkan kolom baru jika belum ada
        try {
            $columns = $conn->query("SHOW COLUMNS FROM `membership`")->fetchAll(PDO::FETCH_COLUMN);
            
            if (!in_array('bukti_pembayaran', $columns)) {
                $conn->exec("ALTER TABLE `membership` ADD COLUMN `bukti_pembayaran` VARCHAR(255) DEFAULT NULL AFTER `status`");
            }
            
            if (!in_array('hari', $columns)) {
                $conn->exec("ALTER TABLE `membership` ADD COLUMN `hari` ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu') DEFAULT NULL AFTER `bukti_pembayaran`");
            }
            
            if (!in_array('jam', $columns)) {
                $conn->exec("ALTER TABLE `membership` ADD COLUMN `jam` TIME DEFAULT NULL AFTER `hari`");
            }
            
            if (!in_array('lapangan', $columns)) {
                $conn->exec("ALTER TABLE `membership` ADD COLUMN `lapangan` ENUM('Lapangan 1', 'Lapangan 2', 'Lapangan 3', 'Lapangan 4', 'Lapangan 5', 'Lapangan 6', 'Lapangan 7', 'Lapangan 8') DEFAULT NULL AFTER `jam`");
            }
        } catch (PDOException $e) {
            // Ignore jika error (kolom mungkin sudah ada)
        }
        
        // Buat tabel membership_schedule untuk tracking jadwal yang sama
        $conn->exec("CREATE TABLE IF NOT EXISTS `membership_schedule` (
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
        
        // Buat tabel reservasi jika belum ada
        $conn->exec("CREATE TABLE IF NOT EXISTS `reservasi` (
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
        
        // Buat tabel pembayaran jika belum ada
        $conn->exec("CREATE TABLE IF NOT EXISTS `pembayaran` (
            `pembayaran_id` INT AUTO_INCREMENT PRIMARY KEY,
            `reservasi_id` INT NOT NULL,
            `bukti_pembayaran` VARCHAR(255) DEFAULT NULL,
            `total_bayar` DECIMAL(10,2) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_reservasi` (`reservasi_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        return true;
    } catch (PDOException $e) {
        error_log('Table creation error: ' . $e->getMessage());
        return false;
    }
}
?>

