<?php
/**
 * API untuk mendapatkan notifikasi membership untuk user
 * Khusus untuk notifikasi expired membership
 */
session_start();
header('Content-Type: application/json');

// CORS Headers
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

// Hanya user yang login yang bisa akses
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// GET - Cek apakah ada membership yang expired
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Cek membership yang baru expired (dalam 7 hari terakhir)
        $stmt = $conn->prepare("
            SELECT membership_id, tanggal_berakhir, status
            FROM membership
            WHERE user_id = :user_id
            AND status = 'expired'
            AND tanggal_berakhir >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY tanggal_berakhir DESC
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $user_id]);
        $expiredMembership = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($expiredMembership) {
            echo json_encode([
                'has_notification' => true,
                'message' => 'Membership Anda telah kedaluwarsa pada ' . date('d/m/Y', strtotime($expiredMembership['tanggal_berakhir'])),
                'type' => 'expired',
                'membership_id' => $expiredMembership['membership_id']
            ]);
        } else {
            echo json_encode([
                'has_notification' => false
            ]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>

