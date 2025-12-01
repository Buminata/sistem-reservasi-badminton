<?php
session_start();
header('Content-Type: application/json');

// Handle error jika db.php gagal
if (!@include 'db.php') {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Anda harus login terlebih dahulu']);
    exit;
}

try {
    // Cek apakah tabel reservasi ada
    $checkTable = $conn->query("SHOW TABLES LIKE 'reservasi'");
    if ($checkTable->rowCount() == 0) {
        echo json_encode([]);
        exit;
    }
    
    $sql = "SELECT tanggal, jam, lapangan, nama_reservasi, status FROM reservasi WHERE user_id = :user_id ORDER BY tanggal DESC, jam DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    $riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($riwayat);
} catch (PDOException $e) {
    // Jika tabel tidak ada, return empty array
    if (strpos($e->getMessage(), "doesn't exist") !== false || 
        strpos($e->getMessage(), "1146") !== false) {
        echo json_encode([]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
}
?>
