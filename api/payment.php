<?php
// backend/api/payment.php (Backend PHP untuk mengelola file upload dan data pembayaran)

// Untuk menerima POST, GET, DELETE request
header('Content-Type: application/json');

// Handle error jika db.php gagal
if (!@include_once 'db.php') {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Include pricing helper
require_once 'pricing.php';

$method = $_SERVER['REQUEST_METHOD'];

function uploadImage($image) {
    $targetDir = __DIR__ . "/uploads/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    $filename = uniqid('bukti_', true) . '.' . strtolower(pathinfo($image["name"], PATHINFO_EXTENSION));
    $targetFile = $targetDir . $filename;
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    $validExtensions = array("jpg", "jpeg", "png");

    if (!in_array($imageFileType, $validExtensions)) {
        return ["status" => false, "message" => "Hanya file gambar yang diperbolehkan"];
    }
    if ($image["size"] > 5000000) {
        return ["status" => false, "message" => "File terlalu besar"];
    }
    if (move_uploaded_file($image["tmp_name"], $targetFile)) {
        return ["status" => true, "file_path" => "uploads/" . $filename];
    } else {
        return ["status" => false, "message" => "Terjadi kesalahan saat mengupload gambar"];
    }
}

// Handle POST - Upload Bukti Pembayaran
if ($method === 'POST') {
    if (isset($_POST['reservasi_id']) && isset($_FILES['bukti_pembayaran'])) {
        $reservasi_id = is_array($_POST['reservasi_id']) ? $_POST['reservasi_id'][0] : $_POST['reservasi_id'];
        
        // Ambil total_bayar jika ada (opsional, bisa dari input form)
        $total_bayar = null;
        if (isset($_POST['total_bayar']) && !empty($_POST['total_bayar'])) {
            $total_bayar = floatval($_POST['total_bayar']);
        } else {
            // Ambil data reservasi untuk menghitung harga
            $reservasi_id = is_array($_POST['reservasi_id']) ? $_POST['reservasi_id'][0] : $_POST['reservasi_id'];
            
            try {
                $getReservasi = $conn->prepare("SELECT tanggal, jam FROM reservasi WHERE reservasi_id = :id");
                $getReservasi->execute(['id' => $reservasi_id]);
                $reservasi = $getReservasi->fetch(PDO::FETCH_ASSOC);
                
                if ($reservasi) {
                    // Cek apakah user adalah member aktif untuk diskon 20%
                    $isMember = false;
                    if (isset($_SESSION['user_id'])) {
                        try {
                            $memberCheck = $conn->prepare("
                                SELECT * FROM membership m
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
                            }
                        } catch (PDOException $e) {
                            // Tabel membership belum ada atau error
                        }
                    }
                    
                    // Hitung harga berdasarkan hari dan jam
                    $jamList = [$reservasi['jam']];
                    $total_bayar = calculateTotalPrice($reservasi['tanggal'], $jamList, $isMember);
                } else {
                    // Fallback jika reservasi tidak ditemukan
                    $total_bayar = 80000.00; // Default harga
                }
            } catch (PDOException $e) {
                // Fallback jika error
                $total_bayar = 80000.00; // Default harga
            }
        }

        // Upload gambar bukti pembayaran
        $uploadResult = uploadImage($_FILES['bukti_pembayaran']);
        if (!$uploadResult['status']) {
            echo json_encode($uploadResult);
            exit;
        }

        try {
            // Pastikan tabel pembayaran ada
            $checkTable = $conn->query("SHOW TABLES LIKE 'pembayaran'");
            if ($checkTable->rowCount() == 0) {
                // Buat tabel pembayaran
                $conn->exec("CREATE TABLE IF NOT EXISTS `pembayaran` (
                    `pembayaran_id` INT AUTO_INCREMENT PRIMARY KEY,
                    `reservasi_id` INT NOT NULL,
                    `bukti_pembayaran` VARCHAR(255) DEFAULT NULL,
                    `total_bayar` DECIMAL(10,2) DEFAULT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_reservasi` (`reservasi_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
            
            // Cek apakah sudah ada pembayaran untuk reservasi ini
            $checkPayment = $conn->prepare("SELECT * FROM pembayaran WHERE reservasi_id = :reservasi_id");
            $checkPayment->execute(['reservasi_id' => $reservasi_id]);
            $existingPayment = $checkPayment->fetch(PDO::FETCH_ASSOC);
            
            if ($existingPayment) {
                // Update pembayaran yang sudah ada
                $query = "UPDATE pembayaran SET bukti_pembayaran = :bukti_pembayaran, total_bayar = :total_bayar WHERE reservasi_id = :reservasi_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':reservasi_id', $reservasi_id, PDO::PARAM_INT);
                $stmt->bindParam(':bukti_pembayaran', $uploadResult['file_path'], PDO::PARAM_STR);
                $stmt->bindParam(':total_bayar', $total_bayar, PDO::PARAM_STR);
                $stmt->execute();
            } else {
                // Insert pembayaran baru
                $query = "INSERT INTO pembayaran (reservasi_id, bukti_pembayaran, total_bayar) VALUES (:reservasi_id, :bukti_pembayaran, :total_bayar)";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':reservasi_id', $reservasi_id, PDO::PARAM_INT);
                $stmt->bindParam(':bukti_pembayaran', $uploadResult['file_path'], PDO::PARAM_STR);
                $stmt->bindParam(':total_bayar', $total_bayar, PDO::PARAM_STR);
                $stmt->execute();
            }

            echo json_encode([
                "status" => true,
                "message" => "Pembayaran berhasil diajukan",
                "file_path" => $uploadResult['file_path']
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                "status" => false,
                "message" => "Gagal menginput pembayaran: " . $e->getMessage()
            ]);
        }
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Data tidak lengkap. Pastikan reservasi_id dan bukti_pembayaran ada"
        ]);
    }
}

// Handle GET - Ambil bukti pembayaran berdasarkan reservasi_id
elseif ($method === 'GET') {
    try {
        // Cek apakah tabel pembayaran ada
        $checkTable = $conn->query("SHOW TABLES LIKE 'pembayaran'");
        if ($checkTable->rowCount() == 0) {
            echo json_encode(["status" => false, "message" => "Pembayaran tidak ditemukan"]);
            exit;
        }
        
        if (isset($_GET['reservasi_id'])) {
            $reservasi_id = $_GET['reservasi_id'];
            
            $sql = "SELECT * FROM pembayaran WHERE reservasi_id = :reservasi_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['reservasi_id' => $reservasi_id]);
            $pembayaran = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($pembayaran) {
                echo json_encode($pembayaran);
            } else {
                echo json_encode(["status" => false, "message" => "Pembayaran tidak ditemukan"]);
            }
        } else {
            echo json_encode(["status" => false, "message" => "reservasi_id tidak ditemukan"]);
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => false, "message" => "Pembayaran tidak ditemukan"]);
    }
}

// Handle DELETE - Hapus bukti pembayaran berdasarkan ID
elseif ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['reservasi_id'])) {
        $reservasi_id = $input['reservasi_id'];

        // Cek apakah pembayaran ada
        $sql = "SELECT * FROM pembayaran WHERE reservasi_id = :reservasi_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['reservasi_id' => $reservasi_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            http_response_code(404);
            echo json_encode(['status' => false, 'message' => 'Pembayaran tidak ditemukan']);
            exit;
        }

        // Hapus file gambar bukti pembayaran
        $file_path = __DIR__ . '/' . $payment['bukti_pembayaran'];
        if (file_exists($file_path)) {
            unlink($file_path); // Menghapus file gambar
        }

        // Hapus data pembayaran dari database
        $sql = "DELETE FROM pembayaran WHERE reservasi_id = :reservasi_id";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute(['reservasi_id' => $reservasi_id])) {
            echo json_encode(['status' => true, 'message' => 'Bukti pembayaran berhasil dihapus']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => false, 'message' => 'Gagal menghapus bukti pembayaran']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'ID reservasi wajib']);
    }
}
?>
