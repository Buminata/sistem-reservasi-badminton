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

try {
    ensureTablesExist($conn);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database setup failed']);
    exit;
}

// Hanya admin yang bisa akses notifikasi
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// GET - Ambil notifikasi baru
if ($method === 'GET') {
    $lastCheck = isset($_GET['last_check']) ? $_GET['last_check'] : date('Y-m-d H:i:s', strtotime('-1 hour'));
    
    try {
        $notifications = [];
        
        // Cek reservasi baru (status masih "Menunggu Konfirmasi")
        $stmtReservasi = $conn->prepare("
            SELECT r.reservasi_id, r.nama_reservasi, r.lapangan, r.tanggal, r.jam, r.created_at,
                   u.username, u.email
            FROM reservasi r
            JOIN users u ON r.user_id = u.user_id
            WHERE r.status = 'Menunggu Konfirmasi'
            AND r.created_at > :last_check
            ORDER BY r.created_at DESC
            LIMIT 10
        ");
        $stmtReservasi->execute(['last_check' => $lastCheck]);
        $reservasiBaru = $stmtReservasi->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($reservasiBaru as $res) {
            $notifications[] = [
                'type' => 'reservasi',
                'id' => $res['reservasi_id'],
                'title' => 'Reservasi Baru',
                'message' => "{$res['username']} membuat reservasi untuk {$res['lapangan']} pada " . 
                            date('d/m/Y', strtotime($res['tanggal'])) . " jam " . 
                            date('H:i', strtotime($res['jam'])),
                'data' => $res,
                'created_at' => $res['created_at']
            ];
        }
        
        // Cek membership baru
        $stmtMembership = $conn->prepare("
            SELECT m.membership_id, m.tanggal_mulai, m.tanggal_berakhir, m.created_at,
                   u.username, u.email
            FROM membership m
            JOIN users u ON m.user_id = u.user_id
            WHERE m.status = 'active'
            AND m.created_at > :last_check
            ORDER BY m.created_at DESC
            LIMIT 10
        ");
        $stmtMembership->execute(['last_check' => $lastCheck]);
        $membershipBaru = $stmtMembership->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($membershipBaru as $mem) {
            $notifications[] = [
                'type' => 'membership',
                'id' => $mem['membership_id'],
                'title' => 'Membership Baru',
                'message' => "{$mem['username']} bergabung menjadi member",
                'data' => $mem,
                'created_at' => $mem['created_at']
            ];
        }
        
        // Sort by created_at descending
        usort($notifications, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'count' => count($notifications),
            'last_check' => date('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// POST - Mark notifikasi sebagai sudah dibaca (opsional, untuk future use)
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Bisa digunakan untuk mark notifikasi sebagai read di future
    echo json_encode(['success' => true, 'message' => 'Notifikasi ditandai sebagai dibaca']);
}
?>

