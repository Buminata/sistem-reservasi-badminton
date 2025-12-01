<?php
session_start(); // Memulai session
header('Content-Type: application/json'); // Mengatur response menjadi JSON

// Cek apakah user_id ada di sesi
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Anda harus login terlebih dahulu']);
    exit;
}

// Handle error jika db.php gagal
if (!@include 'db.php') {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Include pricing helper
require_once 'pricing.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        // Cek apakah tabel reservasi ada
        $checkTable = $conn->query("SHOW TABLES LIKE 'reservasi'");
        if ($checkTable->rowCount() == 0) {
            echo json_encode([]);
            exit;
        }
        
        // Jika ada ID yang dikirimkan dalam query string, ambil reservasi berdasarkan ID
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $sql = "SELECT * FROM reservasi WHERE reservasi_id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['id' => $id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($data ?: []);
        } else {
            // Ambil semua reservasi
            $sql = "SELECT * FROM reservasi ORDER BY tanggal DESC, jam DESC";
            $stmt = $conn->query($sql);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($result);
        }
    } catch (PDOException $e) {
        // Jika tabel tidak ada, return empty array
        if (strpos($e->getMessage(), "doesn't exist") !== false || 
            strpos($e->getMessage(), "1146") !== false) {
            echo json_encode([]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Database error']);
        }
        exit;
    }
} elseif ($method === 'POST') {
    // Proses POST untuk memasukkan reservasi baru
    $input = json_decode(file_get_contents('php://input'), true);

    // Validasi input
    if (empty($input['name']) || empty($input['tanggal']) || empty($input['jam']) || empty($input['lapangan'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Semua field harus diisi!']);
        exit;
    }
    
    // Validasi jam harus array atau string yang valid
    if (empty($input['jam']) || (is_array($input['jam']) && count($input['jam']) === 0)) {
        http_response_code(400);
        echo json_encode(['error' => 'Minimal pilih satu jam!']);
        exit;
    }

    // Mendapatkan data dari input JSON
    $name = $input['name'];
    $tanggal = $input['tanggal'];
    $lapangan = $input['lapangan'];

    // Ubah angka lapangan menjadi string sesuai ENUM di DB
    if (in_array($lapangan, ['1','2','3','4','5','6','7','8'])) {
        $lapangan = 'Lapangan ' . $lapangan;
    }

    // Ubah jam menjadi array jika dikirim dalam bentuk JSON string
    $jamList = $input['jam'];
    if (is_string($jamList)) {
        $jamList = json_decode($jamList, true);
    }
    if (!is_array($jamList)) {
        $jamList = [$jamList];
    }

    // Pastikan tabel reservasi ada
    try {
        $checkTable = $conn->query("SHOW TABLES LIKE 'reservasi'");
        if ($checkTable->rowCount() == 0) {
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
        }
    } catch (PDOException $e) {
        // Ignore jika tabel sudah ada
    }
    
    // Cek apakah user adalah member aktif
    $isMember = false;
    $membershipId = null;
    try {
        $memberCheck = $conn->prepare("
            SELECT m.* FROM membership m
            JOIN users u ON m.user_id = u.user_id
            WHERE m.user_id = :user_id 
            AND m.status = 'active' 
            AND m.tanggal_berakhir >= CURDATE()
            ORDER BY m.created_at DESC
            LIMIT 1
        ");
        $memberCheck->execute(['user_id' => $_SESSION['user_id']]);
        $membership = $memberCheck->fetch(PDO::FETCH_ASSOC);
        if ($membership) {
            $isMember = true;
            $membershipId = $membership['membership_id'];
        }
    } catch (PDOException $e) {
        // Tabel membership belum ada atau error, anggap bukan member
    }
    
    // Jika member, validasi hari dan jam yang sama maksimal 4x dalam sebulan
    if ($isMember) {
        // Ambil jam pertama untuk validasi
        $jamFirst = trim(explode('-', $jamList[0])[0]);
        if (strlen($jamFirst) == 5) {
            $jamFirst = $jamFirst . ':00';
        }
        
        // DAYNAME() di MySQL mengembalikan nama hari dalam bahasa Inggris
        $dayNamesMySQL = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $dayOfWeekMySQL = $dayNamesMySQL[date('w', strtotime($tanggal))];
        $bulan = date('n', strtotime($tanggal));
        $tahun = date('Y', strtotime($tanggal));
        
        // Hitung berapa kali user sudah booking hari dan jam yang sama di bulan ini
        try {
            $countStmt = $conn->prepare("
                SELECT COUNT(*) as count FROM reservasi
                WHERE user_id = :user_id
                AND DAYNAME(tanggal) = :hari
                AND jam = :jam
                AND MONTH(tanggal) = :bulan
                AND YEAR(tanggal) = :tahun
                AND status != 'Dibatalkan'
            ");
            $countStmt->execute([
                'user_id' => $_SESSION['user_id'],
                'hari' => $dayOfWeekMySQL,
                'jam' => $jamFirst,
                'bulan' => $bulan,
                'tahun' => $tahun
            ]);
            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count >= 4) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Anda sudah mencapai batas maksimal 4x booking untuk hari dan jam yang sama dalam sebulan'
                ]);
                exit;
            }
        } catch (PDOException $e) {
            // Jika error, lanjutkan (mungkin tabel belum ada)
        }
    }
    
    $reservasiIds = [];
    foreach ($jamList as $jamText) {
        // Ambil jam awal dari string, misal "10:00 - 11:00" -> "10:00"
        $jam = trim(explode('-', $jamText)[0]);
        // Pastikan format jam adalah HH:MM:SS
        if (strlen($jam) == 5) {
            $jam = $jam . ':00';
        } elseif (strlen($jam) == 8) {
            // Sudah dalam format HH:MM:SS
        } else {
            // Jika format tidak valid, skip
            continue;
        }
        // Cek double booking
        try {
            $sql = "SELECT * FROM reservasi WHERE lapangan = :lapangan AND tanggal = :tanggal AND jam = :jam";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['lapangan' => $lapangan, 'tanggal' => $tanggal, 'jam' => $jam]);
            $existingReservation = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existingReservation) {
                http_response_code(409);
                echo json_encode(['error' => "Lapangan sudah tereservasi pada $jamText."]);
                exit;
            }
        } catch (PDOException $e) {
            // Jika error karena tabel tidak ada, lanjutkan (tabel sudah dibuat di atas)
        }

        // Insert reservasi menggunakan prepared statement
        try {
            $sql = "INSERT INTO reservasi (user_id, lapangan, tanggal, jam, nama_reservasi) 
                    VALUES (:user_id, :lapangan, :tanggal, :jam, :nama_reservasi)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                'user_id' => $_SESSION['user_id'],
                'lapangan' => $lapangan,
                'tanggal' => $tanggal,
                'jam' => $jam,
                'nama_reservasi' => $name
            ]);
            $reservasiIds[] = $conn->lastInsertId();
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Gagal menyimpan reservasi. Silakan coba lagi.']);
            exit;
        }
    }
    
    // Jika tidak ada reservasi yang berhasil dibuat
    if (empty($reservasiIds)) {
        http_response_code(400);
        echo json_encode(['error' => 'Tidak ada reservasi yang berhasil dibuat. Periksa format jam yang dipilih.']);
        exit;
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Reservasi berhasil!',
        'reservasi_id' => count($reservasiIds) === 1 ? $reservasiIds[0] : $reservasiIds // single id atau array
    ]);
} elseif ($method === 'PUT') {
    // Proses PUT untuk memperbarui data reservasi
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['reservasi_id'])) {
        $id = intval($input['reservasi_id']);
        $fields = [];
        
        // Periksa data yang ingin diperbarui dan buat array untuk update
        if (isset($input['lapangan'])) $fields[] = "lapangan = :lapangan";
        if (isset($input['tanggal'])) $fields[] = "tanggal = :tanggal";
        if (isset($input['jam'])) $fields[] = "jam = :jam";
        if (isset($input['status'])) $fields[] = "status = :status";
        
        // Jika tidak ada data yang diubah, kembalikan pesan error
        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Tidak ada data yang diubah']);
            exit;
        }

        // Validasi konflik jika mengubah tanggal, jam, atau lapangan
        if (isset($input['tanggal']) || isset($input['jam']) || isset($input['lapangan'])) {
            // Ambil nilai baru atau nilai lama
            $tanggalBaru = isset($input['tanggal']) ? $input['tanggal'] : null;
            $jamBaru = isset($input['jam']) ? $input['jam'] : null;
            $lapanganBaru = isset($input['lapangan']) ? $input['lapangan'] : null;
            
            // Ambil data reservasi lama
            $getOld = $conn->prepare("SELECT tanggal, jam, lapangan FROM reservasi WHERE reservasi_id = :id");
            $getOld->execute(['id' => $id]);
            $oldData = $getOld->fetch(PDO::FETCH_ASSOC);
            
            if ($oldData) {
                $tanggalCheck = $tanggalBaru ?? $oldData['tanggal'];
                $jamCheck = $jamBaru ?? $oldData['jam'];
                $lapanganCheck = $lapanganBaru ?? $oldData['lapangan'];
                
                // Format jam ke HH:MM:SS jika perlu
                if (strlen($jamCheck) == 5) {
                    $jamCheck = $jamCheck . ':00';
                }
                
                // Validasi lapangan
                if (in_array($lapanganCheck, ['1','2','3','4','5','6','7','8'])) {
                    $lapanganCheck = 'Lapangan ' . $lapanganCheck;
                }
                
                // Cek apakah slot sudah terbooking oleh reservasi lain
                $checkConflict = $conn->prepare("
                    SELECT reservasi_id, nama_reservasi 
                    FROM reservasi 
                    WHERE lapangan = :lapangan 
                    AND tanggal = :tanggal 
                    AND jam = :jam
                    AND reservasi_id != :id
                    AND status != 'Dibatalkan'
                ");
                $checkConflict->execute([
                    'lapangan' => $lapanganCheck,
                    'tanggal' => $tanggalCheck,
                    'jam' => $jamCheck,
                    'id' => $id
                ]);
                
                $conflict = $checkConflict->fetch(PDO::FETCH_ASSOC);
                if ($conflict) {
                    http_response_code(409);
                    echo json_encode([
                        'status' => false, 
                        'message' => 'Slot sudah terbooking oleh reservasi lain: ' . ($conflict['nama_reservasi'] ?? 'Unknown')
                    ]);
                    exit;
                }
            }
        }

        // Bangun query UPDATE
        $sql = "UPDATE reservasi SET " . implode(', ', $fields) . " WHERE reservasi_id = :id";
        
        // Persiapkan dan eksekusi query dengan parameter binding
        $stmt = $conn->prepare($sql);
        
        // Binding parameter
        $params = ['id' => $id];
        if (isset($input['lapangan'])) {
            $lapangan = $input['lapangan'];
            // Validasi format lapangan
            if (in_array($lapangan, ['1','2','3','4','5','6','7','8'])) {
                $lapangan = 'Lapangan ' . $lapangan;
            }
            $params['lapangan'] = $lapangan;
        }
        if (isset($input['tanggal'])) $params['tanggal'] = $input['tanggal'];
        if (isset($input['jam'])) {
            $jam = $input['jam'];
            // Format jam ke HH:MM:SS jika perlu
            if (strlen($jam) == 5) {
                $jam = $jam . ':00';
            }
            $params['jam'] = $jam;
        }
        if (isset($input['status'])) $params['status'] = $input['status'];

        // Eksekusi query
        if ($stmt->execute($params)) {
            // Jika status diubah menjadi "Dikonfirmasi" atau "Konfirmasi", 
            // pastikan total_bayar sudah ada di tabel pembayaran
            if (isset($input['status']) && 
                in_array($input['status'], ['Dikonfirmasi', 'Konfirmasi', 'Sukses', 'Berhasil'])) {
                
                try {
                    // Cek apakah ada pembayaran untuk reservasi ini
                    $checkPayment = $conn->prepare("SELECT * FROM pembayaran WHERE reservasi_id = :id");
                    $checkPayment->execute(['id' => $id]);
                    $payment = $checkPayment->fetch(PDO::FETCH_ASSOC);
                    
                    // Ambil data reservasi untuk menghitung harga
                    $getReservasi = $conn->prepare("SELECT tanggal, jam, user_id FROM reservasi WHERE reservasi_id = :id");
                    $getReservasi->execute(['id' => $id]);
                    $reservasiData = $getReservasi->fetch(PDO::FETCH_ASSOC);
                    
                    if ($reservasiData) {
                        // Cek apakah user adalah member aktif
                        $isMember = false;
                        try {
                            $memberCheck = $conn->prepare("
                                SELECT * FROM membership m
                                WHERE m.user_id = :user_id 
                                AND m.status = 'active' 
                                AND m.tanggal_berakhir >= CURDATE()
                                ORDER BY m.created_at DESC
                                LIMIT 1
                            ");
                            $memberCheck->execute(['user_id' => $reservasiData['user_id']]);
                            $membership = $memberCheck->fetch(PDO::FETCH_ASSOC);
                            if ($membership) {
                                $isMember = true;
                            }
                        } catch (PDOException $e) {
                            // Tabel membership belum ada atau error
                        }
                        
                        // Hitung harga berdasarkan hari dan jam
                        $jamList = [$reservasiData['jam']];
                        $calculatedAmount = calculateTotalPrice($reservasiData['tanggal'], $jamList, $isMember);
                    } else {
                        // Fallback jika reservasi tidak ditemukan
                        $calculatedAmount = 80000.00;
                    }
                    
                    if ($payment) {
                        // Jika total_bayar masih NULL atau 0, set calculated amount
                        if ($payment['total_bayar'] === null || $payment['total_bayar'] == 0 || empty($payment['total_bayar'])) {
                            $updatePayment = $conn->prepare("UPDATE pembayaran SET total_bayar = :total WHERE reservasi_id = :id");
                            $updatePayment->execute(['total' => $calculatedAmount, 'id' => $id]);
                        }
                    } else {
                        // Jika belum ada pembayaran, buat record pembayaran dengan calculated amount
                        // Pastikan tabel pembayaran ada
                        $checkTable = $conn->query("SHOW TABLES LIKE 'pembayaran'");
                        if ($checkTable->rowCount() == 0) {
                            $conn->exec("CREATE TABLE IF NOT EXISTS `pembayaran` (
                                `pembayaran_id` INT AUTO_INCREMENT PRIMARY KEY,
                                `reservasi_id` INT NOT NULL,
                                `bukti_pembayaran` VARCHAR(255) DEFAULT NULL,
                                `total_bayar` DECIMAL(10,2) DEFAULT NULL,
                                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                INDEX `idx_reservasi` (`reservasi_id`)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        }
                        
                        $insertPayment = $conn->prepare("INSERT INTO pembayaran (reservasi_id, total_bayar) VALUES (:id, :total)");
                        $insertPayment->execute(['id' => $id, 'total' => $calculatedAmount]);
                    }
                } catch (PDOException $e) {
                    // Jika error, tetap lanjutkan (tidak critical)
                    error_log("Error updating payment on confirmation: " . $e->getMessage());
                }
            }
            
            echo json_encode(['status' => true, 'message' => 'Reservasi diperbarui']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => false, 'message' => 'Gagal update']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'ID reservasi wajib']);
    }
} elseif ($method === 'DELETE') {
    // Proses DELETE untuk menghapus data reservasi
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['reservasi_id'])) {
        $id = intval($input['reservasi_id']);
        
        // Cek apakah reservasi ada
        $sql = "SELECT * FROM reservasi WHERE reservasi_id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['id' => $id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reservation) {
            http_response_code(404);
            echo json_encode(['status' => false, 'message' => 'Reservasi tidak ditemukan']);
            exit;
        }

        // Hapus reservasi
        $sql = "DELETE FROM reservasi WHERE reservasi_id = :id";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute(['id' => $id])) {
            echo json_encode(['status' => true, 'message' => 'Reservasi berhasil dihapus']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => false, 'message' => 'Gagal menghapus reservasi']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'ID reservasi wajib']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Metode tidak didukung']);
}
?>
