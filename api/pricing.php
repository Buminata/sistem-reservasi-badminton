<?php
/**
 * Pricing Helper Functions
 * Fungsi untuk menghitung harga reservasi berdasarkan hari dan jam
 */

/**
 * Hitung harga per jam berdasarkan hari dan jam
 * @param string $tanggal Format: Y-m-d
 * @param string $jam Format: H:i atau H:i:s
 * @return float Harga per jam
 */
function calculatePricePerHour($tanggal, $jam) {
    // Parse tanggal untuk mendapatkan hari
    $dayOfWeek = date('w', strtotime($tanggal)); // 0=Sunday, 1=Monday, ..., 6=Saturday
    
    // Parse jam
    $jamInt = intval(substr($jam, 0, 2));
    
    // Sabtu (6) atau Minggu (0)
    if ($dayOfWeek == 0 || $dayOfWeek == 6) {
        // Sabtu dan Minggu: 100rb untuk semua jam (07:00-23:00)
        return 100000.00;
    } else {
        // Senin-Jumat
        if ($jamInt >= 7 && $jamInt < 17) {
            // 07:00-16:59 = 80rb
            return 80000.00;
        } else if ($jamInt >= 17 && $jamInt <= 23) {
            // 17:00-23:59 = 100rb
            return 100000.00;
        } else {
            // Diluar jam operasional, default 80rb
            return 80000.00;
        }
    }
}

/**
 * Hitung total harga untuk reservasi
 * @param string $tanggal Format: Y-m-d
 * @param array $jamList Array of jam (format: H:i atau H:i:s)
 * @param bool $isMember Apakah user adalah member
 * @return float Total harga setelah diskon (jika member)
 */
function calculateTotalPrice($tanggal, $jamList, $isMember = false) {
    $total = 0;
    
    foreach ($jamList as $jam) {
        // Ambil jam awal dari string (jika format "10:00 - 11:00")
        $jamClean = trim(explode('-', $jam)[0]);
        if (strlen($jamClean) == 5) {
            $jamClean = $jamClean . ':00';
        }
        
        $pricePerHour = calculatePricePerHour($tanggal, $jamClean);
        $total += $pricePerHour;
    }
    
    // Jika member, diskon 20%
    if ($isMember) {
        $total = $total * 0.8;
    }
    
    return $total;
}

/**
 * Hitung harga membership berdasarkan hari dan jam
 * @param string $hari Format: Senin, Selasa, Rabu, Kamis, Jumat, Sabtu, Minggu
 * @param string $jam Format: H:i atau H:i:s
 * @return float Harga membership setelah diskon 20%
 */
function calculateMembershipPrice($hari, $jam) {
    // Mapping hari Indonesia ke PHP day of week
    $hariMap = [
        'Senin' => 1,
        'Selasa' => 2,
        'Rabu' => 3,
        'Kamis' => 4,
        'Jumat' => 5,
        'Sabtu' => 6,
        'Minggu' => 0
    ];
    
    if (!isset($hariMap[$hari])) {
        return 200000.00; // Default fallback
    }
    
    $dayOfWeek = $hariMap[$hari];
    
    // Parse jam
    $jamInt = intval(substr($jam, 0, 2));
    
    // Hitung harga dasar berdasarkan hari dan jam
    $basePrice = 0;
    
    // Sabtu (6) atau Minggu (0)
    if ($dayOfWeek == 0 || $dayOfWeek == 6) {
        // Sabtu dan Minggu: 100rb untuk semua jam (07:00-23:00)
        $basePrice = 100000.00;
    } else {
        // Senin-Jumat
        if ($jamInt >= 7 && $jamInt < 17) {
            // 07:00-16:59 = 80rb
            $basePrice = 80000.00;
        } else if ($jamInt >= 17 && $jamInt <= 23) {
            // 17:00-23:59 = 100rb
            $basePrice = 100000.00;
        } else {
            // Diluar jam operasional, default 80rb
            $basePrice = 80000.00;
        }
    }
    
    // Harga membership = (harga dasar × 4) - diskon 20%
    // 4x booking dalam sebulan
    $totalBasePrice = $basePrice * 4;
    
    // Apply diskon 20%
    $membershipPrice = $totalBasePrice * 0.8;
    
    return $membershipPrice;
}
?>

