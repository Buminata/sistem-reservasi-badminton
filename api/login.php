<?php
// login.php
session_start();  // Mulai sesi PHP
header('Content-Type: application/json');  // Menyatakan bahwa respons adalah JSON

// Handle error jika db.php gagal
if (!@include 'db.php') {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
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
if (empty($data) || empty($data['email']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Email dan password harus diisi!']);
    exit;
}

// Mendapatkan data dari JSON
$email = $data['email'];
$password = $data['password'];

// Memeriksa email dan password di database
try {
    $sql = "SELECT user_id, username, password, role FROM users WHERE email = :email";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Login database error: ' . $e->getMessage());
    echo json_encode(['error' => 'Terjadi kesalahan pada server. Silakan coba lagi.']);
    exit;
}

// Cek apakah user ditemukan
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Email atau password salah!']);
    exit;
}

// Verifikasi password
if (!password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Email atau password salah!']);
    exit;
}

// Menyimpan user_id dan role dalam sesi
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['role'] = $user['role'];

// Mengembalikan response sukses dan data user
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Login berhasil!',
    'user' => [
        'user_id' => $user['user_id'],
        'username' => $user['username'],
        'role' => $user['role'],
    ]
]);
?>
