<?php
header('Content-Type: application/json'); // Mengatur response menjadi JSON

// Handle error jika db.php gagal
if (!@require_once 'db.php') {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Memastikan hanya metode GET yang diterima
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    try {
        // Total reservasi (dengan error handling jika tabel tidak ada)
        try {
            $q1 = $conn->query("SELECT COUNT(*) as total FROM reservasi");
            $total_reservasi = $q1->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        } catch (PDOException $e) {
            $total_reservasi = 0;
        }

        // Total pembayaran masuk (hanya dari reservasi yang sudah dikonfirmasi)
        try {
            $q2 = $conn->query("
                SELECT SUM(p.total_bayar) as total 
                FROM pembayaran p
                INNER JOIN reservasi r ON p.reservasi_id = r.reservasi_id
                WHERE r.status IN ('Dikonfirmasi', 'Konfirmasi', 'Sukses', 'Berhasil')
                AND p.total_bayar IS NOT NULL
            ");
            $total_pembayaran = $q2->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        } catch (PDOException $e) {
            $total_pembayaran = 0;
        }

        // Total pengguna terdaftar
        try {
            $q3 = $conn->query("SELECT COUNT(*) as total FROM users");
            $total_user = $q3->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        } catch (PDOException $e) {
            $total_user = 0;
        }

        // Lapangan digunakan hari ini
        $tgl = date('Y-m-d');
        try {
            $q4 = $conn->prepare("SELECT COUNT(DISTINCT lapangan) as total FROM reservasi WHERE tanggal = :tanggal");
            $q4->execute(['tanggal' => $tgl]);
            $lapangan_hari_ini = $q4->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        } catch (PDOException $e) {
            $lapangan_hari_ini = 0;
        }
    } catch (Exception $e) {
        // Jika semua query gagal, return default values
        $total_reservasi = 0;
        $total_pembayaran = 0;
        $total_user = 0;
        $lapangan_hari_ini = 0;
    }

    // Mengembalikan hasil dalam format JSON
    echo json_encode([
        'total_reservasi' => (int)$total_reservasi,
        'total_pembayaran' => (float)$total_pembayaran,
        'total_user' => (int)$total_user,
        'lapangan_hari_ini' => (int)$lapangan_hari_ini
    ]);
} else {
    // Jika bukan metode GET, kirimkan response error
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Method not allowed, only GET is allowed']);
}
?>
