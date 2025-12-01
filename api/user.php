<?php
header('Content-Type: application/json');

// Handle error jika db.php gagal
if (!@require_once 'db.php') {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

function getInput() {
    return json_decode(file_get_contents('php://input'), true);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        // Cek apakah tabel users ada
        $checkTable = $conn->query("SHOW TABLES LIKE 'users'");
        if ($checkTable->rowCount() == 0) {
            echo json_encode([]);
            exit;
        }
        
        // Ambil semua user atau user tertentu jika ada ?id=
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT user_id, username, email, no_tlp, role, is_member FROM users WHERE user_id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($data ?: []);
        } else {
            $stmt = $conn->query("SELECT user_id, username, email, no_tlp, role, is_member FROM users ORDER BY user_id DESC");
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
} elseif ($method === 'PUT') {
    // Update data user
    $input = getInput();
    if (isset($input['user_id'])) {
        $id = intval($input['user_id']);
        $fields = [];
        $params = [];
        if (isset($input['username'])) {
            $fields[] = "username = ?";
            $params[] = $input['username'];
        }
        if (isset($input['email'])) {
            $fields[] = "email = ?";
            $params[] = $input['email'];
        }
        if (isset($input['no_tlp'])) {
            $fields[] = "no_tlp = ?";
            $params[] = $input['no_tlp'];
        }
        if (isset($input['role'])) {
            $fields[] = "role = ?";
            $params[] = $input['role'];
        }
        if (isset($input['is_member'])) {
            $fields[] = "is_member = ?";
            $params[] = intval($input['is_member']);
        }
        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Tidak ada data diubah']);
            exit;
        }
        $params[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt->execute($params)) {
            // Jika is_member diubah menjadi 1, buat atau aktifkan membership
            if (isset($input['is_member']) && $input['is_member'] == 1) {
                try {
                    // Cek apakah sudah ada membership aktif
                    $checkMember = $conn->prepare("
                        SELECT * FROM membership 
                        WHERE user_id = ? AND status = 'active' 
                        AND tanggal_berakhir >= CURDATE()
                    ");
                    $checkMember->execute([$id]);
                    $existing = $checkMember->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$existing) {
                        // Buat membership baru
                        $tanggal_mulai = date('Y-m-d');
                        $tanggal_berakhir = date('Y-m-d', strtotime('+1 month'));
                        $harga = isset($input['harga_membership']) ? floatval($input['harga_membership']) : 200000.00;
                        
                        $insertMember = $conn->prepare("
                            INSERT INTO membership (user_id, tanggal_mulai, tanggal_berakhir, status, harga_membership)
                            VALUES (?, ?, ?, 'active', ?)
                        ");
                        $insertMember->execute([$id, $tanggal_mulai, $tanggal_berakhir, $harga]);
                    }
                } catch (PDOException $e) {
                    // Tabel membership belum ada, skip
                }
            }
            
            echo json_encode(['status' => true, 'message' => 'User diperbarui']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => false, 'message' => 'Gagal update']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'ID user wajib']);
    }
} elseif ($method === 'DELETE') {
    // Hapus user
    $input = getInput();
    if (isset($input['user_id'])) {
        $id = intval($input['user_id']);
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        if ($stmt->execute([$id])) {
            echo json_encode(['status' => true, 'message' => 'User dihapus']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => false, 'message' => 'Gagal menghapus user']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'ID user wajib']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Metode tidak diizinkan']);
}
?>