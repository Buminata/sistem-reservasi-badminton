<?php
session_start();
header('Content-Type: application/json');

// Cek apakah user_id ada di sesi
if (isset($_SESSION['user_id'])) {
    echo json_encode([
        'loggedIn' => true,
        'user_id' => $_SESSION['user_id'],
        'role' => $_SESSION['role'] ?? 'user'
    ]);
} else {
    echo json_encode(['loggedIn' => false]);
}
?>
