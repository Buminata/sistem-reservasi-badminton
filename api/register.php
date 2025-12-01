<?php
// register-api.php
header('Content-Type: application/json');  // Menyatakan bahwa response adalah JSON

// Handle error jika db.php gagal
if (!@include 'db.php') {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Fungsi untuk menghasilkan UUID
function generateUUID() {
    // Menghasilkan UUID berbasis timestamp dan karakter acak
    return strtoupper(bin2hex(random_bytes(16)));  // UUID 32 karakter
}

// Mendapatkan data JSON yang dikirimkan melalui POST
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

// Validasi JSON decode
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON format']);
    exit;
}

// Validasi input
if (empty($data) || empty($data['username']) || empty($data['email']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Username, email, dan password harus diisi!']);
    exit;
}

// Mendapatkan data dari JSON
$username = trim($data['username']);
$email = trim($data['email']);
$password = $data['password'];
$no_tlp = isset($data['no_tlp']) ? trim($data['no_tlp']) : '';  // Menambahkan nomor telepon (optional)
$role = isset($data['role']) && $data['role'] !== false ? $data['role'] : 'user';  // Default ke 'user' jika tidak ada role yang diberikan

// Memeriksa apakah email sudah terdaftar
try {
    $sql = "SELECT * FROM users WHERE email = :email";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        http_response_code(409);
        echo json_encode(['error' => 'Email sudah terdaftar!']);
        exit;
    }

    // Memeriksa apakah nomor telepon sudah terdaftar (jika diisi)
    if (!empty($no_tlp)) {
        $sql = "SELECT * FROM users WHERE no_tlp = :no_tlp";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['no_tlp' => $no_tlp]);
        $phoneCheck = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($phoneCheck) {
            http_response_code(409);
            echo json_encode(['error' => 'Nomor telepon sudah terdaftar!']);
            exit;
        }
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Hash password sebelum disimpan
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Generate UUID untuk user_id atau gunakan AUTO_INCREMENT jika user_id adalah INT
// Cek struktur tabel - jika user_id adalah INT, tidak perlu generate UUID
try {
    // Coba insert dengan AUTO_INCREMENT terlebih dahulu
    $sql = "INSERT INTO users (username, email, password, no_tlp, role) 
            VALUES (:username, :email, :password, :no_tlp, :role)";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        'username' => $username,
        'email' => $email,
        'password' => $hashedPassword,
        'no_tlp' => $no_tlp ?: null,
        'role' => $role
    ]);

    if ($result) {
        // Mengembalikan response sukses
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Pendaftaran berhasil!'
        ]);
    } else {
        throw new PDOException('Insert failed');
    }
} catch (PDOException $e) {
    // Jika error karena user_id, coba dengan UUID
    if (strpos($e->getMessage(), 'user_id') !== false || strpos($e->getMessage(), 'Column') !== false) {
        try {
            $user_id = generateUUID();
            $sql = "INSERT INTO users (user_id, username, email, password, no_tlp, role) 
                    VALUES (:user_id, :username, :email, :password, :no_tlp, :role)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                'user_id' => $user_id,
                'username' => $username,
                'email' => $email,
                'password' => $hashedPassword,
                'no_tlp' => $no_tlp ?: null,
                'role' => $role
            ]);
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Pendaftaran berhasil!'
            ]);
        } catch (PDOException $e2) {
            http_response_code(500);
            error_log('Register UUID error: ' . $e2->getMessage());
            echo json_encode(['error' => 'Terjadi kesalahan saat registrasi. Silakan coba lagi.']);
        }
    } else {
        http_response_code(500);
        $errorMsg = 'Terjadi kesalahan saat registrasi.';
        // Jangan expose error detail ke user, tapi log untuk debugging
        error_log('Register error: ' . $e->getMessage());
        echo json_encode(['error' => $errorMsg]);
    }
}
?>
