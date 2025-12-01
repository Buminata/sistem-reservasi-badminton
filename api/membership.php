<?php
session_start();
header('Content-Type: application/json');

// CORS Headers
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';
require_once 'ensure-tables.php';
require_once 'pricing.php';

try {
    ensureTablesExist($conn);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database setup failed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// GET - Ambil data membership
if ($method === 'GET') {
    if (isset($_GET['user_id'])) {
        // Ambil membership user tertentu
        $user_id = intval($_GET['user_id']);
        
        $stmt = $conn->prepare("
            SELECT m.*, u.username, u.email 
            FROM membership m
            JOIN users u ON m.user_id = u.user_id
            WHERE m.user_id = :user_id
            ORDER BY m.created_at DESC
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $user_id]);
        $membership = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($membership) {
            // Cek apakah masih aktif
            $today = date('Y-m-d');
            if ($membership['tanggal_berakhir'] < $today && $membership['status'] === 'active') {
                // Update status menjadi expired
                $updateStmt = $conn->prepare("UPDATE membership SET status = 'expired' WHERE membership_id = :id");
                $updateStmt->execute(['id' => $membership['membership_id']]);
                $membership['status'] = 'expired';
                
                // Update is_member di users
                $updateUser = $conn->prepare("UPDATE users SET is_member = 0 WHERE user_id = :user_id");
                $updateUser->execute(['user_id' => $user_id]);
                
                // Log notifikasi expired (bisa dikembangkan untuk email atau in-app notification)
                error_log("Membership auto-expired for user_id: " . $user_id);
            }
            
            echo json_encode($membership);
        } else {
            echo json_encode(['status' => 'no_membership']);
        }
    } else {
        // Ambil semua membership (untuk admin)
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        
        $stmt = $conn->query("
            SELECT m.*, u.username, u.email, u.no_tlp
            FROM membership m
            JOIN users u ON m.user_id = u.user_id
            ORDER BY m.created_at DESC
        ");
        $memberships = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($memberships);
    }
}

// POST - Join membership atau Set Schedule
if ($method === 'POST') {
    // Handle FormData untuk upload bukti pembayaran
    if (!empty($_POST)) {
        $input = $_POST;
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
    }
    
    // Jika action adalah set_schedule, handle set jadwal untuk member
    if (isset($input['action']) && $input['action'] === 'set_schedule') {
        // Hanya admin yang bisa set schedule
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        
        if (!isset($input['membership_id']) || !isset($input['hari']) || !isset($input['jam']) || !isset($input['lapangan'])) {
            http_response_code(400);
            echo json_encode(['error' => 'membership_id, hari, jam, dan lapangan required']);
            exit;
        }
        
        $membership_id = intval($input['membership_id']);
        $hari = $input['hari']; // Senin, Selasa, dst
        $jam = $input['jam']; // Format: "10:00" atau "10:00:00"
        $lapangan = $input['lapangan']; // "Lapangan 1", "Lapangan 2", dst
        $nama_reservasi = isset($input['nama_reservasi']) ? $input['nama_reservasi'] : 'Reservasi Member';
        
        // Validasi lapangan
        if (in_array($lapangan, ['1','2','3','4','5','6','7','8'])) {
            $lapangan = 'Lapangan ' . $lapangan;
        }
        if (!in_array($lapangan, ['Lapangan 1', 'Lapangan 2', 'Lapangan 3', 'Lapangan 4', 'Lapangan 5', 'Lapangan 6', 'Lapangan 7', 'Lapangan 8'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Lapangan tidak valid']);
            exit;
        }
        
        // Validasi hari
        $hariValid = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        $hariMySQL = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $hariIndex = array_search($hari, $hariValid);
        if ($hariIndex === false) {
            http_response_code(400);
            echo json_encode(['error' => 'Hari tidak valid']);
            exit;
        }
        
        // Format jam
        if (strlen($jam) == 5) {
            $jam = $jam . ':00';
        }
        
        // Ambil data membership
        $getMember = $conn->prepare("SELECT user_id, tanggal_mulai, tanggal_berakhir FROM membership WHERE membership_id = :id");
        $getMember->execute(['id' => $membership_id]);
        $membership = $getMember->fetch(PDO::FETCH_ASSOC);
        
        if (!$membership) {
            http_response_code(404);
            echo json_encode(['error' => 'Membership not found']);
            exit;
        }
        
        $user_id = $membership['user_id'];
        $tanggal_mulai = $membership['tanggal_mulai'];
        
        // Hapus reservasi member yang sudah ada untuk membership ini (jika ada)
        try {
            $deleteOld = $conn->prepare("
                DELETE FROM reservasi 
                WHERE user_id = :user_id 
                AND nama_reservasi LIKE :pattern
                AND status != 'Dibatalkan'
            ");
            $deleteOld->execute([
                'user_id' => $user_id,
                'pattern' => '%Member%'
            ]);
        } catch (PDOException $e) {
            // Ignore jika error
        }
        
        // Buat 4 reservasi untuk 4 minggu ke depan
        try {
            $conn->beginTransaction();
            
            $reservasiIds = [];
            $tanggalBase = new DateTime($tanggal_mulai);
            
            // Mapping hari Indonesia ke PHP day of week
            // PHP date('w'): 0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday
            // Index: 0=Senin, 1=Selasa, 2=Rabu, 3=Kamis, 4=Jumat, 5=Sabtu, 6=Minggu
            // Mapping: Senin(0) -> Monday(1), Selasa(1) -> Tuesday(2), ..., Minggu(6) -> Sunday(0)
            $targetDayOfWeek = ($hariIndex + 1) % 7; // Convert to PHP format (0=Sunday, 1=Monday, ...)
            
            // Cari hari pertama yang sesuai dengan hari yang dipilih
            $currentDayOfWeek = intval($tanggalBase->format('w')); // 0=Sunday, 1=Monday, ...
            $daysToAdd = ($targetDayOfWeek - $currentDayOfWeek + 7) % 7;
            
            // Jika hari target sudah lewat pada tanggal mulai, set untuk minggu depan
            if ($daysToAdd == 0 && $tanggalBase->format('Y-m-d') < date('Y-m-d')) {
                $daysToAdd = 7;
            }
            
            $tanggalBase->modify("+{$daysToAdd} days");
            
            // Buat 4 reservasi (1x setiap minggu)
            for ($i = 0; $i < 4; $i++) {
                $tanggal = $tanggalBase->format('Y-m-d');
                
                // Cek apakah sudah terbooking
                $checkBooking = $conn->prepare("
                    SELECT * FROM reservasi 
                    WHERE lapangan = :lapangan 
                    AND tanggal = :tanggal 
                    AND jam = :jam
                    AND status != 'Dibatalkan'
                ");
                $checkBooking->execute([
                    'lapangan' => $lapangan,
                    'tanggal' => $tanggal,
                    'jam' => $jam
                ]);
                
                if ($checkBooking->fetch()) {
                    // Jika sudah terbooking, skip dan cari tanggal berikutnya
                    $tanggalBase->modify('+7 days');
                    continue;
                }
                
                // Insert reservasi dengan status langsung "Dikonfirmasi"
                $insertReservasi = $conn->prepare("
                    INSERT INTO reservasi (user_id, lapangan, tanggal, jam, nama_reservasi, status)
                    VALUES (:user_id, :lapangan, :tanggal, :jam, :nama_reservasi, 'Dikonfirmasi')
                ");
                $insertReservasi->execute([
                    'user_id' => $user_id,
                    'lapangan' => $lapangan,
                    'tanggal' => $tanggal,
                    'jam' => $jam,
                    'nama_reservasi' => $nama_reservasi . ' (Minggu ' . ($i + 1) . ')'
                ]);
                
                $reservasiIds[] = $conn->lastInsertId();
                
                // Tambah 7 hari untuk minggu berikutnya
                $tanggalBase->modify('+7 days');
            }
            
            // Simpan jadwal ke membership_schedule
            $bulan = intval($tanggalBase->format('n'));
            $tahun = intval($tanggalBase->format('Y'));
            
            $insertSchedule = $conn->prepare("
                INSERT INTO membership_schedule (membership_id, user_id, hari, jam, lapangan, bulan_ke, tahun)
                VALUES (:membership_id, :user_id, :hari, :jam, :lapangan, :bulan, :tahun)
                ON DUPLICATE KEY UPDATE jam = :jam, lapangan = :lapangan
            ");
            $insertSchedule->execute([
                'membership_id' => $membership_id,
                'user_id' => $user_id,
                'hari' => $hari,
                'jam' => $jam,
                'lapangan' => $lapangan,
                'bulan' => $bulan,
                'tahun' => $tahun
            ]);
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Jadwal berhasil diset dan 4 reservasi telah dibuat',
                'reservasi_ids' => $reservasiIds,
                'count' => count($reservasiIds)
            ]);
        } catch (PDOException $e) {
            $conn->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Gagal membuat reservasi: ' . $e->getMessage()]);
        }
        
        exit;
    }
    
    // Join membership (untuk user) - dengan form pembayaran
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Cek apakah user sudah punya membership aktif atau pending
    $checkStmt = $conn->prepare("
        SELECT * FROM membership 
        WHERE user_id = :user_id AND (status = 'active' OR status = 'pending')
        AND tanggal_berakhir >= CURDATE()
    ");
    $checkStmt->execute(['user_id' => $user_id]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        http_response_code(409);
        echo json_encode(['error' => 'User already has active or pending membership']);
        exit;
    }
    
    // Validasi input
    if (empty($input['hari']) || empty($input['jam']) || empty($input['lapangan'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Hari, jam, dan lapangan harus diisi']);
        exit;
    }
    
    // Validasi lapangan
    $lapangan = $input['lapangan'];
    if (in_array($lapangan, ['1','2','3','4','5','6','7','8'])) {
        $lapangan = 'Lapangan ' . $lapangan;
    }
    if (!in_array($lapangan, ['Lapangan 1', 'Lapangan 2', 'Lapangan 3', 'Lapangan 4', 'Lapangan 5', 'Lapangan 6', 'Lapangan 7', 'Lapangan 8'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Lapangan tidak valid']);
        exit;
    }
    
    // Format jam
    $jam = $input['jam'];
    if (strlen($jam) == 5) {
        $jam = $jam . ':00';
    }
    
    // Validasi hari
    $hariValid = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    if (!in_array($input['hari'], $hariValid)) {
        http_response_code(400);
        echo json_encode(['error' => 'Hari tidak valid']);
        exit;
    }
    
    // Hitung harga membership berdasarkan hari dan jam (harga reservasi - diskon 20%)
    $harga_membership = calculateMembershipPrice($input['hari'], $jam);
    
    // Jika ada harga yang dikirim dari frontend, gunakan yang dikirim (untuk override jika perlu)
    if (isset($input['harga']) && floatval($input['harga']) > 0) {
        $harga_membership = floatval($input['harga']);
    }
    
    // Tanggal mulai hari ini, berakhir 1 bulan kemudian
    $tanggal_mulai = date('Y-m-d');
    $tanggal_berakhir = date('Y-m-d', strtotime('+1 month'));
    
    // Handle bukti pembayaran jika ada
    $bukti_pembayaran = null;
    if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] === UPLOAD_ERR_OK) {
        $targetDir = __DIR__ . "/uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $filename = uniqid('bukti_membership_', true) . '.' . strtolower(pathinfo($_FILES['bukti_pembayaran']['name'], PATHINFO_EXTENSION));
        $targetFile = $targetDir . $filename;
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        $validExtensions = array("jpg", "jpeg", "png");
        
        if (in_array($imageFileType, $validExtensions)) {
            if ($_FILES['bukti_pembayaran']['size'] <= 5000000) {
                if (move_uploaded_file($_FILES['bukti_pembayaran']['tmp_name'], $targetFile)) {
                    $bukti_pembayaran = "uploads/" . $filename;
                }
            }
        }
    }
    
    try {
        // Insert membership dengan status pending
        $stmt = $conn->prepare("
            INSERT INTO membership (user_id, tanggal_mulai, tanggal_berakhir, status, harga_membership, bukti_pembayaran, hari, jam, lapangan)
            VALUES (:user_id, :tanggal_mulai, :tanggal_berakhir, 'pending', :harga, :bukti_pembayaran, :hari, :jam, :lapangan)
        ");
        $stmt->execute([
            'user_id' => $user_id,
            'tanggal_mulai' => $tanggal_mulai,
            'tanggal_berakhir' => $tanggal_berakhir,
            'harga' => $harga_membership,
            'bukti_pembayaran' => $bukti_pembayaran,
            'hari' => $input['hari'],
            'jam' => $jam,
            'lapangan' => $lapangan
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Membership berhasil dibuat. Menunggu konfirmasi admin.',
            'membership_id' => $conn->lastInsertId()
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Gagal membuat membership: ' . $e->getMessage()]);
    }
}

// PUT - Update membership (untuk admin)
if ($method === 'PUT') {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['membership_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'membership_id required']);
        exit;
    }
    
    $membership_id = intval($input['membership_id']);
    $fields = [];
    $params = ['id' => $membership_id];
    
    if (isset($input['status'])) {
        $fields[] = "status = :status";
        $params['status'] = $input['status'];
    }
    if (isset($input['tanggal_berakhir'])) {
        $fields[] = "tanggal_berakhir = :tanggal_berakhir";
        $params['tanggal_berakhir'] = $input['tanggal_berakhir'];
    }
    
    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }
    
    $sql = "UPDATE membership SET " . implode(', ', $fields) . " WHERE membership_id = :id";
    $stmt = $conn->prepare($sql);
    
    // Ambil status lama sebelum update
    $getOldStatus = $conn->prepare("SELECT status, hari, jam, lapangan, user_id, tanggal_mulai FROM membership WHERE membership_id = :id");
    $getOldStatus->execute(['id' => $membership_id]);
    $oldMembership = $getOldStatus->fetch(PDO::FETCH_ASSOC);
    $oldStatus = $oldMembership ? $oldMembership['status'] : null;
    
    if ($stmt->execute($params)) {
        // Jika status diubah menjadi active (konfirmasi dari pending), update is_member di users
        if (isset($input['status']) && $input['status'] === 'active' && $oldStatus !== 'active') {
            $getUser = $conn->prepare("SELECT user_id, hari, jam, lapangan, tanggal_mulai FROM membership WHERE membership_id = :id");
            $getUser->execute(['id' => $membership_id]);
            $membership = $getUser->fetch(PDO::FETCH_ASSOC);
            
            if ($membership) {
                $updateUser = $conn->prepare("UPDATE users SET is_member = 1 WHERE user_id = :user_id");
                $updateUser->execute(['user_id' => $membership['user_id']]);
                
                // Auto-create jadwal reservasi jika hari, jam, dan lapangan sudah ada
                if (!empty($membership['hari']) && !empty($membership['jam']) && !empty($membership['lapangan'])) {
                    try {
                        $hari = $membership['hari'];
                        $jam = $membership['jam'];
                        $lapangan = $membership['lapangan'];
                        $tanggal_mulai = $membership['tanggal_mulai'];
                        $user_id = $membership['user_id'];
                        
                        // Format jam jika perlu
                        if (strlen($jam) == 5) {
                            $jam = $jam . ':00';
                        }
                        
                        // Validasi lapangan
                        if (in_array($lapangan, ['1','2','3','4','5','6','7','8'])) {
                            $lapangan = 'Lapangan ' . $lapangan;
                        }
                        
                        // Validasi hari
                        $hariValid = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
                        $hariIndex = array_search($hari, $hariValid);
                        
                        if ($hariIndex !== false && in_array($lapangan, ['Lapangan 1', 'Lapangan 2', 'Lapangan 3', 'Lapangan 4', 'Lapangan 5', 'Lapangan 6', 'Lapangan 7', 'Lapangan 8'])) {
                            // Cek apakah sudah ada reservasi untuk membership ini
                            $checkExisting = $conn->prepare("
                                SELECT COUNT(*) as count FROM reservasi 
                                WHERE user_id = :user_id 
                                AND nama_reservasi LIKE :pattern
                                AND status != 'Dibatalkan'
                            ");
                            $checkExisting->execute([
                                'user_id' => $user_id,
                                'pattern' => '%Member%'
                            ]);
                            $existingCount = $checkExisting->fetch(PDO::FETCH_ASSOC)['count'];
                            
                            // Hanya buat jadwal jika belum ada
                            if ($existingCount == 0) {
                                $conn->beginTransaction();
                                
                                $reservasiIds = [];
                                $tanggalBase = new DateTime($tanggal_mulai);
                                
                                // Mapping hari Indonesia ke PHP day of week
                                $targetDayOfWeek = ($hariIndex + 1) % 7; // Convert to PHP format (0=Sunday, 1=Monday, ...)
                                
                                // Cari hari pertama yang sesuai dengan hari yang dipilih
                                $currentDayOfWeek = intval($tanggalBase->format('w')); // 0=Sunday, 1=Monday, ...
                                $daysToAdd = ($targetDayOfWeek - $currentDayOfWeek + 7) % 7;
                                
                                // Jika hari target sudah lewat pada tanggal mulai, set untuk minggu depan
                                if ($daysToAdd == 0 && $tanggalBase->format('Y-m-d') < date('Y-m-d')) {
                                    $daysToAdd = 7;
                                }
                                
                                $tanggalBase->modify("+{$daysToAdd} days");
                                
                                // Buat 4 reservasi (1x setiap minggu)
                                for ($i = 0; $i < 4; $i++) {
                                    $tanggal = $tanggalBase->format('Y-m-d');
                                    
                                    // Cek apakah sudah terbooking
                                    $checkBooking = $conn->prepare("
                                        SELECT * FROM reservasi 
                                        WHERE lapangan = :lapangan 
                                        AND tanggal = :tanggal 
                                        AND jam = :jam
                                        AND status != 'Dibatalkan'
                                    ");
                                    $checkBooking->execute([
                                        'lapangan' => $lapangan,
                                        'tanggal' => $tanggal,
                                        'jam' => $jam
                                    ]);
                                    
                                    if ($checkBooking->fetch()) {
                                        // Jika sudah terbooking, skip dan cari tanggal berikutnya
                                        $tanggalBase->modify('+7 days');
                                        continue;
                                    }
                                    
                                    // Insert reservasi dengan status langsung "Dikonfirmasi"
                                    $insertReservasi = $conn->prepare("
                                        INSERT INTO reservasi (user_id, lapangan, tanggal, jam, nama_reservasi, status)
                                        VALUES (:user_id, :lapangan, :tanggal, :jam, :nama_reservasi, 'Dikonfirmasi')
                                    ");
                                    $insertReservasi->execute([
                                        'user_id' => $user_id,
                                        'lapangan' => $lapangan,
                                        'tanggal' => $tanggal,
                                        'jam' => $jam,
                                        'nama_reservasi' => 'Reservasi Member (Minggu ' . ($i + 1) . ')'
                                    ]);
                                    
                                    $reservasiIds[] = $conn->lastInsertId();
                                    
                                    // Tambah 7 hari untuk minggu berikutnya
                                    $tanggalBase->modify('+7 days');
                                }
                                
                                // Simpan jadwal ke membership_schedule
                                $bulan = intval($tanggalBase->format('n'));
                                $tahun = intval($tanggalBase->format('Y'));
                                
                                $insertSchedule = $conn->prepare("
                                    INSERT INTO membership_schedule (membership_id, user_id, hari, jam, lapangan, bulan_ke, tahun)
                                    VALUES (:membership_id, :user_id, :hari, :jam, :lapangan, :bulan, :tahun)
                                    ON DUPLICATE KEY UPDATE jam = :jam, lapangan = :lapangan
                                ");
                                $insertSchedule->execute([
                                    'membership_id' => $membership_id,
                                    'user_id' => $user_id,
                                    'hari' => $hari,
                                    'jam' => $jam,
                                    'lapangan' => $lapangan,
                                    'bulan' => $bulan,
                                    'tahun' => $tahun
                                ]);
                                
                                $conn->commit();
                                
                                // Log success (optional)
                                error_log("Auto-created " . count($reservasiIds) . " reservations for membership_id: " . $membership_id);
                            }
                        }
                    } catch (PDOException $e) {
                        // Rollback jika ada error
                        if ($conn->inTransaction()) {
                            $conn->rollBack();
                        }
                        // Log error tapi jangan gagalkan update status
                        error_log("Error auto-creating schedule for membership_id " . $membership_id . ": " . $e->getMessage());
                    }
                }
            }
        } else if (isset($input['status']) && $input['status'] !== 'active') {
            // Jika status diubah dari active ke selain active, update is_member ke 0
            $getUser = $conn->prepare("SELECT user_id FROM membership WHERE membership_id = :id");
            $getUser->execute(['id' => $membership_id]);
            $membership = $getUser->fetch(PDO::FETCH_ASSOC);
            
            if ($membership) {
                $updateUser = $conn->prepare("UPDATE users SET is_member = 0 WHERE user_id = :user_id");
                $updateUser->execute(['user_id' => $membership['user_id']]);
            }
        }
        
        // Jika status diubah menjadi expired, kirim notifikasi ke user
        if (isset($input['status']) && $input['status'] === 'expired') {
            $getUser = $conn->prepare("SELECT m.user_id, u.email, u.username FROM membership m JOIN users u ON m.user_id = u.user_id WHERE m.membership_id = :id");
            $getUser->execute(['id' => $membership_id]);
            $membershipData = $getUser->fetch(PDO::FETCH_ASSOC);
            
            if ($membershipData) {
                // Simpan notifikasi expired ke tabel notifikasi (jika ada) atau log
                // Bisa dikembangkan untuk email notification
                error_log("Membership expired notification for user_id: " . $membershipData['user_id'] . ", email: " . $membershipData['email']);
                
                // Buat notifikasi untuk user (bisa ditampilkan di frontend)
                // Untuk sekarang, kita akan membuat sistem notifikasi sederhana
            }
        }
        
        // Jika status diubah menjadi cancelled, update is_member
        if (isset($input['status']) && $input['status'] === 'cancelled') {
            $getUser = $conn->prepare("SELECT user_id FROM membership WHERE membership_id = :id");
            $getUser->execute(['id' => $membership_id]);
            $membership = $getUser->fetch(PDO::FETCH_ASSOC);
            
            if ($membership) {
                $updateUser = $conn->prepare("UPDATE users SET is_member = 0 WHERE user_id = :user_id");
                $updateUser->execute(['user_id' => $membership['user_id']]);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Membership updated']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update']);
    }
}

// DELETE - Hapus membership (untuk admin)
if ($method === 'DELETE') {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['membership_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'membership_id required']);
        exit;
    }
    
    $membership_id = intval($input['membership_id']);
    
    try {
        // Ambil user_id sebelum delete untuk update is_member
        $getUser = $conn->prepare("SELECT user_id FROM membership WHERE membership_id = :id");
        $getUser->execute(['id' => $membership_id]);
        $membership = $getUser->fetch(PDO::FETCH_ASSOC);
        
        if (!$membership) {
            http_response_code(404);
            echo json_encode(['error' => 'Membership not found']);
            exit;
        }
        
        // Ambil status sebelum delete untuk update is_member
        $getStatus = $conn->prepare("SELECT status, bukti_pembayaran FROM membership WHERE membership_id = :id");
        $getStatus->execute(['id' => $membership_id]);
        $membershipData = $getStatus->fetch(PDO::FETCH_ASSOC);
        
        // Hapus bukti pembayaran jika ada
        if ($membershipData && $membershipData['bukti_pembayaran']) {
            $file_path = __DIR__ . '/' . $membershipData['bukti_pembayaran'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Hapus membership_schedule terkait
        $deleteSchedule = $conn->prepare("DELETE FROM membership_schedule WHERE membership_id = :id");
        $deleteSchedule->execute(['id' => $membership_id]);
        
        // Hapus membership
        $deleteStmt = $conn->prepare("DELETE FROM membership WHERE membership_id = :id");
        $deleteStmt->execute(['id' => $membership_id]);
        
        // Update is_member di users jika membership yang dihapus adalah active
        if ($membershipData && $membershipData['status'] === 'active') {
            $updateUser = $conn->prepare("UPDATE users SET is_member = 0 WHERE user_id = :user_id");
            $updateUser->execute(['user_id' => $membership['user_id']]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Membership berhasil dihapus']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Gagal menghapus membership: ' . $e->getMessage()]);
    }
}

// DELETE - Hapus membership (untuk admin)
if ($method === 'DELETE') {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['membership_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'membership_id required']);
        exit;
    }
    
    $membership_id = intval($input['membership_id']);
    
    // Get user_id
    $getUser = $conn->prepare("SELECT user_id FROM membership WHERE membership_id = :id");
    $getUser->execute(['id' => $membership_id]);
    $membership = $getUser->fetch(PDO::FETCH_ASSOC);
    
    if (!$membership) {
        http_response_code(404);
        echo json_encode(['error' => 'Membership not found']);
        exit;
    }
    
    try {
        $conn->beginTransaction();
        
        // Update status menjadi cancelled
        $stmt = $conn->prepare("UPDATE membership SET status = 'cancelled' WHERE membership_id = :id");
        $stmt->execute(['id' => $membership_id]);
        
        // Update is_member di users
        $updateUser = $conn->prepare("UPDATE users SET is_member = 0 WHERE user_id = :user_id");
        $updateUser->execute(['user_id' => $membership['user_id']]);
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Membership cancelled']);
    } catch (PDOException $e) {
        $conn->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to cancel membership']);
    }
}
?>

